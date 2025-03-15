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

if (is_admin() || (defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN)) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-countries.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-services.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-settings.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-templates.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin-scripts.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/klaro-geo-admin.php';

    // Include tests in admin only
    if (isset($_GET['klaro_geo_run_tests']) || isset($_GET['page']) && $_GET['page'] === 'klaro-geo-tests') {
        require_once plugin_dir_path(__FILE__) . 'includes/klaro-tests.php';
    }
}
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-config.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-geoip.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-consent-receipts.php';
require_once plugin_dir_path(__FILE__) . 'includes/klaro-geo-consent-button.php';

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

    wp_add_inline_script(
        'klaro-js',
        "document.addEventListener('DOMContentLoaded', function() {
            if (typeof klaroConfig !== 'undefined') {
                console.log('Klaro config loaded:', klaroConfig);
                setTimeout(function() {
                    if (typeof window.klaro === 'undefined') {
                        console.error('Klaro not initialized. Attempting manual initialization...');
                        if (typeof window.klaroConfig !== 'undefined') {
                            window.klaro = window.klaro || {};
                            window.klaro.show = function() {
                                console.log('Manual Klaro show called');
                                if (typeof window.klaroManager !== 'undefined') {
                                    window.klaroManager.show();
                                } else {
                                    console.error('Klaro manager not available');
                                }
                            };
                        }
                    } else {
                        console.log('Klaro initialized successfully');
                    }
                }, 500);
            } else {
                console.error('Klaro config not loaded');
            }
        });",
        'after'
    );

    wp_enqueue_script(
        'klaro-geo-admin-bar-js',
        plugins_url('js/klaro-geo-admin-bar.js', __FILE__),
        array('jquery', 'klaro-js'), // Explicit dependency on klaro-js
        '1.0',
        true
    );

    if (get_option('klaro_geo_enable_consent_receipts', false)) {
        wp_enqueue_script(
            'klaro-consent-receipts-js',
            plugins_url('js/klaro-geo-consent-receipts.js', __FILE__),
            array('klaro-js'), // Explicit dependency on klar'jquery', 'klaro-js'), // Ensure klaro-js is loaded first
            KLARO_GEO_VERSION,
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

    // Always get the latest default values to ensure proper syntax
    $defaults = function_exists('get_klaro_default_values') ? get_klaro_default_values() : array(
        'gtm_oninit' => 'window.dataLayer = window.dataLayer || []; window.gtag = function() { dataLayer.push(arguments); }; gtag(\'consent\', \'default\', {\'ad_storage\': \'denied\', \'analytics_storage\': \'denied\', \'ad_user_data\': \'denied\', \'ad_personalization\': \'denied\'}); gtag(\'set\', \'ads_data_redaction\', true);',
        'gtm_onaccept' => 'if (opts.consents.analytics || opts.consents.advertising) { for(let k of Object.keys(opts.consents)){ if (opts.consents[k]){ let eventName = \'klaro-\'+k+\'-accepted\'; dataLayer.push({\'event\': eventName}); } } }'
    );

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

    $services_json = get_option('klaro_geo_services');
    
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

// Add action to validate services on init
add_action('init', 'klaro_geo_validate_services');


// Function to get user location
function klaro_geo_get_user_location() {
    $location = array(
        'country' => '',
        'region' => ''
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

// Helper function to get default templates
function klaro_geo_get_default_templates() {
    return array(
        'default' => array(
            'name' => 'Default Template',
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
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'default' => false,
                'required' => false,
                'mustConsent' => false,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'translations' => array(
                    'zz' => array(
                        'privacyPolicyUrl' => '/privacy',
                        'consentModal' => array(
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ),
                        'consentNotice' => array(
                            'title' => 'Cookie Consent',
                            'description' => 'Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.',
                            'changeDescription' => 'There were changes since your last visit, please renew your consent.',
                            'learnMore' => 'Let me choose'
                        ),
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'ok' => 'That\'s ok',
                        'close' => 'Close',
                        'save' => 'Save',
                        'privacyPolicy' => array(
                            'name' => 'privacy policy',
                            'text' => 'To learn more, please read our {privacyPolicy}.'
                        ),
                        'purposes' => array(
                            'functional' => array(
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise.'
                            ),
                            'analytics' => array(
                                'title' => 'Analytics',
                                'description' => 'These services process personal information to help us understand how visitors interact with the website.'
                            ),
                            'advertising' => array(
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized or interest-based advertisements.'
                            )
                        )
                    )
                )
            ),
            'wordpress_settings' => array(
                'enable_consent_logging' => true
            )
        ),
        'strict' => array(
            'name' => 'Strict Opt-In',
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
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'default' => false,
                'required' => false,
                'mustConsent' => true,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'translations' => array(
                    'zz' => array(
                        'privacyPolicyUrl' => '/privacy',
                        'consentModal' => array(
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ),
                        'consentNotice' => array(
                            'title' => 'Cookie Consent',
                            'description' => 'Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.',
                            'changeDescription' => 'There were changes since your last visit, please renew your consent.',
                            'learnMore' => 'Let me choose'
                        ),
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'ok' => 'That\'s ok',
                        'close' => 'Close',
                        'save' => 'Save',
                        'privacyPolicy' => array(
                            'name' => 'privacy policy',
                            'text' => 'To learn more, please read our {privacyPolicy}.'
                        ),
                        'purposes' => array(
                            'functional' => array(
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise.'
                            ),
                            'analytics' => array(
                                'title' => 'Analytics',
                                'description' => 'These services process personal information to help us understand how visitors interact with the website.'
                            ),
                            'advertising' => array(
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized or interest-based advertisements.'
                            )
                        )
                    )
                )
            ),
            'wordpress_settings' => array(
                'enable_consent_logging' => true
            )
        ),
        'relaxed' => array(
            'name' => 'Relaxed Opt-Out',
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
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'default' => true,
                'required' => false,
                'mustConsent' => true,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'translations' => array(
                    'zz' => array(
                        'privacyPolicyUrl' => '/privacy',
                        'consentModal' => array(
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ),
                        'consentNotice' => array(
                            'title' => 'Cookie Consent',
                            'description' => 'Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.',
                            'changeDescription' => 'There were changes since your last visit, please renew your consent.',
                            'learnMore' => 'Let me choose'
                        ),
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'ok' => 'That\'s ok',
                        'close' => 'Close',
                        'save' => 'Save',
                        'privacyPolicy' => array(
                            'name' => 'privacy policy',
                            'text' => 'To learn more, please read our {privacyPolicy}.'
                        ),
                        'purposes' => array(
                            'functional' => array(
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise.'
                            ),
                            'analytics' => array(
                                'title' => 'Analytics',
                                'description' => 'These services process personal information to help us understand how visitors interact with the website.'
                            ),
                            'advertising' => array(
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized or interest-based advertisements.'
                            )
                        )
                    )
                )
            ),
            'wordpress_settings' => array(
                'enable_consent_logging' => true
            )
        )
    );
}
?>