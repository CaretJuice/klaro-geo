<?php
/**
 * Class TemplatesSanitizeCallbackTest
 *
 * The klaro_geo_templates sanitize_callback must not flatten nested data.
 *
 * Production symptom: a template's 'config' and 'plugin_settings' were both
 * stored as empty strings, so the template contributed nothing to klaroConfig
 * and the admin page could not render its GPC settings.
 *
 * @package Klaro_Geo
 */

class TemplatesSanitizeCallbackTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// register_setting() normally runs on admin_init, but firing that hook in
		// the test harness sends headers. Call the registration directly; it is
		// what installs the sanitize_option_{$option} filter we exercise here.
		klaro_geo_register_main_settings();
	}

	/**
	 * A realistic template, shaped like the ones the plugin ships.
	 *
	 * @return array
	 */
	private function sample_templates() {
		return array(
			'strict' => array(
				'name'            => 'Strict Opt-In',
				'description'     => 'Opt-in template',
				'config'          => array(
					'default'      => false,
					'required'     => false,
					'mustConsent'  => true,
					'cookieName'   => 'klaro',
					'translations' => array(
						'zz' => array( 'acceptAll' => 'Accept all' ),
					),
				),
				'plugin_settings' => array(
					'gpc_enabled'  => true,
					'gpc_purposes' => array( 'advertising' ),
				),
			),
		);
	}

	/**
	 * Nested config must survive sanitisation as an array.
	 */
	public function test_config_survives_sanitisation() {
		$sanitized = sanitize_option( 'klaro_geo_templates', $this->sample_templates() );

		$this->assertIsArray(
			$sanitized['strict']['config'],
			'Template config must remain an array after sanitisation'
		);
		$this->assertSame(
			'klaro',
			$sanitized['strict']['config']['cookieName'],
			'Scalar config values must be preserved'
		);
		$this->assertIsArray(
			$sanitized['strict']['config']['translations'],
			'Nested translations must remain an array'
		);
	}

	/**
	 * plugin_settings must survive too; production lost these as well.
	 */
	public function test_plugin_settings_survive_sanitisation() {
		$sanitized = sanitize_option( 'klaro_geo_templates', $this->sample_templates() );

		$this->assertIsArray(
			$sanitized['strict']['plugin_settings'],
			'plugin_settings must remain an array after sanitisation'
		);
		$this->assertSame(
			array( 'advertising' ),
			$sanitized['strict']['plugin_settings']['gpc_purposes'],
			'gpc_purposes must survive as an array'
		);
	}

	/**
	 * Scalar fields are still sanitised.
	 */
	public function test_scalar_fields_are_still_sanitised() {
		$templates                  = $this->sample_templates();
		$templates['strict']['name'] = '<script>alert(1)</script>Strict';

		$sanitized = sanitize_option( 'klaro_geo_templates', $templates );

		$this->assertStringNotContainsString(
			'<script>',
			$sanitized['strict']['name'],
			'Scalar fields must still be sanitised'
		);
	}
}
