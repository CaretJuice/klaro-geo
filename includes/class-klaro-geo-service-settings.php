<?php
/**
 * Klaro Geo Service Settings Class
 *
 * A class for handling service settings stored in WordPress options
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the base option class if not already included
if ( ! class_exists( 'Klaro_Geo_Option' ) ) {
	require_once __DIR__ . '/class-klaro-geo-option.php';
}

/**
 * Class Klaro_Geo_Service_Settings
 *
 * Class for handling service settings stored in WordPress options
 */
class Klaro_Geo_Service_Settings extends Klaro_Geo_Option {
	/**
	 * The option this class stores.
	 */
	const OPTION_NAME = 'klaro_geo_services';

	/**
	 * Constructor
	 *
	 * @param string $option_name The option name in the WordPress database
	 * @param array  $default_value Default value if option doesn't exist
	 */
	public function __construct( $option_name = 'klaro_geo_services', $default_value = array() ) {
		parent::__construct( $option_name, $default_value );

		// Nothing stored, or not a list of services: use the defaults. This is
		// the one place that decides it, so callers do not re-check the shape.
		if ( ! isset( $this->value[0]['name'] ) ) {
			klaro_geo_debug_log( 'Stored services are empty or invalid, using the default services' );
			$this->value       = $this->get_default_services();
			$this->is_modified = true;
		}

		// Bring every service to the shape validate_services() describes, so
		// code that reads services does not re-check it. A change is saved by
		// klaro_geo_validate_services() on init.
		$validated = $this->validate_services();
		if ( $validated !== $this->value ) {
			$this->value       = $validated;
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
		// Now get the services from the global function
		$services = klaro_geo_get_default_services();

		return $services;
	}

	/**
	 * Get a service by name
	 *
	 * @param string $service_name The service name
	 * @return array|null The service or null if not found
	 */
	public function get_service( $service_name ) {
		foreach ( $this->value as $service ) {
			if ( isset( $service['name'] ) && $service['name'] === $service_name ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Set a service
	 *
	 * @param string $service_name The service name
	 * @param array  $service The service to set
	 * @return $this For method chaining
	 */
	public function set_service( $service_name, $service ) {
		$found = false;

		// Update existing service
		foreach ( $this->value as $key => $existing_service ) {
			if ( isset( $existing_service['name'] ) && $existing_service['name'] === $service_name ) {
				$this->value[ $key ] = $service;
				$found               = true;
				$this->is_modified   = true;
				break;
			}
		}

		// Add new service if not found
		if ( ! $found ) {
			$this->value[]     = $service;
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
	public function is_consent_mode_service( $service_name ) {
		$service = $this->get_service( $service_name );
		if ( ! $service ) {
			return false;
		}
		return isset( $service['is_consent_mode_service'] ) && $service['is_consent_mode_service'] === true;
	}

	/**
	 * Remove a service
	 * Protected consent mode services cannot be deleted, only hidden
	 *
	 * @param string $service_name The service name
	 * @return $this|array For method chaining, or array with error if protected
	 */
	public function remove_service( $service_name ) {
		// Check if this is a protected consent mode service
		if ( $this->is_consent_mode_service( $service_name ) ) {
			klaro_geo_debug_log( 'Cannot delete consent mode service: ' . $service_name . '. Mark it hidden instead.' );
			return array(
				'error'   => true,
				'message' => 'Consent mode services cannot be deleted. They can only be hidden.',
				'service' => $service_name,
			);
		}

		foreach ( $this->value as $key => $service ) {
			if ( isset( $service['name'] ) && $service['name'] === $service_name ) {
				unset( $this->value[ $key ] );
				$this->value       = array_values( $this->value ); // Re-index array
				$this->is_modified = true;
				break;
			}
		}

		return $this;
	}

	/**
	 * The services in the shape the rest of the plugin relies on: every
	 * service has a name, 'purposes' and 'cookies' arrays, and a 'zz'
	 * translation with a title and description; 'required' and 'default' are
	 * booleans when set. A service without a name is dropped.
	 *
	 * Applied when the option is loaded and again before it is saved.
	 *
	 * @return array The validated services
	 */
	public function validate_services() {
		$validated = array();

		foreach ( $this->value as $service ) {
			// Ensure required fields exist
			if ( ! isset( $service['name'] ) || empty( $service['name'] ) ) {
				continue;
			}

			// Ensure purposes is an array
			if ( ! isset( $service['purposes'] ) || ! is_array( $service['purposes'] ) ) {
				$service['purposes'] = array();
			}

			// Ensure cookies is an array
			if ( ! isset( $service['cookies'] ) || ! is_array( $service['cookies'] ) ) {
				$service['cookies'] = array();
			}

			// Handle required and default settings
			// If value is null, it means "inherit from template"
			// If value is set, ensure it's a boolean
			if ( isset( $service['required'] ) ) {
				$service['required'] = filter_var( $service['required'], FILTER_VALIDATE_BOOLEAN );
			}

			if ( isset( $service['default'] ) ) {
				$service['default'] = filter_var( $service['default'], FILTER_VALIDATE_BOOLEAN );
			}

			// Ensure translations structure is correct
			if ( ! isset( $service['translations'] ) || ! is_array( $service['translations'] ) ) {
				$service['translations'] = array();
			}

			// Ensure zz fallback translations exist
			if ( ! isset( $service['translations']['zz'] ) || ! is_array( $service['translations']['zz'] ) ) {
				$service['translations']['zz'] = array();
			}

			// Set default title and description if not set
			if ( ! isset( $service['translations']['zz']['title'] ) || empty( $service['translations']['zz']['title'] ) ) {
				$service['translations']['zz']['title'] = isset( $service['title'] ) ? $service['title'] : $service['name'];
			}

			if ( ! isset( $service['translations']['zz']['description'] ) || empty( $service['translations']['zz']['description'] ) ) {
				$service['translations']['zz']['description'] = 'This service is used for ' . implode( ', ', $service['purposes'] ) . '.';
			}

			// Remove any service-specific translations that should be in the template
			if ( isset( $service['translations']['zz']['optOut'] ) ) {
				unset( $service['translations']['zz']['optOut'] );
			}

			if ( isset( $service['translations']['zz']['required'] ) ) {
				unset( $service['translations']['zz']['required'] );
			}

			if ( isset( $service['translations']['zz']['purpose'] ) ) {
				unset( $service['translations']['zz']['purpose'] );
			}

			if ( isset( $service['translations']['zz']['purposes'] ) ) {
				unset( $service['translations']['zz']['purposes'] );
			}

			$validated[] = $service;
		}

		return $validated;
	}

	/**
	 * Services are stored as a JSON string.
	 */
	protected function value_to_store() {
		return wp_json_encode( $this->value, JSON_PRETTY_PRINT );
	}

	/**
	 * Save services
	 *
	 * @return bool Whether the option was saved successfully
	 */
	public function save() {
		// Validate services before saving
		$this->value       = $this->validate_services();
		$this->is_modified = true;

		return parent::save();
	}
}
