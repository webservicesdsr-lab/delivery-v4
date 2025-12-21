<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Edit City Template (v3.0 Production)
 * ----------------------------------------------------------
 * Shortcode: [knx_edit_city]
 * ✅ Edit City Name + Status
 * ✅ Manage Delivery Rates
 * ✅ REST Integrated with:
 *     /knx/v1/get-city
 *     /knx/v1/update-city
 *     /knx/v1/get-city-details
 *     /knx/v1/update-city-rates
 * ==========================================================
 */

add_shortcode('knx_edit_city', function() {
    knx_set_admin_context();
    
    global $wpdb;

    /** Validate session */
    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager'])) {
        return '<div class="knx-warning">⚠️ Unauthorized access.</div>';
    }

    /** Get city ID */
    $city_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$city_id) {
        return '<div class="knx-warning">⚠️ Invalid or missing City ID.</div>';
    }

    /** Nonce + REST root */
    $nonce = wp_create_nonce('knx_edit_city_nonce');

    /** Global CSS */
    echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/hubs/hubs-style.css') . '">';
    echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/cities/edit-city-style.css') . '">';

    /** Back URL */
    $back_cities_url = esc_url( site_url('/cities') );

    ob_start(); ?>

    <!-- Page-only override: hide global top navbar on this page -->
    <style id="knx-hide-topbar-edit-city">
      #knxTopNavbar,
      .knx-top-navbar,
      .knx-navbar,
      .site-header { display: none !important; }
      /* Ensure scroll isn't locked if mobile menu was opened before navigating here */
      #knxMenuOverlay { display: none !important; }
      body { overflow: auto !important; }
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', function(){
        var overlay = document.getElementById('knxMenuOverlay');
        if (overlay) {
          overlay.classList.remove('active');
          overlay.style.display = 'none';
        }
        if (document.body) document.body.style.overflow = '';
      });
    </script>

    <div class="knx-edit-city-container">

      <!-- Back to Cities (único botón solicitado) -->
      <div class="knx-edit-city-actionbar">
        <a class="knx-btn" href="<?php echo $back_cities_url; ?>">
          <i class="fas fa-arrow-left"></i> Back to Cities
        </a>
      </div>

      <!-- =============================================
           CITY INFO SECTION
      ============================================= -->
      <div class="knx-card knx-edit-city-wrapper"
           data-api-get="<?php echo esc_url(rest_url('knx/v1/get-city')); ?>"
           data-api-update="<?php echo esc_url(rest_url('knx/v1/update-city')); ?>"
           data-city-id="<?php echo esc_attr($city_id); ?>"
           data-nonce="<?php echo esc_attr($nonce); ?>">

        <div class="knx-edit-header">
          <i class="fas fa-city" style="font-size:22px;color:#0B793A;"></i>
          <h1>Edit City</h1>
        </div>

        <div class="knx-form-group">
          <label>City Name</label>
          <input type="text" id="cityName" placeholder="City name">
        </div>

        <div class="knx-form-group">
          <label>Status</label>
          <select id="cityStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="knx-save-row">
          <button id="saveCity" class="knx-btn">Save City</button>
        </div>
      </div>

      <!-- =============================================
           DELIVERY RATES SECTION
      ============================================= -->
      <div class="knx-card knx-edit-city-rates-wrapper"
           data-api-get="<?php echo esc_url(rest_url('knx/v1/get-city-details')); ?>"
           data-api-update="<?php echo esc_url(rest_url('knx/v1/update-city-rates')); ?>"
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

    <!-- =============================================
         LOAD JS MODULES
    ============================================= -->
    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/cities/edit-city-script.js'); ?>"></script>
    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/cities/edit-city-rates.js'); ?>"></script>

    <?php
    return ob_get_clean();
});
