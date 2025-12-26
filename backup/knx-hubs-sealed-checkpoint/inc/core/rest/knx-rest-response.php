<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KNX REST — Response Helpers (PHASE 1)
 * ----------------------------------------------------------
 * - No hooks
 * - No endpoints
 * - No output
 * ==========================================================
 */

if (!function_exists('knx_rest_response')) {
    /**
     * Standard JSON response wrapper.
     *
     * @param bool        $success
     * @param string      $message
     * @param mixed|null  $data
     * @param int         $status
     * @return WP_REST_Response
     */
    function knx_rest_response($success, $message = '', $data = null, $status = 200) {
        $payload = [
            'success' => (bool) $success,
            'message' => (string) $message,
        ];

        if (!is_null($data)) {
            $payload['data'] = $data;
        }

        return new WP_REST_Response($payload, (int) $status);
    }
}

if (!function_exists('knx_rest_error')) {
    /**
     * Error helper (same format).
     *
     * @param string $message
     * @param int    $status
     * @param mixed  $data
     * @return WP_REST_Response
     */
    function knx_rest_error($message, $status = 400, $data = null) {
        return knx_rest_response(false, $message, $data, $status);
    }
}
