<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: SECURE TOTAL (Production)
 * Endpoint:
 *    POST /wp-json/knx/v1/checkout/secure-total
 * ----------------------------------------------------------
 * Responsibilities:
 *  - Validate cart before payment
 *  - Recalculate internal subtotal safely
 *  - Ensure hub is open
 *  - Ensure customer is logged in (Nexus session)
 *  - Generate pre-payment order token
 *  - Redirect to /payment (or Stripe)
 *
 *  SECURITY GOALS:
 *   - Never expose fee/tax formulas to the public
 *   - Never trust frontend subtotal
 *   - Reject ghost carts, expired carts, or fake carts
 *   - Reject attempts to check out without login
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/checkout/secure-total', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_secure_total',
        'permission_callback' => knx_permission_callback(['customer']),
    ]);
});


function knx_api_secure_total(WP_REST_Request $req) {
    global $wpdb;

    $table_carts       = $wpdb->prefix . 'knx_carts';
    $table_cart_items  = $wpdb->prefix . 'knx_cart_items';
    $table_hubs        = $wpdb->prefix . 'knx_hubs';
    $table_preorders   = $wpdb->prefix . 'knx_preorders';

    // --------------------------------------------------------------
    // 0) REQUIRE LOGGED-IN NEXUS SESSION (TASK 3: Auth guard)
    // --------------------------------------------------------------
    $auth_guard = knx_guard_checkout_api('customer');
    if ($auth_guard !== true) return $auth_guard;
    
    $session = knx_get_session();
    $customer_id = intval($session->user_id);

    // --------------------------------------------------------------
    // 1) READ REQUEST BODY
    // --------------------------------------------------------------
    $body = $req->get_json_params();

    if (!is_array($body) || empty($body['cart_id'])) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_cart_id',
            'message' => 'Cart ID is required.'
        ], 400);
    }

    $cart_id = intval($body['cart_id']);

    // --------------------------------------------------------------
    // 2) VERIFY CART EXISTS AND BELONGS TO THIS SESSION
    // --------------------------------------------------------------
    $token = isset($_COOKIE['knx_cart_token'])
        ? sanitize_text_field($_COOKIE['knx_cart_token'])
        : '';

    if ($token === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'session_expired',
            'message' => 'Your session expired. Please return to the menu.'
        ], 403);
    }

    $cart = $wpdb->get_row($wpdb->prepare("
        SELECT *
        FROM {$table_carts}
        WHERE id = %d
          AND session_token = %s
          AND status = 'active'
        LIMIT 1
    ", $cart_id, $token));

    if (!$cart) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_not_found',
            'message' => 'Unable to find your active cart.'
        ], 404);
    }

    // --------------------------------------------------------------
    // 3) LOAD ITEMS and RECOMPUTE SUBTOTAL
    // --------------------------------------------------------------
    $items = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$table_cart_items}
        WHERE cart_id = %d
    ", $cart_id));

    if (!$items) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'empty_cart',
            'message' => 'Your cart has no items.'
        ], 400);
    }

    $recalculated_subtotal = 0.0;

    foreach ($items as $line) {
        $lt = isset($line->line_total) ? floatval($line->line_total) : 0.0;
        if ($lt < 0) $lt = 0.0;
        $recalculated_subtotal += $lt;
    }

    $recalculated_subtotal = round($recalculated_subtotal, 2);

    // --------------------------------------------------------------
    // 4) COMPARE WITH STORED SUBTOTAL (±0.05)
    // --------------------------------------------------------------
    if (abs($recalculated_subtotal - floatval($cart->subtotal)) > 0.05) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'subtotal_mismatch',
            'message' => 'Subtotal mismatch detected. Please review your cart.'
        ], 400);
    }

    // --------------------------------------------------------------
    // 5) VALIDATE HUB IS OPEN
    // --------------------------------------------------------------
    $hub = $wpdb->get_row($wpdb->prepare("
        SELECT id, name
        FROM {$table_hubs}
        WHERE id = %d
    ", $cart->hub_id));

    if (!$hub) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'hub_not_found',
            'message' => 'Restaurant not found.'
        ], 404);
    }

    if (function_exists('knx_hub_is_open')) {
        if (!knx_hub_is_open($hub->id)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'hub_closed',
                'message' => 'This restaurant is currently closed.'
            ], 400);
        }
    }

    // --------------------------------------------------------------
    // 6) GENERATE PRE-ORDER TOKEN (internal only)
    // --------------------------------------------------------------
    $pre_token = bin2hex(random_bytes(24)); // 48-char secure string
    $now = current_time('mysql');

    // Create preorders table if missing
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$table_preorders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            preorder_token VARCHAR(96) NOT NULL,
            cart_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY cart_idx (cart_id),
            KEY preorder_idx (preorder_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert new preorder row
    $wpdb->insert($table_preorders, [
        'preorder_token' => $pre_token,
        'cart_id'        => $cart_id,
        'customer_id'    => $customer_id,
        'created_at'     => $now,
    ], ['%s','%d','%d','%s']);

    // --------------------------------------------------------------
    // 7) RETURN REDIRECT URL
    // --------------------------------------------------------------
    return new WP_REST_Response([
        'success'      => true,
        'message'      => 'Secure total confirmed.',
        'redirect_url' => site_url('/payment?token=' . urlencode($pre_token))
    ], 200);
}
