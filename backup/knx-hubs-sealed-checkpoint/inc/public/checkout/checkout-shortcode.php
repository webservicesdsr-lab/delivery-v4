<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Checkout Shortcode (Production)
 * Shortcode: [knx_checkout]
 * ----------------------------------------------------------
 * - Reads active cart from:
 *     {$wpdb->prefix}knx_carts
 *     {$wpdb->prefix}knx_cart_items
 *   using the "knx_cart_token" cookie.
 *
 * - Renders a single-page checkout summary:
 *   • Items list (with modifiers)
 *   • Items subtotal only
 *   • Collapsible explanation for taxes/fees
 *   • Delivery details placeholder
 *   • Comment box for driver / kitchen
 *   • Restaurant info card
 *   • Payment sidebar with "Continue to secure total" button
 *
 * - Does NOT calculate delivery/tax/fees here.
 *   That is done in the secure backend payment flow.
 * ==========================================================
 */

add_shortcode('knx_checkout', 'knx_render_checkout_page');

function knx_render_checkout_page() {
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // ------------------------------------------------------------------
    // 1) Resolve session_token from cookie (same used by JS cart system)
    // ------------------------------------------------------------------
    $session_token = '';
    if (!empty($_COOKIE['knx_cart_token'])) {
        $session_token = sanitize_text_field(wp_unslash($_COOKIE['knx_cart_token']));
    }

    $cart     = null;
    $items    = [];
    $hub      = null;
    $subtotal = 0.0;

    if ($session_token !== '') {
        // Latest active cart for this session_token
        $cart = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_carts}
                 WHERE session_token = %s
                 AND status = 'active'
                 ORDER BY updated_at DESC
                 LIMIT 1",
                $session_token
            )
        );

        if ($cart) {
            // Cart items
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

            // Hub basic info
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

    // Current WP user (for header only; login gate is on /cart-page)
    $current_user  = wp_get_current_user();
    $customer_name = '';
    if ($current_user && $current_user->exists()) {
        $customer_name = $current_user->display_name ?: $current_user->user_login;
    }

    ob_start();
    ?>

<link rel="stylesheet"
      href="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-style.css?v=' . KNX_VERSION); ?>">
<script src="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-script.js?v=' . KNX_VERSION); ?>"
        defer></script>
<script src="<?php echo esc_url(KNX_URL . 'inc/public/checkout/checkout-payment-flow.js?v=' . KNX_VERSION); ?>"
        defer></script>

<div id="knx-checkout"
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
                    Review your items before we calculate your total.
                </p>
            <?php endif; ?>
        </div>

        <div class="knx-co-header__meta">
            <?php if ($customer_name): ?>
                <span class="knx-co-header__user">
                    Logged in as <strong><?php echo esc_html($customer_name); ?></strong>
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
            <!-- ORDER SUMMARY CARD -->
            <div class="knx-co-card knx-co-card--summary">
                <div class="knx-co-card__head">
                    <h2>Order summary</h2>

                    <?php if (!empty($items)): ?>
                        <span class="knx-co-pill">
                            <?php echo count($items); ?> item<?php echo count($items) === 1 ? '' : 's'; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($items)): ?>
                    <div class="knx-co-empty">
                        <p>Your cart is empty or expired.</p>
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
                            $line_total = isset($line->line_total) ? (float) $line->line_total : $unit_price * $qty;

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
                                Taxes, delivery and service fees are calculated securely in the next step
                                using backend rules, your delivery area and current promos. Internal fee
                                formulas are never exposed in the public frontend.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DELIVERY DETAILS (scaffolding only, no logic yet) -->
            <div class="knx-co-card knx-co-card--delivery">
                <div class="knx-co-card__head">
                    <h2>Delivery details</h2>
                </div>
                <div class="knx-co-card__body">
                    <p class="knx-co-note">
                        Delivery address, drop-off instructions and contact details will be configured
                        in the next iterations. For now this block is just a placeholder so we can
                        plug in address forms and coverage checks later.
                    </p>
                </div>
            </div>

            <!-- COMMENT BLOCK -->
            <div class="knx-co-card knx-co-card--comment">
                <div class="knx-co-card__head">
                    <h2>Comment for the driver / kitchen</h2>
                </div>
                <div class="knx-co-card__body">
                    <label for="knxCoComment" class="knx-co-comment-label">
                        Optional note (for example: gate code, extra crispy, ring the bell).
                    </label>
                    <textarea id="knxCoComment"
                              class="knx-co-comment-textarea"
                              rows="3"
                              placeholder="Type your comment here..."></textarea>
                </div>
            </div>

            <!-- RESTAURANT INFO (simple, without hours parsing for now) -->
            <?php if ($hub): ?>
                <div class="knx-co-card knx-co-card--restaurant">
                    <div class="knx-co-card__head">
                        <h2>Restaurant information</h2>
                    </div>
                    <div class="knx-co-card__body knx-co-restaurant-body">
                        <?php if (!empty($hub->logo_url)): ?>
                            <div class="knx-co-restaurant-logo">
                                <img src="<?php echo esc_url($hub->logo_url); ?>"
                                     alt="<?php echo esc_attr($hub->name); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="knx-co-restaurant-meta">
                            <strong><?php echo esc_html($hub->name); ?></strong><br>
                            <?php if (!empty($hub->address)): ?>
                                <span class="knx-co-restaurant-address">
                                    <?php echo esc_html($hub->address); ?>
                                </span><br>
                            <?php endif; ?>
                            <?php if (!empty($hub->phone)): ?>
                                <span class="knx-co-restaurant-phone">
                                    <?php echo esc_html($hub->phone); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- SIDEBAR -->
        <aside class="knx-co-sidebar">
            <div class="knx-co-card knx-co-card--payment">
                <div class="knx-co-card__head">
                    <h2>Place order</h2>
                </div>
                <div class="knx-co-card__body">
                    <?php if (empty($items)): ?>
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
                                id="knxCoPlaceOrderBtn"
                                data-co-cart-id="<?php echo esc_attr($cart ? (int) $cart->id : 0); ?>"
                                data-co-subtotal="<?php echo esc_attr(number_format($subtotal, 2, '.', '')); ?>">
                            Continue to secure total
                        </button>

                        <p class="knx-co-fineprint">
                            On the next step we will apply taxes, delivery and service fees using backend rules
                            before actually placing the order in the kitchen.
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
