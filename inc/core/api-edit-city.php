<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Edit City (v3.0 Production)
 * ----------------------------------------------------------
 * Manages single city update operations.
 * ✅ Update Name + Status
 * ✅ Nonce & Role Secure
 * ✅ Dynamic Table Prefix
 * ✅ REST response unified
 * ==========================================================
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v1', '/get-city', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_city_v3',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/update-city', [
        'methods'  => 'POST',
        'callback' => 'knx_api_update_city_v3',
        'permission_callback' => '__return_true',
    ]);
});

/** =========================================================
 * 1. Get City by ID
 * ========================================================= */
function knx_api_get_city_v3(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_cities';

    $id = intval($r->get_param('id'));
    if (!$id)
        return new WP_REST_Response(['success' => false, 'error' => 'missing_id'], 400);

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager']))
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $city = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    if (!$city)
        return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);

    return new WP_REST_Response(['success' => true, 'city' => $city], 200);
}

/** =========================================================
 * 2. Update City Name & Status
 * ========================================================= */
function knx_api_update_city_v3(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_cities';

    $data  = json_decode($r->get_body(), true);
    $id    = intval($data['id'] ?? 0);
    $name  = sanitize_text_field($data['name'] ?? '');
    $status = sanitize_text_field($data['status'] ?? 'active');
    $nonce = sanitize_text_field($data['knx_nonce'] ?? '');

    if (!wp_verify_nonce($nonce, 'knx_edit_city_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager']))
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    if (!$id || empty($name))
        return new WP_REST_Response(['success' => false, 'error' => 'missing_fields'], 400);

    $updated = $wpdb->update(
        $table,
        ['name' => $name, 'status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $id],
        ['%s', '%s', '%s'],
        ['%d']
    );

    if ($updated === false)
        return new WP_REST_Response(['success' => false, 'error' => 'db_error'], 500);

    return new WP_REST_Response([
        'success' => true,
        'message' => $updated ? '✅ City updated successfully' : 'ℹ️ No changes detected'
    ], 200);
}
