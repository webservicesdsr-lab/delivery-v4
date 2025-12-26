<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Delivery Rates (v3.0 Production)
 * ----------------------------------------------------------
 * Handles delivery tiers per city.
 * ✅ Get + Update
 * ✅ Auto Seed Defaults
 * ✅ Dynamic Prefix Detection
 * ✅ Secure Role + Nonce Validation
 * ==========================================================
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v1', '/get-city-details', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_city_details_v3',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/update-city-rates', [
        'methods'  => 'POST',
        'callback' => 'knx_api_update_city_rates_v3',
        'permission_callback' => '__return_true',
    ]);
});

/** =========================================================
 * 1. Get City + Rates
 * ========================================================= */
function knx_api_get_city_details_v3(WP_REST_Request $r) {
    global $wpdb;

    $table_cities = $wpdb->prefix . 'knx_cities';
    $table_rates  = $wpdb->prefix . 'knx_delivery_rates';

    $id = intval($r->get_param('id'));
    if (!$id)
        return new WP_REST_Response(['success' => false, 'error' => 'missing_id'], 400);

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager']))
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $city = $wpdb->get_row($wpdb->prepare("SELECT id, name, active FROM {$table_cities} WHERE id = %d", $id));
    if (!$city)
        return new WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);

    $rates = $wpdb->get_results($wpdb->prepare(
        "SELECT id, from_miles, to_miles, price FROM {$table_rates} WHERE city_id = %d ORDER BY from_miles ASC",
        $id
    ));

    if (empty($rates)) {
        $rates = [
            (object)['from_miles' => 0,  'to_miles' => 6,  'price' => 5.99],
            (object)['from_miles' => 6,  'to_miles' => 9,  'price' => 8.99],
            (object)['from_miles' => 9,  'to_miles' => 12, 'price' => 10.99],
            (object)['from_miles' => 12, 'to_miles' => 14, 'price' => 11.99],
            (object)['from_miles' => 14, 'to_miles' => 19, 'price' => 12.99],
            (object)['from_miles' => 19, 'to_miles' => null, 'price' => 15.99],
        ];
    }

    return new WP_REST_Response(['success' => true, 'city' => $city, 'rates' => $rates], 200);
}

/** =========================================================
 * 2. Update Rates
 * ========================================================= */
function knx_api_update_city_rates_v3(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_delivery_rates';

    $data = json_decode($r->get_body(), true);
    $nonce = sanitize_text_field($data['knx_nonce'] ?? '');
    $city_id = intval($data['city_id'] ?? 0);
    $rates = $data['rates'] ?? [];

    if (!wp_verify_nonce($nonce, 'knx_edit_city_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager']))
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    if (!$city_id || !is_array($rates))
        return new WP_REST_Response(['success' => false, 'error' => 'missing_data'], 400);

    $wpdb->delete($table, ['city_id' => $city_id], ['%d']);

    foreach ($rates as $r) {
        $from  = floatval($r['from_miles'] ?? 0);
        $to    = isset($r['to_miles']) && $r['to_miles'] !== '' ? floatval($r['to_miles']) : null;
        $price = floatval($r['price'] ?? 0);

        $wpdb->insert($table, [
            'city_id'    => $city_id,
            'from_miles' => $from,
            'to_miles'   => $to,
            'price'      => $price,
            'created_at' => current_time('mysql')
        ], ['%d', '%f', '%f', '%f', '%s']);
    }

    return new WP_REST_Response(['success' => true, 'message' => '✅ Delivery rates updated successfully'], 200);
}
