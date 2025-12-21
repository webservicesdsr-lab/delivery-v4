<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - API: Save Hub Hours (v4.0)
 * ------------------------------------------------
 * Stores structured weekly opening hours for each Hub.
 * Sunday remains locked (handled by JS).
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/save-hours', [
        'methods'  => 'POST',
        'callback' => 'knx_api_save_hours',
        'permission_callback' => '__return_true',
    ]);
});

function knx_api_save_hours(WP_REST_Request $r)
{
    global $wpdb;

    /** Dynamic table name with current WP prefix */
    $table = $wpdb->prefix . 'knx_hubs';

    $hub_id = intval($r['hub_id']);
    $nonce  = sanitize_text_field($r['knx_nonce']);
    $hours  = $r['hours'];

    if (!$hub_id || !$hours || !is_array($hours))
        return ['success' => false, 'error' => 'invalid_schedule'];

    /** Validate nonce */
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce'))
        return ['success' => false, 'error' => 'invalid_nonce'];

    /** Prepare update data for individual day columns */
    /** Each day stores JSON array of intervals: [{"open":"09:00","close":"17:00"},...] */
    $update_data = [];
    $allowed_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    
    foreach ($allowed_days as $day) {
        $column = 'hours_' . $day;
        $intervals = isset($hours[$day]) && is_array($hours[$day]) ? $hours[$day] : [];
        
        // Store as JSON string (empty array if no intervals)
        $update_data[$column] = !empty($intervals) ? wp_json_encode($intervals) : '';
    }

    /** Update database */
    $result = $wpdb->update(
        $table, 
        $update_data, 
        ['id' => $hub_id],
        array_fill(0, count($update_data), '%s'),
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'error' => 'db_error', 'details' => $wpdb->last_error];
    }

    return ['success' => true, 'message' => 'Hours updated successfully'];
}
