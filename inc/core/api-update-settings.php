<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Update Hub Settings (v1.0)
 * ----------------------------------------------------------
 * Updates timezone, currency, tax_rate, and min_order fields.
 * Secure via REST + nonce validation.
 * ==========================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('knx/v1', '/update-hub-settings', [
        'methods'  => 'POST',
        'callback' => 'knx_api_update_hub_settings',
        'permission_callback' => '__return_true'
    ]);
});

function knx_api_update_hub_settings(WP_REST_Request $r) {
    global $wpdb;
    $table = $wpdb->prefix . 'knx_hubs';

    /** Validate nonce */
    $nonce = sanitize_text_field($r['knx_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);
    }

    /** Validate session */
    $session = function_exists('knx_get_session') ? knx_get_session() : null;
    if (!$session || !in_array($session->role, ['super_admin','manager','hub_management'], true)) {
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);
    }

    /** Data */
    $hub_id    = intval($r['hub_id']);
    $timezone  = sanitize_text_field($r['timezone']);
    $currency  = sanitize_text_field($r['currency']);
    $tax_rate  = floatval($r['tax_rate']);
    $min_order = floatval($r['min_order']);

    if (!$hub_id) {
        return new WP_REST_Response(['success' => false, 'error' => 'missing_hub_id'], 400);
    }

    /** Update */
    $updated = $wpdb->update(
        $table,
        [
            'timezone'  => $timezone,
            'currency'  => $currency,
            'tax_rate'  => $tax_rate,
            'min_order' => $min_order,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $hub_id],
        ['%s','%s','%f','%f','%s'],
        ['%d']
    );

    if ($updated === false) {
        return new WP_REST_Response([
            'success' => false, 
            'error' => 'db_error',
            'detail' => $wpdb->last_error
        ], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}
