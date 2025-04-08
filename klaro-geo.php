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
define('KLARO_GEO_VERSION', '0.3.0');
if (!defined('KLARO_GEO_PATH')) {
    define('KLARO_GEO_PATH', plugin_dir_path(__FILE__));
}
if (!defined('KLARO_GEO_URL')) {
    define('KLARO_GEO_URL', plugin_dir_url(__FILE__));
}
// Default services are defined in includes/admin/klaro-geo-admin.php

// Debug logging function
function klaro_geo_debug_log($message) {
    // Always log during tests, regardless of WP_DEBUG setting
    $is_test = defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN;

    if ($is_test || (defined('WP_DEBUG') && WP_DEBUG === true)) {
        // Format the message
        $formatted_message = '[Klaro Geo Debug] ';
        if (is_array($message) || is_object($message)) {
            $formatted_message .= print_r($message, true);
        } else {
            $formatted_message .= $message;
        }

        // Write to the standard error_log (which will go to the configured WP_DEBUG_LOG location)
        error_log($formatted_message);

        // During tests, also output to stderr for console capture
        if ($is_test) {
            fwrite(STDERR, date('[Y-m-d H:i:s] ') . $formatted_message . PHP_EOL);
        }
    }
}

// Include defaults file first
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-defaults.php';

// Include admin file
require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin.php';

if (is_admin() || (defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN)) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-countries.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-services.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-settings.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-templates.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-scripts.php';
}
// Include base classes
require_once plugin_dir_path(__FILE__) . 'includes/class-klaro-geo-option.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-klaro-geo-country-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-klaro-geo-template-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-klaro-geo-service-settings.php';

// Include legacy files for backward compatibility
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-config.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-geoip.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-consent-receipts.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-consent-button.php';

// The consent receipts table check is now handled in includes/klaro-geo-consent-receipts.php

/**
 * Enqueue all frontend scripts and styles for Klaro Geo
 */
function klaro_geo_enqueue_scripts() {
    // Don't load on admin pages
    if (is_admin()) {
        return;
    }

    wp_enqueue_style('klaro-css', plugins_url('klaro.css', __FILE__), array(), KLARO_GEO_VERSION);
    wp_enqueue_style('klaro-consent-button-css', plugins_url('css/klaro-consent-button.css', __FILE__), array(), KLARO_GEO_VERSION);
    wp_enqueue_style('klaro-embedded-css', plugins_url('css/klaro-embedded.css', __FILE__), array(), KLARO_GEO_VERSION);
    wp_enqueue_style('klaro-geo-consent-mode-css', plugins_url('css/klaro-geo-consent-mode.css', __FILE__), array('klaro-css'), KLARO_GEO_VERSION);

    klaro_geo_generate_config_file();
    $klaro_version = get_option('klaro_geo_js_version', '0.7');
    $klaro_variant = get_option('klaro_geo_js_variant', 'klaro.js');

    wp_enqueue_script(
        'klaro-config',
        plugins_url('klaro-config.js', __FILE__),
        array(),
        KLARO_GEO_VERSION,
        array('strategy' => 'defer', 'in_footer' => true)
    );

    wp_add_inline_script('klaro-config', 'window.klaroVersion = "' . esc_js($klaro_version) . '";', 'before');

    wp_enqueue_script(
        'klaro-js',
        'https://cdn.kiprotect.com/klaro/v' . $klaro_version . '/' . $klaro_variant,
        array('klaro-config'), // Ensures klaro-config loads first
        $klaro_version,
        array('strategy' => 'defer', 'in_footer' => true)
    );

    // Add a simple script to create the Klaro Geo namespace
    wp_add_inline_script('klaro-js', "
        // Create a global Klaro Geo namespace
        window.klaroGeo = window.klaroGeo || {};
    ");

    if ($klaro_variant === 'klaro-no-css.js') {
        wp_enqueue_style(
            'klaro-cdn-css',
            'https://cdn.kiprotect.com/klaro/v' . $klaro_version . '/klaro.min.css',
            array(),
            $klaro_version
        );
    }

    add_filter('script_loader_tag', function ($tag, $handle, $src) {
        if ('klaro-js' === $handle) {
            $tag = str_replace('<script', '<script data-config="klaroConfig"', $tag);
        }
        return $tag;
    }, 10, 3);

    wp_enqueue_script(
        'klaro-geo-admin-bar-js',
        plugins_url('js/klaro-geo-admin-bar.js', __FILE__),
        array('jquery', 'klaro-js'), 
        '1.0',
        true
    );

    // Enqueue the consent mode extension script if needed
    $template_settings = new Klaro_Geo_Template_Settings();
    $templates = $template_settings->get();

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];
    $using_debug_geo = $location['is_admin_override'];

    // Get effective settings for the location, passing the admin override flag
    $effective_settings = klaro_geo_get_effective_settings(
        $user_country . ($user_region ? '-' . $user_region : ''),
        $using_debug_geo
    );

    // Get template to use
    $template_to_use = $effective_settings['template'] ?? 'default';

    // Get the template config
    $template_config = $templates[$template_to_use] ?? $templates['default'] ?? klaro_geo_get_default_templates()['default'];

    // Check if consent mode is enabled in the template
    // First check if consent_mode_settings is directly in the template
    if (isset($template_config['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['consent_mode_settings'];
    } 
    // Then check if it's in the config array (as set by the admin form)
    else if (isset($template_config['config']) && isset($template_config['config']['consent_mode_settings'])) {
        $consent_mode_settings = $template_config['config']['consent_mode_settings'];
    } else {
        $consent_mode_settings = [];
    }

    $initialize_consent_mode = isset($consent_mode_settings['initialize_consent_mode']) ?
        filter_var($consent_mode_settings['initialize_consent_mode'], FILTER_VALIDATE_BOOLEAN) : false;

    // Debug log the consent mode settings
    klaro_geo_debug_log('Consent mode enabled check in wp_enqueue_scripts: ' . ($initialize_consent_mode ? 'true' : 'false'));
    klaro_geo_debug_log('Template config consent_mode_settings: ' . print_r($consent_mode_settings, true));

    if ($initialize_consent_mode) {
        klaro_geo_debug_log('Enqueuing klaro-geo-consent-mode.js script');
        wp_enqueue_script(
            'klaro-geo-consent-mode-js',
            plugins_url('js/klaro-geo-consent-mode.js', __FILE__),
            array('klaro-js'),
            KLARO_GEO_VERSION,
            array('strategy' => 'defer', 'in_footer' => true)
        );
        
        // Create the consent mode settings array
        $consent_mode_data = array(
            'templateSettings' => array(
                'config' => array(
                    'consent_mode_settings' => $consent_mode_settings
                )
            ),
            'detectedCountry' => $user_country,
            'detectedRegion' => $user_region,
            'adminOverride' => $using_debug_geo ? true : false
        );

        // Debug log the consent mode data
        klaro_geo_debug_log('Consent mode data for JavaScript: ' . print_r($consent_mode_data, true));

        // Localize the script with the consent mode settings
        wp_localize_script('klaro-geo-consent-mode-js', 'klaroConsentData', $consent_mode_data);
    }

    if (get_option('klaro_geo_enable_consent_receipts', false)) {
        wp_enqueue_script(
            'klaro-consent-receipts-js',
            plugins_url('js/klaro-geo-consent-receipts.js', __FILE__),
            array('klaro-js'),
            KLARO_GEO_VERSION,
            true
        );

        // Get user location for the script
        $location = klaro_geo_get_user_location();
        $user_country = $location['country'];
        $user_region = $location['region'];
        $using_debug_geo = $location['is_admin_override'];

        // Get template information, passing the admin override flag
        $effective_settings = klaro_geo_get_effective_settings(
            $user_country . ($user_region ? '-' . $user_region : ''),
            $using_debug_geo
        );
        $template_to_use = $effective_settings['template'] ?? 'default';

        // Determine template source
        $template_source = 'fallback';

        // Use the source from effective settings
        if (isset($effective_settings['source'])) {
            $template_source = $effective_settings['source'];
        }

        // Get template settings from the database
        $template_settings = new Klaro_Geo_Template_Settings();
        $templates = $template_settings->get();
        $template_config = $templates[$template_to_use] ?? $templates['default'] ?? klaro_geo_get_default_templates()['default'];

        // Check if consent logging is enabled for this template
        $enable_consent_logging = true; // Default to true
        if (isset($template_config['plugin_settings']['enable_consent_logging'])) {
            $enable_consent_logging = (bool) $template_config['plugin_settings']['enable_consent_logging'];
        }

        // Debug log the admin override value
        klaro_geo_debug_log('Admin override value being passed to JavaScript: ' . var_export($using_debug_geo, true));

        // Get plugin settings from the template
        $plugin_settings = isset($templates[$template_to_use]['plugin_settings']) ?
            $templates[$template_to_use]['plugin_settings'] :
            array('enable_consent_logging' => true);

        // Get enableConsentLogging setting
        $enable_consent_logging = isset($plugin_settings['enable_consent_logging']) ?
            $plugin_settings['enable_consent_logging'] : true;

        // Debug log the settings
        klaro_geo_debug_log('Plugin settings for template ' . $template_to_use . ': ' . print_r($plugin_settings, true));
        klaro_geo_debug_log('enableConsentLogging: ' . ($enable_consent_logging ? 'true' : 'false'));

        // Add variables for the consent receipts script
        wp_localize_script('klaro-consent-receipts-js', 'klaroConsentData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('klaro_geo_consent_nonce'),
            'enableConsentLogging' => $enable_consent_logging,
            'templateName' => $template_to_use,
            'templateSource' => $template_source,
            'detectedCountry' => $user_country,
            'detectedRegion' => $user_region,
            'adminOverride' => $using_debug_geo ? true : false,
            'templateSettings' => $template_config
        ));
    }

    // Get settings for the consent button
    $enable_floating_button = get_option('klaro_geo_enable_floating_button', true);
    $button_text = get_option('klaro_geo_floating_button_text', __('Manage Consent', 'klaro-geo'));
    $button_theme = get_option('klaro_geo_floating_button_theme', 'light');
    $button_position = get_option('klaro_geo_floating_button_position', 'bottom-right');

    // Create settings array
    $button_settings = array(
        'enableFloatingButton' => (bool) $enable_floating_button,
        'floatingButtonText'   => $button_text,
        'floatingButtonTheme'  => $button_theme,
        'floatingButtonPosition' => $button_position,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('klaro_geo_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'version' => KLARO_GEO_VERSION,
    );

    // Add the consent button script with a very high priority to ensure it loads after Klaro
    add_action('wp_footer', function() use ($button_settings) {
        // First output the settings as a global variable
        echo '<script type="text/javascript">
            /* <![CDATA[ */
            window.klaroGeo = ' . wp_json_encode($button_settings) . ';
            /* ]]> */
        </script>';

        // Then include the script
        echo '<script type="text/javascript" src="' . esc_url(KLARO_GEO_URL . 'js/klaro-geo-consent-button.js?ver=' . KLARO_GEO_VERSION) . '" id="klaro-geo-consent-button-js"></script>';
    }, 999);
}
add_action('wp_enqueue_scripts', 'klaro_geo_enqueue_scripts');

// Register activation hooks
register_activation_hook(__FILE__, 'klaro_geo_activate');
register_activation_hook(__FILE__, 'klaro_geo_create_consent_receipts_table');

// Register deactivation hooks
register_deactivation_hook(__FILE__, 'klaro_geo_deactivate');
register_deactivation_hook(__FILE__, 'klaro_geo_drop_consent_receipts_table');

/**
 * Add Google Tag Manager scripts with Klaro compatibility
 * This function adds GTM script tags that work with Klaro consent management
 */
function klaro_geo_add_gtm_head_script() {
    // Don't add to admin pages
    if (is_admin()) {
        return;
    }

    // Get GTM ID from settings
    $gtm_id = get_option('klaro_geo_gtm_id', '');

    // If no GTM ID is set, don't output anything
    if (empty($gtm_id)) {
        return;
    }

    // Output the GTM script with Klaro attributes
    ?>
    <!-- Google Tag Manager (Klaro-compatible) -->
    <script data-type="application/javascript" type="text/plain" data-name="google-tag-manager">
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
    </script>
    <!-- End Google Tag Manager -->
    <?php
}

/**
 * Add Google Tag Manager noscript tag with Klaro compatibility
 */
function klaro_geo_add_gtm_body_script() {
    // Don't add to admin pages
    if (is_admin()) {
        return;
    }

    // Get GTM ID from settings
    $gtm_id = get_option('klaro_geo_gtm_id', '');

    // If no GTM ID is set, don't output anything
    if (empty($gtm_id)) {
        return;
    }

    // Output the GTM noscript tag with Klaro attributes
    ?>
    <!-- Google Tag Manager (noscript) (Klaro-compatible) -->
    <noscript><iframe data-name="google-tag-manager" data-src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php
}

// Add GTM scripts to the appropriate hooks
add_action('wp_head', 'klaro_geo_add_gtm_head_script', 1);
add_action('wp_body_open', 'klaro_geo_add_gtm_body_script', 1);

// Fallback for themes that don't support wp_body_open
function klaro_geo_add_gtm_body_script_fallback() {
    // Only run this if wp_body_open is not supported
    if (!function_exists('wp_body_open')) {
        klaro_geo_add_gtm_body_script();
    }
}
add_action('template_redirect', 'klaro_geo_add_gtm_body_script_fallback');


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

        // Ensure debug_locations is an array
        if (!is_array($debug_locations)) {
            klaro_geo_debug_log('Converting debug locations from string to array: ' . print_r($debug_locations, true));

            if (is_string($debug_locations)) {
                // Convert string to array
                $debug_locations = explode(',', $debug_locations);
                $debug_locations = array_map('trim', $debug_locations);
                $debug_locations = array_filter($debug_locations);

                // Update the option to ensure it's stored as an array for next time
                update_option('klaro_geo_debug_countries', $debug_locations);
            } else {
                // Use default if not a string or array
                $debug_locations = ['US', 'US-CA', 'CA', 'CA-QC', 'UK', 'FR', 'AU'];
            }
        }

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

/**
 * Shortcode for embedding Klaro consent manager in a page or post
 *
 * Usage: [klaro_embedded]
 *
 * This shortcode allows you to embed the Klaro consent manager directly into a page or post.
 * It works best when the template has the "embedded" setting enabled.
 */
function klaro_geo_embedded_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'id' => 'klaro-embedded-container',
        'class' => 'klaro-embedded',
        'style' => 'width: 100%; min-height: 300px;'
    ), $atts, 'klaro_embedded');

    // Get the current template
    $template_settings = new Klaro_Geo_Template_Settings();
    $templates = $template_settings->get();

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];

    // Get effective settings
    $effective_settings = klaro_geo_get_effective_settings($user_country . ($user_region ? '-' . $user_region : ''));
    $template_to_use = $effective_settings['template'] ?? 'default';

    // Check if the template has embedded mode enabled
    $template_config = $templates[$template_to_use]['config'] ?? array();
    $embedded_enabled = isset($template_config['embedded']) && $template_config['embedded'] === true;

    // Create container for the embedded consent manager
    $output = '<div id="' . esc_attr($atts['id']) . '" class="' . esc_attr($atts['class']) . '" style="' . esc_attr($atts['style']) . '">';

    if ($embedded_enabled) {
        // Add a message that this is where Klaro will be embedded
        $output .= '<div class="klaro-placeholder">Loading consent manager...</div>';

        // Add JavaScript to initialize Klaro in this container
        $output .= '<script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof window.klaro !== "undefined") {
                    // If Klaro is already loaded, show it in the container
                    window.klaro.show("' . esc_attr($atts['id']) . '");
                } else {
                    // If Klaro is not loaded yet, wait for it
                    document.addEventListener("klaro-ready", function() {
                        window.klaro.show("' . esc_attr($atts['id']) . '");
                    });
                }
            });
        </script>';
    } else {
        // Show a message that embedded mode is not enabled
        $output .= '<div class="klaro-error">
            <p>Klaro embedded mode is not enabled in the current template settings.</p>
            <p>Please enable the "Embedded Mode" setting in the Klaro Templates page for template: ' . esc_html($template_to_use) . '</p>
        </div>';
    }

    $output .= '</div>';

    return $output;
}
add_shortcode('klaro_embedded', 'klaro_geo_embedded_shortcode');



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

// Register the query variable for debug geo
function klaro_geo_register_query_vars($vars) {
    $vars[] = 'klaro_geo_debug_geo';
    return $vars;
}
add_filter('query_vars', 'klaro_geo_register_query_vars');


function klaro_geo_validate_services() {
    global $default_services;
    static $is_validating = false;

    // Prevent recursive validation
    if ($is_validating) {
        klaro_geo_debug_log('Preventing recursive validation');
        return klaro_geo_get_default_services();
    }

    $is_validating = true;

    // Use the service settings class to get and validate services
    $service_settings = new Klaro_Geo_Service_Settings();
    $services = $service_settings->get();

    // Make sure we have the default services available globally
    $default_services = $service_settings->get_default_services();

    // Validate that services is an array with expected structure
    if (empty($services) || !is_array($services) || !isset($services[0]['name'])) {
        klaro_geo_debug_log('Services has invalid structure, using defaults');
        $services = $default_services;

        // Update the services using the service settings class
        $service_settings->set($services);
        $service_settings->save();
    } else {
        // Validate each service using the class's validate_services method
        $services = $service_settings->validate_services();

        // If validation changed anything, save the changes
        if ($service_settings->is_modified()) {
            $service_settings->save();
        }
    }

    $is_validating = false;

    // Return services array
    return $services;
}

// Add action to validate services on init
add_action('init', 'klaro_geo_validate_services');


// Function to get user location
function klaro_geo_get_user_location() {
    $location = array(
        'country' => '',
        'region' => '',
        'is_admin_override' => false
    );

    // Check if we're in debug mode via query var or GET parameter
    $debug_geo = '';

    // First check query var (works in tests and normal operation)
    $debug_geo = get_query_var('klaro_geo_debug_geo', '');

    // If empty, check GET parameter as fallback (for backward compatibility)
    if (empty($debug_geo) && isset($_GET['klaro_geo_debug_geo'])) {
        $debug_geo = sanitize_text_field($_GET['klaro_geo_debug_geo']);
    }

    // If we have a debug geo value, use it
    if (!empty($debug_geo)) {
        klaro_geo_debug_log('Using debug geo location: ' . $debug_geo);

        // Mark this as an admin override
        $location['is_admin_override'] = true;

        // Check if it's a country-region format (e.g., US-CA)
        if (strpos($debug_geo, '-') !== false) {
            $parts = explode('-', $debug_geo);
            $location['country'] = $parts[0];
            $location['region'] = $parts[1];
        } else {
            $location['country'] = $debug_geo;
        }

        return $location;
    }

    // Check if GeoIP Detection plugin is active
    if (function_exists('geoip_detect2_get_info_from_current_ip')) {
        $geo_info = geoip_detect2_get_info_from_current_ip();

        if ($geo_info && $geo_info->country->isoCode) {
            $location['country'] = $geo_info->country->isoCode;

            // Get region if available
            if ($geo_info->mostSpecificSubdivision && $geo_info->mostSpecificSubdivision->isoCode) {
                $location['region'] = $geo_info->mostSpecificSubdivision->isoCode;
            }
        }
    }

    return $location;
}
?>