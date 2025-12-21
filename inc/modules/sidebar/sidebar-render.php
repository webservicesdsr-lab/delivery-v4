<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Admin Sidebar Renderer (v6.1)
 * - Renders ONLY when admin context is explicitly set (KNX_ADMIN_CONTEXT)
 * - DOES NOT use wp_footer (project rule)
 * - Avoids wp_body_open timing issue (context is set inside shortcodes)
 * - Injects sidebar AFTER shortcodes execute via the_content (late priority)
 */

function knx_sidebar_markup_admin_only() {
    // Gate 1: session required
    if (!function_exists('knx_get_session')) return '';
    $session = knx_get_session();
    if (!$session) return '';

    // Gate 2: role guard required (admins/managers only)
    $role = $session->role ?? 'guest';
    $allowed_roles = ['super_admin', 'manager', 'hub_management', 'menu_uploader'];

    if (function_exists('knx_get_role_level')) {
        $lvl = (int) knx_get_role_level($role);
        if ($lvl < 3 && !in_array($role, $allowed_roles, true)) {
            return '';
        }
    } else {
        if (!in_array($role, $allowed_roles, true)) {
            return '';
        }
    }

    // Assets (project style: inline load in output; no enqueue)
    $out  = '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/sidebar/sidebar-style.css?v=' . KNX_VERSION) . '">';
    $out .= '<script src="' . esc_url(KNX_URL . 'inc/modules/sidebar/sidebar-script.js?v=' . KNX_VERSION) . '" defer></script>';

    ob_start();
    ?>
    <script>document.documentElement.classList.add("knx-has-admin-sidebar");</script>
    <aside class="knx-sidebar" id="knxSidebar">
        <div class="knx-sidebar-header">
            <button id="knxExpandMobile" class="knx-expand-btn" aria-label="Toggle Sidebar" type="button">
                <i class="fas fa-angles-right"></i>
            </button>
            <a href="<?php echo esc_url(site_url('/dashboard')); ?>" class="knx-logo" title="Dashboard">
                <i class="fas fa-home"></i>
            </a>
        </div>

        <div class="knx-sidebar-scroll">
            <ul class="knx-sidebar-menu">
                <li><a href="<?php echo esc_url(site_url('/dashboard')); ?>"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                <li><a href="<?php echo esc_url(site_url('/hubs')); ?>"><i class="fas fa-store"></i><span>Hubs</span></a></li>
                <li><a href="<?php echo esc_url(site_url('/hub-categories')); ?>"><i class="fas fa-list"></i><span>Hub Categories</span></a></li>
                <?php if (in_array($role, ['super_admin', 'manager'], true)): ?>
                <li><a href="<?php echo esc_url(site_url('/drivers')); ?>"><i class="fas fa-car"></i><span>Drivers</span></a></li>
                <li><a href="<?php echo esc_url(site_url('/customers')); ?>"><i class="fas fa-users"></i><span>Customers</span></a></li>
                <?php endif; ?>
                <li><a href="<?php echo esc_url(site_url('/cities')); ?>"><i class="fas fa-city"></i><span>Cities</span></a></li>
            </ul>
        </div>
    </aside>
    <?php
    $out .= ob_get_clean();

    return $out;
}

/**
 * Inject admin sidebar AFTER shortcodes run.
 * Priority 9999 ensures do_shortcode already executed.
 */
function knx_inject_admin_sidebar_into_content($content) {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) return $content;
    if (!is_singular()) return $content;

    // Only inject on KNX internal pages (safety net)
    $allowed_slugs = ['dashboard','hubs','menus','hub-categories','drivers','customers','cities','settings','edit-hub-items','edit-item-categories','edit-item','edit-city','edit-hub'];
    global $post;
    $slug = is_object($post) ? $post->post_name : '';
    if (!$slug || !in_array($slug, $allowed_slugs, true)) return $content;

    // Only inject on main query content
    if (!in_the_loop() || !is_main_query()) return $content;

    $sidebar = knx_sidebar_markup_admin_only();
    if ($sidebar === '') return $content;

    return $sidebar . $content;
}

add_filter('the_content', 'knx_inject_admin_sidebar_into_content', 9999);
