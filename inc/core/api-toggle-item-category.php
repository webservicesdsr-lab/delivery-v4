<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Toggle Item Category (v1.0 Production)
 * ----------------------------------------------------------
 * ✅ 100% REST Real
 * ✅ Uses knx_edit_hub_nonce for validation
 * ✅ Safe toggle between active/inactive
 * ✅ Portable with dynamic prefix (Z7E_ / default)
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/toggle-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_toggle_item_category',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

function knx_api_toggle_item_category(WP_REST_Request $r) {
    global $wpdb;

    /** Detect correct table (supports Z7E_ prefix) */
    $table = knx_items_categories_table();

    /** Validate session */
    $session = knx_get_session();
    if (!$session) {
        return knx_json_response(false, ['error' => 'unauthorized'], 403);
    }

    /** Sanitize and validate input */
    $hub_id      = intval($r->get_param('hub_id'));
    $category_id = intval($r->get_param('category_id'));
    $status      = sanitize_text_field($r->get_param('status'));
    $nonce       = sanitize_text_field($r->get_param('knx_nonce'));

    if (!$hub_id || !$category_id || !in_array($status, ['active', 'inactive'])) {
        return knx_json_response(false, ['error' => 'invalid_request'], 400);
    }

    /** Verify nonce */
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    /** Validate category belongs to hub */
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE id=%d AND hub_id=%d",
        $category_id, $hub_id
    ));
    if (!$exists) {
        return knx_json_response(false, ['error' => 'category_not_found'], 404);
    }

    /** Perform update */
    $updated = $wpdb->update(
        $table,
        [
            'status'     => $status,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $category_id, 'hub_id' => $hub_id],
        ['%s', '%s'],
        ['%d', '%d']
    );

    if ($updated === false) {
        return knx_json_response(false, ['error' => 'db_update_failed'], 500);
    }

    return knx_json_response(true, [
        'message'      => "Category status updated successfully",
        'category_id'  => $category_id,
        'hub_id'       => $hub_id,
        'new_status'   => $status
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
