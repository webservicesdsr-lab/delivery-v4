<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Delivery Zone Helper
 * -------------------------------------
 * Point-in-Polygon validation (from old Laravel app)
 * Ray casting algorithm for checking if an address is within delivery area
 */

/**
 * Check if a point (lat, lng) is within a polygon
 * 
 * @param object $point Object with lat and lng properties
 * @param array $polygon Array of objects with lat and lng properties
 * @param int $n Number of points in polygon
 * @return bool True if point is inside polygon
 */
function knx_within_area($point, $polygon, $n) {
    // Close polygon if not already closed
    if ($polygon[0] != $polygon[$n-1]) {
        $polygon[$n] = $polygon[0];
    }
        
    $j = 0;
    $oddNodes = false;
    $x = $point->lng;
    $y = $point->lat;
    
    // Ray casting algorithm
    for ($i = 0; $i < $n; $i++) {
        $j++;
        if ($j == $n) {
            $j = 0;
        }
        
        if ((($polygon[$i]->lat < $y) && ($polygon[$j]->lat >= $y)) || 
            (($polygon[$j]->lat < $y) && ($polygon[$i]->lat >= $y)))
        {
            if ($polygon[$i]->lng + ($y - $polygon[$i]->lat) / 
                ($polygon[$j]->lat - $polygon[$i]->lat) * 
                ($polygon[$j]->lng - $polygon[$i]->lng) < $x)
            {
                $oddNodes = !$oddNodes;
            }
        }
    }
    
    return $oddNodes;
}

/**
 * Check if an address is within hub's delivery zone
 * 
 * @param int $hub_id Hub ID
 * @param float $address_lat Address latitude
 * @param float $address_lng Address longitude
 * @return array ['in_zone' => bool, 'zone_type' => string, 'message' => string]
 */
function knx_check_address_in_delivery_zone($hub_id, $address_lat, $address_lng) {
    global $wpdb;
    
    // Get hub delivery zone type
    $table_hubs = $wpdb->prefix . 'knx_hubs';
    $hub = $wpdb->get_row($wpdb->prepare(
        "SELECT latitude, longitude, delivery_zone_type, delivery_radius FROM $table_hubs WHERE id = %d",
        $hub_id
    ));
    
    if (!$hub) {
        return [
            'in_zone' => false,
            'zone_type' => null,
            'message' => 'Hub not found'
        ];
    }
    
    // Check based on zone type
    if ($hub->delivery_zone_type === 'polygon') {
        // Get polygon from delivery_zones table
        $table_zones = $wpdb->prefix . 'knx_delivery_zones';
        $zone = $wpdb->get_row($wpdb->prepare(
            "SELECT polygon_points FROM $table_zones WHERE hub_id = %d AND is_active = 1 LIMIT 1",
            $hub_id
        ));
        
        if (!$zone || !$zone->polygon_points) {
            return [
                'in_zone' => false,
                'zone_type' => 'polygon',
                'message' => 'No polygon defined for this hub'
            ];
        }
        
        // Decode polygon points
        $polygon_array = json_decode($zone->polygon_points);
        if (!$polygon_array || count($polygon_array) < 3) {
            return [
                'in_zone' => false,
                'zone_type' => 'polygon',
                'message' => 'Invalid polygon data'
            ];
        }
        
        // Create point object
        $point = (object) [
            'lat' => floatval($address_lat),
            'lng' => floatval($address_lng)
        ];
        
        // Check if point is within polygon
        $in_zone = knx_within_area($point, $polygon_array, count($polygon_array));
        
        return [
            'in_zone' => $in_zone,
            'zone_type' => 'polygon',
            'message' => $in_zone ? 'Address is within delivery zone' : 'Address is outside delivery zone'
        ];
        
    } else {
        // Radius-based check
        $hub_lat = floatval($hub->latitude);
        $hub_lng = floatval($hub->longitude);
        $radius_miles = floatval($hub->delivery_radius);
        
        // Calculate distance using Haversine formula
        $distance = knx_calculate_distance($hub_lat, $hub_lng, $address_lat, $address_lng);
        $in_zone = $distance <= $radius_miles;
        
        return [
            'in_zone' => $in_zone,
            'zone_type' => 'radius',
            'distance' => round($distance, 2),
            'radius' => $radius_miles,
            'message' => $in_zone ? 
                "Address is within delivery radius ($distance miles)" : 
                "Address is outside delivery radius ($distance miles > $radius_miles miles)"
        ];
    }
}

/**
 * Calculate distance between two points using Haversine formula
 * 
 * @param float $lat1 First point latitude
 * @param float $lng1 First point longitude
 * @param float $lat2 Second point latitude
 * @param float $lng2 Second point longitude
 * @return float Distance in miles
 */
function knx_calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 3958.8; // Earth radius in miles
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

/**
 * REST API endpoint to check if address is in delivery zone
 */
add_action('rest_api_init', function () {
    register_rest_route('knx/v1', '/check-delivery-zone', [
        'methods' => 'POST',
        'callback' => 'knx_api_check_delivery_zone',
        'permission_callback' => '__return_true'
    ]);
});

function knx_api_check_delivery_zone($request) {
    $hub_id = intval($request->get_param('hub_id'));
    $lat = floatval($request->get_param('lat'));
    $lng = floatval($request->get_param('lng'));
    
    if (!$hub_id || !$lat || !$lng) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Missing required parameters: hub_id, lat, lng'
        ], 400);
    }
    
    $result = knx_check_address_in_delivery_zone($hub_id, $lat, $lng);
    
    return new WP_REST_Response([
        'success' => true,
        'data' => $result
    ], 200);
}
