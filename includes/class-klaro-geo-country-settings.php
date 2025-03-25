<?php
/**
 * Klaro Geo Country Settings Class
 *
 * A class for handling country settings stored in WordPress options
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include the base option class if not already included
if (!class_exists('Klaro_Geo_Option')) {
    require_once dirname(__FILE__) . '/class-klaro-geo-option.php';
}

// Include the template settings class if not already included
if (!class_exists('Klaro_Geo_Template_Settings')) {
    require_once dirname(__FILE__) . '/class-klaro-geo-template-settings.php';
}

/**
 * Class Klaro_Geo_Country_Settings
 * 
 * Class for handling country settings stored in WordPress options
 */
class Klaro_Geo_Country_Settings extends Klaro_Geo_Option {
    /**
     * The option name for visible countries
     *
     * @var string
     */
    protected $visible_countries_option = 'klaro_geo_visible_countries';

    /**
     * Default visible countries
     *
     * @var array
     */
    protected $default_visible_countries = array(
        // EU countries
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // Other major countries
        'US', 'GB', 'BR', 'CA', 'CN', 'IN', 'JP', 'AU', 'KR'
    );

    /**
     * Visible countries
     *
     * @var array
     */
    protected $visible_countries;

    /**
     * Constructor
     *
     * @param string $option_name The option name in the WordPress database
     * @param array $default_value Default value if option doesn't exist
     */
    public function __construct($option_name = 'klaro_geo_country_settings', $default_value = array('default_template' => 'default')) {
        parent::__construct($option_name, $default_value);
        $this->load_visible_countries();
    }

    /**
     * Load visible countries from the database
     *
     * @return array The visible countries
     */
    public function load_visible_countries() {
        $this->visible_countries = get_option($this->visible_countries_option, $this->default_visible_countries);
        
        // Ensure we have an array
        if (!is_array($this->visible_countries)) {
            $this->visible_countries = $this->default_visible_countries;
        }
        
        return $this->visible_countries;
    }

    /**
     * Save visible countries to the database
     *
     * @param array $countries The countries to save
     * @return bool Whether the option was saved successfully
     */
    public function save_visible_countries($countries = null) {
        if ($countries !== null) {
            $this->visible_countries = $countries;
        }
        
        return update_option($this->visible_countries_option, $this->visible_countries);
    }

    /**
     * Get visible countries
     *
     * @return array The visible countries
     */
    public function get_visible_countries() {
        return $this->visible_countries;
    }

    /**
     * Set visible countries
     *
     * @param array $countries The countries to set
     * @return $this For method chaining
     */
    public function set_visible_countries($countries) {
        if (!is_array($countries)) {
            klaro_geo_debug_log('Invalid value for visible countries, must be an array');
            return $this;
        }
        
        $this->visible_countries = $countries;
        
        return $this;
    }

    /**
     * Get a country's settings
     *
     * @param string $country_code The country code
     * @return array|null The country settings or null if not found
     */
    public function get_country($country_code) {
        return $this->get_key($country_code);
    }

    /**
     * Set a country's settings
     *
     * @param string $country_code The country code
     * @param array $settings The settings to set
     * @return $this For method chaining
     */
    public function set_country($country_code, $settings) {
        return $this->set_key($country_code, $settings);
    }

    /**
     * Remove a country's settings
     *
     * @param string $country_code The country code
     * @return $this For method chaining
     */
    public function remove_country($country_code) {
        return $this->remove_key($country_code);
    }

    /**
     * Get a region's settings
     *
     * @param string $country_code The country code
     * @param string $region_code The region code
     * @return string|null The region template or null if not found
     */
    public function get_region($country_code, $region_code) {
        $country = $this->get_country($country_code);
        
        if (!$country || !isset($country['regions']) || !isset($country['regions'][$region_code])) {
            return null;
        }
        
        return $country['regions'][$region_code];
    }

    /**
     * Set a region's settings
     *
     * @param string $country_code The country code
     * @param string $region_code The region code
     * @param string $template The template to use
     * @return $this For method chaining
     */
    public function set_region($country_code, $region_code, $template) {
        $country = $this->get_country($country_code);
        
        if (!$country) {
            // Create the country if it doesn't exist
            $country = array(
                'template' => $this->get_default_template(),
                'regions' => array()
            );
        }
        
        if (!isset($country['regions'])) {
            $country['regions'] = array();
        }
        
        // Only set if different from current value
        if (!isset($country['regions'][$region_code]) || $country['regions'][$region_code] !== $template) {
            $country['regions'][$region_code] = $template;
            $this->set_country($country_code, $country);
        }
        
        return $this;
    }

    /**
     * Remove a region's settings
     *
     * @param string $country_code The country code
     * @param string $region_code The region code
     * @return $this For method chaining
     */
    public function remove_region($country_code, $region_code) {
        $country = $this->get_country($country_code);
        
        if (!$country || !isset($country['regions']) || !isset($country['regions'][$region_code])) {
            return $this;
        }
        
        unset($country['regions'][$region_code]);
        
        // If regions array is empty, remove it
        if (empty($country['regions'])) {
            unset($country['regions']);
        }
        
        $this->set_country($country_code, $country);
        
        return $this;
    }

    /**
     * Get the default template
     *
     * @return string The default template
     */
    public function get_default_template() {
        return $this->get_key('default_template', 'default');
    }

    /**
     * Set the default template
     *
     * @param string $template The template to set as default
     * @return $this For method chaining
     */
    public function set_default_template($template) {
        return $this->set_key('default_template', $template);
    }

    /**
     * Update country settings from form data
     *
     * @param array $submitted_settings The submitted settings
     * @return $this For method chaining
     */
    public function update_from_form($submitted_settings) {
        if (!is_array($submitted_settings)) {
            klaro_geo_debug_log('Invalid submitted settings, must be an array');
            return $this;
        }

        // Get the default template
        $default_template = isset($submitted_settings['default_template']) ? $submitted_settings['default_template'] : 'default';

        // Get current settings to preserve region settings
        $current_settings = $this->get();

        // Log the submitted settings and current settings for debugging
        klaro_geo_debug_log('Submitted settings: ' . print_r($submitted_settings, true));
        klaro_geo_debug_log('Current settings before update: ' . print_r($current_settings, true));

        // Create a new settings array with the default_template
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

            // Check if regions are included in the submitted settings
            if (isset($config['regions']) && is_array($config['regions'])) {
                klaro_geo_debug_log('Found regions in submitted settings for country ' . $code . ': ' . print_r($config['regions'], true));

                // Filter out empty regions
                $config['regions'] = array_filter($config['regions'], function($value) {
                    return !empty($value) && $value !== 'default' && $value !== 'inherit';
                });

                if (!empty($config['regions'])) {
                    klaro_geo_debug_log('After filtering, regions for country ' . $code . ': ' . print_r($config['regions'], true));
                } else {
                    klaro_geo_debug_log('No valid regions found after filtering for country ' . $code);
                    // Keep an empty regions array for consistency
                    $config['regions'] = array();
                }
            }
            // If no regions in submitted settings, preserve existing ones or create empty array
            else if (isset($current_settings[$code]) && isset($current_settings[$code]['regions'])) {
                $config['regions'] = $current_settings[$code]['regions'];
                klaro_geo_debug_log('Preserving existing region settings for country ' . $code . ': ' . print_r($config['regions'], true));
            } else {
                // Ensure regions key exists
                $config['regions'] = array();
                klaro_geo_debug_log('No regions found for country ' . $code . ', creating empty array');
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

        // Check for countries with region settings that might not be in the submitted settings
        foreach ($current_settings as $code => $config) {
            if ($code === 'default_template') {
                continue;
            }

            // If country is not in new settings but has region overrides, preserve it
            if (!isset($new_settings[$code]) && isset($config['regions']) && !empty($config['regions'])) {
                $new_settings[$code] = $config;
                klaro_geo_debug_log('Preserving country ' . $code . ' with region overrides: ' . print_r($config['regions'], true));
            }
        }

        // Replace the old settings with the new ones
        $this->set($new_settings);

        klaro_geo_debug_log('Updated country settings from form: ' . print_r($new_settings, true));

        return $this;
    }

    /**
     * Update region settings from AJAX data
     *
     * @param array $settings The region settings
     * @return $this For method chaining
     */
    public function update_regions_from_ajax($settings) {
        if (!is_array($settings)) {
            klaro_geo_debug_log('Invalid region settings, must be an array');
            return $this;
        }

        klaro_geo_debug_log('Updating regions from AJAX: ' . print_r($settings, true));

        // Get current settings to ensure we don't lose any data
        $current_settings = $this->get();
        klaro_geo_debug_log('Current settings before updating regions: ' . print_r($current_settings, true));

        // Update region settings for each country
        foreach ($settings as $country => $country_data) {
            // Get existing country settings or create new ones
            $country_settings = $this->get_country($country);

            if (!$country_settings) {
                $country_settings = array(
                    'template' => $this->get_default_template()
                );
            }

            // Make sure we preserve the existing template setting
            if (!isset($country_settings['template'])) {
                $country_settings['template'] = $this->get_default_template();
            }

            // Log the country settings before updating
            klaro_geo_debug_log('Country settings before updating regions for ' . $country . ': ' . print_r($country_settings, true));
            
            // Check if regions is defined in the incoming data
            if (isset($country_data['regions']) && is_array($country_data['regions'])) {
                // Initialize regions array if it doesn't exist
                if (!isset($country_settings['regions'])) {
                    $country_settings['regions'] = array();
                }
                
                // Process each region
                foreach ($country_data['regions'] as $region_code => $template) {
                    // Only save if the value is not "default" or "inherit" (use country default)
                    if ($template !== 'default' && $template !== 'inherit') {
                        $country_settings['regions'][$region_code] = $template;
                        klaro_geo_debug_log("Setting region {$region_code} to template {$template}");
                    } else {
                        // If the value is "default" or "inherit", remove any existing override
                        if (isset($country_settings['regions'][$region_code])) {
                            unset($country_settings['regions'][$region_code]);
                            klaro_geo_debug_log("Removing region {$region_code} override (set to default/inherit)");
                        }
                    }
                }
            } else {
                // Process legacy format where regions are directly in the country_data
                foreach ($country_data as $key => $value) {
                    if ($key !== 'template' && $key !== 'regions') {
                        // Initialize regions array if it doesn't exist
                        if (!isset($country_settings['regions'])) {
                            $country_settings['regions'] = array();
                        }
                        
                        // Only save if the value is not "default" or "inherit" (use country default)
                        if ($value !== 'default' && $value !== 'inherit') {
                            $country_settings['regions'][$key] = $value;
                            klaro_geo_debug_log("Setting region {$key} to template {$value} (legacy format)");
                        } else {
                            // If the value is "default" or "inherit", remove any existing override
                            if (isset($country_settings['regions'][$key])) {
                                unset($country_settings['regions'][$key]);
                                klaro_geo_debug_log("Removing region {$key} override (set to default/inherit) (legacy format)");
                            }
                        }
                    }
                }
            }
            
            // Ensure regions key exists
            if (!isset($country_settings['regions'])) {
                $country_settings['regions'] = array();
                klaro_geo_debug_log("Creating empty regions array for country {$country}");
            } else if (empty($country_settings['regions'])) {
                // We'll keep the empty regions array to ensure we don't lose region settings
                klaro_geo_debug_log("Empty regions array for country {$country}, but keeping it for consistency");
            }

            // Update the country settings
            $this->set_country($country, $country_settings);

            // Log the country settings after updating
            klaro_geo_debug_log('Country settings after updating regions for ' . $country . ': ' . print_r($country_settings, true));
        }

        // Get settings after updating all countries
        $updated_settings = $this->get();
        klaro_geo_debug_log('Settings after updating all regions: ' . print_r($updated_settings, true));

        // Skip optimization to ensure we don't lose any region settings
        // $this->optimize();

        return $this;
    }

    /**
     * Optimize the settings by removing countries that use the default template and have no region overrides
     *
     * @return $this For method chaining
     */
    public function optimize() {
        $default_template = $this->get_default_template();
        $optimized = array(
            'default_template' => $default_template
        );

        // Debug log before optimization
        klaro_geo_debug_log('Before optimization: ' . print_r($this->value, true));

        foreach ($this->value as $code => $config) {
            // Skip the default_template key
            if ($code === 'default_template') {
                continue;
            }

            // Ensure regions key exists
            if (!isset($config['regions'])) {
                $config['regions'] = array();
            }

            // Check for region settings
            $has_regions = !empty($config['regions']);

            // If the country uses the default template and has no regions, skip it
            if (isset($config['template']) && $config['template'] === $default_template && !$has_regions) {
                klaro_geo_debug_log('Optimizing: Removing country ' . $code . ' (uses default template with no region overrides)');
                continue;
            }

            // Always preserve countries with region settings
            if ($has_regions) {
                klaro_geo_debug_log('Optimizing: Preserving country ' . $code . ' because it has region settings: ' . print_r($config['regions'], true));
            }

            // Add the country to the optimized settings
            $optimized[$code] = $config;
        }

        // Debug log after optimization
        klaro_geo_debug_log('After optimization: ' . print_r($optimized, true));

        $this->set($optimized);

        return $this;
    }

    /**
     * Get the effective settings for a location
     *
     * @param string $location_code The location code (country or country-region)
     * @return array The effective settings
     */
    public function get_effective_settings($location_code) {
        klaro_geo_debug_log('Getting effective settings for location: ' . $location_code);
        
        // Split location code to check if it's a region request
        $parts = explode('-', $location_code);
        $country_code = $parts[0];
        $region_code = isset($parts[1]) ? $parts[1] : null;
        
        klaro_geo_debug_log('Parsed location - Country: ' . $country_code . ', Region: ' . ($region_code ?? 'none'));
        
        // Default settings
        $effective_settings = array(
            'template' => $this->get_default_template(),
            'fallback_behavior' => 'default',
            'source' => 'default' // Track where the template came from
        );
        
        // Check if the country is in the visible countries list
        if (in_array($country_code, $this->visible_countries)) {
            // If the country is not in the settings, it means it's using the default template
            if (!$this->has_key($country_code)) {
                // Country is using default template
                klaro_geo_debug_log('Country ' . $country_code . ' not found in settings, using default template');
                $effective_settings['template'] = $this->get_default_template();
                $effective_settings['source'] = 'default';
            } else {
                // Country has specific settings
                $country_settings = $this->get_country($country_code);
                klaro_geo_debug_log('Country ' . $country_code . ' found in settings');
                $effective_settings['template'] = $country_settings['template'] ?? $effective_settings['template'];
                $effective_settings['source'] = 'country';
                
                // Check for region override
                if ($region_code && isset($country_settings['regions']) && 
                    isset($country_settings['regions'][$region_code])) {
                    klaro_geo_debug_log('Region ' . $region_code . ' found in settings');
                    $effective_settings['template'] = $country_settings['regions'][$region_code];
                    $effective_settings['source'] = 'region';
                }
            }
        }
        
        // Allow filtering of effective settings
        $effective_settings = apply_filters('klaro_geo_effective_settings', $effective_settings);
        
        // In tests, we need to use mock templates
        if (defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN) {
            // For tests, use a simple array of templates
            $templates = array(
                'default' => array('name' => 'Default Template'),
                'strict' => array('name' => 'Strict Template'),
                'relaxed' => array('name' => 'Relaxed Template')
            );

            // Allow tests to filter templates
            $templates = apply_filters('klaro_geo_test_templates', $templates);
        } else {
            // For normal operation, get the user-defined templates
            $template_settings = new Klaro_Geo_Template_Settings();
            $templates = $template_settings->get();

            // Allow filtering of templates
            $templates = apply_filters('klaro_geo_templates', $templates);
        }
        
        if (isset($templates[$effective_settings['template']])) {
            if (defined('WP_TESTS_DOMAIN') && WP_TESTS_DOMAIN) {
                klaro_geo_debug_log('Template "' . $effective_settings['template'] . '" exists in test templates');
            } else {
                klaro_geo_debug_log('Template "' . $effective_settings['template'] . '" exists in user-defined templates');

                // Log the default setting for this template
                $template_config = isset($templates[$effective_settings['template']]['config']) ?
                    $templates[$effective_settings['template']]['config'] : array();
                $default_setting = isset($template_config['default']) ?
                    ($template_config['default'] ? 'true' : 'false') : 'not set';
                klaro_geo_debug_log('Template "' . $effective_settings['template'] . '" default setting: ' . $default_setting);
            }
        } else {
            klaro_geo_debug_log('WARNING: Template "' . $effective_settings['template'] . '" not found in templates!');
            klaro_geo_debug_log('Available templates: ' . implode(', ', array_keys($templates)));

            // Fall back to default template if the selected one doesn't exist
            $effective_settings['template'] = 'default';
            $effective_settings['source'] = 'fallback';
            klaro_geo_debug_log('Falling back to default template');
        }
        
        klaro_geo_debug_log('Final effective settings: ' . print_r($effective_settings, true));
        return $effective_settings;
    }
}