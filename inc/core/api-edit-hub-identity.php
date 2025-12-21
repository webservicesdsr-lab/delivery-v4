<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Edit Hub Identity API (v4.5 Production)
 * ----------------------------------------------------------
 * Fixes included:
 * - Correct handling of category_id & city_id with NULL values
 * - Proper WPDB formats (NO "NULL" in formats array)
 * - Accurate "changes made" detection
 * - Full validation: nonce, roles, active status
 * - Production safe (no debug output)
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/update-hub-identity', [
        'methods'  => 'POST',
        'callback' => 'knx_update_hub_identity_v45',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

function knx_update_hub_identity_v45(WP_REST_Request $request) {
    global $wpdb;

    /** Dynamic table names */
    $table_hubs   = $wpdb->prefix . 'knx_hubs';
    $table_cities = $wpdb->prefix . 'knx_cities';
    $table_cats   = $wpdb->prefix . 'knx_hub_categories';

    /** Parse JSON body */
    $data = json_decode($request->get_body(), true);

    /** Validate nonce */
    if (empty($data['knx_nonce']) || !wp_verify_nonce($data['knx_nonce'], 'knx_edit_hub_nonce')) {
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);
    }

    /** Validate session */
    $session = knx_get_session();
    if (
        !$session ||
        !in_array(
            $session->role,
            ['super_admin', 'manager', 'hub_management', 'menu_uploader', 'vendor_owner'],
            true
        )
    ) {
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);
    }

    /** Sanitize input */
    $hub_id      = intval($data['hub_id'] ?? 0);
    $city_id     = intval($data['city_id'] ?? 0);
    $category_id = intval($data['category_id'] ?? 0);
    $email       = sanitize_email($data['email'] ?? '');
    $phone       = sanitize_text_field($data['phone'] ?? '');
    $status      = in_array($data['status'] ?? 'active', ['active', 'inactive'])
                    ? $data['status']
                    : 'active';

    /** Required fields */
    if (!$hub_id || empty($email)) {
        return new WP_REST_Response(['success' => false, 'error' => 'missing_fields'], 400);
    }

    /** Validate city_id if present */
    if ($city_id > 0) {
        $city_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cities} WHERE id = %d AND status = 'active'",
            $city_id
        ));
        if (!$city_exists) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_city'], 404);
        }
    }

    /** Validate category_id if present */
    if ($category_id > 0) {
        $cat_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_cats} WHERE id = %d AND status = 'active'",
            $category_id
        ));
        if (!$cat_exists) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_category'], 404);
        }
    }

    /**
     * Normalize to NULL
     */
    $city_id     = $city_id     > 0 ? $city_id     : null;
    $category_id = $category_id > 0 ? $category_id : null;

    /** Prepare update payload */
    $update_data = [
        'email'       => $email,
        'phone'       => $phone,
        'status'      => $status,
        'city_id'     => $city_id,
        'category_id' => $category_id,
        'updated_at'  => current_time('mysql')
    ];

    /**
     * Formats MUST match WP rules.
     * NULL must be passed directly in $update_data
     * %d still used for integers
     */
    $formats = [
        '%s', // email
        '%s', // phone
        '%s', // status
        '%d', // city_id
        '%d', // category_id
        '%s'  // updated_at
    ];

    /** Perform update */
    $updated = $wpdb->update(
        $table_hubs,
        $update_data,
        ['id' => $hub_id],
        $formats,
        ['%d']
    );

    /** Handle DB error */
    if ($updated === false) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'db_error'
        ], 500);
    }

    /** Response */
    return new WP_REST_Response([
        'success' => true,
        'hub_id'  => $hub_id,
        'message' => $updated ? 'Hub identity updated successfully' : 'No changes made'
    ], 200);
}
