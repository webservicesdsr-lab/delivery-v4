<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Secure Total API (Production v1)
 * Endpoint:
 *   POST /wp-json/knx/v1/checkout/secure-total
 * ----------------------------------------------------------
 * Goal:
 * - Second backend step after /checkout/prevalidate
 * - Recompute and return a safe fee breakdown, never exposing formulas
 * - Enforce KNX session (no guest calls, even if cart cookie is stolen)
 *
 * Input (JSON):
 * {
 *   "session_token": "string (optional, fallback to knx_cart_token cookie)"
 * }
 *
 * Output (JSON, success):
 * {
 *   "success": true,
 *   "cart_id": 1,
 *   "hub_id": 10,
 *   "hub_name": "Koi Asian Bistro",
 *   "currency": "usd",
 *   "breakdown": {
 *     "items_subtotal": 25.50,
 *     "delivery_fee": 0.00,
 *     "tax": 0.00,
 *     "service_fee": 0.00,
 *     "processing_fee": 0.00,
 *     "tip_default": 0.00
 *   },
 *   "estimated_total": 25.50,
 *   "next_step": "payment"
 * }
 *
 * For now fees are placeholders (0.00).
 * In next iterations we will plug the real delivery & fee engine here.
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/checkout/secure-total', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_checkout_secure_total',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Compute secure total for a validated cart.
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function knx_api_checkout_secure_total(WP_REST_Request $req) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // ------------------------------------------------------
    // 1) Enforce KNX session (no guest access)
    // ------------------------------------------------------
    if (!function_exists('knx_get_session')) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'session_engine_missing',
            'message' => 'Session engine is not available.'
        ], 500);
    }

    $session = knx_get_session();
    if (!$session) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'unauthorized',
            'message' => 'Login required.'
        ], 401);
    }

    // ------------------------------------------------------
    // 2) Resolve session_token (body or cookie)
    // ------------------------------------------------------
    $body = $req->get_json_params();
    $session_token = '';

    if (!empty($body['session_token'])) {
        $session_token = sanitize_text_field($body['session_token']);
    } elseif (!empty($_COOKIE['knx_cart_token'])) {
        $session_token = sanitize_text_field(wp_unslash($_COOKIE['knx_cart_token']));
    }

    if ($session_token === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_session_token',
            'message' => 'Cart session token is missing.'
        ], 400);
    }

    // ------------------------------------------------------
    // 3) Fetch active cart for this session token
    // ------------------------------------------------------
    $cart = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_carts}
             WHERE session_token = %s AND status = 'active'
             ORDER BY updated_at DESC
             LIMIT 1",
            $session_token
        )
    );

    if (!$cart) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_not_found',
            'message' => 'Active cart not found or expired.'
        ], 404);
    }

    // ------------------------------------------------------
    // 4) Enforce ownership binding:
    //    - If cart has customer_id set and it does NOT match session->user_id => block.
    //    - If cart has no customer_id yet, bind it to this user (first claim wins).
    // ------------------------------------------------------
    $current_user_id = !empty($session->user_id) ? (int) $session->user_id : 0;

    if (!empty($cart->customer_id)) {
        if ((int) $cart->customer_id !== $current_user_id) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'cart_ownership_mismatch',
                'message' => 'This cart belongs to another user.'
            ], 403);
        }
    } elseif ($current_user_id > 0) {
        // First-time claim: attach cart to this user
        $wpdb->update(
            $table_carts,
            ['customer_id' => $current_user_id],
            ['id' => (int) $cart->id],
            ['%d'],
            ['%d']
        );
    }

    // ------------------------------------------------------
    // 5) Fetch items and recompute items_subtotal
    // ------------------------------------------------------
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_cart_items}
             WHERE cart_id = %d
             ORDER BY id ASC",
            $cart->id
        )
    );

    if (empty($items)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'empty_cart',
            'message' => 'The cart has no items.'
        ], 400);
    }

    $items_subtotal = 0.0;

    foreach ($items as $line) {
        $line_total = isset($line->line_total) ? (float) $line->line_total : 0.0;
        if ($line_total < 0) {
            $line_total = 0.0;
        }
        $items_subtotal += $line_total;
    }

    $items_subtotal = round($items_subtotal, 2);

    // Small integrity check vs stored subtotal (defensive)
    $stored_subtotal = isset($cart->subtotal) ? (float) $cart->subtotal : 0.0;
    if (abs($items_subtotal - $stored_subtotal) > 0.05) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'subtotal_mismatch',
            'message' => 'Subtotal mismatch detected while securing total.'
        ], 400);
    }

    // ------------------------------------------------------
    // 6) Fetch hub basic info (for label)
    // ------------------------------------------------------
    $hub = null;
    if (!empty($cart->hub_id)) {
        $hub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name
                 FROM {$table_hubs}
                 WHERE id = %d",
                $cart->hub_id
            )
        );
    }

    if (!$hub) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'hub_not_found',
            'message' => 'Hub not found for this cart.'
        ], 404);
    }

    // ------------------------------------------------------
    // 7) Fee engine placeholder
    //    For now, all fees are 0.00. Later we will plug:
    //    - Delivery zones engine
    //    - Taxes per city/state
    //    - Service + software + processing fees
    //    - Default tip suggestions
    // ------------------------------------------------------
    $delivery_fee    = 0.00;
    $tax             = 0.00;
    $service_fee     = 0.00;
    $processing_fee  = 0.00;
    $tip_default     = 0.00;

    $estimated_total = $items_subtotal
        + $delivery_fee
        + $tax
        + $service_fee
        + $processing_fee
        + $tip_default;

    $estimated_total = round($estimated_total, 2);

    // ------------------------------------------------------
    // 8) (Future) Create payment session / intent
    //    Hook with Stripe via payments-helpers.php here.
    // ------------------------------------------------------
    // Example placeholder:
    // $payment_session_token = null;
    // if (function_exists('knx_payments_create_session')) {
    //     $payment_session_token = knx_payments_create_session(
    //         (int) $cart->id,
    //         $current_user_id,
    //         $estimated_total,
    //         'usd'
    //     );
    // }

    // ------------------------------------------------------
    // 9) Respond with safe breakdown
    // ------------------------------------------------------
    return new WP_REST_Response([
        'success'   => true,
        'cart_id'   => (int) $cart->id,
        'hub_id'    => (int) $hub->id,
        'hub_name'  => $hub->name,
        'currency'  => 'usd',
        'breakdown' => [
            'items_subtotal'  => $items_subtotal,
            'delivery_fee'    => $delivery_fee,
            'tax'             => $tax,
            'service_fee'     => $service_fee,
            'processing_fee'  => $processing_fee,
            'tip_default'     => $tip_default,
        ],
        'estimated_total' => $estimated_total,
        'next_step'       => 'payment',
        // 'payment_session_token' => $payment_session_token, // future
    ], 200);
}
