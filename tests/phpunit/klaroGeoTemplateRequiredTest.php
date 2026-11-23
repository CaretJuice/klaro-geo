<?php
/**
 * Class TemplateRequiredTest
 *
 * Issue #6: a service set to "Use Global Setting" for Required must take the
 * template's Required setting, per template, so Google Tag Manager can be
 * required in most regions and consent-gated where the law calls for it.
 *
 * Previously the template's Required reached Klaro as its global
 * config.required. Klaro loaded every inheriting service while showing it as
 * switchable and recording it as not consented, and its changeAll() granted
 * every service, explicitly non-required ones included, when the visitor
 * clicked Decline.
 *
 * @package Klaro_Geo
 */

class TemplateRequiredTest extends WP_UnitTestCase {

	/**
	 * @return array Services: GTM inheriting, GA explicitly not required,
	 *               a security service explicitly required.
	 */
	private function services() {
		return array(
			array(
				'name'     => 'google-tag-manager',
				'cookies' => array(),
				'purposes' => array( 'infrastructure' ),
				'required' => null,
				'default'  => false,
			),
			array(
				'name'     => 'google-analytics',
				'cookies' => array(),
				'purposes' => array( 'analytics' ),
				'required' => false,
				'default'  => false,
			),
			array(
				'name'     => 'security',
				'cookies' => array(),
				'purposes' => array( 'functional' ),
				'required' => true,
			),
		);
	}

	/**
	 * Build the payload for one template with the given Required setting.
	 *
	 * @param bool $template_required The template's Required setting.
	 * @return array Payload from klaro_geo_build_template_payload().
	 */
	private function payload( $template_required ) {
		$templates = array(
			'default' => array(
				'name'   => 'Default Template',
				'config' => array(
					'default'  => false,
					'required' => $template_required,
				),
			),
		);
		return klaro_geo_build_template_payload( 'default', $templates, $this->services() );
	}

	/**
	 * @param array  $payload The payload.
	 * @param string $name    Service name.
	 * @return array The Klaro service entry.
	 */
	private function service( array $payload, $name ) {
		foreach ( $payload['config']['services'] as $service ) {
			if ( $name === $service['name'] ) {
				return $service;
			}
		}
		$this->fail( "Service $name missing from klaroConfig" );
	}

	public function test_required_template_makes_inheriting_service_required() {
		$payload = $this->payload( true );

		$this->assertTrue( $this->service( $payload, 'google-tag-manager' )['required'] );
		$this->assertSame( 'granted', $payload['consent_defaults']['google_tag_manager_consent'] );
	}

	public function test_required_template_leaves_explicit_settings_alone() {
		$payload = $this->payload( true );

		$this->assertFalse( $this->service( $payload, 'google-analytics' )['required'], 'Explicit No must win' );
		$this->assertSame( 'denied', $payload['consent_defaults']['google_analytics_consent'] );
		$this->assertTrue( $this->service( $payload, 'security' )['required'], 'Explicit Yes must win' );
	}

	public function test_non_required_template_leaves_inheriting_service_declinable() {
		$payload = $this->payload( false );

		$this->assertFalse( $this->service( $payload, 'google-tag-manager' )['required'] );
		$this->assertSame( 'denied', $payload['consent_defaults']['google_tag_manager_consent'] );
		$this->assertTrue( $this->service( $payload, 'security' )['required'], 'Explicit Yes must win' );
	}

	/**
	 * Klaro's global required makes Decline grant every service, so it must
	 * never be emitted as true, whatever the template says.
	 */
	public function test_global_required_never_reaches_klaro() {
		$this->assertFalse( $this->payload( true )['config']['required'] );
		$this->assertFalse( $this->payload( false )['config']['required'] );
	}

	/**
	 * The point of the feature: one service, two templates, two answers.
	 */
	public function test_same_service_differs_between_templates() {
		$templates = array(
			'default' => array(
				'name'   => 'Default',
				'config' => array( 'required' => true ),
			),
			'germany' => array(
				'name'   => 'Germany',
				'config' => array( 'required' => false ),
			),
		);

		$default = klaro_geo_build_template_payload( 'default', $templates, $this->services() );
		$germany = klaro_geo_build_template_payload( 'germany', $templates, $this->services() );

		$this->assertTrue( $this->service( $default, 'google-tag-manager' )['required'] );
		$this->assertFalse( $this->service( $germany, 'google-tag-manager' )['required'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool, 2: bool}>
	 */
	public function resolve_provider() {
		return array(
			'null inherits true'     => array( null, true, true ),
			'null inherits false'    => array( null, false, false ),
			'missing inherits'       => array( 'MISSING', true, true ),
			'empty string inherits'  => array( '', true, true ),
			'"global" inherits'      => array( 'global', true, true ),
			'explicit false wins'    => array( false, true, false ),
			'explicit true wins'     => array( true, false, true ),
			'string "false" wins'    => array( 'false', true, false ),
			'string "true" wins'     => array( 'true', false, true ),
		);
	}

	/**
	 * @dataProvider resolve_provider
	 * @param mixed $stored            The service's stored required value.
	 * @param bool  $template_required The template's Required setting.
	 * @param bool  $expected          The resolved value.
	 */
	public function test_resolve_service_required( $stored, $template_required, $expected ) {
		$service = array( 'name' => 'x' );
		if ( 'MISSING' !== $stored ) {
			$service['required'] = $stored;
		}

		$this->assertSame( $expected, klaro_geo_resolve_service_required( $service, $template_required ) );
	}
}
