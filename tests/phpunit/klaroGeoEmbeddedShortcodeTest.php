<?php
/**
 * The [klaro_embedded] shortcode's attributes are written by whoever can edit
 * the post, so they are escaped and filtered like post content.
 *
 * @package Klaro_Geo
 */

class KlaroGeoEmbeddedShortcodeTest extends WP_UnitTestCase {

	public function test_default_style_is_kept() {
		$html = do_shortcode( '[klaro_embedded]' );

		$this->assertStringContainsString( 'id="klaro-embedded-container"', $html );
		$this->assertStringContainsString( 'width: 100%', $html );
		$this->assertStringContainsString( 'min-height: 300px', $html );
	}

	public function test_style_goes_through_the_css_filter() {
		$html = do_shortcode( '[klaro_embedded style="width: 50%; behavior: url(evil.htc); background-image: url(javascript:alert(1))"]' );

		$this->assertStringContainsString( 'width: 50%', $html );
		$this->assertStringNotContainsString( 'behavior', $html );
		$this->assertStringNotContainsString( 'javascript', $html );
	}

	public function test_attributes_cannot_break_out_of_the_tag() {
		$html = do_shortcode( "[klaro_embedded id='x\"><script>alert(1)</script>' class='a\" onclick=\"alert(1)']" );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '" onclick="', $html );
	}
}
