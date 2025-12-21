<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Users CRUD API
 * Admin-only user management
 */

add_action('rest_api_init', function() {
    
    // List users
    register_rest_route('knx/v1', '/users', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_users',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Create user
    register_rest_route('knx/v1', '/users', [
        'methods'  => 'POST',
        'callback' => 'knx_api_create_user',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Update user
    register_rest_route('knx/v1', '/users/(?P<id>\d+)', [
        'methods'  => 'PUT',
        'callback' => 'knx_api_update_user',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
    
    // Delete user
    register_rest_route('knx/v1', '/users/(?P<id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => 'knx_api_delete_user',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager']),
    ]);
});

/**
 * Get all users
 */
function knx_api_get_users(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    
    $table = $wpdb->prefix . 'knx_users';
    $role = sanitize_text_field($request->get_param('role'));
    $search = sanitize_text_field($request->get_param('search'));
    
    $where = ["status != 'deleted'"];
    $params = [];
    
    if ($role) {
        $where[] = "role = %s";
        $params[] = $role;
    }
    
    if ($search) {
        $where[] = "(username LIKE %s OR email LIKE %s OR full_name LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
        $params[] = '%' . $wpdb->esc_like($search) . '%';
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    $where_clause = implode(' AND ', $where);
    $query = "SELECT id, username, email, full_name, role, status, created_at 
              FROM {$table} 
              WHERE {$where_clause}
              ORDER BY created_at DESC";
    
    if (!empty($params)) {
        $query = $wpdb->prepare($query, ...$params);
    }
    
    $users = $wpdb->get_results($query);
    
    return new WP_REST_Response([
        'success' => true,
        'users' => $users
    ], 200);
}

/**
 * Create new user
 */
function knx_api_create_user(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_users_nonce');
    
    $data = json_decode($request->get_body(), true);
    
    $username = sanitize_text_field($data['username'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $full_name = sanitize_text_field($data['full_name'] ?? '');
    $role = sanitize_text_field($data['role'] ?? 'customer');
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'missing_fields'
        ], 400);
    }
    
    // Validate role
    $valid_roles = ['super_admin', 'manager', 'hub_management', 'menu_uploader', 'driver', 'customer'];
    if (!in_array($role, $valid_roles, true)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'invalid_role'
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
            'error' => 'user_exists'
        ], 400);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $inserted = $wpdb->insert($table, [
        'username' => $username,
        'email' => $email,
        'password_hash' => $password_hash,
        'full_name' => $full_name,
        'role' => $role,
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
        'user_id' => $wpdb->insert_id,
        'message' => 'User created successfully'
    ], 201);
}

/**
 * Update user
 */
function knx_api_update_user(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_users_nonce');
    
    $user_id = intval($request['id']);
    $data = json_decode($request->get_body(), true);
    
    $table = $wpdb->prefix . 'knx_users';
    
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
    
    if (isset($data['role'])) {
        $role = sanitize_text_field($data['role']);
        $valid_roles = ['super_admin', 'manager', 'hub_management', 'menu_uploader', 'driver', 'customer'];
        if (in_array($role, $valid_roles, true)) {
            $update_data['role'] = $role;
            $formats[] = '%s';
        }
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
    
    $updated = $wpdb->update($table, $update_data, ['id' => $user_id], $formats, ['%d']);
    
    if ($updated === false) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'db_error'
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'message' => 'User updated successfully'
    ], 200);
}

/**
 * Delete user (soft delete)
 */
function knx_api_delete_user(WP_REST_Request $request) {
    global $wpdb;
    
    knx_require_role(['super_admin', 'manager']);
    knx_require_nonce('knx_users_nonce');
    
    $user_id = intval($request['id']);
    $table = $wpdb->prefix . 'knx_users';
    
    // Prevent deleting yourself
    $session = knx_get_session();
    if ($user_id === intval($session->user_id)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'cannot_delete_self'
        ], 400);
    }
    
    // Soft delete
    $deleted = $wpdb->update($table, 
        ['status' => 'deleted'], 
        ['id' => $user_id],
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
        'message' => 'User deleted successfully'
    ], 200);
}
