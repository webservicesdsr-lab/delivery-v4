<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * KNX Cities — Sealed GET Cities
 * ----------------------------------------------------------
 * Primary endpoint:
 * - GET /wp-json/knx/v1/knx-cities
 *
 * Transition aliases (so removing legacy get-cities.php doesn't break UI):
 * - GET /wp-json/knx/v2/cities/get
 * - GET /wp-json/knx/v2/cities
 *
 * Role scope:
 * - super_admin → ALL cities
 * - manager     → ONLY assigned city (best-effort resolution)
 * ==========================================================
 */

add_action('rest_api_init', function () {

    // Primary sealed route
    register_rest_route('knx/v1', '/knx-cities', [
        'methods'  => 'GET',
        'callback' => 'knx_sealed_get_cities',
        'permission_callback' => '__return_true',
    ]);

    // Compatibility routes (safe during migration)
    register_rest_route('knx/v2', '/cities/get', [
        'methods'  => 'GET',
        'callback' => 'knx_sealed_get_cities',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v2', '/cities', [
        'methods'  => 'GET',
        'callback' => 'knx_sealed_get_cities',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Local response wrapper (fallback-safe).
 */
if (!function_exists('knx_cities_rest')) {
    function knx_cities_rest($success, $message, $data = null, $status = 200) {
        if (function_exists('knx_rest_response')) {
            return knx_rest_response((bool) $success, (string) $message, $data, (int) $status);
        }
        return new WP_REST_Response([
            'success' => (bool) $success,
            'message' => (string) $message,
            'data'    => $data,
        ], (int) $status);
    }
}

/**
 * Column cache + detection.
 */
if (!function_exists('knx_cities_table_has_col')) {
    function knx_cities_table_has_col($table, $col) {
        static $cache = [];
        $key = $table . '::' . $col;
        if (isset($cache[$key])) return (bool) $cache[$key];

        global $wpdb;
        $like = $wpdb->esc_like($col);
        $exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE '{$like}'");
        $cache[$key] = $exists ? true : false;
        return (bool) $cache[$key];
    }
}

/**
 * Manager city resolver (best effort).
 */
if (!function_exists('knx_cities_resolve_manager_city_id')) {
    function knx_cities_resolve_manager_city_id($session) {
        global $wpdb;

        // 1) If session already has a city_id, trust it.
        if (isset($session->city_id) && absint($session->city_id) > 0) {
            return absint($session->city_id);
        }
        if (isset($session->cityId) && absint($session->cityId) > 0) {
            return absint($session->cityId);
        }

        $hubs_table = $wpdb->prefix . 'knx_hubs';

        // 2) If session has hub_id, resolve city_id from hubs.
        if (isset($session->hub_id) && absint($session->hub_id) > 0) {
            $city_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT city_id FROM {$hubs_table} WHERE id = %d LIMIT 1",
                absint($session->hub_id)
            ));
            return $city_id > 0 ? $city_id : 0;
        }

        // 3) If hubs table has manager_user_id, resolve from there.
        $user_id = isset($session->user_id) ? absint($session->user_id) : 0;
        if ($user_id > 0 && knx_cities_table_has_col($hubs_table, 'manager_user_id')) {
            $city_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT city_id FROM {$hubs_table} WHERE manager_user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ));
            return $city_id > 0 ? $city_id : 0;
        }

        return 0;
    }
}

function knx_sealed_get_cities(WP_REST_Request $request) {
    global $wpdb;

    if (!function_exists('knx_get_session')) {
        return knx_cities_rest(false, 'Session system not available', null, 500);
    }

    $session = knx_get_session();
    if (!$session) {
        return knx_cities_rest(false, 'Unauthorized', null, 401);
    }

    $role = isset($session->role) ? (string) $session->role : '';

    $cities_table = $wpdb->prefix . 'knx_cities';

    // Determine schema traits safely
    $has_deleted_at = knx_cities_table_has_col($cities_table, 'deleted_at');
    $has_status     = knx_cities_table_has_col($cities_table, 'status');
    $has_active     = knx_cities_table_has_col($cities_table, 'active');
    $has_operational= knx_cities_table_has_col($cities_table, 'operational');
    $has_state      = knx_cities_table_has_col($cities_table, 'state');
    $has_country    = knx_cities_table_has_col($cities_table, 'country');

    // Base WHERE for soft-delete (only if column exists)
    $where_soft = '';
    if ($has_deleted_at) {
        $where_soft = "WHERE (deleted_at IS NULL OR deleted_at = '')";
    }

    // SUPER ADMIN → ALL
    if ($role === 'super_admin') {
        $sql = "SELECT * FROM {$cities_table} {$where_soft} ORDER BY name ASC";
        $rows = $wpdb->get_results($sql);

        $cities = [];
        foreach ((array) $rows as $c) {
            $status = $has_status ? (string) ($c->status ?? 'inactive') : ( $has_active ? ((int) ($c->active ?? 0) === 1 ? 'active' : 'inactive') : 'active' );
            $operational = $has_operational ? (int) ($c->operational ?? 0) : (strtolower($status) === 'active' ? 1 : 0);

            $cities[] = [
                'id'          => absint($c->id ?? 0),
                'name'        => isset($c->name) ? (string) $c->name : '',
                'status'      => strtolower($status),
                'operational' => (int) $operational,
                'state'       => $has_state ? (string) ($c->state ?? '') : '',
                'country'     => $has_country ? (string) ($c->country ?? '') : '',
                'edit_url'    => site_url('/edit-city?id=' . absint($c->id ?? 0)),
            ];
        }

        return knx_cities_rest(true, 'Cities list', [
            'scope'  => 'all',
            'cities' => $cities,
        ], 200);
    }

    // MANAGER → OWN CITY ONLY
    if ($role === 'manager') {
        $city_id = knx_cities_resolve_manager_city_id($session);
        if ($city_id <= 0) {
            return knx_cities_rest(false, 'City not assigned', null, 403);
        }

        $where = $has_deleted_at
            ? "WHERE id = %d AND (deleted_at IS NULL OR deleted_at = '')"
            : "WHERE id = %d";

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cities_table} {$where} LIMIT 1", $city_id));
        if (!$row) {
            return knx_cities_rest(false, 'City not found', null, 404);
        }

        $status = $has_status ? (string) ($row->status ?? 'inactive') : ( $has_active ? ((int) ($row->active ?? 0) === 1 ? 'active' : 'inactive') : 'active' );
        $operational = $has_operational ? (int) ($row->operational ?? 0) : (strtolower($status) === 'active' ? 1 : 0);

        $city = [
            'id'          => absint($row->id ?? 0),
            'name'        => isset($row->name) ? (string) $row->name : '',
            'status'      => strtolower($status),
            'operational' => (int) $operational,
            'state'       => $has_state ? (string) ($row->state ?? '') : '',
            'country'     => $has_country ? (string) ($row->country ?? '') : '',
            'edit_url'    => site_url('/edit-city?id=' . absint($row->id ?? 0)),
        ];

        return knx_cities_rest(true, 'City scope', [
            'scope'  => 'own',
            'cities' => [$city],
        ], 200);
    }

    return knx_cities_rest(false, 'Forbidden', null, 403);
}
