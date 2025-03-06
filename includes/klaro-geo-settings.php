<?php

/**
 * Helper functions for managing Klaro Geo settings
 */

/**
 * Get settings for a specific location (country or region)
 * 
 * @param string $location_code Country code or region code (e.g., 'US' or 'US-CA')
 * @return array|null Settings for the location or null if not found
 */

 // AJAX handler for saving visible countries
add_action('wp_ajax_save_klaro_visible_countries', 'klaro_geo_save_visible_countries_ajax');

function klaro_geo_save_visible_countries_ajax() {
    check_ajax_referer('klaro_geo_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $countries = isset($_POST['countries']) ? (array)$_POST['countries'] : array();
    $countries = array_map('sanitize_text_field', $countries);

    // Save the visible countries
    update_option('klaro_geo_visible_countries', $countries);

    wp_send_json_success();
}



function klaro_geo_get_location_settings($location_code) {
    klaro_geo_debug_log('Getting location settings for: ' . $location_code);
    $settings = get_option('klaro_geo_settings', array());
    if (is_string($settings)) {
        $settings = json_decode($settings, true) ?: array();
    }
    klaro_geo_debug_log('Raw settings from DB: ' . print_r($settings, true));

    // Split into country and region if region code is provided
    $parts = explode('-', $location_code);
    $country_code = $parts[0];
    $region_code = isset($parts[1]) ? $parts[1] : null;
    klaro_geo_debug_log('Country: ' . $country_code . ', Region: ' . ($region_code ?? 'none'));
    
    // If no settings exist for country, return null
    if (!isset($settings['countries'][$country_code])) {
        klaro_geo_debug_log('No settings found for country: ' . $country_code);
        klaro_geo_debug_log('Available settings: ' . print_r($settings, true));
        return null;
    }

    // Get the country settings
    $country_settings = $settings['countries'][$country_code];
    klaro_geo_debug_log('Found country settings: ' . print_r($country_settings, true));

    // If region code is provided, try to get region settings
    if ($region_code) {
        klaro_geo_debug_log('Checking for region settings');
        if (isset($country_settings['regions'][$region_code])) {
            klaro_geo_debug_log('Found region settings: ' . print_r($country_settings['regions'][$region_code], true));
            klaro_geo_debug_log('Country settings: ' . print_r($country_settings, true));
            // Merge region settings with country settings (region overrides country)
            // Create a copy of country settings without the regions array
            $base_settings = $country_settings;
            unset($base_settings['regions']);

            // Merge in order: country base settings, region settings, is_region flag
            $merged = array_merge(
                $base_settings,
                $country_settings['regions'][$region_code],
                ['is_region' => true]
            );
            klaro_geo_debug_log('Merged settings: ' . print_r($merged, true));
            return $merged;
        }
        klaro_geo_debug_log('No region settings found');
        return null;
    }

    // Return country settings if no region was found or no region code was provided
    return $country_settings;
}

/**
 * Update settings for a specific location
 * 
 * @param string $location_code Country code or region code
 * @param array $new_settings New settings to apply
 * @return bool Success status
 */
function klaro_geo_update_location_settings($location_code, $new_settings) {
    $settings = get_option('klaro_geo_settings', array());
    
    // Split into country and region if region code is provided
    $parts = explode('-', $location_code);
    $country_code = $parts[0];
    $region_code = isset($parts[1]) ? $parts[1] : null;
    
    // Ensure countries array exists
    if (!isset($settings['countries'])) {
        $settings['countries'] = array();
    }

    if ($region_code) {
        // Ensure country exists
        if (!isset($settings['countries'][$country_code])) {
            $settings['countries'][$country_code] = array('regions' => array());
        }
        // Ensure regions array exists
        if (!isset($settings['countries'][$country_code]['regions'])) {
            $settings['countries'][$country_code]['regions'] = array();
        }
        // Update region settings
        $settings['countries'][$country_code]['regions'][$region_code] = $new_settings;
    } else {
        // Update country settings
        $settings['countries'][$country_code] = $new_settings;
        // Ensure regions are preserved if they exist
        if (isset($settings['countries'][$country_code]['regions'])) {
            $settings['countries'][$country_code]['regions'] = array_merge(
                $settings['countries'][$country_code]['regions'],
                $new_settings['regions'] ?? array()
            );
        }
    }
    
    return update_option('klaro_geo_settings', $settings);
}

/**
 * Get all regions for a country
 * 
 * @param string $country_code Country code
 * @return array Array of region settings
 */
add_action('wp_ajax_get_country_regions', 'klaro_geo_get_country_regions');


function klaro_geo_get_country_regions($country_code) {
    // If this is an AJAX request, validate it
    if (defined('DOING_AJAX') && DOING_AJAX) {
        check_ajax_referer('klaro_geo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $country_code = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        if (empty($country_code)) {
            wp_send_json_error('Country code is required');
            return;
        }
    }
    $settings = get_option('klaro_geo_settings', array());
    if (!is_array($settings)) {
        $settings = json_decode($settings, true) ?: array();
    }
    // Initialize empty array even if no settings exist
    if (empty($settings[$country_code])) {
        $settings[$country_code] = array('regions' => array());
    }

    // Get regions from subdivisions.csv
    $regions_by_lang = array();
    $available_languages = array();
    $csv_file = plugin_dir_path(dirname(__FILE__)) . 'subdivisions.csv';

    if (file_exists($csv_file)) {
        $handle = fopen($csv_file, 'r');
        if ($handle !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                if ($data[0] === $country_code) {
                    $region_code = $data[1];
                    $region_name = $data[2];
                    $language = $data[3];
                    $full_code = $country_code . '-' . $region_code;

                    // Skip if language is empty
                    if (empty($language)) {
                        continue;
                    }

                    // Add language to available languages
                    if (!in_array($language, $available_languages)) {
                        $available_languages[] = $language;
                    }

                    // Get existing settings for this region
                    $region_settings = isset($settings[$country_code]['regions'][$region_code])
                        ? $settings[$country_code]['regions'][$region_code]
                        : array('template' => 'inherit');

                    // Store region data by language
                    $regions_by_lang[$language][$region_code] = array(
                        'code' => $region_code,
                        'name' => $region_name,
                        'template' => $region_settings['template']
                    );
                }
            }
            fclose($handle);
        }
    }

    // Sort languages alphabetically
    sort($available_languages);

    // Default to first available language if none specified
    $current_language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : reset($available_languages);

    // Get regions for current language
    $regions = isset($regions_by_lang[$current_language]) ? array_values($regions_by_lang[$current_language]) : array();

    $result = array(
        'regions' => $regions,
        'languages' => $available_languages,
        'current_language' => $current_language
    );

    // If this is an AJAX request, send JSON response
    if (defined('DOING_AJAX') && DOING_AJAX) {
        wp_send_json_success($result);
    }

    // Otherwise return the regions directly
    return isset($settings['countries'][$country_code]['regions']) ? $settings['countries'][$country_code]['regions'] : array();
}



/**
 * Get the effective settings for a location, considering inheritance
 */
function klaro_geo_get_effective_settings($location_code) {
    klaro_geo_debug_log('Getting effective settings for location: ' . $location_code);

    $settings = klaro_geo_get_location_settings($location_code);
    klaro_geo_debug_log('Location settings returned: ' . print_r($settings, true));
    $geo_settings = get_option('klaro_geo_settings', array());
    klaro_geo_debug_log('Global settings: ' . print_r($geo_settings, true));

    if (is_string($geo_settings)) {
        $geo_settings = json_decode($geo_settings, true) ?: array();
    }

    // Default settings
    $effective_settings = array(
        'template' => $geo_settings['default_template'] ?? 'default',
        'fallback_behavior' => $geo_settings['fallback_behavior'] ?? 'default'
    );

    // Split location code to check if it's a region request
    $parts = explode('-', $location_code);
    $country_code = $parts[0];
    $region_code = isset($parts[1]) ? $parts[1] : null;

    // Get country settings
    $country_settings = isset($geo_settings['countries'][$country_code]) ? $geo_settings['countries'][$country_code] : null;

    if ($region_code && $country_settings && isset($country_settings['regions'][$region_code]['template'])) {
        // If region exists and has a template, use it
        klaro_geo_debug_log('Using region template');
        $effective_settings['template'] = $country_settings['regions'][$region_code]['template'];
    } elseif ($country_settings && isset($country_settings['template'])) {
        // If no region match but country exists and has template, use country template
        klaro_geo_debug_log('Using country template');
        $effective_settings['template'] = $country_settings['template'];
    } else {
        // Fall back to default template
        klaro_geo_debug_log('Using default template');
        // Default template already set in $effective_settings
    }

    klaro_geo_debug_log('Effective settings: ' . print_r($effective_settings, true));
    return $effective_settings;
}