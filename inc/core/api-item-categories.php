<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - API: Item Categories (v1.0 Production)
 * Endpoints:
 *  - GET  /knx/v1/get-item-categories       (hub_id)
 *  - POST /knx/v1/save-item-category        (hub_id, [id], name, [sort_order])
 *  - POST /knx/v1/reorder-item-category     (hub_id, category_id, move=up|down)
 *  - POST /knx/v1/toggle-item-category      (hub_id, category_id, status=active|inactive)  <-- ya lo tienes, lo exponemos aquí por si aún no existe
 * Tablas:
 *  - Z7E_knx_items_categories (preferred) / legacy Z7E_items_categories
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v1', '/get-item-categories', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_item_categories',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);

    register_rest_route('knx/v1', '/save-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_save_item_category',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);

    register_rest_route('knx/v1', '/reorder-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_reorder_item_category',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);

    // Si ya tienes este endpoint en otro archivo, puedes comentar este bloque:
    register_rest_route('knx/v1', '/toggle-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_toggle_item_category',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

// Use centralized table resolver `knx_items_categories_table()` from `inc/functions/helpers.php`.
// The canonical table name is provided by `knx_table('items_categories')` and
// must be WP-prefixed + 'knx_' (e.g. Z7E_knx_items_categories). Legacy fallbacks
// have been removed intentionally to avoid collisions.

/** JSON helper */
if (!function_exists('knx_json_response')) {
    function knx_json_response($success, $data = [], $status = 200) {
        return new WP_REST_Response(array_merge(['success' => $success], $data), $status);
    }
}

/**
 * GET categories (por hub)
 */
function knx_api_get_item_categories(WP_REST_Request $r) {
    global $wpdb;
    $table = knx_items_categories_table();

    $session = knx_get_session();
    if (!$session) return knx_json_response(false, ['error' => 'unauthorized'], 403);

    $hub_id = intval($r->get_param('hub_id'));
    if (!$hub_id) return knx_json_response(false, ['error' => 'missing_hub_id'], 400);

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT id, hub_id, name, sort_order, status, created_at, updated_at
        FROM {$table}
        WHERE hub_id = %d
        ORDER BY sort_order ASC, id ASC
    ", $hub_id));

    return knx_json_response(true, ['categories' => $rows ?: []]);
}

/**
 * Add/Update category
 */
function knx_api_save_item_category(WP_REST_Request $r) {
    global $wpdb;
    $table = knx_items_categories_table();

    $session = knx_get_session();
    if (!$session) return knx_json_response(false, ['error' => 'unauthorized'], 403);

    $nonce = sanitize_text_field($r->get_param('knx_nonce'));
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $id         = intval($r->get_param('id'));
    $hub_id     = intval($r->get_param('hub_id'));
    $name       = trim(sanitize_text_field($r->get_param('name')));
    $sort_order = intval($r->get_param('sort_order') ?? 0);

    if (!$hub_id || $name === '') {
        return knx_json_response(false, ['error' => 'missing_fields'], 400);
    }

    if ($id > 0) {
        // Update
        $ok = $wpdb->update(
            $table,
            [
                'name'       => $name,
                'sort_order' => $sort_order,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id, 'hub_id' => $hub_id],
            ['%s','%d','%s'],
            ['%d','%d']
        );
        if ($ok === false) return knx_json_response(false, ['error' => 'db_update_error'], 500);
        return knx_json_response(true, ['message' => 'Category updated', 'id' => $id]);
    } else {
        // Insert
        $ok = $wpdb->insert(
            $table,
            [
                'hub_id'     => $hub_id,
                'name'       => $name,
                'sort_order' => $sort_order,
                'status'     => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d','%s','%d','%s','%s','%s']
        );
        if (!$ok) return knx_json_response(false, ['error' => 'db_insert_error'], 500);
        return knx_json_response(true, ['message' => 'Category created', 'id' => $wpdb->insert_id]);
    }
}

/**
 * Reorder category (up/down) dentro del mismo hub
 */
function knx_api_reorder_item_category(WP_REST_Request $r) {
    global $wpdb;
    $table = knx_items_categories_table();

    $session = knx_get_session();
    if (!$session) return knx_json_response(false, ['error' => 'unauthorized'], 403);

    $nonce = sanitize_text_field($r->get_param('knx_nonce'));
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $hub_id      = intval($r->get_param('hub_id'));
    $category_id = intval($r->get_param('category_id'));
    $move        = sanitize_text_field($r->get_param('move'));

    if (!$hub_id || !$category_id || !in_array($move, ['up','down'])) {
        return knx_json_response(false, ['error' => 'invalid_request'], 400);
    }

    $cat = $wpdb->get_row($wpdb->prepare("
        SELECT id, sort_order
        FROM {$table}
        WHERE id=%d AND hub_id=%d
        LIMIT 1
    ", $category_id, $hub_id));
    if (!$cat) return knx_json_response(false, ['error' => 'category_not_found'], 404);

    $operator = $move === 'up' ? '<' : '>';
    $order    = $move === 'up' ? 'DESC' : 'ASC';

    $neighbor = $wpdb->get_row($wpdb->prepare("
        SELECT id, sort_order
        FROM {$table}
        WHERE hub_id=%d AND sort_order {$operator} %d
        ORDER BY sort_order {$order}
        LIMIT 1
    ", $hub_id, $cat->sort_order));

    if (!$neighbor) return knx_json_response(false, ['error' => 'no_neighbor'], 400);

    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->update($table, ['sort_order' => $neighbor->sort_order], ['id' => $cat->id], ['%d'], ['%d']);
        $wpdb->update($table, ['sort_order' => $cat->sort_order], ['id' => $neighbor->id], ['%d'], ['%d']);
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return knx_json_response(false, ['error' => 'transaction_failed'], 500);
    }

    return knx_json_response(true, ['message' => 'Category reordered', 'move' => $move, 'category_id' => $category_id]);
}

/**
 * Toggle category status
 * Si ya lo tenías en otro archivo, borra este o mantenlo igual para evitar duplicidad.
 */
function knx_api_toggle_item_category(WP_REST_Request $r) {
    global $wpdb;
    $table = knx_items_categories_table();

    $session = knx_get_session();
    if (!$session) return knx_json_response(false, ['error' => 'unauthorized'], 403);

    $nonce = sanitize_text_field($r->get_param('knx_nonce'));
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $hub_id      = intval($r->get_param('hub_id'));
    $category_id = intval($r->get_param('category_id'));
    $status      = sanitize_text_field($r->get_param('status'));

    if (!$hub_id || !$category_id || !in_array($status, ['active','inactive'])) {
        return knx_json_response(false, ['error' => 'invalid_request'], 400);
    }

    $ok = $wpdb->update(
        $table,
        ['status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $category_id, 'hub_id' => $hub_id],
        ['%s','%s'],
        ['%d','%d']
    );
    if ($ok === false) return knx_json_response(false, ['error' => 'db_update_error'], 500);

    return knx_json_response(true, ['message' => 'Category status updated', 'status' => $status]);
}
