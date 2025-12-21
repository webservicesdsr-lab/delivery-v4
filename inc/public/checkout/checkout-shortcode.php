<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Checkout Shortcode (Production v3)
 * Shortcode: [knx_checkout]
 * ----------------------------------------------------------
 * Responsibilities:
 * - Guard access via KNX session (no guests allowed)
 * - Resolve active cart from DB using "knx_cart_token" cookie
 * - Render Uber-style summary (items, hub, subtotal)
 * - Load checkout CSS + JS (collapsible + payment flow)
 *
 * NOTE:
 * - This page DOES NOT calculate delivery/tax/fees.
 * - That logic will live in secure-total + payments layer.
 * ==========================================================
 */

/**
 * Render checkout page (protected, customer-only).
 *
 * @return string
 */
function knx_render_checkout_page() {
    global $wpdb;

    // ------------------------------------------------------
    // 1) Guard access with NEXUS auth (no guests)
    // FIX #1: Use checkout guard to preserve redirect destination
    // ------------------------------------------------------
    if (!function_exists('knx_guard_checkout_page')) {
        // Fallback: if helper is missing, do a hard redirect
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    // Require at least "customer" role + preserve redirect to /checkout
    $session = knx_guard_checkout_page('customer');
    // No fallback needed - knx_guard_checkout_page exits on fail

    // ------------------------------------------------------
    // 2) Resolve session_token from cookie (cart identity)
    // ------------------------------------------------------
    $session_token = '';
    if (!empty($_COOKIE['knx_cart_token'])) {
        $session_token = sanitize_text_field(wp_unslash($_COOKIE['knx_cart_token']));
    }

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    $cart     = null;
    $items    = [];
    $hub      = null;
    $subtotal = 0.0;

    if ($session_token !== '') {
        // Last active cart for this session token
        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_carts}
                 WHERE session_token = %s AND status = 'active'
                 ORDER BY updated_at DESC
                 LIMIT 1",
                $session_token
            )
        );

        if ($cart) {
            // Optional: If cart is not yet attached, bind it to this customer
            if (empty($cart->customer_id) && !empty($session->user_id)) {
                $wpdb->update(
                    $table_carts,
                    ['customer_id' => (int) $session->user_id],
                    ['id' => (int) $cart->id],
                    ['%d'],
                    ['%d']
                );
            }

            // Fetch items
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_cart_items}
                     WHERE cart_id = %d
                     ORDER BY id ASC",
                    $cart->id
                )
            );

            if ($items) {
                foreach ($items as $line) {
                    $line_total = isset($line->line_total) ? (float) $line->line_total : 0.0;
                    $subtotal  += $line_total;
                }
            }

            // Basic hub data
            if (!empty($cart->hub_id)) {
                $hub = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, name, address, phone, logo_url
                         FROM {$table_hubs}
                         WHERE id = %d",
                        $cart->hub_id
                    )
                );
            }
        }
    }

    // ------------------------------------------------------
    // 3) Resolve display name for current customer
    //    (this is KNX user, not WP user, but we keep it simple)
    // ------------------------------------------------------
    $customer_label = '';
    if (!empty($session->username)) {
        $customer_label = $session->username;
    } elseif (!empty($session->email)) {
        $customer_label = $session->email;
    }

    // ------------------------------------------------------
    // 4) Output HTML
    // ------------------------------------------------------
    ob_start();
    ?>

    <link rel="stylesheet"
          href="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-style.css?v=' . KNX_VERSION); ?>">

    <script src="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-script.js?v=' . KNX_VERSION); ?>"
            defer></script>

    <script src="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-payment-flow.js?v=' . KNX_VERSION); ?>"
            defer></script>

    <div id="knx-checkout"
         data-session-token="<?php echo esc_attr($session_token); ?>"
         data-cart-id="<?php echo esc_attr($cart ? (int) $cart->id : 0); ?>"
         data-hub-id="<?php echo esc_attr($cart ? (int) $cart->hub_id : 0); ?>">

        <!-- HEADER -->
        <header class="knx-co-header">
            <div class="knx-co-header__title">
                <h1>Checkout</h1>

                <?php if ($hub): ?>
                    <p class="knx-co-header__subtitle">
                        Ordering from
                        <strong><?php echo esc_html($hub->name); ?></strong>
                    </p>
                <?php else: ?>
                    <p class="knx-co-header__subtitle">
                        Review your items before we calculate your secure total.
                    </p>
                <?php endif; ?>
            </div>

            <div class="knx-co-header__meta">
                <?php if ($customer_label): ?>
                    <span class="knx-co-header__user">
                        Logged in as <strong><?php echo esc_html($customer_label); ?></strong>
                    </span>
                <?php endif; ?>

                <a href="<?php echo esc_url(site_url('/cart-page')); ?>"
                   class="knx-co-header__link">
                    Back to cart
                </a>
            </div>
        </header>

        <div class="knx-co-layout">
            <!-- MAIN COLUMN -->
            <section class="knx-co-main">
                <div class="knx-co-card knx-co-card--summary">
                    <div class="knx-co-card__head">
                        <h2>Order summary</h2>

                        <?php if (!empty($items)): ?>
                            <span class="knx-co-pill">
                                <?php echo count($items); ?> item<?php echo count($items) === 1 ? '' : 's'; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$cart || empty($items)): ?>
                        <div class="knx-co-empty">
                            <p>Your cart is empty or has expired.</p>
                            <a href="<?php echo esc_url(site_url('/explore-hubs')); ?>"
                               class="knx-co-btn knx-co-btn--ghost">
                                Browse restaurants
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="knx-co-items">
                            <?php foreach ($items as $line): ?>
                                <?php
                                $name       = isset($line->name_snapshot) ? $line->name_snapshot : '';
                                $image      = isset($line->image_snapshot) ? $line->image_snapshot : '';
                                $qty        = isset($line->quantity) ? (int) $line->quantity : 1;
                                $unit_price = isset($line->unit_price) ? (float) $line->unit_price : 0.0;
                                $line_total = isset($line->line_total) ? (float) $line->line_total : ($unit_price * $qty);

                                $mods_text = '';
                                if (!empty($line->modifiers_json)) {
                                    $mods = json_decode($line->modifiers_json, true);
                                    if (is_array($mods) && !empty($mods)) {
                                        $parts = [];
                                        foreach ($mods as $m) {
                                            if (empty($m['options']) || !is_array($m['options'])) {
                                                continue;
                                            }
                                            $opt_names = array_map(
                                                static function ($o) {
                                                    return isset($o['name']) ? $o['name'] : '';
                                                },
                                                $m['options']
                                            );
                                            $label = (isset($m['name']) ? $m['name'] . ': ' : '') . implode(', ', $opt_names);
                                            $parts[] = $label;
                                        }
                                        $mods_text = implode(' • ', $parts);
                                    }
                                }
                                ?>
                                <article class="knx-co-item">
                                    <?php if (!empty($image)): ?>
                                        <div class="knx-co-item__img">
                                            <img src="<?php echo esc_url($image); ?>"
                                                 alt="<?php echo esc_attr($name); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="knx-co-item__body">
                                        <div class="knx-co-item__row">
                                            <h3 class="knx-co-item__name">
                                                <?php echo esc_html($qty . '× ' . $name); ?>
                                            </h3>
                                            <div class="knx-co-item__price">
                                                $<?php echo number_format($line_total, 2); ?>
                                            </div>
                                        </div>

                                        <?php if ($mods_text): ?>
                                            <div class="knx-co-item__mods">
                                                <?php echo esc_html($mods_text); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="knx-co-totals">
                            <div class="knx-co-totals__row">
                                <span>Items subtotal</span>
                                <strong>$<?php echo number_format($subtotal, 2); ?></strong>
                            </div>

                            <button type="button"
                                    class="knx-co-disclaimer-toggle"
                                    data-co-toggle="fees">
                                <span>How are taxes and fees calculated?</span>
                                <span class="knx-co-disclaimer-icon">⌄</span>
                            </button>

                            <div class="knx-co-disclaimer" data-co-panel="fees">
                                <p>
                                    Taxes, delivery and service fees are calculated securely
                                    on the backend using your delivery area and current promos.
                                    Formulas are never exposed in the public frontend.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- DELIVERY / NOTES BLOCK (placeholder for future address forms) -->
                <div class="knx-co-card knx-co-card--delivery">
                    <div class="knx-co-card__head">
                        <h2>Delivery details</h2>
                    </div>
                    <div class="knx-co-card__body">
                        <p class="knx-co-note">
                            Delivery address, drop-off instructions and contact details
                            will be added in the next iteration. For now this block is a
                            visual placeholder so we can plug coverage checks later.
                        </p>
                    </div>
                </div>
            </section>

            <!-- SIDEBAR -->
            <aside class="knx-co-sidebar">
                <div class="knx-co-card knx-co-card--payment">
                    <div class="knx-co-card__head">
                        <h2>Place order</h2>
                    </div>
                    <div class="knx-co-card__body">
                        <?php if (!$cart || empty($items)): ?>
                            <p class="knx-co-note">
                                Add at least one item to continue.
                            </p>
                            <a href="<?php echo esc_url(site_url('/explore-hubs')); ?>"
                               class="knx-co-btn knx-co-btn--primary knx-co-btn--full">
                                Browse restaurants
                            </a>
                        <?php else: ?>
                            <div class="knx-co-sidebar-row">
                                <span>Items subtotal</span>
                                <strong>$<?php echo number_format($subtotal, 2); ?></strong>
                            </div>

                            <div class="knx-co-sidebar-row knx-co-sidebar-row--muted">
                                <span>Estimated total</span>
                                <strong>Calculated next</strong>
                            </div>

                            <button type="button"
                                    class="knx-co-btn knx-co-btn--primary knx-co-btn--full"
                                    id="knxCoPlaceOrderBtn">
                                Continue to secure total
                            </button>

                            <p class="knx-co-fineprint">
                                On the next step we will apply delivery, taxes and fees using backend rules
                                before sending the order to the kitchen.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('knx_checkout', 'knx_render_checkout_page');
