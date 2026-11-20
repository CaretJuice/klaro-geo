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
		$wpdb->query( "ALTER TABLE $table DROP COLUMN gpc_signal, DROP COLUMN gpc_affected_services" );
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

	public function test_old_table_still_stores_receipts_and_is_upgraded() {
		global $wpdb;

		// Before the upgrade has run, a receipt must not be lost for want of
		// the new columns.
		$this->assertFalse( klaro_geo_receipts_column_exists( 'gpc_signal' ) );
		$suppress = $wpdb->suppress_errors( true );
		$result   = klaro_geo_store_consent_receipt( $this->receipt( 'receipt_before_upgrade' ) );
		$wpdb->suppress_errors( $suppress );
		$this->assertIsInt( $result );

		klaro_geo_upgrade_consent_receipts_table();

		$this->assertTrue( klaro_geo_receipts_column_exists( 'gpc_signal' ) );
		$this->assertTrue( klaro_geo_receipts_column_exists( 'gpc_affected_services' ) );
		$this->assertSame( KLARO_GEO_RECEIPTS_DB_VERSION, get_option( 'klaro_geo_consent_receipts_db_version' ) );

		// The receipt stored before the upgrade says "not recorded", not "no signal".
		$old = $this->stored( 'receipt_before_upgrade' );
		$this->assertNull( $old->gpc_signal );
		$this->assertSame( 'Not recorded', klaro_geo_receipt_gpc_label( $old ) );

		$this->assertIsInt( klaro_geo_store_consent_receipt( $this->receipt( 'receipt_after_upgrade' ) ) );
		$new = $this->stored( 'receipt_after_upgrade' );
		$this->assertSame( '1', (string) $new->gpc_signal );
		$this->assertSame( array( 'google-ads' ), json_decode( $new->gpc_affected_services, true ) );
	}
}
