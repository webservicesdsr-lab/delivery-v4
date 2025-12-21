<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Dashboard Shortcode
 * Minimal internal dashboard landing page
 */

add_shortcode('knx_dashboard', 'knx_render_dashboard');

function knx_render_dashboard() {
    $session = knx_get_session();
    if (!$session) {
        wp_redirect(site_url('/login'));
        exit;
    }

    $allowed_roles = ['super_admin', 'manager', 'hub_management', 'menu_uploader'];
    if (!in_array($session->role, $allowed_roles, true)) {
        return '<p>Access denied.</p>';
    }

    ob_start();
    ?>
    <div class="knx-dashboard">
        <h1>Dashboard</h1>
        <p>Welcome back, <?php echo esc_html($session->username); ?>.</p>
        <p>This dashboard will show system metrics and activity.</p>
        <p><em>Under construction.</em></p>
    </div>
    <?php
    return ob_get_clean();
}
