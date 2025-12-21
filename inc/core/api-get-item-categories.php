<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Get Item Categories (v1.3 Production)
 * ----------------------------------------------------------
 * ✅ 100% REST Real
 * ✅ Returns categories sorted by sort_order ASC
 * ✅ Auto-repair for missing sort_order values
 * ✅ Works with dynamic prefix (Z7E_ / default)
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/get-item-categories', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_item_categories',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

function knx_api_get_item_categories(WP_REST_Request $r) {
    global $wpdb;

    // Resolve categories table using knx naming
    if (function_exists('knx_items_categories_table')) {
        $table = knx_items_categories_table();
    } else {
        $table = knx_items_categories_table();
    }

    $hub_id = intval($r->get_param('hub_id'));
    if (!$hub_id) {
        return knx_json_response(false, ['error' => 'missing_hub_id'], 400);
    }

    // Fetch categories
    $categories = $wpdb->get_results($wpdb->prepare("
        SELECT id, hub_id, name, sort_order, status, created_at, updated_at
        FROM $table
        WHERE hub_id = %d
        ORDER BY sort_order ASC, id ASC
    ", $hub_id));

    // If any sort_order = 0, normalize them
    $needs_fix = false;
    foreach ($categories as $c) {
        if ($c->sort_order == 0) { $needs_fix = true; break; }
    }

    if ($needs_fix) {
        $i = 1;
        foreach ($categories as $c) {
            $wpdb->update($table, ['sort_order' => $i], ['id' => $c->id], ['%d'], ['%d']);
            $c->sort_order = $i;
            $i++;
        }
    }

    return knx_json_response(true, [
        'hub_id'     => $hub_id,
        'total'      => count($categories),
        'categories' => $categories
    ]);
}

/** JSON Response helper */
if (!function_exists('knx_json_response')) {
    function knx_json_response($success, $data = [], $status = 200) {
        return new WP_REST_Response(array_merge(['success' => $success], $data), $status);
    }
}
