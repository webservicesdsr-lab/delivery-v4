<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Auth Handler (v2.1)
 *
 * Processes secure login, session creation, and logout.
 * Integrates with CSRF nonces, rate limiting, and safe cookies.
 */

add_action('init', function() {
    global $wpdb;
    $users_table    = $wpdb->prefix . 'knx_users';
    $sessions_table = $wpdb->prefix . 'knx_sessions';

    // Handle login
    if (isset($_POST['knx_login_btn'])) {
        $ip = knx_get_client_ip();

        // Rate limit protection
        if (knx_is_ip_blocked($ip)) {
            wp_safe_redirect(site_url('/login?error=locked'));
            exit;
        }

        // Validate nonce
        if (!isset($_POST['knx_nonce']) || !knx_verify_nonce($_POST['knx_nonce'], 'login')) {
            wp_die('Security check failed. Please refresh and try again.');
        }

        $login    = sanitize_text_field($_POST['knx_login']);
        $password = sanitize_text_field($_POST['knx_password']);
        $remember = isset($_POST['knx_remember']);

        // Lookup user by username or email
        $user = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $users_table
            WHERE username = %s OR email = %s
            LIMIT 1
        ", $login, $login));

        // Validation
        if (!$user || $user->status !== 'active' || !password_verify($password, $user->password)) {
            knx_limit_login_attempts($ip);
            wp_safe_redirect(site_url('/login?error=invalid'));
            exit;
        }

        // Generate secure session token
        $token   = knx_generate_token();
        $expires = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+1 day'));
        $agent   = substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);

        // Store session
        $wpdb->insert($sessions_table, [
            'user_id'    => $user->id,
            'token'      => $token,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'expires_at' => $expires
        ]);

        // Set secure cookie
        setcookie('knx_session', $token, [
            'expires'  => $remember ? time() + (30 * DAY_IN_SECONDS) : time() + DAY_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // TASK 5: Normalize Login Redirect Behavior
        $redirect_to = null;

        // Check for preserved redirect from deep-link
        if (isset($_POST['knx_redirect']) && !empty($_POST['knx_redirect'])) {
            $redirect_candidate = sanitize_text_field($_POST['knx_redirect']);
            
            // Security: only allow internal paths (starts with / but not //)
            if (strpos($redirect_candidate, '/') === 0 && strpos($redirect_candidate, '//') === false) {
                // Basic validation: check if path looks reasonable
                $path = trim($redirect_candidate, '/');
                $parts = explode('/', $path);
                // Accept paths like /dashboard, /hubs, /edit-hub?id=123, etc.
                if (!empty($parts[0]) && preg_match('/^[a-z0-9-]+/', $parts[0])) {
                    $redirect_to = $redirect_candidate;
                }
            }
        }
        
        // Fallback to role-based defaults if no valid redirect
        if (!$redirect_to) {
            $redirects = [
                'super_admin'   => '/hubs',
                'manager'       => '/hubs',
                'menu_uploader' => '/hubs',
                'hub_management' => '/hubs',
                'driver'        => '/hubs', // Temporary until driver-dashboard is built
                'customer'      => '/home',
                'user'          => '/home'
            ];
            $redirect_to = isset($redirects[$user->role]) ? $redirects[$user->role] : '/home';
        }
        
        wp_safe_redirect(site_url($redirect_to));
        exit;
    }

    // Handle logout (form-based)
    if (isset($_POST['knx_logout'])) {
        if (!isset($_POST['knx_logout_nonce']) || !wp_verify_nonce($_POST['knx_logout_nonce'], 'knx_logout_action')) {
            wp_die('Security check failed.');
        }

        knx_logout_user();
        exit;
    }
});


/**
 * AJAX Logout (for sidebar / navbar)
 * Secure version — requires valid session & nonce
 */
add_action('wp_ajax_knx_logout_user', function() {
    $session = knx_get_session();
    if (!$session) {
        wp_send_json_error(['message' => 'Unauthorized'], 401);
    }

    // Validate nonce
    $nonce = sanitize_text_field($_POST['knx_logout_nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'knx_logout_action')) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    // Perform logout without redirect (AJAX context)
    knx_logout_user(false);

    wp_send_json_success(['redirect' => site_url('/login')]);
});
