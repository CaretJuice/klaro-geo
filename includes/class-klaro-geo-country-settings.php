<?php
/**
 * Klaro Geo Country Settings Class
 *
 * A class for handling country settings stored in WordPress options
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the base option class if not already included
if ( ! class_exists( 'Klaro_Geo_Option' ) ) {
	require_once __DIR__ . '/class-klaro-geo-option.php';
}

// Include the template settings class if not already included
if ( ! class_exists( 'Klaro_Geo_Template_Settings' ) ) {
	require_once __DIR__ . '/class-klaro-geo-template-settings.php';
}

/**
 * Class Klaro_Geo_Country_Settings
 *
 * Class for handling country settings stored in WordPress options
 */
class Klaro_Geo_Country_Settings extends Klaro_Geo_Option {
	/**
	 * The option this class stores.
	 */
	const OPTION_NAME = 'klaro_geo_country_settings';

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
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
		// Other major countries
		'US',
		'GB',
		'BR',
		'CA',
		'CN',
		'IN',
		'JP',
		'AU',
		'KR',
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
	 * @param array  $default_value Default value if option doesn't exist
	 */
	public function __construct( $option_name = 'klaro_geo_country_settings', $default_value = array() ) {
		// No stored default_template is handled by get_default_template().
		parent::__construct( $option_name, $default_value );
		$this->load_visible_countries();
	}

	/**
	 * Load the option and bring every country entry to one shape:
	 * array( 'template' => string, 'regions' => array( region code => template key ) ).
	 *
	 * Earlier versions stored a region's template directly on the country
	 * ( 'US' => array( 'template' => 'x', 'CA' => 'y' ) ) or as
	 * array( 'template' => 'y' ). This is the one place those are read, so
	 * everything after it can rely on the shape above.
	 *
	 * @return array The option value
	 */
	public function load() {
		parent::load();

		foreach ( $this->value as $code => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$normalized = self::normalize_country( $config );
			if ( $normalized !== $config ) {
				klaro_geo_debug_log( 'Country ' . $code . ' is stored in an old region format, read as: ' . wp_json_encode( $normalized ) );
				$this->value[ $code ] = $normalized;
			}
		}

		return $this->value;
	}

	/**
	 * One country entry in the shape described in load().
	 *
	 * @param array $config The stored entry.
	 * @return array
	 */
	private static function normalize_country( array $config ) {
		$regions = isset( $config['regions'] ) && is_array( $config['regions'] ) ? $config['regions'] : array();
		foreach ( $regions as $region_code => $region ) {
			if ( is_array( $region ) ) {
				$regions[ $region_code ] = isset( $region['template'] ) ? $region['template'] : '';
			}
		}

		$normalized = array();
		foreach ( $config as $key => $value ) {
			if ( 'template' === $key || 'regions' === $key ) {
				continue;
			}
			if ( is_string( $value ) ) {
				// Old flat format: a region code directly on the country.
				$regions[ $key ] = $value;
			} else {
				$normalized[ $key ] = $value;
			}
		}

		if ( isset( $config['template'] ) ) {
			$normalized = array( 'template' => $config['template'] ) + $normalized;
		}
		if ( isset( $config['regions'] ) || ! empty( $regions ) ) {
			$normalized['regions'] = $regions;
		}

		return $normalized;
	}

	/**
	 * Load visible countries from the database
	 *
	 * @return array The visible countries
	 */
	public function load_visible_countries() {
		$this->visible_countries = get_option( $this->visible_countries_option, $this->default_visible_countries );

		// Ensure we have an array
		if ( ! is_array( $this->visible_countries ) ) {
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
	public function save_visible_countries( $countries = null ) {
		if ( $countries !== null ) {
			$this->visible_countries = $countries;
		}

		return update_option( $this->visible_countries_option, $this->visible_countries );
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
	public function set_visible_countries( array $countries ) {
		$this->visible_countries = $countries;

		return $this;
	}

	/**
	 * Get a country's settings
	 *
	 * @param string $country_code The country code
	 * @return array|null The country settings or null if not found
	 */
	public function get_country( $country_code ) {
		return $this->get_key( $country_code );
	}

	/**
	 * Set a country's settings
	 *
	 * @param string $country_code The country code
	 * @param array  $settings The settings to set
	 * @return $this For method chaining
	 */
	public function set_country( $country_code, $settings ) {
		return $this->set_key( $country_code, $settings );
	}

	/**
	 * Get the default template
	 *
	 * @return string The default template
	 */
	public function get_default_template() {
		// Get the fallback template from settings
		$fallback_template = $this->get_key( 'default_template', '' );

		// If no fallback template is set, try to get the first available template
		if ( empty( $fallback_template ) ) {
			$templates = Klaro_Geo_Template_Settings::get_instance()->get();

			// If there are templates, use the first one
			if ( ! empty( $templates ) ) {
				$template_keys     = array_keys( $templates );
				$fallback_template = reset( $template_keys );
				klaro_geo_debug_log( 'No fallback template set, using first available template: ' . $fallback_template );
			} else {
				// If no templates are available, use an empty string
				// This will be handled by the template loading code
				$fallback_template = '';
				klaro_geo_debug_log( 'No templates available, using empty fallback template' );
			}
		}

		return $fallback_template;
	}

	/**
	 * Update country settings from form data
	 *
	 * @param array $submitted_settings The submitted settings
	 * @return $this For method chaining
	 */
	public function update_from_form( array $submitted_settings ) {
		// Get available templates for validation
		$template_settings   = Klaro_Geo_Template_Settings::get_instance();
		$available_templates = $template_settings->get();
		// Template keys are array keys, so a template named "2024" has the integer key 2024.
		$available_keys = array_map( 'strval', array_keys( $available_templates ) );

		// Get the default template and validate it exists
		$default_template = isset( $submitted_settings['default_template'] ) ? $submitted_settings['default_template'] : 'default';
		if ( ! empty( $available_keys ) && ! in_array( $default_template, $available_keys, true ) && $default_template !== 'inherit' ) {
			klaro_geo_debug_log( 'WARNING: Default template "' . $default_template . '" does not exist in templates database. Available: ' . implode( ', ', $available_keys ) );
			// Fall back to first available template or 'default'
			$default_template = in_array( 'default', $available_keys, true ) ? 'default' : $available_keys[0];
			klaro_geo_debug_log( 'Falling back to template: ' . $default_template );
		}

		// Get current settings to preserve region settings
		$current_settings = $this->get();

		// Create a new settings array with the default_template
		$new_settings = array(
			'default_template' => $default_template,
		);

		// Process each country in the submitted settings
		foreach ( $submitted_settings as $code => $config ) {
			// Skip the default_template key
			if ( $code === 'default_template' ) {
				continue;
			}

			// Remove the _is_default flag if it exists
			if ( isset( $config['_is_default'] ) ) {
				unset( $config['_is_default'] );
			}

			// Check if regions are included in the submitted settings
			if ( isset( $config['regions'] ) && is_array( $config['regions'] ) ) {
				// Filter out empty regions
				$config['regions'] = array_filter(
					$config['regions'],
					function ( $value ) {
						return ! empty( $value ) && $value !== 'default' && $value !== 'inherit';
					}
				);
			} elseif ( isset( $current_settings[ $code ] ) && isset( $current_settings[ $code ]['regions'] ) ) {
				$config['regions'] = $current_settings[ $code ]['regions'];
			} else {
				// Ensure regions key exists
				$config['regions'] = array();
			}

			// If the country uses the default template or inherits from fallback and has no regions, skip it
			if ( isset( $config['template'] ) &&
				( $config['template'] === $default_template || $config['template'] === 'inherit' ) &&
				( ! isset( $config['regions'] ) || empty( $config['regions'] ) ) ) {
				continue;
			}

			// Validate country template exists (if not 'inherit')
			if ( isset( $config['template'] ) && $config['template'] !== 'inherit' && ! empty( $available_keys ) ) {
				if ( ! in_array( $config['template'], $available_keys, true ) ) {
					klaro_geo_debug_log( 'WARNING: Country ' . $code . ' references non-existent template "' . $config['template'] . '". Available: ' . implode( ', ', $available_keys ) );
					// Fall back to inherit (use fallback template)
					$config['template'] = 'inherit';
				}
			}

			// Add the country to the new settings
			$new_settings[ $code ] = $config;
		}

		// Check for countries with region settings that might not be in the submitted settings
		foreach ( $current_settings as $code => $config ) {
			if ( $code === 'default_template' ) {
				continue;
			}

			// If country is not in new settings but has region overrides, preserve it
			if ( ! isset( $new_settings[ $code ] ) && isset( $config['regions'] ) && ! empty( $config['regions'] ) ) {
				$new_settings[ $code ] = $config;
			}
		}

		// Replace the old settings with the new ones
		$this->set( $new_settings );

		return $this;
	}

	/**
	 * Update region settings from AJAX data
	 *
	 * @param array $settings The region settings
	 * @return $this For method chaining
	 */
	public function update_regions_from_ajax( array $settings ) {
		// Update region settings for each country
		foreach ( $settings as $country => $country_data ) {
			// Get existing country settings or create new ones
			$country_settings = $this->get_country( $country );

			if ( ! $country_settings ) {
				$country_settings = array(
					'template' => $this->get_default_template(),
				);
			}

			// Make sure we preserve the existing template setting
			if ( ! isset( $country_settings['template'] ) ) {
				$country_settings['template'] = $this->get_default_template();
			}

			// Check if regions is defined in the incoming data
			if ( isset( $country_data['regions'] ) && is_array( $country_data['regions'] ) ) {
				// Initialize regions array if it doesn't exist
				if ( ! isset( $country_settings['regions'] ) ) {
					$country_settings['regions'] = array();
				}

				// Process each region
				foreach ( $country_data['regions'] as $region_code => $template ) {
					// Only save if the value is not "inherit" or "default" (use country default)
					if ( $template !== 'inherit' && $template !== 'default' ) {
						$country_settings['regions'][ $region_code ] = $template;
					} elseif ( isset( $country_settings['regions'][ $region_code ] ) ) {
						// If the value is "inherit" or "default", remove any existing override
						unset( $country_settings['regions'][ $region_code ] );
					}
				}
			}

			// Ensure regions key exists
			if ( ! isset( $country_settings['regions'] ) ) {
				$country_settings['regions'] = array();
			}

			// Update the country settings
			$this->set_country( $country, $country_settings );
		}

		return $this;
	}

	/**
	 * Get the effective settings for a location, considering inheritance
	 *
	 * @param string $location_code The location code (e.g., 'US' or 'US-CA')
	 * @param bool   $is_admin_override Optional. Whether this location is from an admin override. Default false.
	 * @return array The effective settings for the location
	 */
	public function get_effective_settings( $location_code, $is_admin_override = false ) {
		// Split location code to check if it's a region request
		$parts        = explode( '-', $location_code );
		$country_code = $parts[0];
		$region_code  = isset( $parts[1] ) ? $parts[1] : null;

		// Get the default template
		$default_template = $this->get_default_template();

		// Default settings
		$effective_settings = array(
			'template' => $default_template, // Use the default template from settings
			'source'   => 'default', // Track where the template came from
		);

		// Check if the country is in the visible countries list
		if ( in_array( $country_code, $this->visible_countries, true ) ) {
			// If the country is not in the settings, it means it's using the default template
			if ( ! $this->has_key( $country_code ) ) {
				// Country is using default template
				$effective_settings['template'] = $this->get_default_template();
				$effective_settings['source']   = 'default';
			} else {
				// Country has specific settings
				$country_settings = $this->get_country( $country_code );

				// Check if the country is set to inherit from fallback template
				if ( isset( $country_settings['template'] ) && $country_settings['template'] === 'inherit' ) {
					$effective_settings['template'] = $this->get_default_template();
					$effective_settings['source']   = 'fallback';
				} else {
					$effective_settings['template'] = $country_settings['template'] ?? $effective_settings['template'];
					$effective_settings['source']   = 'country';
				}

				// Check for region override
				if ( $region_code && isset( $country_settings['regions'] ) &&
					isset( $country_settings['regions'][ $region_code ] ) ) {
					$region_template = $country_settings['regions'][ $region_code ];

					// A region set to inherit keeps the country's template.
					if ( 'inherit' !== $region_template ) {
						$effective_settings['template'] = $region_template;
						$effective_settings['source']   = 'region';
					}
				}
			}
		}

		// Allow filtering of effective settings
		$effective_settings = apply_filters( 'klaro_geo_effective_settings', $effective_settings );

		// Get the template configuration to verify it exists (using class methods)
		// IMPORTANT: Always use database templates - no hardcoded fallbacks here
		// Per contract: Database values MUST be preferred over hardcoded defaults
		$template_settings = Klaro_Geo_Template_Settings::get_instance();
		$templates         = $template_settings->get();

		// Allow filtering of templates
		$templates = apply_filters( 'klaro_geo_templates', $templates );

		if ( ! isset( $templates[ $effective_settings['template'] ] ) ) {
			// Fall back to the fallback template if the selected one doesn't exist
			$fallback_template = $this->get_default_template();
			klaro_geo_debug_log( 'WARNING: Template "' . $effective_settings['template'] . '" for ' . $location_code . ' not found in templates (available: ' . implode( ', ', array_keys( $templates ) ) . '); falling back to "' . $fallback_template . '"' );
			$effective_settings['template'] = $fallback_template;
			$effective_settings['source']   = 'fallback';
		}

		klaro_geo_debug_log( 'Template for ' . $location_code . ( $is_admin_override ? ' (admin override)' : '' ) . ': ' . $effective_settings['template'] . ', source=' . $effective_settings['source'] );
		return $effective_settings;
	}
}
