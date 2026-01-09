<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Main settings page content
function klaro_geo_settings_page_content() {
    global $default_services;

    add_filter('pre_update_option_klaro_geo_debug_countries', 'klaro_geo_process_debug_countries', 10, 2);

    // Get existing services or initialize with defaults using the service settings class
    $service_settings = Klaro_Geo_Service_Settings::get_instance();
    $default_services = $service_settings->get();

    if (empty($default_services)) {
        // Get default services from the service settings class
        $default_services = $service_settings->get_default_services();
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
                        <input type="checkbox" name="klaro_geo_enable_floating_button" id="klaro_geo_enable_floating_button" value="1" <?php checked(get_option('klaro_geo_enable_floating_button', true), 1); ?> />
                        <p class="description">When enabled, a floating "Manage Consent Settings" button will appear at the bottom right of your website. This button allows visitors to easily access and update their consent preferences at any time.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_floating_button_text">Button Text</label></th>
                    <td>
                        <input type="text" name="klaro_geo_floating_button_text" id="klaro_geo_floating_button_text" value="<?php echo esc_attr(get_option('klaro_geo_floating_button_text', 'Manage Consent')); ?>" class="regular-text" />
                        <p class="description">Text to display on the floating consent button.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_floating_button_theme">Button Theme</label></th>
                    <td>
                        <select name="klaro_geo_floating_button_theme" id="klaro_geo_floating_button_theme">
                            <option value="light" <?php selected(get_option('klaro_geo_floating_button_theme', 'light'), 'light'); ?>>Light</option>
                            <option value="dark" <?php selected(get_option('klaro_geo_floating_button_theme', 'dark'), 'dark'); ?>>Dark</option>
                            <option value="blue" <?php selected(get_option('klaro_geo_floating_button_theme', 'blue'), 'blue'); ?>>Blue</option>
                            <option value="green" <?php selected(get_option('klaro_geo_floating_button_theme', 'green'), 'green'); ?>>Green</option>
                        </select>
                        <p class="description">Choose the button style theme.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_floating_button_position">Button Position</label></th>
                    <td>
                        <select name="klaro_geo_floating_button_position" id="klaro_geo_floating_button_position">
                            <option value="bottom-right" <?php selected(get_option('klaro_geo_floating_button_position', 'bottom-right'), 'bottom-right'); ?>>Bottom Right</option>
                            <option value="bottom-left" <?php selected(get_option('klaro_geo_floating_button_position', 'bottom-left'), 'bottom-left'); ?>>Bottom Left</option>
                            <option value="top-right" <?php selected(get_option('klaro_geo_floating_button_position', 'top-right'), 'top-right'); ?>>Top Right</option>
                            <option value="top-left" <?php selected(get_option('klaro_geo_floating_button_position', 'top-left'), 'top-left'); ?>>Top Left</option>
                        </select>
                        <p class="description">Choose where the floating button should appear on the page.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">WordPress Menu Integration</th>
                    <td>
                        <p><strong>How to add a consent button to your WordPress menu:</strong></p>
                        <ol>
                            <li>Go to <a href="<?php echo admin_url('nav-menus.php'); ?>">Appearance > Menus</a> in your WordPress admin</li>
                            <li>Create a new menu or edit an existing one</li>
                            <li>In the "Custom Links" section, add a link with:
                                <ul>
                                    <li><strong>URL:</strong> # (just a hash symbol)</li>
                                    <li><strong>Link Text:</strong> "Manage Cookies" or whatever text you prefer</li>
                                </ul>
                            </li>
                            <li>Click "Add to Menu"</li>
                            <li>Expand the newly added menu item</li>
                            <li>In the "CSS Classes (optional)" field, add the class <code>open-klaro-modal</code></li>
                            <li>Save the menu</li>
                        </ol>
                        <p class="description">When clicked, this menu item will open the Klaro consent management popup, allowing users to update their consent preferences at any time.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Shortcode</th>
                    <td>
                        <p><strong>Use the shortcode to add a consent button anywhere:</strong></p>
                        <code>[klaro_consent_button text="Manage Cookie Settings" class="my-custom-class"]</code>
                        <p class="description">This shortcode creates a button or link that opens the Klaro consent modal when clicked. Parameters:</p>
                        <ul>
                            <li><strong>text</strong> - (Optional) The button text. Defaults to the Button Text setting above.</li>
                            <li><strong>class</strong> - (Optional) Additional CSS classes to add to the button.</li>
                            <li><strong>style</strong> - (Optional) Set to "link" for a text link instead of a button.</li>
                        </ul>
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
            <h2>DataLayer Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_suppress_consents_events">Suppress Individual Klaro Consent Event dataLayer Pushes</label></th>
                    <td>
                        <input type="checkbox" name="klaro_geo_suppress_consents_events" id="klaro_geo_suppress_consents_events" value="1" <?php checked(get_option('klaro_geo_suppress_consents_events', true)); ?> />
                        <p class="description">Klaro sends a "consents" event every time an individual consent toggle is changed in the consent banner. These don't take effect until the user saves their choices, at which point the plugin sends a consolidated event. Enabling this setting (recommended) suppresses the individual toggle events, which can pollute the dataLayer when users toggle multiple settings. Disable this only if you need to track individual consent toggle interactions within the banner.</p>
                    </td>
                </tr>
            </table>

            <h2>Debug Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_enable_debug_logging">Enable Plugin Debug Logging</label></th>
                    <td>
                        <input type="checkbox" name="klaro_geo_enable_debug_logging" id="klaro_geo_enable_debug_logging" value="1" <?php checked(get_option('klaro_geo_enable_debug_logging', false)); ?> />
                        <p class="description">When enabled, the plugin will output debug messages to WordPress logs (PHP) and browser console (JavaScript). This is useful for troubleshooting but should be disabled in production to reduce log noise. Note: WP_DEBUG must also be enabled for PHP logging to work.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="klaro_geo_debug_countries">Debug Countries/Regions (comma-separated):</label></th>
                    <td>
                        <?php
                        $debug_countries = get_option('klaro_geo_debug_countries', ['US','UK','CA','FR','AU', 'US-CA', 'CA-QC']);

                        // Ensure we have an array
                        if (!is_array($debug_countries)) {
                            if (is_string($debug_countries)) {
                                // Convert string to array
                                $debug_countries = explode(',', $debug_countries);
                                $debug_countries = array_map('trim', $debug_countries);
                                $debug_countries = array_filter($debug_countries);
                            } else {
                                // Use default
                                $debug_countries = ['US','UK','CA','FR','AU', 'US-CA', 'CA-QC'];
                            }
                            // Update the option to ensure it's stored as an array
                            update_option('klaro_geo_debug_countries', $debug_countries);
                        }

                        $debug_countries_str = implode(',', $debug_countries);
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

// Function to process the debug countries option
function klaro_geo_process_debug_countries($new_value, $old_value) {
    if (is_string($new_value)) {
        $debug_countries_array = array_map('trim', explode(',', $new_value));
        return $debug_countries_array;
    }
    return $new_value; // Return the value as is if it's not a string
}

// Register settings for the main settings page
function klaro_geo_register_main_settings() {
    // Template settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_templates');

    // Service and purpose settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_analytics_purposes');
    register_setting('klaro_geo_settings_group', 'klaro_geo_ad_purposes');
    register_setting('klaro_geo_settings_group', 'klaro_geo_purposes', [
        'type' => 'string',
        'default' => 'functional,analytics,advertising',
        'sanitize_callback' => function($input) {
            if (empty($input)) {
                return 'functional,analytics,advertising';
            }
            // Sanitize the comma-separated list
            $purposes = array_map('trim', explode(',', $input));
            $purposes = array_map('sanitize_text_field', $purposes);
            return implode(',', $purposes);
        }
    ]);
    
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
    register_setting('klaro_geo_settings_group', 'klaro_geo_debug_countries', [
        'type' => 'array',
        'default' => ['US', 'US-CA', 'CA', 'CA-QC', 'UK', 'FR', 'AU'],
        'sanitize_callback' => function($input) {
            // If input is already an array, return it
            if (is_array($input)) {
                return $input;
            }

            // If input is a string, split by commas and trim whitespace
            if (is_string($input)) {
                $countries = explode(',', $input);
                $countries = array_map('trim', $countries);
                $countries = array_filter($countries); // Remove empty values
                return $countries;
            }

            // Default return if input is neither string nor array
            return ['US', 'US-CA', 'CA', 'CA-QC', 'UK', 'FR', 'AU'];
        },
    ]);
    register_setting('klaro_geo_settings_group', 'klaro_geo_enable_consent_receipts');
    register_setting('klaro_geo_settings_group', 'klaro_geo_cleanup_on_deactivate');
    register_setting('klaro_geo_settings_group', 'klaro_geo_enable_debug_logging', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);

    // DataLayer settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_suppress_consents_events', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);

    // Button settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_enable_floating_button', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);

    // Floating button settings
    register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_text', [
        'type' => 'string',
        'default' => 'Manage Consent',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_theme', [
        'type' => 'string',
        'default' => 'light',
        'sanitize_callback' => function($input) {
            // Validate button theme
            $valid_themes = ['light', 'dark', 'blue', 'green'];
            return in_array($input, $valid_themes) ? $input : 'light';
        }
    ]);
    register_setting('klaro_geo_settings_group', 'klaro_geo_floating_button_position', [
        'type' => 'string',
        'default' => 'bottom-right',
        'sanitize_callback' => function($input) {
            // Validate button position
            $valid_positions = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
            return in_array($input, $valid_positions) ? $input : 'bottom-right';
        }
    ]);
}
add_action('admin_init', 'klaro_geo_register_main_settings');