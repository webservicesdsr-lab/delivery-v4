<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - CART API (Production v3)
 * Endpoint:
 *   POST /wp-json/knx/v1/cart/sync
 * ----------------------------------------------------------
 * Payload (JSON):
 * {
 *   "session_token": "string (required)",
 *   "hub_id": 1,
 *   "items": [
 *     {
 *       "item_id": 1,
 *       "name": "Plain Alfredo Pasta",
 *       "image": "https://...",
 *       "unit_price": 10.50,
 *       "quantity": 2,
 *       "line_total": 21.00,
 *       "modifiers": [...]
 *     }
 *   ],
 *   "subtotal": 21.00
 * }
 *
 * Writes into:
 *   {$wpdb->prefix}knx_carts
 *   {$wpdb->prefix}knx_cart_items
 * ==========================================================
 */

/**
 * Register REST route for cart sync.
 */
add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/cart/sync', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_cart_sync',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Register cron job for abandoned cart cleanup (hourly).
 * This lives here to keep it scoped to the cart module.
 */
add_action('init', 'knx_register_cart_cleanup_cron');

function knx_register_cart_cleanup_cron() {
    if (!wp_next_scheduled('knx_cleanup_carts_event')) {
        wp_schedule_event(time(), 'hourly', 'knx_cleanup_carts_event');
    }
}

add_action('knx_cleanup_carts_event', 'knx_cleanup_abandoned_carts');

/**
 * Mark old active carts as abandoned.
 * Rule:
 *   - status = 'active'
 *   - updated_at < NOW() - 12 hours
 */
function knx_cleanup_abandoned_carts() {
    global $wpdb;
    $table_carts = $wpdb->prefix . 'knx_carts';

    // Safety: table exists?
    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table_carts
        )
    );

    if (!$table_exists) {
        return;
    }

    $hours = 12;

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_carts}
             SET status = 'abandoned'
             WHERE status = 'active'
               AND updated_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $hours
        )
    );
}

/**
 * Handle cart sync request.
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function knx_api_cart_sync(WP_REST_Request $req) {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';

    // Basic guard: tables must exist
    if (!knx_cart_tables_exist($table_carts, $table_cart_items)) {
        return new WP_REST_Response(['error' => 'cart-tables-missing'], 500);
    }

    $body = $req->get_json_params();
    if (empty($body) || !is_array($body)) {
        return new WP_REST_Response(['error' => 'invalid-body'], 400);
    }

    $session_token = isset($body['session_token']) ? sanitize_text_field($body['session_token']) : '';
    $hub_id        = isset($body['hub_id']) ? intval($body['hub_id']) : 0;
    $items         = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
    $subtotal      = isset($body['subtotal']) ? floatval($body['subtotal']) : 0.0;

    if ($session_token === '') {
        return new WP_REST_Response(['error' => 'missing-session-token'], 400);
    }

    if ($hub_id <= 0) {
        return new WP_REST_Response(['error' => 'missing-hub-id'], 400);
    }

    if (empty($items)) {
        return new WP_REST_Response(['error' => 'no-items'], 400);
    }

    // Hard cap on items to avoid malicious huge payloads
    $MAX_ITEMS_PER_CART = 200;
    if (count($items) > $MAX_ITEMS_PER_CART) {
        $items = array_slice($items, 0, $MAX_ITEMS_PER_CART);
    }

    // Map WP user to customer_id (future you can map to knx_users if needed)
    $customer_id = get_current_user_id();
    if (!$customer_id) {
        $customer_id = null;
    }

    // One ACTIVE cart per (session_token, hub_id)
    $cart_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table_carts}
             WHERE session_token = %s AND hub_id = %d AND status = 'active'
             ORDER BY id DESC
             LIMIT 1",
            $session_token,
            $hub_id
        )
    );

    $now = current_time('mysql');

    if ($cart_id) {
        // Update existing cart
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

        // Clear previous items
        $wpdb->delete($table_cart_items, ['cart_id' => $cart_id], ['%d']);
    } else {
        // Insert new cart
        $inserted = $wpdb->insert(
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
            ['%s', '%d', '%d', '%f', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_REST_Response(['error' => 'cart-insert-failed'], 500);
        }

        $cart_id = $wpdb->insert_id;
    }

    // Insert line items
    $saved = 0;

    foreach ($items as $item) {
        // Basic validation and normalization
        $item_id    = isset($item['item_id']) ? intval($item['item_id']) : null;
        $name       = isset($item['name']) ? sanitize_text_field($item['name']) : '';
        $image      = isset($item['image']) ? esc_url_raw($item['image']) : '';
        $quantity   = isset($item['quantity']) ? max(1, intval($item['quantity'])) : 1;
        if ($quantity > 500) {
            $quantity = 500; // hard sanity cap
        }

        $unit_price = isset($item['unit_price']) ? floatval($item['unit_price']) : 0.0;
        if ($unit_price < 0) {
            $unit_price = 0.0;
        }

        $line_total = isset($item['line_total']) ? floatval($item['line_total']) : ($unit_price * $quantity);
        if ($line_total < 0) {
            $line_total = $unit_price * $quantity;
        }

        $modifiers  = isset($item['modifiers']) ? $item['modifiers'] : null;
        $modifiers_json = null;

        if (!empty($modifiers) && is_array($modifiers)) {
            // Optional: you could sanitize inner arrays deeper here
            $modifiers_json = wp_json_encode($modifiers);
        }

        $wpdb->insert(
            $table_cart_items,
            [
                'cart_id'        => $cart_id,
                'item_id'        => $item_id ?: null,
                'name_snapshot'  => $name,
                'image_snapshot' => $image,
                'quantity'       => $quantity,
                'unit_price'     => $unit_price,
                'line_total'     => $line_total,
                'modifiers_json' => $modifiers_json,
                'created_at'     => $now,
            ],
            [
                '%d', // cart_id
                '%d', // item_id
                '%s', // name_snapshot
                '%s', // image_snapshot
                '%d', // quantity
                '%f', // unit_price
                '%f', // line_total
                '%s', // modifiers_json
                '%s', // created_at
            ]
        );

        if ($wpdb->insert_id) {
            $saved++;
        }
    }

    return new WP_REST_Response(
        [
            'success'     => true,
            'cart_id'     => intval($cart_id),
            'saved_items' => $saved,
        ],
        200
    );
}

/**
 * Check if cart tables exist before writing.
 *
 * @param string $table_carts
 * @param string $table_cart_items
 * @return bool
 */
function knx_cart_tables_exist($table_carts, $table_cart_items) {
    global $wpdb;

    $db = DB_NAME;

    $sql = $wpdb->prepare(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = %s
           AND TABLE_NAME IN (%s, %s)",
        $db,
        $table_carts,
        $table_cart_items
    );

    $found = $wpdb->get_col($sql);
    return in_array($table_carts, $found, true) && in_array($table_cart_items, $found, true);
}
