<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Country settings page content
function klaro_geo_country_settings_page_content() {
    // Get existing settings or initialize with defaults
    $geo_settings = get_option('klaro_geo_country_settings', array());

    // If empty, try the legacy format or initialize with defaults
    if (empty($geo_settings)) {
        $geo_settings = klaro_geo_get_default_geo_settings();
    }
    // Debug log to see what's being loaded
    klaro_geo_debug_log('Country settings loaded: ' . print_r($geo_settings, true));
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
    $csv_file = dirname(dirname(plugin_dir_path(__FILE__))) . '/countries.csv';    
    $countries = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $countries[$data[0]] = $data[1];
        }
        fclose($handle);
    }
    
    // Get templates
    $templates = get_option('klaro_geo_templates', array());

    // Add data to JavaScript - use the consolidated script
    wp_localize_script('klaro-geo-admin-js', 'klaroGeoAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('klaro_geo_nonce'),
        'templates' => $templates,
        'visibleCountries' => $visible_countries
    ));
    
    ?>
    <div class="wrap">
        <h1>Klaro Geo Country Settings</h1>
        <form method="post" action="options.php" id="klaro-country-settings-form" onsubmit="return true;">
            <?php settings_fields('klaro_geo_country_settings_group'); ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Template</th>
                        <th>Regions with Overrides</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <!-- Default settings for other countries -->
                <tr class="default-settings">
                    <td><strong>Country Default/Fallback Template</strong></td>
                    <td>
                        <select name="klaro_geo_country_settings[default_template]">
                            <option value="default">Default Template</option>
                            <?php foreach ($templates as $key => $template) : ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                    <?php selected(isset($geo_settings['default_template']) ? $geo_settings['default_template'] : 'default', $key); ?>>
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
                    // Check if we have settings for this country in the new format
                    if (isset($geo_settings[$code]) && is_array($geo_settings[$code])) {
                        $country_config = $geo_settings[$code];
                    }
                    // Check if we have settings in the legacy format
                    else if (isset($geo_settings['countries'][$code])) {
                        $country_config = $geo_settings['countries'][$code];
                    }
                    // Default settings if nothing is found
                    else {
                        $country_config = array(
                            'template' => 'default',
                            'regions' => array()
                        );
                        // Flag this as a default config so we don't save it unnecessarily
                        $country_config['_is_default'] = true;
                    }
                    ?>
                        <tr class="country-row">
                            <td><?php echo esc_html($code); ?> (<?php echo esc_html($name); ?>)</td>
                            <td>
                                <select name="klaro_geo_country_settings[<?php echo esc_attr($code); ?>][template]">
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
                                <?php
                                // Display regions with overrides
                                $regions_with_overrides = array();
                                
                                // Check if we have regions in the new nested format
                                if (isset($country_config['regions']) && is_array($country_config['regions'])) {
                                    foreach ($country_config['regions'] as $region_code => $template) {
                                        if (!empty($template) && $template !== 'default') {
                                            $regions_with_overrides[] = $region_code;
                                        }
                                    }
                                } else {
                                    // Check for regions in the old flat format
                                    foreach ($country_config as $key => $value) {
                                        // Skip the template key as it's not a region
                                        if ($key !== 'template' && $key !== 'regions' && !empty($value) && $value !== 'default') {
                                            $regions_with_overrides[] = $key;
                                        }
                                    }
                                }
                                
                                if (!empty($regions_with_overrides)) {
                                    echo '<span class="regions-list">' . esc_html(implode(', ', $regions_with_overrides)) . '</span>';
                                } else {
                                    echo '<span class="no-regions">None</span>';
                                }
                                ?>
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
                                <form id="region-form-<?php echo esc_attr($code); ?>" class="region-form" onsubmit="return false;" action="javascript:void(0);">
                                    <div class="region-list" data-country="<?php echo esc_attr($code); ?>">
                                        Loading regions...
                                    </div>
                                </form>
                                <div class="region-modal-actions">
                                    <button type="button" class="button button-primary save-regions" data-country="<?php echo esc_attr($code); ?>" id="save-regions-<?php echo esc_attr($code); ?>">Save Regions</button>
                                    <button type="button" class="button close-region-modal" id="close-region-modal-<?php echo esc_attr($code); ?>">Close</button>
                                </div>
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['option_page']) && $_POST['option_page'] === 'klaro_geo_country_settings_group' ) {
        // Get the submitted settings
        $submitted_settings = isset($_POST['klaro_geo_country_settings']) ? $_POST['klaro_geo_country_settings'] : array();

        // Get the default template
        $default_template = isset($submitted_settings['default_template']) ? $submitted_settings['default_template'] : 'default';

        // Create a new settings array with only the default_template
        $new_settings = array(
            'default_template' => $default_template
        );

        // Process each country in the submitted settings
        foreach ($submitted_settings as $code => $config) {
            // Skip the default_template key
            if ($code === 'default_template') {
                continue;
            }

            // Remove the _is_default flag if it exists
            if (isset($config['_is_default'])) {
                unset($config['_is_default']);
            }

            // If the country uses the default template and has no regions (or empty regions array), skip it
            if (isset($config['template']) && $config['template'] === $default_template &&
                (!isset($config['regions']) || empty($config['regions']))) {
                klaro_geo_debug_log('Skipping country ' . $code . ' (uses default template with no region overrides)');
                continue;
            }

            // Add the country to the new settings
            $new_settings[$code] = $config;
        }

        // Replace the old settings with the new ones
        $geo_settings = $new_settings;

        klaro_geo_debug_log('Optimized settings: ' . print_r($geo_settings, true));

        // Save as a regular array, not JSON encoded
        update_option('klaro_geo_country_settings', $geo_settings);
        klaro_geo_debug_log('Saved optimized country settings as array from form submission');
    }
}

// Register settings for country settings
function klaro_geo_register_country_settings() {
    register_setting('klaro_geo_country_settings_group', 'klaro_geo_country_settings');
    register_setting('klaro_geo_country_settings_group', 'klaro_geo_visible_countries');
}
add_action('admin_init', 'klaro_geo_register_country_settings');

// AJAX handler for saving country settings
function klaro_geo_save_country_settings() {
    check_ajax_referer('klaro_geo_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    parse_str($_POST['settings'], $settings);

    // Debug log
    klaro_geo_debug_log('Parsed settings from AJAX: ' . print_r($settings, true));

    // Get the submitted settings
    $submitted_settings = isset($settings['klaro_geo_country_settings']) ? $settings['klaro_geo_country_settings'] : array();

    // Get the default template
    $default_template = isset($submitted_settings['default_template']) ? $submitted_settings['default_template'] : 'default';

    // Create a new settings array with only the default_template
    $new_settings = array(
        'default_template' => $default_template
    );

    // Process each country in the submitted settings
    foreach ($submitted_settings as $code => $config) {
        // Skip the default_template key
        if ($code === 'default_template') {
            continue;
        }

        // Remove the _is_default flag if it exists
        if (isset($config['_is_default'])) {
            unset($config['_is_default']);
        }

        // If the country uses the default template and has no regions (or empty regions array), skip it
        if (isset($config['template']) && $config['template'] === $default_template &&
            (!isset($config['regions']) || empty($config['regions']))) {
            klaro_geo_debug_log('Skipping country ' . $code . ' (uses default template with no region overrides)');
            continue;
        }

        // Add the country to the new settings
        $new_settings[$code] = $config;
    }

    // Replace the old settings with the new ones
    $country_settings = $new_settings;

    klaro_geo_debug_log('Optimized settings: ' . print_r($country_settings, true));

    // Update options - save as array, not JSON encoded
    update_option('klaro_geo_country_settings', $country_settings);
    klaro_geo_debug_log('Saved optimized country settings as array from AJAX');
    
    wp_send_json_success();
}
add_action('wp_ajax_save_klaro_country_settings', 'klaro_geo_save_country_settings');

// AJAX handler for saving region settings
function klaro_geo_save_region_settings_ajax() {
    check_ajax_referer('klaro_geo_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    // Log the raw POST data for debugging
    klaro_geo_debug_log('Raw POST data: ' . print_r($_POST, true));

    $settings_json = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';

    if (empty($settings_json)) {
        wp_send_json_error(['message' => 'No settings provided']);
        return;
    }

    // Log the JSON string
    klaro_geo_debug_log('Settings JSON: ' . $settings_json);

    // Decode the JSON string
    $settings = json_decode($settings_json, true);
    klaro_geo_debug_log('Decoded settings: ' . print_r($settings, true));

    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid JSON: ' . json_last_error_msg()]);
        return;
    }

    // Debug log
    klaro_geo_debug_log('Region settings received: ' . print_r($settings, true));

    // Get existing country settings
    $country_settings = get_option('klaro_geo_country_settings', []);

    // If country settings is a string (JSON encoded), decode it
    if (is_string($country_settings)) {
        $country_settings = json_decode($country_settings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If decoding fails, initialize with an empty array
            $country_settings = [];
            klaro_geo_debug_log('Failed to decode existing country settings, initializing with empty array');
        } else {
            klaro_geo_debug_log('Decoded existing country settings from JSON string');
        }
    }

    // Ensure country_settings is an array
    if (!is_array($country_settings)) {
        $country_settings = [];
        klaro_geo_debug_log('Existing country settings is not an array, initializing with empty array');
    }

    klaro_geo_debug_log('Existing country settings: ' . print_r($country_settings, true));

    // Update region settings for each country
    foreach ($settings as $country => $country_data) {
        // Initialize country settings if they don't exist
        if (!isset($country_settings[$country])) {
            $country_settings[$country] = [
                'template' => 'default'
            ];
        }

        // Make sure we preserve the existing template setting
        if (!isset($country_settings[$country]['template'])) {
            $country_settings[$country]['template'] = 'default';
        }

        // Check if regions is defined in the incoming data
        if (isset($country_data['regions']) && is_array($country_data['regions'])) {
            // Initialize regions array if it doesn't exist
            if (!isset($country_settings[$country]['regions'])) {
                $country_settings[$country]['regions'] = [];
            }

            // Process each region
            foreach ($country_data['regions'] as $region_code => $template) {
                // Only save if the value is not "default" or "inherit" (use country default)
                if ($template !== 'default' && $template !== 'inherit') {
                    $country_settings[$country]['regions'][$region_code] = $template;
                    klaro_geo_debug_log("Setting region {$region_code} to template {$template}");
                } else {
                    // If the value is "default" or "inherit", remove any existing override
                    if (isset($country_settings[$country]['regions'][$region_code])) {
                        unset($country_settings[$country]['regions'][$region_code]);
                        klaro_geo_debug_log("Removing region {$region_code} override (set to default/inherit)");
                    }
                }
            }
        } else {
            // Process legacy format where regions are directly in the country_data
            foreach ($country_data as $key => $value) {
                if ($key !== 'template' && $key !== 'regions') {
                    // Initialize regions array if it doesn't exist
                    if (!isset($country_settings[$country]['regions'])) {
                        $country_settings[$country]['regions'] = [];
                    }

                    // Only save if the value is not "default" or "inherit" (use country default)
                    if ($value !== 'default' && $value !== 'inherit') {
                        $country_settings[$country]['regions'][$key] = $value;
                        klaro_geo_debug_log("Setting region {$key} to template {$value} (legacy format)");
                    } else {
                        // If the value is "default" or "inherit", remove any existing override
                        if (isset($country_settings[$country]['regions'][$key])) {
                            unset($country_settings[$country]['regions'][$key]);
                            klaro_geo_debug_log("Removing region {$key} override (set to default/inherit) (legacy format)");
                        }
                    }
                }
            }
        }

        klaro_geo_debug_log('Country settings after merge: ' . print_r($country_settings[$country], true));

        // If regions array is empty, remove it to save space
        if (empty($country_settings[$country]['regions'])) {
            unset($country_settings[$country]['regions']);
            klaro_geo_debug_log("Removed empty regions array for country {$country}");
        }
    }

    // Get the default template
    $default_template = isset($country_settings['default_template']) ? $country_settings['default_template'] : 'default';

    // Create a new settings array with only the default_template
    $new_settings = array(
        'default_template' => $default_template
    );

    // Process each country in the settings
    foreach ($country_settings as $code => $config) {
        // Skip the default_template key
        if ($code === 'default_template') {
            continue;
        }

        // Remove the _is_default flag if it exists
        if (isset($config['_is_default'])) {
            unset($config['_is_default']);
        }

        // If the country uses the default template and has no regions (or empty regions array), skip it
        if (isset($config['template']) && $config['template'] === $default_template &&
            (!isset($config['regions']) || empty($config['regions']))) {
            klaro_geo_debug_log('Skipping country ' . $code . ' (uses default template with no region overrides)');
            continue;
        }

        // Add the country to the new settings
        $new_settings[$code] = $config;
    }

    // Replace the old settings with the new ones
    $country_settings = $new_settings;

    // Debug log
    klaro_geo_debug_log('Updated and optimized country settings: ' . print_r($country_settings, true));

    // Save updated country settings as array, not JSON encoded
    $result = update_option('klaro_geo_country_settings', $country_settings);

    // Debug log the result
    klaro_geo_debug_log('Update option result: ' . ($result ? 'true' : 'false'));

    // Get the updated option to verify it was saved correctly
    $updated_settings = get_option('klaro_geo_country_settings', []);
    klaro_geo_debug_log('Retrieved updated settings: ' . print_r($updated_settings, true));

    wp_send_json_success([
        'message' => 'Region settings saved successfully',
        'settings' => $settings,
        'updated_settings' => $country_settings
    ]);
}
add_action('wp_ajax_save_klaro_region_settings', 'klaro_geo_save_region_settings_ajax');

// AJAX handler for getting regions
function klaro_geo_get_regions() {
    check_ajax_referer('klaro_geo_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';

    if (empty($country)) {
        wp_send_json_error(['message' => 'Country code is required']);
        return;
    }

    // Debug log
    klaro_geo_debug_log('Getting regions for country: ' . $country . ', language: ' . $language);

    // Load regions from CSV file
    $regions_file = KLARO_GEO_PATH . 'subdivisions.csv';
    klaro_geo_debug_log('Attempting to load regions from: ' . $regions_file);

    $regions = [];
    $languages = [];

    if (file_exists($regions_file) && ($handle = fopen($regions_file, "r")) !== FALSE) {
        // Skip the header row
        $header = fgetcsv($handle, 1000, ",");
        klaro_geo_debug_log('CSV Header: ' . print_r($header, true));

        $row_count = 0;
        $match_count = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;

            // Debug first few rows to understand the structure
            if ($row_count <= 5) {
                klaro_geo_debug_log('CSV Row ' . $row_count . ': ' . print_r($data, true));
            }

            // The CSV format is: country_code, subdivision_code, subdivision_name, language_code, ...
            // Check if this is a region for the requested country
            if (isset($data[0]) && $data[0] === $country && isset($data[1])) {
                $match_count++;
                klaro_geo_debug_log('Found match for country ' . $country . ': ' . print_r($data, true));

                // Extract the region code from the subdivision_code (e.g., "BE-WBR" -> "WBR")
                $region_code = str_replace($country . '-', '', $data[1]);

                // If we already have this region code, append the new name
                if (isset($regions[$region_code])) {
                    // Only add if it's a different name
                    if ($regions[$region_code] !== $data[2]) {
                        $regions[$region_code] .= ' / ' . $data[2];
                    }
                } else {
                    $regions[$region_code] = $data[2];
                }

                // Add language if it's specified in the CSV
                if (isset($data[3]) && !empty($data[3]) && !in_array($data[3], $languages)) {
                    $languages[] = $data[3];
                }
            }
        }

        klaro_geo_debug_log('Processed ' . $row_count . ' rows, found ' . $match_count . ' matches for country ' . $country);
        fclose($handle);
    }

    // If no regions found, return an empty array
    if (empty($regions)) {
        klaro_geo_debug_log('No regions found for country: ' . $country);
        wp_send_json_success([
            'regions' => [],
            'languages' => [],
            'current_language' => $language,
            'settings' => []
        ]);
        return;
    }

    // Get existing region settings
    $country_settings = get_option('klaro_geo_country_settings', []);
    $region_settings = [];

    // Debug log the country settings
    klaro_geo_debug_log('Country settings: ' . print_r($country_settings, true));

    // Check if we have settings for this country
    if (isset($country_settings[$country])) {
        // Check if we have regions in the new nested format
        if (isset($country_settings[$country]['regions']) && is_array($country_settings[$country]['regions'])) {
            $region_settings = $country_settings[$country]['regions'];
            klaro_geo_debug_log('Found region settings in nested format: ' . print_r($region_settings, true));
        } else {
            // Extract all keys that are not 'template' or 'regions' as region settings (old flat format)
            foreach ($country_settings[$country] as $key => $value) {
                if ($key !== 'template' && $key !== 'regions') {
                    $region_settings[$key] = $value;
                }
            }
            klaro_geo_debug_log('Found region settings in flat format: ' . print_r($region_settings, true));
        }
    }
    // Check if we have settings in the legacy format
    else if (isset($country_settings['countries']) && isset($country_settings['countries'][$country]) && isset($country_settings['countries'][$country]['regions'])) {
        $region_settings = $country_settings['countries'][$country]['regions'];
        klaro_geo_debug_log('Found region settings in legacy format: ' . print_r($region_settings, true));
    }

    // Convert regions to the format expected by the JavaScript
    $formatted_regions = [];
    foreach ($regions as $code => $name) {
        // For the JavaScript, we need to provide the regions in a key-value format
        $formatted_regions[$code] = $name;
    }

    // Debug log
    klaro_geo_debug_log('Returning regions: ' . print_r($formatted_regions, true));
    klaro_geo_debug_log('Returning region settings: ' . print_r($region_settings, true));
    klaro_geo_debug_log('Returning languages: ' . print_r($languages, true));

    wp_send_json_success([
        'regions' => $formatted_regions,
        'languages' => $languages,
        'current_language' => $language,
        'settings' => $region_settings
    ]);
}
add_action('wp_ajax_get_klaro_regions', 'klaro_geo_get_regions');

// AJAX handler for saving visible countries
function klaro_geo_save_visible_countries() {
    check_ajax_referer('klaro_geo_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $countries = isset($_POST['countries']) ? $_POST['countries'] : [];

    // Sanitize country codes
    $countries = array_map('sanitize_text_field', $countries);

    // Debug log
    klaro_geo_debug_log('Saving visible countries: ' . print_r($countries, true));

    // Save visible countries
    update_option('klaro_geo_visible_countries', $countries);

    wp_send_json_success();
}
add_action('wp_ajax_klaro_geo_save_visible_countries', 'klaro_geo_save_visible_countries');
add_action('wp_ajax_save_klaro_visible_countries', 'klaro_geo_save_visible_countries'); // Legacy support