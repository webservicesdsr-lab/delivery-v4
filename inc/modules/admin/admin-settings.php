<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Admin Settings (v3.0)
 * -------------------------------------
 * Visual panel to manage global API keys and system configurations.
 * Fully connected to REST endpoints and knx_settings table.
 */

// Get saved key (from DB)
global $wpdb;
$table = $wpdb->prefix . 'knx_settings';
$google_key = '';
if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
    $google_key = $wpdb->get_var("SELECT google_maps_api FROM $table ORDER BY id DESC LIMIT 1");
}
?>

<div class="knx-admin-wrap">
  <div class="knx-admin-header">
      <h1><i class="dashicons dashicons-admin-generic"></i> Nexus Settings</h1>
      <p class="subtitle">Configure your API keys and system-wide integrations.</p>
  </div>

  <div class="knx-card">
      <h2><i class="dashicons dashicons-location-alt"></i> Google Maps API</h2>
      <p style="margin-top:-4px;color:#555;">Your Google Maps API key enables autocompletion and maps in Hub Location editor.</p>

      <form id="knxSettingsForm" style="margin-top:15px;">
          <input type="hidden" id="knxApiUrl" value="<?php echo esc_url(rest_url('knx/v1/update-settings')); ?>">
          <input type="hidden" id="knxNonce" value="<?php echo wp_create_nonce('wp_rest'); ?>">

          <div class="knx-input">
              <label for="google_maps_key"><strong>Google Maps API Key</strong></label><br>
              <input type="text" id="google_maps_key" name="google_maps_key"
                     value="<?php echo esc_attr($google_key); ?>"
                     placeholder="Enter your Google Maps API Key"
                     style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;">
          </div>

          <p>
              <button type="submit" class="button button-primary">
                  <i class="dashicons dashicons-yes-alt"></i> Save Settings
              </button>
          </p>
      </form>
  </div>

  <div id="mapCard" class="knx-card" style="display:none;margin-top:20px;">
      <h2><i class="dashicons dashicons-location"></i> Map Preview</h2>
      <div id="knxMapPreview" style="width:100%;height:300px;border-radius:8px;"></div>
  </div>

  <div id="knxToast" style="display:none;"></div>

  <div class="knx-card" style="margin-top:20px;">
      <h2><i class="dashicons dashicons-info"></i> Notes</h2>
      <p style="font-size:14px;line-height:1.5;color:#444;">
        - If no API key is configured, location-based features will show a warning.<br>
        - You can replace or remove your key anytime for security reasons.<br>
        - The map preview below confirms your key is valid.
      </p>
  </div>
</div>

<script src="<?php echo esc_url(KNX_URL . 'inc/modules/admin/admin-settings.js'); ?>"></script>
