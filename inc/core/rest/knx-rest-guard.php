<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KNX REST — Guard Helpers (PHASE 1)
 * ----------------------------------------------------------
 * - No hooks
 * - No endpoints
 * - No output
 * ==========================================================
 */

if (!function_exists('knx_rest_get_session')) {
    /**
     * Returns current session or false.
     *
     * @return object|false
     */
    function knx_rest_get_session() {
        if (function_exists('knx_get_session')) {
            return knx_get_session();
        }
        return false;
    }
}

if (!function_exists('knx_rest_require_session')) {
    /**
     * Require a valid session or return WP_REST_Response error.
     *
     * @return object|WP_REST_Response
     */
    function knx_rest_require_session() {
        $session = knx_rest_get_session();
        if (!$session) {
            return knx_rest_error('Unauthorized', 401);
        }
        return $session;
    }
}

if (!function_exists('knx_rest_require_role')) {
    /**
     * Require role to be in allowed list.
     *
     * @param object $session
     * @param array  $allowed_roles
     * @return true|WP_REST_Response
     */
    function knx_rest_require_role($session, array $allowed_roles) {
        $role = isset($session->role) ? (string) $session->role : '';
        if (!$role || !in_array($role, $allowed_roles, true)) {
            return knx_rest_error('Forbidden', 403);
        }
        return true;
    }
}

if (!function_exists('knx_rest_verify_nonce')) {
    /**
     * Verify nonce and return true or error response.
     *
     * @param string $nonce
     * @param string $action
     * @return true|WP_REST_Response
     */
    function knx_rest_verify_nonce($nonce, $action) {
        $nonce = is_string($nonce) ? trim($nonce) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, $action)) {
            return knx_rest_error('Invalid nonce', 403);
        }
        return true;
    }
}
