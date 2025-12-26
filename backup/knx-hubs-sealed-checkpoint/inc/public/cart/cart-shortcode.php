<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - CART PAGE SHORTCODE (Production)
 * Shortcode: [knx_cart_page]
 * ----------------------------------------------------------
 * - Reads active cart from DB (knx_carts / knx_cart_items)
 * - Resolves by session_token (cookie knx_cart_token)
 * - Shows UberEats-style summary (no fees / taxes exposed)
 * ==========================================================
 */

/**
 * Register shortcode.
 */
add_shortcode('knx_cart_page', 'knx_render_cart_page');

/**
 * Render cart page.
 *
 * @return string
 */
function knx_render_cart_page() {
    /** @var wpdb $wpdb */
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // Basic safety: check that main cart table exists
    if (!knx_cart_tables_exist($table_carts, $table_cart_items)) {
        return '<div id="knx-cart-page"><p>Cart engine is not available.</p></div>';
    }

    // Resolve session token from KNX helper or cookie
    $session_token = '';
    if (function_exists('knx_get_cart_token')) {
        $session_token = knx_get_cart_token();
    } else {
        if (!empty($_COOKIE['knx_cart_token'])) {
            $session_token = sanitize_text_field(wp_unslash($_COOKIE['knx_cart_token']));
        }
    }

    if ($session_token === '') {
        // No token -> treat as empty cart
        return knx_cart_page_render_html(null, [], 0.0, null);
    }

    // Find latest active cart for this session
    $cart_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT c.*, h.name AS hub_name, h.slug AS hub_slug
             FROM {$table_carts} AS c
             LEFT JOIN {$table_hubs} AS h ON h.id = c.hub_id
             WHERE c.session_token = %s
               AND c.status = 'active'
             ORDER BY c.updated_at DESC
             LIMIT 1",
            $session_token
        )
    );

    if (!$cart_row) {
        // No active cart
        return knx_cart_page_render_html(null, [], 0.0, null);
    }

    // Fetch items
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_cart_items}
             WHERE cart_id = %d
             ORDER BY id ASC",
            $cart_row->id
        )
    );

    if (!$items) {
        return knx_cart_page_render_html($cart_row, [], 0.0, $cart_row->hub_name ?? null);
    }

    // Recalculate subtotal from items (trust DB but recalc for safety)
    $subtotal = 0.0;
    foreach ($items as $item) {
        $line = (float) $item->line_total;
        if ($line < 0) {
            $line = (float) $item->unit_price * (int) $item->quantity;
        }
        $subtotal += $line;
    }

    return knx_cart_page_render_html($cart_row, $items, $subtotal, $cart_row->hub_name ?? null);
}

/**
 * Build final HTML for cart page.
 *
 * @param object|null $cart_row
 * @param array       $items
 * @param float       $subtotal
 * @param string|null $hub_name
 * @return string
 */
function knx_cart_page_render_html($cart_row, $items, $subtotal, $hub_name) {
    ob_start();

    // Load cart stylesheet (path can be adjusted if needed)
    ?>
    <link rel="stylesheet"
          href="<?php echo esc_url(KNX_URL . 'inc/public/cart/cart-style.css?v=' . KNX_VERSION); ?>">

    <div id="knx-cart-page">
        <h1 class="knx-cart-page__title">
            <?php echo esc_html__('Your cart', 'kingdom-nexus'); ?>
        </h1>

        <?php if (!empty($hub_name)) : ?>
            <p style="text-align:center; margin-top:-14px; margin-bottom:22px; font-size:0.9rem; color:#6b7280;">
                <?php
                printf(
                    /* translators: %s is hub name */
                    esc_html__('Ordering from %s', 'kingdom-nexus'),
                    '<strong>' . esc_html($hub_name) . '</strong>'
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if (empty($items)) : ?>
            <div class="knx-cart-empty">
                <i class="fas fa-shopping-basket" aria-hidden="true"></i>
                <p><?php echo esc_html__('Your cart is empty right now.', 'kingdom-nexus'); ?></p>
                <a href="<?php echo esc_url(site_url('/explore-hubs')); ?>"
                   class="knx-cart-empty__btn">
                    <?php echo esc_html__('Browse restaurants', 'kingdom-nexus'); ?>
                </a>
            </div>
        <?php else : ?>

            <div class="knx-cart-items">
                <?php foreach ($items as $item) : ?>
                    <?php
                    $name     = $item->name_snapshot;
                    $img      = $item->image_snapshot;
                    $qty      = (int) $item->quantity;
                    $line     = (float) $item->line_total;
                    $mods_raw = $item->modifiers_json;

                    if ($qty < 1) {
                        $qty = 1;
                    }
                    if ($line < 0) {
                        $line = (float) $item->unit_price * $qty;
                    }

                    $mods_text = '';
                    if (!empty($mods_raw)) {
                        $decoded = json_decode($mods_raw, true);
                        if (is_array($decoded) && !empty($decoded)) {
                            $parts = [];
                            foreach ($decoded as $mod) {
                                if (empty($mod['name']) || empty($mod['options']) || !is_array($mod['options'])) {
                                    continue;
                                }
                                $opt_names = [];
                                foreach ($mod['options'] as $opt) {
                                    if (!empty($opt['name'])) {
                                        $opt_names[] = $opt['name'];
                                    }
                                }
                                if ($opt_names) {
                                    $parts[] = $mod['name'] . ': ' . implode(', ', $opt_names);
                                }
                            }
                            if ($parts) {
                                $mods_text = implode(' • ', $parts);
                            }
                        }
                    }
                    ?>
                    <div class="knx-cart-item">
                        <?php if (!empty($img)) : ?>
                            <div class="knx-cart-item__img">
                                <img src="<?php echo esc_url($img); ?>"
                                     alt="<?php echo esc_attr($name); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="knx-cart-item__body">
                            <div class="knx-cart-item__title">
                                <?php echo esc_html($name); ?>
                            </div>

                            <?php if ($mods_text) : ?>
                                <div class="knx-cart-item__mods">
                                    <?php echo esc_html($mods_text); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="knx-cart-item__meta">
                            <div class="knx-cart-item__price">
                                <?php echo esc_html('$' . number_format_i18n($line, 2)); ?>
                            </div>
                            <div class="knx-cart-item__qty">
                                <?php
                                printf(
                                    /* translators: %d is quantity */
                                    esc_html__('%d×', 'kingdom-nexus'),
                                    $qty
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="knx-cart-summary">
                <div class="knx-cart-summary__line">
                    <span><?php echo esc_html__('Items subtotal', 'kingdom-nexus'); ?></span>
                    <strong><?php echo esc_html('$' . number_format_i18n($subtotal, 2)); ?></strong>
                </div>

                <a href="<?php echo esc_url(site_url('/checkout')); ?>"
                   class="knx-cart-summary__checkout">
                    <?php echo esc_html__('Proceed to checkout', 'kingdom-nexus'); ?>
                </a>
            </div>

        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}
