<?php
global $default_services;
if (!isset($default_services) || empty($default_services)) {
    $default_services = [
        [
            "name" => "google-tag-manager",
            "required" => false,
            "purposes" => ["analytics", "advertising"],
            "default" => false,
            "cookies" => []
        ]
    ];
}

// Enqueue admin scripts
function klaro_geo_admin_scripts($hook) {
    klaro_geo_debug_log('Admin script hook: ' . $hook);

    wp_enqueue_script(
        'klaro-geo-admin-js',
        plugins_url( '../js/klaro-geo-admin.js', __FILE__ ),
        array('jquery'),
        '1.0',
        true
    );

    if ($hook === 'klaro-geo_page_klaro-geo-country') {
        // Enqueue the JavaScript file for country settings
        wp_enqueue_script(
            'klaro-geo-country-settings-js',
            plugins_url('../js/klaro-geo-country-settings.js', __FILE__),
            array('jquery'), // Add jQuery as a dependency
            '1.0', // Version number
            true // Load in footer
        );
    }
    // Enqueue the CSS files for Klaro Geo admin
    if (strpos($hook, 'klaro-geo') !== false) {
        wp_enqueue_style(
            'klaro-geo-admin',
            plugins_url('../css/klaro-geo-admin.css', __FILE__),
            array(),
            KLARO_GEO_VERSION
        );

        // Add jQuery UI styles for tabs
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );

        // Add translations CSS
        wp_enqueue_style(
            'klaro-geo-translations',
            plugins_url('../css/klaro-geo-translations.css', __FILE__),
            array('jquery-ui-style'),
            KLARO_GEO_VERSION
        );

        // Enqueue jQuery UI tabs
        wp_enqueue_script('jquery-ui-tabs');
        wp_register_script(
            'klaro-geo-admin',
            plugins_url('js/klaro-geo-admin.js', dirname(__FILE__)),
            array('jquery'),
            KLARO_GEO_VERSION,
            false
        );
        
        $templates = get_option('klaro_geo_templates', array());

        wp_localize_script(
            'klaro-geo-admin',
           'klaroGeoAdmin',
           array(
               'ajaxurl' => admin_url('admin-ajax.php'),
               'nonce' => wp_create_nonce('klaro_geo_nonce'),
               'templates' => $templates
           )
        );

        // Also make templates available globally
        wp_add_inline_script(
            'klaro-geo-admin',
            'window.klaroTemplates = ' . wp_json_encode($templates) . ';',
            'before'
        );

        wp_enqueue_script('klaro-geo-admin');
        klaro_geo_debug_log('Script handle exists: ' . (wp_script_is('klaro-geo-admin', 'registered') ? 'yes' : 'no'));
        klaro_geo_debug_log('Script is enqueued: ' . (wp_script_is('klaro-geo-admin', 'enqueued') ? 'yes' : 'no'));
    } else {
        klaro_geo_debug_log('Not loading Klaro Geo admin scripts on this page');
    }



}
add_action( 'admin_enqueue_scripts', 'klaro_geo_admin_scripts' );


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
            JS,
            'gtm_ondecline' => ""
        );
    }
    
    return $defaults;
}


// Register settings
add_action('admin_init', function() {
    $defaults = get_klaro_default_values();

    // Register region settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_opt_in_regions', [
        'type' => 'string',
        'default' => '[]',
        'sanitize_callback' => function($input) {
            if (empty($input)) {
                return '[]';
            }
            // Validate ISO 3166-2 format
            $regions = array_map('trim', explode(',', $input));
            $valid_regions = array_filter($regions, function($region) {
                return preg_match('/^[A-Z]{2}-[A-Z0-9]{2,3}$/', $region);
            });
            return implode(',', $valid_regions);
        }
    ]);

    // Register GTM settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_gtm_oninit', [
        'type' => 'string',
        'default' => $defaults['gtm_oninit'],
        'sanitize_callback' => function($input) {
            $defaults = get_klaro_default_values();
            if (empty($input)) {
                klaro_geo_debug_log('Empty GTM onInit input, using default');
                return $defaults['gtm_oninit'];
            }
            $input = preg_replace('/\R+/', ' ', $input);
            return $input;
        }
    ]);
    klaro_geo_debug_log('[Klaro Geo Debug] Register settings - $defaults[gtm_oninit] value: ' . print_r($defaults['gtm_oninit'], true));

    register_setting('klaro_geo_settings_group', 'klaro_geo_gtm_onaccept', [
        'type' => 'string',
        'default' => $defaults['gtm_onaccept'],
        'sanitize_callback' => function($input) {
            $defaults = get_klaro_default_values();
            if (empty($input)) {
                klaro_geo_debug_log('Empty GTM onAccept input, using default');
                return $defaults['gtm_onaccept'];
            }
            $input = preg_replace('/\R+/', ' ', $input);
            return $input;
        }
    ]);
    
    register_setting('klaro_geo_settings_group', 'klaro_geo_gtm_ondecline', [
        'type' => 'string',
        'default' => $defaults['gtm_ondecline'],
        'sanitize_callback' => function($input) {
            $defaults = get_klaro_default_values();
            if (empty($input)) {
                klaro_geo_debug_log('Empty GTM onDecline input, using default');
                return $defaults['gtm_ondecline']; // Return the default value
            }
            $input = preg_replace('/\R+/', ' ', $input);
            return $input;  // Return the sanitized value if it exists
        }
    ]);

    register_setting('klaro_geo_settings_group', 'klaro_geo_services', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => function($input) {
            global $default_services;
            
            if (empty($input)) {
                klaro_geo_debug_log('Empty services input, using defaults');
                return wp_json_encode($default_services, JSON_PRETTY_PRINT);
            }
            
            $decoded = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                klaro_geo_debug_log('Invalid JSON in services, using defaults. Error: ' . json_last_error_msg());
                return wp_json_encode($default_services, JSON_PRETTY_PRINT);
            }
            
            if (empty($decoded)) {
                klaro_geo_debug_log('Decoded services empty, using defaults');
                return wp_json_encode($default_services, JSON_PRETTY_PRINT);
            }
            
            klaro_geo_debug_log('Sanitizing services input:');
            klaro_geo_debug_log('Input: ' . print_r($input, true));
            klaro_geo_debug_log('Decoded: ' . print_r($decoded, true));
            return wp_json_encode($decoded, JSON_PRETTY_PRINT);        
        }
    ]);
    register_setting('klaro_geo_settings_group', 'klaro_geo_debug_countries', [
        'type' => 'array',
        'sanitize_callback' => function($input) {
            if (empty($input)) {
                return ['US','UK','CA','FR','AU', 'US-CA', 'CA-QC'];
            }
            $countries = array_map('trim', explode(',', $input));
            $countries = array_map('strtoupper', $countries);
            return array_filter($countries); // Remove empty values
        }
    ]);
    register_setting('klaro_geo_settings_group', 'klaro_geo_cleanup_on_deactivate', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => function($input) {
            return !empty($input);
        }
    ]);

    // Register consent receipts setting
    register_setting('klaro_geo_settings_group', 'klaro_geo_enable_consent_receipts', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => function($input) {
            return !empty($input);
        }
    ]);
    // Register the templates
    register_setting('klaro_geo_settings_group', 'klaro_geo_templates', [
        'type' => 'array',
        'sanitize_callback' => 'klaro_geo_sanitize_templates', // Custom sanitization callback
    ]);
});

function klaro_geo_sanitize_templates( $templates ) {
    // Ensure templates are an array
    if ( ! is_array( $templates ) ) {
        return [];
    }

    // Sanitize each template
    foreach ( $templates as $key => $template ) {
        $templates[$key]['name'] = sanitize_text_field( $template['name'] );

        // Sanitize config array
        if ( is_array( $template['config'] ) ) {
            foreach ( $template['config'] as $config_key => $config_value ) {
                if ( is_bool( $config_value ) ) {
                    // Booleans don't need sanitization
                } elseif ( is_string( $config_value ) ) {
                    $templates[$key]['config'][$config_key] = sanitize_text_field( $config_value );
                }
            }
        }

        // Sanitize WordPress settings
        if ( isset( $template['wordpress_settings'] ) && is_array( $template['wordpress_settings'] ) ) {
            foreach ( $template['wordpress_settings'] as $setting_key => $setting_value ) {
                if ( $setting_key === 'enable_consent_logging' ) {
                    // Convert to boolean
                    $templates[$key]['wordpress_settings'][$setting_key] = (bool) $setting_value;
                }
            }
        } else {
            // Initialize with default WordPress settings if not set
            $templates[$key]['wordpress_settings'] = array(
                'enable_consent_logging' => true
            );
        }
    }

    return $templates;
}

add_action('init', function() {
    global $wp;
    $wp->add_query_var('klaro_geo_debug_geo');
});


// Add settings page
function klaro_geo_settings_page() {
    add_menu_page(
        'Klaro Geo Settings',
        'Klaro Geo',
        'manage_options',
        'klaro-geo',
        'klaro_geo_settings_page_content',
        'dashicons-shield-alt', // You can change the icon
        600 // Adjust the position in the menu
    );
    add_submenu_page(
        'klaro-geo', // Parent slug (main Klaro Geo menu)
        'Templates', // Page title
        'Templates', // Menu title
        'manage_options', // Capability
        'klaro-geo-templates', // Menu slug
        'klaro_geo_templates_page' // Function to display the page
    );
    
    if ( is_plugin_active( 'geoip-detect/geoip-detect.php' ) ) {
        // Add a submenu page for country settings
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
        'klaro-geo', // Parent slug (your main Klaro settings page)
        'Klaro Services',  // Page title
        'Services',         // Menu title
        'manage_options', // Capability
        'klaro-geo-services', // Menu slug
        'klaro_geo_services_page_content' // Callback function
    );
}
add_action( 'admin_menu', 'klaro_geo_settings_page' );


function klaro_geo_activate() {
    global $default_services;
    $defaults = get_klaro_default_values();

    // Clean up the default values
    $defaults['gtm_oninit'] = preg_replace('/\R+/', ' ', $defaults['gtm_oninit']);
    $defaults['gtm_onaccept'] = preg_replace('/\R+/', ' ', $defaults['gtm_onaccept']);
    $defaults['gtm_ondecline'] = preg_replace('/\R+/', ' ', $defaults['gtm_ondecline']);

    // Check if services already exist
    $existing_services = get_option('klaro_geo_services', '');

    // Only set default services if they don't already exist
    if (empty($existing_services)) {
        if (!isset($default_services) || empty($default_services)) {
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

        // Set default services only on first activation
        $encoded_services = wp_json_encode($default_services, JSON_PRETTY_PRINT);
        add_option('klaro_geo_services', $encoded_services);
    }

    // Set default GTM settings (only if they don't exist)
    add_option('klaro_geo_gtm_oninit', $defaults['gtm_oninit'], '', 'no');
    add_option('klaro_geo_gtm_onaccept', $defaults['gtm_onaccept']);
    add_option('klaro_geo_gtm_ondecline', $defaults['gtm_ondecline']);

    // Set default purposes (only if they don't exist)
    add_option('klaro_geo_ad_purposes', json_encode(['advertising']));
    add_option('klaro_geo_analytics_purposes', json_encode(['analytics']));

    // Set default geo settings
    add_option('klaro_geo_settings', wp_json_encode(klaro_geo_get_default_geo_settings()));

    // Set up the default templates
    $default_templates = klaro_geo_get_default_templates();
    add_option('klaro_geo_templates', $default_templates);

    // Other settings
    add_option('klaro_geo_js_version', '0.7');
    add_option('klaro_geo_debug_countries', ['US', 'UK', 'CA', 'FR', 'AU', 'US-CA', 'CA-QC']);
    add_option('klaro_geo_fallback_behavior','default');
    add_option('klaro_geo_enable_consent_receipts', false);
}



// Main settings page content
function klaro_geo_settings_page_content() {
    global $defaults;
    global $default_services;
    $defaults = get_klaro_default_values();

    // Get existing services or initialize with defaults
    $services_json = get_option('klaro_geo_services', '');
    if (empty($services_json)) {
        if (!isset($default_services) || empty($default_services)) {
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
    } else {
        $default_services = json_decode($services_json, true);
        if (!is_array($default_services)) {
            $default_services = [];
        }
    }
    ?>
    <div class="wrap">
        <h1>Klaro Geo Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'klaro_geo_settings_group' );
            do_settings_sections( 'klaro-geo' );
            ?>
            <table class="form-table">
            <tr valign="top">
                    <th scope="row"><label for="klaro_geo_fallback_behavior">Fallback Consent Behavior</label></th>
                    <td>
                        <select name="klaro_geo_fallback_behavior" id="klaro_geo_fallback_behavior">
                            <option value="opt-in" <?php selected( get_option('klaro_geo_fallback_behavior', 'opt-in'), 'opt-in' ); ?>>Opt-in</option>
                            <option value="opt-out" <?php selected( get_option('klaro_geo_fallback_behavior'), 'opt-out' ); ?>>Opt-out</option>
                            <option value="no-consent" <?php selected( get_option('klaro_geo_fallback_behavior'), 'no-consent' ); ?>>No Consent Required</option>
                            <option value="default" <?php selected( get_option('klaro_geo_fallback_behavior'), 'default' ); ?>>Default</option>
                        </select>
                        <p class="description">Select the default consent behavior when IP lookup fails.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_enable_consent_receipts">Enable Consent Receipts</label></th>
                    <td>
                        <input type="checkbox" name="klaro_geo_enable_consent_receipts" id="klaro_geo_enable_consent_receipts" value="1" <?php checked(get_option('klaro_geo_enable_consent_receipts', false)); ?> />
                        <p class="description">When enabled, consent choices will be pushed to the dataLayer for tracking and auditing purposes.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_js_version">Klaro JS Version</label></th>
                    <td>
                        <input type="text" name="klaro_geo_js_version" id="klaro_geo_js_version" value="<?php echo esc_attr( get_option('klaro_geo_js_version', '0.7') ); ?>" />
                        <p class="description">Enter the version number of Klaro.js to load (e.g., "0.7").</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_js_variant">Klaro Script Variant</label></th>
                    <td>
                        <select name="klaro_geo_js_variant" id="klaro_geo_js_variant">
                            <option value="klaro.js" <?php selected(get_option('klaro_geo_js_variant', 'klaro.js'), 'klaro.js'); ?>>Standard (klaro.js)</option>
                            <option value="klaro-no-css.js" <?php selected(get_option('klaro_geo_js_variant', 'klaro.js'), 'klaro-no-css.js'); ?>>No CSS (klaro-no-css.js)</option>
                        </select>
                        <p class="description">Choose which Klaro script variant to load. The "No CSS" option is useful if you want to use your own styles.</p>
                    </td>
                </tr>
            </table>

            <h2>Google Tag Manager</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_gtm_id">Google Tag Manager ID</label></th>
                    <td>
                        <input type="text" name="klaro_geo_gtm_id" id="klaro_geo_gtm_id" value="<?php echo esc_attr(get_option('klaro_geo_gtm_id', '')); ?>" placeholder="GTM-XXXXXX" />
                        <p class="description">Enter your Google Tag Manager container ID (e.g., GTM-XXXXXX). Leave empty to disable GTM integration.</p>
                    </td>
                </tr>
            </table>

            <h2>Consent Management Buttons</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_enable_floating_button">Enable Floating Consent Button</label></th>
                    <td>
                        <input type="checkbox" name="klaro_geo_enable_floating_button" id="klaro_geo_enable_floating_button" value="1" <?php checked(get_option('klaro_geo_enable_floating_button', true)); ?> />
                        <p class="description">When enabled, a floating "Manage Consent Settings" button will appear at the bottom right of your website. This button allows visitors to easily access and update their consent preferences at any time.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_button_text">Button Text</label></th>
                    <td>
                        <input type="text" name="klaro_geo_button_text" id="klaro_geo_button_text" value="<?php echo esc_attr(get_option('klaro_geo_button_text', 'Manage Consent Settings')); ?>" class="regular-text" />
                        <p class="description">Text to display on the consent button.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_button_theme">Button Theme</label></th>
                    <td>
                        <select name="klaro_geo_button_theme" id="klaro_geo_button_theme">
                            <option value="light" <?php selected(get_option('klaro_geo_button_theme', 'light'), 'light'); ?>>Light</option>
                            <option value="dark" <?php selected(get_option('klaro_geo_button_theme', 'dark'), 'dark'); ?>>Dark</option>
                            <option value="success" <?php selected(get_option('klaro_geo_button_theme', 'success'), 'success'); ?>>Success (Green)</option>
                        </select>
                        <p class="description">Choose the button style theme.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">WordPress Menu Integration</th>
                    <td>
                        <p><strong>How to add a consent button to your WordPress menu:</strong></p>
                        <ol>
                            <li>Go to <a href="<?php echo admin_url('nav-menus.php'); ?>">Appearance > Menus</a> in your WordPress admin</li>
                            <li>Create a new menu or edit an existing one</li>
                            <li>Look for the "Klaro Consent Button" box in the left sidebar (you may need to click "Screen Options" at the top and enable it)</li>
                            <li>Click "Add Consent Button" to add it to your menu</li>
                            <li>Save the menu</li>
                        </ol>
                        <p class="description">This will add a "Manage Consent Settings" button to your menu. The button will use the same text configured above.</p>
                    </td>
                </tr>
            </table>


            <h2>Advanced Consent Mode</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_analytics_purposes">Analytics Storage Purposes:</label></th>
                    <td>
                        <select name="klaro_geo_analytics_purposes[]" id="klaro_geo_analytics_purposes" multiple>  // Multi-select
                            <?php
                            $purposes = explode(',', get_option('klaro_geo_purposes', 'functional,analytics,advertising'));
                            $selected_analytics_purposes = get_option('klaro_geo_analytics_purposes', json_encode(['analytics'])); // Default to 'analytics'
                            $selected_analytics_purposes = is_array($selected_analytics_purposes) ? $selected_analytics_purposes : json_decode($selected_analytics_purposes, true);
                            foreach ($purposes as $purpose) {
                                echo '<option value="' . esc_attr($purpose) . '" ' . (in_array($purpose, $selected_analytics_purposes) ? 'selected' : '') . '>' . esc_html($purpose) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Select the purposes that will trigger analytics_storage consent updates.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_ad_purposes">Ad Storage, Personalization, and User Data Purposes:</label></th>
                    <td>
                        <select name="klaro_geo_ad_purposes[]" id="klaro_geo_ad_purposes" multiple>  // Multi-select
                            <?php
                            $selected_ad_purposes = get_option('klaro_geo_ad_purposes', json_encode(['advertising'])); // Default to 'advertising'
                            $selected_ad_purposes = is_array($selected_ad_purposes) ? $selected_ad_purposes : json_decode($selected_ad_purposes, true);
                            foreach ($purposes as $purpose) {
                                echo '<option value="' . esc_attr($purpose) . '" ' . (in_array($purpose, $selected_ad_purposes) ? 'selected' : '') . '>' . esc_html($purpose) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Select the purposes that will trigger ad_storage, ad_user_data, and ad_personalization consent updates.</p>
                    </td>
                </tr>
            </table>
            <h2>Purposes</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_purposes">Purposes (comma-separated):</label></th>
                    <td>
                        <input type="text" name="klaro_geo_purposes" id="klaro_geo_purposes" value="<?php echo esc_attr(get_option('klaro_geo_purposes', 'functional,analytics,advertising')); ?>" class="regular-text" />
                        <p class="description">Enter the purposes for which you use cookies, separated by commas.  These will be available for selection when configuring services.</p>
                    </td>
                </tr>
            </table>
            <h2>Debug Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_debug_countries">Debug Countries/Regions (comma-separated):</label></th>
                    <td>
                        <?php
                        $debug_countries = get_option('klaro_geo_debug_countries', ['US','UK','CA','FR','AU', 'US-CA', 'CA-QC']);
                        $debug_countries_str = is_array($debug_countries) ? implode(',', $debug_countries) : $debug_countries;
                        ?>
                        <input type="text" name="klaro_geo_debug_countries" id="klaro_geo_debug_countries" value="<?php echo esc_attr($debug_countries_str); ?>" class="regular-text" />
                        <p class="description">Enter two-digit country codes or hyphen-separated ISO 3166-2 region codes for debugging, separated by commas (e.g., US,UK,CA,FR,AU,US-CA,CA-QC).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Plugin Cleanup</th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="klaro_geo_cleanup_on_deactivate" 
                                   value="1" <?php checked(get_option('klaro_geo_cleanup_on_deactivate', false)); ?> />
                            Remove all plugin settings when deactivating
                        </label>
                        <p class="description">Warning: If enabled, all settings will be deleted when the plugin is deactivated.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// 2. Callback Function for the New Page
function klaro_geo_config_page_content() {
    ?>
    <div class="wrap">
        <h1>Klaro Configuration</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'klaro_geo_settings_group' );  // Use the same settings group
            do_settings_sections( 'klaro-geo-config' ); // New settings section
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_version">Klaro Version</label></th>
                    <td>
                        <input type="number" name="klaro_geo_version" id="klaro_geo_version" value="<?php echo esc_attr( get_option('klaro_geo_version', 1) ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_elementID">Element ID</label></th>
                    <td>
                        <input type="text" name="klaro_geo_elementID" id="klaro_geo_elementID" value="<?php echo esc_attr( get_option('klaro_geo_elementID', 'klaro') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Theme Color</th>
                    <td>
                        <select name="klaro_geo_theme_color" id="klaro_geo_theme_color">
                            <option value="light" <?php selected( get_option('klaro_geo_theme_color', 'light'), 'light' ); ?>>Light</option>
                            <option value="dark" <?php selected( get_option('klaro_geo_theme_color'), 'dark' ); ?>>Dark</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Theme Position</th>
                    <td>
                        <select name="klaro_geo_theme_position" id="klaro_geo_theme_position">
                            <option value="top" <?php selected( get_option('klaro_geo_theme_position', 'top'), 'top' ); ?>>Top</option>
                            <option value="bottom" <?php selected( get_option('klaro_geo_theme_position'), 'bottom' ); ?>>Bottom</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Theme Width</th>
                    <td>
                        <select name="klaro_geo_theme_width" id="klaro_geo_theme_width">
                            <option value="wide" <?php selected( get_option('klaro_geo_theme_width', 'wide'), 'wide' ); ?>>Wide</option>
                            <option value="narrow" <?php selected( get_option('klaro_geo_theme_width'), 'narrow' ); ?>>Narrow</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Consent Modal - Show Widget</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_consent_modal_show_widget" id="klaro_geo_consent_modal_show_widget" value="1" <?php checked( get_option('klaro_geo_consent_modal_show_widget', false) ); ?> /> Enable</label>
                        <p class="description">Show the "manage cookies" widget in the consent modal.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_title">Consent Modal Title</label></th>
                    <td>
                        <input type="text" name="klaro_geo_consent_modal_title" id="klaro_geo_consent_modal_title" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_title', 'Privacy Settings') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_description">Consent Modal Description</label></th>
                    <td>
                        <textarea name="klaro_geo_consent_modal_description" id="klaro_geo_consent_modal_description" rows="5" cols="50"><?php echo esc_textarea( get_option('klaro_geo_consent_modal_description') ); ?></textarea>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_accept_all_button_text">Consent Modal Accept All Button Text</label></th>
                    <td>
                        <input type="text" name="klaro_geo_consent_modal_accept_all_button_text" id="klaro_geo_consent_modal_accept_all_button_text" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_accept_all_button_text', 'Accept All') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_decline_all_button_text">Consent Modal Decline All Button Text</label></th>
                    <td>
                    <input type="text" name="klaro_geo_consent_modal_decline_all_button_text" id="klaro_geo_consent_modal_decline_all_button_text" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_decline_all_button_text', 'Decline All') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_learn_more_button_text">Consent Modal Learn More Button Text</label></th>
                    <td>
                    <input type="text" name="klaro_geo_consent_modal_learn_more_button_text" id="klaro_geo_consent_modal_learn_more_button_text" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_learn_more_button_text', 'Learn More') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_accept_button_text">Consent Modal Accept Button Text</label></th>
                    <td>
                    <input type="text" name="klaro_geo_consent_modal_accept_button_text" id="klaro_geo_consent_modal_accept_button_text" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_accept_button_text', 'Accept') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_consent_modal_decline_button_text">Consent Modal Decline Button Text</label></th>
                    <td>
                    <input type="text" name="klaro_geo_consent_modal_decline_button_text" id="klaro_geo_consent_modal_decline_button_text" value="<?php echo esc_attr( get_option('klaro_geo_consent_modal_decline_button_text', 'Decline') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">No Auto Load</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_noAutoLoad" id="klaro_geo_noAutoLoad" value="1" <?php checked( get_option('klaro_geo_noAutoLoad', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">HTML Texts</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_htmlTexts" id="klaro_geo_htmlTexts" value="1" <?php checked( get_option('klaro_geo_htmlTexts', true) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Embedded</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_embedded" id="klaro_geo_embedded" value="1" <?php checked( get_option('klaro_geo_embedded', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Group by Purpose</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_groupByPurpose" id="klaro_geo_groupByPurpose" value="1" <?php checked( get_option('klaro_geo_groupByPurpose', true) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Storage Method</th>
                    <td>
                        <select name="klaro_geo_storageMethod" id="klaro_geo_storageMethod">
                            <option value="cookie" <?php selected( get_option('klaro_geo_storageMethod', 'cookie'), 'cookie' ); ?>>Cookie</option>
                            <option value="localStorage" <?php selected( get_option('klaro_geo_storageMethod'), 'localStorage' ); ?>>Local Storage</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_cookieName">Cookie Name</label></th>
                    <td>
                        <input type="text" name="klaro_geo_cookieName" id="klaro_geo_cookieName" value="<?php echo esc_attr( get_option('klaro_geo_cookieName', 'klaro') ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_cookieExpiresAfterDays">Cookie Expires After Days</label></th>
                    <td>
                        <input type="number" name="klaro_geo_cookieExpiresAfterDays" id="klaro_geo_cookieExpiresAfterDays" value="<?php echo esc_attr( get_option('klaro_geo_cookieExpiresAfterDays', 365) ); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_cookieDomain">Cookie Domain</label></th>
                    <td>
                        <input type="text" name="klaro_geo_cookieDomain" id="klaro_geo_cookieDomain" value="<?php echo esc_attr( get_option('klaro_geo_cookieDomain') ); ?>" />
                        <p class="description">Enter the cookie domain (e.g., example.com). Leave blank for default behavior.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Default</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_default" id="klaro_geo_default" value="1" <?php checked( get_option('klaro_geo_default', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Must Consent</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_mustConsent" id="klaro_geo_mustConsent" value="1" <?php checked( get_option('klaro_geo_mustConsent', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Accept All</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_acceptAll" id="klaro_geo_acceptAll" value="1" <?php checked( get_option('klaro_geo_acceptAll', true) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Hide Decline All</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_hideDeclineAll" id="klaro_geo_hideDeclineAll" value="1" <?php checked( get_option('klaro_geo_hideDeclineAll', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Hide Learn More</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_hideLearnMore" id="klaro_geo_hideLearnMore" value="1" <?php checked( get_option('klaro_geo_hideLearnMore', false) ); ?> /> Enable</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Notice as Modal</th>
                    <td>
                        <label><input type="checkbox" name="klaro_geo_noticeAsModal" id="klaro_geo_noticeAsModal" value="1" <?php checked( get_option('klaro_geo_noticeAsModal', false) ); ?> /> Enable</label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Services Page Content

function klaro_geo_services_page_content() {
    $purposes = explode(',', get_option('klaro_geo_purposes', 'functional,analytics,advertising')); // Get available purposes from settings
    $services = klaro_geo_validate_services(); // Use our validation function
    klaro_geo_debug_log('Services page content - services: ' . print_r($services, true));
    wp_enqueue_script(
        'klaro-geo-services-js',
        plugin_dir_url(__FILE__) . '../js/klaro-geo-services-admin.js',  // Updated to use our new admin JS file
        ['jquery'],
        time(), // time as version for cache busting
        true
    );

    // Get templates for language detection
    $templates = get_option('klaro_geo_templates', array());

    // Ensure templates have translations
    foreach ($templates as $key => $template) {
        if (!isset($template['config']['translations'])) {
            $templates[$key]['config']['translations'] = array(
                'zz' => array(
                    'consentModal' => array(
                        'title' => 'Privacy Settings',
                        'description' => ''
                    ),
                    'acceptAll' => 'Accept All',
                    'declineAll' => 'Decline All'
                )
            );
        }
    }

    wp_localize_script(
        'klaro-geo-services-js',
        'klaroGeo',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('klaro_geo_nonce'),
            'purposes' => $purposes,
            'services' => $services ? $services : [],
            'templates' => $templates
        )
    );

    // Also make templates available globally with a unique timestamp to prevent caching issues
    wp_add_inline_script(
        'klaro-geo-services-js',
        'window.klaroTemplates = ' . wp_json_encode($templates) . ';' .
        'window.klaroTemplatesTimestamp = ' . time() . ';',
        'before'
    );
    ?>
    <div class="wrap">
        <h1>Klaro Services</h1>
        <?php // 3. Add Service Button ?>
        <button id="add-new-service" class="button button-primary">Add New Service</button>
        <?php // 4. Table for Existing Services ?>
        <table class="wp-list-table widefat fixed striped" id="klaro-services-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Required</th>
                    <th>Default</th>
                    <th>Purposes</th>
                    <th>Advanced</th>
                    <th>Cookies</th>
                    <th>Callbacks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="klaro-services-list">
                 <?php
                if (empty($services)) {

                    echo "<tr><td colspan='8'>No services configured yet.</td></tr>"; // Updated colspan for the new advanced column
                } else {
                    foreach ($services as $index => $service) {
                        klaro_geo_debug_log('Processing service in admin loop: ' . print_r($service, true));

                        $name = isset($service['name']) ? esc_html($service['name']) : "N/A";
                        $required = isset($service['required']) ? ($service['required'] ? 'Yes' : 'No') : "N/A";
                        $default = isset($service['default']) ? ($service['default'] ? 'Yes' : 'No') : "N/A";
                        
                        // Handle purposes (ensure it's an array)
                        $purposes = isset($service['purposes']) ? $service['purposes'] : array();
                        $purposes_str = !empty($purposes) && is_array($purposes) ? esc_html(implode(', ', $purposes)) : "N/A";

                        // Handle advanced settings
                        $advanced = array();
                        if (isset($service['optOut']) && $service['optOut']) $advanced[] = 'Opt-Out';
                        if (isset($service['onlyOnce']) && $service['onlyOnce']) $advanced[] = 'Only Once';
                        if (isset($service['contextualConsentOnly']) && $service['contextualConsentOnly']) $advanced[] = 'Contextual Only';
                        $advanced_str = !empty($advanced) ? implode(', ', $advanced) : 'None';

                        // Handle cookies
                        $cookies = isset($service['cookies']) ? $service['cookies'] : array();
                        $cookies_str = !empty($cookies) && is_array($cookies) ? 
                            esc_html(json_encode($cookies)) : "N/A";
                        // Check for callbacks
                        $has_oninit = !empty($service['onInit']);
                        $has_onaccept = !empty($service['onAccept']);
                        $has_ondecline = !empty($service['onDecline']);
                        $callbacks = [];
                        if ($has_oninit) $callbacks[] = 'onInit';
                        if ($has_onaccept) $callbacks[] = 'onAccept';
                        if ($has_ondecline) $callbacks[] = 'onDecline';
                        $callbacks_str = !empty($callbacks) ? implode(', ', $callbacks) : 'None';

                        echo "<tr>";
                        echo "<td>" . $name . "</td>"; //Corrected to $name
                        echo "<td>" . $required . "</td>";
                        echo "<td>" . $default . "</td>";
                        echo "<td>" . $purposes_str . "</td>";
                        echo "<td>" . $advanced_str . "</td>";
                        echo "<td>" . $cookies_str . "</td>";
                        echo "<td>" . $callbacks_str . "</td>";
                        echo "<td>";
                        echo "<button class='edit-service button button-secondary' data-index='$index'>Edit</button> "; // Edit button
                        echo "<button class='delete-service button button-danger' data-index='$index'>Delete</button>"; // Delete button
                        echo "</td>";
                        echo "</tr>";
                    }
                }
                ?>



            </tbody>
        </table>



        <?php // 5. Form for Add/Edit Service (Hidden initially) ?>
        <div id="service-form-container" style="display: none;">
            <h2>Add/Edit Service</h2>
            <form id="service-form"> <input type="hidden" name="action" value="save_klaro_services">
                <input type="hidden" name="service_index" id="service_index">
                <label for="service_name">Name:</label><br>
                <input type="text" id="service_name" name="service_name" required><br>
                <label for="service_required">Required:</label><br>
                <select id="service_required" name="service_required">
                    <option value="global">Use Global Setting</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                </select>
                <p class="description">When set to "Yes", users cannot decline this service. Only use for essential services that are required for your website to function.</p><br>

                <label for="service_default">Default:</label><br>
                <select id="service_default" name="service_default">
                    <option value="global">Use Global Setting</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                </select>
                <p class="description">When set to "Yes", this service will be activated by default (opt-out). Use with caution as this may not be compliant with privacy regulations in some regions.</p><br>
                <label for="service_purposes">Purposes:</label><br>
                <div id="service_purposes_container"></div>
                <label for="service_cookies">Cookies:</label><br>
                <div id="service_cookies_container0"></div>

                <h3>Advanced Settings</h3>
                <div class="advanced-settings">
                    <label for="service_optout">
                        <input type="checkbox" id="service_optout" name="service_optout" value="1">
                        Opt-Out
                    </label>
                    <p class="description">If enabled, this service will be loaded even before the user gives explicit consent. <strong>We strongly advise against this</strong> as it may violate privacy regulations.</p>

                    <label for="service_onlyonce">
                        <input type="checkbox" id="service_onlyonce" name="service_onlyonce" value="1">
                        Only Once
                    </label>
                    <p class="description">If enabled, the service will only be executed once regardless of how often the user toggles it on and off. This is useful for tracking scripts that would generate new page view events every time they are re-enabled.</p>

                    <label for="service_contextual">
                        <input type="checkbox" id="service_contextual" name="service_contextual" value="1">
                        Contextual Consent Only
                    </label>
                    <p class="description">If enabled, this service will only be shown in the consent modal when it's actually used on the page (e.g., embedded YouTube videos).</p>
                </div>

                <h3>Callback Scripts</h3>
                <div class="callback-scripts">
                    <label for="service_oninit">onInit Script:</label><br>
                    <textarea id="service_oninit" name="service_oninit" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when Klaro initializes this service.</p>

                    <label for="service_onaccept">onAccept Script:</label><br>
                    <textarea id="service_onaccept" name="service_onaccept" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when the user accepts this service.</p>

                    <label for="service_ondecline">onDecline Script:</label><br>
                    <textarea id="service_ondecline" name="service_ondecline" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when the user declines this service.</p>
                </div>

                <h3>Service Translations</h3>
                <div class="translations">
                    <p class="description">Configure translations for this service. The "zz" language code is used as a fallback for any missing translations.</p>
                    <p class="description"><strong>Note:</strong> Languages are automatically pulled from the Templates section. To add new languages, please add them in the Templates page first.</p>

                    <div id="service-translations-tabs" class="translations-container">
                        <ul class="translations-tabs-nav">
                            <li><a href="#service-tab-zz">Fallback</a></li>
                            <!-- Additional language tabs will be dynamically added based on templates -->
                        </ul>

                        <div id="service-tab-zz" class="translation-tab">
                            <h4>Fallback Translations (zz)</h4>
                            <p class="description">These translations will be used when a specific language translation is not available.</p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_title" name="service_translations[zz][title]" class="regular-text">
                                        <p class="description">The name of the service as displayed to users.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <textarea id="service_translations_zz_description" name="service_translations[zz][description]" rows="4" cols="50" class="large-text"></textarea>
                                        <p class="description">A description of what this service does and what data it collects.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Opt-Out Message</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_optOut_title" name="service_translations[zz][optOut][title]" value="(opt-out)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_optOut_description" name="service_translations[zz][optOut][description]" value="This services is loaded by default (but you can opt out)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Required Message</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_required_title" name="service_translations[zz][required][title]" value="(always required)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_required_description" name="service_translations[zz][required][description]" value="This services is always required" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Purposes</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Purpose (singular)</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_purpose" name="service_translations[zz][purpose]" value="purpose" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Purposes (plural)</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_purposes" name="service_translations[zz][purposes]" value="purposes" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Additional language tabs will be dynamically added here -->
                    </div>
                </div>

                <br> <button type="submit" class="button button-primary">Save Service</button>
                <button type="button" class="button button-primary add-cookie-group" data-index="0">Add Cookie Group</button> </form>
        </div>
    </div>
    <?php
}


// Register settings
function klaro_geo_register_settings() {
    // Template settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_templates');

    // Country and region settings
    register_setting('klaro_geo_country_settings_group', 'klaro_geo_country_settings');
    
    // Service and purpose settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_services');
    register_setting('klaro_geo_settings_group', 'klaro_geo_analytics_purposes');
    register_setting('klaro_geo_settings_group', 'klaro_geo_ad_purposes');
    
    // GTM settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_gtm_id', [
        'type' => 'string',
        'sanitize_callback' => function($input) {
            // Validate GTM ID format (GTM-XXXXXX)
            if (empty($input) || preg_match('/^GTM-[A-Z0-9]+$/', $input)) {
                return $input;
            }
            return '';
        }
    ]);

    // Other settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_js_version');
    register_setting('klaro_geo_settings_group', 'klaro_geo_js_variant');
    register_setting('klaro_geo_settings_group', 'klaro_geo_fallback_behavior');
    register_setting('klaro_geo_settings_group', 'klaro_geo_debug_countries');
    register_setting('klaro_geo_settings_group', 'klaro_geo_enable_consent_receipts');
    register_setting('klaro_geo_settings_group', 'klaro_geo_cleanup_on_deactivate');
}
add_action('admin_init', 'klaro_geo_register_settings');

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
        
        // Clean up any legacy GTM settings
        delete_option('klaro_geo_gtm_oninit');
        delete_option('klaro_geo_gtm_onaccept');
        delete_option('klaro_geo_gtm_ondecline');
        
        // Other settings
        delete_option('klaro_geo_js_version');
        delete_option('klaro_geo_js_variant');
        delete_option('klaro_geo_fallback_behavior');
        delete_option('klaro_geo_debug_countries');
        delete_option('klaro_geo_enable_consent_receipts');
        delete_option('klaro_geo_cleanup_on_deactivate');
    }
}


add_action('wp_ajax_save_klaro_services', 'klaro_geo_save_services');

function klaro_geo_save_services(){
    klaro_geo_debug_log('$_POST: ' . print_r($_POST, true));

    if (isset($_POST['services'])) {

        // Use wp_unslash() to remove escaping, only if necessary.
        $services = json_decode(wp_unslash($_POST['services']), true);


        if (json_last_error() === JSON_ERROR_NONE && is_array($services)) {
            // Save the services array to the database
            update_option('klaro_geo_services', wp_json_encode($services));
            wp_send_json_success();
        } else {
            wp_send_json_error('Invalid services JSON or not an array');
        }
        klaro_geo_debug_log('Saved services: ' . print_r($services, true)); // Log saved services
    } else {
        wp_send_json_error('No services data provided');
   }
   wp_die(); 
}


add_action('wp_ajax_delete_klaro_service', 'klaro_geo_delete_service');

function klaro_geo_delete_service() {

    $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
    if ($index < 0) {
        wp_send_json_error(['message' => 'Invalid service index.']);
        wp_die(); // Always wp_die() after wp_send_json_error()
    }

    // Get the existing services and decode once using wp_unslash() on the raw POST data
    $services_json = get_option('klaro_geo_services', '[]');
    $services = json_decode($services_json, true);

    if (is_array($services) && isset($services[$index])) {
        array_splice($services, $index, 1); // Remove the service

        $updated_services_json = wp_json_encode($services);  // No wp_slash() needed!
        update_option('klaro_geo_services', $updated_services_json); //Update DB

        wp_send_json_success($services);  // Send the updated services data
    } else {
        wp_send_json_error(['message' => 'Service not found or invalid services data.']);
    }
    wp_die();
}



// Add AJAX handler for template creation
add_action('wp_ajax_create_klaro_template', 'klaro_geo_create_template');

function klaro_geo_create_template() {
    check_ajax_referer('klaro_geo_template_nonce', 'nonce');
    
    $template_name = sanitize_text_field($_POST['template_name']);
    $inherit_from = sanitize_text_field($_POST['inherit_from']);
    
    // Get existing templates
    $templates = get_option('klaro_geo_templates', array());
    
    // Check if template name already exists
    foreach ($templates as $template) {
        if (strcasecmp($template['name'], $template_name) === 0) {
            wp_send_json_error(array(
                'message' => 'A template with this name already exists. Please choose a different name.'
            ));
            return;
        }
    }
    
    // Generate a unique key for the template
    $template_key = sanitize_title($template_name);
    $original_key = $template_key;
    $counter = 1;
    while (isset($templates[$template_key])) {
        $template_key = $original_key . '-' . $counter;
        $counter++;
    }
    
    // Validate inherited template exists
    if (!isset($templates[$inherit_from])) {
        wp_send_json_error(array(
            'message' => 'Selected template to copy from does not exist.'
        ));
        return;
    }
    
    // Copy settings from inherited template
    $inherited_config = $templates[$inherit_from]['config'];
    
    // Create new template
    $templates[$template_key] = array(
        'name' => $template_name,
        'config' => $inherited_config
    );
    
    // Save updated templates
    update_option('klaro_geo_templates', $templates);
    
    wp_send_json_success(array(
        'template_key' => $template_key,
        'message' => 'Template created successfully.'
    ));
}



function klaro_geo_templates_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Ensure default template exists and has the correct name
    klaro_geo_create_templates();

    // Enqueue script to handle template translations
    wp_enqueue_script(
        'klaro-geo-template-translations',
        plugins_url('../js/klaro-geo-template-translations.js', __FILE__),
        array('jquery', 'jquery-ui-tabs'),
        time(), // Use time for cache busting during development
        true
    );

    // Add custom CSS for the template translations
    wp_add_inline_style('wp-admin', '
        .translation-json-buttons {
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .translation-json-buttons .button {
            margin-right: 10px;
        }
        #translations_json_editor {
            font-family: monospace;
            resize: vertical;
        }
        .translations-container {
            margin-bottom: 20px;
        }
    ');

    // Get templates for JavaScript
    $templates = get_option('klaro_geo_templates', array());

    // Pass templates to JavaScript
    wp_localize_script(
        'klaro-geo-template-translations',
        'klaroGeoTemplates',
        array(
            'templates' => $templates,
            'timestamp' => time() // Add timestamp to prevent caching issues
        )
    );

    // Save template changes
    if (isset($_POST['submit_template'])) {
        check_admin_referer('klaro_geo_template_nonce');

        $templates = get_option('klaro_geo_templates', array());
        $current_template = sanitize_text_field($_POST['current_template']);

        // Get the template configuration from POST data
        if (isset($_POST['template_config']) && is_array($_POST['template_config'])) {
            $template_config = array();

            // Process translations_json if it exists
            if (isset($_POST['template_config']['translations_json'])) {
                $translations_json = $_POST['template_config']['translations_json'];

                // Try to decode it to use in the config
                $decoded_translations = json_decode($translations_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_translations)) {
                    // If valid JSON, use it for the translations
                    $template_config['translations'] = $decoded_translations;
                } else {
                    // If invalid JSON, log an error and use the form data instead
                    klaro_geo_debug_log('Invalid JSON in translations_json: ' . json_last_error_msg());

                    // If we have translations in the form, use those
                    if (isset($_POST['template_config']['translations']) && is_array($_POST['template_config']['translations'])) {
                        $template_config['translations'] = $_POST['template_config']['translations'];
                    }
                }

                // Remove from the array so we don't process it again below
                unset($_POST['template_config']['translations_json']);
            }

            // Sanitize the rest of the config values
            foreach ($_POST['template_config'] as $key => $value) {
                // Skip translations_json as we already processed it
                if ($key === 'translations_json') continue;

                if (is_array($value)) {
                    // Handle nested arrays (like styling)
                    $template_config[$key] = array_map('sanitize_text_field', $value);
                } else {
                    // Handle simple values
                    $template_config[$key] = sanitize_text_field($value);
                }
            }

            // Update the template configuration
            $templates[$current_template]['config'] = $template_config;
        }

        // Get WordPress settings
        if (isset($_POST['wordpress_settings']) && is_array($_POST['wordpress_settings'])) {
            $wordpress_settings = array();

            // Process enable_consent_logging setting
            $wordpress_settings['enable_consent_logging'] = isset($_POST['wordpress_settings']['enable_consent_logging']);

            // Update the WordPress settings
            $templates[$current_template]['wordpress_settings'] = $wordpress_settings;
        } else {
            // Set default WordPress settings if not provided
            $templates[$current_template]['wordpress_settings'] = array(
                'enable_consent_logging' => true
            );
        }

        update_option('klaro_geo_templates', $templates);

        // Update inheritance
        if (isset($_POST['inherit_from'])) {
            $templates[$current_template]['inherit_from'] = sanitize_text_field($_POST['inherit_from']);
        }

        update_option('klaro_geo_templates', $templates);
        // Add success message
        add_settings_error(
            'klaro_geo_templates',
            'templates_updated',
            'Template settings saved successfully.',
            'updated'
        );
        wp_redirect(add_query_arg('template', $current_template));
    }

    // Get current templates
    $templates = get_option('klaro_geo_templates', array());
    $current_template = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'default';
    
    ?>
    <div class="wrap">
        <h1>Klaro Templates</h1>
        <?php settings_errors('klaro_geo_templates'); ?>
        <form method="post" action="">
            <?php wp_nonce_field('klaro_geo_template_nonce'); ?>
            
            <select name="current_template" id="template_selector">
                <?php
                // Ensure default template is always first in the list
                if (isset($templates['default'])) {
                    $default_name = isset($templates['default']['name']) ? $templates['default']['name'] : 'Default Template';
                    ?>
                    <option value="default" <?php selected($current_template, 'default'); ?>>
                        <?php echo esc_html($default_name); ?>
                    </option>
                    <?php
                }

                // Add all other templates
                foreach ($templates as $key => $template) :
                    if ($key === 'default') continue; // Skip default as we already added it
                ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_template, $key); ?>>
                        <?php echo esc_html($template['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" id="add_template" class="button">Add New Template</button>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle template selection change
                $('#template_selector').on('change', function() {
                    var selectedTemplate = $(this).val();
                    window.location.href = '<?php echo admin_url('admin.php?page=klaro-geo-templates'); ?>&template=' + selectedTemplate;
                });
            });
            </script>

            <div id="template_creation_container">
                     <?php 
                    // Add nonce for AJAX
                    wp_nonce_field('klaro_geo_template_nonce', 'klaro_geo_template_nonce');
                    ?>
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#add_template').click(function() {
                            // Hide the main template config temporarily
                            $('#template_config').hide();
                            
                            // Show the new template form
                            var newTemplateForm = $('<div id="new_template_form" class="template-creation-form">' +
                                '<h3>Create New Template</h3>' +
                                '<table class="form-table">' +
                                '<tr>' +
                                '<th><label for="new_template_name">Template Name:</label></th>' +
                                '<td><input type="text" id="new_template_name" name="new_template_name" required></td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th><label for="inherit_from_template">Copy Settings From:</label></th>' +
                                '<td><select id="inherit_from_template" name="inherit_from_template">' +
                                $('#template_selector').html() + // Reuse existing templates list
                                '</select></td>' +
                                '</tr>' +
                                '</table>' +
                                '<div class="template-creation-buttons">' +
                                '<button type="button" class="button button-primary" id="create_template">Create Template</button>' +
                                '<button type="button" class="button" id="cancel_template">Cancel</button>' +
                                '</div>' +
                                '</div>');
                            
                            // Insert the form after the Add Template button
                            $(this).after(newTemplateForm);
                            $(this).hide();
                            
                            // Handle Cancel
                            $('#cancel_template').click(function() {
                                $('#new_template_form').remove();
                                $('#add_template').show();
                                $('#template_config').show();
                            });
                            
                            // Handle Create
                            $('#create_template').click(function() {
                                var templateName = $('#new_template_name').val();
                                var inheritFrom = $('#inherit_from_template').val();
                                
                                if (!templateName) {
                                    alert('Please enter a template name');
                                    return;
                                }
                                
                                // Create new template by copying selected template's settings
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'create_klaro_template',
                                        template_name: templateName,
                                        inherit_from: inheritFrom,
                                        nonce: $('#klaro_geo_template_nonce').val()
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            // Add new template to selector and select it
                                            var option = new Option(templateName, response.data.template_key);
                                            $('#template_selector').append(option);
                                            $('#template_selector').val(response.data.template_key).trigger('change');
                                            
                                            // Clean up creation form
                                            $('#new_template_form').remove();
                                            $('#add_template').show();
                                            $('#template_config').show();
                                        } else {
                                            alert(response.data.message);
                                        }
                                    },
                                    error: function() {
                                        alert('Error creating template. Please try again.');
                                    }
                                });
                            });
                        });
                    });
                    </script>
                    <?php 
                     $current_config = $templates[$current_template]['config'];
                     ?>


            <div id="template_settings">
                <h2>Template Settings</h2>
                <div id="template_configuration">
                <?php 
                    $current_config = $templates[$current_template]['config'];
                    ?>
                    <h3>Basic Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Version</th>
                            <td>
                                <input type="number" name="template_config[version]" 
                                       value="<?php echo esc_attr($current_config['version'] ?? 1); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Element ID</th>
                            <td>
                                <input type="text" name="template_config[elementID]" 
                                       value="<?php echo esc_attr($current_config['elementID'] ?? 'klaro'); ?>">
                            </td>
                        </tr>
                    </table>

                    <h3>Styling</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Theme Color</th>
                            <td>
                                <select name="template_config[styling][theme][color]">
                                    <option value="light" <?php selected($current_config['styling']['theme']['color'] ?? 'light', 'light'); ?>>Light</option>
                                    <option value="dark" <?php selected($current_config['styling']['theme']['color'] ?? 'light', 'dark'); ?>>Dark</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Position</th>
                            <td>
                                <select name="template_config[styling][theme][position]">
                                    <option value="top" <?php selected($current_config['styling']['theme']['position'] ?? 'top', 'top'); ?>>Top</option>
                                    <option value="bottom" <?php selected($current_config['styling']['theme']['position'] ?? 'top', 'bottom'); ?>>Bottom</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <h3>Behavior Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Default</th>
                            <td>
                                <input type="checkbox" name="template_config[default]" value="1"
                                       <?php checked($current_config['default'] ?? false); ?>>
                                <p class="description">When enabled, services will be activated by default (opt-out). This setting can also be overridden per-service.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Required</th>
                            <td>
                                <input type="checkbox" name="template_config[required]" value="1"
                                       <?php checked($current_config['required'] ?? false); ?>>
                                <p class="description">When enabled, users cannot decline services. Only use for essential services that are required for your website to function. This setting can also be overridden per-service.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">No Auto Load</th>
                            <td>
                                <input type="checkbox" name="template_config[noAutoLoad]" value="1"
                                       <?php checked($current_config['noAutoLoad'] ?? false); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">HTML Texts</th>
                            <td>
                                <input type="checkbox" name="template_config[htmlTexts]" value="1"
                                       <?php checked($current_config['htmlTexts'] ?? true); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Group By Purpose</th>
                            <td>
                                <input type="checkbox" name="template_config[groupByPurpose]" value="1"
                                       <?php checked($current_config['groupByPurpose'] ?? true); ?>>
                            </td>
                        </tr>
                    </table>

                    <h3>WordPress Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Consent Logging</th>
                            <td>
                                <input type="checkbox" name="wordpress_settings[enable_consent_logging]" value="1"
                                       <?php checked($templates[$current_template]['wordpress_settings']['enable_consent_logging'] ?? true); ?>>
                                <p class="description">When enabled, consent choices will be logged to the WordPress database. Disable this for countries that don't allow tracking consent with unique IDs (e.g., Finland). Note: This setting only has an effect if consent receipts are enabled in the main settings.</p>
                            </td>
                        </tr>
                    </table>

                    <h3>Cookie Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Storage Method</th>
                            <td>
                                <select name="template_config[storageMethod]">
                                    <option value="cookie" <?php selected($current_config['storageMethod'] ?? 'cookie', 'cookie'); ?>>Cookie</option>
                                    <option value="localStorage" <?php selected($current_config['storageMethod'] ?? 'cookie', 'localStorage'); ?>>Local Storage</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cookie Name</th>
                            <td>
                                <input type="text" name="template_config[cookieName]" 
                                       value="<?php echo esc_attr($current_config['cookieName'] ?? 'klaro'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cookie Expires (days)</th>
                            <td>
                                <input type="number" name="template_config[cookieExpiresAfterDays]" 
                                       value="<?php echo esc_attr($current_config['cookieExpiresAfterDays'] ?? 365); ?>">
                            </td>
                        </tr>
                    </table>

                    <h3>Consent Modal Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Must Consent</th>
                            <td>
                                <input type="checkbox" name="template_config[mustConsent]" value="1" 
                                       <?php checked($current_config['mustConsent'] ?? false); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Accept All</th>
                            <td>
                                <input type="checkbox" name="template_config[acceptAll]" value="1" 
                                       <?php checked($current_config['acceptAll'] ?? true); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Hide Decline All</th>
                            <td>
                                <input type="checkbox" name="template_config[hideDeclineAll]" value="1" 
                                       <?php checked($current_config['hideDeclineAll'] ?? false); ?>>
                            </td>
                        </tr>
                    </table>

<!-- Basic Text Settings section removed -->

                    <h3>Translations</h3>
                    <p class="description">
                        Configure translations for multiple languages. The "zz" language code is used as a fallback for any missing translations.
                    </p>

                    <div id="translations-tabs" class="translations-container">
                        <ul class="translations-tabs-nav">
                            <li><a href="#tab-zz">Fallback</a></li>
                            <li><a href="#tab-add">+ Add Language</a></li>
                        </ul>

                        <div id="tab-zz" class="translation-tab">
                            <h4>Fallback Translations</h4>
                            <p class="description">These translations will be used when a specific language translation is not available.</p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Privacy Policy URL</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicyUrl]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['privacyPolicyUrl'] ?? '/privacy'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Accept All</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][acceptAll]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['acceptAll'] ?? 'Accept all'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Accept Selected</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][acceptSelected]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['acceptSelected'] ?? 'Accept selected'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Close</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][close]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['close'] ?? 'Close'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Decline</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][decline]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['decline'] ?? 'I decline'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">OK</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][ok]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['ok'] ?? 'That\'s ok'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Powered By</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][poweredBy]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['poweredBy'] ?? 'Realized with Klaro!'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Save</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][save]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['save'] ?? 'Save'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Consent Modal</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentModal][title]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['consentModal']['title'] ?? 'Services we would like to use'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <textarea name="template_config[translations][zz][consentModal][description]" rows="4" cols="50" class="large-text"><?php
                                            echo esc_textarea($current_config['translations']['zz']['consentModal']['description'] ?? 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.');
                                        ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Consent Notice</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][title]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['consentNotice']['title'] ?? 'Cookie Consent'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <textarea name="template_config[translations][zz][consentNotice][description]" rows="4" cols="50" class="large-text"><?php
                                            echo esc_textarea($current_config['translations']['zz']['consentNotice']['description'] ?? 'Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.');
                                        ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Change Description</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][changeDescription]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['consentNotice']['changeDescription'] ?? 'There were changes since your last visit, please renew your consent.'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Learn More</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][learnMore]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['consentNotice']['learnMore'] ?? 'Let me choose'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Privacy Policy</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Name</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicy][name]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['privacyPolicy']['name'] ?? 'privacy policy'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Text</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicy][text]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['privacyPolicy']['text'] ?? 'To learn more, please read our {privacyPolicy}.'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Contextual Consent</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Accept Always</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][contextualConsent][acceptAlways]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['contextualConsent']['acceptAlways'] ?? 'Always'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Accept Once</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][contextualConsent][acceptOnce]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['contextualConsent']['acceptOnce'] ?? 'Yes'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][contextualConsent][description]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['contextualConsent']['description'] ?? 'Do you want to load external content supplied by {title}?'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description Empty Store</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][contextualConsent][descriptionEmptyStore]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['contextualConsent']['descriptionEmptyStore'] ?? 'To agree to this service permanently, you must accept {title} in the {link}.'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Modal Link Text</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][contextualConsent][modalLinkText]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['contextualConsent']['modalLinkText'] ?? 'Consent Manager'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Services</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Service (singular)</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][purposeItem][service]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['purposeItem']['service'] ?? 'service'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Services (plural)</th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][purposeItem][services]"
                                            value="<?php echo esc_attr($current_config['translations']['zz']['purposeItem']['services'] ?? 'services'); ?>" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Purposes</h4></th>
                                </tr>

                                <?php
                                // Get the purposes from the main settings
                                $purposes = explode(',', get_option('klaro_geo_purposes', 'functional,analytics,advertising'));

                                // Loop through each purpose and create form fields
                                foreach ($purposes as $purpose) {
                                    $purpose_key = sanitize_key($purpose);
                                    $purpose_title = ucfirst($purpose);

                                    // Default descriptions based on purpose
                                    $default_description = '';
                                    switch ($purpose_key) {
                                        case 'functional':
                                            $default_description = 'These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise.';
                                            break;
                                        case 'analytics':
                                            $default_description = 'These services process personal information to help us understand how visitors interact with the website.';
                                            break;
                                        case 'advertising':
                                            $default_description = 'These services process personal information to show you personalized or interest-based advertisements.';
                                            break;
                                        default:
                                            $default_description = 'These services process personal information for ' . $purpose . ' purposes.';
                                    }

                                    // Title field
                                    ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html($purpose_title); ?> Title</th>
                                        <td>
                                            <input type="text"
                                                name="template_config[translations][zz][purposes][<?php echo esc_attr($purpose_key); ?>][title]"
                                                value="<?php echo esc_attr($current_config['translations']['zz']['purposes'][$purpose_key]['title'] ?? $purpose_title); ?>"
                                                class="regular-text">
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php echo esc_html($purpose_title); ?> Description</th>
                                        <td>
                                            <textarea
                                                name="template_config[translations][zz][purposes][<?php echo esc_attr($purpose_key); ?>][description]"
                                                rows="3"
                                                cols="50"
                                                class="large-text"><?php
                                                echo esc_textarea($current_config['translations']['zz']['purposes'][$purpose_key]['description'] ?? $default_description);
                                            ?></textarea>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>



                        <div id="tab-add" class="translation-tab">
                            <h4>Add New Language</h4>
                            <p>Select a language code to add:</p>
                            <select id="new-language-code">
                                <option value="en">English (en)</option>
                                <option value="de">German (de)</option>
                                <option value="fr">French (fr)</option>
                                <option value="es">Spanish (es)</option>
                                <option value="it">Italian (it)</option>
                                <option value="nl">Dutch (nl)</option>
                                <option value="pt">Portuguese (pt)</option>
                                <option value="custom">Custom...</option>
                            </select>
                            <div id="custom-language-code" style="display:none; margin-top: 10px;">
                                <label for="custom-code">Enter custom language code:</label>
                                <input type="text" id="custom-code" placeholder="e.g., ja, zh, ru" maxlength="5">
                            </div>
                            <button type="button" id="add-language-btn" class="button button-secondary" style="margin-top: 10px;">Add Language</button>
                        </div>
                    </div>

                    <h3>Translations JSON</h3>
                    <p class="description">
                        Edit translations directly in JSON format or copy/paste between templates.
                        <a href="https://kiprotect.github.io/klaro/annotated/translations/" target="_blank">See documentation</a> for examples.
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Translations JSON</th>
                            <td>
                                <textarea id="translations_json_editor" name="template_config[translations_json]" rows="15" cols="80" class="large-text code"><?php
                                    // Always convert the existing translations to JSON
                                    if (isset($current_config['translations']) && is_array($current_config['translations'])) {
                                        echo esc_textarea(wp_json_encode($current_config['translations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                    } else {
                                        echo '{}';
                                    }
                                ?></textarea>
                                <div class="translation-json-buttons">
                                    <button type="button" id="update_from_json" class="button">Update Form from JSON</button>
                                    <button type="button" id="update_to_json" class="button">Update JSON from Form</button>
                                </div>
                                <p class="description">
                                    Changes in the form fields will automatically update this JSON. You can also paste JSON here and click "Update Form from JSON" to update the form fields.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <script>
                    jQuery(document).ready(function($) {
                        // Initialize tabs
                        $("#translations-tabs").tabs();

                        // Load existing languages from the template
                        function loadExistingLanguages() {
                            <?php
                            // Get current template
                            $current_template = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'default';
                            $templates = get_option('klaro_geo_templates', array());

                            if (isset($templates[$current_template]) &&
                                isset($templates[$current_template]['config']) &&
                                isset($templates[$current_template]['config']['translations'])) {

                                $translations = $templates[$current_template]['config']['translations'];

                                // Output JavaScript to add tabs for each language
                                foreach ($translations as $lang_code => $translation) {
                                    // Skip fallback language as it already exists
                                    if ($lang_code === 'zz') continue;

                                    // Get language name
                                    $lang_names = array(
                                        'en' => 'English',
                                        'de' => 'German',
                                        'fr' => 'French',
                                        'es' => 'Spanish',
                                        'it' => 'Italian',
                                        'nl' => 'Dutch',
                                        'pt' => 'Portuguese'
                                    );

                                    $lang_name = isset($lang_names[$lang_code]) ? $lang_names[$lang_code] : 'Custom';

                                    echo "addLanguageTab('$lang_code', '$lang_name');\n";
                                }
                            }
                            ?>
                        }

                        // Function to add a language tab
                        function addLanguageTab(langCode, langName) {
                            // Check if this language already exists
                            if ($("#tab-" + langCode).length > 0) {
                                return; // Tab already exists
                            }

                            // Add new tab
                            var newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
                            newTab.insertBefore($(".translations-tabs-nav li:last"));

                            // Clone the fallback tab content as a starting point
                            var newContent = $("#tab-zz").clone();
                            newContent.attr('id', 'tab-' + langCode);

                            // Add delete button to the heading
                            var heading = newContent.find('h4');
                            heading.text(langName + ' Translations (' + langCode + ')');
                            heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

                            // Update all input names to use the new language code
                            newContent.find('input, textarea').each(function() {
                                var name = $(this).attr('name');
                                name = name.replace('[zz]', '[' + langCode + ']');
                                $(this).attr('name', name);
                            });

                            // Add the new tab content
                            newContent.appendTo("#translations-tabs");

                            // Refresh tabs
                            $("#translations-tabs").tabs("refresh");
                        }

                        // Make the function available globally
                        window.addLanguageTab = addLanguageTab;

                        // Load existing languages
                        loadExistingLanguages();

                        // Handle custom language code selection
                        $("#new-language-code").change(function() {
                            if ($(this).val() === 'custom') {
                                $("#custom-language-code").show();
                            } else {
                                $("#custom-language-code").hide();
                            }
                        });

                        // Handle adding a new language
                        $("#add-language-btn").click(function() {
                            var langCode;
                            if ($("#new-language-code").val() === 'custom') {
                                langCode = $("#custom-code").val().trim().toLowerCase();
                                if (!langCode || langCode.length > 5) {
                                    alert("Please enter a valid language code (1-5 characters)");
                                    return;
                                }
                            } else {
                                langCode = $("#new-language-code").val();
                            }

                            // Check if this language already exists
                            if ($("#tab-" + langCode).length > 0) {
                                alert("This language is already added");
                                return;
                            }

                            // Get language name
                            var langName;
                            switch(langCode) {
                                case 'en': langName = 'English'; break;
                                case 'de': langName = 'German'; break;
                                case 'fr': langName = 'French'; break;
                                case 'es': langName = 'Spanish'; break;
                                case 'it': langName = 'Italian'; break;
                                case 'nl': langName = 'Dutch'; break;
                                case 'pt': langName = 'Portuguese'; break;
                                default: langName = 'Custom'; break;
                            }

                            // Add new tab with delete button
                            var newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
                            newTab.insertBefore($(".translations-tabs-nav li:last"));

                            // Clone the fallback tab content as a starting point
                            var newContent = $("#tab-zz").clone();
                            newContent.attr('id', 'tab-' + langCode);

                            // Add delete button to the heading
                            var heading = newContent.find('h4');
                            heading.text(langName + ' Translations (' + langCode + ')');
                            heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

                            // Update all input names to use the new language code
                            newContent.find('input, textarea').each(function() {
                                var name = $(this).attr('name');
                                name = name.replace('[zz]', '[' + langCode + ']');
                                $(this).attr('name', name);
                            });

                            // Add the new tab content
                            newContent.appendTo("#translations-tabs");

                            // Refresh tabs
                            $("#translations-tabs").tabs("refresh");

                            // Switch to the new tab
                            $("#translations-tabs").tabs("option", "active", $(".translations-tabs-nav li").length - 2);
                        });

                        // Handle deleting a language
                        $(document).on('click', '.delete-language-btn', function(e) {
                            e.preventDefault();
                            var langCode = $(this).data('lang');

                            if (confirm('Are you sure you want to delete the ' + langCode + ' language? This action cannot be undone.')) {
                                // Remove the tab and its content
                                var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                                $("#tab-" + langCode).remove();
                                $(".translations-tabs-nav li").eq(tabIndex).remove();

                                // Refresh tabs and switch to fallback tab
                                $("#translations-tabs").tabs("refresh");
                                $("#translations-tabs").tabs("option", "active", 0);
                            }
                        });

                        // Add delete buttons to existing language tabs (except fallback)
                        $(".translation-tab").each(function() {
                            var tabId = $(this).attr('id');
                            if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                                var langCode = tabId.replace('tab-', '');
                                var heading = $(this).find('h4');
                                if (!heading.find('.delete-language-btn').length) {
                                    heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');
                                }
                            }
                        });
                    });
                    </script>                
                </div>
            </div>
            
            <input type="submit" name="submit_template" class="button button-primary" value="Save Template">
        </form>
    </div>
    
    <div id="template_usage">
        <h3>Template Usage</h3>
        <?php
        $country_settings = get_option('klaro_geo_country_settings', array());
        $countries_using_template = array();
        $csv_file = plugin_dir_path(dirname(__FILE__)) . 'countries.csv';
        $country_names = array();
        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $country_names[$data[0]] = $data[1]; // Map country code to name
            }
            fclose($handle);
        }
        
        foreach ($country_settings as $code => $settings) {
            if (isset($settings['template']) && $settings['template'] === $current_template) {
                $countries_using_template[] = $code;
            }
        }
        
        if (empty($countries_using_template)) {
            if ($current_template === 'default') {
                echo '<p>This is the default template used when no other template is specified.</p>';
            } else {
                echo '<p>This template is not currently assigned to any countries.</p>';
            }
        } else {
            echo '<p>This template is used by the following countries:</p>';
            echo '<ul class="template-countries-list">';
            foreach ($countries_using_template as $code) {
                $name = isset($country_names[$code]) ? $country_names[$code] : $code;
                echo '<li>' . esc_html($name) . ' (' . esc_html($code) . ')</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#add_template').click(function() {
            // Hide the main template settings temporarily
            $('#template_settings').hide();
            
            // Show the new template form
            var newTemplateForm = $('<div id="new_template_form" class="template-creation-form">' +
                // ... (keep existing form HTML) ...
                '</div>');
            
            // Insert the form into the container
            $('#template_creation_container').html(newTemplateForm);
            $(this).hide();
            
            // Handle Cancel
            $('#cancel_template').click(function() {
                $('#new_template_form').remove();
                $('#add_template').show();
                $('#template_settings').show();
            });
            
            // Handle Create
            $('#create_template').click(function() {
                // ... (keep existing AJAX logic) ...
                success: function(response) {
                    if (response.success) {
                        // Add new template to selector and select it
                        var option = new Option(templateName, response.data.template_key);
                        $('#template_selector').append(option);
                        $('#template_selector').val(response.data.template_key).trigger('change');
                        
                        // Clean up creation form
                        $('#new_template_form').remove();
                        $('#add_template').show();
                        $('#template_settings').show();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });
    });

    jQuery(document).ready(function($) {
        // Bulk edit button
        $('#bulk_edit_templates').click(function() {
            $('#bulk_edit_modal').show();
        });
        
        // Close modal
        $('#close_bulk_modal').click(function() {
            $('#bulk_edit_modal').hide();
        });
        
        // Select all countries
        $('#select_all_countries').change(function() {
            $('.country-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Apply bulk template
        $('#apply_bulk_template').click(function() {
            var selectedTemplate = $('#bulk_template').val();
            var selectedCountries = $('.country-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedCountries.length === 0) {
                alert('Please select at least one country.');
                return;
            }
            
            selectedCountries.forEach(function(code) {
                $('select[name="klaro_geo_country_settings[' + code + '][template]"]').val(selectedTemplate);
            });
            
            $('#bulk_edit_modal').hide();
        });
    });
    
    </script>
    <?php
}

add_action('wp_ajax_save_klaro_country_settings', 'klaro_geo_save_country_settings');

add_action('wp_ajax_save_klaro_region_settings', 'klaro_geo_save_region_settings');

function klaro_geo_save_region_settings() {    check_ajax_referer('klaro_geo_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    parse_str($_POST['settings'], $settings);

        // Update region settings
        if (isset($settings['klaro_geo_region_settings'])) {
            update_option('klaro_geo_region_settings', $settings['klaro_geo_region_settings']);
        }
    
        wp_send_json_success();
    }
    
    function klaro_geo_save_country_settings() {
        check_ajax_referer('klaro_geo_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
    
        parse_str($_POST['settings'], $settings);
    

    // Update options
    update_option('klaro_geo_country_settings', $settings['klaro_geo_country_settings']);
    
    wp_send_json_success();
}

function klaro_geo_get_template_config($template_key) {
    $templates = get_option('klaro_geo_templates', array());

    if ($template_key === 'default' || !isset($templates[$template_key])) {
        // Get default templates
        $default_templates = klaro_geo_get_default_templates();
        return $default_templates['strict'];
    }

    return $templates[$template_key];
}

function klaro_geo_get_config() {
    // Get current settings
    $geo_settings = json_decode(get_option('klaro_geo_settings'), true);
    if (empty($geo_settings)) {
        $geo_settings = klaro_geo_get_default_geo_settings();
    }

    // Get user location
    $location = klaro_geo_get_user_location();
    $user_country = $location['country'];
    $user_region = $location['region'];

    // Determine which template to use
    $template_to_use = $geo_settings['default_template'];

    if ($user_country && isset($geo_settings['countries'][$user_country])) {
        $country_config = $geo_settings['countries'][$user_country];

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
        'onaccept' => get_option('klaro_geo_gtm_onaccept'),
        'ondecline' => get_option('klaro_geo_gtm_ondecline')
    );

    if (!empty(array_filter($gtm_config))) {
        $config['gtm'] = $gtm_config;
    }

    return $config;
}

// Function to output the configuration
function klaro_geo_output_config() {
    $config = klaro_geo_get_config();
    return 'var klaroConfig = ' . wp_json_encode($config, JSON_PRETTY_PRINT) . ';';
}


// Add settings to admin page
function klaro_geo_country_settings_page_content() {
    // Get existing settings or initialize with defaults
    $geo_settings = json_decode(get_option('klaro_geo_settings'), true);
    if (empty($geo_settings)) {
        $geo_settings = klaro_geo_get_default_geo_settings();
    }
    // Define default visible countries
    $default_visible_countries = array(
        // EU countries
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // Other major countries
        'US', 'GB', 'BR', 'CA', 'CN', 'IN', 'JP', 'AU', 'KR'
    );

    // Get visible countries from user settings
    $visible_countries = get_option('klaro_geo_visible_countries', $default_visible_countries);
    
    // Load country codes from CSV file
    $csv_file = plugin_dir_path(__DIR__ . '/../klaro.php') . 'countries.csv';
    $countries = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $countries[$data[0]] = $data[1];
        }
        fclose($handle);
    }
    
    // Get templates
    $templates = get_option('klaro_geo_templates', array());

    // Add data to JavaScript
    wp_localize_script('klaro-geo-admin', 'klaroGeoAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('klaro_geo_nonce'),
        'templates' => $templates,
        'visibleCountries' => $visible_countries
    ));
    
    ?>
    <div class="wrap">
        <h1>Klaro Geo Country Settings</h1>
        <form method="post" action="options.php" id="klaro-country-settings-form">
            <?php settings_fields('klaro_geo_settings_group'); ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Settings</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <!-- Default settings for other countries -->
                <tr class="default-settings">
                    <td><strong>Country Default/Fallback Template</strong></td>
                    <td>
                        <select name="klaro_geo_settings[default_template]">
                            <option value="default">Default Template</option>
                            <?php foreach ($templates as $key => $template) : ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                    <?php selected($geo_settings['default_template'] ?? 'default', $key); ?>>
                                    <?php echo esc_html($template['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button type="button" id="manage-countries" class="button">Show/Hide Countries</button>
                    </td>
                </tr>

                <!-- Visible countries -->
                <?php foreach ($visible_countries as $code) :
                    if (!isset($countries[$code])) continue;
                    $name = $countries[$code];
                    $country_config = isset($geo_settings['countries'][$code]) ?
                        $geo_settings['countries'][$code] :
                        array(
                            'template' => 'default',
                            'consent_mode' => $geo_settings['fallback_behavior'],
                            'regions' => array()
                        );
                    ?>
                        <tr class="country-row">
                            <td><?php echo esc_html($code); ?> (<?php echo esc_html($name); ?>)</td>
                            <td>
                                <select name="klaro_geo_settings[countries][<?php echo esc_attr($code); ?>][template]">
                                    <option value="default">Default Template</option>
                                    <?php foreach ($templates as $key => $template) : ?>
                                        <option value="<?php echo esc_attr($key); ?>"
                                            <?php selected($country_config['template'], $key); ?>>
                                            <?php echo esc_html($template['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button manage-regions" data-country="<?php echo esc_attr($code); ?>">
                                    Manage Regions
                                </button>
                                <button type="button" class="button hide-country" data-country="<?php echo esc_attr($code); ?>">
                                    Hide
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Region Management Modal -->
                        <div id="region-modal-<?php echo esc_attr($code); ?>" class="klaro-modal" style="display: none;">
                            <div class="klaro-modal-content">
                                <h2>Manage Regions for <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)</h2>
                                <div class="region-list" data-country="<?php echo esc_attr($code); ?>">
                                    Loading regions...
                                </div>
                                <button type="button" class="button close-region-modal">Close</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Country Management Modal -->
            <div id="country-modal" class="klaro-modal" style="display: none;">
                <div class="klaro-modal-content">
                    <h2>Manage Countries</h2>
                    <div class="country-list">
                        <?php foreach ($countries as $code => $name) : ?>
                            <div class="country-item">
                                <label>
                                    <input type="checkbox"
                                           name="klaro_geo_visible_countries[]"
                                           value="<?php echo esc_attr($code); ?>"
                                           <?php checked(in_array($code, $visible_countries)); ?>>
                                    <?php echo esc_html($code . ' - ' . $name); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="button button-primary save-countries">Save</button>
                        <button type="button" class="button close-country-modal">Cancel</button>
                    </div>
                </div>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

?>