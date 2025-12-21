<?php
/**
 * Plugin Name: Kingdom Nexus
 * Description: Modular secure framework for authentication, roles, and dashboards with smart redirects.
 * Version: 2.8.1
 * Author: Kingdom Builders
 */

if (!defined('ABSPATH')) exit;

define('KNX_PATH', plugin_dir_path(__FILE__));
define('KNX_URL', plugin_dir_url(__FILE__));
define('KNX_VERSION', '2.8.1');

/**
 * Force HTTPS behind proxies (HostGator, Cloudflare, etc.)
 */
if (!is_ssl() && !defined('WP_DEBUG')) {
    if (strpos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') !== false) {
        $_SERVER['HTTPS'] = 'on';
    }
}

/**
 * Secure PHP session
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

/**
 * Require helper to load files safely
 */
function knx_require($relative) {
    $path = KNX_PATH . ltrim($relative, '/');
    if (file_exists($path)) {
        require_once $path;
    }
}

/**
 * Plugin activation
 */
function knx_activate_plugin() {
    require_once KNX_PATH . 'inc/core/db-install.php';
    require_once KNX_PATH . 'inc/core/pages-installer.php';

    knx_install_tables();
    knx_install_pages();
    flush_rewrite_rules();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Kingdom Nexus activated successfully.');
    }
}
register_activation_hook(__FILE__, 'knx_activate_plugin');

/**
 * Load all core and module files
 */
add_action('plugins_loaded', function() {

    // === FUNCTIONS ===
    knx_require('inc/functions/helpers.php');
    knx_require('inc/functions/security.php');
    knx_require('inc/functions/delivery-zone-helper.php');
    knx_require('inc/functions/hours-engine.php');

    // === CORE ===
    knx_require('inc/core/db-install.php');
    knx_require('inc/core/pages-installer.php');
    knx_require('inc/core/session-cleaner.php');
    knx_require('inc/core/api-settings.php');
    knx_require('inc/core/api.php');
    knx_require('inc/core/api-get-hub.php');
    knx_require('inc/core/api-hub-hours.php');
    knx_require('inc/core/api-cities.php');
    knx_require('inc/core/api-hub-categories.php');
    knx_require('inc/core/api-edit-city.php');
    knx_require('inc/core/api-delivery-rates.php');
    knx_require('inc/core/api-check-coverage.php');
    knx_require('inc/core/api-get-cities.php');
    knx_require('inc/core/api-hub-items.php');
    knx_require('inc/core/api-reorder-item.php');
    knx_require('inc/core/api-get-item-categories.php');
    knx_require('inc/core/api-save-item-category.php');
    knx_require('inc/core/api-reorder-item-category.php');
    knx_require('inc/core/api-toggle-item-category.php');
    knx_require('inc/core/api-delete-item-category.php');
    knx_require('inc/core/api-menu-read.php'); 
    knx_require('inc/core/api-cart.php');
    


    // Public explore hubs API
    knx_require('inc/core/api-explore-hubs.php');

    // Other APIs
    knx_require('inc/core/api-toggle-featured.php');
    knx_require('inc/core/api-location-search.php');
    knx_require('inc/core/api-hubs.php');
    knx_require('inc/core/api-hours-extension.php');
    knx_require('inc/core/api-delete-hub.php');
    knx_require('inc/core/api-update-hub-slug.php');

    // Menu SEO-friendly URLs
    knx_require('inc/public/menu/menu-rewrite-rules.php');
    knx_require('inc/public/menu/menu-shortcode.php');

    // Item editing APIs
    knx_require('inc/core/api-get-item-details.php');
    knx_require('inc/core/api-update-item.php');

    // Modifiers & Addons
    knx_require('inc/core/api-modifiers.php');
    knx_require('inc/core/api-item-addons.php');

    // Hubs module
    knx_require('inc/modules/hubs/hubs-shortcode.php');
    knx_require('inc/modules/hubs/edit-hub-template.php');
    knx_require('inc/modules/hubs/edit-hub-identity.php');

    // Cities module
    knx_require('inc/modules/cities/cities-shortcode.php');
    knx_require('inc/modules/cities/edit-city.php');

    // Hub categories
    knx_require('inc/modules/hub-categories/hub-categories-shortcode.php');

    // Items module
    knx_require('inc/modules/items/edit-hub-items.php');
    knx_require('inc/modules/items/edit-item-categories.php');
    knx_require('inc/modules/items/edit-item.php');
    knx_require('inc/modules/items/menu-uploading-frontend/loader.php');

    // Navbar + Sidebar
    knx_require('inc/modules/navbar/navbar-render.php');
    knx_require('inc/modules/sidebar/sidebar-render.php');

    // Auth
    knx_require('inc/modules/auth/auth-shortcode.php');
    knx_require('inc/modules/auth/auth-handler.php');
    knx_require('inc/modules/auth/auth-redirects.php');

    // Admin UI
    knx_require('inc/modules/admin/admin-menu.php');

    // Public modules
    knx_require('inc/public/home/home-shortcode.php');
    knx_require('inc/shortcodes/cities-grid-shortcode.php');
    knx_require('inc/public/explore-hubs/explore-hubs-shortcode.php');
    knx_require('inc/public/cart/cart-shortcode.php');
    knx_require('inc/public/checkout/checkout-shortcode.php');
});

/**
 * Auto-detect missing columns/tables and repair
 */
add_action('init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'knx_hubs';
    $has_category = $wpdb->get_var(
        $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'category_id')
    );

    if (!$has_category && function_exists('knx_install_tables')) {
        knx_install_tables();
    }
});

/**
 * Scheduled session cleanup
 */
if (!wp_next_scheduled('knx_hourly_cleanup')) {
    wp_schedule_event(time(), 'hourly', 'knx_hourly_cleanup');
}
add_action('knx_hourly_cleanup', 'knx_cleanup_sessions');

/**
 * Global frontend assets
 * (kept minimal to avoid conflicts with Elementor)
 */
add_action('wp_enqueue_scripts', function() {

    // === GLOBAL REQUIRED ASSETS (SAFE) ===

    // Font Awesome (icons)
    wp_enqueue_style(
        'knx-fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        [],
        '6.5.1'
    );

    // Toast Notifications
    wp_enqueue_style(
        'knx-toast',
        KNX_URL . 'inc/modules/core/knx-toast.css',
        [],
        KNX_VERSION
    );
    wp_enqueue_script(
        'knx-toast',
        KNX_URL . 'inc/modules/core/knx-toast.js',
        [],
        KNX_VERSION,
        true
    );

    // Choices.js (used in backend forms)
    wp_enqueue_style(
        'choices-js',
        'https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css',
        [],
        null
    );
    wp_enqueue_script(
        'choices-js',
        'https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js',
        [],
        null,
        true
    );

});

?>
