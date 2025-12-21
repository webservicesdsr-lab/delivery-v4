<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Core API (v4.4 Production)
 * ----------------------------------------------------------
 * REST API for Global Hubs Management (CRUD level)
 * ✅ Add Hub (with city_id)
 * ✅ Toggle Hub Status
 * ✅ Update Temporary Closure
 * ----------------------------------------------------------
 * Modular edit endpoints handled separately:
 *   - api-edit-hub-identity.php
 *   - api-edit-hub-location.php
 *   - api-upload-logo.php
 *   - api-update-hours.php
 * ==========================================================
 */

/**
 * ==========================================================
 * 1. Register REST Routes
 * ==========================================================
 */
add_action('rest_api_init', function() {

    register_rest_route('knx/v1', '/add-hub', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_add_hub_v44',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/update-hub-closure', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_update_hub_closure_v44',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/toggle-hub', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_toggle_hub_status_v44',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * ==========================================================
 * 2. JSON Response Helper
 * ==========================================================
 */
function knx_json_response($success, $data = [], $status_code = 200) {
    $response = array_merge(['success' => $success], $data);
    return new WP_REST_Response($response, $status_code);
}

/**
 * ==========================================================
 * 3. Add Hub (v4.4)
 * ----------------------------------------------------------
 * Adds a new hub entry with city_id validation.
 * ==========================================================
 */
function knx_api_add_hub_v44(WP_REST_Request $r) {
    global $wpdb;

    /** Dynamic table names with current WP prefix */
    $table_hubs   = $wpdb->prefix . 'knx_hubs';
    $table_cities = $wpdb->prefix . 'knx_cities';

    /** Validate session */
    $session = knx_get_session();
    if (!$session)
        return knx_json_response(false, ['error' => 'unauthorized'], 403);

    /** Sanitize inputs */
    $name    = sanitize_text_field($r['name']);
    $phone   = sanitize_text_field($r['phone']);
    $email   = sanitize_email($r['email']);
    $city_id = intval($r['city_id']);
    $nonce   = sanitize_text_field($r['knx_nonce']);

    /** Validate nonce */
    if (!wp_verify_nonce($nonce, 'knx_add_hub_nonce'))
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);

    /** Required fields */
    if (empty($name) || empty($email))
        return knx_json_response(false, ['error' => 'missing_fields'], 400);

    /** Optional: validate city_id if provided */
    if ($city_id > 0) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_cities} WHERE id = %d", $city_id));
        if (!$exists)
            return knx_json_response(false, ['error' => 'invalid_city'], 404);
    }

    /** Insert hub with generated slug */
    $hub_id = null;
    $slug = knx_slugify_hub_name($name);
    
    $inserted = $wpdb->insert(
        $table_hubs,
        [
            'name'       => $name,
            'phone'      => $phone,
            'email'      => $email,
            'city_id'    => $city_id ?: null,
            'slug'       => $slug,
            'status'     => 'active',
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted)
        return knx_json_response(false, ['error' => 'db_error'], 500);

    return knx_json_response(true, [
        'hub_id'  => $wpdb->insert_id,
        'message' => '✅ Hub added successfully'
    ]);
}

/**
 * ==========================================================
 * 4. Update Temporary Closure (unchanged, v4.4)
 * ==========================================================
 */
function knx_api_update_hub_closure_v44(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_hubs';

    /** Validate session */
    $session = knx_get_session();
    if (!$session)
        return knx_json_response(false, ['error' => 'unauthorized'], 403);

    /** Sanitize and validate */
    $hub_id = intval($r['hub_id']);
    $nonce  = sanitize_text_field($r['knx_nonce']);
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce'))
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);

    if (!$hub_id)
        return knx_json_response(false, ['error' => 'missing_id'], 400);

    $is_temp_closed     = intval($r['is_temp_closed']);
    $temp_close_message = sanitize_textarea_field($r['temp_close_message']);
    $temp_reopen_at     = sanitize_text_field($r['temp_reopen_at']);

    /** Update */
    $wpdb->update(
        $table,
        [
            'is_temp_closed'     => $is_temp_closed,
            'temp_close_message' => $temp_close_message,
            'temp_reopen_at'     => $temp_reopen_at ?: null,
            'updated_at'         => current_time('mysql')
        ],
        ['id' => $hub_id],
        ['%d', '%s', '%s', '%s'],
        ['%d']
    );

    return knx_json_response(true, ['message' => 'Temporary closure updated successfully']);
}

/**
 * ==========================================================
 * 5. Toggle Hub Status (v4.4)
 * ==========================================================
 */
function knx_api_toggle_hub_status_v44(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_hubs';

    /** Validate session */
    $session = knx_get_session();
    if (!$session)
        return knx_json_response(false, ['error' => 'unauthorized'], 403);

    /** Normalize params */
    $hub_id = intval($r['hub_id'] ?? $r['id'] ?? 0);
    $status = sanitize_text_field($r['status'] ?? '');
    $nonce  = sanitize_text_field($r['knx_nonce'] ?? $r['nonce'] ?? '');

    /** Nonce check */
    if (
        !wp_verify_nonce($nonce, 'knx_toggle_hub_nonce') &&
        !wp_verify_nonce($nonce, 'knx_edit_hub_nonce')
    )
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);

    /** Validate data */
    if (!$hub_id || !in_array($status, ['active', 'inactive']))
        return knx_json_response(false, ['error' => 'invalid_data'], 400);

    /** Update */
    $wpdb->update(
        $table,
        ['status' => $status],
        ['id' => $hub_id],
        ['%s'],
        ['%d']
    );

    return knx_json_response(true, [
        'message' => 'Hub status updated successfully',
        'hub_id'  => $hub_id,
        'status'  => $status
    ]);
}
