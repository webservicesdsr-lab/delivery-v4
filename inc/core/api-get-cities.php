<?php
/**
 * Kingdom Nexus - Get Active Cities API
 * REST Endpoint: /wp-json/knx/v1/get-cities
 * Method: GET
 * Returns: List of active cities with hub count
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('knx/v1', '/get-cities', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_cities',
        'permission_callback' => '__return_true'
    ]);
});

function knx_api_get_cities($request) {
    global $wpdb;

    $cities_table = $wpdb->prefix . 'knx_cities';
    $hubs_table  = $wpdb->prefix . 'knx_hubs';

    // Use prepared statement to protect against SQL injection
    $sql = $wpdb->prepare(<<<'SQL'
        SELECT
            c.id,
            c.name,
            c.state,
            c.slug,
            COUNT(h.id) as hub_count
        FROM {$cities_table} c
        LEFT JOIN {$hubs_table} h
            ON h.city_id = c.id AND h.status = %s
        WHERE c.status = %s
        GROUP BY c.id, c.name, c.state, c.slug
        ORDER BY hub_count DESC, c.name ASC
        LIMIT 8
    SQL
, 'active', 'active');

    $cities = $wpdb->get_results($sql);

    $formatted = array();
    if ($cities) {
        foreach ($cities as $city) {
            $formatted[] = array(
                'id' => absint($city->id),
                'name' => sanitize_text_field($city->name),
                'state' => sanitize_text_field($city->state),
                'slug' => sanitize_title($city->slug),
                'hub_count' => absint($city->hub_count),
                'display_name' => sanitize_text_field($city->name) . ', ' . sanitize_text_field($city->state)
            );
        }
    }

    return rest_ensure_response(array(
        'success' => true,
        'cities' => $formatted
    ));
}
