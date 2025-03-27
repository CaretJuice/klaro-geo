<?php

/**
 * Helper functions for managing Klaro Geo settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;


/**
 * Get settings for a specific location (country or region)
 *
 * @param string $location_code Country code or region code (e.g., 'US' or 'US-CA')
 * @return array|null Settings for the location or null if not found
 */
function klaro_geo_get_location_settings($location_code) {
    $country_settings_class = new Klaro_Geo_Country_Settings();
    return $country_settings_class->get_location_settings($location_code);
}

/**
 * Update settings for a specific location
 *
 * @param string $location_code Country code or region code
 * @param array $new_settings New settings to apply
 * @return bool Success status
 */
function klaro_geo_update_location_settings($location_code, $new_settings) {
    $country_settings_class = new Klaro_Geo_Country_Settings();
    return $country_settings_class->update_location_settings($location_code, $new_settings);
}

/**
 * Get all regions for a country
 *
 * @param string $country_code Country code
 * @return array Array of region settings
 */
function klaro_geo_get_country_regions($country_code) {
    $country_settings_class = new Klaro_Geo_Country_Settings();
    return $country_settings_class->get_country_regions($country_code);
}


/**
 * Get the effective settings for a location
 *
 * @param string $location_code The location code (country or country-region)
 * @param bool $is_admin_override Optional. Whether this location is from an admin override. Default false.
 * @return array The effective settings
 */
function klaro_geo_get_effective_settings($location_code, $is_admin_override = false) {
    $country_settings_class = new Klaro_Geo_Country_Settings();
    return $country_settings_class->get_effective_settings($location_code, $is_admin_override);
}
