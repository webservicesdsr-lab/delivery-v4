<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KNX REST — Wrapper (PHASE 1)
 * ----------------------------------------------------------
 * This is a tiny "controller wrapper" that:
 * - catches fatals/exceptions (best effort)
 * - normalizes returns to WP_REST_Response
 *
 * IMPORTANT:
 * - No hooks
 * - No endpoints
 * - No output
 * ==========================================================
 */

if (!function_exists('knx_rest_wrap')) {
    /**
     * Wrap a REST handler callback.
     *
     * Usage in a resource:
     * register_rest_route(... [
     *   'callback' => knx_rest_wrap('my_handler')
     * ])
     *
     * @param callable|string $handler Callable or function name.
     * @return callable
     */
    function knx_rest_wrap($handler) {
        return function(WP_REST_Request $request) use ($handler) {

            try {
                if (is_string($handler) && function_exists($handler)) {
                    $out = call_user_func($handler, $request);
                } elseif (is_callable($handler)) {
                    $out = call_user_func($handler, $request);
                } else {
                    return knx_rest_error('Invalid handler', 500);
                }

                // If handler returned WP_REST_Response already, pass through.
                if ($out instanceof WP_REST_Response) {
                    return $out;
                }

                // If handler returned WP_Error, normalize.
                if (is_wp_error($out)) {
                    $status = (int) ($out->get_error_data('status') ?: 500);
                    return knx_rest_error($out->get_error_message(), $status, [
                        'code' => $out->get_error_code(),
                    ]);
                }

                // If handler returned array/object, wrap as success.
                if (is_array($out) || is_object($out)) {
                    return knx_rest_response(true, 'OK', $out, 200);
                }

                // Fallback scalar
                return knx_rest_response(true, 'OK', ['result' => $out], 200);

            } catch (Throwable $e) {
                // Never leak full error details in production.
                return knx_rest_error('Server error', 500);
            }
        };
    }
}
