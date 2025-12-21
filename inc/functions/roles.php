<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Role Definitions (v1)
 *
 * Canonical role hierarchy and metadata.
 * Single source of truth for authorization across all modules.
 */

/**
 * Get the canonical role hierarchy.
 * Higher numbers = higher privileges.
 * 
 * @return array Role name => level mapping
 */
function knx_get_role_hierarchy() {
    return [
        'customer'       => 1,
        'driver'         => 2,
        'menu_uploader'  => 3,
        'hub_management' => 4,
        'manager'        => 5,
        'super_admin'    => 6
    ];
}

/**
 * Get the privilege level for a specific role.
 * 
 * @param string $role Role name
 * @return int Role level (0 if role doesn't exist)
 */
function knx_get_role_level($role) {
    $hierarchy = knx_get_role_hierarchy();
    return $hierarchy[$role] ?? 0;
}
