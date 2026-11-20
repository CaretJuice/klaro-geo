<?php
/**
 * A service's consent key and default, as used by the frontend config and by
 * the preview on the Templates page.
 *
 * @package Klaro_Geo
 */

class KlaroGeoServiceConsentDefaultTest extends WP_UnitTestCase {

	public function test_consent_mode_services_use_their_google_key() {
		$this->assertSame(
			'analytics_storage',
			klaro_geo_service_consent_key(
				array(
					'name'                    => 'analytics-storage',
					'is_consent_mode_service' => true,
					'consent_mode_key'        => 'analytics_storage',
				)
			)
		);
		$this->assertSame( 'facebook_pixel_consent', klaro_geo_service_consent_key( array( 'name' => 'facebook-pixel' ) ) );
	}

	public function test_default_follows_required_then_service_then_template() {
		$inherits = array( 'name' => 'a' );
		$own_off  = array( 'name' => 'b', 'default' => false );
		$own_on   = array( 'name' => 'c', 'default' => 'true' );
		$required = array( 'name' => 'd', 'required' => true, 'default' => false );
		$global   = array( 'name' => 'e', 'required' => 'global' );

		$this->assertSame( array( 'key' => 'a_consent', 'default' => 'denied', 'source' => 'template' ), klaro_geo_service_consent_default( $inherits, false, false ) );
		$this->assertSame( 'granted', klaro_geo_service_consent_default( $inherits, true, false )['default'] );
		$this->assertSame( array( 'key' => 'b_consent', 'default' => 'denied', 'source' => 'service' ), klaro_geo_service_consent_default( $own_off, true, false ) );
		$this->assertSame( 'granted', klaro_geo_service_consent_default( $own_on, false, false )['default'] );
		$this->assertSame( array( 'key' => 'd_consent', 'default' => 'granted', 'source' => 'required' ), klaro_geo_service_consent_default( $required, false, false ) );

		// "Use Global Setting" takes the template's Required setting.
		$this->assertSame( 'template', klaro_geo_service_consent_default( $global, false, false )['source'] );
		$this->assertSame( 'required', klaro_geo_service_consent_default( $global, false, true )['source'] );
	}
}
