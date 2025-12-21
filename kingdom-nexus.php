<?php
/**
 * Plugin Name: Kingdom Nexus
 * Description: Modular secure framework for authentication, roles, dashboards, delivery, menus, checkout and payment flow.
 * Version: 3.0.0
 * Author: Kingdom Builders
 */

if (!defined('ABSPATH')) exit;

define('KNX_PATH', plugin_dir_path(__FILE__));
define('KNX_URL', plugin_dir_url(__FILE__));
define('KNX_VERSION', '3.0.0');

/**
 * Force HTTPS behind proxies
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
 * Safe loader
 */
function knx_require($file) {
    $path = KNX_PATH . ltrim($file, '/');
    if (file_exists($path)) require_once $path;
}

/**
 * Plugin Activation
 */
function knx_activate_plugin() {
    require_once KNX_PATH . 'inc/core/db-install.php';
    require_once KNX_PATH . 'inc/core/pages-installer.php';

    knx_install_tables();
    knx_install_pages();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'knx_activate_plugin');

/**
 * Load all modules
 */
add_action('plugins_loaded', function() {

    /** FUNCTIONS **/
    knx_require('inc/functions/roles.php');
    knx_require('inc/functions/helpers.php');
    knx_require('inc/functions/security.php');
    
    knx_require('inc/functions/order-state-machine.php');
    knx_require('inc/functions/fee-calculator.php');
    knx_require('inc/functions/delivery-zone-helper.php');
    knx_require('inc/functions/hours-engine.php');

    /** CORE **/
    knx_require('inc/core/knx-rest-wrapper.php');
    knx_require('inc/core/db-install.php');
    knx_require('inc/core/pages-installer.php');
    knx_require('inc/core/session-cleaner.php');
    knx_require('inc/core/api-settings.php');
    knx_require('inc/core/api.php');
    knx_require('inc/core/api-get-hub.php');
    
    // ========== SECURIZED HUB APIs (COMMENTED - Enable one by one) ==========
    knx_require('inc/core/api-edit-hub-identity.php');
    knx_require('inc/core/api-edit-hub-location.php');
    knx_require('inc/core/api-update-closure.php');
    knx_require('inc/core/api-upload-logo.php');
    knx_require('inc/core/api-update-settings.php');
    
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

    /** CART & CHECKOUT ENGINE **/
    knx_require('inc/core/api-cart.php');
    knx_require('inc/core/api-checkout-prevalidate.php');
    knx_require('inc/core/api-secure-total.php');

    /** PAYMENTS **/
    knx_require('inc/core/payments-helpers.php');
    knx_require('inc/core/payments-api.php');

    /** PUBLIC APIS **/
    knx_require('inc/core/api-explore-hubs.php');
    knx_require('inc/core/api-toggle-featured.php');
    knx_require('inc/core/api-location-search.php');
    knx_require('inc/core/api-hubs.php');
    knx_require('inc/core/api-hours-extension.php');
    knx_require('inc/core/api-delete-hub.php');
    knx_require('inc/core/api-update-hub-slug.php');

    /** MENU SEO **/
    knx_require('inc/public/menu/menu-rewrite-rules.php');
    knx_require('inc/public/menu/menu-shortcode.php');

    /** ITEM EDITING **/
    knx_require('inc/core/api-get-item-details.php');
    knx_require('inc/core/api-update-item.php');
    knx_require('inc/core/api-modifiers.php');
    knx_require('inc/core/api-item-addons.php');

    // ========== NEW: USER MANAGEMENT APIs (COMMENTED - Enable one by one) ==========
    knx_require('inc/core/api-users.php');
    // knx_require('inc/core/api-drivers.php');
    // knx_require('inc/core/api-fees-settings.php');

    /** HUBS **/
    knx_require('inc/modules/hubs/hubs-shortcode.php');
    knx_require('inc/modules/hubs/edit-hub-template.php');
    knx_require('inc/modules/hubs/edit-hub-identity.php');

    /** CITIES **/
    knx_require('inc/modules/cities/cities-shortcode.php');
    knx_require('inc/modules/cities/edit-city.php');

    /** HUB CATEGORIES **/
    knx_require('inc/modules/hub-categories/hub-categories-shortcode.php');

    /** ITEMS **/
    knx_require('inc/modules/items/edit-hub-items.php');
    knx_require('inc/modules/items/edit-item-categories.php');
    knx_require('inc/modules/items/edit-item.php');
    knx_require('inc/modules/items/menu-uploading-frontend/loader.php');

    /** NAVBAR / SIDEBAR **/
    knx_require('inc/modules/navbar/navbar-render.php');
    knx_require('inc/modules/sidebar/sidebar-render.php');
    knx_require('inc/modules/account/account-drawer-render.php');

    /** AUTH **/
    knx_require('inc/modules/auth/auth-shortcode.php');
    knx_require('inc/modules/auth/auth-handler.php');
    knx_require('inc/modules/auth/auth-redirects.php');

    /** FRONTEND DASHBOARD MODULES **/
    knx_require('inc/modules/dashboard/dashboard-shortcode.php');
    knx_require('inc/modules/orders/customer-orders-shortcode.php');
    knx_require('inc/modules/profile/edit-profile-shortcode.php');
    knx_require('inc/modules/customers/customers-shortcode.php');
    knx_require('inc/modules/drivers/drivers-shortcode.php');

    /** ADMIN **/
    knx_require('inc/modules/admin/admin-menu.php');
    knx_require('inc/modules/admin/stripe-settings.php');
    
    // ========== NEW: USER MANAGEMENT MODULES (COMMENTED - Enable one by one) ==========
    // knx_require('inc/modules/admin/fees-settings.php');
    knx_require('inc/modules/users/users-admin.php');

    /** PUBLIC FRONTEND **/
    knx_require('inc/public/home/home-shortcode.php');
    knx_require('inc/shortcodes/cities-grid-shortcode.php');
    knx_require('inc/public/explore-hubs/explore-hubs-shortcode.php');
    knx_require('inc/public/cart/cart-shortcode.php');
    knx_require('inc/public/checkout/checkout-shortcode.php');
    knx_require('inc/public/checkout/secure-total-shortcode.php');
    knx_require('inc/public/checkout/order-confirmation-shortcode.php');
});

/**
 * Auto repair
 */
add_action('init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'knx_hubs';
    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'category_id'));
    if (!$col && function_exists('knx_install_tables')) {
        knx_install_tables();
    }
});

/**
 * Cron cleanup sessions
 */
if (!wp_next_scheduled('knx_hourly_cleanup')) {
    wp_schedule_event(time(), 'hourly', 'knx_hourly_cleanup');
}
add_action('knx_hourly_cleanup', 'knx_cleanup_sessions');

/**
 * Global assets (kept minimal)
 */
add_action('wp_enqueue_scripts', function() {

    wp_enqueue_style(
        'knx-fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        [],
        '6.5.1'
    );

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

    wp_enqueue_style(
        'choices-js',
        'https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css'
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
