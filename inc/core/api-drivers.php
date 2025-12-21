<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Drivers API
 * Driver registration and management
 */

add_action('rest_api_init', function() {
    
    // List drivers (admin only)
    register_rest_route('knx/v1', '/drivers', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_drivers',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Register driver
    register_rest_route('knx/v1', '/drivers', [
        'methods'  => 'POST',
        'callback' => 'knx_api_register_driver',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Update driver
    register_rest_route('knx/v1', '/drivers/(?P<id>\d+)', [
        'methods'  => 'PUT',
        'callback' => 'knx_api_update_driver',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Delete driver
    register_rest_route('knx/v1', '/drivers/(?P<id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => 'knx_api_delete_driver',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
});

/**
 * Get all drivers
 */
function knx_api_get_drivers(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    
    $table = $wpdb->prefix . 'knx_users';
    $search = sanitize_text_field($request->get_param('search'));
    $status = sanitize_text_field($request->get_param('status'));
    
    $where = ["role = 'driver'", "status != 'deleted'"];
    $params = [];
    
    if ($search) {
        $where[] = "(username LIKE %s OR email LIKE %s OR full_name LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
        $params[] = '%' . $wpdb->esc_like($search) . '%';
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    if ($status) {
        $where[] = "status = %s";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $where);
    $query = "SELECT id, username, email, full_name, status, created_at 
              FROM {$table} 
              WHERE {$where_clause}
              ORDER BY created_at DESC";
    
    if (!empty($params)) {
        $query = $wpdb->prepare($query, ...$params);
    }
    
    $drivers = $wpdb->get_results($query);
    
    return new WP_REST_Response([
        'success' => true,
        'drivers' => $drivers
    ], 200);
}

/**
 * Register new driver
 */
function knx_api_register_driver(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_drivers_nonce');
    
    $data = json_decode($request->get_body(), true);
    
    $username = sanitize_text_field($data['username'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $full_name = sanitize_text_field($data['full_name'] ?? '');
    $phone = sanitize_text_field($data['phone'] ?? '');
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'missing_required_fields'
        ], 400);
    }
    
    // Check if username/email already exists
    $table = $wpdb->prefix . 'knx_users';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE username = %s OR email = %s",
        $username, $email
    ));
    
    if ($exists) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'driver_exists'
        ], 400);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert driver
    $inserted = $wpdb->insert($table, [
        'username' => $username,
        'email' => $email,
        'password_hash' => $password_hash,
        'full_name' => $full_name,
        'role' => 'driver',
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    
    if (!$inserted) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'db_error'
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'driver_id' => $wpdb->insert_id,
        'message' => 'Driver registered successfully'
    ], 201);
}

/**
 * Update driver
 */
function knx_api_update_driver(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_drivers_nonce');
    
    $driver_id = intval($request['id']);
    $data = json_decode($request->get_body(), true);
    
    $table = $wpdb->prefix . 'knx_users';
    
    // Verify driver exists
    $driver = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND role = 'driver'",
        $driver_id
    ));
    
    if (!$driver) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'driver_not_found'
        ], 404);
    }
    
    // Build update data
    $update_data = [];
    $formats = [];
    
    if (isset($data['email'])) {
        $update_data['email'] = sanitize_email($data['email']);
        $formats[] = '%s';
    }
    
    if (isset($data['full_name'])) {
        $update_data['full_name'] = sanitize_text_field($data['full_name']);
        $formats[] = '%s';
    }
    
    if (isset($data['status'])) {
        $status = sanitize_text_field($data['status']);
        if (in_array($status, ['active', 'inactive'], true)) {
            $update_data['status'] = $status;
            $formats[] = '%s';
        }
    }
    
    if (isset($data['password']) && !empty($data['password'])) {
        $update_data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $formats[] = '%s';
    }
    
    if (empty($update_data)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'no_changes'
        ], 400);
    }
    
    $updated = $wpdb->update($table, $update_data, ['id' => $driver_id], $formats, ['%d']);
    
    if ($updated === false) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'db_error'
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Driver updated successfully'
    ], 200);
}

/**
 * Delete driver (soft delete)
 */
function knx_api_delete_driver(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_drivers_nonce');
    
    $driver_id = intval($request['id']);
    $table = $wpdb->prefix . 'knx_users';
    
    // Verify driver exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE id = %d AND role = 'driver'",
        $driver_id
    ));
    
    if (!$exists) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'driver_not_found'
        ], 404);
    }
    
    // Soft delete
    $deleted = $wpdb->update($table, 
        ['status' => 'deleted'], 
        ['id' => $driver_id],
        ['%s'],
        ['%d']
    );
    
    if ($deleted === false) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'db_error'
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Driver deleted successfully'
    ], 200);
}
