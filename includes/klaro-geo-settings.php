<?php

/**
 * Helper functions for managing Klaro Geo settings
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Get the effective settings for a location
 *
 * @param string $location_code The location code (country or country-region)
 * @param bool   $is_admin_override Optional. Whether this location is from an admin override. Default false.
 * @return array The effective settings
 */
function klaro_geo_get_effective_settings( $location_code, $is_admin_override = false ) {
	$country_settings_class = Klaro_Geo_Country_Settings::get_instance();
	return $country_settings_class->get_effective_settings( $location_code, $is_admin_override );
}
