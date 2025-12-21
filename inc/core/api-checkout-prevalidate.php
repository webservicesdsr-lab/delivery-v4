<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Checkout Pre-Validation API (SAFE BUILD)
 * Version: Production v5 - Stable, No external calls at load
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/checkout/prevalidate', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_checkout_prevalidate_secure',
        'permission_callback' => knx_permission_callback(['customer']),
    ]);
});

/**
 * ==========================================================
 * SAFE SHIELD (NO external HTTP calls)
 * ==========================================================
 */

function knx_prevalidate_shield_safe() {

    // Use helper to get real IP (handles proxies/CDN)
    $ip = function_exists('knx_get_client_ip') ? knx_get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    // Rate limit only (removed IPv6 blocking - legitimate mobile users)
    $key  = 'knx_prevalidate_rate_' . md5($ip);
    $hits = intval(get_transient($key));

    if ($hits > 40) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'rate_limited',
            'message' => 'Too many requests. Try again shortly.'
        ], 429);
    }

    set_transient($key, $hits + 1, 20); // 20 seconds

    return true;
}

/**
 * ==========================================================
 * MAIN CHECKOUT PREVALIDATE HANDLER
 * ==========================================================
 */

function knx_api_checkout_prevalidate_secure(WP_REST_Request $req) {
    global $wpdb;

    // ---------- SAFE SHIELD ----------
    $shield = knx_prevalidate_shield_safe();
    if ($shield !== true) return $shield;
    
    // ---------- TASK 3: AUTH GUARD ----------
    $auth_guard = knx_guard_checkout_api('customer');
    if ($auth_guard !== true) return $auth_guard;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // ----------------------------------------
    // 1) Extract JSON body
    // ----------------------------------------
    $body = $req->get_json_params();

    if (!is_array($body)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_payload',
            'message' => 'Malformed JSON body.'
        ], 400);
    }

    $session_token = sanitize_text_field($body['session_token'] ?? '');

    if (!$session_token) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_session',
            'message' => 'Session token missing.'
        ], 400);
    }

    // ----------------------------------------
    // 2) Locate active cart
    // ----------------------------------------
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts}
         WHERE session_token = %s
           AND status = 'active'
         ORDER BY updated_at DESC
         LIMIT 1",
        $session_token
    ));

    if (!$cart) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_not_found',
            'message' => 'Cart expired or missing.'
        ], 404);
    }

    // ----------------------------------------
    // 3) Load cart items
    // ----------------------------------------
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_cart_items}
             WHERE cart_id = %d
             ORDER BY id ASC",
            $cart->id
        )
    );

    if (!$items) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'empty_cart',
            'message' => 'No items found.'
        ], 400);
    }

    // ----------------------------------------
    // 4) Validate hub
    // ----------------------------------------
    $table_hubs = $wpdb->prefix . "knx_hubs";

    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$table_hubs} WHERE id = %d",
        $cart->hub_id
    ));

    if (!$hub) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'hub_not_found',
            'message' => 'Restaurant missing.'
        ], 404);
    }

    // ----------------------------------------
    // 5) Recompute subtotal
    // ----------------------------------------
    $computed = 0.0;

    foreach ($items as $line) {
        $computed += floatval($line->line_total);
    }

    if (abs($computed - floatval($cart->subtotal)) > 0.05) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'subtotal_mismatch',
            'message' => 'Subtotal mismatch detected.'
        ], 400);
    }

    // ----------------------------------------
    // 6) Final OK response
    // ----------------------------------------
    return new WP_REST_Response([
        'success'   => true,
        'cart_id'   => intval($cart->id),
        'hub_id'    => intval($hub->id),
        'hub_name'  => $hub->name,
        'subtotal'  => round($computed, 2),
        'next_step' => 'ready_for_payment'
    ], 200);
}
