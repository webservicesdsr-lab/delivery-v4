<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Orders Model (Enterprise Production v1)
 * ----------------------------------------------------------
 * CENTRALIZED DATABASE ACCESS FOR ORDERS
 *
 * Provides:
 *  - Create order
 *  - Read order
 *  - Read order items
 *  - Change order status
 *  - List orders by customer / hub / driver
 *
 * This file prevents duplicated SQL and keeps logic consistent.
 * ==========================================================
 */

class KNX_Orders_Model {

    /**
     * Return the correct table names automatically.
     */
    public static function tables() {
        global $wpdb;
        return (object)[
            'orders'      => $wpdb->prefix . 'knx_orders',
            'order_items' => $wpdb->prefix . 'knx_order_items',
            'carts'       => $wpdb->prefix . 'knx_carts',
            'cart_items'  => $wpdb->prefix . 'knx_cart_items',
            'hubs'        => $wpdb->prefix . 'knx_hubs',
            'users'       => $wpdb->prefix . 'knx_users'
        ];
    }

    /**
     * ======================================================
     * CREATE ORDER
     * Called after Stripe confirms the payment.
     * ======================================================
     *
     * @param int    $cart_id
     * @param string $payment_intent_id
     *
     * @return int|false Order ID
     */
    public static function create_order($cart_id, $payment_intent_id) {
        global $wpdb;
        $t = self::tables();

        // Fetch cart
        $cart = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t->carts} WHERE id = %d AND status = 'active' LIMIT 1",
            $cart_id
        ));

        if (!$cart) return false;

        // Fetch cart items
        $cart_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t->cart_items} WHERE cart_id = %d ORDER BY id ASC",
            $cart_id
        ));

        if (empty($cart_items)) return false;

        $now = current_time('mysql');

        // Create order
        $inserted = $wpdb->insert(
            $t->orders,
            [
                'customer_id'        => $cart->customer_id,
                'hub_id'             => $cart->hub_id,
                'subtotal'           => $cart->subtotal,
                'payment_intent_id'  => sanitize_text_field($payment_intent_id),
                'status'             => 'pending',     // default state
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            ['%d','%d','%f','%s','%s','%s','%s']
        );

        if (!$inserted) return false;

        $order_id = $wpdb->insert_id;

        // Insert order items
        foreach ($cart_items as $item) {
            $wpdb->insert(
                $t->order_items,
                [
                    'order_id'       => $order_id,
                    'item_id'        => $item->item_id,
                    'name_snapshot'  => $item->name_snapshot,
                    'image_snapshot' => $item->image_snapshot,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'line_total'     => $item->line_total,
                    'modifiers_json' => $item->modifiers_json,
                    'created_at'     => $now,
                ],
                ['%d','%d','%s','%s','%d','%f','%f','%s','%s']
            );
        }

        // Mark cart as "converted"
        $wpdb->update(
            $t->carts,
            ['status' => 'converted', 'updated_at' => $now],
            ['id' => $cart_id],
            ['%s','%s'],
            ['%d']
        );

        return $order_id;
    }

    /**
     * ======================================================
     * GET ORDER
     * Returns full order info (without items).
     * ======================================================
     */
    public static function get_order($order_id) {
        global $wpdb;
        $t = self::tables();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t->orders} WHERE id = %d LIMIT 1",
            $order_id
        ));
    }

    /**
     * ======================================================
     * GET ORDER ITEMS
     * ======================================================
     */
    public static function get_order_items($order_id) {
        global $wpdb;
        $t = self::tables();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t->order_items} WHERE order_id = %d ORDER BY id ASC",
            $order_id
        ));
    }

    /**
     * ======================================================
     * CHANGE ORDER STATUS
     * (pending → accepted → preparing → on_the_way → delivered)
     * ======================================================
     */
    public static function update_status($order_id, $new_status) {
        global $wpdb;
        $t = self::tables();

        $allowed = ['pending','accepted','preparing','on_the_way','delivered','cancelled'];

        if (!in_array($new_status, $allowed, true)) {
            return false;
        }

        return $wpdb->update(
            $t->orders,
            [
                'status'     => sanitize_text_field($new_status),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id],
            ['%s','%s'],
            ['%d']
        );
    }

    /**
     * ======================================================
     * LIST ORDERS BY CUSTOMER
     * ======================================================
     */
    public static function get_orders_by_customer($customer_id) {
        global $wpdb;
        $t = self::tables();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$t->orders}
             WHERE customer_id = %d
             ORDER BY id DESC",
            $customer_id
        ));
    }

    /**
     * ======================================================
     * LIST ORDERS BY HUB (for vendor dashboard)
     * ======================================================
     */
    public static function get_orders_by_hub($hub_id) {
        global $wpdb;
        $t = self::tables();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$t->orders}
             WHERE hub_id = %d
             ORDER BY id DESC",
            $hub_id
        ));
    }

    /**
     * ======================================================
     * GET DRIVER ORDERS (future)
     * ======================================================
     */
    public static function get_orders_for_driver($driver_id) {
        global $wpdb;
        $t = self::tables();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$t->orders}
             WHERE driver_id = %d
             ORDER BY id DESC",
            $driver_id
        ));
    }
}
