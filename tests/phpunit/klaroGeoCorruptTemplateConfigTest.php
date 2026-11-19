<?php
/**
 * Class CorruptTemplateConfigTest
 *
 * A template whose 'config' is not an array must not be iterated.
 *
 * Production logged this 247 times in a single debug.log:
 *   foreach() argument must be of type array|object, string given
 *     in includes/klaro-geo-config.php
 *
 * The foreach copied nothing, but the surrounding branch still ran, so
 * klaroConfig came out with no 'default' and no 'required' at all. Falling back
 * to the hardcoded defaults is both safer and, per the spec's
 * no_silent_failures rule, must be logged.
 *
 * @package Klaro_Geo
 */

class CorruptTemplateConfigTest extends WP_UnitTestCase {

	/**
	 * Build a payload for a template whose config has been corrupted.
	 *
	 * @param mixed $config What to store as the template's config.
	 * @return array The payload returned by klaro_geo_build_template_payload().
	 */
	private function payload_for_config( $config ) {
		$service_settings = Klaro_Geo_Service_Settings::get_instance();
		$services         = $service_settings->get_default_services();

		$templates = array(
			'default' => array(
				'name'   => 'Default Template',
				'config' => $config,
			),
		);

		return klaro_geo_build_template_payload( 'default', $templates, $services );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function corrupt_config_provider() {
		return array(
			'json string' => array( '{"default":false,"required":true}' ),
			'plain string' => array( 'corrupted' ),
			'integer'      => array( 42 ),
			'boolean'      => array( true ),
		);
	}

	/**
	 * A non-array config must fall back to the documented safe defaults rather
	 * than leaving them unset.
	 *
	 * @dataProvider corrupt_config_provider
	 * @param mixed $config The corrupt config value.
	 */
	public function test_non_array_config_falls_back_to_safe_defaults( $config ) {
		$payload = $this->payload_for_config( $config );
		$klaro   = $payload['config'];

		$this->assertArrayHasKey( 'default', $klaro, 'Safe defaults must be applied' );
		$this->assertArrayHasKey( 'required', $klaro, 'Safe defaults must be applied' );

		// Fail closed: nothing is granted and nothing is undeclinable.
		$this->assertFalse( $klaro['default'], 'default must fail closed' );
		$this->assertFalse( $klaro['required'], 'required must fail closed' );
	}

	/**
	 * The warning must name the offending template, so a site with several
	 * templates can tell which one to re-save.
	 */
	public function test_warning_names_the_offending_template() {
		$service_settings = Klaro_Geo_Service_Settings::get_instance();
		$services         = $service_settings->get_default_services();

		$templates = array(
			'default' => array(
				'name'   => 'Default Template',
				'config' => array( 'default' => false ),
			),
			'strict'  => array(
				'name'   => 'Strict Opt-In',
				'config' => '',
			),
		);

		// klaro_geo_debug_log() writes straight to error_log(), so redirect it.
		$log_file     = tempnam( sys_get_temp_dir(), 'klaro-log-' );
		$previous_log = ini_get( 'error_log' );
		ini_set( 'error_log', $log_file );

		try {
			klaro_geo_build_template_payload( 'strict', $templates, $services );
		} finally {
			ini_set( 'error_log', $previous_log );
		}

		$logged = (string) file_get_contents( $log_file );
		unlink( $log_file );

		$this->assertStringContainsString(
			'is not an array',
			$logged,
			'A corrupt config must be logged'
		);
		$this->assertStringContainsString(
			'"strict"',
			$logged,
			'The warning must name the offending template'
		);
	}

	/**
	 * A well-formed config is still copied through untouched.
	 */
	public function test_array_config_is_still_applied() {
		$payload = $this->payload_for_config(
			array(
				'default'     => true,
				'required'    => true,
				'mustConsent' => true,
			)
		);
		$klaro   = $payload['config'];

		$this->assertTrue( $klaro['default'], 'A valid config must still be copied' );
		$this->assertTrue( $klaro['mustConsent'], 'A valid config must still be copied' );

		// required is applied per service, never as Klaro's global flag (issue #6)
		$this->assertFalse( $klaro['required'], 'Global required must never reach Klaro' );
	}

	/**
	 * Issue #5: with acceptAll unset, Klaro wires the notice's "That's ok"
	 * button to saveAndHide, which saves the default consents and so declines
	 * every service. The corrupt-config fallback must therefore set it.
	 *
	 * @dataProvider corrupt_config_provider
	 * @param mixed $config The corrupt config value.
	 */
	public function test_corrupt_config_fallback_enables_accept_all( $config ) {
		$klaro = $this->payload_for_config( $config )['config'];

		$this->assertArrayHasKey( 'acceptAll', $klaro );
		$this->assertTrue( $klaro['acceptAll'], 'Fallback must use the hardcoded acceptAll=true' );
		$this->assertNotEmpty( $klaro['translations'], 'Fallback must carry the hardcoded translations' );
	}

	/**
	 * A stored config missing a key the admin page shows as checked by default
	 * must get that default, so the frontend matches the admin.
	 */
	public function test_missing_admin_true_by_default_keys_are_backfilled() {
		$klaro = $this->payload_for_config( array( 'default' => false ) )['config'];

		foreach ( klaro_geo_get_admin_true_by_default_keys() as $key ) {
			$this->assertArrayHasKey( $key, $klaro, "$key must be backfilled" );
			$this->assertTrue( $klaro[ $key ], "$key must default to true" );
		}
	}

	/**
	 * An explicit false is a real choice and must survive the backfill.
	 */
	public function test_explicit_false_is_not_backfilled() {
		$klaro = $this->payload_for_config(
			array(
				'default'   => false,
				'acceptAll' => false,
			)
		)['config'];

		$this->assertFalse( $klaro['acceptAll'], 'An explicit acceptAll=false must be kept' );
	}

	/**
	 * The backfill list must match the checkboxes the templates admin page
	 * renders as checked when unset, or the two will drift apart again.
	 */
	public function test_backfill_list_matches_admin_checkbox_defaults() {
		$admin_source = file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/klaro-geo-admin-templates.php' );
		preg_match_all(
			"/checked\(\s*isset\(\s*\\\$current_config\['(\w+)'\]\s*\)\s*\?\s*\\\$current_config\['\\1'\]\s*:\s*true\s*\)/",
			$admin_source,
			$matches
		);

		$admin_keys    = $matches[1];
		$backfill_keys = klaro_geo_get_admin_true_by_default_keys();
		sort( $admin_keys );
		sort( $backfill_keys );

		$this->assertNotEmpty( $admin_keys, 'Pattern must find the admin checkboxes' );
		$this->assertSame( $admin_keys, $backfill_keys );
	}
}
