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
	$service_settings           = Klaro_Geo_Service_Settings::get_instance();
	$klaro_geo_default_services = $service_settings->get_default_services();

	// Make default services available globally
	$GLOBALS['klaro_geo_default_services'] = $klaro_geo_default_services;

	// Check if services already exist
	$existing_services = $service_settings->get();

	// Only set default services if they don't already exist
	if ( empty( $existing_services ) ) {
		// Set default services only on first activation
		$service_settings->set( $klaro_geo_default_services );
		$service_settings->save();
	}

	// Set default geo settings
	add_option( 'klaro_geo_country_settings', wp_json_encode( klaro_geo_get_default_geo_settings() ) );

	// Set up the default templates using the template settings class
	$template_settings = new Klaro_Geo_Template_Settings();
	$default_templates = $template_settings->get_default_templates();

	// Only set default templates if they don't already exist
	if ( empty( $template_settings->get() ) ) {
		$template_settings->set( $default_templates );
		$template_settings->save();
	}

	// Floating button settings
	add_option( 'klaro_geo_enable_floating_button', false );
	add_option( 'klaro_geo_button_text', 'Manage Consent' );
	add_option( 'klaro_geo_button_theme', 'light' );

	// Other settings
	add_option( 'klaro_geo_js_version', '0.7' );
	add_option( 'klaro_geo_debug_countries', [ 'US', 'UK', 'CA', 'FR', 'AU', 'US-CA', 'CA-QC' ] );
	add_option( 'klaro_geo_enable_consent_receipts', false );
}

// Deactivation function
function klaro_geo_deactivate() {
	// Only delete options if cleanup is enabled
	if ( get_option( 'klaro_geo_cleanup_on_deactivate' ) ) {
		// Template settings
		delete_option( 'klaro_geo_templates' );

		// Country and region settings
		delete_option( 'klaro_geo_country_settings' );

		// Service and purpose settings
		delete_option( 'klaro_geo_services' );
		delete_option( 'klaro_geo_analytics_purposes' );
		delete_option( 'klaro_geo_ad_purposes' );
		delete_option( 'klaro_geo_purposes' );

		// GTM settings
		delete_option( 'klaro_geo_gtm_id' );
		delete_option( 'klaro_geo_consent_mode_type' );
		delete_option( 'klaro_geo_gtm_oninit' );
		delete_option( 'klaro_geo_gtm_onaccept' );
		delete_option( 'klaro_geo_gtm_ondecline' );

		// Consent Mode settings
		delete_option( 'klaro_geo_consent_mode_settings' );
		delete_option( 'klaro_geo_analytics_storage_service' );
		// Legacy option removed
		delete_option( 'klaro_geo_initialization_code' );

		// Other settings
		delete_option( 'klaro_geo_js_version' );
		delete_option( 'klaro_geo_js_variant' );
		delete_option( 'klaro_geo_fallback_behavior' );
		delete_option( 'klaro_geo_debug_countries' );
		delete_option( 'klaro_geo_debug_geo' );
		delete_option( 'klaro_geo_enable_consent_receipts' );
		delete_option( 'klaro_geo_cleanup_on_deactivate' );

		// Button settings
		delete_option( 'klaro_geo_enable_floating_button' );
		delete_option( 'klaro_geo_button_text' );
		delete_option( 'klaro_geo_button_theme' );
		delete_option( 'klaro_geo_floating_button_text' );
		delete_option( 'klaro_geo_floating_button_theme' );
		delete_option( 'klaro_geo_floating_button_position' );
	}
}

// Helper function to get default geo settings
function klaro_geo_get_default_geo_settings() {
	// Get templates
	$templates = get_option( 'klaro_geo_templates', array() );

	// If there are templates, use the first one as the default
	if ( ! empty( $templates ) ) {
		$template_keys    = array_keys( $templates );
		$default_template = reset( $template_keys );
	} else {
		// If no templates are available, use an empty string
		// The get_default_template method will handle this case
		$default_template = '';
	}

	return array(
		'default_template' => $default_template,
		'countries'        => array(),
	);
}

// Default services are now defined in includes/klaro-geo-defaults.php

