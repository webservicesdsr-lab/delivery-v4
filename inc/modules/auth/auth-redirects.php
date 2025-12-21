<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Auth Redirects (v2)
 *
 * Controls access flow and route restrictions across the site.
 * - Prevents logged-in users from viewing /login or /register
 * - Redirects unauthorized roles trying to access restricted pages
 * - Handles guest restrictions to protected dashboards
 */

add_action('template_redirect', function() {
    global $post;

    $session = knx_get_session();
    $slug = is_object($post) ? $post->post_name : '';

    // Define route categories
    $public_pages    = ['home', 'about', 'contact', 'terms', 'privacy', 'login', 'register'];
    $restricted_pages = ['hubs', 'drivers', 'customers', 'cities', 'advanced-dashboard', 'dashboard', 'account-settings'];
    $dashboard_pages = ['hubs', 'drivers', 'customers', 'cities', 'advanced-dashboard', 'dashboard'];

    // Redirect logged-in users away from login/register
    if ($session && in_array($slug, ['login', 'register'])) {
        wp_safe_redirect(site_url('/home'));
        exit;
    }

    // Redirect guests trying to access restricted pages
    if (!$session && in_array($slug, $restricted_pages)) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    // Role-based restrictions
    if ($session) {
        $role = $session->role;

        // Customers cannot access dashboards
        if ($role === 'customer' && in_array($slug, $dashboard_pages)) {
            wp_safe_redirect(site_url('/home'));
            exit;
        }

        // Menu uploader cannot access admin or driver pages
        if ($role === 'menu_uploader' && in_array($slug, ['drivers', 'advanced-dashboard'])) {
            wp_safe_redirect(site_url('/hubs'));
            exit;
        }

        // Manager cannot access super admin routes
        if ($role === 'manager' && $slug === 'advanced-dashboard') {
            wp_safe_redirect(site_url('/hubs'));
            exit;
        }

        // Drivers cannot access hubs or admin dashboards
        // Temporarily redirect to /hubs until driver dispatch system is built
        if ($role === 'driver' && in_array($slug, ['hubs', 'customers', 'cities', 'advanced-dashboard'])) {
            wp_safe_redirect(site_url('/hubs'));
            exit;
        }
    }
});

/**
 * Task 3: Safe Frontend Redirects (Navigation Cleanup)
 * Redirect phantom/alias pages to existing functionality
 */
add_action('template_redirect', function() {
    global $post;
    if (!is_singular() || !is_object($post)) return;

    $slug = $post->post_name;

    // Redirect /menus to /hubs (menu management is done through hub management)
    if ($slug === 'menus') {
        wp_safe_redirect(site_url('/hubs'), 301);
        exit;
    }

    // Redirect /driver-dashboard to /hubs (temporary until driver dispatch exists)
    if ($slug === 'driver-dashboard') {
        wp_safe_redirect(site_url('/hubs'), 301);
        exit;
    }
}, 5);

/**
 * Task 4: Harden Frontend Internal Pages (Security Guard)
 * Prevent direct URL access to internal pages without valid Nexus session
 */
add_action('template_redirect', function() {
    if (is_admin() || wp_doing_ajax()) return;

    global $post;
    if (!is_singular() || !is_object($post)) return;

    $slug = $post->post_name;

    // Define internal frontend pages that require authentication
    $protected_pages = [
        'dashboard',
        'orders',
        'customers',
        'drivers',
        'edit-profile'
    ];

    // Check if current page is protected
    if (!in_array($slug, $protected_pages, true)) return;

    // Validate Nexus session
    $session = function_exists('knx_get_session') ? knx_get_session() : null;

    if (!$session) {
        // Build redirect URL with original destination
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $redirect_param = $current_url ? '?redirect=' . urlencode($current_url) : '';
        wp_safe_redirect(site_url('/login' . $redirect_param));
        exit;
    }
}, 10);

/**
 * Task 7: Fail-Safe for Future Phantom Pages (Airbag)
 * Catch unexpected internal page requests and redirect safely
 */
add_action('template_redirect', function() {
    if (is_admin() || wp_doing_ajax()) return;
    if (!is_404()) return;

    // Check if request looks like internal Nexus slug
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $internal_patterns = [
        '/dashboard',
        '/hubs',
        '/menus',
        '/drivers',
        '/customers',
        '/cities',
        '/orders',
        '/edit-',
        '/settings'
    ];

    $is_internal = false;
    foreach ($internal_patterns as $pattern) {
        if (strpos($request_uri, $pattern) !== false) {
            $is_internal = true;
            break;
        }
    }

    if (!$is_internal) return;

    // Redirect based on session status
    $session = function_exists('knx_get_session') ? knx_get_session() : null;

    if ($session) {
        // Logged in: send to dashboard
        wp_safe_redirect(site_url('/dashboard'), 302);
    } else {
        // Not logged in: send to login
        wp_safe_redirect(site_url('/login'), 302);
    }
    exit;
}, 15);
