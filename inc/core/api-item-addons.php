<?php
/**
 * Kingdom Nexus - API: Item Addons (v1.0)
 * =========================================
 * Vincular addon groups a items específicos
 * 
 * Endpoints:
 * - GET  /knx/v1/get-item-addon-groups
 * - POST /knx/v1/assign-addon-group-to-item
 * - POST /knx/v1/remove-addon-group-from-item
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    // GET grupos asignados a un item
    register_rest_route('knx/v1', '/get-item-addon-groups', [
        'methods'  => 'GET',
        'callback' => 'knx_api_get_item_addon_groups',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);

    // POST asignar grupo a item
    register_rest_route('knx/v1', '/assign-addon-group-to-item', [
        'methods'  => 'POST',
        'callback' => 'knx_api_assign_addon_group_to_item',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);

    // POST remover grupo de item
    register_rest_route('knx/v1', '/remove-addon-group-from-item', [
        'methods'  => 'POST',
        'callback' => 'knx_api_remove_addon_group_from_item',
        'permission_callback' => knx_permission_callback(['super_admin', 'manager', 'hub_management', 'menu_uploader']),
    ]);
});

/**
 * GET grupos asignados a un item (con addons incluidos)
 */
function knx_api_get_item_addon_groups(WP_REST_Request $r) {
    global $wpdb;
    $rel_table = $wpdb->prefix . 'knx_item_addon_groups';
    $groups_table = $wpdb->prefix . 'knx_addon_groups';
    $addons_table = $wpdb->prefix . 'knx_addons';

    $item_id = intval($r->get_param('item_id'));
    if (!$item_id) {
        return knx_json_response(false, ['error' => 'missing_item_id'], 400);
    }

    // Get assigned addon groups (correct column: group_id)
    $groups = $wpdb->get_results($wpdb->prepare("
        SELECT g.*
        FROM {$groups_table} g
        INNER JOIN {$rel_table} r ON r.group_id = g.id
        WHERE r.item_id = %d
        ORDER BY g.sort_order ASC, g.name ASC
    ", $item_id));

    // Get addons for each group
    foreach ($groups as $group) {
        $group->addons = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$addons_table}
            WHERE group_id = %d AND status = 'active'
            ORDER BY sort_order ASC, name ASC
        ", $group->id));
    }

    return knx_json_response(true, ['groups' => $groups ?: []]);
}

/**
 * POST asignar addon group a item
 */
function knx_api_assign_addon_group_to_item(WP_REST_Request $r) {
    global $wpdb;
    $table = $wpdb->prefix . 'knx_item_addon_groups';

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager', 'hub_management', 'menu_uploader'])) {
        return knx_json_response(false, ['error' => 'unauthorized'], 403);
    }

    $nonce = sanitize_text_field($r->get_param('knx_nonce'));
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $item_id = intval($r->get_param('item_id'));
    $group_id = intval($r->get_param('group_id'));

    if (!$item_id || !$group_id) {
        return knx_json_response(false, ['error' => 'missing_fields'], 400);
    }

    // Check si ya existe
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$table}
        WHERE item_id = %d AND group_id = %d
    ", $item_id, $group_id));

    if ($exists) {
        return knx_json_response(false, ['error' => 'already_assigned'], 400);
    }

    // Insert
    $wpdb->insert($table, [
        'item_id' => $item_id,
        'group_id' => $group_id,
    ], ['%d', '%d']);

    return knx_json_response(true, ['message' => 'Addon group assigned']);
}

/**
 * POST remover addon group de item
 */
function knx_api_remove_addon_group_from_item(WP_REST_Request $r) {
    global $wpdb;
    $table = $wpdb->prefix . 'knx_item_addon_groups';

    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['super_admin', 'manager', 'hub_management', 'menu_uploader'])) {
        return knx_json_response(false, ['error' => 'unauthorized'], 403);
    }

    $nonce = sanitize_text_field($r->get_param('knx_nonce'));
    if (!wp_verify_nonce($nonce, 'knx_edit_hub_nonce')) {
        return knx_json_response(false, ['error' => 'invalid_nonce'], 403);
    }

    $item_id = intval($r->get_param('item_id'));
    $group_id = intval($r->get_param('group_id'));

    if (!$item_id || !$group_id) {
        return knx_json_response(false, ['error' => 'missing_fields'], 400);
    }

    $wpdb->delete($table, [
        'item_id' => $item_id,
        'group_id' => $group_id,
    ], ['%d', '%d']);

    return knx_json_response(true, ['message' => 'Addon group removed']);
}
