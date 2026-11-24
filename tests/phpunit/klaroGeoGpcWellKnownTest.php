<?php
/**
 * /.well-known/gpc.json: the GPC support statement, served only when the
 * "Serve /.well-known/gpc.json" setting is on.
 *
 * The handler ends the request, so the tests cover the two things it is made
 * of: the URL reaching it, and klaro_geo_gpc_json_body(). The status and
 * headers are checked against a running site in e2e/tests/08-gpc-well-known.spec.js.
 *
 * @package Klaro_Geo
 */

class KlaroGeoGpcWellKnownTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'klaro_geo_gpc_well_known' );
		delete_option( 'klaro_geo_gpc_well_known_updated' );
	}

	public function tear_down() {
		delete_option( 'klaro_geo_gpc_well_known' );
		delete_option( 'klaro_geo_gpc_well_known_updated' );
		parent::tear_down();
	}

	public function test_the_url_is_routed_to_the_handler() {
		$this->set_permalink_structure( '/%postname%/' );
		klaro_geo_gpc_rewrite_rules();
		flush_rewrite_rules();

		$this->go_to( home_url( '/.well-known/gpc.json' ) );

		$this->assertSame( '1', get_query_var( 'klaro_geo_gpc_json' ) );
	}

	public function test_other_urls_are_not() {
		$this->set_permalink_structure( '/%postname%/' );
		klaro_geo_gpc_rewrite_rules();
		flush_rewrite_rules();

		$this->go_to( home_url( '/.well-known/security.txt' ) );
		$this->assertSame( '', get_query_var( 'klaro_geo_gpc_json' ) );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( '', get_query_var( 'klaro_geo_gpc_json' ) );
	}

	public function test_nothing_is_served_unless_the_setting_is_on() {
		$this->assertNull( klaro_geo_gpc_json_body(), 'Off by default' );

		update_option( 'klaro_geo_gpc_well_known', false );
		$this->assertNull( klaro_geo_gpc_json_body() );
	}

	public function test_the_statement_says_gpc_is_honoured() {
		update_option( 'klaro_geo_gpc_well_known', true );

		$body = klaro_geo_gpc_json_body();

		$this->assertTrue( $body['gpc'] );
		$this->assertSame( gmdate( 'Y-m-d' ), $body['lastUpdate'] );
		$this->assertSame( array( 'gpc', 'lastUpdate' ), array_keys( $body ) );
	}

	/**
	 * lastUpdate is the date the statement last changed: the day the setting
	 * was last switched, not the day of the request.
	 */
	public function test_last_update_is_the_day_the_setting_was_switched() {
		update_option( 'klaro_geo_gpc_well_known', true );
		$this->assertSame( gmdate( 'Y-m-d' ), get_option( 'klaro_geo_gpc_well_known_updated' ), 'Recorded when switched on' );

		// Some time later: the recorded date is what is served.
		update_option( 'klaro_geo_gpc_well_known_updated', '2025-03-14' );
		$this->assertSame( '2025-03-14', klaro_geo_gpc_json_body()['lastUpdate'] );

		// Saving the settings page again without switching it changes nothing.
		// The page stores true where '1' is stored.
		update_option( 'klaro_geo_gpc_well_known', '1' );
		update_option( 'klaro_geo_gpc_well_known', true );
		$this->assertSame( '2025-03-14', klaro_geo_gpc_json_body()['lastUpdate'] );

		// Switching it off and on again is a change.
		update_option( 'klaro_geo_gpc_well_known', false );
		update_option( 'klaro_geo_gpc_well_known', true );
		$this->assertSame( gmdate( 'Y-m-d' ), klaro_geo_gpc_json_body()['lastUpdate'] );
	}

	/**
	 * A site that switched the setting on before the date was recorded.
	 */
	public function test_a_missing_date_is_recorded_once() {
		update_option( 'klaro_geo_gpc_well_known', true );
		delete_option( 'klaro_geo_gpc_well_known_updated' );

		$this->assertSame( gmdate( 'Y-m-d' ), klaro_geo_gpc_json_body()['lastUpdate'] );
		$this->assertSame( gmdate( 'Y-m-d' ), get_option( 'klaro_geo_gpc_well_known_updated' ) );
	}
}
