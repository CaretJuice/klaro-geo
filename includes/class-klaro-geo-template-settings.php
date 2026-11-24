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
			$this->value = array();
			foreach ( $this->get_default_templates() as $key => $template ) {
				$this->value[ $key ] = self::normalize_template( $key, $template );
			}
			$this->is_modified = true;
		}
	}

	/**
	 * Load the templates and bring each one to the shape described in
	 * normalize_template(), so code that reads templates does not re-check it.
	 *
	 * What is stored is not rewritten here. A repaired template is only written
	 * back if the templates are saved for another reason.
	 *
	 * @return array The templates
	 */
	public function load() {
		parent::load();

		foreach ( $this->value as $key => $template ) {
			$this->value[ $key ] = self::normalize_template( (string) $key, $template );
		}

		return $this->value;
	}

	/**
	 * One template in the shape the rest of the plugin relies on:
	 *
	 *   'name'            string
	 *   'config'          array, with 'translations' => array( 'zz' => array( 'purposes' => array, ... ), ... )
	 *                     and every key of klaro_geo_get_admin_true_by_default_keys()
	 *   'plugin_settings' optional array; 'gpc_purposes' in it is an array when set
	 *
	 * A config that is missing or not an array is replaced by
	 * klaro_geo_get_fail_closed_template_config(), and the log names the
	 * template so it can be re-saved. Production has been seen storing a
	 * string as a config.
	 *
	 * @param string $key      The template key, for the log.
	 * @param mixed  $template The template as stored.
	 * @return array
	 */
	public static function normalize_template( $key, $template ) {
		if ( ! is_array( $template ) ) {
			klaro_geo_debug_log( 'WARNING: Template "' . $key . '" is not an array (type: ' . gettype( $template ) . '); using the fail-closed default config. Re-save this template in Klaro Geo > Templates to rebuild it.' );
			$template = array();
		}

		if ( ! isset( $template['name'] ) || ! is_string( $template['name'] ) ) {
			$template['name'] = $key;
		}

		if ( ! isset( $template['config'] ) || ! is_array( $template['config'] ) ) {
			if ( isset( $template['config'] ) ) {
				klaro_geo_debug_log(
					'WARNING: Template "' . $key . '" has a config that is not an array (type: ' .
					gettype( $template['config'] ) . '), falling back to hardcoded defaults. ' .
					'Re-save this template in Klaro Geo > Templates to rebuild it. Value starts: ' .
					substr( (string) wp_json_encode( $template['config'] ), 0, 200 )
				);
			} else {
				klaro_geo_debug_log( 'WARNING: Template "' . $key . '" has no config; using hardcoded default template config' );
			}
			$template['config'] = klaro_geo_get_fail_closed_template_config();
		}

		$config = $template['config'];

		// Older versions stored the translations as a JSON string beside the array.
		if ( isset( $config['translations_json'] ) ) {
			$decoded = is_string( $config['translations_json'] ) ? json_decode( $config['translations_json'], true ) : null;
			if ( is_array( $decoded ) ) {
				$config['translations'] = $decoded;
			} else {
				klaro_geo_debug_log( 'Template "' . $key . '" has translations JSON that cannot be read; using its translations array' );
			}
			unset( $config['translations_json'] );
		}

		if ( ! isset( $config['translations'] ) || ! is_array( $config['translations'] ) ) {
			$config['translations'] = array();
		}
		if ( ! isset( $config['translations']['zz'] ) || ! is_array( $config['translations']['zz'] ) ) {
			$config['translations']['zz'] = array();
		}
		if ( ! isset( $config['translations']['zz']['purposes'] ) || ! is_array( $config['translations']['zz']['purposes'] ) ) {
			$config['translations']['zz']['purposes'] = array();
		}

		// Consent mode is always on; these two are no longer settings.
		if ( isset( $config['consent_mode_settings'] ) && is_array( $config['consent_mode_settings'] ) ) {
			unset( $config['consent_mode_settings']['initialization_code'], $config['consent_mode_settings']['initialize_consent_mode'] );
		}

		// The templates admin page renders these checkboxes as checked when the
		// stored config does not set them, but Klaro reads a missing key as
		// false. Without this the admin shows a setting as on while the site
		// behaves as if it were off. acceptAll is the costly one: when it is
		// not true, the notice's "That's ok" button saves the default consents
		// (usually everything declined) instead of accepting all services.
		$backfilled = array();
		foreach ( klaro_geo_get_admin_true_by_default_keys() as $admin_key ) {
			if ( ! array_key_exists( $admin_key, $config ) ) {
				$config[ $admin_key ] = true;
				$backfilled[]         = $admin_key;
			}
		}
		if ( $backfilled ) {
			klaro_geo_log_once(
				'template_backfill_' . $key,
				'Template "' . $key . '" config is missing ' . implode( ', ', $backfilled ) .
				'; using the admin default (true). Re-save the template to store these explicitly.'
			);
		}

		$template['config'] = $config;

		if ( isset( $template['plugin_settings'] ) && ! is_array( $template['plugin_settings'] ) ) {
			klaro_geo_debug_log( 'Template "' . $key . '" has plugin settings that are not an array; ignoring them' );
			unset( $template['plugin_settings'] );
		}
		if ( isset( $template['plugin_settings']['gpc_purposes'] ) && ! is_array( $template['plugin_settings']['gpc_purposes'] ) ) {
			klaro_geo_debug_log( 'Template "' . $key . '" has a GPC purpose list that is not an array; using all purposes' );
			unset( $template['plugin_settings']['gpc_purposes'] );
		}

		return $template;
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

		if ( ! $template ) {
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
