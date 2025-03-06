<?php
/**
 * Plugin Name: Klaro Geo
 * Description: Loads Klaro! with Geo overrides when installed with the Geolocation IP Detection plugin.
 * Version: 0.3.0
 * Author: Caret Juice Data Ltd., Damon Gudaitis
 * Author URI: https://caretjuice.com
 * Requires at least: 6.6
 * Tested up to: 6.7
 * Requires PHP: 7.2
 */
defined('ABSPATH') or die('No script kiddies please!');
define('KLARO_GEO_VERSION', '1.0.0');

function klaro_geo_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        // Check if we're running in a test environment
        $prefix = defined('WP_TESTS_DOMAIN') ? '[Klaro Geo Test]' : '[Klaro Geo]';

        // Get the debug log path
        $debug_log_path = WP_DEBUG_LOG === true ? '/var/www/html/wp-content/debug.log' : WP_DEBUG_LOG;

        // Make sure the directory exists and is writable
        $debug_log_dir = dirname($debug_log_path);
        if (!file_exists($debug_log_dir)) {
            @mkdir($debug_log_dir, 0755, true);
        }

        // Make sure the log file exists and is writable
        if (!file_exists($debug_log_path)) {
            @touch($debug_log_path);
            @chmod($debug_log_path, 0666);
        }

        // Format the message
        $formatted_message = $prefix . ' ' . $message;

        // *** Prioritize writing directly to debug.log ***
        @file_put_contents($debug_log_path, date('[Y-m-d H:i:s]') . ' ' . $formatted_message . "\n", FILE_APPEND);

        // Try standard error_log first
        error_log($formatted_message);

        // Also write directly to the file as a backup
        // This ensures logging works even if error_log is redirected elsewhere
        @file_put_contents($debug_log_path, date('[Y-m-d H:i:s]') . ' ' . $formatted_message . "\n", FILE_APPEND);

        // For debugging purposes during development
        if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
            echo '<pre>[Debug] ' . htmlspecialchars($formatted_message) . '</pre>';
        }
    }
}


// Define default services globally
if (!isset($GLOBALS['default_services'])) {
    $GLOBALS['default_services'] = [
        [
            "name" => "google-tag-manager",
            "required" => false,
            "purposes" => ["analytics", "advertising"],
            "default" => false,
            "cookies" => []
        ]
    ];
}

if (is_admin() || (defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN)) {
    require_once plugin_dir_path(__FILE__) . 'includes/klaro-admin.php';
}
require_once plugin_dir_path(__FILE__) . 'includes/klaro-config.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geoip.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-defaults.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-consent-receipts.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-consent-button.php';

// Enqueue Klaro! files
function klaro_geo_enqueue_scripts() {
    wp_enqueue_style( 'klaro-css', plugins_url( 'klaro.css', __FILE__ ) );
    wp_enqueue_style( 'klaro-consent-button-css', plugins_url( 'css/klaro-consent-button.css', __FILE__ ) );

    // Generate and enqueue klaro-config.js (this needs to be done FIRST)
    klaro_geo_generate_config_file();

    wp_enqueue_script(
        'klaro-config',
        plugins_url( 'klaro-config.js', __FILE__ ),
        array(), // No dependencies for klaro-config.js
        '1.0',
        array(
            'strategy' => 'defer',
            'in_footer' => true
        )
    );

    // Get the Klaro JS version and variant from settings
    $klaro_version = get_option('klaro_geo_js_version', '0.7');
    $klaro_variant = get_option('klaro_geo_js_variant', 'klaro.js');

    // Enqueue the Klaro script from CDN
    wp_enqueue_script(
        'klaro-js',
        'https://cdn.kiprotect.com/klaro/v' . $klaro_version . '/' . $klaro_variant,
        array('klaro-config'), // Make sure config is loaded first
        $klaro_version,
        array(
            'strategy' => 'defer',
            'in_footer' => true
        )
    );

    // If using the no-css variant, we need to load the CSS separately
    if ($klaro_variant === 'klaro-no-css.js') {
        wp_enqueue_style(
            'klaro-cdn-css',
            'https://cdn.kiprotect.com/klaro/v' . $klaro_version . '/klaro.min.css',
            array(),
            $klaro_version
        );
    }

    // Add data attributes to the script tag
    add_filter('script_loader_tag', function($tag, $handle, $src) {
        if ('klaro-js' === $handle) {
            $tag = str_replace('<script', '<script data-config="klaroConfig"', $tag);
        }
        return $tag;
    }, 10, 3);

    wp_enqueue_script(
        'klaro-geo-admin-bar-js', // Handle/name
        plugins_url( 'js/klaro-geo-admin-bar.js', __FILE__ ), // Corrected path
        array('jquery'),  // Add jQuery as a dependency
        '1.0', // Version
        true // In footer
    );

    // Enqueue consent button script
    wp_enqueue_script(
        'klaro-consent-button-js',
        plugins_url('js/klaro-consent-button.js', __FILE__),
        array(),
        '1.0',
        true
    );

    // Get template information for theme detection
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];
    $effective_settings = klaro_geo_get_effective_settings($user_country . ($user_region ? '-' . $user_region : ''));
    $template_to_use = $effective_settings['template'] ?? 'default';
    $templates = klaro_geo_get_default_templates();
    $template_config = $templates[$template_to_use] ?? $templates['default'];

    // Determine theme based on template config
    $theme = 'light';
    if (isset($template_config['config']['styling']['theme']['color']) &&
        $template_config['config']['styling']['theme']['color'] === 'dark') {
        $theme = 'dark';
    }

    // Add variables for the consent button script
    wp_localize_script('klaro-consent-button-js', 'klaroConsentButtonData', array(
        'floatingButtonEnabled' => get_option('klaro_geo_enable_floating_button', true),
        'buttonText' => get_option('klaro_geo_button_text', 'Manage Consent Settings'),
        'theme' => get_option('klaro_geo_button_theme', $theme)
    ));


    // Enqueue consent receipts script if enabled
    if (get_option('klaro_geo_enable_consent_receipts', false)) {
        wp_enqueue_script(
            'klaro-consent-receipts-js',
            plugins_url('js/klaro-consent-receipts.js', __FILE__),
            array(),
            '1.0',
            true
        );

        // Get user location for the script
        $location = klaro_geo_get_user_location();
        $user_country = $location['country'];
        $user_region = $location['region'];

        // Get template information
        $effective_settings = klaro_geo_get_effective_settings($user_country . ($user_region ? '-' . $user_region : ''));
        $template_to_use = $effective_settings['template'] ?? 'default';

        // Determine template source
        $template_source = 'fallback';
        if (isset($effective_settings['source'])) {
            $template_source = $effective_settings['source'];
        } elseif (!empty($user_country) && !empty($user_region)) {
            $template_source = 'geo-match region';
        } elseif (!empty($user_country)) {
            $template_source = 'geo-match country';
        }

        // Get template settings
        $templates = klaro_geo_get_default_templates();
        $template_config = $templates[$template_to_use] ?? $templates['default'];

        // Check if consent logging is enabled for this template
        $enable_consent_logging = true; // Default to true
        if (isset($template_config['wordpress_settings']['enable_consent_logging'])) {
            $enable_consent_logging = (bool) $template_config['wordpress_settings']['enable_consent_logging'];
        }

        // Add variables for the consent receipts script
        wp_localize_script('klaro-consent-receipts-js', 'klaroConsentData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('klaro_geo_consent_nonce'),
            'consentReceiptsEnabled' => true,
            'enableConsentLogging' => $enable_consent_logging,
            'templateName' => $template_to_use,
            'templateSource' => $template_source,
            'detectedCountry' => $user_country,
            'detectedRegion' => $user_region,
            'templateSettings' => $template_config
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'klaro_geo_enqueue_scripts' );

// Register activation hooks
register_activation_hook(__FILE__, 'klaro_geo_activate');
register_activation_hook(__FILE__, 'klaro_geo_create_consent_receipts_table');

// Register deactivation hooks
register_deactivation_hook(__FILE__, 'klaro_geo_deactivate');
register_deactivation_hook(__FILE__, 'klaro_geo_drop_consent_receipts_table');



function klaro_geo_admin_bar_menu($wp_admin_bar) {
    if (current_user_can('manage_options')) { // Only show to admins
        // Get the current debug country from the query var
        $current_country = get_query_var('klaro_geo_debug_geo', 'None');
        
        // Set the title to show the current debug country
        $args = array(
            'id' => 'klaro-geo-debug',
            'title' => 'Klaro Geo Debug Country: ' . $current_country,
            'href' => '#',
        );
        $wp_admin_bar->add_node($args);

        // Add country and region options
        $debug_locations = get_option('klaro_geo_debug_countries', ['US', 'US-CA', 'CA', 'CA-QC', 'UK', 'FR', 'AU']);

        // Add Countries submenu
        $wp_admin_bar->add_node(array(
            'id' => 'klaro-geo-debug-countries',
            'title' => 'Countries',
            'parent' => 'klaro-geo-debug'
        ));

        // Add Regions submenu
        $wp_admin_bar->add_node(array(
            'id' => 'klaro-geo-debug-regions',
            'title' => 'Regions',
            'parent' => 'klaro-geo-debug'
        ));

        foreach ($debug_locations as $code) {
            // Determine if this is a region code
            $is_region = strpos($code, '-') !== false;

            $args = array(
                'id' => 'klaro-geo-debug-' . str_replace('-', '_', $code), // Replace - with _ for valid ID
                'title' => $code,
                'href' => add_query_arg('klaro_geo_debug_geo', $code, wp_unslash($_SERVER['REQUEST_URI'])),
                'parent' => $is_region ? 'klaro-geo-debug-regions' : 'klaro-geo-debug-countries',
            );
            $wp_admin_bar->add_node($args);
        }
    }
}
add_action('admin_bar_menu', 'klaro_geo_admin_bar_menu', 999); // High priority to ensure it shows



// Add sanitization for ISO 3166-2 codes
function klaro_geo_sanitize_debug_geo($value) {
    $value = strtoupper($value); // Transform to uppercase first
    if (preg_match('/^[A-Z]{2}(-[A-Z0-9]{2,3})?$/', $value)) { // Check if it's a 2-letter country code or ISO 3166-2
        return $value;
    } else {
        return ''; // Return empty string if not valid
    }
}

add_filter('sanitize_option_klaro_geo_debug_geo', 'klaro_geo_sanitize_debug_geo');



register_activation_hook(__FILE__, 'klaro_geo_create_templates');

function klaro_geo_create_templates() {
    $existing_templates = get_option('klaro_geo_templates');
    if (!empty($existing_templates)) {
        return;
    }

    $default_template = array(
        'name' => 'Default Template',
        'inherit_from' => 'none',
        'config' => array(
            'version' => 1,
            'elementID' => 'klaro',
            'styling' => array(
                'theme' => array(
                    'color' => 'light',
                    'position' => 'top',
                    'width' => 'wide'
                )
            ),
            'noAutoLoad' => false,
            'htmlTexts' => true,
            'embedded' => false,
            'groupByPurpose' => true,
            'storageMethod' => 'cookie',
            'cookieName' => 'klaro',
            'cookieDomain' => '',
            'cookieExpiresAfterDays' => 365,
            'default' => false,
            'mustConsent' => false,
            'acceptAll' => true,
            'hideDeclineAll' => false,
            'hideLearnMore' => false,
            'noticeAsModal' => false,
            'translations' => array(
                'en' => array(
                    'consentModal' => array(
                        'title' => 'Privacy Settings',
                        'description' => '',
                    ),
                    'acceptAll' => 'Accept All',
                    'declineAll' => 'Decline All',
                    'learnMore' => 'Learn More',
                    'accept' => 'Accept',
                    'decline' => 'Decline',
                )
            )
        ),
        'wordpress_settings' => array(
            'enable_consent_logging' => true
        )
    );

    update_option('klaro_geo_templates', array('default' => $default_template));
}



function klaro_geo_validate_services() {
    global $default_services;
    static $is_validating = false;

    if (!isset($default_services) || empty($default_services)) {
            // Get default callback values
            $defaults = function_exists('get_klaro_default_values') ? get_klaro_default_values() : array(
                'gtm_oninit' => '',
                'gtm_onaccept' => '',
                'gtm_ondecline' => ''
            );

            $default_services = [
                [
                    "name" => "google-tag-manager",
                    "required" => false,
                    "purposes" => ["analytics", "advertising"],
                    "default" => false,
                    "cookies" => [],
                    "onInit" => $defaults['gtm_oninit'],
                    "onAccept" => $defaults['gtm_onaccept'],
                    "onDecline" => $defaults['gtm_ondecline']
                ]
            ];
        }

    $services_json = get_option('klaro_geo_services');
    klaro_geo_debug_log('Validating services. Current value: ' . print_r($services_json, true));
    
    // Handle empty services
    if (empty($services_json)) {
        if ($is_validating) {
                klaro_geo_debug_log('Preventing recursive validation');
                return $default_services;
            }
        $is_validating = true;
        klaro_geo_debug_log('Services empty, setting defaults');
        $encoded_services = wp_json_encode($default_services, JSON_PRETTY_PRINT);
        update_option('klaro_geo_services', $encoded_services);
        return $default_services;
    }
    
    // Handle JSON decoding
    $services = json_decode($services_json, true);
    $json_error = json_last_error();
    
    if ($json_error !== JSON_ERROR_NONE) {
        if ($is_validating) {
            klaro_geo_debug_log('Preventing recursive validation');
            return $default_services;
        }
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            klaro_geo_debug_log('Invalid JSON in services, using defaults. Error: ' . json_last_error_msg());
        }
        $encoded_services = wp_json_encode($default_services, JSON_PRETTY_PRINT);
        update_option('klaro_geo_services', $encoded_services);
        return $default_services;
    }
    $is_validating = false;
    // Return decoded services
    return $services;
}


function klaro_geo_get_user_location() {
    // Check for debug geo override
    $debug_geo = get_query_var('klaro_geo_debug_geo', '');
    if (!empty($debug_geo)) {
        klaro_geo_debug_log('Using debug geo override: ' . $debug_geo);
        $parts = explode('-', $debug_geo);
        $country_code = $parts[0];
        $region_code = isset($parts[1]) ? $debug_geo : null;

        return array(
            'country' => $country_code,
            'region' => $region_code
        );
    }

    // Fall back to GeoIP detection
    if (!function_exists('geoip_detect2_get_info_from_current_ip')) {
        return array('country' => null, 'region' => null);
    }

    $geo_info = geoip_detect2_get_info_from_current_ip();
    $country_code = $geo_info->country->isoCode;
    $region_code = null;

    if ($geo_info->mostSpecificSubdivision) {
        $region_code = $country_code . '-' . $geo_info->mostSpecificSubdivision->isoCode;
    }

    return array(
        'country' => $country_code,
        'region' => $region_code
    );
}
?>