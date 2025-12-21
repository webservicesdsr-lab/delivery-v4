<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Hub Items CRUD (v3.0 Production)
 * ----------------------------------------------------------
 * ✅ REST Real: get-hub-items, add-hub-item, delete-hub-item
 * ✅ Includes category_name via LEFT JOIN (knx_items_categories)
 * ✅ Compatible with edit-hub-items.js v3.0
 * ✅ Portable prefix (Z7E_ or default)
 * ==========================================================
 */

add_action('rest_api_init', function () {

    // GET items by hub
    register_rest_route('knx/v1', '/get-hub-items', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_hub_items',
        'permission_callback' => '__return_true',
    ]);

    // ADD item
    register_rest_route('knx/v1', '/add-hub-item', [
        'methods'  => 'POST',
        'callback' => 'knx_api_add_hub_item',
        'permission_callback' => '__return_true',
    ]);

    // DELETE item
    register_rest_route('knx/v1', '/delete-hub-item', [
        'methods'  => 'POST',
        'callback' => 'knx_api_delete_hub_item',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * ==========================================================
 * 1️⃣ GET HUB ITEMS
 * ==========================================================
 */
function knx_api_get_hub_items(WP_REST_Request $r) {
    global $wpdb;

    // Canonical KNX tables (no legacy fallbacks). Use central helper so names are consistent.
    $table_items = knx_table('hub_items');
    $table_cats  = knx_table('items_categories');

    $hub_id = intval($r->get_param('hub_id'));
    if (!$hub_id) {
        return knx_json_response(false, ['error' => 'missing_hub_id'], 400);
    }

    $page      = max(1, intval($r->get_param('page') ?: 1));
    $per_page  = 20;
    $offset    = ($page - 1) * $per_page;
    $search    = sanitize_text_field($r->get_param('search') ?? '');

    $where = $wpdb->prepare("WHERE i.hub_id = %d", $hub_id);
    if ($search) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (i.name LIKE %s OR i.description LIKE %s)", $like, $like);
    }

    // ✅ LEFT JOIN to include category name
    $sql = "
        SELECT i.*, c.name AS category_name
        FROM $table_items i
        LEFT JOIN $table_cats c ON i.category_id = c.id
        $where
        ORDER BY i.sort_order ASC, i.created_at DESC
        LIMIT %d OFFSET %d
    ";
    $items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));

    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_items i $where"));
    $pages = ceil($total / $per_page);

    return knx_json_response(true, [
        'items'     => $items,
        'total'     => intval($total),
        'page'      => $page,
        'per_page'  => $per_page,
        'pages'     => $pages
    ]);
}

/**
 * ==========================================================
 * 2️⃣ ADD HUB ITEM
 * ==========================================================
 */
function knx_api_add_hub_item(WP_REST_Request $r) {
    global $wpdb;

    // Use canonical KNX hub items table
    $table_items = knx_table('hub_items');

    $session = knx_get_session();
    if (!$session) {
        return knx_json_response(false, ['error' => 'unauthorized'], 403);
    }

    $hub_id      = intval($r->get_param('hub_id'));
    $category_id = intval($r->get_param('category_id'));
    $name        = sanitize_text_field($r->get_param('name'));
    $desc        = sanitize_textarea_field($r->get_param('description'));
    $price       = floatval($r->get_param('price'));
    $nonce       = sanitize_text_field($r->get_param('knx_nonce'));

    if (!$hub_id || !$category_id || !$name || !$price) {
        return knx_json_response(false, ['error' => 'missing_fields'], 400);
    }

    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    // ✅ Handle image upload
    $image_url = null;
    if (!empty($_FILES['item_image']) && $_FILES['item_image']['size'] > 0) {
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']) . 'knx-items/' . $hub_id . '/';
        $base_url = trailingslashit($upload_dir['baseurl']) . 'knx-items/' . $hub_id . '/';

        if (!file_exists($base_dir)) wp_mkdir_p($base_dir);
        if (!file_exists($base_dir . 'index.html')) file_put_contents($base_dir . 'index.html', '');

        $file = $_FILES['item_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            return knx_json_response(false, ['error' => 'invalid_image_type'], 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('item_', true) . '.' . $ext;
        $target = $base_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $image_url = $base_url . $new_name;
        }
    }

    // ✅ Insert item
    $wpdb->insert($table_items, [
        'hub_id'       => $hub_id,
        'category_id'  => $category_id,
        'name'         => $name,
        'description'  => $desc,
        'price'        => $price,
        'image_url'    => esc_url_raw($image_url),
        'status'       => 'available',
        'sort_order'   => time(),
        'created_at'   => current_time('mysql'),
        'updated_at'   => current_time('mysql'),
    ], ['%d','%d','%s','%s','%f','%s','%s','%d','%s','%s']);

    return knx_json_response(true, [
        'message' => 'Item added successfully',
        'id'      => $wpdb->insert_id,
        'image'   => $image_url
    ]);
}

/**
 * ==========================================================
 * 3️⃣ DELETE HUB ITEM
 * ==========================================================
 */
function knx_api_delete_hub_item(WP_REST_Request $r) {
    global $wpdb;

    // Use canonical KNX hub items table
    $table_items = knx_table('hub_items');

    $hub_id = intval($r->get_param('hub_id'));
    $id     = intval($r->get_param('id'));
    $nonce  = sanitize_text_field($r->get_param('knx_nonce'));

    if (!$hub_id || !$id) {
        return knx_json_response(false, ['error' => 'missing_fields'], 400);
    }
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $deleted = $wpdb->delete($table_items, ['id' => $id, 'hub_id' => $hub_id], ['%d','%d']);
    if ($deleted === false) {
        return knx_json_response(false, ['error' => 'delete_failed'], 500);
    }

    return knx_json_response(true, [
        'message' => 'Item deleted successfully',
        'id'      => $id
    ]);
}

/**
 * ==========================================================
 * Helper: JSON Response
 * ==========================================================
 */
if (!function_exists('knx_json_response')) {
    function knx_json_response($success, $data = [], $status = 200) {
        return new WP_REST_Response(array_merge(['success' => $success], $data), $status);
    }
}
