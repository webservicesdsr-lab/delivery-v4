<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KINGDOM NEXUS - PAYMENTS HELPERS (Production v1)
 * ----------------------------------------------------------
 * Provides secure backend-only engines used by:
 *   - checkout pre-validation
 *   - Stripe PaymentIntent creation
 *
 * Contains:
 *   ✔ Stripe key getters (from secure settings table)
 *   ✔ Private totals computation engine
 *   ✔ Delivery fee engine
 *   ✔ Service fee engine
 *   ✔ Tax engine
 *   ✔ Anti-tampering protections
 * ==========================================================
 */


/* ==========================================================
   1) STRIPE SECRET KEY LOADER (SECURE)
========================================================== */

/**
 * Loads the Stripe Secret Key stored securely in DB.
 * Admin config page will save into: wp_knx_settings → stripe_secret
 */
function knx_get_stripe_secret_key() {
    global $wpdb;

    $table = $wpdb->prefix . "knx_settings";
    $row = $wpdb->get_var("SELECT stripe_secret FROM {$table} LIMIT 1");

    if (!$row || strlen(trim($row)) < 10) {
        return null;
    }
    return trim($row);
}

/**
 * Public key (for front-end Stripe.js)
 */
function knx_get_stripe_public_key() {
    global $wpdb;

    $table = $wpdb->prefix . "knx_settings";
    $row = $wpdb->get_var("SELECT stripe_public FROM {$table} LIMIT 1");

    if (!$row || strlen(trim($row)) < 10) {
        return null;
    }
    return trim($row);
}



/* ==========================================================
   2) SAFE TOTALS ENGINE
========================================================== */

/**
 * Computes ALL totals (INTERNALLY + PRIVATELY)
 * Never exposes tax formulas or fee logic.
 *
 * Returns:
 *  [
 *     success      => true,
 *     subtotal     => 19.99,
 *     delivery_fee => 4.50,
 *     service_fee  => 1.99,
 *     taxes        => 2.18,
 *     tip          => 2.00,
 *     grand_total  => 30.66
 *  ]
 */
function knx_compute_secure_totals($cart_id, $tip = 0.00) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . "knx_carts";
    $table_cart_items = $wpdb->prefix . "knx_cart_items";
    $table_hubs       = $wpdb->prefix . "knx_hubs";

    // ---------------------------
    // Load cart & ensure active
    // ---------------------------
    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts}
         WHERE id = %d AND status = 'active'
         LIMIT 1",
        $cart_id
    ));

    if (!$cart) {
        return [
            "success" => false,
            "error"   => "cart_not_found"
        ];
    }

    // Load items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT line_total FROM {$table_cart_items}
         WHERE cart_id = %d",
        $cart_id
    ));

    if (!$items) {
        return [
            "success" => false,
            "error"   => "cart_empty"
        ];
    }

    // ---------------------------
    // SUBTOTAL
    // ---------------------------
    $subtotal = 0.0;
    foreach ($items as $i) {
        $line = floatval($i->line_total);
        if ($line < 0) $line = 0;
        $subtotal += $line;
    }

    // ---------------------------
    // DELIVERY FEE (private)
    // ---------------------------
    $delivery_fee = knx_safe_delivery_fee_engine($cart->hub_id);

    // ---------------------------
    // SERVICE FEE (private)
    // ---------------------------
    $service_fee = knx_safe_service_fee_engine($subtotal);

    // ---------------------------
    // TAXES (private)
    // ---------------------------
    $taxes = knx_safe_tax_engine($subtotal, $service_fee);

    // ---------------------------
    // TIP (customer-defined)
    // ---------------------------
    $tip = floatval($tip);
    if ($tip < 0) $tip = 0;

    // ---------------------------
    // FINAL TOTAL
    // ---------------------------
    $grand = $subtotal + $delivery_fee + $service_fee + $taxes + $tip;

    return [
        "success"      => true,
        "subtotal"     => round($subtotal, 2),
        "delivery_fee" => round($delivery_fee, 2),
        "service_fee"  => round($service_fee, 2),
        "taxes"        => round($taxes, 2),
        "tip"          => round($tip, 2),
        "grand_total"  => round($grand, 2),
    ];
}



/* ==========================================================
   3) DELIVERY FEE ENGINE (PRIVATE)
   - Based on hub distance radius settings in the DB
   - NEVER reveals logic to frontend
========================================================== */

function knx_safe_delivery_fee_engine($hub_id) {
    global $wpdb;

    $table_hubs = $wpdb->prefix . "knx_hubs";

    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT delivery_fee_json
         FROM {$table_hubs}
         WHERE id = %d",
        $hub_id
    ));

    if (!$hub) return 0.00;

    // delivery_fee_json example:
    // [{"min":0,"max":5,"fee":2.99},{"min":5,"max":12,"fee":4.99},{"min":12,"max":999,"fee":7.99}]
    // Your new version should compute distance user→hub, but until address module is ready:
    // fallback fee = middle range.

    $rules = json_decode($hub->delivery_fee_json, true);

    if (!is_array($rules)) {
        return 3.99; // fallback safe default
    }

    // TEMPORARY: Default to first tier to avoid exposing distance logic
    $tier = $rules[0];
    return isset($tier['fee']) ? floatval($tier['fee']) : 3.99;
}



/* ==========================================================
   4) SERVICE FEE ENGINE (PRIVATE)
   - You choose how much to charge
   - Hidden from frontend
========================================================== */

function knx_safe_service_fee_engine($subtotal) {
    // Example: 6% service fee with a $0.50 floor
    // You can tune this anytime without touching frontend.
    $fee = $subtotal * 0.06;
    if ($fee < 0.50) $fee = 0.50;

    return $fee;
}



/* ==========================================================
   5) TAX ENGINE (PRIVATE)
   - US style: municipal + county + state combined percentage
   - Hidden from frontend
========================================================== */

function knx_safe_tax_engine($subtotal, $service_fee) {
    // Example: 8.75% combined tax
    $rate = 0.0875;

    $base = $subtotal + $service_fee;
    $tax  = $base * $rate;

    if ($tax < 0) $tax = 0;
    return $tax;
}
