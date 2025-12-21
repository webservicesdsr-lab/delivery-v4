<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - API: Update Hub Closure (v1.0)
 * ----------------------------------------------------------
 * Updates hub temporary closure settings (type, reason, reopen)
 */

add_action('rest_api_init', function() {
  register_rest_route('knx/v1', '/update-closure', [
    'methods' => 'POST',
    'callback' => 'knx_update_closure',
    'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management']),
  ]);
});

function knx_update_closure(WP_REST_Request $r) {
  global $wpdb;

  /** Dynamic table name with current WP prefix */
  $table = $wpdb->prefix . 'knx_hubs';

  /** Verify nonce */
  $hub_id = intval($r['hub_id']);
  $nonce  = sanitize_text_field($r['knx_nonce']);
  if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
    return ['success' => false, 'error' => 'invalid_nonce'];
  }

  $is_closed = intval($r['is_closed']);
  $closure_type = sanitize_text_field($r['closure_type']);
  $closure_reason = sanitize_textarea_field($r['closure_reason']);
  $reopen_date = $r['reopen_date'] ? date('Y-m-d', strtotime($r['reopen_date'])) : null;
  $reopen_time = isset($r['reopen_time']) ? sanitize_text_field($r['reopen_time']) : null;

  /** Map to actual table columns */
  $update_data = [];
  
  if ($is_closed) {
    // Hub is being closed
    $update_data['closure_start'] = current_time('mysql', false);
    $update_data['closure_reason'] = $closure_reason;
    // Generate ISO datetime for closure_until if temporary
    if ($closure_type === 'temporary' && $reopen_date && $reopen_time) {
      // Combine date and time, assume local time
      $iso_datetime = date('Y-m-d\TH:i:s', strtotime($reopen_date . ' ' . $reopen_time));
      $update_data['closure_until'] = $iso_datetime;
    } else {
      $update_data['closure_until'] = null;
    }
  } else {
    // Hub is being reopened - clear all closure fields
    $update_data['closure_start'] = null;
    $update_data['closure_reason'] = null;
    $update_data['closure_until'] = null;
  }

  $result = $wpdb->update(
    $table, 
    $update_data, 
    ['id' => $hub_id],
    array_fill(0, count($update_data), '%s'),
    ['%d']
  );

  if ($result === false) {
    return ['success' => false, 'error' => 'db_error', 'details' => $wpdb->last_error];
  } elseif ($result === 0) {
    return ['success' => false, 'error' => 'no_update', 'message' => 'No changes saved.'];
  }

  return ['success' => true, 'message' => 'Closure settings updated'];
}
