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
        // Use the central function to get default services
        if (function_exists('klaro_geo_get_default_services')) {
            return klaro_geo_get_default_services();
        }

        // Fallback if the function doesn't exist yet (should never happen)
        $default_services = array(
            array(
                'name' => 'google-tag-manager',
                'title' => 'Google Tag Manager',
                'purposes' => array('analytics', 'advertising'),
                'required' => false,
                'default' => false,
                'cookies' => array(),
                'callback' => array(
                    'onInit' => "",
                    'onAccept' => "if (opts.consents.analytics || opts.consents.advertising) { for(let k of Object.keys(opts.consents)){ if (opts.consents[k]){ let eventName = 'klaro-'+k+'-accepted' dataLayer.push({'event': eventName}) } } }",
                    'onDecline' => ""
                )
            )
        );

        return $default_services;
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
     * Remove a service
     *
     * @param string $service_name The service name
     * @return $this For method chaining
     */
    public function remove_service($service_name) {
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
                // Convert to boolean
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
            
            // Ensure required and default are booleans
            $service['required'] = isset($service['required']) ? filter_var($service['required'], FILTER_VALIDATE_BOOLEAN) : false;
            $service['default'] = isset($service['default']) ? filter_var($service['default'], FILTER_VALIDATE_BOOLEAN) : false;
            
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