<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - REST API Wrapper (Sanctum-like Security Layer)
 * 
 * Provides centralized route registration with built-in:
 * - CSRF protection (SameSite cookies + custom header)
 * - Authentication guards (KNX session + role hierarchy)
 * - Rate limiting (per IP or per session)
 * - Standardized JSON error responses
 * 
 * Usage:
 *   knx_register_route('knx/v1', '/cities', [
 *       'methods' => 'GET',
 *       'callback' => 'my_callback'
 *   ], [
 *       'requires_auth' => true,
 *       'min_role' => 'manager',
 *       'csrf' => false,  // GET requests
 *       'rate_limit' => ['limit' => 60, 'window' => 60, 'scope' => 'ip']
 *   ]);
 */

/**
 * Inject CSRF token into <head> on all pages (admin + public)
 */
add_action('wp_head', function() {
    $token = knx_csrf_get_token();
    if ($token) {
        echo '<meta name="knx-csrf" content="' . esc_attr($token) . '">' . "\n";
        echo '<script>window.KNX = window.KNX || {}; window.KNX.csrf = "' . esc_js($token) . '";</script>' . "\n";
    }
}, 1);

add_action('admin_head', function() {
    $token = knx_csrf_get_token();
    if ($token) {
        echo '<meta name="knx-csrf" content="' . esc_attr($token) . '">' . "\n";
        echo '<script>window.KNX = window.KNX || {}; window.KNX.csrf = "' . esc_js($token) . '";</script>' . "\n";
    }
}, 1);

/**
 * Generate or retrieve CSRF token for current session.
 * Token is stored in a non-HttpOnly cookie (accessible to JS).
 * 
 * @return string 64-character hex token
 */
function knx_csrf_get_token() {
    // Check if token already exists in cookie
    if (isset($_COOKIE['knx_csrf']) && strlen($_COOKIE['knx_csrf']) === 64) {
        return sanitize_text_field($_COOKIE['knx_csrf']);
    }
    
    // Generate new token (32 bytes = 64 hex characters)
    $token = bin2hex(random_bytes(32));
    
    // Set cookie (24 hours, non-HttpOnly for JS access)
    setcookie('knx_csrf', $token, [
        'expires' => time() + (24 * 60 * 60),
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => false,  // Must be accessible to JavaScript
        'samesite' => 'Strict'
    ]);
    
    return $token;
}

/**
 * Verify CSRF token from request.
 * Compares cookie value with X-KNX-CSRF header.
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|true Returns error response on failure, true on success
 */
function knx_csrf_verify_request($request) {
    // Get token from cookie
    $cookie_token = isset($_COOKIE['knx_csrf']) ? sanitize_text_field($_COOKIE['knx_csrf']) : '';
    
    // Get token from header
    $header_token = $request->get_header('X-KNX-CSRF');
    if (!$header_token) {
        $header_token = $request->get_header('x-knx-csrf'); // Fallback lowercase
    }
    $header_token = $header_token ? sanitize_text_field($header_token) : '';
    
    // Both must exist
    if (empty($cookie_token) || empty($header_token)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'csrf_missing',
            'code' => 'CSRF001',
            'message' => 'CSRF token missing. Please refresh the page.'
        ], 403);
    }
    
    // Must match (constant-time comparison)
    if (!hash_equals($cookie_token, $header_token)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'csrf_invalid',
            'code' => 'CSRF002',
            'message' => 'CSRF token mismatch. Please refresh the page.'
        ], 403);
    }
    
    return true;
}

/**
 * Rate limit check for REST endpoint.
 * 
 * @param array $config ['limit' => 60, 'window' => 60, 'scope' => 'ip'|'session']
 * @return WP_REST_Response|true Returns error response if rate limited, true otherwise
 */
function knx_rest_rate_limit($config) {
    $limit = isset($config['limit']) ? (int)$config['limit'] : 60;
    $window = isset($config['window']) ? (int)$config['window'] : 60;
    $scope = isset($config['scope']) ? $config['scope'] : 'ip';
    
    // Build rate limit key
    if ($scope === 'session' && isset($_COOKIE['knx_session'])) {
        $identifier = substr(sanitize_text_field($_COOKIE['knx_session']), 0, 16);
    } else {
        $identifier = function_exists('knx_get_client_ip') 
            ? knx_get_client_ip() 
            : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    
    $key = 'knx_rate_' . md5($identifier . $_SERVER['REQUEST_URI']);
    $hits = (int)get_transient($key);
    
    if ($hits >= $limit) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'rate_limited',
            'code' => 'RATE001',
            'message' => 'Too many requests. Please try again later.'
        ], 429);
    }
    
    set_transient($key, $hits + 1, $window);
    return true;
}

/**
 * Check authentication and role requirement.
 * 
 * @param string $min_role Minimum required role
 * @return WP_REST_Response|object Returns error response or session object
 */
function knx_rest_auth_guard($min_role) {
    $session = function_exists('knx_get_session') ? knx_get_session() : false;
    
    if (!$session) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'unauthorized',
            'code' => 'AUTH001',
            'message' => 'You must be logged in to access this endpoint.'
        ], 401);
    }
    
    // Check role hierarchy
    if (function_exists('knx_get_role_hierarchy')) {
        $hierarchy = knx_get_role_hierarchy();
        $user_level = isset($hierarchy[$session->role]) ? $hierarchy[$session->role] : 0;
        $min_level = isset($hierarchy[$min_role]) ? $hierarchy[$min_role] : 999;
        
        if ($user_level < $min_level) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'forbidden',
                'code' => 'AUTH002',
                'message' => 'Your account role cannot access this feature.'
            ], 403);
        }
    }
    
    return $session;
}

/**
 * Register a REST route with built-in security layers.
 * 
 * @param string $namespace Route namespace (e.g., 'knx/v1')
 * @param string $route Route path (e.g., '/cities')
 * @param array $args Standard WP REST args (methods, callback, args, etc.)
 * @param array $opts Security options:
 *   - requires_auth (bool): Require KNX session
 *   - min_role (string): Minimum role ('customer', 'manager', etc.)
 *   - csrf (bool): Require CSRF token for write methods
 *   - rate_limit (array|false): ['limit' => 60, 'window' => 60, 'scope' => 'ip']
 */
function knx_register_route($namespace, $route, $args, $opts = []) {
    // Default options
    $defaults = [
        'requires_auth' => false,
        'min_role' => 'customer',
        'csrf' => false,
        'rate_limit' => false
    ];
    $opts = array_merge($defaults, $opts);
    
    // Store original callback
    $original_callback = isset($args['callback']) ? $args['callback'] : null;
    if (!$original_callback || !is_callable($original_callback)) {
        trigger_error("knx_register_route: Invalid callback for route {$route}", E_USER_WARNING);
        return;
    }
    
    // Detect if this is a write operation
    $methods = isset($args['methods']) ? $args['methods'] : 'GET';
    if (!is_array($methods)) {
        $methods = [$methods];
    }
    $is_write = false;
    foreach ($methods as $method) {
        $method = strtoupper($method);
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $is_write = true;
            break;
        }
    }
    
    // Wrap callback with security layers
    $args['callback'] = function($request) use ($original_callback, $opts, $is_write) {
        // Layer 1: Rate limiting
        if ($opts['rate_limit'] !== false) {
            $rate_check = knx_rest_rate_limit($opts['rate_limit']);
            if ($rate_check !== true) {
                return $rate_check; // Return 429 response
            }
        }
        
        // Layer 2: Authentication guard
        if ($opts['requires_auth']) {
            $auth_check = knx_rest_auth_guard($opts['min_role']);
            if ($auth_check instanceof WP_REST_Response) {
                return $auth_check; // Return 401/403 response
            }
            // Store session in request for use in callback
            $request->set_param('_knx_session', $auth_check);
        }
        
        // Layer 3: CSRF verification (only for write operations when enabled)
        if ($opts['csrf'] && $is_write) {
            $csrf_check = knx_csrf_verify_request($request);
            if ($csrf_check !== true) {
                return $csrf_check; // Return 403 response
            }
        }
        
        // All checks passed - call original callback
        return call_user_func($original_callback, $request);
    };
    
    // Always use our permission callback (since we handle auth inside wrapped callback)
    $args['permission_callback'] = '__return_true';
    
    // Register the route
    register_rest_route($namespace, $route, $args);
}

/**
 * Initialize CSRF token on page load.
 * Called early in WordPress lifecycle.
 */
add_action('init', function() {
    // Generate CSRF token if not exists
    // This ensures token is available before any output
    if (!isset($_COOKIE['knx_csrf'])) {
        knx_csrf_get_token();
    }
}, 1);
