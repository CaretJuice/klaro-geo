<?php
/**
 * Class SanitizeDeepTest
 *
 * klaro_geo_sanitize_array() and klaro_geo_sanitize_translations() share one
 * traversal but apply deliberately different scalar policies. These tests pin
 * that difference down so the two are not collapsed into one.
 *
 * @package Klaro_Geo
 */

class SanitizeDeepTest extends WP_UnitTestCase {

	/**
	 * Config comes from checkboxes and selects, so the form's 'true', 'false'
	 * and 'on' must become real booleans.
	 */
	public function test_config_sanitiser_coerces_form_booleans() {
		$result = klaro_geo_sanitize_array(
			array(
				'required'    => 'false',
				'mustConsent' => 'true',
				'checkbox'    => 'on',
			)
		);

		$this->assertFalse( $result['required'] );
		$this->assertTrue( $result['mustConsent'] );
		$this->assertTrue( $result['checkbox'] );
	}

	/**
	 * Native scalars must survive the config sanitiser untouched -- this is the
	 * bug that map_deep() would reintroduce.
	 */
	public function test_config_sanitiser_preserves_native_scalars() {
		$result = klaro_geo_sanitize_array(
			array(
				'boolTrue'  => true,
				'boolFalse' => false,
				'int'       => 365,
				'float'     => 1.5,
			)
		);

		$this->assertTrue( $result['boolTrue'] );
		$this->assertFalse( $result['boolFalse'], 'false must not become an empty string' );
		$this->assertSame( 365, $result['int'] );
		$this->assertSame( 1.5, $result['float'] );
	}

	/**
	 * Translations are human-readable text. A label that happens to read "on"
	 * or "false" must stay a string.
	 */
	public function test_translation_sanitiser_does_not_coerce_text() {
		$result = klaro_geo_sanitize_translations(
			array(
				'zz' => array(
					'toggleLabel' => 'on',
					'declineWord' => 'false',
					'acceptAll'   => 'Accept all',
				),
			)
		);

		$this->assertSame( 'on', $result['zz']['toggleLabel'] );
		$this->assertSame( 'false', $result['zz']['declineWord'] );
		$this->assertSame( 'Accept all', $result['zz']['acceptAll'] );
	}

	/**
	 * Both sanitisers recurse and both still strip markup.
	 */
	public function test_both_recurse_and_strip_markup() {
		$dirty = array( 'a' => array( 'b' => array( 'c' => '<script>x</script>clean' ) ) );

		foreach ( array( 'klaro_geo_sanitize_array', 'klaro_geo_sanitize_translations' ) as $fn ) {
			$result = $fn( $dirty );
			$this->assertIsArray( $result['a']['b'], "$fn must recurse" );
			$this->assertStringNotContainsString( '<script>', $result['a']['b']['c'], "$fn must strip markup" );
		}
	}

	/**
	 * Non-array input yields an empty array rather than throwing.
	 */
	public function test_non_array_input_returns_empty_array() {
		$this->assertSame( array(), klaro_geo_sanitize_array( 'a string' ) );
		$this->assertSame( array(), klaro_geo_sanitize_translations( null ) );
	}
}
