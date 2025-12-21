<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Cities Management (v3.0 Production)
 * ----------------------------------------------------------
 * Central REST controller for CRUD city operations.
 * ✅ Add / Toggle / Get
 * ✅ Prefix auto-detection (Z7E_, wp_, G25_, etc.)
 * ✅ Secure session + Nonce validation
 * ✅ Unified JSON structure with Edit City
 * ==========================================================
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v1', '/get-cities', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_cities_v3',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/add-city', [
        'methods'  => 'POST',
        'callback' => 'knx_api_add_city_v3',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/toggle-city', [
        'methods'  => 'POST',
        'callback' => 'knx_api_toggle_city_v3',
        'permission_callback' => '__return_true',
    ]);
});

/** =========================================================
 * 1. Get All Cities
 * ========================================================= */
function knx_api_get_cities_v3() {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_cities';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $cities = $wpdb->get_results("SELECT id, name, status, state, country, created_at, updated_at FROM {$table} ORDER BY id DESC");

    return new WP_REST_Response(['success' => true, 'cities' => $cities], 200);
}

/** =========================================================
 * 2. Add City
 * ========================================================= */
function knx_api_add_city_v3(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_cities';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $name  = sanitize_text_field($r['name']);
    $nonce = sanitize_text_field($r['knx_nonce']);

    if (!wp_verify_nonce($nonce, 'knx_add_city_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    if (empty($name))
        return new WP_REST_Response(['success' => false, 'error' => 'missing_name'], 400);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE name = %s", $name));
    if ($exists)
        return new WP_REST_Response(['success' => false, 'error' => 'duplicate_city'], 409);

    $insert = $wpdb->insert($table, [
        'name'       => $name,
        'status'     => 'active',
        'state'      => '',
        'country'    => 'USA',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%s', '%s']);

    return new WP_REST_Response([
        'success' => (bool) $insert,
        'message' => $insert ? '✅ City added successfully' : '❌ Database error'
    ], $insert ? 200 : 500);
}

/** =========================================================
 * 3. Toggle City Active/Inactive
 * ========================================================= */
function knx_api_toggle_city_v3(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_cities';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $data  = json_decode($r->get_body(), true);
    $id    = intval($data['id'] ?? 0);
    $status = sanitize_text_field($data['status'] ?? 'active');
    $nonce = sanitize_text_field($data['knx_nonce'] ?? '');

    if (!wp_verify_nonce($nonce, 'knx_toggle_city_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    if (!$id)
        return new WP_REST_Response(['success' => false, 'error' => 'missing_id'], 400);

    $wpdb->update($table, ['status' => $status], ['id' => $id], ['%s'], ['%d']);

    return new WP_REST_Response(['success' => true, 'message' => '⚙️ City status updated'], 200);
}
