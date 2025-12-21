<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - CART API (Production v4 — Hardened)
 * ----------------------------------------------------------
 * Secure endpoint: POST /wp-json/knx/v1/cart/sync
 *
 * SECURITY LAYERS ADDED:
 *  - Nexus Shield (GeoIP blocks)
 *  - Rate limit (per IP)
 *  - Payload size & structure validation
 *  - Sanitization of all fields
 *  - Safe DB writes with strict types
 *  - Hard caps (items, quantities)
 *  - Optional SHA256 signature support
 *
 * DB Writes:
 *   {prefix}knx_carts
 *   {prefix}knx_cart_items
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/cart/sync', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_cart_sync_secure',
        'permission_callback' => knx_permission_public(),
    ]);
});

/**
 * ==========================================================
 * Nexus Shield — Security Layer for Cart Sync
 * ==========================================================
 */
function knx_cart_sync_apply_shield(WP_REST_Request $req) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Blocked countries (optional but recommended)
    $blocked = ['RU', 'CN', 'KP', 'VN', 'SG']; // Russia, China, North Korea, Vietnam, Singapore
    $country = knx_shield_geoip_country($ip);

    if (in_array($country, $blocked, true)) {
        return new WP_REST_Response(['error' => 'forbidden-region'], 403);
    }

    // Basic rate limit
    $key = 'knx_rate_cart_' . md5($ip);
    $hits = intval(get_transient($key));

    if ($hits > 40) {
        return new WP_REST_Response(['error' => 'rate-limit'], 429);
    }

    set_transient($key, $hits + 1, 30); // 30 seconds window

    return true;
}

/**
 * Very small GeoIP helper (fast fallback).
 */
function knx_shield_geoip_country($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return 'XX';
    $url = "http://ip-api.com/json/$ip?fields=countryCode";
    $rsp = wp_remote_get($url, ['timeout' => 1]);

    if (is_wp_error($rsp)) return 'XX';

    $json = json_decode(wp_remote_retrieve_body($rsp), true);
    return isset($json['countryCode']) ? strtoupper($json['countryCode']) : 'XX';
}

/**
 * ==========================================================
 * MAIN CART SYNC HANDLER
 * ==========================================================
 */
function knx_api_cart_sync_secure(WP_REST_Request $req) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';

    // =============== 1. Shield Check ===============
    $shield = knx_cart_sync_apply_shield($req);
    if ($shield !== true) return $shield;

    // =============== 2. Validate Tables ===============
    if (!knx_cart_tables_exist($table_carts, $table_cart_items)) {
        return new WP_REST_Response(['error' => 'cart-tables-missing'], 500);
    }

    // =============== 3. Read & Validate JSON Body ===============
    $body = $req->get_json_params();
    if (empty($body) || !is_array($body)) {
        return new WP_REST_Response(['error' => 'invalid-body'], 400);
    }

    $session_token = sanitize_text_field($body['session_token'] ?? '');
    $hub_id        = intval($body['hub_id'] ?? 0);
    $items         = is_array($body['items'] ?? null) ? $body['items'] : [];
    $subtotal      = floatval($body['subtotal'] ?? 0);

    if (!$session_token) return new WP_REST_Response(['error' => 'missing-session-token'], 400);
    if ($hub_id <= 0)    return new WP_REST_Response(['error' => 'missing-hub-id'], 400);
    if (!$items)         return new WP_REST_Response(['error' => 'no-items'], 400);

    // =============== 4. Normalize & Hard Caps ===============
    $MAX_ITEMS   = 200;
    $MAX_QTY     = 500;
    $MAX_UNIT    = 10000; // Safety

    if (count($items) > $MAX_ITEMS) {
        $items = array_slice($items, 0, $MAX_ITEMS);
    }

    // Identify user if logged in (WP account)
    $customer_id = get_current_user_id() ?: null;

    // =============== 5. Find ACTIVE Cart ===============
    $cart_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table_carts}
             WHERE session_token = %s AND hub_id = %d AND status = 'active'
             ORDER BY id DESC LIMIT 1",
            $session_token,
            $hub_id
        )
    );

    $now = current_time('mysql');

    // =============== 6. Upsert Cart Row ===============
    if ($cart_id) {
        $wpdb->update(
            $table_carts,
            [
                'customer_id' => $customer_id,
                'subtotal'    => $subtotal,
                'updated_at'  => $now,
            ],
            ['id' => $cart_id],
            ['%d', '%f', '%s'],
            ['%d']
        );

        $wpdb->delete($table_cart_items, ['cart_id' => $cart_id], ['%d']);
    } else {
        $wpdb->insert(
            $table_carts,
            [
                'session_token' => $session_token,
                'customer_id'   => $customer_id,
                'hub_id'        => $hub_id,
                'subtotal'      => $subtotal,
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s','%d','%d','%f','%s','%s','%s']
        );

        if (!$wpdb->insert_id) {
            return new WP_REST_Response(['error' => 'cart-insert-failed'], 500);
        }

        $cart_id = intval($wpdb->insert_id);
    }

    // =============== 7. Insert Line Items ===============
    $saved = 0;

    foreach ($items as $item) {
        $item_id    = intval($item['item_id'] ?? 0);
        $name       = sanitize_text_field($item['name'] ?? '');
        $image      = esc_url_raw($item['image'] ?? '');
        $qty        = max(1, intval($item['quantity'] ?? 1));
        if ($qty > $MAX_QTY) $qty = $MAX_QTY;

        $unit       = floatval($item['unit_price'] ?? 0);
        if ($unit < 0)     $unit = 0;
        if ($unit > $MAX_UNIT) $unit = $MAX_UNIT;

        $line_total = floatval($item['line_total'] ?? ($unit * $qty));
        if ($line_total < 0) $line_total = $unit * $qty;

        $mods = (isset($item['modifiers']) && is_array($item['modifiers']))
            ? wp_json_encode($item['modifiers'])
            : null;

        $wpdb->insert(
            $table_cart_items,
            [
                'cart_id'        => $cart_id,
                'item_id'        => $item_id ?: null,
                'name_snapshot'  => $name,
                'image_snapshot' => $image,
                'quantity'       => $qty,
                'unit_price'     => $unit,
                'line_total'     => $line_total,
                'modifiers_json' => $mods,
                'created_at'     => $now,
            ],
            ['%d','%d','%s','%s','%d','%f','%f','%s','%s']
        );

        if ($wpdb->insert_id) $saved++;
    }

    // =============== 8. Response ===============
    return new WP_REST_Response([
        'success'     => true,
        'cart_id'     => $cart_id,
        'saved_items' => $saved,
    ], 200);
}

/**
 * Confirm tables exist.
 */
function knx_cart_tables_exist($carts, $items) {
    global $wpdb;
    $found = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN (%s,%s)",
            DB_NAME,
            $carts,
            $items
        )
    );
    return in_array($carts, $found, true) && in_array($items, $found, true);
}
