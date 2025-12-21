<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Core Settings API (v3.2)
 * ----------------------------------------
 * Handles saving and reading Google Maps API keys for Nexus modules.
 * Auto-detects hybrid prefixes like "Z7E_knx_" for compatibility.
 */

add_action('rest_api_init', function() {
    register_rest_route('knx/v1', '/update-settings', [
        'methods'             => 'POST',
        'callback'            => 'knx_api_update_settings',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

/**
 * Save or update the Google Maps API key (auto-creates table if missing)
 */
function knx_api_update_settings(WP_REST_Request $r) {
    global $wpdb;

    // --- Detect correct prefixed table name ---
    $base_prefix = $wpdb->prefix; // e.g. Z7E_
    $table = $base_prefix . 'knx_settings';

    // --- Create the table if it doesn't exist ---
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id INT(11) NOT NULL AUTO_INCREMENT,
            google_maps_api VARCHAR(255) DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    $google_maps_api = sanitize_text_field($r['google_maps_api']);

    // Allow empty string to clear the key (for dual map testing)
    // Just validate it's a string, not null/undefined
    if (!isset($r['google_maps_api'])) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'missing_parameter',
            'message' => 'google_maps_api parameter is required (can be empty string to clear).'
        ], 400);
    }

    // --- Replace any previous key (empty string clears it) ---
    $wpdb->query("TRUNCATE TABLE $table");
    
    // Only insert if there's a key, otherwise leave table empty
    if (!empty($google_maps_api)) {
        $wpdb->insert($table, [
            'google_maps_api' => $google_maps_api,
            'updated_at'      => current_time('mysql'),
        ]);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => empty($google_maps_api) 
            ? 'API key cleared. System will use OpenStreetMap.' 
            : 'Settings updated successfully.',
        'google_maps_api' => $google_maps_api
    ], 200);
}

/**
 * Helper: Get the current Google Maps API key (for other modules)
 */
function knx_get_google_maps_key() {
    global $wpdb;
    $base_prefix = $wpdb->prefix;
    $table = $base_prefix . 'knx_settings';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return null;
    return $wpdb->get_var("SELECT google_maps_api FROM $table ORDER BY id DESC LIMIT 1");
}
