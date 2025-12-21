<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Customer Orders Shortcode
 * Read-only customer order history placeholder
 */

add_shortcode('knx_customer_orders', 'knx_render_customer_orders');

function knx_render_customer_orders() {
    $session = knx_get_session();
    if (!$session || $session->role !== 'customer') {
        wp_redirect(site_url('/login'));
        exit;
    }

    ob_start();
    ?>
    <div class="knx-orders">
        <h1>My Orders</h1>
        <p>Your order history will appear here.</p>
        <p><em>No orders to display yet.</em></p>
    </div>
    <?php
    return ob_get_clean();
}