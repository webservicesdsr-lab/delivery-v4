<?php
/**
 * Kingdom Nexus - Database Installation
 * Creates all required tables with proper structure
 */

if (!defined('ABSPATH')) exit;

/**
 * Main function to install/update all Kingdom Nexus database tables
 * Called during plugin activation
 */
function knx_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'knx_';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // ===== USERS TABLE =====
    $sql_users = "CREATE TABLE {$table_prefix}users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(191) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin','manager','menu_uploader','hub_management','driver','customer','user') DEFAULT 'user',
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY username (username),
        UNIQUE KEY email (email),
        KEY email_2 (email),
        KEY role (role),
        KEY status (status)
    ) $charset_collate;";

    // ===== SESSIONS TABLE =====
    $sql_sessions = "CREATE TABLE {$table_prefix}sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        token CHAR(64) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY token_2 (token),
        KEY expires_at (expires_at),
        KEY user_id (user_id),
        CONSTRAINT {$table_prefix}sessions_ibfk_1 
            FOREIGN KEY (user_id) 
            REFERENCES {$table_prefix}users (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== CITIES TABLE =====
    $sql_cities = "CREATE TABLE {$table_prefix}cities (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        state VARCHAR(100) DEFAULT NULL,
        country VARCHAR(100) DEFAULT 'USA',
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY name (name)
    ) $charset_collate;";

    // ===== HUB CATEGORIES TABLE =====
    $sql_hub_categories = "CREATE TABLE {$table_prefix}hub_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY sort_order (sort_order),
        KEY name (name)
    ) $charset_collate;";

    // ===== HUBS TABLE (CON DELIVERY_ZONE_TYPE) =====
    $sql_hubs = "CREATE TABLE {$table_prefix}hubs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        tagline VARCHAR(255) DEFAULT NULL,
        city_id BIGINT UNSIGNED DEFAULT NULL,
        category_id BIGINT UNSIGNED DEFAULT NULL,
        address TEXT DEFAULT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        delivery_radius DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Legacy: Delivery radius in miles (used when delivery_zone_type=radius)',
        delivery_zone_type ENUM('radius','polygon') DEFAULT 'radius' COMMENT 'Type of delivery zone: radius (legacy) or polygon (custom area)',
        delivery_available TINYINT(1) DEFAULT 1,
        pickup_available TINYINT(1) DEFAULT 1,
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(191) DEFAULT NULL,
        logo_url VARCHAR(500) DEFAULT NULL,
        hero_img VARCHAR(500) DEFAULT NULL,
        type ENUM('Restaurant','Food Truck','Cottage Food') DEFAULT 'Restaurant',
        rating DECIMAL(2,1) DEFAULT 4.5,
        cuisines TEXT DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        is_featured TINYINT(1) DEFAULT 0 COMMENT 'Show in Locals Love These section',
        slug VARCHAR(191) DEFAULT NULL COMMENT 'SEO-friendly URL slug',
        hours_monday VARCHAR(50) DEFAULT NULL,
        hours_tuesday VARCHAR(50) DEFAULT NULL,
        hours_wednesday VARCHAR(50) DEFAULT NULL,
        hours_thursday VARCHAR(50) DEFAULT NULL,
        hours_friday VARCHAR(50) DEFAULT NULL,
        hours_saturday VARCHAR(50) DEFAULT NULL,
        hours_sunday VARCHAR(50) DEFAULT NULL,
        closure_start DATE DEFAULT NULL,
        closure_end DATE DEFAULT NULL,
        closure_reason TEXT DEFAULT NULL,
        timezone VARCHAR(50) DEFAULT 'America/Chicago' COMMENT 'IANA timezone identifier',
        currency VARCHAR(3) DEFAULT 'USD' COMMENT 'ISO 4217 currency code',
        tax_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Tax percentage (e.g., 8.25 for 8.25%)',
        min_order DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum order amount in local currency',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY city_id (city_id),
        KEY category_id (category_id),
        KEY status (status),
        KEY name (name),
        KEY rating (rating),
        KEY is_featured (is_featured),
        KEY slug (slug),
        KEY delivery_zone_type (delivery_zone_type),
        CONSTRAINT {$table_prefix}hubs_ibfk_1 
            FOREIGN KEY (city_id) 
            REFERENCES {$table_prefix}cities (id) 
            ON DELETE SET NULL,
        CONSTRAINT {$table_prefix}hubs_ibfk_2 
            FOREIGN KEY (category_id) 
            REFERENCES {$table_prefix}hub_categories (id) 
            ON DELETE SET NULL
    ) $charset_collate;";

    // ===== DELIVERY ZONES TABLE (NUEVA) =====
    $sql_delivery_zones = "CREATE TABLE {$table_prefix}delivery_zones (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        hub_id BIGINT UNSIGNED NOT NULL,
        zone_name VARCHAR(100) DEFAULT 'Main Delivery Area',
        polygon_points JSON NOT NULL COMMENT 'Array of [lat, lng] coordinates',
        fill_color VARCHAR(7) DEFAULT '#0b793a' COMMENT 'Hex color for polygon fill',
        fill_opacity DECIMAL(3,2) DEFAULT 0.35 COMMENT 'Opacity 0.00-1.00',
        stroke_color VARCHAR(7) DEFAULT '#0b793a' COMMENT 'Hex color for polygon border',
        stroke_weight INT UNSIGNED DEFAULT 2 COMMENT 'Border width in pixels',
        is_active TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable this zone',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY hub_id (hub_id),
        KEY is_active (is_active),
        KEY hub_active (hub_id, is_active),
        CONSTRAINT {$table_prefix}delivery_zones_ibfk_1 
            FOREIGN KEY (hub_id) 
            REFERENCES {$table_prefix}hubs (id) 
            ON DELETE CASCADE
    ) $charset_collate COMMENT='Polygon-based delivery zones for hubs';";

    // ===== ITEMS CATEGORIES TABLE =====
    $sql_items_categories = "CREATE TABLE {$table_prefix}items_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        hub_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(191) NOT NULL,
        sort_order INT UNSIGNED DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY hub_id (hub_id),
        KEY hub_id_2 (hub_id, sort_order),
        KEY status (status),
        CONSTRAINT {$table_prefix}items_categories_ibfk_1 
            FOREIGN KEY (hub_id) 
            REFERENCES {$table_prefix}hubs (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== HUB ITEMS TABLE =====
    $sql_hub_items = "CREATE TABLE {$table_prefix}hub_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        hub_id BIGINT UNSIGNED NOT NULL,
        category_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(191) NOT NULL,
        description TEXT DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        image_url VARCHAR(500) DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY hub_id (hub_id),
        KEY category_id (category_id),
        KEY hub_id_2 (hub_id, category_id, sort_order),
        KEY status (status),
        CONSTRAINT {$table_prefix}hub_items_ibfk_1 
            FOREIGN KEY (hub_id) 
            REFERENCES {$table_prefix}hubs (id) 
            ON DELETE CASCADE,
        CONSTRAINT {$table_prefix}hub_items_ibfk_2 
            FOREIGN KEY (category_id) 
            REFERENCES {$table_prefix}items_categories (id) 
            ON DELETE SET NULL
    ) $charset_collate;";

    // ===== ITEM MODIFIERS TABLE =====
    $sql_item_modifiers = "CREATE TABLE {$table_prefix}item_modifiers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        item_id BIGINT UNSIGNED DEFAULT NULL,
        hub_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(191) NOT NULL,
        type VARCHAR(20) DEFAULT 'single',
        required TINYINT(1) DEFAULT 0,
        min_selection INT UNSIGNED DEFAULT 0,
        max_selection INT UNSIGNED DEFAULT NULL,
        is_global TINYINT(1) DEFAULT 0,
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY item_id (item_id),
        KEY hub_id (hub_id),
        KEY is_global (is_global),
        KEY item_id_2 (item_id, sort_order),
        KEY hub_id_2 (hub_id, is_global, sort_order),
        CONSTRAINT {$table_prefix}item_modifiers_ibfk_1 
            FOREIGN KEY (item_id) 
            REFERENCES {$table_prefix}hub_items (id) 
            ON DELETE CASCADE,
        CONSTRAINT {$table_prefix}item_modifiers_ibfk_2 
            FOREIGN KEY (hub_id) 
            REFERENCES {$table_prefix}hubs (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== MODIFIER OPTIONS TABLE =====
    $sql_modifier_options = "CREATE TABLE {$table_prefix}modifier_options (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        modifier_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(191) NOT NULL,
        price_adjustment DECIMAL(10,2) DEFAULT 0.00,
        is_default TINYINT(1) DEFAULT 0,
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY modifier_id (modifier_id),
        KEY modifier_id_2 (modifier_id, sort_order),
        CONSTRAINT {$table_prefix}modifier_options_ibfk_1 
            FOREIGN KEY (modifier_id) 
            REFERENCES {$table_prefix}item_modifiers (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== ITEM GLOBAL MODIFIERS TABLE =====
    $sql_item_global_modifiers = "CREATE TABLE {$table_prefix}item_global_modifiers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        item_id BIGINT UNSIGNED NOT NULL,
        global_modifier_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_item_global (item_id, global_modifier_id),
        KEY item_id (item_id),
        KEY global_modifier_id (global_modifier_id),
        CONSTRAINT {$table_prefix}item_global_modifiers_ibfk_1 
            FOREIGN KEY (item_id) 
            REFERENCES {$table_prefix}hub_items (id) 
            ON DELETE CASCADE,
        CONSTRAINT {$table_prefix}item_global_modifiers_ibfk_2 
            FOREIGN KEY (global_modifier_id) 
            REFERENCES {$table_prefix}item_modifiers (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== ADDON GROUPS TABLE =====
    $sql_addon_groups = "CREATE TABLE {$table_prefix}addon_groups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        hub_id BIGINT UNSIGNED NOT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY hub_id (hub_id),
        KEY hub_id_2 (hub_id, sort_order),
        KEY name (name),
        CONSTRAINT {$table_prefix}addon_groups_ibfk_1 
            FOREIGN KEY (hub_id) 
            REFERENCES {$table_prefix}hubs (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== ADDONS TABLE =====
    $sql_addons = "CREATE TABLE {$table_prefix}addons (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        group_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(191) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sort_order INT UNSIGNED DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY group_id (group_id),
        KEY group_id_2 (group_id, sort_order),
        KEY status (status),
        CONSTRAINT {$table_prefix}addons_ibfk_1 
            FOREIGN KEY (group_id) 
            REFERENCES {$table_prefix}addon_groups (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== ITEM ADDON GROUPS TABLE =====
    $sql_item_addon_groups = "CREATE TABLE {$table_prefix}item_addon_groups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        item_id BIGINT UNSIGNED NOT NULL,
        group_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_item_group (item_id, group_id),
        KEY item_id (item_id),
        KEY group_id (group_id),
        CONSTRAINT {$table_prefix}item_addon_groups_ibfk_1 
            FOREIGN KEY (item_id) 
            REFERENCES {$table_prefix}hub_items (id) 
            ON DELETE CASCADE,
        CONSTRAINT {$table_prefix}item_addon_groups_ibfk_2 
            FOREIGN KEY (group_id) 
            REFERENCES {$table_prefix}addon_groups (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== DELIVERY RATES TABLE =====
    $sql_delivery_rates = "CREATE TABLE {$table_prefix}delivery_rates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        city_id BIGINT UNSIGNED NOT NULL,
        zone_name VARCHAR(100) DEFAULT NULL,
        base_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        per_mile_rate DECIMAL(10,2) DEFAULT 0.00,
        min_order DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY city_id (city_id),
        KEY status (status),
        CONSTRAINT {$table_prefix}delivery_rates_ibfk_1 
            FOREIGN KEY (city_id) 
            REFERENCES {$table_prefix}cities (id) 
            ON DELETE CASCADE
    ) $charset_collate;";

    // ===== SETTINGS TABLE =====
    $sql_settings = "CREATE TABLE {$table_prefix}settings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(100) NOT NULL,
        setting_value LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key),
        KEY setting_key_2 (setting_key)
    ) $charset_collate;";

    // Execute all CREATE TABLE statements
    dbDelta($sql_users);
    dbDelta($sql_sessions);
    dbDelta($sql_cities);
    dbDelta($sql_hub_categories);
    dbDelta($sql_hubs);
    dbDelta($sql_delivery_zones); // ← NUEVA TABLA
    dbDelta($sql_items_categories);
    dbDelta($sql_hub_items);
    dbDelta($sql_item_modifiers);
    dbDelta($sql_modifier_options);
    dbDelta($sql_item_global_modifiers);
    dbDelta($sql_addon_groups);
    dbDelta($sql_addons);
    dbDelta($sql_item_addon_groups);
    dbDelta($sql_delivery_rates);
    dbDelta($sql_settings);

    // Store database version
    update_option('knx_db_version', '2.0.0');
    
    // Log success
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Kingdom Nexus: Database v2.0.0 installed successfully with polygon delivery zones support');
    }
}