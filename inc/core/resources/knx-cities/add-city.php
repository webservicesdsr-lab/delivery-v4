<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KNX Cities — Add City (SEALED v2)
 * ----------------------------------------------------------
 * Endpoint:
 * - POST /wp-json/knx/v2/cities/add
 *
 * Payload:
 * - name (string)
 * - knx_nonce
 *
 * Security:
 * - Session required
 * - Role: super_admin ONLY
 * - Nonce required
 * - Wrapped with knx_rest_wrap
 * ==========================================================
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v2', '/cities/add', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            return knx_rest_wrap('knx_v2_add_city')($request);
        },
        'permission_callback' => '__return_true',
    ]);
});

function knx_v2_add_city(WP_REST_Request $request) {
    global $wpdb;

    /* ---------------------------
     * Session + role
     * --------------------------- */
    $session = knx_rest_require_session();
    if ($session instanceof WP_REST_Response) return $session;

    if (($session->role ?? '') !== 'super_admin') {
        return knx_rest_error('Forbidden', 403);
    }

    /* ---------------------------
     * Nonce
     * --------------------------- */
    $nonceCheck = knx_rest_verify_nonce(
        $request->get_param('knx_nonce'),
        'knx_city_add'
    );
    if ($nonceCheck instanceof WP_REST_Response) return $nonceCheck;

    /* ---------------------------
     * Validate input
     * --------------------------- */
    $name = sanitize_text_field($request->get_param('name'));
    if ($name === '') {
        return knx_rest_error('City name is required', 400);
    }

    $table = $wpdb->prefix . 'knx_cities';

    /* ---------------------------
     * Prevent duplicates
     * --------------------------- */
    $exists = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE name = %s AND deleted_at IS NULL", $name)
    );
    if ($exists > 0) {
        return knx_rest_error('City already exists', 409);
    }

    /* ---------------------------
     * Insert
     * --------------------------- */
    $inserted = $wpdb->insert(
        $table,
        [
            'name'           => $name,
            'status'         => 'active',
            'is_operational' => 1,
            'created_at'     => current_time('mysql'),
        ],
        ['%s', '%s', '%d', '%s']
    );

    if (!$inserted) {
        return knx_rest_error('Database error', 500);
    }

    return knx_rest_response(true, 'City added successfully', [
        'city_id' => $wpdb->insert_id,
        'name'    => $name,
    ]);
}
