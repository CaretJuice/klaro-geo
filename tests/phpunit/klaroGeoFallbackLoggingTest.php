<?php
/**
 * "Never fall back silently" (CLAUDE.md): each fallback the plugin can take
 * leaves a line in the debug log saying so.
 *
 * One case per fallback. A fallback added without a log line should get a
 * case here that fails first.
 *
 * @package Klaro_Geo
 */

class KlaroGeoFallbackLoggingTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->reset_settings();
	}

	public function tear_down() {
		remove_all_filters( 'site_url' );
		$this->reset_settings();
		parent::tear_down();
	}

	private function reset_settings() {
		delete_option( 'klaro_geo_templates' );
		delete_option( 'klaro_geo_services' );
		delete_option( 'klaro_geo_country_settings' );
		Klaro_Geo_Template_Settings::clear_instance_cache();
		Klaro_Geo_Service_Settings::clear_instance_cache();
		Klaro_Geo_Country_Settings::clear_instance_cache();
	}

	/**
	 * What klaro_geo_debug_log() writes while $action runs.
	 *
	 * @param callable $action The code that takes the fallback.
	 * @return string
	 */
	private function logged( callable $action ) {
		// klaro_geo_debug_log() writes through error_log(), so redirect it.
		$log_file     = tempnam( sys_get_temp_dir(), 'klaro-log-' );
		$previous_log = ini_get( 'error_log' );
		ini_set( 'error_log', $log_file );

		try {
			$action();
		} finally {
			ini_set( 'error_log', $previous_log );
		}

		$logged = (string) file_get_contents( $log_file );
		unlink( $log_file );
		return $logged;
	}

	private function templates() {
		return Klaro_Geo_Template_Settings::get_instance()->get();
	}

	private function services() {
		return Klaro_Geo_Service_Settings::get_instance()->get();
	}

	public function test_no_templates_stored() {
		$logged = $this->logged(
			function () {
				$this->templates();
			}
		);

		$this->assertStringContainsString( 'No templates stored, using the default templates', $logged );
	}

	public function test_stored_services_unusable() {
		update_option( 'klaro_geo_services', 'not json' );

		$logged = $this->logged(
			function () {
				$this->services();
			}
		);

		$this->assertStringContainsString( 'using the default services', $logged );
	}

	public function test_requested_template_missing_uses_the_default_template() {
		$templates = $this->templates();
		$services  = $this->services();

		$logged = $this->logged(
			function () use ( $templates, $services ) {
				$payload = klaro_geo_build_template_payload( 'deleted-template', $templates, $services );
				$this->assertSame( 'default', $payload['template_key'] );
			}
		);

		$this->assertStringContainsString( 'WARNING: Requested template "deleted-template" not found in database, falling back to "default"', $logged );
	}

	public function test_no_default_template_either_uses_the_built_in_one() {
		$templates = $this->templates();
		unset( $templates['default'] );
		$services = $this->services();

		$logged = $this->logged(
			function () use ( $templates, $services ) {
				$payload = klaro_geo_build_template_payload( 'deleted-template', $templates, $services );
				$this->assertSame( 'hardcoded-fallback', $payload['template_source_override'] );
			}
		);

		$this->assertStringContainsString( 'using hardcoded defaults', $logged );
	}

	public function test_country_pointing_at_a_missing_template_uses_the_fallback_template() {
		$this->templates();
		update_option(
			'klaro_geo_country_settings',
			array(
				'default_template' => 'default',
				'US'               => array( 'template' => 'deleted-template' ),
			)
		);
		Klaro_Geo_Country_Settings::clear_instance_cache();

		$logged = $this->logged(
			function () {
				$effective = Klaro_Geo_Country_Settings::get_instance()->get_effective_settings( 'US' );
				$this->assertSame( 'default', $effective['template'] );
				$this->assertSame( 'fallback', $effective['source'] );
			}
		);

		$this->assertStringContainsString( 'WARNING: Template "deleted-template" for US not found', $logged );
	}

	public function test_template_with_an_unusable_config() {
		update_option(
			'klaro_geo_templates',
			array(
				'default' => array(
					'name'   => 'Default',
					'config' => 'corrupted',
				),
			)
		);
		Klaro_Geo_Template_Settings::clear_instance_cache();

		$logged = $this->logged(
			function () {
				$this->templates();
			}
		);

		$this->assertStringContainsString( 'WARNING: Template "default" has a config that is not an array', $logged );
	}

	public function test_cookie_domain_cannot_be_detected() {
		$templates = $this->templates();
		$services  = $this->services();
		add_filter(
			'site_url',
			function () {
				return '';
			}
		);

		$logged = $this->logged(
			function () use ( $templates, $services ) {
				$payload = klaro_geo_build_template_payload( 'default', $templates, $services );
				$this->assertSame( '', $payload['config']['cookieDomain'] );
			}
		);

		$this->assertStringContainsString( 'WARNING: Could not auto-detect cookieDomain', $logged );
	}

	public function test_old_region_format_is_reported() {
		update_option(
			'klaro_geo_country_settings',
			array(
				'default_template' => 'default',
				'US'               => array(
					'template' => 'default',
					'CA'       => 'strict',
				),
			)
		);
		Klaro_Geo_Country_Settings::clear_instance_cache();

		$logged = $this->logged(
			function () {
				Klaro_Geo_Country_Settings::get_instance();
			}
		);

		$this->assertStringContainsString( 'Country US is stored in an old region format', $logged );
	}
}
