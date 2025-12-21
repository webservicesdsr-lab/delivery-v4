<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Reorder Item Category (v1.2 Production)
 * ----------------------------------------------------------
 * ✅ 100% REST Real
 * ✅ Moves categories up/down automatically
 * ✅ Works with dynamic table prefix (Z7E_ / default)
 * ✅ Keeps sort_order sequence consistent
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/reorder-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_reorder_item_category',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

function knx_api_reorder_item_category(WP_REST_Request $r) {
    global $wpdb;

    /** Detect correct table (portable) */
    // Resolve categories table using knx naming
    $table = knx_items_categories_table();

    /** Validate session */
    $session = knx_get_session();
    if (!$session) {
        return knx_json_response(false, ['error' => 'unauthorized'], 403);
    }

    /** Sanitize input */
    $hub_id       = intval($r->get_param('hub_id'));
    $category_id  = intval($r->get_param('category_id'));
    $move         = sanitize_text_field($r->get_param('move'));
    $nonce        = sanitize_text_field($r->get_param('knx_nonce'));

    if (!$hub_id || !$category_id || !in_array($move, ['up', 'down'])) {
        return knx_json_response(false, ['error' => 'invalid_request'], 400);
    }

    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    /** Get current category */
    $current = $wpdb->get_row($wpdb->prepare("
        SELECT id, sort_order 
        FROM $table 
        WHERE id = %d AND hub_id = %d
        LIMIT 1
    ", $category_id, $hub_id));

    if (!$current) {
        return knx_json_response(false, ['error' => 'category_not_found'], 404);
    }

    /** Determine direction */
    $operator = $move === 'up' ? '<' : '>';
    $order    = $move === 'up' ? 'DESC' : 'ASC';

    /** Find the neighboring category */
    $neighbor = $wpdb->get_row($wpdb->prepare("
        SELECT id, sort_order 
        FROM $table 
        WHERE hub_id = %d AND sort_order $operator %d
        ORDER BY sort_order $order
        LIMIT 1
    ", $hub_id, $current->sort_order));

    if (!$neighbor) {
        return knx_json_response(false, ['error' => 'no_neighbor'], 400);
    }

    /** Swap positions inside a transaction */
    $wpdb->query('START TRANSACTION');

    try {
        $wpdb->update(
            $table,
            ['sort_order' => $neighbor->sort_order],
            ['id' => $current->id],
            ['%d'],
            ['%d']
        );

        $wpdb->update(
            $table,
            ['sort_order' => $current->sort_order],
            ['id' => $neighbor->id],
            ['%d'],
            ['%d']
        );

        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return knx_json_response(false, ['error' => 'transaction_failed'], 500);
    }

    /** ✅ After swap, normalize sort_order sequence */
    knx_normalize_category_sort_order($table, $hub_id);

    return knx_json_response(true, [
        'message' => 'Category reordered successfully',
        'moved'   => $move,
        'category_id' => $category_id
    ]);
}

/**
 * ==========================================================
 * Helper: Normalize sort_order sequence
 * ----------------------------------------------------------
 * Ensures categories remain sequential (1,2,3,...)
 * ==========================================================
 */
function knx_normalize_category_sort_order($table, $hub_id) {
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT id FROM $table
        WHERE hub_id = %d
        ORDER BY sort_order ASC, id ASC
    ", $hub_id));

    $i = 1;
    foreach ($rows as $row) {
        $wpdb->update($table, ['sort_order' => $i], ['id' => $row->id], ['%d'], ['%d']);
        $i++;
    }
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
