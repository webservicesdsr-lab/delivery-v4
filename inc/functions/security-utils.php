<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Security Utilities (Minimal Version)
 * Created to restore broken backup - contains only essential functions
 */

/**
 * Permission callback for role-based access
 * Returns a callable that checks if user has required role
 */
function knx_permission_callback($allowed_roles) {
    return function() use ($allowed_roles) {
        $session = function_exists('knx_get_session') ? knx_get_session() : null;
        
        if (!$session) {
            return false;
        }
        
        return in_array($session->role, $allowed_roles, true);
    };
}

/**
 * Permission callback for public endpoints
 * Returns a callable that always allows access
 */
function knx_permission_public() {
    return function() {
        return true;
    };
}

/**
 * Permission callback for authenticated users
 * Returns a callable that checks for valid session
 */
function knx_permission_authenticated() {
    return function() {
        $session = function_exists('knx_get_session') ? knx_get_session() : null;
        return $session !== null && $session !== false;
    };
}

/**
 * Require valid session (aborts if no session)
 */
function knx_require_session() {
    $session = function_exists('knx_get_session') ? knx_get_session() : null;
    
    if (!$session) {
        wp_send_json_error(['message' => 'Authentication required'], 401);
        exit;
    }
    
    return $session;
}

/**
 * Require specific role (aborts if role not allowed)
 */
function knx_require_role($allowed_roles) {
    $session = knx_require_session();
    
    if (!in_array($session->role, $allowed_roles, true)) {
        wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        exit;
    }
    
    return $session;
}

/**
 * Abort request with error
 */
function knx_abort($message, $code = 400) {
    wp_send_json_error(['message' => $message], $code);
    exit;
}

/**
 * Require valid nonce for state-changing operations
 */
function knx_require_nonce($action, $param = 'knx_nonce') {
    $nonce = null;
    
    // Check REST request body
    $request = rest_get_server()->get_raw_data();
    if ($request) {
        $data = json_decode($request, true);
        if (is_array($data)) {
            $nonce = $data[$param] ?? $data['_wpnonce'] ?? $data['nonce'] ?? null;
        }
    }
    
    // Fallback to $_REQUEST
    if (!$nonce) {
        $nonce = $_REQUEST[$param] ?? $_REQUEST['_wpnonce'] ?? $_REQUEST['nonce'] ?? null;
    }
    
    if (!$nonce || !wp_verify_nonce($nonce, $action)) {
        knx_abort('Invalid security token', 403);
    }
}

/**
 * Rate limiting using transients
 */
function knx_require_rate_limit($key, $max_requests = 20, $window_seconds = 60) {
    $transient_key = 'knx_rate_' . md5($key);
    $count = get_transient($transient_key);
    
    if ($count === false) {
        set_transient($transient_key, 1, $window_seconds);
        return;
    }
    
    if ($count >= $max_requests) {
        knx_abort('Rate limit exceeded. Please try again later.', 429);
    }
    
    set_transient($transient_key, $count + 1, $window_seconds);
}

/**
 * Require ownership of a resource (for hub_management role)
 */
function knx_require_ownership($entity_type, $entity_id) {
    global $wpdb;
    
    $session = knx_require_session();
    
    // Super admin and manager bypass ownership checks
    if (in_array($session->role, ['super_admin', 'manager'], true)) {
        return;
    }
    
    // hub_management must own the hub
    if ($session->role === 'hub_management' && $entity_type === 'hub') {
        $table = $wpdb->prefix . 'knx_hub_managers';
        $owns = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND hub_id = %d",
            $session->user_id,
            intval($entity_id)
        ));
        
        if (!$owns) {
            knx_abort('You do not own this hub', 403);
        }
        return;
    }
    
    // Other roles not allowed
    knx_abort('Insufficient permissions', 403);
}
