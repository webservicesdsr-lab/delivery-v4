<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Edit City Template (SEALED v3)
 * ----------------------------------------------------------
 * Shortcode: [knx_edit_city]
 *
 * Goals:
 * - Hard gate access (session + role + manager scope)
 * - Safe redirect (server-side when possible, JS fallback if headers already sent)
 * - Theme-safe wrapper
 * - Sidebar layout WITHOUT wp_footer dependency
 * - Keep existing Delivery Rates UI unchanged (no internal refactor here)
 *
 * NOTE:
 * - City Info now uses v2 SEALED endpoints.
 * - Delivery Rates remains v1 for now (out of scope).
 * ==========================================================
 */

add_shortcode('knx_edit_city', function () {
    global $wpdb;

    /** Base URLs */
    $back_cities_url = site_url('/cities');

    /**
     * Redirect helper safe for shortcode context.
     * Returns a JS/meta redirect if headers already sent.
     */
    $knx_redirect = function ($url) {
        $url = esc_url_raw($url);

        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        }

        $safe = esc_url($url);

        return ''
            . '<script>window.location.href=' . wp_json_encode($safe) . ';</script>'
            . '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($safe) . '"></noscript>';
    };

    /**
     * Soft-delete detector (best-effort, schema-agnostic).
     */
    $knx_city_is_soft_deleted = function ($city_row) {
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
                    return true; // fail closed on malformed row
                }
            }
        }

        return false;
    };

    /**
     * Manager scope check (best-effort):
     * - Prefer session->hub_id / session->hub_ids if present.
     * - Otherwise, fallback to "city has hubs" (still restricted, but less strict).
     *
     * If hubs table does not exist => deny managers (fail closed).
     */
    $knx_manager_has_city_scope = function ($session, $city_id) use ($wpdb) {
        $table_hubs = $wpdb->prefix . 'knx_hubs';

        // Verify hubs table exists
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_hubs));
        if (!$exists) return false;

        $city_id = absint($city_id);
        if (!$city_id) return false;

        // 1) Strict scope via hub_id / hub_ids if available
        $hub_ids = [];

        if (is_object($session) && isset($session->hub_id)) {
            $hub_ids = [absint($session->hub_id)];
        } elseif (is_object($session) && isset($session->hub_ids) && is_array($session->hub_ids)) {
            $hub_ids = array_values(array_filter(array_map('absint', $session->hub_ids)));
        }

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

        // 2) Fallback: any hub in city
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table_hubs} WHERE city_id = %d",
            $city_id
        ));

        return $count > 0;
    };

    /** =========================
     * 1) Session + Role Gate
     * ========================= */
    $session = knx_get_session();
    if (!is_object($session) || !isset($session->role) || !in_array($session->role, ['super_admin', 'manager'], true)) {
        return $knx_redirect($back_cities_url);
    }

    /** =========================
     * 2) City ID Gate
     * ========================= */
    $city_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if (!$city_id) {
        return $knx_redirect($back_cities_url);
    }

    /** =========================
     * 3) City Exists + Not Soft Deleted
     * ========================= */
    $table_cities = $wpdb->prefix . 'knx_cities';
    $city = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_cities} WHERE id = %d", $city_id));

    if (!$city || $knx_city_is_soft_deleted($city)) {
        return $knx_redirect($back_cities_url);
    }

    /** =========================
     * 4) Manager Scope Gate
     * ========================= */
    if ($session->role === 'manager') {
        if (!$knx_manager_has_city_scope($session, $city_id)) {
            return $knx_redirect($back_cities_url);
        }
    }

    /** =========================
     * 5) Nonce + API roots
     * ========================= */
    $nonce = wp_create_nonce('knx_edit_city_nonce');

    // City Info => v2 SEALED
    $api_city_get    = rest_url('knx/v2/cities/get-city');
    $api_city_update = rest_url('knx/v2/cities/update-city');

    // Rates => keep v1 for now (out of scope)
    $api_rates_get    = rest_url('knx/v1/get-city-details');
    $api_rates_update = rest_url('knx/v1/update-city-rates');

    /** Version for cache busting */
    $ver = defined('KNX_VERSION') ? KNX_VERSION : '1.0';

    /** Prefill (optional UX) */
    $prefill_name   = isset($city->name) ? (string)$city->name : '';
    $prefill_status = isset($city->status) ? (string)$city->status : 'active';
    $prefill_status = in_array($prefill_status, ['active', 'inactive'], true) ? $prefill_status : 'active';

    ob_start();
    ?>
    <div class="knx-edit-city-signed" data-module="knx-edit-city">

        <!-- Load module styles -->
        <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/hubs/hubs-style.css?ver=' . rawurlencode($ver)); ?>">
        <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/cities/edit-city-style.css?ver=' . rawurlencode($ver)); ?>">

        <!-- Load sidebar assets WITHOUT wp_footer -->
        <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/sidebar/sidebar-style.css?ver=' . rawurlencode($ver)); ?>">

        <!-- Hide KNX top navbar only (do not touch theme header) -->
        <style>
            .knx-edit-city-signed #knxTopNavbar,
            .knx-edit-city-signed .knx-top-navbar,
            .knx-edit-city-signed .knx-navbar {
                display: none !important;
            }
        </style>

        <div class="knx-edit-city-shell" style="display:flex;gap:18px;align-items:stretch;">

            <!-- =========================
                 SIDEBAR (inline)
                 ========================= -->
            <aside class="knx-sidebar" id="knxSidebar">
                <div class="knx-sidebar-header">
                    <button id="knxExpandMobile" class="knx-expand-btn" aria-label="Toggle Sidebar">
                        <i class="fas fa-angles-right"></i>
                    </button>
                    <a href="<?php echo esc_url(site_url('/dashboard')); ?>" class="knx-logo" title="Dashboard">
                        <i class="fas fa-home"></i>
                    </a>
                </div>

                <div class="knx-sidebar-scroll">
                    <ul class="knx-sidebar-menu">
                        <li><a href="<?php echo esc_url(site_url('/dashboard')); ?>"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/hubs')); ?>"><i class="fas fa-store"></i><span>Hubs</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/menus')); ?>"><i class="fas fa-utensils"></i><span>Menus</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/hub-categories')); ?>"><i class="fas fa-list"></i><span>Hub Categories</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/drivers')); ?>"><i class="fas fa-car"></i><span>Drivers</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/customers')); ?>"><i class="fas fa-users"></i><span>Customers</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/cities')); ?>"><i class="fas fa-city"></i><span>Cities</span></a></li>
                        <li><a href="<?php echo esc_url(site_url('/settings')); ?>"><i class="fas fa-cog"></i><span>Settings</span></a></li>
                    </ul>
                </div>
            </aside>

            <!-- =========================
                 MAIN CONTENT
                 ========================= -->
            <main class="knx-edit-city-main" style="flex:1;min-width:0;">

                <div class="knx-edit-city-container">

                    <div class="knx-edit-city-actionbar">
                        <a class="knx-btn" href="<?php echo esc_url($back_cities_url); ?>">
                            <i class="fas fa-arrow-left"></i> Back to Cities
                        </a>
                    </div>

                    <!-- =============================================
                         CITY INFO SECTION (v2)
                    ============================================= -->
                    <div class="knx-card knx-edit-city-wrapper"
                         data-api-get="<?php echo esc_url($api_city_get); ?>"
                         data-api-update="<?php echo esc_url($api_city_update); ?>"
                         data-city-id="<?php echo esc_attr($city_id); ?>"
                         data-nonce="<?php echo esc_attr($nonce); ?>">

                        <div class="knx-edit-header">
                            <i class="fas fa-city" style="font-size:22px;color:#0B793A;"></i>
                            <h1>Edit City</h1>
                        </div>

                        <div class="knx-form-group">
                            <label>City Name</label>
                            <input type="text" id="cityName" placeholder="City name" value="<?php echo esc_attr($prefill_name); ?>">
                        </div>

                        <div class="knx-form-group">
                            <label>Status</label>
                            <select id="cityStatus">
                                <option value="active" <?php selected($prefill_status, 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($prefill_status, 'inactive'); ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="knx-save-row">
                            <button id="saveCity" class="knx-btn">Save City</button>
                        </div>
                    </div>

                    <!-- =============================================
                         DELIVERY RATES SECTION (UNCHANGED - v1)
                    ============================================= -->
                    <div class="knx-card knx-edit-city-rates-wrapper"
                         data-api-get="<?php echo esc_url($api_rates_get); ?>"
                         data-api-update="<?php echo esc_url($api_rates_update); ?>"
                         data-city-id="<?php echo esc_attr($city_id); ?>"
                         data-nonce="<?php echo esc_attr($nonce); ?>">

                        <h2>Delivery Rates</h2>
                        <p style="color:#666;margin-bottom:10px;">Manage delivery pricing tiers for this city.</p>

                        <div id="knxRatesContainer" class="knx-rates-container"></div>

                        <div class="knx-rates-actions">
                            <button id="addRateBtn" class="knx-btn-secondary"><i class="fas fa-plus"></i> Add Rate</button>
                            <button id="saveRatesBtn" class="knx-btn"><i class="fas fa-save"></i> Save Rates</button>
                        </div>
                    </div>

                </div>
            </main>
        </div>

        <!-- Load JS modules (no enqueue) -->
        <script src="<?php echo esc_url(KNX_URL . 'inc/modules/sidebar/sidebar-script.js?ver=' . rawurlencode($ver)); ?>"></script>
        <script src="<?php echo esc_url(KNX_URL . 'inc/modules/cities/edit-city-script.js?ver=' . rawurlencode($ver)); ?>"></script>
        <script src="<?php echo esc_url(KNX_URL . 'inc/modules/cities/edit-city-rates.js?ver=' . rawurlencode($ver)); ?>"></script>
    </div>
    <?php

    return ob_get_clean();
});
