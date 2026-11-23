<?php
/**
 * The country settings form is saved through one function,
 * klaro_geo_save_country_settings_form_data(), whether it arrives from the
 * Save button (admin-post.php) or the autosave (admin-ajax.php). Both get the
 * validation in Klaro_Geo_Country_Settings::update_from_form().
 *
 * @package Klaro_Geo
 */

class KlaroGeoCountrySettingsFormSaveTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		delete_option( 'klaro_geo_country_settings' );
		Klaro_Geo_Template_Settings::clear_instance_cache();
		Klaro_Geo_Country_Settings::clear_instance_cache();

		$templates = Klaro_Geo_Template_Settings::get_instance();
		$templates->set(
			array(
				'default' => array( 'name' => 'Default' ),
				'strict'  => array( 'name' => 'Strict' ),
			)
		);
		$templates->save();
	}

	public function tearDown(): void {
		Klaro_Geo_Template_Settings::clear_instance_cache();
		Klaro_Geo_Country_Settings::clear_instance_cache();
		parent::tearDown();
	}

	public function test_template_names_are_validated() {
		klaro_geo_save_country_settings_form_data(
			array(
				'default_template' => 'deleted-template',
				'US'               => array( 'template' => 'strict' ),
				'CA'               => array( 'template' => 'deleted-template' ),
			)
		);

		$stored = get_option( 'klaro_geo_country_settings' );

		$this->assertSame( 'default', $stored['default_template'], 'An unknown fallback template is replaced' );
		$this->assertSame( 'strict', $stored['US']['template'] );
		$this->assertSame( 'inherit', $stored['CA']['template'], 'An unknown country template is reset to inherit' );
	}

	public function test_countries_repeating_the_fallback_are_not_stored() {
		klaro_geo_save_country_settings_form_data(
			array(
				'default_template' => 'default',
				'US'               => array( 'template' => 'default' ),
				'FR'               => array( 'template' => 'inherit' ),
				'DE'               => array( 'template' => 'strict' ),
			)
		);

		$stored = get_option( 'klaro_geo_country_settings' );

		$this->assertArrayNotHasKey( 'US', $stored );
		$this->assertArrayNotHasKey( 'FR', $stored );
		$this->assertSame( 'strict', $stored['DE']['template'] );
	}

	public function test_submitted_values_are_sanitized() {
		klaro_geo_save_country_settings_form_data(
			array(
				'default_template' => 'default',
				'US'               => array(
					'template' => 'strict',
					'regions'  => array( 'CA' => '<b>strict</b>' ),
				),
			)
		);

		$stored = get_option( 'klaro_geo_country_settings' );

		$this->assertSame( 'strict', $stored['US']['regions']['CA'] );
	}

	/**
	 * The Save button must not post to options.php, which would store the
	 * option without going through update_from_form().
	 */
	public function test_option_is_not_registered_with_the_settings_api() {
		$this->assertArrayNotHasKey( 'klaro_geo_country_settings', get_registered_settings() );
		$this->assertNotFalse( has_action( 'admin_post_klaro_geo_save_country_settings_form', 'klaro_geo_save_country_settings_form' ) );
	}
}
