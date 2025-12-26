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
function knx_require_role($role = 'user') {
    $session = knx_get_session();
    if (!$session) {
        return false;
    }

    $hierarchy = [
        'user'           => 1,
        'customer'       => 1,
        'menu_uploader'  => 2,
        'hub_management' => 3,
        'manager'        => 4,
        'super_admin'    => 5
    ];

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
function knx_guard($required_role = 'user') {
    $session = knx_require_role($required_role);

    if (!$session) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    return $session;
}

/**
 * Secure logout handler.
 * Deletes the current session and clears the cookie.
 */
function knx_logout_user() {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'knx_sessions';

    if (isset($_COOKIE['knx_session'])) {
        $token = sanitize_text_field($_COOKIE['knx_session']);

        // Delete session from database
        $wpdb->delete($sessions_table, ['token' => $token]);

        // Clear browser cookie securely
        setcookie('knx_session', '', time() - 3600, '/', '', is_ssl(), true);
    }

    // Ensure user is redirected to home or login
    wp_safe_redirect(site_url('/login'));
    exit;
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
