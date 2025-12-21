<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Public Account Drawer (v1.0)
 * - Shows on PUBLIC pages for logged-in users
 * - Does NOT depend on wp_footer
 * - Loads assets via echo (matches your style)
 * - Toggle button is expected in navbar: #knxAccountToggle
 */

function knx_render_account_drawer() {
    // Never render inside explicit admin context pages (avoid mixing UIs)
    if (function_exists('knx_is_admin_context') && knx_is_admin_context()) {
        return;
    }

    if (!function_exists('knx_get_session')) return;
    $session = knx_get_session();
    if (!$session) return;

    // Hard block: admins/managers should never see the PUBLIC customer drawer
    $role = $session->role ?? 'guest';
    if (function_exists('knx_get_role_level')) {
        $lvl = (int) knx_get_role_level($role);
        if ($lvl >= 3) return; // admin+ (matches navbar logic)
    } else {
        // Fallback allowlist: only customers/guests-like roles should pass
        $blocked = ['super_admin','manager','hub_management','menu_uploader'];
        if (in_array($role, $blocked, true)) return;
    }

    // Echo assets (no enqueue)
    echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/account/account-drawer.css?v=' . KNX_VERSION) . '">';
    echo '<script src="' . esc_url(KNX_URL . 'inc/modules/account/account-drawer.js?v=' . KNX_VERSION) . '" defer></script>';

    $username = esc_html($session->username ?? 'Account');

    // Update these URLs later when pages exist
    $profile_url = esc_url(site_url('/edit-profile'));
    $orders_url  = esc_url(site_url('/orders'));
    ?>
    <aside class="knx-account-drawer" id="knxAccountDrawer" role="dialog" aria-modal="true" aria-labelledby="knxAccountTitle" aria-hidden="true">
        <header class="knx-account-drawer__header">
            <div>
                <h3 id="knxAccountTitle">Your Account</h3>
                <p class="knx-account-drawer__sub"><?php echo $username; ?></p>
            </div>
            <button type="button" class="knx-account-drawer__close" id="knxAccountClose" aria-label="Close account drawer">×</button>
        </header>

        <div class="knx-account-drawer__body">
            <a class="knx-account-link" href="<?php echo $profile_url; ?>">
                <i class="fas fa-user"></i>
                <span>Edit Profile</span>
            </a>

            <a class="knx-account-link" href="<?php echo $orders_url; ?>">
                <i class="fas fa-receipt"></i>
                <span>Orders</span>
            </a>

            <div class="knx-account-divider"></div>

            <form method="post" class="knx-account-logout">
                <?php wp_nonce_field('knx_logout_action','knx_logout_nonce'); ?>
                <button type="submit" name="knx_logout" class="knx-account-logout__btn">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="knx-account-overlay" id="knxAccountOverlay" aria-hidden="true"></div>
    <?php
}

add_action('wp_body_open', 'knx_render_account_drawer', 8);
