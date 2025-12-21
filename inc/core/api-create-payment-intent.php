<?php
if (!defined('ABSPATH')) exit;

require_once KNX_PATH . "inc/core/payments-helpers.php";

/**
 * ==========================================================
 * Kingdom Nexus - CREATE PAYMENT INTENT API (Production)
 * Endpoint:
 *   POST /wp-json/knx/v1/payments/create-intent
 * ----------------------------------------------------------
 * Input:
 *   {
 *      "preorder_token": "abc123xyz",
 *      "tip": 0 or 2.00 or 5.00 ...
 *   }
 *
 * Output:
 *   {
 *      success: true,
 *      client_secret: "...",
 *      stripe_intent_id: "...",
 *      order_total: 54.39
 *   }
 *
 * Rules:
 *  - Only logged-in customers
 *  - Token must match an ACTIVE cart
 *  - Internal totals computed securely (NO public formulas)
 *  - Stripe Intent created server-side
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/payments/create-intent', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_payments_create_intent',
        'permission_callback' => knx_permission_callback(['customer']),
    ]);
});


function knx_api_payments_create_intent(WP_REST_Request $req) {
    global $wpdb;

    // ======================================================
    // 1) REQUIRE NEXUS SESSION (NO GUESTS)
    // ======================================================
    $session = knx_get_session();
    if (!$session) {
        return new WP_REST_Response([
            "success" => false,
            "error"   => "auth_required",
            "message" => "You must be logged in to continue."
        ], 401);
    }

    $user_id = intval($session->user_id);

    // ======================================================
    // 2) EXTRACT INPUT
    // ======================================================
    $body = $req->get_json_params();
    $token = isset($body['preorder_token'])
        ? sanitize_text_field($body['preorder_token'])
        : '';

    $tip = isset($body['tip']) ? floatval($body['tip']) : 0.0;
    if ($tip < 0) $tip = 0;

    if ($token === '') {
        return new WP_REST_Response([
            "success" => false,
            "error"   => "missing_token",
            "message" => "Missing preorder token."
        ], 400);
    }

    // ======================================================
    // 3) VALIDATE TOKEN (SECURE LOOKUP)
    // ======================================================
    $order_table = $wpdb->prefix . "knx_preorders";

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$order_table}
         WHERE token = %s
           AND status = 'pending'
         LIMIT 1",
        $token
    ));

    if (!$row) {
        return new WP_REST_Response([
            "success" => false,
            "error"   => "invalid_token",
            "message" => "Preorder token is invalid or expired."
        ], 400);
    }

    $cart_id = intval($row->cart_id);

    // ======================================================
    // 4) LOAD CART + ITEMS + COMPUTE INTERNAL TOTALS
    // ======================================================
    $totals = knx_compute_secure_totals($cart_id, $tip);

    if (!$totals['success']) {
        return new WP_REST_Response($totals, 400);
    }

    $grand_total = $totals['grand_total']; // final amount in USD
    $stripe_amount = intval(round($grand_total * 100)); // convert to cents

    // ======================================================
    // 5) STRIPE → CREATE PAYMENT INTENT (SECURE)
    // ======================================================
    $stripe_secret = knx_get_stripe_secret_key();
    if (!$stripe_secret) {
        return new WP_REST_Response([
            "success" => false,
            "error"   => "stripe_key_missing",
            "message" => "Stripe is not configured."
        ], 500);
    }

    // Load Stripe
    if (!class_exists('\Stripe\Stripe')) {
        require_once KNX_PATH . "vendor/stripe/init.php";
    }

    \Stripe\Stripe::setApiKey($stripe_secret);

    try {
        $intent = \Stripe\PaymentIntent::create([
            "amount"               => $stripe_amount,
            "currency"             => "usd",
            "metadata" => [
                "cart_id"      => $cart_id,
                "user_id"      => $user_id,
                "preorder_tok" => $token,
            ],
            "description" => "Local Bites Order #{$cart_id}",
        ]);
    }
    catch (Exception $e) {
        return new WP_REST_Response([
            "success" => false,
            "error"   => "stripe_failure",
            "message" => $e->getMessage()
        ], 500);
    }

    // ======================================================
    // 6) UPDATE PREORDER RECORD WITH INTENT ID
    // ======================================================
    $wpdb->update(
        $order_table,
        [
            "stripe_intent_id" => $intent->id,
            "updated_at"       => current_time('mysql'),
        ],
        ["id" => $row->id],
        ["%s", "%s"],
        ["%d"]
    );

    // ======================================================
    // 7) RETURN SUCCESS
    // ======================================================
    return new WP_REST_Response([
        "success"          => true,
        "client_secret"    => $intent->client_secret,
        "stripe_intent_id" => $intent->id,
        "order_total"      => $grand_total,
        "breakdown"        => [
            "subtotal"     => $totals['subtotal'],
            "delivery"     => $totals['delivery_fee'],
            "service_fee"  => $totals['service_fee'],
            "taxes"        => $totals['taxes'],
            "tip"          => $totals['tip'],
        ]
    ], 200);
}
