<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Delete Hub (v3.0 Production)
 * ----------------------------------------------------------
 * Hard delete functionality for hubs with cascading cleanup.
 * ✅ Session validation (knx_get_session)
 * ✅ Secure nonce validation (knx_edit_hub_nonce)
 * ✅ Transaction safety with rollback
 * ✅ Cascading cleanup of all related data
 * ==========================================================
 */

add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/delete-hub', [
        'methods' => 'POST',
        'callback' => 'knx_api_delete_hub_v3',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

function knx_api_delete_hub_v3(WP_REST_Request $request) {
    global $wpdb;
    
    // Session validation (Kingdom Nexus standard)
    $session = knx_get_session();
    if (!$session) {
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);
    }
    
    // Role validation - only admins and hub managers can delete
    $allowed_roles = ['super_admin', 'manager', 'hub_management'];
    if (!in_array($session->role, $allowed_roles)) {
        return new WP_REST_Response(['success' => false, 'error' => 'insufficient_privileges'], 403);
    }
    
    // Get data from request
    $data = $request->get_json_params();
    $hub_id = intval($data['hub_id'] ?? 0);
    $nonce = sanitize_text_field($data['knx_nonce'] ?? '');
    
    // Validate nonce (Kingdom Nexus standard)
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);
    }
    
    if (!$hub_id) {
        return new WP_REST_Response(['success' => false, 'error' => 'missing_hub_id'], 400);
    }
    
    // Verify hub exists
    $hubs_table = $wpdb->prefix . 'knx_hubs';
    $hub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$hubs_table} WHERE id = %d", $hub_id));
    if (!$hub) {
        return new WP_REST_Response(['success' => false, 'error' => 'hub_not_found'], 404);
    }
    
    // Start transaction for safe deletion
    $wpdb->query('START TRANSACTION');
    
    try {
        // Tables to clean - only ones that actually use hub_id
        $hub_tables = [
            'knx_hub_items' => 'hub_id',
            'knx_item_categories' => 'hub_id',
            'knx_orders' => 'hub_id',
            'knx_order_items' => 'hub_id',
            'knx_item_addons' => 'hub_id',
            'knx_item_modifiers' => 'hub_id'
        ];
        
        // Clean related data first
        foreach ($hub_tables as $table => $hub_column) {
            $full_table = $wpdb->prefix . $table;
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            if (!$table_exists) {
                error_log("Table does not exist: $full_table");
                continue; // Skip non-existent tables
            }
            
            // Check if column exists in table
            $columns = $wpdb->get_col("DESCRIBE $full_table");
            if (!in_array($hub_column, $columns)) {
                error_log("Column $hub_column does not exist in table $full_table");
                continue;
            }
            
            $result = $wpdb->delete($full_table, [$hub_column => $hub_id], ['%d']);
            
            if ($result === false) {
                error_log("Failed to delete from $table: " . $wpdb->last_error);
                throw new Exception("Failed to delete from table: $table - " . $wpdb->last_error);
            }
            
            error_log("Successfully deleted from $table: $result rows");
        }
        
        // Handle delivery_rates separately (uses city_id, not hub_id)
        $delivery_rates_table = $wpdb->prefix . 'delivery_rates';
        if ($wpdb->get_var("SHOW TABLES LIKE '$delivery_rates_table'") === $delivery_rates_table) {
            // Only delete if this hub has a city_id and delivery rates exist for that city
            if (!empty($hub->city_id)) {
                $delivery_result = $wpdb->delete($delivery_rates_table, ['city_id' => $hub->city_id], ['%d']);
                if ($delivery_result !== false) {
                    error_log("Deleted delivery rates for city_id {$hub->city_id}: $delivery_result rows");
                }
            }
        }
        
        // Delete the hub itself
        $result = $wpdb->delete($hubs_table, ['id' => $hub_id], ['%d']);
        
        if ($result === false) {
            throw new Exception("Failed to delete hub: " . $wpdb->last_error);
        }
        
        error_log("Successfully deleted hub $hub_id");
        
        // Delete logo file if exists
        if (!empty($hub->logo_url)) {
            $upload_dir = wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $hub->logo_url);
            if (file_exists($logo_path)) {
                wp_delete_file($logo_path);
            }
        }
        
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ Hub deleted successfully'
        ], 200);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('Hub deletion failed: ' . $e->getMessage());
        
        return new WP_REST_Response([
            'success' => false,
            'message' => '❌ Failed to delete hub: ' . $e->getMessage()
        ], 500);
    }
}