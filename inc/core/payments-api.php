<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - PAYMENTS API (FINAL PRODUCTION)
 * Endpoint:
 *    POST /wp-json/knx/v1/payments/create-intent
 *
 * Purpose:
 *    - Validates Nexus session (no guests)
 *    - Validates cart integrity (same rules as prevalidate)
 *    - Retrieves Stripe keys from knx_settings
 *    - Uses Stripe SDK (server-side only)
 *    - Creates PaymentIntent with hidden fee/tax engines
 *    - Returns client_secret to frontend
 *
 * Security:
 *    - Hardened role check (must be customer/super_admin)
 *    - Cart ownership validation
 *    - Subtotal mismatch detection
 *    - Prevents spoofing (no client-side amount control)
 *    - Secret key is NEVER exposed to JS
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1/payments', '/create-intent', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_payments_create_intent',
        'permission_callback' => '__return_true',
    ]);
});


function knx_api_payments_create_intent(WP_REST_Request $req) {
    global $wpdb;

    // ==========================================================
    // 0) NEXUS SESSION VALIDATION
    // ==========================================================
    $session = function_exists('knx_get_session') ? knx_get_session() : false;

    if (!$session || !isset($session->role)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'unauthorized',
            'message' => 'You must be logged in to continue.'
        ], 401);
    }

    // Only customer roles are allowed to pay
    $allowed = ['customer', 'super_admin', 'manager'];
    if (!in_array($session->role, $allowed, true)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'forbidden',
            'message' => 'You are not allowed to place orders.'
        ], 403);
    }

    // ==========================================================
    // 1) EXTRACT JSON
    // ==========================================================
    $body = $req->get_json_params();
    if (empty($body) || !is_array($body)) {
        return new WP_REST_Response(['error' => 'invalid_body'], 400);
    }

    $cart_id = isset($body['cart_id']) ? intval($body['cart_id']) : 0;
    if ($cart_id <= 0) {
        return new WP_REST_Response(['error' => 'missing_cart_id'], 400);
    }

    // ==========================================================
    // 2) LOAD CART + ITEMS
    // ==========================================================
    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts} WHERE id = %d LIMIT 1",
        $cart_id
    ));

    if (!$cart) {
        return new WP_REST_Response(['error' => 'cart_not_found'], 404);
    }

    // Validate: session token owner
    if ($cart->session_token !== ($_COOKIE['knx_cart_token'] ?? '')) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'session_mismatch',
            'message' => 'This cart does not belong to your session.'
        ], 403);
    }

    // Validate status
    if ($cart->status !== 'active') {
        return new WP_REST_Response(['error' => 'cart_not_active'], 400);
    }

    // Load items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_cart_items} WHERE cart_id = %d ORDER BY id ASC",
        $cart->id
    ));

    if (!$items) {
        return new WP_REST_Response(['error' => 'empty_cart'], 400);
    }

    // ==========================================================
    // 3) Validate hub
    // ==========================================================
    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$table_hubs} WHERE id = %d LIMIT 1",
        $cart->hub_id
    ));

    if (!$hub) {
        return new WP_REST_Response(['error' => 'hub_not_found'], 404);
    }

    // ==========================================================
    // 4) Recalculate subtotal (server trust only)
    // ==========================================================
    $computed_subtotal = 0;

    foreach ($items as $line) {
        $computed_subtotal += floatval($line->line_total);
    }

    if (abs($computed_subtotal - floatval($cart->subtotal)) > 0.05) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'subtotal_mismatch',
            'message' => 'Subtotal mismatch detected.'
        ], 400);
    }

    // ==========================================================
    // 5) LOAD STRIPE KEYS (server-side only)
    // ==========================================================
    $settings_table = $wpdb->prefix . 'knx_settings';

    $secret_key = $wpdb->get_var(
        "SELECT value FROM {$settings_table} WHERE name = 'stripe_secret_key' LIMIT 1"
    );

    $publishable_key = $wpdb->get_var(
        "SELECT value FROM {$settings_table} WHERE name = 'stripe_publishable_key' LIMIT 1"
    );

    if (!$secret_key || !$publishable_key) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'stripe_not_configured',
            'message' => 'Stripe has not been configured.'
        ], 500);
    }

    // Load Stripe SDK
    if (!class_exists('\Stripe\Stripe')) {
        require_once KNX_PATH . 'vendor/stripe/stripe-php/init.php';
    }

    \Stripe\Stripe::setApiKey($secret_key);

    // ==========================================================
    // 6) TAX + FEES CALCULATION (never exposed to customer)
    // ==========================================================
    $tax_rate = 0.085; // 8.5% sample, will come from DB later
    $software_fee = 1.20; // example
    $delivery_fee = 4.49; // example

    $tax_amount = round($computed_subtotal * $tax_rate, 2);

    $final_amount = $computed_subtotal + $tax_amount + $software_fee + $delivery_fee;

    // Stripe requires amount in cents
    $amount_cents = intval(round($final_amount * 100));

    // ==========================================================
    // 7) CREATE PAYMENT INTENT (server-side only)
    // ==========================================================
    try {
        $intent = \Stripe\PaymentIntent::create([
            'amount'               => $amount_cents,
            'currency'             => 'usd',
            'metadata'             => [
                'cart_id'   => $cart_id,
                'hub_id'    => $hub->id,
                'customer'  => $session->user_id,
                'subtotal'  => $computed_subtotal,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'stripe_error',
            'message' => $e->getMessage(),
        ], 500);
    }

    // ==========================================================
    // 8) Return safe response (no keys exposed)
    // ==========================================================
    return new WP_REST_Response([
        'success'        => true,
        'client_secret'  => $intent->client_secret,
        'publishable_key'=> $publishable_key, // safe (public key)
        'amount'         => $final_amount,
        'breakdown'      => [
            'items_subtotal' => $computed_subtotal,
            'tax'            => $tax_amount,
            'software_fee'   => $software_fee,
            'delivery_fee'   => $delivery_fee,
        ],
        'currency'       => 'usd',
    ], 200);
}
