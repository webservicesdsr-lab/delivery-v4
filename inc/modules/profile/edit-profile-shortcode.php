<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Edit Profile Shortcode
 * Minimal profile editor placeholder
 */

add_shortcode('knx_edit_profile', 'knx_render_edit_profile');

function knx_render_edit_profile() {
    $session = knx_get_session();
    if (!$session) {
        wp_redirect(site_url('/login'));
        exit;
    }

    ob_start();
    ?>
    <div class="knx-edit-profile">
        <h1>Edit Profile</h1>
        <p>Profile editing will be available here.</p>
        <p><strong>User:</strong> <?php echo esc_html($session->username); ?></p>
        <p><em>Under construction.</em></p>
    </div>
    <?php
    return ob_get_clean();
}
