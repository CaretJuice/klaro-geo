<?php
/**
 * Klaro Geo Service Settings Class
 *
 * A class for handling service settings stored in WordPress options
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include the base option class if not already included
if (!class_exists('Klaro_Geo_Option')) {
    require_once dirname(__FILE__) . '/class-klaro-geo-option.php';
}

/**
 * Class Klaro_Geo_Service_Settings
 *
 * Class for handling service settings stored in WordPress options
 */
class Klaro_Geo_Service_Settings extends Klaro_Geo_Option {
    /**
     * Static instance cache to avoid repeated database loads
     * Key is the option name, value is the instance
     *
     * @var array
     */
    private static $instances = array();

    /**
     * Get a cached instance or create a new one
     * This avoids repeated database loads when the class is instantiated multiple times
     *
     * @param string $option_name The option name in the WordPress database
     * @return Klaro_Geo_Service_Settings
     */
    public static function get_instance($option_name = 'klaro_geo_services') {
        if (!isset(self::$instances[$option_name])) {
            self::$instances[$option_name] = new self($option_name);
        }
        return self::$instances[$option_name];
    }

    /**
     * Clear the instance cache (useful for testing or after saves)
     *
     * @param string|null $option_name Specific option to clear, or null for all
     */
    public static function clear_instance_cache($option_name = null) {
        if ($option_name === null) {
            self::$instances = array();
        } elseif (isset(self::$instances[$option_name])) {
            unset(self::$instances[$option_name]);
        }
    }

    /**
     * Constructor
     *
     * @param string $option_name The option name in the WordPress database
     * @param array $default_value Default value if option doesn't exist
     */
    public function __construct($option_name = 'klaro_geo_services', $default_value = '[]') {
        parent::__construct($option_name, $default_value);

        // If empty, initialize with default services
        if (empty($this->value)) {
            $this->value = $this->get_default_services();
            $this->is_modified = true;
        }
    }

    /**
     * Get default services
     *
     * @return array The default services
     */
    public function get_default_services() {
        // Always use the global function to get default services
        // This ensures we have a single source of truth for service definitions
        if (!function_exists('klaro_geo_get_default_services')) {
            // If the function doesn't exist, include the defaults file that defines it
            require_once dirname(__FILE__) . '/klaro-geo-defaults.php';

            // Log a warning that we had to include the file
            klaro_geo_debug_log('WARNING: Had to include klaro-geo-defaults.php to get default services');
        }

        // Now get the services from the global function
        $services = klaro_geo_get_default_services();

        // Log the services we got
        klaro_geo_debug_log('Got ' . count($services) . ' default services from global function');

        return $services;
    }

    /**
     * Get a service by name
     *
     * @param string $service_name The service name
     * @return array|null The service or null if not found
     */
    public function get_service($service_name) {
        foreach ($this->value as $service) {
            if (isset($service['name']) && $service['name'] === $service_name) {
                return $service;
            }
        }
        
        return null;
    }

    /**
     * Set a service
     *
     * @param string $service_name The service name
     * @param array $service The service to set
     * @return $this For method chaining
     */
    public function set_service($service_name, $service) {
        $found = false;
        
        // Update existing service
        foreach ($this->value as $key => $existing_service) {
            if (isset($existing_service['name']) && $existing_service['name'] === $service_name) {
                $this->value[$key] = $service;
                $found = true;
                $this->is_modified = true;
                break;
            }
        }
        
        // Add new service if not found
        if (!$found) {
            $this->value[] = $service;
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Check if a service is a consent mode service (protected from deletion)
     *
     * @param string $service_name The service name
     * @return bool True if the service is a consent mode service
     */
    public function is_consent_mode_service($service_name) {
        $service = $this->get_service($service_name);
        if (!$service) {
            return false;
        }
        return isset($service['is_consent_mode_service']) && $service['is_consent_mode_service'] === true;
    }

    /**
     * Get all consent mode services
     *
     * @return array Array of consent mode services
     */
    public function get_consent_mode_services() {
        $consent_mode_services = array();
        foreach ($this->value as $service) {
            if (isset($service['is_consent_mode_service']) && $service['is_consent_mode_service'] === true) {
                $consent_mode_services[] = $service;
            }
        }
        return $consent_mode_services;
    }

    /**
     * Hide a service (sets hidden flag, does not delete)
     *
     * @param string $service_name The service name
     * @param bool $hidden Whether to hide the service
     * @return $this For method chaining
     */
    public function hide_service($service_name, $hidden = true) {
        $service = $this->get_service($service_name);
        if ($service) {
            $service['hidden'] = $hidden;
            $this->set_service($service_name, $service);
        }
        return $this;
    }

    /**
     * Remove a service
     * Protected consent mode services cannot be deleted, only hidden
     *
     * @param string $service_name The service name
     * @return $this|array For method chaining, or array with error if protected
     */
    public function remove_service($service_name) {
        // Check if this is a protected consent mode service
        if ($this->is_consent_mode_service($service_name)) {
            klaro_geo_debug_log('Cannot delete consent mode service: ' . $service_name . '. Use hide_service() instead.');
            return array(
                'error' => true,
                'message' => 'Consent mode services cannot be deleted. They can only be hidden.',
                'service' => $service_name
            );
        }

        foreach ($this->value as $key => $service) {
            if (isset($service['name']) && $service['name'] === $service_name) {
                unset($this->value[$key]);
                $this->value = array_values($this->value); // Re-index array
                $this->is_modified = true;
                break;
            }
        }

        return $this;
    }

    /**
     * Get a service's callback
     *
     * @param string $service_name The service name
     * @param string $callback_type The callback type (onInit, onAccept, onDecline)
     * @return string|null The callback or null if not found
     */
    public function get_service_callback($service_name, $callback_type) {
        $service = $this->get_service($service_name);
        
        if (!$service || !isset($service['callback']) || !isset($service['callback'][$callback_type])) {
            return null;
        }
        
        return $service['callback'][$callback_type];
    }

    /**
     * Set a service's callback
     *
     * @param string $service_name The service name
     * @param string $callback_type The callback type (onInit, onAccept, onDecline)
     * @param string $callback The callback to set
     * @return $this For method chaining
     */
    public function set_service_callback($service_name, $callback_type, $callback) {
        $service = $this->get_service($service_name);
        
        if (!$service) {
            return $this;
        }
        
        if (!isset($service['callback'])) {
            $service['callback'] = array();
        }
        
        $service['callback'][$callback_type] = $callback;
        
        return $this->set_service($service_name, $service);
    }

    /**
     * Update a service from form data
     *
     * @param string $service_name The service name
     * @param array $form_data The form data
     * @return $this For method chaining
     */
    public function update_service_from_form($service_name, $form_data) {
        if (!is_array($form_data)) {
            klaro_geo_debug_log('Invalid form data for service ' . $service_name);
            return $this;
        }
        
        $service = $this->get_service($service_name);
        
        if (!$service) {
            $service = array(
                'name' => $service_name,
                'title' => isset($form_data['title']) ? $form_data['title'] : $service_name,
                'purposes' => array(),
                'required' => false,
                'default' => false,
                'cookies' => array(),
                'callback' => array(
                    'onInit' => '',
                    'onAccept' => '',
                    'onDecline' => ''
                )
            );
        }
        
        // Update service properties
        foreach ($form_data as $key => $value) {
            // Handle special cases
            if ($key === 'purposes') {
                // Convert purposes to array if it's a string
                if (is_string($value)) {
                    $value = explode(',', $value);
                    $value = array_map('trim', $value);
                }
            } elseif ($key === 'required' || $key === 'default') {
                // Handle 'global' value for template inheritance
                if ($value === 'global') {
                    // Use null to indicate that this setting should inherit from template
                    $value = null;
                } else {
                    // Convert to boolean for explicit settings
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
            } elseif ($key === 'cookies') {
                // Convert cookies to array if it's a string
                if (is_string($value)) {
                    $value = explode(',', $value);
                    $value = array_map('trim', $value);
                }
            } elseif ($key === 'callback' && is_array($value)) {
                // Merge callbacks
                if (!isset($service['callback'])) {
                    $service['callback'] = array();
                }

                $service['callback'] = array_merge($service['callback'], $value);
                continue; // Skip setting this key directly
            } elseif ($key === 'translations' && is_array($value)) {
                // Handle translations
                if (!isset($service['translations'])) {
                    $service['translations'] = array();
                }

                // Process each language
                foreach ($value as $lang => $translations) {
                    if (!isset($service['translations'][$lang])) {
                        $service['translations'][$lang] = array();
                    }

                    // Process each translation key
                    foreach ($translations as $trans_key => $trans_value) {
                        // Handle nested structures like optOut and required
                        if (is_array($trans_value) && in_array($trans_key, array('optOut', 'required'))) {
                            if (!isset($service['translations'][$lang][$trans_key])) {
                                $service['translations'][$lang][$trans_key] = array();
                            }

                            // Set title and description
                            if (isset($trans_value['title'])) {
                                $service['translations'][$lang][$trans_key]['title'] = $trans_value['title'];
                            }

                            if (isset($trans_value['description'])) {
                                $service['translations'][$lang][$trans_key]['description'] = $trans_value['description'];
                            }
                        } else {
                            // Set the translation directly
                            $service['translations'][$lang][$trans_key] = $trans_value;
                        }
                    }

                    // Remove any service-specific translations that should be in the template
                    if ($lang === 'zz') {
                        // Remove optOut translations if they exist
                        if (isset($service['translations'][$lang]['optOut'])) {
                            unset($service['translations'][$lang]['optOut']);
                        }

                        // Remove required translations if they exist
                        if (isset($service['translations'][$lang]['required'])) {
                            unset($service['translations'][$lang]['required']);
                        }

                        // Remove purpose/purposes translations if they exist
                        if (isset($service['translations'][$lang]['purpose'])) {
                            unset($service['translations'][$lang]['purpose']);
                        }

                        if (isset($service['translations'][$lang]['purposes'])) {
                            unset($service['translations'][$lang]['purposes']);
                        }
                    }
                }

                continue; // Skip setting this key directly
            }

            $service[$key] = $value;
        }
        
        return $this->set_service($service_name, $service);
    }

    /**
     * Get services by purpose
     *
     * @param string $purpose The purpose
     * @return array The services with the specified purpose
     */
    public function get_services_by_purpose($purpose) {
        $services = array();
        
        foreach ($this->value as $service) {
            if (isset($service['purposes']) && is_array($service['purposes']) && in_array($purpose, $service['purposes'])) {
                $services[] = $service;
            }
        }
        
        return $services;
    }

    /**
     * Get all purposes
     *
     * @return array All unique purposes used by services
     */
    public function get_all_purposes() {
        $purposes = array();
        
        foreach ($this->value as $service) {
            if (isset($service['purposes']) && is_array($service['purposes'])) {
                $purposes = array_merge($purposes, $service['purposes']);
            }
        }
        
        return array_unique($purposes);
    }

    /**
     * Validate services
     *
     * @return array The validated services
     */
    public function validate_services() {
        $validated = array();

        foreach ($this->value as $service) {
            // Ensure required fields exist
            if (!isset($service['name']) || empty($service['name'])) {
                continue;
            }

            // Ensure purposes is an array
            if (!isset($service['purposes']) || !is_array($service['purposes'])) {
                $service['purposes'] = array();
            }

            // Ensure cookies is an array
            if (!isset($service['cookies']) || !is_array($service['cookies'])) {
                $service['cookies'] = array();
            }

            // Ensure callback is an array
            if (!isset($service['callback']) || !is_array($service['callback'])) {
                $service['callback'] = array();
            }

            // Handle required and default settings
            // If value is null, it means "inherit from template"
            // If value is set, ensure it's a boolean
            if (isset($service['required']) && $service['required'] !== null) {
                $service['required'] = filter_var($service['required'], FILTER_VALIDATE_BOOLEAN);
            }

            if (isset($service['default']) && $service['default'] !== null) {
                $service['default'] = filter_var($service['default'], FILTER_VALIDATE_BOOLEAN);
            }

            // Ensure translations structure is correct
            if (!isset($service['translations']) || !is_array($service['translations'])) {
                $service['translations'] = array();
            }

            // Ensure zz fallback translations exist
            if (!isset($service['translations']['zz']) || !is_array($service['translations']['zz'])) {
                $service['translations']['zz'] = array();
            }

            // Set default title and description if not set
            if (!isset($service['translations']['zz']['title']) || empty($service['translations']['zz']['title'])) {
                $service['translations']['zz']['title'] = isset($service['title']) ? $service['title'] : $service['name'];
            }

            if (!isset($service['translations']['zz']['description']) || empty($service['translations']['zz']['description'])) {
                $service['translations']['zz']['description'] = 'This service is used for ' . implode(', ', $service['purposes']) . '.';
            }

            // Remove any service-specific translations that should be in the template
            if (isset($service['translations']['zz']['optOut'])) {
                unset($service['translations']['zz']['optOut']);
            }

            if (isset($service['translations']['zz']['required'])) {
                unset($service['translations']['zz']['required']);
            }

            if (isset($service['translations']['zz']['purpose'])) {
                unset($service['translations']['zz']['purpose']);
            }

            if (isset($service['translations']['zz']['purposes'])) {
                unset($service['translations']['zz']['purposes']);
            }

            $validated[] = $service;
        }

        return $validated;
    }

    /**
     * Save services
     *
     * @return bool Whether the option was saved successfully
     */
    public function save() {
        // Validate services before saving
        $this->value = $this->validate_services();
        $this->is_modified = true;

        return parent::save();
    }

    /**
     * Check if the option has been modified
     *
     * @return bool Whether the option has been modified
     */
    public function is_modified() {
        return $this->is_modified;
    }
}