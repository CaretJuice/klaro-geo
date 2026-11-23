<?php
/**
 * Klaro Geo Option Class
 *
 * A class for handling JSON objects stored in WordPress options
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Klaro_Geo_Option
 *
 * Base class for handling JSON objects stored in WordPress options
 *
 * @phpstan-consistent-constructor
 */
class Klaro_Geo_Option {
	/**
	 * The option a subclass stores, used by get_instance() when no name is
	 * given. The base class has none: pass a name.
	 */
	const OPTION_NAME = '';

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
	 * One instance per class and option name, so an option is loaded from the
	 * database once per request.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Get the shared instance for an option.
	 *
	 * @param string|null $option_name Option name; defaults to the class's OPTION_NAME.
	 * @return static
	 */
	public static function get_instance( $option_name = null ) {
		$option_name = null === $option_name ? static::OPTION_NAME : $option_name;
		$key         = static::class . ':' . $option_name;
		if ( ! isset( self::$instances[ $key ] ) ) {
			self::$instances[ $key ] = new static( $option_name );
		}
		return self::$instances[ $key ];
	}

	/**
	 * Forget this class's shared instances, so the next get_instance() reloads
	 * from the database. Used by tests and after an option is replaced directly.
	 *
	 * @param string|null $option_name One option, or null for all of this class's.
	 */
	public static function clear_instance_cache( $option_name = null ) {
		$prefix = static::class . ':';
		foreach ( array_keys( self::$instances ) as $key ) {
			if ( 0 === strpos( $key, $prefix ) && ( null === $option_name || $key === $prefix . $option_name ) ) {
				unset( self::$instances[ $key ] );
			}
		}
	}

	/**
	 * The value as it is written to the database. Subclasses that store a
	 * different representation override this.
	 *
	 * @return mixed
	 */
	protected function value_to_store() {
		return $this->value;
	}

	/**
	 * Constructor
	 *
	 * @param string $option_name The option name in the WordPress database
	 * @param array  $default_value Default value if option doesn't exist
	 */
	public function __construct( $option_name, $default_value = array() ) {
		$this->option_name   = $option_name;
		$this->default_value = $default_value;
		$this->load();
	}

	/**
	 * Load the option from the database
	 *
	 * @return array The option value
	 */
	public function load() {
		$value = get_option( $this->option_name, $this->default_value );

		// If the value is a string, try to decode it as JSON
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$value = $decoded;
			} else {
				klaro_geo_debug_log( 'Failed to decode ' . $this->option_name . ' from JSON: ' . json_last_error_msg() );
			}
		}

		// Ensure we have an array
		if ( ! is_array( $value ) ) {
			klaro_geo_debug_log( 'Invalid value for ' . $this->option_name . ', using default' );
			$value = $this->default_value;
		}

		$this->value       = $value;
		$this->is_modified = false;

		// Single compact log entry for the load operation
		klaro_geo_debug_log( 'Loaded ' . $this->option_name . ' (' . count( $this->value ) . ' items)' );

		return $this->value;
	}

	/**
	 * Save the option to the database
	 *
	 * @return bool Whether the option was saved successfully
	 */
	public function save() {
		if ( ! $this->is_modified ) {
			return true;
		}

		// Check for any potential issues with the value
		if ( empty( $this->value ) ) {
			klaro_geo_debug_log( 'WARNING: Attempting to save empty value for ' . $this->option_name );
		}

		$result = update_option( $this->option_name, $this->value_to_store() );

		if ( $result ) {
			$this->is_modified = false;
			klaro_geo_debug_log( 'Saved ' . $this->option_name . ' (' . count( $this->value ) . ' items)' );
		} else {
			klaro_geo_debug_log( 'Failed to save ' . $this->option_name );
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
	 * @param mixed  $default Default value if key doesn't exist
	 * @return mixed The value of the key
	 */
	public function get_key( $key, $default = null ) {
		if ( isset( $this->value[ $key ] ) ) {
			return $this->value[ $key ];
		}

		return $default;
	}

	/**
	 * Set a specific key in the option
	 *
	 * @param string $key The key to set
	 * @param mixed  $value The value to set
	 * @return $this For method chaining
	 */
	public function set_key( $key, $value ) {
		if ( ! isset( $this->value[ $key ] ) || $this->value[ $key ] !== $value ) {
			$this->value[ $key ] = $value;
			$this->is_modified   = true;
		}

		return $this;
	}

	/**
	 * Remove a specific key from the option
	 *
	 * @param string $key The key to remove
	 * @return $this For method chaining
	 */
	public function remove_key( $key ) {
		if ( isset( $this->value[ $key ] ) ) {
			unset( $this->value[ $key ] );
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
	public function set( array $value ) {
		if ( $this->value !== $value ) {
			$this->value       = $value;
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
	public function has_key( $key ) {
		return isset( $this->value[ $key ] );
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
