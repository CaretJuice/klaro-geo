<?php
/**
 * Which services Global Privacy Control switches off by default.
 *
 * The generated config marks services gpc_sensitive; when the browser sends
 * the GPC signal, the frontend sets default=false on those services. Three
 * levels decide the flag, most specific first:
 *
 *   1. The service's own gpc_sensitive setting (true or false).
 *   2. The template's gpc_purposes list: a service is sensitive when one of
 *      its purposes is in the list.
 *   3. When the template has no list, every configured purpose counts.
 *
 * GPC must also be enabled both globally and on the template; otherwise no
 * service is marked at all.
 *
 * @package Klaro_Geo
 */

class KlaroGeoGpcSensitivityTest extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'klaro_geo_gpc_enabled' );
		delete_option( 'klaro_geo_purposes' );
		parent::tearDown();
	}

	private function services() {
		return array(
			array(
				'name'     => 'analytics-tool',
				'purposes' => array( 'analytics' ),
				'required' => false,
			),
			array(
				'name'     => 'ad-tool',
				'purposes' => array( 'advertising' ),
				'required' => false,
			),
		);
	}

	/**
	 * Build the payload for one template and return gpc_sensitive by service
	 * name (null when the service carries no flag).
	 */
	private function sensitivity( array $plugin_settings, $services = null ) {
		$template = array(
			'name'            => 'Test',
			'config'          => array( 'default' => false ),
			'plugin_settings' => $plugin_settings,
		);
		$payload  = klaro_geo_build_template_payload(
			'test',
			array( 'test' => $template ),
			null === $services ? $this->services() : $services
		);

		$flags = array();
		foreach ( $payload['config']['services'] as $service ) {
			$flags[ $service['name'] ] = array_key_exists( 'gpc_sensitive', $service ) ? $service['gpc_sensitive'] : null;
		}
		return $flags;
	}

	public function test_template_purpose_list_decides_by_default() {
		$flags = $this->sensitivity( array( 'gpc_purposes' => array( 'advertising' ) ) );

		$this->assertTrue( $flags['ad-tool'] );
		$this->assertFalse( $flags['analytics-tool'] );
	}

	public function test_all_configured_purposes_are_sensitive_when_the_template_has_no_list() {
		update_option( 'klaro_geo_purposes', 'functional,analytics,advertising' );

		$flags = $this->sensitivity( array() );

		$this->assertTrue( $flags['ad-tool'] );
		$this->assertTrue( $flags['analytics-tool'] );
	}

	public function test_service_setting_overrides_the_template_purpose_list() {
		$services                     = $this->services();
		$services[0]['gpc_sensitive'] = true;  // analytics, not in the list
		$services[1]['gpc_sensitive'] = false; // advertising, in the list

		$flags = $this->sensitivity( array( 'gpc_purposes' => array( 'advertising' ) ), $services );

		$this->assertTrue( $flags['analytics-tool'] );
		$this->assertFalse( $flags['ad-tool'] );
	}

	public function test_no_service_is_marked_when_gpc_is_disabled_on_the_template() {
		$flags = $this->sensitivity(
			array(
				'gpc_enabled'  => false,
				'gpc_purposes' => array( 'advertising' ),
			)
		);

		$this->assertNull( $flags['ad-tool'] );
		$this->assertNull( $flags['analytics-tool'] );
	}

	public function test_no_service_is_marked_when_gpc_is_disabled_globally() {
		// 0, not false: update_option() will not create a missing option whose new
		// value equals get_option()'s false default.
		update_option( 'klaro_geo_gpc_enabled', 0 );

		$flags = $this->sensitivity( array( 'gpc_purposes' => array( 'advertising' ) ) );

		$this->assertNull( $flags['ad-tool'] );
		$this->assertNull( $flags['analytics-tool'] );
	}
}
