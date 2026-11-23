<?php
/**
 * Klaro Geo Template Settings Class
 *
 * A class for handling template settings stored in WordPress options
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
 * Class Klaro_Geo_Template_Settings
 *
 * Class for handling template settings stored in WordPress options
 */
class Klaro_Geo_Template_Settings extends Klaro_Geo_Option {
	/**
	 * The option this class stores.
	 */
	const OPTION_NAME = 'klaro_geo_templates';

	/**
	 * Constructor
	 *
	 * @param string $option_name The option name in the WordPress database
	 * @param array  $default_value Default value if option doesn't exist
	 */
	public function __construct( $option_name = 'klaro_geo_templates', $default_value = array() ) {
		parent::__construct( $option_name, $default_value );

		// If empty, initialize with default templates
		if ( empty( $this->value ) ) {
			klaro_geo_debug_log( 'No templates stored, using the default templates' );
			$this->value       = $this->get_default_templates();
			$this->is_modified = true;
		}
	}

	/**
	 * Get default templates
	 *
	 * @return array The default templates
	 */
	public function get_default_templates() {
		// Always use the global function to get default templates
		// This ensures we have a single source of truth for template definitions
		// Now get the templates from the global function
		$templates = klaro_geo_get_default_templates();

		return $templates;
	}

	/**
	 * Get a template
	 *
	 * @param string $template_key The template key
	 * @return array|null The template or null if not found
	 */
	public function get_template( $template_key ) {
		return $this->get_key( $template_key );
	}

	/**
	 * Set a template
	 *
	 * @param string $template_key The template key
	 * @param array  $template The template to set
	 * @return $this For method chaining
	 */
	public function set_template( $template_key, $template ) {
		return $this->set_key( $template_key, $template );
	}

	/**
	 * Remove a template
	 *
	 * @param string $template_key The template key
	 * @return $this For method chaining
	 */
	public function remove_template( $template_key ) {
		return $this->remove_key( $template_key );
	}

	/**
	 * Get a template's config
	 *
	 * @param string $template_key The template key
	 * @return array|null The template config or null if not found
	 */
	public function get_template_config( $template_key ) {
		$template = $this->get_template( $template_key );

		if ( ! $template || ! isset( $template['config'] ) ) {
			return null;
		}

		return $template['config'];
	}

	/**
	 * Set a template's config
	 *
	 * The config is stored as given. Form strings ('true', 'on') are turned
	 * into booleans where the form is read, by klaro_geo_sanitize_array().
	 *
	 * @param string $template_key The template key
	 * @param array  $config The config to set
	 * @return $this For method chaining
	 */
	public function set_template_config( $template_key, $config ) {
		$template = $this->get_template( $template_key );

		if ( ! $template ) {
			$template = array(
				'name'        => $template_key,
				'description' => '',
				'config'      => $config,
			);
		} else {
			// Check if we're losing translations
			if ( isset( $template['config']['translations'] ) &&
				( ! isset( $config['translations'] ) || empty( $config['translations'] ) ) ) {
				klaro_geo_debug_log( 'WARNING: Template ' . $template_key . ' is losing translations in set_template_config' );

				// Preserve the existing translations
				$config['translations'] = $template['config']['translations'];
			}

			$template['config'] = $config;
		}

		return $this->set_template( $template_key, $template );
	}

}
