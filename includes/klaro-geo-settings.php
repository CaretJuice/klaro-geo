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
    $country_settings_class = Klaro_Geo_Country_Settings::get_instance();
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
    $country_settings_class = Klaro_Geo_Country_Settings::get_instance();
    return $country_settings_class->update_location_settings($location_code, $new_settings);
}

/**
 * Get all regions for a country
 *
 * @param string $country_code Country code
 * @return array Array of region settings
 */
function klaro_geo_get_country_regions($country_code) {
    $country_settings_class = Klaro_Geo_Country_Settings::get_instance();
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
    $country_settings_class = Klaro_Geo_Country_Settings::get_instance();
    return $country_settings_class->get_effective_settings($location_code, $is_admin_override);
}

/**
 * Get template data for a specific template
 *
 * @param string $template_key The template key
 * @return array The template data
 */
function klaro_geo_get_template_data($template_key) {
    // Initialize the template settings class
    $template_settings = Klaro_Geo_Template_Settings::get_instance();

    // Get the template
    $template = $template_settings->get_template($template_key);

    // If template not found, return the fallback template
    if (!$template) {
        // Get the fallback template key from country settings
        $country_settings = Klaro_Geo_Country_Settings::get_instance();
        $fallback_template_key = $country_settings->get_default_template();

        // Get the fallback template
        $template = $template_settings->get_template($fallback_template_key);

        // If still no template found, try to get the first available template
        if (!$template) {
            $templates = $template_settings->get();
            if (!empty($templates)) {
                $template_keys = array_keys($templates);
                $first_template_key = reset($template_keys);
                $template = $template_settings->get_template($first_template_key);
            }
        }
    }

    // Ensure consent_mode_settings exists with default values
    // NOTE: initialize_consent_mode has been removed - consent mode is always enabled
    if (!isset($template['consent_mode_settings'])) {
        $template['consent_mode_settings'] = array(
            'ad_storage_service' => 'no_service',
            'analytics_storage_service' => 'no_service',
            'ad_user_data' => false,
            'ad_personalization' => false,
        );
    } else {
        // Remove legacy initialize_consent_mode if present
        unset($template['consent_mode_settings']['initialize_consent_mode']);

        // Ensure ad_storage_service is set
        if (!isset($template['consent_mode_settings']['ad_storage_service'])) {
            $template['consent_mode_settings']['ad_storage_service'] = 'no_service';
        }

        // Ensure analytics_storage_service is set
        if (!isset($template['consent_mode_settings']['analytics_storage_service'])) {
            $template['consent_mode_settings']['analytics_storage_service'] = 'no_service';
        }

        if (!isset($template['consent_mode_settings']['ad_user_data'])) {
            $template['consent_mode_settings']['ad_user_data'] = false;
        }
        if (!isset($template['consent_mode_settings']['ad_personalization'])) {
            $template['consent_mode_settings']['ad_personalization'] = false;
        }
    }

    return $template;
}

/**
 * Localize scripts with template data
 *
 * This function is used to pass template data to JavaScript
 */
function klaro_geo_localize_scripts() {
    // Get current template data from filter
    $template_data = apply_filters('klaro_geo_current_template_data', array());

    // Create data array for JavaScript
    $data = array(
        'templateSettings' => array(
            'config' => $template_data
        )
    );

    // Allow other plugins to modify the data
    $data = apply_filters('klaro_geo_localize_script_data', $data);

    // Localize the script with the data
    wp_localize_script('klaro-geo-consent-mode-js', 'klaroConsentData', $data);

    return $data;
}
