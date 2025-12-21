<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Software Fees Settings API
 * Super admin only
 */

add_action('rest_api_init', function() {
    
    // Get fees settings
    register_rest_route('knx/v1', '/settings/fees', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_fees_settings',
        'permission_callback' => knx_permission_callback(['super_admin']),
    ]);
    
    // Update fees settings
    register_rest_route('knx/v1', '/settings/fees', [
        'methods'  => 'PUT',
        'callback' => 'knx_api_update_fees_settings',
        'permission_callback' => knx_permission_callback(['super_admin']),
    ]);
});

/**
 * Get current fees settings
 */
function knx_api_get_fees_settings(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin']);
    
    $table = $wpdb->prefix . 'knx_settings';
    
    // Default fees
    $defaults = [
        'platform_fee_percent' => 0,
        'platform_fee_fixed' => 0,
        'delivery_base_fee' => 0,
        'delivery_per_km' => 0,
        'service_fee_percent' => 0,
        'tax_rate' => 0,
        'minimum_order_amount' => 0
    ];
    
    // Get from database
    $settings = [];
    $keys = array_keys($defaults);
    $placeholders = implode(',', array_fill(0, count($keys), '%s'));
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT setting_key, setting_value FROM {$table} WHERE setting_key IN ({$placeholders})",
        ...$keys
    ));
    
    foreach ($results as $row) {
        $settings[$row->setting_key] = floatval($row->setting_value);
    }
    
    // Merge with defaults
    $settings = array_merge($defaults, $settings);
    
    return new WP_REST_Response([
        'success' => true,
        'settings' => $settings
    ], 200);
}

/**
 * Update fees settings
 */
function knx_api_update_fees_settings(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin']);
    knx_require_nonce('knx_settings_nonce');
    
    $data = json_decode($request->get_body(), true);
    $table = $wpdb->prefix . 'knx_settings';
    
    $allowed_keys = [
        'platform_fee_percent',
        'platform_fee_fixed',
        'delivery_base_fee',
        'delivery_per_km',
        'service_fee_percent',
        'tax_rate',
        'minimum_order_amount'
    ];
    
    foreach ($allowed_keys as $key) {
        if (isset($data[$key])) {
            $value = floatval($data[$key]);
            
            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE setting_key = %s",
                $key
            ));
            
            if ($exists) {
                $wpdb->update($table, 
                    ['setting_value' => $value],
                    ['setting_key' => $key],
                    ['%s'],
                    ['%s']
                );
            } else {
                $wpdb->insert($table, [
                    'setting_key' => $key,
                    'setting_value' => $value
                ], ['%s', '%s']);
            }
        }
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Fees settings updated successfully'
    ], 200);
}
