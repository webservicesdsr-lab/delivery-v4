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
 * Safe file loader
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
}
register_activation_hook(__FILE__, 'knx_activate_plugin');

/**
 * Load core + modules
 */
add_action('plugins_loaded', function() {

    /* ======================================================
     * FUNCTIONS
     * ====================================================== */
    knx_require('inc/functions/helpers.php');
    knx_require('inc/functions/security.php');
    knx_require('inc/functions/delivery-zone-helper.php');
    knx_require('inc/functions/hours-engine.php');

    /* ======================================================
     * REST INFRASTRUCTURE (PHASE 1)
     * - MUST NOT register endpoints
     * - MUST NOT echo/output anything
     * - Only helpers/wrappers
     * ====================================================== */
    knx_require('inc/core/rest/knx-rest-response.php');
    knx_require('inc/core/rest/knx-rest-guard.php');
    knx_require('inc/core/rest/knx-rest-wrapper.php');

    /* ======================================================
     * CORE (LEGACY - STABLE)
     * ====================================================== */
    knx_require('inc/core/db-install.php');
    knx_require('inc/core/pages-installer.php');
    knx_require('inc/core/session-cleaner.php');

    // Cities (supporting)
    knx_require('inc/core/api-delivery-rates.php');

    /* ======================================================
     * RESOURCES — KNX HUBS (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-hubs/api-hub-categories.php');
    knx_require('inc/core/resources/knx-hubs/api-hubs-core.php');
    knx_require('inc/core/resources/knx-hubs/api-hubs.php');
    knx_require('inc/core/resources/knx-hubs/api-get-hub.php');
    knx_require('inc/core/resources/knx-hubs/api-toggle-featured.php');
    knx_require('inc/core/resources/knx-hubs/api-update-hub-slug.php');
    knx_require('inc/core/resources/knx-hubs/api-upload-logo.php');
    knx_require('inc/core/resources/knx-hubs/api-edit-hub-identity.php');
    knx_require('inc/core/resources/knx-hubs/api-edit-hub-location.php');
    knx_require('inc/core/resources/knx-hubs/api-hub-hours.php');
    knx_require('inc/core/resources/knx-hubs/api-update-closure.php');
    knx_require('inc/core/resources/knx-hubs/api-delete-hub.php');
    knx_require('inc/core/resources/knx-hubs/api-update-settings.php');

    /* ======================================================
     * RESOURCES — KNX CART (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-cart/api-cart.php');

    /* ======================================================
     * RESOURCES — KNX CHECKOUT (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-checkout/api-checkout-prevalidate.php');

    /* ======================================================
     * RESOURCES — KNX LOCATION (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-location/api-check-coverage.php');
    knx_require('inc/core/resources/knx-location/api-location-search.php');

    /* ======================================================
     * RESOURCES — KNX EXPLORE (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-explore/api-explore-hubs.php');

    /* ======================================================
     * RESOURCES — KNX SYSTEM (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-system/api-hours-extension.php');
    knx_require('inc/core/resources/knx-system/api-settings.php');

    /* ======================================================
     * RESOURCES — KNX ITEMS (CANONICAL)
     * ====================================================== */
    knx_require('inc/core/resources/knx-items/api-get-item-categories.php');
    knx_require('inc/core/resources/knx-items/api-save-item-category.php');
    knx_require('inc/core/resources/knx-items/api-toggle-item-category.php');
    knx_require('inc/core/resources/knx-items/api-delete-item-category.php');
    knx_require('inc/core/resources/knx-items/api-reorder-item-category.php');
    knx_require('inc/core/resources/knx-items/api-hub-items.php');
    knx_require('inc/core/resources/knx-items/api-update-item.php');
    knx_require('inc/core/resources/knx-items/api-reorder-item.php');
    knx_require('inc/core/resources/knx-items/api-item-addons.php');
    knx_require('inc/core/resources/knx-items/api-menu-read.php');

    /* ======================================================
     * RESOURCES — KNX CITIES (SEALED)
     * ====================================================== */
    knx_require('inc/core/resources/knx-cities/api-edit-city.php');
    knx_require('inc/core/resources/knx-cities/get-cities.php');
    knx_require('inc/core/resources/knx-cities/post-operational-toggle.php');
    knx_require('inc/core/resources/knx-cities/add-city.php');
    knx_require('inc/core/resources/knx-cities/delete-city.php');
    knx_require('inc/core/resources/knx-cities/get-delivery-rates.php');
    knx_require('inc/core/resources/knx-cities/update-delivery-rates.php');

    /* ======================================================
     * MODULES — KNX CITIES (NEW UI)
     * ====================================================== */
    knx_require('inc/modules/knx-cities/knx-cities-shortcode.php');
    knx_require('inc/modules/knx-cities/knx-edit-city.php');

    /* ======================================================
     * MENU (SEO)
     * ====================================================== */
    knx_require('inc/public/menu/menu-rewrite-rules.php');
    knx_require('inc/public/menu/menu-shortcode.php');

    /* ======================================================
     * KNX ITEMS (CANONICAL) - MODIFIERS
     * ====================================================== */
    knx_require('inc/core/resources/knx-items/api-modifiers.php');

    /* ======================================================
     * MODULES — LEGACY
     * ====================================================== */
    knx_require('inc/modules/hubs/hubs-shortcode.php');
    knx_require('inc/modules/hubs/edit-hub-template.php');
    knx_require('inc/modules/hubs/edit-hub-identity.php');

    knx_require('inc/modules/hub-categories/hub-categories-shortcode.php');

    knx_require('inc/modules/items/edit-hub-items.php');
    knx_require('inc/modules/items/edit-item-categories.php');
    knx_require('inc/modules/items/edit-item.php');

    knx_require('inc/modules/navbar/navbar-render.php');
    knx_require('inc/modules/sidebar/sidebar-render.php');

    knx_require('inc/modules/auth/auth-shortcode.php');
    knx_require('inc/modules/auth/auth-handler.php');
    knx_require('inc/modules/auth/auth-redirects.php');

    knx_require('inc/modules/admin/admin-menu.php');

    /* ======================================================
     * PUBLIC FRONTEND
     * ====================================================== */
    knx_require('inc/public/home/home-shortcode.php');
    knx_require('inc/shortcodes/cities-grid-shortcode.php');
    knx_require('inc/public/explore-hubs/explore-hubs-shortcode.php');
    knx_require('inc/public/cart/cart-shortcode.php');
    knx_require('inc/public/checkout/checkout-shortcode.php');
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
