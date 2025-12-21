<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Customers Management Shortcode
 * Minimal admin placeholder
 */

add_shortcode('knx_customers', 'knx_render_customers');

function knx_render_customers() {
    $session = knx_get_session();
    if (!$session) {
        wp_redirect(site_url('/login'));
        exit;
    }

    $allowed_roles = ['super_admin', 'manager'];
    if (!in_array($session->role, $allowed_roles, true)) {
        return '<p>Access denied.</p>';
    }

    ob_start();
    ?>
    <div class="knx-customers">
        <h1>Customers</h1>
        <p>Customer management interface.</p>
        <p><em>Under construction.</em></p>
    </div>
    <?php
    return ob_get_clean();
}
