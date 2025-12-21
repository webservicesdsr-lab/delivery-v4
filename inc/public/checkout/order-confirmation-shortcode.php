<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Order Confirmation Shortcode (Production)
 * Shortcode: [knx_order_confirmation]
 * ----------------------------------------------------------
 * - Only visible to authenticated KNX users
 * - Ensures the order belongs to the current customer
 * - Displays:
 *      • Order ID + status
 *      • Restaurant (hub) basic info
 *      • Items list (with modifiers)
 *      • Subtotal + taxes/fees if available
 * ==========================================================
 */

/**
 * Ensure Orders Model is available.
 */
if (!class_exists('KNX_Orders_Model') && defined('KNX_PATH')) {
    require_once KNX_PATH . 'inc/core/orders-model.php';
}

add_shortcode('knx_order_confirmation', 'knx_render_order_confirmation_page');

/**
 * Render order confirmation page.
 *
 * @return string
 */
function knx_render_order_confirmation_page() {
    global $wpdb;

    // ------------------------------------------------------
    // 1) Require Nexus session (customer or above)
    // FIX #1: Use checkout guard to preserve redirect destination
    // ------------------------------------------------------
    $session = null;
    if (function_exists('knx_guard_checkout_page')) {
        // This will redirect to /login?redirect=/order-confirmation if not authorized
        $session = knx_guard_checkout_page('customer');
    } elseif (function_exists('knx_get_session')) {
        $session = knx_get_session();
        if (!$session) {
            return '<div id="knx-order-confirmation"><p>You must sign in to view this page.</p></div>';
        }
    } else {
        // If helpers are not loaded, fail safely
        return '<div id="knx-order-confirmation"><p>Auth system not available.</p></div>';
    }

    $customer_id = (int) $session->user_id;

    // ------------------------------------------------------
    // 2) Get order ID from query (?order=123 or ?order_id=123)
    // ------------------------------------------------------
    $order_id = 0;
    if (isset($_GET['order'])) {
        $order_id = (int) $_GET['order'];
    } elseif (isset($_GET['order_id'])) {
        $order_id = (int) $_GET['order_id'];
    }

    if ($order_id <= 0) {
        return '<div id="knx-order-confirmation"><p>Order not found.</p></div>';
    }

    $t = KNX_Orders_Model::tables();

    // ------------------------------------------------------
    // 3) Load order + items using central model
    // ------------------------------------------------------
    $order = KNX_Orders_Model::get_order($order_id);
    if (!$order) {
        return '<div id="knx-order-confirmation"><p>Order not found.</p></div>';
    }

    // The order must belong to the current customer
    if (!empty($order->customer_id) && (int) $order->customer_id !== $customer_id) {
        return '<div id="knx-order-confirmation"><p>Order not found.</p></div>';
    }

    $items = KNX_Orders_Model::get_order_items($order_id);

    // Hub info
    $hub = null;
    if (!empty($order->hub_id)) {
        $hub = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, address, phone, logo_url
             FROM {$t->hubs}
             WHERE id = %d",
            $order->hub_id
        ));
    }

    // ------------------------------------------------------
    // 4) Resolve monetary fields (safe defaults)
    // ------------------------------------------------------
    $subtotal     = isset($order->subtotal)       ? (float) $order->subtotal       : 0.0;
    $tax_amount   = isset($order->tax_amount)     ? (float) $order->tax_amount     : 0.0;
    $delivery_fee = isset($order->delivery_fee)   ? (float) $order->delivery_fee   : 0.0;
    $service_fee  = isset($order->service_fee)    ? (float) $order->service_fee    : 0.0;
    $tip_amount   = isset($order->tip_amount)     ? (float) $order->tip_amount     : 0.0;
    $total_amount = isset($order->total_amount)   ? (float) $order->total_amount   : 0.0;

    if ($total_amount <= 0) {
        $total_amount = $subtotal + $tax_amount + $delivery_fee + $service_fee + $tip_amount;
    }

    $status = isset($order->status) ? (string) $order->status : 'pending';

    // Status label mapping
    $status_labels = [
        'pending'    => 'Pending',
        'accepted'   => 'Accepted by restaurant',
        'preparing'  => 'Being prepared',
        'on_the_way' => 'On the way',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled'
    ];

    $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);

    // ------------------------------------------------------
    // 5) Begin output
    // ------------------------------------------------------
    ob_start();
    ?>

    <!-- Styles for this page -->
    <link rel="stylesheet"
          href="<?php echo esc_url(KNX_URL . 'inc/public/checkout/order-confirmation-style.css?v=' . KNX_VERSION); ?>">

    <div id="knx-order-confirmation">

        <!-- HEADER -->
        <header class="knx-oc-header">
            <div class="knx-oc-header-main">
                <h1>Order #<?php echo esc_html($order_id); ?></h1>
                <p class="knx-oc-subtitle">
                    <?php echo esc_html($status_label); ?>
                </p>
            </div>

            <div class="knx-oc-status-badge knx-oc-status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html($status_label); ?>
            </div>
        </header>

        <div class="knx-oc-layout">

            <!-- LEFT: RESTAURANT + ITEMS -->
            <section class="knx-oc-column">

                <article class="knx-oc-card knx-oc-card--hub">
                    <h2 class="knx-oc-card-title">Restaurant</h2>

                    <?php if ($hub): ?>
                        <div class="knx-oc-hub">
                            <?php if (!empty($hub->logo_url)): ?>
                                <div class="knx-oc-hub-logo">
                                    <img src="<?php echo esc_url($hub->logo_url); ?>"
                                         alt="<?php echo esc_attr($hub->name); ?>">
                                </div>
                            <?php endif; ?>

                            <div class="knx-oc-hub-info">
                                <h3><?php echo esc_html($hub->name); ?></h3>

                                <?php if (!empty($hub->address)): ?>
                                    <p class="knx-oc-hub-line">
                                        <?php echo esc_html($hub->address); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($hub->phone)): ?>
                                    <p class="knx-oc-hub-line">
                                        Phone: <?php echo esc_html($hub->phone); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="knx-oc-note">Restaurant information is not available.</p>
                    <?php endif; ?>
                </article>

                <article class="knx-oc-card knx-oc-card--items">
                    <h2 class="knx-oc-card-title">Items</h2>

                    <?php if (empty($items)): ?>
                        <p class="knx-oc-note">This order has no items.</p>
                    <?php else: ?>
                        <div class="knx-oc-items">
                            <?php foreach ($items as $line): ?>
                                <?php
                                $qty        = isset($line->quantity) ? (int) $line->quantity : 1;
                                $name       = isset($line->name_snapshot) ? $line->name_snapshot : '';
                                $img        = isset($line->image_snapshot) ? $line->image_snapshot : '';
                                $line_total = isset($line->line_total) ? (float) $line->line_total : 0.0;

                                $mods_text = '';
                                if (!empty($line->modifiers_json)) {
                                    $mods = json_decode($line->modifiers_json, true);
                                    if (is_array($mods) && !empty($mods)) {
                                        $parts = [];
                                        foreach ($mods as $m) {
                                            if (empty($m['options']) || !is_array($m['options'])) {
                                                continue;
                                            }
                                            $opt_names = [];
                                            foreach ($m['options'] as $opt) {
                                                if (!empty($opt['name'])) {
                                                    $opt_names[] = $opt['name'];
                                                }
                                            }
                                            if (!empty($opt_names)) {
                                                $label = (isset($m['name']) ? $m['name'] . ': ' : '') . implode(', ', $opt_names);
                                                $parts[] = $label;
                                            }
                                        }
                                        if (!empty($parts)) {
                                            $mods_text = implode(' • ', $parts);
                                        }
                                    }
                                }
                                ?>
                                <div class="knx-oc-item">
                                    <?php if (!empty($img)): ?>
                                        <div class="knx-oc-item-img">
                                            <img src="<?php echo esc_url($img); ?>"
                                                 alt="<?php echo esc_attr($name); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="knx-oc-item-body">
                                        <div class="knx-oc-item-row">
                                            <h3><?php echo esc_html($qty . '× ' . $name); ?></h3>
                                            <span class="knx-oc-item-price">
                                                $<?php echo number_format($line_total, 2); ?>
                                            </span>
                                        </div>

                                        <?php if ($mods_text): ?>
                                            <div class="knx-oc-item-mods">
                                                <?php echo esc_html($mods_text); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <!-- RIGHT: TOTAL SUMMARY -->
            <aside class="knx-oc-sidebar">
                <div class="knx-oc-card knx-oc-card--summary">
                    <h2 class="knx-oc-card-title">Summary</h2>

                    <div class="knx-oc-summary-row">
                        <span>Items subtotal</span>
                        <strong>$<?php echo number_format($subtotal, 2); ?></strong>
                    </div>

                    <?php if ($tax_amount > 0): ?>
                        <div class="knx-oc-summary-row">
                            <span>Tax</span>
                            <span>$<?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($delivery_fee > 0): ?>
                        <div class="knx-oc-summary-row">
                            <span>Delivery fee</span>
                            <span>$<?php echo number_format($delivery_fee, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($service_fee > 0): ?>
                        <div class="knx-oc-summary-row">
                            <span>Service fee</span>
                            <span>$<?php echo number_format($service_fee, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($tip_amount > 0): ?>
                        <div class="knx-oc-summary-row">
                            <span>Tip</span>
                            <span>$<?php echo number_format($tip_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="knx-oc-summary-row knx-oc-summary-row--total">
                        <span>Total charged</span>
                        <strong>$<?php echo number_format($total_amount, 2); ?></strong>
                    </div>

                    <p class="knx-oc-fineprint">
                        A receipt has been created for this order.  
                        You can always review it later from your account.
                    </p>

                    <a href="<?php echo esc_url(site_url('/explore-hubs')); ?>"
                       class="knx-oc-btn-primary">
                        Back to restaurants
                    </a>
                </div>
            </aside>

        </div><!-- .knx-oc-layout -->

    </div><!-- #knx-order-confirmation -->

    <?php
    return ob_get_clean();
}
