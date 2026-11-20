<?php
/**
 * Sanitizing of services posted by the services admin page.
 *
 * @package Klaro_Geo
 */

class KlaroGeoServiceSanitizeTest extends WP_UnitTestCase {

	public function test_markup_is_stripped_from_every_string_field() {
		$clean = klaro_geo_sanitize_services(
			array(
				array(
					'name'          => 'My_Service <b>x</b>',
					'title'         => 'Title<script>alert(1)</script>',
					'purposes'      => array( 'analytics<img src=x onerror=alert(1)>' ),
					'cookies'       => array( '_ga</script><script>alert(1)</script>', array( '/^_gid$/', '/', '.example.com<b>' ) ),
					'unknown_field' => '<i>kept but cleaned</i>',
					'nested'        => array( 'deep' => array( 'value' => '<u>x</u>' ) ),
					'translations'  => array( 'zz' => array( 'title' => 'T<script>alert(1)</script>' ) ),
				),
			)
		)[0];

		$this->assertStringNotContainsString( '<', wp_json_encode( $clean, JSON_UNESCAPED_SLASHES ) );
		$this->assertSame( 'my-service-x', $clean['name'] );
		$this->assertSame( 'Title', $clean['title'] );
		$this->assertSame( array( 'analytics' ), $clean['purposes'] );
		$this->assertSame( '/^_gid$/', $clean['cookies'][1][0] );
		$this->assertSame( 'kept but cleaned', $clean['unknown_field'] );
		$this->assertSame( 'x', $clean['nested']['deep']['value'] );
		$this->assertSame( 'T', $clean['translations']['zz']['title'] );
	}

	/**
	 * Service callbacks were removed. JavaScript left in a service saved by an
	 * earlier version must not survive a save.
	 */
	public function test_callback_fields_are_dropped() {
		$clean = klaro_geo_sanitize_services(
			array(
				array(
					'name'      => 'svc',
					'onInit'    => 'alert(1)',
					'onAccept'  => 'alert(2)',
					'onDecline' => 'alert(3)',
					'callback'  => array( 'onInit' => 'alert(4)' ),
				),
			)
		)[0];

		$this->assertSame( array( 'name' ), array_keys( $clean ) );
	}

	public function test_boolean_fields_and_use_global_setting() {
		$clean = klaro_geo_sanitize_services(
			array(
				array(
					'name'                    => 'svc',
					'required'                => null,   // Use Global Setting
					'default'                 => 'true',
					'optOut'                  => 1,
					'gpc_sensitive'           => false,
					'is_consent_mode_service' => true,
					'consent_mode_key'        => 'analytics_storage',
				),
			)
		)[0];

		$this->assertNull( $clean['required'] );
		$this->assertTrue( $clean['default'] );
		$this->assertTrue( $clean['optOut'] );
		$this->assertFalse( $clean['gpc_sensitive'] );
		$this->assertTrue( $clean['is_consent_mode_service'] );
		$this->assertSame( 'analytics_storage', $clean['consent_mode_key'] );
	}

	public function test_entries_that_are_not_services_are_dropped() {
		$clean = klaro_geo_sanitize_services( array( 'a string', 42, null, array( 'name' => 'real' ) ) );

		$this->assertCount( 1, $clean );
		$this->assertSame( 'real', $clean[0]['name'] );
	}

	public function test_wrong_types_become_empty_arrays() {
		$clean = klaro_geo_sanitize_services(
			array(
				array(
					'name'         => 'svc',
					'purposes'     => '<script>',
					'cookies'      => 'x',
					'translations' => 'y',
				),
			)
		)[0];

		$this->assertSame( array(), $clean['purposes'] );
		$this->assertSame( array(), $clean['cookies'] );
		$this->assertSame( array(), $clean['translations'] );
	}
}
