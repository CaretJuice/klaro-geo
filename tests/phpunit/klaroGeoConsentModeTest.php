<?php
/**
 * Tests for Google Consent Mode output of the template payload builder.
 *
 * Everything here goes through klaro_geo_build_template_payload(), the function
 * the frontend config is generated from, so these assertions describe what a
 * visitor's browser actually receives.
 *
 * @package Klaro_Geo
 */

class KlaroGeoConsentModeTest extends WP_UnitTestCase {

	/**
	 * Services used by the consent-default tests: one consent mode service and
	 * one regular service, neither with an explicit default.
	 *
	 * @return array
	 */
	private function services() {
		return array(
			array(
				'name'                    => 'analytics-storage',
				'cookies' => array(),
				'purposes'                => array( 'analytics' ),
				'required'                => false,
				'is_consent_mode_service' => true,
				'consent_mode_key'        => 'analytics_storage',
			),
			array(
				'name'     => 'google-ads',
				'cookies' => array(),
				'purposes' => array( 'advertising' ),
				'required' => false,
			),
		);
	}

	/**
	 * Build a payload for a single template stored under the key 'test'.
	 *
	 * @param array      $template The template definition.
	 * @param array|null $services Services to use; defaults to services().
	 * @return array
	 */
	private function payload( array $template, $services = null ) {
		// Templates reach the builder in the shape loading gives them.
		return klaro_geo_build_template_payload(
			'test',
			array( 'test' => Klaro_Geo_Template_Settings::normalize_template( 'test', $template ) ),
			null === $services ? $this->services() : $services
		);
	}

	/**
	 * A minimal opt-in template.
	 *
	 * @param array $config Extra config keys.
	 * @return array
	 */
	private function template( array $config = array() ) {
		return array(
			'name'   => 'Test',
			'config' => array_merge(
				array(
					'default'  => false,
					'required' => false,
				),
				$config
			),
		);
	}

	public function test_standard_consent_mode_keys_default_to_denied() {
		$defaults = $this->payload( $this->template(), array() )['consent_defaults'];

		foreach ( array( 'ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization' ) as $key ) {
			$this->assertSame( 'denied', $defaults[ $key ], $key . ' should default to denied' );
		}
	}

	public function test_consent_mode_service_uses_its_consent_mode_key() {
		$defaults = $this->payload( $this->template() )['consent_defaults'];

		$this->assertArrayHasKey( 'analytics_storage', $defaults );
		$this->assertArrayNotHasKey( 'analytics_storage_consent', $defaults, 'A consent mode service must not also get a *_consent key' );
	}

	public function test_regular_service_gets_a_consent_suffixed_key() {
		$defaults = $this->payload( $this->template() )['consent_defaults'];

		$this->assertSame( 'denied', $defaults['google_ads_consent'] );
	}

	public function test_services_inherit_the_template_default() {
		$defaults = $this->payload( $this->template( array( 'default' => true ) ) )['consent_defaults'];

		$this->assertSame( 'granted', $defaults['analytics_storage'] );
		$this->assertSame( 'granted', $defaults['google_ads_consent'] );
	}

	public function test_explicit_service_default_overrides_the_template_default() {
		$services               = $this->services();
		$services[1]['default'] = false;

		$defaults = $this->payload( $this->template( array( 'default' => true ) ), $services )['consent_defaults'];

		$this->assertSame( 'granted', $defaults['analytics_storage'] );
		$this->assertSame( 'denied', $defaults['google_ads_consent'] );
	}

	public function test_required_service_is_granted_by_default() {
		$services                = $this->services();
		$services[1]['required'] = true;

		$defaults = $this->payload( $this->template(), $services )['consent_defaults'];

		$this->assertSame( 'granted', $defaults['google_ads_consent'] );
		$this->assertSame( 'denied', $defaults['analytics_storage'] );
	}

	public function test_gtag_settings_fall_back_to_defaults() {
		$gtag = $this->payload( $this->template() )['gtag_settings'];

		$this->assertSame( 'true', $gtag['ads_data_redaction'] );
		$this->assertSame( 'false', $gtag['url_passthrough'] );
	}

	public function test_gtag_settings_are_read_from_the_template_config() {
		$template = $this->template(
			array(
				'consent_mode_settings' => array(
					'gtag_settings' => array(
						'ads_data_redaction' => 'false',
						'url_passthrough'    => 'true',
					),
				),
			)
		);

		$gtag = $this->payload( $template )['gtag_settings'];

		$this->assertSame( 'false', $gtag['ads_data_redaction'] );
		$this->assertSame( 'true', $gtag['url_passthrough'] );
	}

	public function test_gtag_settings_are_read_from_top_level_consent_mode_settings() {
		$template                          = $this->template();
		$template['consent_mode_settings'] = array(
			'gtag_settings' => array(
				'ads_data_redaction' => 'false',
				'url_passthrough'    => 'true',
			),
		);

		$gtag = $this->payload( $template )['gtag_settings'];

		$this->assertSame( 'false', $gtag['ads_data_redaction'] );
		$this->assertSame( 'true', $gtag['url_passthrough'] );
	}

	/**
	 * Consent mode is always on. The legacy toggle and the legacy stored
	 * initialization code must never reach klaroConfig.
	 */
	public function test_legacy_consent_mode_keys_are_stripped_from_the_config() {
		$template = $this->template(
			array(
				'consent_mode_settings' => array(
					'initialize_consent_mode' => false,
					'initialization_code'     => 'gtag("consent", "default", {});',
					'gtag_settings'           => array( 'url_passthrough' => 'true' ),
				),
			)
		);

		$settings = $this->payload( $template )['config']['consent_mode_settings'];

		$this->assertArrayNotHasKey( 'initialize_consent_mode', $settings );
		$this->assertArrayNotHasKey( 'initialization_code', $settings );
		$this->assertSame( 'true', $settings['gtag_settings']['url_passthrough'] );
	}

	/**
	 * A child consent mode service is denied while its parent is not granted,
	 * in the defaults as in the updates the browser sends.
	 */
	public function test_child_consent_mode_service_is_denied_while_its_parent_is() {
		$services = array(
			array(
				'name'                    => 'ad-storage',
				'cookies'                 => array(),
				'purposes'                => array( 'advertising' ),
				'is_consent_mode_service' => true,
				'consent_mode_key'        => 'ad_storage',
				'default'                 => false,
			),
			array(
				'name'                    => 'ad-user-data',
				'cookies'                 => array(),
				'purposes'                => array( 'advertising' ),
				'is_consent_mode_service' => true,
				'consent_mode_key'        => 'ad_user_data',
				'parent_service'          => 'ad-storage',
				'default'                 => true,
			),
		);

		$denied_parent = $this->payload( $this->template(), $services )['consent_defaults'];
		$this->assertSame( 'denied', $denied_parent['ad_storage'] );
		$this->assertSame( 'denied', $denied_parent['ad_user_data'], 'The child has its own default of granted, but the parent is denied' );

		$services[0]['default'] = true;
		$granted_parent         = $this->payload( $this->template(), $services )['consent_defaults'];
		$this->assertSame( 'granted', $granted_parent['ad_storage'] );
		$this->assertSame( 'granted', $granted_parent['ad_user_data'] );
	}

	public function test_consent_mode_service_maps() {
		$services   = $this->services();
		$services[] = array(
			'name'                    => 'ad-user-data',
			'cookies' => array(),
			'is_consent_mode_service' => true,
			'consent_mode_key'        => 'ad_user_data',
			'parent_service'          => 'ad-storage',
		);

		$maps = klaro_geo_get_consent_mode_service_maps( $services );

		$this->assertSame(
			array(
				'analytics_storage' => 'analytics-storage',
				'ad_user_data'      => 'ad-user-data',
			),
			$maps['consent_mode_services']
		);
		$this->assertSame( array( 'ad-storage' => array( 'ad-user-data' ) ), $maps['parent_child_map'] );
	}
}
