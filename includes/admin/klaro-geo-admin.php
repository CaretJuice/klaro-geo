<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include admin files
require_once plugin_dir_path( __FILE__ ) . 'klaro-geo-admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'klaro-geo-admin-geoip-warning.php';
require_once plugin_dir_path( __FILE__ ) . 'klaro-geo-admin-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'klaro-geo-admin-countries.php';
require_once plugin_dir_path( __FILE__ ) . 'klaro-geo-admin-services.php';

// Register admin menu
function klaro_geo_admin_menu() {
	add_menu_page(
		'Klaro Geo Settings',
		'Klaro Geo',
		'manage_options',
		'klaro-geo',
		'klaro_geo_settings_page_content',
		'dashicons-shield-alt',
		600
	);

	add_submenu_page(
		'klaro-geo',
		'Templates',
		'Templates',
		'manage_options',
		'klaro-geo-templates',
		'klaro_geo_templates_page'
	);

	if ( is_plugin_active( 'geoip-detect/geoip-detect.php' ) ) {
		add_submenu_page(
			'klaro-geo',
			'Country Settings',
			'Country Settings',
			'manage_options',
			'klaro-geo-country',
			'klaro_geo_country_settings_page_content'
		);
	}

	add_submenu_page(
		'klaro-geo',
		'Klaro Services',
		'Services',
		'manage_options',
		'klaro-geo-services',
		'klaro_geo_services_page_content'
	);
}
add_action( 'admin_menu', 'klaro_geo_admin_menu' );


// Activation function
function klaro_geo_activate() {
	// Set up the default services using the service settings class
	$service_settings = Klaro_Geo_Service_Settings::get_instance();

	// Check if services already exist
	$existing_services = $service_settings->get();

	// Only set default services if they don't already exist
	if ( empty( $existing_services ) ) {
		// Set default services only on first activation
		$service_settings->set( $service_settings->get_default_services() );
		$service_settings->save();
	}

	// Set up the default templates using the template settings class
	$template_settings = Klaro_Geo_Template_Settings::get_instance();
	$default_templates = $template_settings->get_default_templates();

	// Only set default templates if they don't already exist
	if ( empty( $template_settings->get() ) ) {
		$template_settings->set( $default_templates );
		$template_settings->save();
	}

	// Floating button settings
	add_option( 'klaro_geo_enable_floating_button', false );

	// Other settings
	add_option( 'klaro_geo_enable_consent_receipts', false );
}

// On deactivation, remove the plugin's options if "Remove all plugin settings
// when deactivating" is on. The list is the one uninstall uses.
function klaro_geo_deactivate() {
	if ( ! get_option( 'klaro_geo_cleanup_on_deactivate' ) ) {
		return;
	}

	require_once dirname( __DIR__ ) . '/klaro-geo-uninstall.php';
	foreach ( klaro_geo_uninstall_option_names() as $option ) {
		delete_option( $option );
	}
}

/**
 * Authorize an admin AJAX request, or end it with a 403.
 *
 * A valid nonce only proves the request came from a page WordPress served to
 * this user; it says nothing about what the user may do. Every admin AJAX
 * handler therefore needs both checks, and calls this first.
 *
 * @param string $nonce_action The nonce action.
 * @param string $nonce_field  The request field holding the nonce.
 */
function klaro_geo_verify_admin_ajax( $nonce_action, $nonce_field = 'nonce' ) {
	if ( ! check_ajax_referer( $nonce_action, $nonce_field, false ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ), 403 );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ), 403 );
	}
}

// Default services are now defined in includes/klaro-geo-defaults.php
