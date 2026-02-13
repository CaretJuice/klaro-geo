<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include admin files
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-templates.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-countries.php';
require_once plugin_dir_path(__FILE__) . 'klaro-geo-admin-services.php';

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
    // Set up the default services using the service settings class
    $service_settings = Klaro_Geo_Service_Settings::get_instance();
    $default_services = $service_settings->get_default_services();

    // Make default services available globally
    $GLOBALS['default_services'] = $default_services;

    // Check if services already exist
    $existing_services = $service_settings->get();

    // Only set default services if they don't already exist
    if (empty($existing_services)) {
        // Set default services only on first activation
        $service_settings->set($default_services);
        $service_settings->save();
    }

    // Set default geo settings
    add_option('klaro_geo_country_settings', wp_json_encode(klaro_geo_get_default_geo_settings()));

    // Set up the default templates using the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();
    $default_templates = $template_settings->get_default_templates();

    // Only set default templates if they don't already exist
    if (empty($template_settings->get())) {
        $template_settings->set($default_templates);
        $template_settings->save();
    }

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

        // GTM settings
        delete_option('klaro_geo_gtm_id');
        delete_option('klaro_geo_consent_mode_type');
        delete_option('klaro_geo_gtm_oninit');
        delete_option('klaro_geo_gtm_onaccept');
        delete_option('klaro_geo_gtm_ondecline');

        // Consent Mode settings
        delete_option('klaro_geo_consent_mode_settings');
        delete_option('klaro_geo_analytics_storage_service');
        // Legacy option removed
        delete_option('klaro_geo_initialization_code');

        // Other settings
        delete_option('klaro_geo_js_version');
        delete_option('klaro_geo_js_variant');
        delete_option('klaro_geo_fallback_behavior');
        delete_option('klaro_geo_debug_countries');
        delete_option('klaro_geo_debug_geo');
        delete_option('klaro_geo_enable_consent_receipts');
        delete_option('klaro_geo_cleanup_on_deactivate');

        // Button settings
        delete_option('klaro_geo_enable_floating_button');
        delete_option('klaro_geo_button_text');
        delete_option('klaro_geo_button_theme');
        delete_option('klaro_geo_floating_button_text');
        delete_option('klaro_geo_floating_button_theme');
        delete_option('klaro_geo_floating_button_position');
    }
}

// Helper function to get default geo settings
function klaro_geo_get_default_geo_settings() {
    // Get templates
    $templates = get_option('klaro_geo_templates', array());

    // If there are templates, use the first one as the default
    if (!empty($templates)) {
        $template_keys = array_keys($templates);
        $default_template = reset($template_keys);
    } else {
        // If no templates are available, use an empty string
        // The get_default_template method will handle this case
        $default_template = '';
    }

    return array(
        'default_template' => $default_template,
        'countries' => array()
    );
}

// Default services are now defined in includes/klaro-geo-defaults.php

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
    // Use the first available template if nothing is found
    else {
        // Get templates
        $templates = get_option('klaro_geo_templates', array());

        // If there are templates, use the first one
        if (!empty($templates)) {
            $template_keys = array_keys($templates);
            $template_to_use = reset($template_keys);
            klaro_geo_debug_log('No template specified, using first available template: ' . $template_to_use);
        } else {
            // If no templates are available, use an empty string
            // This will be handled by the template loading code
            $template_to_use = '';
            klaro_geo_debug_log('No templates available, using empty template');
        }
    }

    // Check for country-specific settings in new format
    if ($user_country && isset($country_settings[$user_country])) {
        $country_config = $country_settings[$user_country];

        // Check for template setting
        if (isset($country_config['template'])) {
            // If template is set to 'inherit', use the fallback template
            if ($country_config['template'] === 'inherit') {
                // template_to_use is already set to the fallback template
                klaro_geo_debug_log('Country ' . $user_country . ' is set to inherit from fallback template');
            } else {
                $template_to_use = $country_config['template'];
            }
        }

        // Check for region-specific settings
        if ($user_region && isset($country_config['regions']) && isset($country_config['regions'][$user_region])) {
            // If region template is set to 'inherit', use the fallback template
            if ($country_config['regions'][$user_region] === 'inherit') {
                // template_to_use is already set to the fallback template
                klaro_geo_debug_log('Region ' . $user_region . ' is set to inherit from fallback template');
            } else {
                $template_to_use = $country_config['regions'][$user_region];
            }
        }
    }
    // Check for country-specific settings in legacy format
    else if ($user_country && isset($legacy_settings['countries']) && isset($legacy_settings['countries'][$user_country])) {
        $country_config = $legacy_settings['countries'][$user_country];

        if ($user_region && isset($country_config['regions'][$user_region])) {
            // Use region-specific template if set
            if (!empty($country_config['regions'][$user_region]['template'])) {
                // If template is set to 'inherit', use the fallback template
                if ($country_config['regions'][$user_region]['template'] === 'inherit') {
                    // template_to_use is already set to the fallback template
                    klaro_geo_debug_log('Region ' . $user_region . ' is set to inherit from fallback template (legacy format)');
                } else {
                    $template_to_use = $country_config['regions'][$user_region]['template'];
                }
            }
        } else {
            // Use country-level template if set
            if (!empty($country_config['template'])) {
                // If template is set to 'inherit', use the fallback template
                if ($country_config['template'] === 'inherit') {
                    // template_to_use is already set to the fallback template
                    klaro_geo_debug_log('Country ' . $user_country . ' is set to inherit from fallback template (legacy format)');
                } else {
                    $template_to_use = $country_config['template'];
                }
            }
        }
    }

    // Log the template being used
    klaro_geo_debug_log('Using template: ' . $template_to_use . ' for country: ' . $user_country . ', region: ' . $user_region);

    // Get template configuration
    $template_config = klaro_geo_get_template_config($template_to_use);

    // Build final configuration
    $config = $template_config['config'];

    // Add services configuration using the service settings class
    $service_settings = Klaro_Geo_Service_Settings::get_instance();
    $services = $service_settings->get();
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
    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // Get the template using the class method
    $template = $template_settings->get_template($template_key);

    if (!$template) {
        // Get the fallback template key from country settings
        $country_settings = Klaro_Geo_Country_Settings::get_instance();
        $fallback_template_key = $country_settings->get_default_template();

        // Get the fallback template
        $fallback_template = $template_settings->get_template($fallback_template_key);

        // If fallback template not found, try to get the first available template
        if (!$fallback_template) {
            $templates = $template_settings->get();
            if (!empty($templates)) {
                $template_keys = array_keys($templates);
                $first_template_key = reset($template_keys);
                return $template_settings->get_template($first_template_key);
            }
        }

        return $fallback_template;
    }

    return $template;
}

// Function to output the configuration
function klaro_geo_output_config() {
    $config = klaro_geo_get_config();
    return 'var klaroConfig = ' . wp_json_encode($config, JSON_PRETTY_PRINT) . ';';
}