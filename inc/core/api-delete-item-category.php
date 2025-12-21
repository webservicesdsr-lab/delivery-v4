<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Delete Item Category (v1.2 Production)
 * ----------------------------------------------------------
 * ✅ REST Real
 * ✅ Deletes category by ID and reorders automatically
 * ✅ Safe rollback on failure
 * ✅ Portable with dynamic prefix (Z7E_ / default)
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/delete-item-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_delete_item_category',
        'permission_callback' => '__return_true',
    ]);
});

function knx_api_delete_item_category(WP_REST_Request $r) {
    global $wpdb;

    /** Detect correct table (supports Z7E_ prefix) */
    $table = knx_items_categories_table();

    /** Validate and sanitize input */
    $hub_id = intval($r->get_param('hub_id'));
    $id     = intval($r->get_param('category_id'));
    $nonce  = sanitize_text_field($r->get_param('knx_nonce'));

    if (!$hub_id || !$id) {
        return knx_json_response(false, ['error' => 'missing_parameters'], 400);
    }

    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    /** Start transaction */
    $wpdb->query('START TRANSACTION');

    try {
        /** Delete category */
        $deleted = $wpdb->delete($table, ['id' => $id, 'hub_id' => $hub_id], ['%d', '%d']);

        if ($deleted === false) {
            $wpdb->query('ROLLBACK');
            return knx_json_response(false, ['error' => 'db_delete_failed'], 500);
        }

        /** Fetch remaining categories for reordering */
        $remaining = $wpdb->get_results($wpdb->prepare("
            SELECT id FROM $table 
            WHERE hub_id = %d 
            ORDER BY sort_order ASC, id ASC
        ", $hub_id));

        /** Normalize sort_order */
        $i = 1;
        foreach ($remaining as $c) {
            $wpdb->update($table, ['sort_order' => $i], ['id' => $c->id], ['%d'], ['%d']);
            $i++;
        }

        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return knx_json_response(false, ['error' => 'transaction_failed'], 500);
    }

    return knx_json_response(true, [
        'message' => 'Category deleted and sort_order normalized',
        'hub_id'  => $hub_id,
        'deleted_id' => $id
    ]);
}

/** JSON Response helper */
if (!function_exists('knx_json_response')) {
    function knx_json_response($success, $data = [], $status = 200) {
        return new WP_REST_Response(array_merge(['success' => $success], $data), $status);
    }
}
