<?php
/**
 * Activating the plugin on a site that has never had it.
 *
 * @package Klaro_Geo
 */

class KlaroGeoActivationTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'klaro_geo_templates' );
		delete_option( 'klaro_geo_services' );
		Klaro_Geo_Template_Settings::clear_instance_cache();
		Klaro_Geo_Service_Settings::clear_instance_cache();
	}

	public function tear_down() {
		Klaro_Geo_Template_Settings::clear_instance_cache();
		Klaro_Geo_Service_Settings::clear_instance_cache();
		parent::tear_down();
	}

	/**
	 * The defaults are stored, not only handed out on every read: the admin
	 * pages and anything else that reads the options find them there.
	 */
	public function test_first_activation_stores_the_default_templates_and_services() {
		$this->assertFalse( get_option( 'klaro_geo_templates' ) );

		klaro_geo_activate();

		$templates = get_option( 'klaro_geo_templates' );
		$this->assertIsArray( $templates );
		$this->assertSame( array_keys( klaro_geo_get_default_templates() ), array_keys( $templates ) );
		$this->assertNotEmpty( $templates['default']['config'] );

		$services = get_option( 'klaro_geo_services' );
		$services = is_string( $services ) ? json_decode( $services, true ) : $services;
		$this->assertContains( 'google-tag-manager', wp_list_pluck( $services, 'name' ) );
	}

	public function test_activation_leaves_stored_templates_alone() {
		$stored = array(
			'only' => array(
				'name'   => 'Only Template',
				'config' => array( 'default' => true ),
			),
		);
		update_option( 'klaro_geo_templates', $stored );
		Klaro_Geo_Template_Settings::clear_instance_cache();

		klaro_geo_activate();

		$this->assertSame( $stored, get_option( 'klaro_geo_templates' ) );
	}
}
