<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - SECURE TOTAL Shortcode (Production)
 * Shortcode: [knx_secure_total]
 * ----------------------------------------------------------
 * - Muestra confirmación previa al pago
 * - No expone cálculos de taxes/fees
 * - Solo muestra lo necesario antes de enviar a Stripe
 * - Modular: CSS y JS cargados con <link> y <script> (NO enqueue)
 * ==========================================================
 */

add_shortcode('knx_secure_total', 'knx_render_secure_total_page');

function knx_render_secure_total_page() {
    // TASK 2: Require authenticated session with checkout role
    knx_guard_checkout_page('customer');
    
    global $wpdb;

    $table_carts      = $wpdb->prefix . 'knx_carts';
    $table_cart_items = $wpdb->prefix . 'knx_cart_items';
    $table_hubs       = $wpdb->prefix . 'knx_hubs';

    // ==========================================================
    // 1) Resolve session token → find active cart
    // ==========================================================
    $token = isset($_COOKIE['knx_cart_token'])
        ? sanitize_text_field($_COOKIE['knx_cart_token'])
        : '';

    if ($token === '') {
        return "<div id='knx-secure-total'><p>No active session.</p></div>";
    }

    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_carts}
         WHERE session_token = %s AND status = 'active'
         ORDER BY updated_at DESC
         LIMIT 1",
        $token
    ));

    if (!$cart) {
        return "<div id='knx-secure-total'><p>Your cart has expired.</p></div>";
    }

    // Load items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_cart_items}
         WHERE cart_id = %d ORDER BY id ASC",
        $cart->id
    ));

    if (!$items) {
        return "<div id='knx-secure-total'><p>Your cart is empty.</p></div>";
    }

    // Load hub
    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, address, phone FROM {$table_hubs} WHERE id = %d",
        $cart->hub_id
    ));

    // ==========================================================
    // 2) Begin markup
    // ==========================================================

    ob_start();
    ?>

<!-- ==========================================================
     MODULAR CSS (ECHO LINK) 
========================================================== -->
<link rel="stylesheet"
      href="<?php echo esc_url(KNX_URL . 'inc/public/checkout/secure-total-style.css?v=' . KNX_VERSION); ?>">

<!-- ==========================================================
     WRAPPER
========================================================== -->
<div id="knx-secure-total"
     data-cart-id="<?php echo esc_attr($cart->id); ?>">

    <header class="knx-st-header">
        <h1>Review Total</h1>
        <p>Almost done — confirm your order below.</p>
    </header>

    <div class="knx-st-layout">

        <!-- LEFT SIDE: Items Summary -->
        <section class="knx-st-card">
            <h2 class="knx-st-card-title">Your items</h2>

            <div class="knx-st-items">

                <?php foreach ($items as $line): ?>
                    <?php
                        $qty        = (int) $line->quantity;
                        $name       = esc_html($line->name_snapshot);
                        $img        = esc_url($line->image_snapshot);
                        $line_total = number_format((float)$line->line_total, 2);
                    ?>

                    <article class="knx-st-item">
                        <?php if (!empty($img)): ?>
                        <div class="knx-st-item-img">
                            <img src="<?php echo $img; ?>" alt="<?php echo $name; ?>">
                        </div>
                        <?php endif; ?>

                        <div class="knx-st-item-body">
                            <div class="knx-st-item-row">
                                <h3><?php echo "{$qty}× {$name}"; ?></h3>
                                <span class="knx-st-price">$<?php echo $line_total; ?></span>
                            </div>

                            <?php if (!empty($line->modifiers_json)): ?>
                                <?php
                                $mods_text = [];
                                $mods = json_decode($line->modifiers_json, true);
                                if (is_array($mods)) {
                                    foreach ($mods as $m) {
                                        if (!empty($m['options'])) {
                                            $opt_names = array_column($m['options'], 'name');
                                            $mods_text[] = $m['name'] . ': ' . implode(', ', $opt_names);
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($mods_text)): ?>
                                    <div class="knx-st-item-mods">
                                        <?php echo esc_html(implode(' • ', $mods_text)); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                <?php endforeach; ?>
            </div>

            <div class="knx-st-subtotal">
                <span>Items subtotal</span>
                <strong>$<?php echo number_format((float)$cart->subtotal, 2); ?></strong>
            </div>
        </section>

        <!-- RIGHT SIDE: Payment Confirmation -->
        <aside class="knx-st-sidebar">
            <div class="knx-st-card">
                <h2 class="knx-st-card-title">Secure total</h2>

                <p class="knx-st-note">
                    On the next step, our backend will calculate taxes, delivery and service fees.
                </p>

                <button id="knxSecureTotalConfirmBtn"
                        class="knx-st-btn-primary"
                        data-cart-id="<?php echo esc_attr($cart->id); ?>">
                    Confirm & Continue
                </button>

                <p class="knx-st-fineprint">
                    You won’t be charged yet.
                </p>
            </div>
        </aside>

    </div><!-- layout -->

</div><!-- wrapper -->

<!-- ==========================================================
     MODULAR JS (ECHO SCRIPT)
========================================================== -->
<script src="<?php echo esc_url(KNX_URL . 'inc/public/checkout/secure-total-script.js?v=' . KNX_VERSION); ?>"></script>

<?php
    return ob_get_clean();
}
