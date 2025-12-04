<?php
/**
 * Klaro Geo Option Class
 *
 * A class for handling JSON objects stored in WordPress options
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Class Klaro_Geo_Option
 * 
 * Base class for handling JSON objects stored in WordPress options
 */
class Klaro_Geo_Option {
    /**
     * The option name in the WordPress database
     *
     * @var string
     */
    protected $option_name;

    /**
     * Default value if option doesn't exist
     *
     * @var array
     */
    protected $default_value;

    /**
     * Current value of the option
     *
     * @var array
     */
    protected $value;

    /**
     * Whether the option has been modified
     *
     * @var bool
     */
    protected $is_modified = false;

    /**
     * Constructor
     *
     * @param string $option_name The option name in the WordPress database
     * @param array $default_value Default value if option doesn't exist
     */
    public function __construct($option_name, $default_value = array()) {
        $this->option_name = $option_name;
        $this->default_value = $default_value;
        $this->load();
    }

    /**
     * Load the option from the database
     *
     * @return array The option value
     */
    public function load() {
        $value = get_option($this->option_name, $this->default_value);

        // If the value is a string, try to decode it as JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                klaro_geo_debug_log('Failed to decode ' . $this->option_name . ' from JSON: ' . json_last_error_msg());
            }
        }

        // Ensure we have an array
        if (!is_array($value)) {
            klaro_geo_debug_log('Invalid value for ' . $this->option_name . ', using default');
            $value = $this->default_value;
        }

        $this->value = $value;
        $this->is_modified = false;

        // Single compact log entry for the load operation
        klaro_geo_debug_log('Loaded ' . $this->option_name . ' (' . count($this->value) . ' items)');

        return $this->value;
    }

    /**
     * Save the option to the database
     *
     * @return bool Whether the option was saved successfully
     */
    public function save() {
        if (!$this->is_modified) {
            return true;
        }

        // Check for any potential issues with the value
        if (empty($this->value)) {
            klaro_geo_debug_log('WARNING: Attempting to save empty value for ' . $this->option_name);
        }

        // Special handling for services - always save as JSON string
        if ($this->option_name === 'klaro_geo_services') {
            $json_value = wp_json_encode($this->value, JSON_PRETTY_PRINT);
            $result = update_option($this->option_name, $json_value);
        } else {
            // Save the option as is for other options
            $result = update_option($this->option_name, $this->value);
        }

        if ($result) {
            $this->is_modified = false;
            klaro_geo_debug_log('Saved ' . $this->option_name . ' (' . count($this->value) . ' items)');
        } else {
            klaro_geo_debug_log('Failed to save ' . $this->option_name);
        }

        return $result;
    }

    /**
     * Get the entire option value
     *
     * @return array The option value
     */
    public function get() {
        return $this->value;
    }

    /**
     * Get a specific key from the option
     *
     * @param string $key The key to get
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The value of the key
     */
    public function get_key($key, $default = null) {
        if (isset($this->value[$key])) {
            return $this->value[$key];
        }
        
        return $default;
    }

    /**
     * Set a specific key in the option
     *
     * @param string $key The key to set
     * @param mixed $value The value to set
     * @return $this For method chaining
     */
    public function set_key($key, $value) {
        if (!isset($this->value[$key]) || $this->value[$key] !== $value) {
            $this->value[$key] = $value;
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Remove a specific key from the option
     *
     * @param string $key The key to remove
     * @return $this For method chaining
     */
    public function remove_key($key) {
        if (isset($this->value[$key])) {
            unset($this->value[$key]);
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Set the entire option value
     *
     * @param array $value The new value
     * @return $this For method chaining
     */
    public function set($value) {
        if (!is_array($value)) {
            klaro_geo_debug_log('Invalid value for ' . $this->option_name . ', must be an array');
            return $this;
        }
        
        if ($this->value !== $value) {
            $this->value = $value;
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Check if a key exists in the option
     *
     * @param string $key The key to check
     * @return bool Whether the key exists
     */
    public function has_key($key) {
        return isset($this->value[$key]);
    }

    /**
     * Get a nested value from the option
     *
     * @param array $keys Array of keys to traverse
     * @param mixed $default Default value if path doesn't exist
     * @return mixed The value at the specified path
     */
    public function get_nested($keys, $default = null) {
        $value = $this->value;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * Set a nested value in the option
     *
     * @param array $keys Array of keys to traverse
     * @param mixed $value The value to set
     * @return $this For method chaining
     */
    public function set_nested($keys, $value) {
        if (empty($keys)) {
            return $this;
        }
        
        $reference = &$this->value;
        $last_key = array_pop($keys);
        
        foreach ($keys as $key) {
            if (!isset($reference[$key]) || !is_array($reference[$key])) {
                $reference[$key] = array();
            }
            
            $reference = &$reference[$key];
        }
        
        if (!isset($reference[$last_key]) || $reference[$last_key] !== $value) {
            $reference[$last_key] = $value;
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Remove a nested value from the option
     *
     * @param array $keys Array of keys to traverse
     * @return $this For method chaining
     */
    public function remove_nested($keys) {
        if (empty($keys)) {
            return $this;
        }
        
        $reference = &$this->value;
        $last_key = array_pop($keys);
        
        foreach ($keys as $key) {
            if (!isset($reference[$key]) || !is_array($reference[$key])) {
                return $this;
            }
            
            $reference = &$reference[$key];
        }
        
        if (isset($reference[$last_key])) {
            unset($reference[$last_key]);
            $this->is_modified = true;
        }
        
        return $this;
    }

    /**
     * Merge an array into the option
     *
     * @param array $value The array to merge
     * @param bool $recursive Whether to merge recursively
     * @return $this For method chaining
     */
    public function merge($value, $recursive = true) {
        if (!is_array($value)) {
            klaro_geo_debug_log('Invalid value for merge, must be an array');
            return $this;
        }
        
        if ($recursive) {
            $this->value = array_merge_recursive($this->value, $value);
        } else {
            $this->value = array_merge($this->value, $value);
        }
        
        $this->is_modified = true;
        
        return $this;
    }

    /**
     * Reset the option to its default value
     *
     * @return $this For method chaining
     */
    public function reset() {
        $this->value = $this->default_value;
        $this->is_modified = true;

        return $this;
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