<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - API: Hub Categories (v1)
 * ----------------------------------------------------------
 * Minimal CRUD identical to Cities: Get / Add / Toggle
 * - Secure session + Nonce validation
 * - Prepared statements & sanitized inputs
 * ==========================================================
 */

add_action('rest_api_init', function () {

    register_rest_route('knx/v1', '/get-hub-categories', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_hub_categories',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/add-hub-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_add_hub_category',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('knx/v1', '/toggle-hub-category', [
        'methods'  => 'POST',
        'callback' => 'knx_api_toggle_hub_category',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Get all hub categories (ordered by sort_order ASC, then id DESC)
 */
function knx_api_get_hub_categories() {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_hub_categories';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $sql = "SELECT id, name, status, sort_order, created_at, updated_at FROM {$table} ORDER BY sort_order ASC, id DESC";
    $rows = $wpdb->get_results($sql);

    return new WP_REST_Response(['success' => true, 'categories' => $rows], 200);
}

/**
 * Add a new hub category
 */
function knx_api_add_hub_category(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_hub_categories';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $name  = sanitize_text_field($r['name']);
    $nonce = sanitize_text_field($r['knx_nonce']);

    if (!wp_verify_nonce($nonce, 'knx_add_hub_category_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    if (empty($name))
        return new WP_REST_Response(['success' => false, 'error' => 'missing_name'], 400);

    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE name = %s", $name));
    if ($exists)
        return new WP_REST_Response(['success' => false, 'error' => 'duplicate_category'], 409);

    // Determine next sort_order
    $next_sort = (int) $wpdb->get_var("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table}");

    $insert = $wpdb->insert($table, [
        'name'       => $name,
        'status'     => 'active',
        'sort_order' => $next_sort,
        'created_at' => current_time('mysql')
    ], ['%s', '%s', '%d', '%s']);

    return new WP_REST_Response([
        'success' => (bool) $insert,
        'message' => $insert ? '✅ Category added' : '❌ Database error'
    ], $insert ? 200 : 500);
}

/**
 * Toggle a hub category active/inactive
 */
function knx_api_toggle_hub_category(WP_REST_Request $r) {
    global $wpdb;

    $table = $wpdb->prefix . 'knx_hub_categories';

    $session = knx_get_session();
    if (!$session)
        return new WP_REST_Response(['success' => false, 'error' => 'unauthorized'], 403);

    $data   = json_decode($r->get_body(), true);
    $id     = intval($data['id'] ?? 0);
    $status = sanitize_text_field($data['status'] ?? 'active');
    $nonce  = sanitize_text_field($data['knx_nonce'] ?? '');

    if (!wp_verify_nonce($nonce, 'knx_toggle_hub_category_nonce'))
        return new WP_REST_Response(['success' => false, 'error' => 'invalid_nonce'], 403);

    if (!$id)
        return new WP_REST_Response(['success' => false, 'error' => 'missing_id'], 400);

    $wpdb->update($table, ['status' => $status], ['id' => $id], ['%s'], ['%d']);

    return new WP_REST_Response(['success' => true, 'message' => '⚙️ Category status updated'], 200);
}
