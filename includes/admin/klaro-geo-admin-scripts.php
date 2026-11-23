<?php
/**
 * Admin scripts for Klaro Geo
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and enqueue all admin scripts and styles
 */
function klaro_geo_admin_scripts( $hook ) {
	// Only load on Klaro Geo admin pages
	if ( strpos( $hook, 'klaro-geo' ) === false ) {
		return;
	}

	// 1. Enqueue common admin styles
	wp_enqueue_style(
		'klaro-geo-admin',
		KLARO_GEO_URL . 'css/klaro-geo-admin.css',
		array(),
		KLARO_GEO_VERSION
	);

	// Use WordPress core dialog styles (tabs are styled in klaro-geo-admin.css)
	wp_enqueue_style( 'wp-jquery-ui-dialog' );

	// Add translations CSS
	wp_enqueue_style(
		'klaro-geo-translations',
		KLARO_GEO_URL . 'css/klaro-geo-translations.css',
		array( 'klaro-geo-admin' ),
		KLARO_GEO_VERSION
	);

	// 2. Register the debug script first (provides klaroGeoLog function)
	wp_register_script(
		'klaro-geo-debug-js',
		KLARO_GEO_URL . 'js/klaro-geo-debug.js',
		array(),
		KLARO_GEO_VERSION,
		true
	);
	wp_enqueue_script( 'klaro-geo-debug-js' );

	// 3. Register and enqueue the main admin script (depends on debug script)
	wp_register_script(
		'klaro-geo-admin-js',
		KLARO_GEO_URL . 'js/klaro-geo-admin.js',
		array( 'jquery', 'jquery-ui-tabs', 'klaro-geo-debug-js' ),
		KLARO_GEO_VERSION,
		true
	);

	$templates = Klaro_Geo_Template_Settings::get_instance()->get();

	wp_localize_script(
		'klaro-geo-admin-js',
		'klaroGeoAdmin',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'klaro_geo_nonce' ),
		)
	);

	// The templates, for every admin script (window.klaroTemplates).
	wp_add_inline_script( 'klaro-geo-admin-js', 'window.klaroTemplates = ' . wp_json_encode( $templates ) . ';', 'before' );

	wp_enqueue_script( 'klaro-geo-admin-js' );

	// 4. Page-specific scripts

	// Services page
	if ( strpos( $hook, 'klaro-geo-services' ) !== false ) {
		// Get available purposes from settings
		$purposes = klaro_geo_get_purposes();
		$services = klaro_geo_validate_services();

		// Register and enqueue the service translations script
		wp_register_script(
			'klaro-geo-service-translations-js',
			KLARO_GEO_URL . 'js/klaro-geo-admin-services.js',
			array( 'jquery', 'jquery-ui-tabs', 'klaro-geo-admin-js' ),
			KLARO_GEO_VERSION,
			true
		);

		// Localize the services script with all the data needed
		wp_localize_script(
			'klaro-geo-service-translations-js',
			'klaroGeoServices',
			array(
				'nonce'    => wp_create_nonce( 'klaro_geo_nonce' ),
				'purposes' => $purposes,
				'services' => $services ? $services : [],
			)
		);

		wp_enqueue_script( 'klaro-geo-service-translations-js' );
	}

	// Templates page
	if ( strpos( $hook, 'klaro-geo-templates' ) !== false ) {
		// Register and enqueue the templates script
		wp_register_script(
			'klaro-geo-templates-js',
			KLARO_GEO_URL . 'js/klaro-geo-admin-templates.js',
			array( 'jquery', 'jquery-ui-tabs', 'jquery-ui-dialog', 'klaro-geo-admin-js' ),
			KLARO_GEO_VERSION,
			true
		);

		wp_enqueue_script( 'klaro-geo-templates-js' );
	}
}
add_action( 'admin_enqueue_scripts', 'klaro_geo_admin_scripts' );
