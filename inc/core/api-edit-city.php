<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Edit City (SEALED v3)
 * ----------------------------------------------------------
 * v2 Endpoints:
 * - GET  /wp-json/knx/v2/cities/get-city?city_id=123   (or id=123)
 * - POST /wp-json/knx/v2/cities/update-city           (JSON)
 *
 * Payload (POST JSON):
 * - city_id (int) or id (int)
 * - name (string)
 * - status (active|inactive)
 * - knx_nonce (string)  (action: knx_edit_city_nonce)
 *
 * Security:
 * - Session required (route-level + handler-level)
 * - Roles: super_admin | manager
 * - Manager scope enforced (must have hub(s) in city)
 * - Nonce required for update
 *
 * Back-compat:
 * - v1 routes kept as shims, but NOW guarded (no __return_true)
 * ==========================================================
 */

add_action('rest_api_init', function () {

    // Permission callback (guarded). Fallback is safe if guard isn't loaded for any reason.
    $perm_roles = function (array $roles) {
        if (function_exists('knx_rest_permission_roles')) {
            return knx_rest_permission_roles($roles);
        }
        return function ($request) use ($roles) {
            $s = function_exists('knx_get_session') ? knx_get_session() : null;
            return (is_object($s) && isset($s->role) && in_array($s->role, $roles, true));
        };
    };

    // Lazy wrapper callback builder
    $cb = function (string $handler_fn) {
        return function ($request) use ($handler_fn) {
            if (function_exists('knx_rest_wrap')) {
                return knx_rest_wrap($handler_fn)($request);
            }
            return call_user_func($handler_fn, $request);
        };
    };

    /**
     * =========================
     * v2 (SEALED)
     * =========================
     */
    register_rest_route('knx/v2', '/cities/get-city', [
        'methods'  => 'GET',
        'callback' => $cb('knx_api_v2_cities_get_city'),
        'permission_callback' => $perm_roles(['super_admin', 'manager']),
    ]);

    register_rest_route('knx/v2', '/cities/update-city', [
        'methods'  => 'POST',
        'callback' => $cb('knx_api_v2_cities_update_city'),
        'permission_callback' => $perm_roles(['super_admin', 'manager']),
    ]);

    /**
     * =========================
     * v1 shims (guarded now)
     * =========================
     * Keep existing JS working while you migrate UI to v2.
     */
    register_rest_route('knx/v1', '/get-city', [
        'methods'  => 'GET',
        'callback' => $cb('knx_api_v2_cities_get_city'),
        'permission_callback' => $perm_roles(['super_admin', 'manager']),
    ]);

    register_rest_route('knx/v1', '/update-city', [
        'methods'  => 'POST',
        'callback' => $cb('knx_api_v2_cities_update_city'),
        'permission_callback' => $perm_roles(['super_admin', 'manager']),
    ]);
});

/**
 * ==========================================================
 * Helpers (file-scoped, prefixed to avoid collisions)
 * ==========================================================
 */

function knx_api__get_session_safe() {
    if (function_exists('knx_rest_get_session')) return knx_rest_get_session();
    if (function_exists('knx_get_session')) return knx_get_session();
    return null;
}

function knx_api__deny($error, $status = 403) {
    return new WP_REST_Response(['success' => false, 'error' => $error], (int)$status);
}

function knx_api__ok($payload, $status = 200) {
    if (!is_array($payload)) $payload = ['data' => $payload];
    $payload = array_merge(['success' => true], $payload);
    return new WP_REST_Response($payload, (int)$status);
}

function knx_api__city_is_soft_deleted($city_row) {
    if (!is_object($city_row)) return true;

    $checks = [
        ['deleted_at', function ($v) { return !empty($v) && $v !== '0000-00-00 00:00:00'; }],
        ['is_deleted', function ($v) { return (int)$v === 1; }],
        ['deleted', function ($v) { return (int)$v === 1; }],
        ['archived', function ($v) { return (int)$v === 1; }],
        ['status', function ($v) { return is_string($v) && strtolower($v) === 'deleted'; }],
    ];

    foreach ($checks as [$field, $fn]) {
        if (property_exists($city_row, $field)) {
            try {
                if ($fn($city_row->{$field})) return true;
            } catch (\Throwable $e) {
                return true; // fail closed on malformed rows
            }
        }
    }

    return false;
}

function knx_api__hubs_table_exists($wpdb) {
    $table_hubs = $wpdb->prefix . 'knx_hubs';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_hubs));
    return $exists ? $table_hubs : false;
}

/**
 * Manager scope check:
 * - If session has hub_id or hub_ids, require at least one hub in that city.
 * - Else fallback to "city has any hubs" (still restrictive).
 * - If hubs table missing => deny (fail closed).
 */
function knx_api__manager_has_city_scope($session, $city_id) {
    global $wpdb;

    $city_id = absint($city_id);
    if (!$city_id) return false;

    $table_hubs = knx_api__hubs_table_exists($wpdb);
    if (!$table_hubs) return false;

    $hub_ids = [];

    if (is_object($session) && isset($session->hub_id)) {
        $hub_ids = [absint($session->hub_id)];
    } elseif (is_object($session) && isset($session->hub_ids) && is_array($session->hub_ids)) {
        $hub_ids = array_values(array_filter(array_map('absint', $session->hub_ids)));
    }

    // Strict: match hub_ids -> city_id
    if (!empty($hub_ids)) {
        $hub_ids = array_values(array_unique($hub_ids));
        $placeholders = implode(',', array_fill(0, count($hub_ids), '%d'));

        $params = $hub_ids;
        $params[] = $city_id;

        $sql = $wpdb->prepare(
            "SELECT COUNT(1) FROM {$table_hubs} WHERE id IN ({$placeholders}) AND city_id = %d",
            $params
        );

        return ((int)$wpdb->get_var($sql)) > 0;
    }

    // Fallback: any hub in the city
    $count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM {$table_hubs} WHERE city_id = %d",
        $city_id
    ));

    return $count > 0;
}

/**
 * ==========================================================
 * Handlers
 * ==========================================================
 */

/**
 * GET city by id (guarded)
 */
function knx_api_v2_cities_get_city(WP_REST_Request $r) {
    global $wpdb;

    $session = knx_api__get_session_safe();
    if (!is_object($session) || !isset($session->role) || !in_array($session->role, ['super_admin', 'manager'], true)) {
        return knx_api__deny('unauthorized', 403);
    }

    $city_id = absint($r->get_param('city_id') ?: $r->get_param('id'));
    if (!$city_id) {
        return knx_api__deny('missing_id', 400);
    }

    // Manager scope
    if ($session->role === 'manager' && !knx_api__manager_has_city_scope($session, $city_id)) {
        return knx_api__deny('forbidden_scope', 403);
    }

    $table_cities = $wpdb->prefix . 'knx_cities';
    $city = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_cities} WHERE id = %d", $city_id));

    if (!$city || knx_api__city_is_soft_deleted($city)) {
        return knx_api__deny('not_found', 404);
    }

    return knx_api__ok(['city' => $city], 200);
}

/**
 * POST update city (guarded + nonce)
 */
function knx_api_v2_cities_update_city(WP_REST_Request $r) {
    global $wpdb;

    $session = knx_api__get_session_safe();
    if (!is_object($session) || !isset($session->role) || !in_array($session->role, ['super_admin', 'manager'], true)) {
        return knx_api__deny('unauthorized', 403);
    }

    $data = $r->get_json_params();
    if (!is_array($data)) {
        $data = json_decode($r->get_body(), true);
    }
    if (!is_array($data)) $data = [];

    $city_id = absint($data['city_id'] ?? $data['id'] ?? 0);
    $name    = sanitize_text_field($data['name'] ?? '');
    $status  = sanitize_text_field($data['status'] ?? 'active');
    $nonce   = sanitize_text_field($data['knx_nonce'] ?? '');

    if (!$city_id || $name === '') {
        return knx_api__deny('missing_fields', 400);
    }

    $allowed_status = ['active', 'inactive'];
    if (!in_array($status, $allowed_status, true)) {
        $status = 'active';
    }

    // Nonce required
    if (!wp_verify_nonce($nonce, 'knx_edit_city_nonce')) {
        return knx_api__deny('invalid_nonce', 403);
    }

    // Manager scope
    if ($session->role === 'manager' && !knx_api__manager_has_city_scope($session, $city_id)) {
        return knx_api__deny('forbidden_scope', 403);
    }

    // Ensure city exists + not soft deleted
    $table_cities = $wpdb->prefix . 'knx_cities';
    $city = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_cities} WHERE id = %d", $city_id));
    if (!$city || knx_api__city_is_soft_deleted($city)) {
        return knx_api__deny('not_found', 404);
    }

    $updated = $wpdb->update(
        $table_cities,
        [
            'name'       => $name,
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $city_id],
        ['%s', '%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        return knx_api__deny('db_error', 500);
    }

    return knx_api__ok([
        'message' => $updated ? '✅ City updated successfully' : 'ℹ️ No changes detected'
    ], 200);
}
