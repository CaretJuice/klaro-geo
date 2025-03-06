<?php 
// Function to handle GeoIP lookups and determine template
function klaro_geo_determine_template() {
    klaro_geo_debug_log('Starting GeoIP lookup...');

    // Get settings using new nested structure
    $settings = get_option('klaro_geo_settings', array());
    if (is_string($settings)) {
        $settings = json_decode($settings, true) ?: array();
    }
    if (empty($settings)) {
        $settings = klaro_geo_get_default_geo_settings();
    }

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];

    klaro_geo_debug_log('User location - Country: ' . $user_country . ', Region: ' . $user_region);

    // Get effective settings for the location
    $effective_settings = klaro_geo_get_effective_settings($user_country . ($user_region ? '-' . $user_region : ''));

    // Return the template name
    return $effective_settings['template'] ?? 'default';
}

// Legacy function for backward compatibility
function klaro_geo_determine_default_consent() {
    $template = klaro_geo_determine_template();
    // Default template is strict (opt-in)
    return $template === 'relaxed';
}


?>