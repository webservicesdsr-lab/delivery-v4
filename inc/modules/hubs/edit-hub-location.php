<?php
// filepath: inc/modules/hubs/edit-hub-location.php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Edit Hub Location
 * Dual Maps: Google Maps (if key) or Leaflet (fallback)
 * Version: 3.0 - Polygon Support
 */

global $hub;
$maps_key = get_option('knx_google_maps_key', '');
?>

<div class="knx-edit-hub-wrapper"
  data-api-get="<?php echo esc_url(rest_url('knx/v1/get-hub')); ?>"
  data-api-location="<?php echo esc_url(rest_url('knx/v1/update-hub-location')); ?>"
  data-hub-id="<?php echo esc_attr($hub->id); ?>"
  data-nonce="<?php echo esc_attr(wp_create_nonce('knx_edit_hub')); ?>">

  <h2>📍 Edit Hub Location & Delivery Zone</h2>

  <!-- Address Input -->
  <div style="margin-bottom: 20px;">
    <label for="hubAddress" style="display:block; font-weight:600; margin-bottom:6px;">
      Address
    </label>
    <input type="text" id="hubAddress" placeholder="Enter address..."
      style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:15px;" />
  </div>

  <!-- Coordinates -->
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
    <div>
      <label for="hubLat" style="display:block; font-weight:600; margin-bottom:6px;">Latitude</label>
      <input type="text" id="hubLat" readonly
        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; background:#f9fafb;" />
    </div>
    <div>
      <label for="hubLng" style="display:block; font-weight:600; margin-bottom:6px;">Longitude</label>
      <input type="text" id="hubLng" readonly
        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; background:#f9fafb;" />
    </div>
  </div>

  <!-- Delivery Zone Type Selection -->
  <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e5e7eb;">
    <label style="display:block; font-weight:600; margin-bottom:12px; color:#111;">
      🚚 Delivery Zone Type
    </label>
    
    <div style="display: flex; gap: 24px; margin-bottom: 16px;">
      <!-- Radius Option -->
      <label style="display: flex; align-items: center; cursor: pointer;">
        <input type="radio" name="deliveryZoneType" value="radius" checked
          style="margin-right: 8px; cursor: pointer;" />
        <span style="font-weight: 500;">Radius (Legacy)</span>
      </label>
      
      <!-- Polygon Option -->
      <label style="display: flex; align-items: center; cursor: pointer;">
        <input type="radio" name="deliveryZoneType" value="polygon"
          style="margin-right: 8px; cursor: pointer;" />
        <span style="font-weight: 500;">Custom Polygon (Draw Area)</span>
      </label>
    </div>

    <!-- Radius Options (shown by default) -->
    <div id="radiusOptions" style="display: block;">
      <label for="deliveryRadius" style="display:block; font-weight:500; margin-bottom:6px; color:#374151;">
        Delivery Radius (miles)
      </label>
      <input type="number" id="deliveryRadius" step="0.1" min="0" value="5"
        style="width:150px; padding:8px; border:1px solid #ddd; border-radius:6px;" />
      <p style="margin:8px 0 0; font-size:13px; color:#6b7280;">
        🔵 Customers within this radius can order for delivery
      </p>
    </div>

    <!-- Polygon Options (hidden by default) -->
    <div id="polygonOptions" style="display: none;">
      <div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 12px;">
        <p style="margin: 0; font-size: 14px; color: #374151;">
          <strong>📐 How to draw:</strong> Click "Start Drawing", then click on the map to add points. 
          Need at least 3 points. Click "Complete Polygon" when done.
        </p>
      </div>

      <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px;">
        <button type="button" id="startDrawing" 
          style="padding: 10px 20px; background: #0b793a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
          🖊️ Start Drawing
        </button>
        <button type="button" id="completePolygon" disabled
          style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: not-allowed; font-weight: 500;">
          ✅ Complete Polygon
        </button>
        <button type="button" id="clearPolygon" disabled
          style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: not-allowed; font-weight: 500;">
          🗑️ Clear
        </button>
      </div>

      <div id="polygonStatus" style="padding: 10px; background: #f3f4f6; border-radius: 6px; font-size: 14px; color: #6b7280;">
        Click "Start Drawing" to begin
      </div>
    </div>
  </div>

  <!-- Map -->
  <div style="margin-bottom: 20px;">
    <label style="display:block; font-weight:600; margin-bottom:8px;">Map</label>
    <div id="map" style="width:100%; height:500px; border:1px solid #ddd; border-radius:8px;"></div>
  </div>

  <!-- Save Button -->
  <button type="button" id="saveLocation"
    style="padding:12px 24px; background:#0b793a; color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px;">
    💾 Save Location & Delivery Zone
  </button>

</div>

<style>
#startDrawing:not(:disabled) {
  background: #0b793a;
  cursor: pointer;
}
#startDrawing:not(:disabled):hover {
  background: #095a2a;
}

#completePolygon:not(:disabled) {
  background: #10b981;
  cursor: pointer;
}
#completePolygon:not(:disabled):hover {
  background: #059669;
}

#clearPolygon:not(:disabled) {
  background: #ef4444;
  cursor: pointer;
}
#clearPolygon:not(:disabled):hover {
  background: #dc2626;
}
</style>

<script>
  window.KNX_MAPS_KEY = <?php echo $maps_key ? '"' . esc_js($maps_key) . '"' : 'null'; ?>;
</script>

<?php
wp_enqueue_script(
  'knx-edit-hub-location',
  KNX_URL . 'inc/modules/hubs/edit-hub-location.js',
  [],
  KNX_VERSION,
  true
);
?>