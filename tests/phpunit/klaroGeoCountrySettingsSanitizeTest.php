<?php
/**
 * Class CountrySettingsSanitizeTest
 *
 * The klaro_geo_country_settings sanitize_callback uses map_deep(), which
 * applies sanitize_text_field() to every scalar including booleans -- true
 * becomes '1' and false becomes ''. This asserts the option's real structure
 * survives a round trip unchanged.
 *
 * Sibling of TemplatesSanitizeCallbackTest, which covers the same class of bug
 * in the templates option.
 *
 * @package Klaro_Geo
 */

class CountrySettingsSanitizeTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Installs the sanitize_option_{$option} filters without firing
		// admin_init, which sends headers in the test harness.
		klaro_geo_register_country_settings();
	}

	/**
	 * The shape the countries admin form actually posts -- a default template,
	 * per-country templates and nested region maps -- must round trip unchanged.
	 */
	public function test_real_shape_survives_sanitisation() {
		$settings = array(
			'default_template'  => 'default',
			'fallback_behavior' => 'default',
			'US'                => array(
				'template' => 'relaxed',
				'regions'  => array(
					'TX' => 'relaxed',
					'CA' => 'strict',
				),
			),
			'IN'                => array(
				'template' => 'strict',
				'regions'  => array(),
			),
		);

		$country_settings = new Klaro_Geo_Country_Settings();
		$country_settings->set( $settings );
		$country_settings->save();

		$stored = $country_settings->get();
		$this->assertNotEmpty( $stored, 'Precondition: settings should be stored' );

		$sanitized = sanitize_option( 'klaro_geo_country_settings', $stored );

		$this->assertSame(
			$stored,
			$sanitized,
			'Country settings must survive sanitisation unchanged'
		);
	}

	/**
	 * Booleans anywhere in the structure must not be flattened.
	 */
	public function test_booleans_are_not_flattened() {
		$settings = array(
			'default_template' => 'default',
			'US'               => array(
				'template' => 'strict',
				'enabled'  => true,
				'disabled' => false,
				'regions'  => array( 'TX' => 'relaxed' ),
			),
		);

		$sanitized = sanitize_option( 'klaro_geo_country_settings', $settings );

		$this->assertTrue( $sanitized['US']['enabled'], 'true must stay boolean true' );
		$this->assertFalse( $sanitized['US']['disabled'], 'false must stay boolean false' );
		$this->assertSame(
			array( 'TX' => 'relaxed' ),
			$sanitized['US']['regions'],
			'Nested region arrays must be preserved'
		);
	}
}
