-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 24-12-2025 a las 14:41:26
-- Versión del servidor: 8.0.43-34
-- Versión de PHP: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `oywwofte_WP2FN`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_addons`
--

CREATE TABLE `fyN_knx_addons` (
  `id` bigint UNSIGNED NOT NULL,
  `group_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sort_order` int UNSIGNED DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_addon_groups`
--

CREATE TABLE `fyN_knx_addon_groups` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `description` text COLLATE utf8mb4_unicode_520_ci,
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_carts`
--

CREATE TABLE `fyN_knx_carts` (
  `id` bigint UNSIGNED NOT NULL,
  `session_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','abandoned','converted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fyN_knx_carts`
--

INSERT INTO `fyN_knx_carts` (`id`, `session_token`, `customer_id`, `hub_id`, `subtotal`, `status`, `created_at`, `updated_at`) VALUES
(1, 'knx_ccb8f434212c7819af62f2251', NULL, 1, 54.24, 'abandoned', '2025-12-07 00:21:15', '2025-12-08 09:24:06'),
(2, 'knx_ccb8f434212c7819af62f2251', 1, 1, 18.08, 'abandoned', '2025-12-08 16:55:27', '2025-12-21 02:51:25'),
(3, 'knx_7e675ec516bb0819b13bf6d54', NULL, 1, 18.08, 'abandoned', '2025-12-12 18:07:51', '2025-12-21 02:51:25'),
(4, 'knx_da7345fe1331a19b15ca8a84', 2, 1, 18.08, 'abandoned', '2025-12-13 03:39:16', '2025-12-21 02:51:25'),
(5, 'knx_1d8e08266f930819b41eb534d', NULL, 1, 13.59, 'abandoned', '2025-12-21 17:18:20', '2025-12-22 05:58:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_cart_items`
--

CREATE TABLE `fyN_knx_cart_items` (
  `id` bigint UNSIGNED NOT NULL,
  `cart_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_snapshot` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `modifiers_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fyN_knx_cart_items`
--

INSERT INTO `fyN_knx_cart_items` (`id`, `cart_id`, `item_id`, `name_snapshot`, `image_snapshot`, `quantity`, `unit_price`, `line_total`, `modifiers_json`, `created_at`) VALUES
(9, 1, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 2, 18.08, 36.16, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 2, \"name\": \"Large\", \"price_adjustment\": 4.49}], \"required\": true}]', '2025-12-07 16:23:59'),
(10, 1, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 1, 18.08, 18.08, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 2, \"name\": \"Large\", \"price_adjustment\": 4.49}], \"required\": true}]', '2025-12-07 16:23:59'),
(14, 2, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 1, 18.08, 18.08, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 2, \"name\": \"Large\", \"price_adjustment\": 4.49}], \"required\": true}]', '2025-12-09 23:16:07'),
(15, 3, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 1, 18.08, 18.08, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 2, \"name\": \"Large\", \"price_adjustment\": 4.49}], \"required\": true}]', '2025-12-12 18:07:51'),
(18, 4, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 1, 18.08, 18.08, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 2, \"name\": \"Large\", \"price_adjustment\": 4.49}], \"required\": true}]', '2025-12-15 00:35:02'),
(19, 5, 1, 'Plain Alfredo Pasta', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 1, 13.59, 13.59, '[{\"id\": 1, \"name\": \"Size\", \"type\": \"single\", \"options\": [{\"id\": 1, \"name\": \"Small\", \"price_adjustment\": 0}], \"required\": true}]', '2025-12-21 17:18:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_cities`
--

CREATE TABLE `fyN_knx_cities` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT 'USA',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `is_operational` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_cities`
--

INSERT INTO `fyN_knx_cities` (`id`, `name`, `state`, `country`, `status`, `is_operational`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Kankakee County, IL', '', 'USA', 'active', 1, '2025-11-21 03:58:51', '2025-12-24 23:28:23', NULL),
(2, 'Collin County, TX', '', 'USA', 'active', 1, '2025-12-13 22:38:09', '2025-12-23 20:32:52', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_delivery_rates`
--

CREATE TABLE `fyN_knx_delivery_rates` (
  `id` bigint UNSIGNED NOT NULL,
  `city_id` bigint UNSIGNED NOT NULL,
  `flat_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rate_per_distance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `distance_unit` enum('mile','kilometer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'mile',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_delivery_rates`
--

INSERT INTO `fyN_knx_delivery_rates` (`id`, `city_id`, `flat_rate`, `rate_per_distance`, `distance_unit`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 4.25, 0.80, 'mile', 'active', '2025-12-24 20:27:29', NULL),
(2, 2, 0.00, 0.00, 'mile', 'active', '2025-12-24 20:27:29', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_delivery_zones`
--

CREATE TABLE `fyN_knx_delivery_zones` (
  `id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `zone_name` varchar(100) COLLATE utf8mb4_unicode_520_ci DEFAULT 'Main Delivery Area',
  `polygon_points` json NOT NULL COMMENT 'Array of [lat, lng] coordinates',
  `fill_color` varchar(7) COLLATE utf8mb4_unicode_520_ci DEFAULT '#0b793a' COMMENT 'Hex color for polygon fill',
  `fill_opacity` decimal(3,2) DEFAULT '0.35' COMMENT 'Opacity 0.00-1.00',
  `stroke_color` varchar(7) COLLATE utf8mb4_unicode_520_ci DEFAULT '#0b793a' COMMENT 'Hex color for polygon border',
  `stroke_weight` int UNSIGNED DEFAULT '2' COMMENT 'Border width in pixels',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Enable/disable this zone',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Polygon-based delivery zones for hubs';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_hubs`
--

CREATE TABLE `fyN_knx_hubs` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL COMMENT 'SEO-friendly URL slug',
  `tagline` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `city_id` bigint UNSIGNED DEFAULT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_520_ci,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `delivery_radius` decimal(5,2) DEFAULT '5.00' COMMENT 'Legacy: Delivery radius in miles (used when delivery_zone_type=radius)',
  `delivery_zone_type` enum('radius','polygon') COLLATE utf8mb4_unicode_520_ci DEFAULT 'radius' COMMENT 'Type of delivery zone: radius (legacy) or polygon (custom area)',
  `delivery_available` tinyint(1) DEFAULT '1',
  `pickup_available` tinyint(1) DEFAULT '1',
  `phone` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hero_img` varchar(500) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `type` enum('Restaurant','Food Truck','Cottage Food') COLLATE utf8mb4_unicode_520_ci DEFAULT 'Restaurant',
  `rating` decimal(2,1) DEFAULT '4.5',
  `cuisines` text COLLATE utf8mb4_unicode_520_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `hours_monday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_tuesday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_wednesday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_thursday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_friday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_saturday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `hours_sunday` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `closure_start` date DEFAULT NULL,
  `closure_until` datetime DEFAULT NULL,
  `closure_reason` text COLLATE utf8mb4_unicode_520_ci,
  `timezone` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT 'America/Chicago' COMMENT 'IANA timezone identifier',
  `currency` varchar(3) COLLATE utf8mb4_unicode_520_ci DEFAULT 'USD' COMMENT 'ISO 4217 currency code',
  `tax_rate` decimal(5,2) DEFAULT '0.00' COMMENT 'Tax percentage (e.g., 8.25 for 8.25%)',
  `min_order` decimal(10,2) DEFAULT '0.00' COMMENT 'Minimum order amount in local currency',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `is_featured` tinyint(1) DEFAULT '0' COMMENT 'Show in Locals Love These section'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_hubs`
--

INSERT INTO `fyN_knx_hubs` (`id`, `name`, `slug`, `tagline`, `city_id`, `category_id`, `address`, `latitude`, `longitude`, `delivery_radius`, `delivery_zone_type`, `delivery_available`, `pickup_available`, `phone`, `email`, `logo_url`, `hero_img`, `type`, `rating`, `cuisines`, `status`, `hours_monday`, `hours_tuesday`, `hours_wednesday`, `hours_thursday`, `hours_friday`, `hours_saturday`, `hours_sunday`, `closure_start`, `closure_until`, `closure_reason`, `timezone`, `currency`, `tax_rate`, `min_order`, `created_at`, `updated_at`, `is_featured`) VALUES
(1, 'Chef Vaughn\'s Kitchen', 'chefvaughnskitchen', NULL, 1, 1, '670 West Station Street, IL United States, Illinois, 60964', 41.0248430, -87.7225890, 5.00, 'radius', 1, 1, '+1 815-386-3652', 'chefvaughnskitchen_nexus@outlook.com', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-uploads/1/20251220-153125-7dd96.jpg', NULL, 'Restaurant', 4.5, NULL, 'active', '[{\"open\":\"00:00\",\"close\":\"23:45\"}]', '[{\"open\":\"00:00\",\"close\":\"23:00\"}]', '[{\"open\":\"00:00\",\"close\":\"23:00\"}]', '[{\"open\":\"00:00\",\"close\":\"23:45\"}]', '[{\"open\":\"00:00\",\"close\":\"23:45\"}]', '[{\"open\":\"00:00\",\"close\":\"23:45\"}]', '[{\"open\":\"00:00\",\"close\":\"23:45\"}]', NULL, NULL, NULL, 'America/Chicago', 'USD', 20.00, 0.00, '2025-11-21 04:17:03', '2025-12-20 15:31:25', 1),
(2, 'Burgers & Beer', 'burgers-beer', NULL, 1, 2, '756 W Jeffery St, Kankakee, Illinois, Estados Unidos', 41.1200000, -87.8600000, 5.00, 'radius', 1, 1, '+1 815-523-7144', 'burgersnbeers_nexus@outlook.com', 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-uploads/2/20251121-183352-d8736.jpg', NULL, 'Restaurant', 4.5, NULL, 'active', '[{\"open\":\"11:00\",\"close\":\"20:30\"}]', '[{\"open\":\"11:00\",\"close\":\"20:30\"}]', '[{\"open\":\"11:00\",\"close\":\"20:30\"}]', '[{\"open\":\"11:00\",\"close\":\"20:30\"}]', '[{\"open\":\"11:00\",\"close\":\"20:30\"}]', '', '', '2025-11-25', '2025-11-25 22:45:00', 'Spot Remodeling', 'America/Chicago', 'USD', 20.00, 0.00, '2025-11-22 00:23:07', '2025-12-24 20:32:24', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_hub_categories`
--

CREATE TABLE `fyN_knx_hub_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_hub_categories`
--

INSERT INTO `fyN_knx_hub_categories` (`id`, `name`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Soul Food', 'active', 1, '2025-11-21 03:59:20', NULL),
(2, 'Burgers', 'active', 2, '2025-11-22 00:30:33', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_hub_items`
--

CREATE TABLE `fyN_knx_hub_items` (
  `id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_520_ci,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `image_url` varchar(500) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_hub_items`
--

INSERT INTO `fyN_knx_hub_items` (`id`, `hub_id`, `category_id`, `name`, `description`, `price`, `image_url`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Plain Alfredo Pasta', 'Fettuccine pasta smothered in homemade Alfredo sauce.', 13.59, 'https://website-03f19273.oyw.wof.temporary.site/wp-content/uploads/knx-items/1/item_691f96caa14525.83076040.jpg', 'active', 1763677898, '2025-11-21 04:31:38', '2025-11-28 03:48:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_items_categories`
--

CREATE TABLE `fyN_knx_items_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `sort_order` int UNSIGNED DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_items_categories`
--

INSERT INTO `fyN_knx_items_categories` (`id`, `hub_id`, `name`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Famous Alfredo', 1, 'active', '2025-11-21 04:29:09', '2025-11-21 04:29:09'),
(2, 2, 'Famous Alfredo', 1, 'active', '2025-11-27 07:45:21', '2025-11-27 07:45:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_item_addon_groups`
--

CREATE TABLE `fyN_knx_item_addon_groups` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `group_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_item_global_modifiers`
--

CREATE TABLE `fyN_knx_item_global_modifiers` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED NOT NULL,
  `global_modifier_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_item_modifiers`
--

CREATE TABLE `fyN_knx_item_modifiers` (
  `id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_520_ci DEFAULT 'single',
  `required` tinyint(1) DEFAULT '0',
  `min_selection` int UNSIGNED DEFAULT '0',
  `max_selection` int UNSIGNED DEFAULT NULL,
  `is_global` tinyint(1) DEFAULT '0',
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_item_modifiers`
--

INSERT INTO `fyN_knx_item_modifiers` (`id`, `item_id`, `hub_id`, `name`, `type`, `required`, `min_selection`, `max_selection`, `is_global`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Size', 'single', 1, 0, NULL, 0, 1, '2025-11-21 04:40:00', '2025-11-21 04:40:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_modifier_options`
--

CREATE TABLE `fyN_knx_modifier_options` (
  `id` bigint UNSIGNED NOT NULL,
  `modifier_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `price_adjustment` decimal(10,2) DEFAULT '0.00',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_modifier_options`
--

INSERT INTO `fyN_knx_modifier_options` (`id`, `modifier_id`, `name`, `price_adjustment`, `is_default`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Small', 0.00, 0, 1, '2025-11-21 04:40:01', '2025-11-21 04:40:01'),
(2, 1, 'Large', 4.49, 0, 2, '2025-11-21 04:40:01', '2025-11-21 04:40:01'),
(3, 1, 'Full Pan', 65.59, 0, 3, '2025-11-21 04:40:01', '2025-11-21 04:40:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_orders`
--

CREATE TABLE `fyN_knx_orders` (
  `id` bigint UNSIGNED NOT NULL,
  `order_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `hub_id` bigint UNSIGNED NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_intent_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_id` bigint UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_order_items`
--

CREATE TABLE `fyN_knx_order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `item_id` bigint UNSIGNED DEFAULT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `modifiers_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_order_items_modifiers`
--

CREATE TABLE `fyN_knx_order_items_modifiers` (
  `id` bigint UNSIGNED NOT NULL,
  `order_item_id` bigint UNSIGNED NOT NULL,
  `modifier_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_adjustment` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_preorders`
--

CREATE TABLE `fyN_knx_preorders` (
  `id` bigint UNSIGNED NOT NULL,
  `preorder_token` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cart_id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `status` enum('pending','completed','expired') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_sessions`
--

CREATE TABLE `fyN_knx_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` char(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_sessions`
--

INSERT INTO `fyN_knx_sessions` (`id`, `user_id`, `token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(39, 1, '68c91cad5c53778bf158da29593926ef1d1f93c832233c7ca33e210a8258c73d', '187.252.250.70', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-12-24 20:11:45', '2025-12-23 20:11:45'),
(40, 1, 'e8c5174274dc540b78962cca9839be8a91b66c76b9b77d83ecbc72f4f4c36ba7', '187.252.250.70', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-12-24 20:12:51', '2025-12-23 20:12:51'),
(41, 1, 'faed6d2782a17d2f6c87a90b5465d3da85fc5c3657309a6a9222e531aa60a0f8', '187.252.250.70', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-12-25 20:22:13', '2025-12-24 20:22:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_settings`
--

CREATE TABLE `fyN_knx_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_520_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fyN_knx_users`
--

CREATE TABLE `fyN_knx_users` (
  `id` bigint UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `role` enum('super_admin','manager','menu_uploader','hub_management','driver','customer','user') COLLATE utf8mb4_unicode_520_ci DEFAULT 'user',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Volcado de datos para la tabla `fyN_knx_users`
--

INSERT INTO `fyN_knx_users` (`id`, `username`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SuperAdmin', 'superadmin@email.com', '$2y$10$8/b00QhHe7gD/MuUkfPKdu3vBhOfUwspwiin11ycXUOQld4adztuO', 'super_admin', 'active', '2025-11-20 21:33:04', NULL),
(2, 'danielsr', 'daniel_sr@email.com', '$2y$12$adZ6.FOQ8F5b69ljY0/yF.5Nbsb6/0fdsZqcYXppudqQoTZxaLUQq', 'customer', 'active', '2025-12-14 19:02:46', NULL),
(3, 'KNXManager', 'manager@email.com', '$2y$12$uSl/BwdSliP9HXNkn2jYGOCV3s0eJ8qEktN55Xho20CPyqLJFO2qC', 'manager', 'active', '2025-12-22 19:59:49', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `fyN_knx_addons`
--
ALTER TABLE `fyN_knx_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `group_id_2` (`group_id`,`sort_order`),
  ADD KEY `status` (`status`);

--
-- Indices de la tabla `fyN_knx_addon_groups`
--
ALTER TABLE `fyN_knx_addon_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hub_id` (`hub_id`),
  ADD KEY `hub_id_2` (`hub_id`,`sort_order`),
  ADD KEY `name` (`name`);

--
-- Indices de la tabla `fyN_knx_carts`
--
ALTER TABLE `fyN_knx_carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_hub` (`customer_id`,`hub_id`),
  ADD KEY `session_idx` (`session_token`),
  ADD KEY `customer_idx` (`customer_id`),
  ADD KEY `hub_idx` (`hub_id`),
  ADD KEY `status_idx` (`status`);

--
-- Indices de la tabla `fyN_knx_cart_items`
--
ALTER TABLE `fyN_knx_cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_idx` (`cart_id`),
  ADD KEY `item_idx` (`item_id`);

--
-- Indices de la tabla `fyN_knx_cities`
--
ALTER TABLE `fyN_knx_cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `name` (`name`),
  ADD KEY `idx_is_operational` (`is_operational`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indices de la tabla `fyN_knx_delivery_rates`
--
ALTER TABLE `fyN_knx_delivery_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_city` (`city_id`),
  ADD KEY `status` (`status`);

--
-- Indices de la tabla `fyN_knx_delivery_zones`
--
ALTER TABLE `fyN_knx_delivery_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hub_id` (`hub_id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `hub_active` (`hub_id`,`is_active`);

--
-- Indices de la tabla `fyN_knx_hubs`
--
ALTER TABLE `fyN_knx_hubs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `status` (`status`),
  ADD KEY `name` (`name`),
  ADD KEY `rating` (`rating`),
  ADD KEY `delivery_zone_type` (`delivery_zone_type`),
  ADD KEY `idx_hub_slug` (`slug`),
  ADD KEY `is_featured` (`is_featured`),
  ADD KEY `slug` (`slug`);

--
-- Indices de la tabla `fyN_knx_hub_categories`
--
ALTER TABLE `fyN_knx_hub_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `sort_order` (`sort_order`),
  ADD KEY `name` (`name`);

--
-- Indices de la tabla `fyN_knx_hub_items`
--
ALTER TABLE `fyN_knx_hub_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hub_id` (`hub_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `hub_id_2` (`hub_id`,`category_id`,`sort_order`),
  ADD KEY `status` (`status`);

--
-- Indices de la tabla `fyN_knx_items_categories`
--
ALTER TABLE `fyN_knx_items_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hub_id` (`hub_id`),
  ADD KEY `hub_id_2` (`hub_id`,`sort_order`),
  ADD KEY `status` (`status`);

--
-- Indices de la tabla `fyN_knx_item_addon_groups`
--
ALTER TABLE `fyN_knx_item_addon_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_group` (`item_id`,`group_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indices de la tabla `fyN_knx_item_global_modifiers`
--
ALTER TABLE `fyN_knx_item_global_modifiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_global` (`item_id`,`global_modifier_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `global_modifier_id` (`global_modifier_id`);

--
-- Indices de la tabla `fyN_knx_item_modifiers`
--
ALTER TABLE `fyN_knx_item_modifiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `hub_id` (`hub_id`),
  ADD KEY `is_global` (`is_global`),
  ADD KEY `item_id_2` (`item_id`,`sort_order`),
  ADD KEY `hub_id_2` (`hub_id`,`is_global`,`sort_order`);

--
-- Indices de la tabla `fyN_knx_modifier_options`
--
ALTER TABLE `fyN_knx_modifier_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modifier_id` (`modifier_id`),
  ADD KEY `modifier_id_2` (`modifier_id`,`sort_order`);

--
-- Indices de la tabla `fyN_knx_orders`
--
ALTER TABLE `fyN_knx_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_idx` (`customer_id`),
  ADD KEY `hub_idx` (`hub_id`),
  ADD KEY `status_idx` (`status`),
  ADD KEY `payment_intent_idx` (`payment_intent_id`),
  ADD KEY `driver_idx` (`driver_id`);

--
-- Indices de la tabla `fyN_knx_order_items`
--
ALTER TABLE `fyN_knx_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_idx` (`order_id`),
  ADD KEY `item_idx` (`item_id`);

--
-- Indices de la tabla `fyN_knx_order_items_modifiers`
--
ALTER TABLE `fyN_knx_order_items_modifiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_idx` (`order_item_id`);

--
-- Indices de la tabla `fyN_knx_preorders`
--
ALTER TABLE `fyN_knx_preorders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_idx` (`cart_id`),
  ADD KEY `preorder_idx` (`preorder_token`),
  ADD KEY `status_idx` (`status`);

--
-- Indices de la tabla `fyN_knx_sessions`
--
ALTER TABLE `fyN_knx_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `token_2` (`token`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `fyN_knx_settings`
--
ALTER TABLE `fyN_knx_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `setting_key_2` (`setting_key`);

--
-- Indices de la tabla `fyN_knx_users`
--
ALTER TABLE `fyN_knx_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_2` (`email`),
  ADD KEY `role` (`role`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `fyN_knx_addons`
--
ALTER TABLE `fyN_knx_addons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_addon_groups`
--
ALTER TABLE `fyN_knx_addon_groups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_carts`
--
ALTER TABLE `fyN_knx_carts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_cart_items`
--
ALTER TABLE `fyN_knx_cart_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_cities`
--
ALTER TABLE `fyN_knx_cities`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_delivery_rates`
--
ALTER TABLE `fyN_knx_delivery_rates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_delivery_zones`
--
ALTER TABLE `fyN_knx_delivery_zones`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_hubs`
--
ALTER TABLE `fyN_knx_hubs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_hub_categories`
--
ALTER TABLE `fyN_knx_hub_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_hub_items`
--
ALTER TABLE `fyN_knx_hub_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_items_categories`
--
ALTER TABLE `fyN_knx_items_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_item_addon_groups`
--
ALTER TABLE `fyN_knx_item_addon_groups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_item_global_modifiers`
--
ALTER TABLE `fyN_knx_item_global_modifiers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_item_modifiers`
--
ALTER TABLE `fyN_knx_item_modifiers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_modifier_options`
--
ALTER TABLE `fyN_knx_modifier_options`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_orders`
--
ALTER TABLE `fyN_knx_orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_order_items`
--
ALTER TABLE `fyN_knx_order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_order_items_modifiers`
--
ALTER TABLE `fyN_knx_order_items_modifiers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_preorders`
--
ALTER TABLE `fyN_knx_preorders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_sessions`
--
ALTER TABLE `fyN_knx_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_settings`
--
ALTER TABLE `fyN_knx_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fyN_knx_users`
--
ALTER TABLE `fyN_knx_users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `fyN_knx_addons`
--
ALTER TABLE `fyN_knx_addons`
  ADD CONSTRAINT `fyN_knx_addons_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `fyN_knx_addon_groups` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_addon_groups`
--
ALTER TABLE `fyN_knx_addon_groups`
  ADD CONSTRAINT `fyN_knx_addon_groups_ibfk_1` FOREIGN KEY (`hub_id`) REFERENCES `fyN_knx_hubs` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_cart_items`
--
ALTER TABLE `fyN_knx_cart_items`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `fyN_knx_carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_items_item` FOREIGN KEY (`item_id`) REFERENCES `fyN_knx_hub_items` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `fyN_knx_delivery_rates`
--
ALTER TABLE `fyN_knx_delivery_rates`
  ADD CONSTRAINT `fyN_knx_delivery_rates_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `fyN_knx_cities` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_delivery_zones`
--
ALTER TABLE `fyN_knx_delivery_zones`
  ADD CONSTRAINT `fyN_knx_delivery_zones_ibfk_1` FOREIGN KEY (`hub_id`) REFERENCES `fyN_knx_hubs` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_hubs`
--
ALTER TABLE `fyN_knx_hubs`
  ADD CONSTRAINT `fyN_knx_hubs_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `fyN_knx_cities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fyN_knx_hubs_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `fyN_knx_hub_categories` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `fyN_knx_hub_items`
--
ALTER TABLE `fyN_knx_hub_items`
  ADD CONSTRAINT `fyN_knx_hub_items_ibfk_1` FOREIGN KEY (`hub_id`) REFERENCES `fyN_knx_hubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fyN_knx_hub_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `fyN_knx_items_categories` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `fyN_knx_items_categories`
--
ALTER TABLE `fyN_knx_items_categories`
  ADD CONSTRAINT `fyN_knx_items_categories_ibfk_1` FOREIGN KEY (`hub_id`) REFERENCES `fyN_knx_hubs` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_item_addon_groups`
--
ALTER TABLE `fyN_knx_item_addon_groups`
  ADD CONSTRAINT `fyN_knx_item_addon_groups_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `fyN_knx_hub_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fyN_knx_item_addon_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `fyN_knx_addon_groups` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_item_global_modifiers`
--
ALTER TABLE `fyN_knx_item_global_modifiers`
  ADD CONSTRAINT `fyN_knx_item_global_modifiers_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `fyN_knx_hub_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fyN_knx_item_global_modifiers_ibfk_2` FOREIGN KEY (`global_modifier_id`) REFERENCES `fyN_knx_item_modifiers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_item_modifiers`
--
ALTER TABLE `fyN_knx_item_modifiers`
  ADD CONSTRAINT `fyN_knx_item_modifiers_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `fyN_knx_hub_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fyN_knx_item_modifiers_ibfk_2` FOREIGN KEY (`hub_id`) REFERENCES `fyN_knx_hubs` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_modifier_options`
--
ALTER TABLE `fyN_knx_modifier_options`
  ADD CONSTRAINT `fyN_knx_modifier_options_ibfk_1` FOREIGN KEY (`modifier_id`) REFERENCES `fyN_knx_item_modifiers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fyN_knx_sessions`
--
ALTER TABLE `fyN_knx_sessions`
  ADD CONSTRAINT `fyN_knx_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `fyN_knx_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
