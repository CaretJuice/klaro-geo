<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include admin files
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-templates.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-countries.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-services.php';

// Define default values as a function to ensure they're always available
function get_klaro_default_values() {
    static $defaults = null;

    if ($defaults === null) {
        $defaults = array(
            'gtm_oninit' => <<<JS
            window.dataLayer = window.dataLayer || [];
            window.gtag = function() { dataLayer.push(arguments); };
            gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'});
            gtag('set', 'ads_data_redaction', true);
            JS,

            'gtm_onaccept' => <<<JS
            if (opts.consents.analytics || opts.consents.advertising) {
                for(let k of Object.keys(opts.consents)){
                    if (opts.consents[k]){
                        let eventName = 'klaro-'+k+'-accepted';
                        dataLayer.push({'event': eventName});
                    }
                }
            }
            JS
        );
    }

    return $defaults;
}

// Register admin menu
function klaro_geo_admin_menu() {
    add_menu_page(
        'Klaro Geo Settings',
        'Klaro Geo',
        'manage_options',
        'klaro-geo',
        'klaro_geo_settings_page_content',
        'dashicons-shield-alt',
        600
    );
    
    add_submenu_page(
        'klaro-geo',
        'Templates',
        'Templates',
        'manage_options',
        'klaro-geo-templates',
        'klaro_geo_templates_page'
    );
    
    if (is_plugin_active('geoip-detect/geoip-detect.php')) {
        add_submenu_page(
            'klaro-geo',
            'Country Settings',
            'Country Settings',
            'manage_options',
            'klaro-geo-country',
            'klaro_geo_country_settings_page_content'
        );
    }
    
    add_submenu_page(
        'klaro-geo',
        'Klaro Services',
        'Services',
        'manage_options',
        'klaro-geo-services',
        'klaro_geo_services_page_content'
    );
}
add_action('admin_menu', 'klaro_geo_admin_menu');


// Activation function
function klaro_geo_activate() {
    global $default_services;
    $defaults = get_klaro_default_values();

    // Clean up the default values
    $defaults['gtm_oninit'] = preg_replace('/\R+/', ' ', $defaults['gtm_oninit']);
    $defaults['gtm_onaccept'] = preg_replace('/\R+/', ' ', $defaults['gtm_onaccept']);

    // Set up the default services with GTM callbacks
    $default_services = [
        [
            "name" => "google-tag-manager",
            "required" => false,
            "purposes" => ["analytics", "advertising"],
            "default" => false,
            "cookies" => [],
            "onInit" => $defaults['gtm_oninit'],
            "onAccept" => $defaults['gtm_onaccept']
        ]
    ];

    // Check if services already exist
    $existing_services = get_option('klaro_geo_services', '');

    // Only set default services if they don't already exist
    if (empty($existing_services)) {
        // Set default services only on first activation
        $encoded_services = wp_json_encode($default_services, JSON_PRETTY_PRINT);
        add_option('klaro_geo_services', $encoded_services);
    }

    // Set default GTM settings (only if they don't exist)
    add_option('klaro_geo_gtm_oninit', $defaults['gtm_oninit'], '', 'no');
    add_option('klaro_geo_gtm_onaccept', $defaults['gtm_onaccept']);

    // Set default purposes (only if they don't exist)
    add_option('klaro_geo_ad_purposes', json_encode(['advertising']));
    add_option('klaro_geo_analytics_purposes', json_encode(['analytics']));

    // Set default geo settings
    add_option('klaro_geo_country_settings', wp_json_encode(klaro_geo_get_default_geo_settings()));

    // Set up the default templates
    $default_templates = klaro_geo_get_default_templates();
    add_option('klaro_geo_templates', $default_templates);

    // Floating button settings
    add_option('klaro_geo_enable_floating_button', false);
    add_option('klaro_geo_button_text', 'Manage Consent');
    add_option('klaro_geo_button_theme', 'light');

    // Other settings
    add_option('klaro_geo_js_version', '0.7');
    add_option('klaro_geo_debug_countries', ['US', 'UK', 'CA', 'FR', 'AU', 'US-CA', 'CA-QC']);
    add_option('klaro_geo_enable_consent_receipts', false);
}

// Deactivation function
function klaro_geo_deactivate() {
    // Only delete options if cleanup is enabled
    if (get_option('klaro_geo_cleanup_on_deactivate')) {
        // Template settings
        delete_option('klaro_geo_templates');
        
        // Country and region settings
        delete_option('klaro_geo_country_settings');
        
        // Service and purpose settings
        delete_option('klaro_geo_services');
        delete_option('klaro_geo_analytics_purposes');
        delete_option('klaro_geo_ad_purposes');
        delete_option('klaro_geo_purposes');
        
        // Other settings
        delete_option('klaro_geo_js_version');
        delete_option('klaro_geo_js_variant');
        delete_option('klaro_geo_fallback_behavior');
        delete_option('klaro_geo_debug_countries');
        delete_option('klaro_geo_enable_consent_receipts');
        delete_option('klaro_geo_cleanup_on_deactivate');

        // Button settings
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_button_text');
        delete_option('klaro_geo_button_theme');
        delete_option('klaro_geo_gtm_id');
    }
}

// Helper function to get default geo settings
function klaro_geo_get_default_geo_settings() {
    return array(
        'default_template' => 'default',
        'countries' => array()
    );
}

// Function to get config based on user location
function klaro_geo_get_config() {
    // Get current settings from the new format
    $country_settings = get_option('klaro_geo_country_settings', array());

    // If empty, try the legacy format
    $legacy_settings = array();
    if (empty($country_settings)) {
        $legacy_settings = json_decode(get_option('klaro_geo_country_settings'), true);
        if (empty($legacy_settings)) {
            $legacy_settings = klaro_geo_get_default_geo_settings();
        }
    }

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];

    // Determine which template to use - first check new format
    if (isset($country_settings['default_template'])) {
        $template_to_use = $country_settings['default_template'];
    }
    // Then check legacy format
    else if (isset($legacy_settings['default_template'])) {
        $template_to_use = $legacy_settings['default_template'];
    }
    // Default to 'default' if nothing is found
    else {
        $template_to_use = 'default';
    }

    // Check for country-specific settings in new format
    if ($user_country && isset($country_settings[$user_country])) {
        $country_config = $country_settings[$user_country];

        // Check for template setting
        if (isset($country_config['template'])) {
            $template_to_use = $country_config['template'];
        }

        // Check for region-specific settings
        if ($user_region && isset($country_config['regions']) && isset($country_config['regions'][$user_region])) {
            $template_to_use = $country_config['regions'][$user_region];
        }
    }
    // Check for country-specific settings in legacy format
    else if ($user_country && isset($legacy_settings['countries']) && isset($legacy_settings['countries'][$user_country])) {
        $country_config = $legacy_settings['countries'][$user_country];

        if ($user_region && isset($country_config['regions'][$user_region])) {
            // Use region-specific template if set
            if (!empty($country_config['regions'][$user_region]['template'])) {
                $template_to_use = $country_config['regions'][$user_region]['template'];
            }
        } else {
            // Use country-level template if set
            if (!empty($country_config['template'])) {
                $template_to_use = $country_config['template'];
            }
        }
    }

    // Log the template being used
    klaro_geo_debug_log('Using template: ' . $template_to_use . ' for country: ' . $user_country . ', region: ' . $user_region);

    // Get template configuration
    $template_config = klaro_geo_get_template_config($template_to_use);

    // Build final configuration
    $config = $template_config['config'];

    // Add services configuration
    $services = json_decode(get_option('klaro_geo_services'), true);
    if (!empty($services)) {
        $config['services'] = $services;
    }

    // Add GTM configuration if needed
    $gtm_config = array(
        'oninit' => get_option('klaro_geo_gtm_oninit'),
        'onaccept' => get_option('klaro_geo_gtm_onaccept')
    );

    if (!empty(array_filter($gtm_config))) {
        $config['gtm'] = $gtm_config;
    }

    return $config;
}

// Function to get template configuration
function klaro_geo_get_template_config($template_key) {
    $templates = get_option('klaro_geo_templates', array());

    if ($template_key === 'default' || !isset($templates[$template_key])) {
        // Get default templates
        $default_templates = klaro_geo_get_default_templates();
        return $default_templates['default'];
    }

    return $templates[$template_key];
}

// Function to output the configuration
function klaro_geo_output_config() {
    $config = klaro_geo_get_config();
    return 'var klaroConfig = ' . wp_json_encode($config, JSON_PRETTY_PRINT) . ';';
}