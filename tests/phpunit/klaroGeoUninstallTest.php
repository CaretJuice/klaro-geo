<?php
/**
 * Tests for what deleting the plugin removes.
 *
 * These call klaro_geo_uninstall(), the function uninstall.php runs.
 *
 * @package Klaro_Geo
 */

require_once dirname( __DIR__, 2 ) . '/includes/klaro-geo-uninstall.php';

class KlaroGeoUninstallTest extends WP_UnitTestCase {

	/**
	 * DROP TABLE statements issued during the test.
	 *
	 * @var string[]
	 */
	private $drops = array();

	public function set_up() {
		parent::set_up();
		$this->drops = array();
		add_filter( 'query', array( $this, 'record_drops' ) );
	}

	public function tear_down() {
		remove_filter( 'query', array( $this, 'record_drops' ) );
		parent::tear_down();
	}

	public function record_drops( $query ) {
		if ( preg_match( '/^\s*DROP\s/i', $query ) ) {
			$this->drops[] = $query;
		}
		return $query;
	}

	/**
	 * A setting added to the settings pages must also be removed on uninstall.
	 */
	public function test_every_registered_setting_is_removed() {
		klaro_geo_register_main_settings();

		$registered = array_filter(
			array_keys( get_registered_settings() ),
			function ( $name ) {
				return 0 === strpos( $name, 'klaro_geo_' );
			}
		);

		$this->assertNotEmpty( $registered );
		$this->assertSame(
			array(),
			array_values( array_diff( $registered, klaro_geo_uninstall_option_names() ) ),
			'Registered settings missing from klaro_geo_uninstall_option_names()'
		);
	}

	public function test_uninstall_deletes_options_transients_and_user_meta() {
		global $wpdb;

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		klaro_geo_activate();
		update_option( 'klaro_geo_gtm_id', 'GTM-TEST' );
		update_option( 'klaro_geo_gpc_enabled', true );
		update_option( 'klaro_geo_gtm_purpose_migrated', true );
		set_transient( 'klaro_geo_geoip_ajax_probe', 'ok', HOUR_IN_SECONDS );
		set_transient( 'klaro_geo_rl_' . md5( '203.0.113.0' ), 3, MINUTE_IN_SECONDS );
		update_user_meta( $user_id, 'klaro_geo_dismiss_geoip_ajax_warning', time() );

		klaro_geo_uninstall();

		$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%klaro\_geo\_%'" );
		$this->assertSame( array(), $left );
		$this->assertSame( '', get_user_meta( $user_id, 'klaro_geo_dismiss_geoip_ajax_warning', true ) );
	}

	/**
	 * Deactivation removes the same options as uninstall, and only when
	 * "Remove all plugin settings when deactivating" is on.
	 */
	public function test_deactivation_removes_options_only_when_cleanup_is_on() {
		global $wpdb;

		klaro_geo_activate();
		update_option( 'klaro_geo_gtm_id', 'GTM-TEST' );
		update_option( 'klaro_geo_gpc_enabled', true );
		update_option( 'klaro_geo_visible_countries', array( 'US' ) );

		klaro_geo_deactivate();
		$this->assertSame( 'GTM-TEST', get_option( 'klaro_geo_gtm_id' ) );

		update_option( 'klaro_geo_cleanup_on_deactivate', true );
		klaro_geo_deactivate();

		$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'klaro\_geo\_%'" );
		$this->assertSame( array(), $left );
	}

	public function test_receipts_table_is_kept_by_default() {
		klaro_geo_uninstall();

		$this->assertSame( array(), $this->drops );
	}

	public function test_receipts_table_is_dropped_when_the_owner_chose_that() {
		global $wpdb;
		update_option( 'klaro_geo_delete_receipts_on_uninstall', true );

		klaro_geo_uninstall();

		// The receipts, and the Klaro configs they refer to.
		$this->assertCount( 2, $this->drops );
		$this->assertStringContainsString( $wpdb->prefix . 'klaro_geo_consent_receipts', $this->drops[0] );
		$this->assertStringContainsString( $wpdb->prefix . 'klaro_geo_consent_configs', $this->drops[1] );
	}
}
