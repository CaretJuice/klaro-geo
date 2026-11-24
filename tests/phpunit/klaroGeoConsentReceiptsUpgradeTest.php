<?php
/**
 * Upgrading a receipts table created before the GPC columns were added.
 *
 * @package Klaro_Geo
 */

class KlaroGeoConsentReceiptsUpgradeTest extends WP_UnitTestCase {

	/**
	 * Build the table as version 1.0 left it. Done outside the per-test
	 * transaction because ALTER TABLE commits implicitly.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		global $wpdb;
		klaro_geo_create_consent_receipts_table();
		$table = klaro_geo_receipts_table_name();
		$wpdb->query( "ALTER TABLE $table DROP COLUMN gpc_signal, DROP COLUMN gpc_affected_services, DROP COLUMN receipt_type, DROP COLUMN config_hash, DROP COLUMN config_status" );
		$wpdb->query( 'DROP TABLE ' . klaro_geo_consent_configs_table_name() );
		update_option( 'klaro_geo_consent_receipts_db_version', '1.0' );
		update_option( 'klaro_geo_enable_consent_receipts', true );
	}

	public static function wpTearDownAfterClass() {
		global $wpdb;
		$table = klaro_geo_receipts_table_name();
		$wpdb->query( "DELETE FROM $table" );
		klaro_geo_drop_consent_receipts_table();
		delete_option( 'klaro_geo_consent_receipts_db_version' );
		delete_option( 'klaro_geo_enable_consent_receipts' );
	}

	/**
	 * WP_UnitTestCase rewrites CREATE and DROP TABLE into their TEMPORARY
	 * forms, and a temporary table is invisible to the check that the upgrade
	 * worked. These tests need the real tables created and dropped.
	 */
	public function set_up() {
		parent::set_up();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	private function receipt( $id ) {
		return array(
			'receipt_id'            => $id,
			'timestamp'             => 1795000000,
			'consent_choices'       => array( 'google-analytics' => true ),
			'template_name'         => 'default',
			'template_source'       => 'fallback',
			'country_code'          => 'US',
			'region_code'           => '',
			'gpc_signal'            => true,
			'gpc_affected_services' => array( 'google-ads' ),
		);
	}

	private function stored( $receipt_id ) {
		global $wpdb;
		$table = klaro_geo_receipts_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE receipt_id = %s", $receipt_id ) );
	}

	public function test_upgrade_adds_the_columns_and_leaves_old_receipts_unrecorded() {
		global $wpdb;
		$table = klaro_geo_receipts_table_name();

		$this->assertFalse( klaro_geo_receipts_column_exists( 'gpc_signal' ) );
		$wpdb->insert(
			$table,
			array(
				'receipt_id'      => 'receipt_from_1_0',
				'timestamp'       => '2026-01-01 00:00:00',
				'consent_data'    => '{}',
				'template_name'   => 'default',
				'template_source' => 'fallback',
			)
		);

		klaro_geo_upgrade_consent_receipts_table();

		foreach ( array( 'gpc_signal', 'gpc_affected_services', 'receipt_type', 'config_hash', 'config_status' ) as $column ) {
			$this->assertTrue( klaro_geo_receipts_column_exists( $column ), $column );
		}
		$this->assertTrue( klaro_geo_consent_configs_table_exists() );
		$this->assertSame( KLARO_GEO_RECEIPTS_DB_VERSION, get_option( 'klaro_geo_consent_receipts_db_version' ) );

		// A receipt from before the upgrade says "not recorded", not "no signal".
		$old = $this->stored( 'receipt_from_1_0' );
		$this->assertNull( $old->gpc_signal );
		$this->assertSame( 'Not recorded', klaro_geo_receipt_gpc_label( $old ) );
		$this->assertSame( 'Visitor saved a choice', klaro_geo_receipt_type_label( $old ) );
		$this->assertNull( $old->config_hash );
		$this->assertStringStartsWith( 'Not recorded', klaro_geo_receipt_config_status_label( $old ) );
	}

	/**
	 * A receipt that arrives while the table is behind the schema, or missing,
	 * is not lost: the table is repaired and the receipt stored whole.
	 */
	public function test_receipt_is_stored_when_the_table_is_behind_or_missing() {
		global $wpdb;
		$table = klaro_geo_receipts_table_name();

		$wpdb->query( "ALTER TABLE $table DROP COLUMN receipt_type" );
		$suppress = $wpdb->suppress_errors( true );
		$behind   = klaro_geo_store_consent_receipt( $this->receipt( 'receipt_table_behind' ) );
		$wpdb->suppress_errors( $suppress );

		$this->assertIsInt( $behind );
		$row = $this->stored( 'receipt_table_behind' );
		$this->assertSame( 'choice', $row->receipt_type );
		$this->assertSame( '1', (string) $row->gpc_signal );
		$this->assertSame( array( 'google-ads' ), json_decode( $row->gpc_affected_services, true ) );

		$wpdb->query( "DROP TABLE $table" );
		$this->assertFalse( klaro_geo_receipts_table_exists() );
		$suppress = $wpdb->suppress_errors( true );
		$missing  = klaro_geo_store_consent_receipt( $this->receipt( 'receipt_table_missing' ) );
		$wpdb->suppress_errors( $suppress );

		$this->assertIsInt( $missing );
		$this->assertTrue( klaro_geo_receipts_table_exists() );
		$this->assertSame( '1', (string) $this->stored( 'receipt_table_missing' )->gpc_signal );
	}
}
