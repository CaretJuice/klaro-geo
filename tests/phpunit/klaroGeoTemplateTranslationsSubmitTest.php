<?php
/**
 * Which translations are saved when the template form is submitted.
 *
 * @package Klaro_Geo
 */

class KlaroGeoTemplateTranslationsSubmitTest extends WP_UnitTestCase {

	private $existing = array( 'zz' => array( 'acceptAll' => 'Stored accept' ) );

	public function test_valid_json_is_used_and_its_text_is_left_alone() {
		// Text an earlier "JSON repair" step rewrote: ", note:" looked like an
		// unquoted property name, and a comma before a closing brace like a
		// trailing comma.
		$description = 'We use cookies, note: you can decline. Options {analytics, ads,} apply.';
		$json        = wp_json_encode(
			array(
				'zz' => array( 'consentModal' => array( 'description' => $description ) ),
				'de' => array( 'acceptAll' => 'Alle akzeptieren' ),
			)
		);

		$result = klaro_geo_resolve_submitted_translations(
			array(
				'translations_json' => $json,
				'translations'      => array( 'zz' => array( 'acceptAll' => 'From the form fields' ) ),
			),
			$this->existing
		);

		$this->assertSame( $description, $result['zz']['consentModal']['description'] );
		$this->assertSame( 'Alle akzeptieren', $result['de']['acceptAll'] );
	}

	public function test_invalid_json_keeps_the_stored_translations_and_reports_it() {
		global $wp_settings_errors;
		$wp_settings_errors = array();

		$result = klaro_geo_resolve_submitted_translations( array( 'translations_json' => '{zz: {acceptAll: "x",}}' ), $this->existing );

		$this->assertSame( $this->existing, $result );
		$this->assertSame( 'json_parse_error', get_settings_errors( 'klaro_geo_templates' )[0]['code'] );
	}

	public function test_form_fields_are_used_without_json_and_are_sanitized() {
		$result = klaro_geo_resolve_submitted_translations(
			array( 'translations' => array( 'zz' => array( 'acceptAll' => 'Accept<script>alert(1)</script>' ) ) ),
			$this->existing
		);

		$this->assertSame( 'Accept', $result['zz']['acceptAll'] );
	}

	public function test_nothing_submitted_keeps_stored_translations_else_the_defaults() {
		$this->assertSame( $this->existing, klaro_geo_resolve_submitted_translations( array(), $this->existing ) );
		$this->assertSame( klaro_geo_get_default_translations(), klaro_geo_resolve_submitted_translations( array(), null ) );
	}
}
