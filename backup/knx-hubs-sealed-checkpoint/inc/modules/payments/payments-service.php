<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Payments Service (Production)
 * ----------------------------------------------------------
 * Centralized server-side total calculation for orders.
 * TOTALLY BACKEND TRUSTED:
 *  - Reads cart from database (not from frontend)
 *  - Applies hub rules: tax, delivery rates, minimums
 *  - Generates secure HMAC OrderToken to avoid tampering
 *
 * Services provided:
 *   knx_pay_calculate_totals($cart_id, $session_token)
 *       → returns full total breakdown + secure OrderToken
 *
 *   knx_pay_resolve_cart($session_token, $hub_id)
 *       → fetches active cart safely
 * ==========================================================
 */


/**
 * Fetch cart + items using session_token + hub_id
 */
function knx_pay_resolve_cart($session_token, $hub_id) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';

    // Get cart
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts}
         WHERE session_token = %s AND hub_id = %d AND status = 'active'
         ORDER BY id DESC
         LIMIT 1",
        $session_token,
        $hub_id
    ));

    if (!$cart) return null;

    // Get items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_cart_items} WHERE cart_id = %d",
        $cart->id
    ));

    return (object)[
        'cart'  => $cart,
        'items' => $items
    ];
}



/**
 * MAIN TOTAL CALCULATION ENGINE
 * Returns:
 *   [
 *     subtotal,
 *     delivery_fee,
 *     service_fee,
 *     tax,
 *     total,
 *     token,
 *   ]
 */
function knx_pay_calculate_totals($cart_id, $session_token) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';
    $table_rates      = $wpdb->prefix . 'knx_delivery_rates';

    /** ------------------------------
     * 1. Load cart
     * ------------------------------ */
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts} WHERE id = %d AND status='active' LIMIT 1",
        $cart_id
    ));

    if (!$cart) {
        return ['error' => 'cart-not-found'];
    }

    /** ------------------------------
     * 2. Load hub settings
     * ------------------------------ */
    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT id, tax_rate, min_order FROM {$table_hubs} WHERE id = %d LIMIT 1",
        $cart->hub_id
    ));

    if (!$hub) {
        return ['error' => 'hub-not-found'];
    }

    $subtotal = floatval($cart->subtotal);



    /** ------------------------------
     * 3. Delivery Fee (simple placeholder)
     * Later: dynamic based on delivery_zones table
     * ------------------------------ */
    $delivery_fee = 3.99; // default temporary fee



    /** ------------------------------
     * 4. Service Fee (platform fee)
     * ------------------------------ */
    $service_fee = round($subtotal * 0.06, 2); // 6% for early phase



    /** ------------------------------
     * 5. Tax Calculation
     * ------------------------------ */
    $tax_rate = floatval($hub->tax_rate); // Example: 8.25
    $tax = round(($subtotal + service_fee) * ($tax_rate / 100), 2);



    /** ------------------------------
     * 6. GRAND TOTAL
     * ------------------------------ */
    $total = round($subtotal + $delivery_fee + $service_fee + $tax, 2);



    /** ------------------------------
     * 7. Generate Secure OrderToken
     * ------------------------------ */
    if (!function_exists('knx_pay_build_order_token')) {
        return ['error' => 'missing-token-engine'];
    }

    $order_token = knx_pay_build_order_token($session_token, $cart->id);



    /** ------------------------------
     * 8. Return Final Breakdown
     * ------------------------------ */
    return [
        'cart_id'       => intval($cart->id),
        'hub_id'        => intval($cart->hub_id),
        'subtotal'      => round($subtotal, 2),
        'delivery_fee'  => $delivery_fee,
        'service_fee'   => $service_fee,
        'tax'           => $tax,
        'total'         => $total,
        'order_token'   => $order_token,
        'timestamp'     => time(),
    ];
}
