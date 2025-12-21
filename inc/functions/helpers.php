<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Global Helper Functions (v2)
 *
 * Handles secure session validation, role hierarchy,
 * and access guards for all restricted modules.
 */

/**
 * Get the current active session.
 * Returns a user object if valid, otherwise false.
 */
function knx_get_session() {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'knx_sessions';
    $users_table    = $wpdb->prefix . 'knx_users';

    if (empty($_COOKIE['knx_session'])) {
        return false;
    }

    $token = sanitize_text_field($_COOKIE['knx_session']);
    $query = $wpdb->prepare("
        SELECT s.*, u.id AS user_id, u.username, u.email, u.role, u.status
        FROM $sessions_table s
        JOIN $users_table u ON s.user_id = u.id
        WHERE s.token = %s
        AND s.expires_at > NOW()
        AND u.status = 'active'
        LIMIT 1
    ", $token);

    $session = $wpdb->get_row($query);
    return $session ? $session : false;
}

/**
 * Require a minimum role hierarchy.
 * Returns the session object or false if unauthorized.
 */
function knx_require_role($role = 'customer') {
    $session = knx_get_session();
    if (!$session) {
        return false;
    }

    $hierarchy = knx_get_role_hierarchy();
    $user_role = $session->role;

    if (!isset($hierarchy[$user_role]) || !isset($hierarchy[$role])) {
        return false;
    }

    if ($hierarchy[$user_role] < $hierarchy[$role]) {
        return false;
    }

    return $session;
}

/**
 * Guard a restricted page or shortcode.
 * If unauthorized, redirect safely to the login page.
 */
function knx_guard($required_role = 'customer') {
    $session = knx_require_role($required_role);

    if (!$session) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    return $session;
}

/**
 * Set admin context flag for the current request.
 * Must be called at the top of admin shortcodes/templates.
 */
if (!function_exists('knx_set_admin_context')) {
    function knx_set_admin_context() {
        if (!defined('KNX_ADMIN_CONTEXT')) {
            define('KNX_ADMIN_CONTEXT', true);
        }
    }
}

/**
 * Check if the current request is in admin context.
 * 
 * @return bool True if admin context is explicitly set
 */
if (!function_exists('knx_is_admin_context')) {
    function knx_is_admin_context() {
        return defined('KNX_ADMIN_CONTEXT') && KNX_ADMIN_CONTEXT === true;
    }
}

/**
 * Guard checkout pages with redirect preservation.
 * Redirects to /login?redirect=<current_url> if unauthorized.
 * 
 * @param string $required_role Minimum role required (default: 'customer')
 * @return object Session object if authorized
 */
function knx_guard_checkout_page($required_role = 'customer') {
    $session = knx_get_session();
    
    // No session: redirect to login with return URL (preserves query strings)
    if (!$session) {
        $current_uri = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '/');
        
        // Security: validate it's an internal path
        // Allow: /checkout, /payment?token=abc, /checkout/confirm?id=123
        // Block: //evil.com, http://evil.com, javascript:alert(1)
        if (strpos($current_uri, '/') !== 0 || strpos($current_uri, '//') !== false) {
            // Malicious redirect attempt - go to login without redirect
            wp_safe_redirect(site_url('/login'));
            exit;
        }
        
        // URL-encode the full path+query (WordPress will decode it)
        wp_safe_redirect(site_url('/login?redirect=' . urlencode($current_uri)));
        exit;
    }
    
    // Check role hierarchy
    $allowed_roles = ['customer', 'manager', 'super_admin'];
    if (!in_array($session->role, $allowed_roles)) {
        wp_safe_redirect(site_url('/home'));
        exit;
    }
    
    return $session;
}

/**
 * Guard REST endpoints in checkout flow.
 * Returns 401/403 JSON if unauthorized (does NOT redirect).
 * 
 * @param string $required_role Minimum role required (default: 'customer')
 * @return WP_REST_Response|true Returns error response on failure, true on success
 */
function knx_guard_checkout_api($required_role = 'customer') {
    $session = knx_get_session();
    
    // No session: 401 unauthorized
    if (!$session) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'unauthorized',
            'code'    => 'AUTH001',
            'message' => 'You must be logged in to continue.'
        ], 401);
    }
    
    // Check role allowlist
    $allowed_roles = ['customer', 'manager', 'super_admin'];
    if (!in_array($session->role, $allowed_roles)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'forbidden',
            'code'    => 'AUTH002',
            'message' => 'Your account role cannot access this feature.'
        ], 403);
    }
    
    return true;
}

/**
 * Secure logout handler.
 * Deletes the current session and clears the cookie.
 * 
 * @param bool $redirect Whether to perform automatic redirect (default: true)
 */
function knx_logout_user($redirect = true) {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'knx_sessions';

    if (isset($_COOKIE['knx_session'])) {
        $token = sanitize_text_field($_COOKIE['knx_session']);

        // Delete session from database
        $wpdb->delete($sessions_table, ['token' => $token]);

        // Clear browser cookie securely
        setcookie('knx_session', '', time() - 3600, '/', '', is_ssl(), true);
    }

    // Redirect only if not called from AJAX
    if ($redirect) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }
}

/**
 * Invalidate all sessions for a specific user.
 * Use when password changes or account is compromised.
 * 
 * @param int $user_id The user ID to invalidate sessions for
 * @return int|false Number of sessions deleted or false on failure
 */
function knx_invalidate_user_sessions($user_id) {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'knx_sessions';
    return $wpdb->delete($sessions_table, ['user_id' => $user_id], ['%d']);
}


/**
 * Return the canonical KNX table name for a logical resource.
 *
 * Usage: knx_table('items_categories') => "$wpdb->prefix . 'knx_items_categories'"
 * This enforces the rule that all KNX tables are named using the WP DB prefix
 * + the "knx_" namespace (e.g. Z7E_knx_items_categories). We intentionally
 * *do not* fallback to legacy or bare table names to avoid collisions with
 * other plugins/tables.
 */
function knx_table($name) {
    global $wpdb;
    $clean = preg_replace('/[^a-z0-9_]/i', '', $name);
    return $wpdb->prefix . 'knx_' . $clean;
}

/**
 * Resolve the items categories table name (canonical).
 */
function knx_items_categories_table() {
    return knx_table('items_categories');
}

/**
 * Generate a clean, SEO-friendly slug from hub name.
 * Removes special characters, accents, and ensures WordPress compatibility.
 * 
 * @param string $name The hub name to slugify
 * @param int $hub_id Optional hub ID for fallback if name is empty
 * @return string Clean slug containing only [a-z0-9-]
 */
function knx_slugify_hub_name($name, $hub_id = 0) {
    // Return fallback if name is empty
    if (empty(trim($name))) {
        return $hub_id ? "hub-{$hub_id}" : 'local-hub';
    }
    
    // Use WordPress sanitize_title for initial cleaning
    $slug = sanitize_title($name);
    
    // Additional cleanup to ensure only [a-z0-9-]
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    
    // Collapse multiple hyphens into single ones
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim hyphens from start and end
    $slug = trim($slug, '-');
    
    // Final fallback if somehow empty after cleaning
    if (empty($slug)) {
        return $hub_id ? "hub-{$hub_id}" : 'local-hub';
    }
    
    return $slug;
}

/**
 * Migrate existing hubs with empty slugs to generate proper slugs.
 * This function should be called once after deploying the slug functionality.
 * 
 * @return array Results of the migration
 */
function knx_migrate_hub_slugs() {
    global $wpdb;
    
    $table_hubs = $wpdb->prefix . 'knx_hubs';
    $updated = 0;
    $errors = 0;
    
    // Find hubs with empty or null slugs
    $hubs = $wpdb->get_results("
        SELECT id, name
        FROM {$table_hubs}
        WHERE slug IS NULL OR slug = ''
        ORDER BY id ASC
    ");
    
    if (!$hubs) {
        return [
            'updated' => 0,
            'errors' => 0,
            'message' => 'No hubs need slug migration'
        ];
    }
    
    foreach ($hubs as $hub) {
        $new_slug = knx_slugify_hub_name($hub->name, $hub->id);
        
        $result = $wpdb->update(
            $table_hubs,
            ['slug' => $new_slug],
            ['id' => $hub->id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            $updated++;
        } else {
            $errors++;
        }
    }
    
    return [
        'updated' => $updated,
        'errors' => $errors,
        'message' => "Migration completed: {$updated} hubs updated, {$errors} errors"
    ];
}
