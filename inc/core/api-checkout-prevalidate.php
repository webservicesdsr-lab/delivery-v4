<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Checkout Pre-Validation API (Production)
 * Endpoint:
 *   POST /wp-json/knx/v1/checkout/prevalidate
 * ----------------------------------------------------------
 * Secures the checkout by validating:
 * - Session token (guest or logged-in)
 * - Cart existence in DB
 * - Cart has items
 * - Hub exists
 * - Hub is open at this moment
 * - Subtotal integrity
 *
 * Returns a safe payload used by checkout-payment-flow.js
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/checkout/prevalidate', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_checkout_prevalidate',
        'permission_callback' => '__return_true',
    ]);
});


function knx_api_checkout_prevalidate(WP_REST_Request $req) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // ----------------------------------------
    // 1) Extract session_token from request
    // ----------------------------------------
    $body = $req->get_json_params();
    $session_token = isset($body['session_token'])
        ? sanitize_text_field($body['session_token'])
        : '';

    if ($session_token === '') {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_session',
            'message' => 'No session token provided.'
        ], 400);
    }

    // ----------------------------------------
    // 2) Get active cart for this session
    // ----------------------------------------
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts}
         WHERE session_token = %s AND status = 'active'
         ORDER BY updated_at DESC
         LIMIT 1",
        $session_token
    ));

    if (!$cart) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_not_found',
            'message' => 'Cart does not exist or expired.'
        ], 404);
    }

    // ----------------------------------------
    // 3) Fetch cart items
    // ----------------------------------------
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

    // ----------------------------------------
    // 4) Fetch hub info
    // ----------------------------------------
    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, hours_json, temporary_closed_until 
         FROM {$table_hubs}
         WHERE id = %d",
        $cart->hub_id
    ));

    if (!$hub) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'hub_not_found',
            'message' => 'Hub does not exist.'
        ], 404);
    }

    // ----------------------------------------
    // 5) Validate that hub is open (using hours engine)
    // ----------------------------------------
    if (function_exists('knx_hub_is_open')) {
        $is_open = knx_hub_is_open($hub->id);

        if (!$is_open) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'hub_closed',
                'message' => 'The restaurant is currently closed.'
            ], 400);
        }
    }

    // ----------------------------------------
    // 6) Validate subtotal consistency
    // ----------------------------------------
    $computed_subtotal = 0.0;

    foreach ($items as $line) {
        $line_total = isset($line->line_total)
            ? (float) $line->line_total
            : 0;

        $computed_subtotal += $line_total;
    }

    // Allow small floating discrepancies (Stripe level)
    if (abs($computed_subtotal - (float)$cart->subtotal) > 0.05) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'subtotal_mismatch',
            'message' => 'Subtotal mismatch detected.'
        ], 400);
    }

    // ----------------------------------------
    // 7) Build sanitized response for frontend
    // ----------------------------------------
    return new WP_REST_Response([
        'success'   => true,
        'cart_id'   => (int) $cart->id,
        'hub_id'    => (int) $hub->id,
        'hub_name'  => $hub->name,
        'subtotal'  => round($computed_subtotal, 2),
        'next_step' => 'ready_for_payment',
        'message'   => 'Cart validated successfully.'
    ], 200);
}
