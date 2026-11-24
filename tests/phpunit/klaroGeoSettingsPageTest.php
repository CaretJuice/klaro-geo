<?php
/**
 * The main settings page and the options its form saves.
 *
 * @package Klaro_Geo
 */

class KlaroGeoSettingsPageTest extends WP_UnitTestCase {

	private function render() {
		ob_start();
		klaro_geo_settings_page_content();
		return ob_get_clean();
	}

	/**
	 * options.php saves every option registered in the submitted group and
	 * writes null for one the form has no field for. An option registered in
	 * the group without a field is therefore erased on every save, which is
	 * how saving this page used to wipe all templates.
	 */
	public function test_every_option_in_the_settings_group_has_a_field_on_the_page() {
		global $wp_registered_settings;
		$saved = $wp_registered_settings;

		klaro_geo_register_main_settings();
		$html = $this->render();

		$in_group = array();
		foreach ( get_registered_settings() as $name => $args ) {
			if ( 'klaro_geo_settings_group' === $args['group'] ) {
				$in_group[] = $name;
			}
		}
		$wp_registered_settings = $saved;

		$this->assertContains( 'klaro_geo_gtm_id', $in_group );
		$this->assertNotContains( 'klaro_geo_templates', $in_group );
		foreach ( $in_group as $name ) {
			$this->assertMatchesRegularExpression( '/name="' . preg_quote( $name, '/' ) . '(\\[\\])?"/', $html, "$name is saved by the settings form but has no field in it" );
		}
	}

	/**
	 * Before the button options are saved for the first time, each dropdown
	 * must show the default, and only that.
	 */
	public function test_button_dropdowns_select_only_the_current_value() {
		delete_option( 'klaro_geo_floating_button_theme' );
		delete_option( 'klaro_geo_floating_button_position' );
		$html = $this->render();

		$this->assertSame( 2, preg_match_all( '/<option[^>]*selected/', $this->selects( $html ) ) );
		$this->assertMatchesRegularExpression( '/<option value="light"\s+selected/', $html );
		$this->assertMatchesRegularExpression( '/<option value="bottom-right"\s+selected/', $html );

		update_option( 'klaro_geo_floating_button_theme', 'blue' );
		$this->assertMatchesRegularExpression( '/<option value="blue"\s+selected/', $this->render() );
	}

	/**
	 * The markup of the two button dropdowns.
	 */
	private function selects( $html ) {
		preg_match_all( '/<select name="klaro_geo_floating_button_(?:theme|position)".*?<\/select>/s', $html, $matches );
		$this->assertCount( 2, $matches[0] );
		return implode( '', $matches[0] );
	}
}
