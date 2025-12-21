<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 *  KINGDOM NEXUS - SHIELD (v1)
 *  Enterprise Security Layer for all KNX API Endpoints
 * ----------------------------------------------------------
 *  Provides:
 *   ✔ Session validation (knx_sessions)
 *   ✔ HMAC request signing (Stripe-level)
 *   ✔ Anti-replay window (30s timestamp)
 *   ✔ IP rate-limiting
 *   ✔ Role requirements (optional)
 *   ✔ Secure JSON input/output helpers
 *
 *  Every sensitive API should call:
 *       knx_shield_require_auth();
 *       knx_shield_require_signature();
 *
 *  Optional:
 *       knx_shield_require_role('manager');
 *
 * ==========================================================
 */


/* ----------------------------------------------------------
 * 1. SAFE JSON INPUT HELPERS
 * ---------------------------------------------------------- */
function knx_shield_json_input() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function knx_shield_json_response($data, $code = 200) {
    status_header($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}


/* ----------------------------------------------------------
 * 2. SESSION AUTHENTICATION (NEXUS SESSIONS)
 * ---------------------------------------------------------- */
function knx_shield_require_auth() {
    $session = knx_get_session(); // Your existing session resolver

    if (!$session) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'unauthorized',
            'message' => 'You must be logged in.'
        ], 401);
    }

    return $session;
}


/* ----------------------------------------------------------
 * 3. HMAC SIGNATURE VERIFICATION
 * ----------------------------------------------------------
 *  Ensures request integrity:
 *   client signs body with:
 *      sign = HMAC_SHA256(body, secret)
 *   header:
 *      X-KNX-Signature: sign
 *
 *  secret lives ONLY in backend + Stripe keys page.
 * ---------------------------------------------------------- */
function knx_shield_get_secret() {
    $secret = get_option('knx_api_secret');
    if (!$secret) {
        // auto-generate ONCE
        $secret = bin2hex(random_bytes(32));
        update_option('knx_api_secret', $secret, true);
    }
    return $secret;
}

function knx_shield_require_signature() {
    $signature = $_SERVER['HTTP_X_KNX_SIGNATURE'] ?? '';
    $timestamp = $_SERVER['HTTP_X_KNX_TIMESTAMP'] ?? '';

    if (!$signature || !$timestamp) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'missing-signature',
            'message' => 'Signature or timestamp missing.'
        ], 400);
    }

    // Reject requests older than 30 seconds (anti–replay)
    if (abs(time() - intval($timestamp)) > 30) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'expired-request',
            'message' => 'Timestamp expired.'
        ], 400);
    }

    $raw = file_get_contents('php://input');
    $secret = knx_shield_get_secret();

    $expected = hash_hmac('sha256', $raw . $timestamp, $secret);

    if (!hash_equals($expected, $signature)) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'invalid-signature',
            'message' => 'Request signature mismatch.'
        ], 403);
    }
}


/* ----------------------------------------------------------
 * 4. ROLE REQUIREMENT (OPTIONAL)
 * ---------------------------------------------------------- */
function knx_shield_require_role($required_role = 'customer') {
    $session = knx_shield_require_auth();

    $hier = knx_get_role_hierarchy();
    $role = $session->role;

    if (!isset($hier[$role]) || $hier[$role] < $hier[$required_role]) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'forbidden',
            'message' => 'Insufficient permissions.'
        ], 403);
    }

    return $session;
}


/* ----------------------------------------------------------
 * 5. RATE LIMITING PER IP (GLOBAL ANTI-SPAM)
 * ---------------------------------------------------------- */
function knx_shield_rate_limit($key, $max = 30, $window = 60) {
    $ip  = knx_get_client_ip();
    $tag = "knx_rl_{$key}_" . md5($ip);

    $count = get_transient($tag);

    if ($count === false) {
        set_transient($tag, 1, $window);
        return true;
    }

    if ($count >= $max) {
        knx_shield_json_response([
            'success' => false,
            'error'   => 'rate-limit',
            'message' => 'Too many requests. Slow down.'
        ], 429);
    }

    set_transient($tag, $count + 1, $window);
    return true;
}
