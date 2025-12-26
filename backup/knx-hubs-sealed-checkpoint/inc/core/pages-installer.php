<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Pages Installer (v1.1)
 *
 * Automatically creates required WordPress pages with correct shortcodes.
 *
 * Pages created:
 * - /login             → [knx_auth]
 * - /home              → [knx_home]
 * - /dashboard         → [knx_home]
 * - /hubs              → [knx_hubs]
 * - /edit-hub          → [knx_edit_hub]
 * - /cities            → [knx_cities]
 * - /edit-city         → [knx_edit_city]
 * - /edit-hub-items    → [knx_edit_hub_items]
 * - /edit-item-categories → [knx_edit_item_categories]
 * - /edit-item         → [knx_edit_item]
 * - /menu              → [knx_menu]          (Elementor Canvas friendly)
 * - /explore-hubs      → [olc_explore_hubs]  (Elementor Canvas friendly)
 */

/**
 * Creates all required pages for Kingdom Nexus.
 * Skips pages that already exist.
 */
function knx_install_pages() {

    // If Elementor is present, we prefer its blank canvas template
    $elementor_canvas = defined('ELEMENTOR_VERSION') ? 'elementor_canvas' : '';

    $pages = [
        // Authentication
        [
            'title'     => 'Login',
            'slug'      => 'login',
            'shortcode' => '[knx_auth]',
            'template'  => ''
        ],

        // Dashboard/Home
        [
            'title'     => 'Home',
            'slug'      => 'home',
            'shortcode' => '[knx_home]',
            'template'  => ''
        ],
        [
            'title'     => 'Dashboard',
            'slug'      => 'dashboard',
            'shortcode' => '[knx_home]',
            'template'  => ''
        ],

        // Hubs Management
        [
            'title'     => 'Hubs',
            'slug'      => 'hubs',
            'shortcode' => '[knx_hubs]',
            'template'  => ''
        ],
        [
            'title'     => 'Edit Hub',
            'slug'      => 'edit-hub',
            'shortcode' => '[knx_edit_hub]',
            'template'  => ''
        ],

        // Cities Management
        [
            'title'     => 'Cities',
            'slug'      => 'cities',
            'shortcode' => '[knx_cities]',
            'template'  => ''
        ],
        [
            'title'     => 'Edit City',
            'slug'      => 'edit-city',
            'shortcode' => '[knx_edit_city]',
            'template'  => ''
        ],

        // Menu/Items Management
        [
            'title'     => 'Edit Hub Items',
            'slug'      => 'edit-hub-items',
            'shortcode' => '[knx_edit_hub_items]',
            'template'  => ''
        ],
        [
            'title'     => 'Edit Item Categories',
            'slug'      => 'edit-item-categories',
            'shortcode' => '[knx_edit_item_categories]',
            'template'  => ''
        ],
        [
            'title'     => 'Edit Item',
            'slug'      => 'edit-item',
            'shortcode' => '[knx_edit_item]',
            'template'  => ''
        ],

        // Public: Menu page (Elementor-friendly)
        [
            'title'     => 'Menu',
            'slug'      => 'menu',
            'shortcode' => '[knx_menu]',
            // Use Elementor Canvas when available for a clean, header/footer-free layout
            'template'  => $elementor_canvas,
        ],

        // Public: Explore Hubs (optional, but handy to control with Elementor)
        [
            'title'     => 'Explore Hubs',
            'slug'      => 'explore-hubs',
            'shortcode' => '[olc_explore_hubs]',
            'template'  => $elementor_canvas,
        ],
    ];

    $created = [];
    $skipped = [];

    foreach ($pages as $page) {

        // Check if page already exists by slug
        $existing = get_page_by_path($page['slug']);

        if ($existing) {
            $skipped[] = $page['slug'];
            continue;
        }

        // Create page
        $page_id = wp_insert_post([
            'post_title'     => $page['title'],
            'post_name'      => $page['slug'],
            'post_content'   => $page['shortcode'],
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'post_author'    => 1
        ]);

        if ($page_id && !is_wp_error($page_id)) {

            // Set custom template if specified
            if (!empty($page['template'])) {
                update_post_meta($page_id, '_wp_page_template', $page['template']);
            }

            $created[] = $page['slug'];

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Kingdom Nexus: Created page /{$page['slug']} (ID: {$page_id})");
            }
        }
    }

    // Flush rewrite rules to ensure permalinks work
    flush_rewrite_rules();

    return [
        'created' => $created,
        'skipped' => $skipped,
        'total'   => count($pages)
    ];
}

/**
 * Verifies all required pages exist.
 * Returns array with status and missing pages.
 */
function knx_verify_pages() {

    $required_slugs = [
        'login',
        'home',
        'dashboard',
        'hubs',
        'edit-hub',
        'cities',
        'edit-city',
        'edit-hub-items',
        'edit-item-categories',
        'edit-item',
        'menu',
        'explore-hubs',
    ];

    $missing = [];

    foreach ($required_slugs as $slug) {
        if (!get_page_by_path($slug)) {
            $missing[] = $slug;
        }
    }

    return [
        'complete' => empty($missing),
        'missing'  => $missing,
        'total'    => count($required_slugs),
        'found'    => count($required_slugs) - count($missing)
    ];
}

/**
 * Repair missing pages by re-running installation.
 */
function knx_repair_pages() {
    $result = knx_install_pages();
    $verify = knx_verify_pages();

    return array_merge($result, $verify);
}
