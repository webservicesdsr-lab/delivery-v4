<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Place Order API (Production v1)
 * Endpoint:
 *   POST /wp-json/knx/v1/orders/place
 * ----------------------------------------------------------
 * Expected JSON body:
 * {
 *   "cart_id": 123,
 *   "payment_intent_id": "pi_XXXX"   // or any gateway transaction id
 * }
 *
 * Flow:
 *  - Requires valid KNX session (knx_session cookie)
 *  - Ensures cart exists, is active and belongs to current customer
 *  - Ensures cart has items
 *  - Prevents duplicate use of same payment_intent_id
 *  - Calls KNX_Orders_Model::create_order(...)
 *  - Marks cart as converted (handled inside model)
 *
 * Returns (on success):
 * {
 *   "success": true,
 *   "order_id": 123,
 *   "redirect_url": "https://.../order-confirmation?order=123"
 * }
 * ==========================================================
 */

/**
 * Make sure Orders Model is loaded.
 */
if (!class_exists('KNX_Orders_Model') && defined('KNX_PATH')) {
    require_once KNX_PATH . 'inc/core/orders-model.php';
}

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/orders/place', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_place_order',
        'permission_callback' => '__return_true', // we validate session inside
    ]);
});

/**
 * Handle "place order" after successful payment.
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function knx_api_place_order(WP_REST_Request $req) {
    global $wpdb;

    // ------------------------------------------------------
    // 1) Require Nexus session (NOT anonymous)
    // ------------------------------------------------------
    $session = function_exists('knx_get_session') ? knx_get_session() : false;
    if (!$session) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'unauthorized',
            'message' => 'You must be logged in to place an order.'
        ], 401);
    }

    $customer_id = (int) $session->user_id;

    // ------------------------------------------------------
    // 2) Read and validate JSON body
    // ------------------------------------------------------
    $body = $req->get_json_params();
    if (empty($body) || !is_array($body)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_body',
            'message' => 'Invalid JSON payload.'
        ], 400);
    }

    $cart_id          = isset($body['cart_id']) ? (int) $body['cart_id'] : 0;
    $payment_intent_id = isset($body['payment_intent_id'])
        ? sanitize_text_field($body['payment_intent_id'])
        : '';

    if ($cart_id <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_cart_id',
            'message' => 'Cart ID is required.'
        ], 400);
    }

    if ($payment_intent_id === '' || strlen($payment_intent_id) < 4) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_payment_intent',
            'message' => 'Payment intent ID is required.'
        ], 400);
    }

    $t = KNX_Orders_Model::tables();

    // ------------------------------------------------------
    // 3) Ensure cart exists, is active and has items
    // ------------------------------------------------------
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$t->carts}
         WHERE id = %d
           AND status = 'active'
         LIMIT 1",
        $cart_id
    ));

    if (!$cart) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_not_found',
            'message' => 'Cart does not exist or is not active.'
        ], 404);
    }

    // Attach customer_id if cart was anonymous but session exists
    if (empty($cart->customer_id) && $customer_id > 0) {
        $wpdb->update(
            $t->carts,
            ['customer_id' => $customer_id],
            ['id' => $cart_id],
            ['%d'],
            ['%d']
        );
        $cart->customer_id = $customer_id;
    }

    // Cart must belong to current customer (if set)
    if (!empty($cart->customer_id) && (int) $cart->customer_id !== $customer_id) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'cart_ownership_mismatch',
            'message' => 'This cart does not belong to the current user.'
        ], 403);
    }

    // Check cart has items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM {$t->cart_items} WHERE cart_id = %d LIMIT 1",
        $cart_id
    ));
    if (empty($items)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'empty_cart',
            'message' => 'Cart has no items.'
        ], 400);
    }

    // ------------------------------------------------------
    // 4) Prevent duplicate processing of same payment_intent
    // ------------------------------------------------------
    $existing_order_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$t->orders}
         WHERE payment_intent_id = %s
         LIMIT 1",
        $payment_intent_id
    ));

    if ($existing_order_id) {
        // Idempotent response: already processed
        $redirect_url = add_query_arg(
            ['order' => (int) $existing_order_id],
            site_url('/order-confirmation')
        );

        return new WP_REST_Response([
            'success'      => true,
            'order_id'     => (int) $existing_order_id,
            'redirect_url' => esc_url_raw($redirect_url),
            'message'      => 'Order already exists for this payment.'
        ], 200);
    }

    // ------------------------------------------------------
    // 5) (Optional future) Verify payment with Stripe here
    // ------------------------------------------------------
    // TODO: In a later phase, call Stripe API with the secret key
    //       and validate that:
    //       - payment_intent_id exists
    //       - status is "succeeded"
    //       - amount_received matches cart total (in cents)
    // For now we trust the workflow from the frontend.

    // ------------------------------------------------------
    // 6) Create order via central model
    // ------------------------------------------------------
    $order_id = KNX_Orders_Model::create_order($cart_id, $payment_intent_id);

    if (!$order_id) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'order_create_failed',
            'message' => 'Could not create order from cart.'
        ], 500);
    }

    // ------------------------------------------------------
    // 7) Build redirect URL for order confirmation page
    // ------------------------------------------------------
    $redirect_url = add_query_arg(
        ['order' => (int) $order_id],
        site_url('/order-confirmation')
    );

    return new WP_REST_Response([
        'success'      => true,
        'order_id'     => (int) $order_id,
        'redirect_url' => esc_url_raw($redirect_url),
        'message'      => 'Order created successfully.'
    ], 200);
}
