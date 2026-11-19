<?php
/**
 * Tests for consent receipt validation and storage.
 *
 * These call klaro_geo_store_consent_receipt(), the function the public AJAX
 * endpoint wraps, against the real receipts table.
 *
 * @package Klaro_Geo
 */

class KlaroGeoConsentReceiptStorageTest extends WP_UnitTestCase {

	/**
	 * The table is created once, outside the per-test transaction, so it is a
	 * real table (WP_UnitTestCase turns CREATE TABLE inside a test into a
	 * temporary table, which SHOW TABLES cannot see). Rows written by a test
	 * are rolled back with that test's transaction.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		klaro_geo_create_consent_receipts_table();
	}

	public static function wpTearDownAfterClass() {
		klaro_geo_drop_consent_receipts_table();
	}

	public function set_up() {
		parent::set_up();

		update_option( 'klaro_geo_enable_consent_receipts', true );
		update_option(
			'klaro_geo_templates',
			array(
				'default'    => array(
					'name'            => 'Default Template',
					'config'          => array( 'default' => false ),
					'plugin_settings' => array( 'enable_consent_logging' => true ),
				),
				'no_logging' => array(
					'name'            => 'No Logging Template',
					'config'          => array( 'default' => false ),
					'plugin_settings' => array( 'enable_consent_logging' => false ),
				),
			)
		);
		Klaro_Geo_Template_Settings::clear_instance_cache();
	}

	public function tear_down() {
		Klaro_Geo_Template_Settings::clear_instance_cache();
		parent::tear_down();
	}

	/**
	 * A valid receipt as the browser sends it.
	 *
	 * @param array $overrides Fields to replace.
	 * @return array
	 */
	private function receipt( array $overrides = array() ) {
		return array_merge(
			array(
				'receipt_id'      => klaro_geo_generate_receipt_id(),
				'timestamp'       => 1795000000,
				'consent_choices' => array(
					'google-analytics' => true,
					'facebook-pixel'   => false,
				),
				'template_name'   => 'default',
				'template_source' => 'fallback',
				'country_code'    => 'US',
				'region_code'     => 'CA',
			),
			$overrides
		);
	}

	/**
	 * Fetch a stored row by its public receipt_id.
	 */
	private function stored( $receipt_id ) {
		global $wpdb;
		$table = klaro_geo_receipts_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE receipt_id = %s", $receipt_id ) );
	}

	private function count_rows() {
		return (int) klaro_geo_receipts_count();
	}

	public function test_table_has_expected_columns() {
		global $wpdb;
		$table   = klaro_geo_receipts_table_name();
		$columns = wp_list_pluck( $wpdb->get_results( "DESCRIBE $table" ), 'Field' );

		foreach ( array( 'id', 'receipt_id', 'user_id', 'ip_address', 'timestamp', 'consent_data', 'template_name', 'template_source', 'country_code', 'region_code', 'user_agent', 'klaro_config' ) as $column ) {
			$this->assertContains( $column, $columns );
		}
	}

	public function test_generated_receipt_ids_are_unique_and_valid() {
		$ids = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$id = klaro_geo_generate_receipt_id();
			$this->assertMatchesRegularExpression( '/^receipt_[A-Za-z0-9_\-]{1,42}$/', $id );
			$this->assertNotContains( $id, $ids );
			$ids[] = $id;
		}
	}

	public function test_ip_anonymization() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.123';
		$this->assertSame( '192.168.1.0', klaro_geo_get_anonymized_ip() );

		$_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
		$this->assertSame( '2001:0db8:85a3:0:0:0:0:0', klaro_geo_get_anonymized_ip() );
	}

	public function test_valid_receipt_is_stored() {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
		$receipt                = $this->receipt();

		$result = klaro_geo_store_consent_receipt( $receipt );

		$this->assertIsInt( $result );
		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertNotNull( $row );
		$this->assertSame( (string) $result, (string) $row->id );
		$this->assertSame( 'default', $row->template_name );
		$this->assertSame( 'fallback', $row->template_source );
		$this->assertSame( 'US', $row->country_code );
		$this->assertSame( 'CA', $row->region_code );
		$this->assertSame( '203.0.113.0', $row->ip_address );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', 1795000000 ), $row->timestamp );
		$this->assertSame( $receipt['consent_choices'], json_decode( $row->consent_data, true ) );
	}

	public function test_template_source_is_normalized() {
		$cases = array(
			array( array( 'template_source' => 'country', 'region_code' => '' ), 'geo-match country' ),
			array( array( 'template_source' => 'region', 'region_code' => 'CA' ), 'geo-match region' ),
			array( array( 'template_source' => 'country', 'region_code' => '', 'admin_override' => true ), 'admin-override country' ),
			array( array( 'template_source' => 'admin-override region', 'region_code' => 'QC' ), 'admin-override region' ),
			array( array( 'template_source' => 'fallback' ), 'fallback' ),
		);

		foreach ( $cases as $case ) {
			$receipt = $this->receipt( $case[0] );
			$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );
			$this->assertSame( $case[1], $this->stored( $receipt['receipt_id'] )->template_source );
		}
	}

	public function test_not_stored_when_receipts_are_disabled_globally() {
		update_option( 'klaro_geo_enable_consent_receipts', false );

		$result = klaro_geo_store_consent_receipt( $this->receipt() );

		$this->assertWPError( $result );
		$this->assertSame( 'klaro_geo_logging_disabled', $result->get_error_code() );
		$this->assertSame( 0, $this->count_rows() );
	}

	public function test_not_stored_when_the_template_has_logging_disabled() {
		$stored  = klaro_geo_store_consent_receipt( $this->receipt() );
		$refused = klaro_geo_store_consent_receipt( $this->receipt( array( 'template_name' => 'no_logging' ) ) );

		$this->assertIsInt( $stored );
		$this->assertWPError( $refused );
		$this->assertSame( 'klaro_geo_logging_disabled', $refused->get_error_code() );
		$this->assertSame( 1, $this->count_rows() );
	}

	public function test_duplicate_receipt_id_is_not_stored_twice() {
		$receipt = $this->receipt();

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );
		$second = klaro_geo_store_consent_receipt( $receipt );

		$this->assertWPError( $second );
		$this->assertSame( 'klaro_geo_duplicate_receipt', $second->get_error_code() );
		$this->assertSame( 1, $this->count_rows() );
	}

	/**
	 * @dataProvider invalid_receipts
	 */
	public function test_invalid_receipts_are_rejected( $raw ) {
		$result = klaro_geo_store_consent_receipt( $raw );

		$this->assertWPError( $result );
		$this->assertSame( 'klaro_geo_invalid_receipt', $result->get_error_code() );
		$this->assertSame( 0, $this->count_rows() );
	}

	public function invalid_receipts() {
		$base = array(
			'receipt_id'      => 'receipt_1',
			'consent_choices' => array( 'google-analytics' => true ),
			'template_name'   => 'default',
			'template_source' => 'fallback',
		);

		return array(
			'not an array'                 => array( 'a string' ),
			'null'                         => array( null ),
			'missing receipt_id'           => array( array_diff_key( $base, array( 'receipt_id' => 1 ) ) ),
			'receipt_id with markup'       => array( array_merge( $base, array( 'receipt_id' => '<img src=x onerror=alert(1)>' ) ) ),
			'receipt_id too long'          => array( array_merge( $base, array( 'receipt_id' => str_repeat( 'a', 51 ) ) ) ),
			'receipt_id not a string'      => array( array_merge( $base, array( 'receipt_id' => array( 'x' ) ) ) ),
			'missing consent_choices'      => array( array_diff_key( $base, array( 'consent_choices' => 1 ) ) ),
			'consent_choices not an array' => array( array_merge( $base, array( 'consent_choices' => '<script>alert(1)</script>' ) ) ),
			'too many consent choices'     => array( array_merge( $base, array( 'consent_choices' => array_fill( 0, 201, true ) ) ) ),
		);
	}

	/**
	 * Consent choices are stored as service => boolean. A value that is not
	 * literally true is stored as false, so markup can never be stored.
	 */
	public function test_consent_choice_values_are_coerced_to_booleans() {
		$receipt = $this->receipt(
			array(
				'consent_choices' => array(
					'google-analytics'    => true,
					'payload'             => '</pre><img src=x onerror=alert(1)>',
					'truthy-string'       => 'true',
					'<b>bold</b>-service' => true,
				),
			)
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertStringNotContainsString( '<', $row->consent_data );
		$this->assertSame(
			array(
				'google-analytics' => true,
				'payload'          => false,
				'truthy-string'    => false,
				'bold-service'     => true,
			),
			json_decode( $row->consent_data, true )
		);
	}

	public function test_text_fields_are_stripped_of_markup() {
		$receipt = $this->receipt(
			array(
				'template_name' => 'default<script>alert(1)</script>',
				'country_code'  => '<b>US</b>',
				'region_code'   => '',
			)
		);

		// The template name no longer matches a stored template, so the template
		// lookup finds nothing and the global setting applies.
		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertSame( 'default', $row->template_name );
		$this->assertSame( 'US', $row->country_code );
	}

	public function test_non_string_fields_do_not_cause_errors() {
		$receipt = $this->receipt(
			array(
				'template_source' => array( 'admin-override' ),
				'template_name'   => array( 'default' ),
				'country_code'    => null,
				'timestamp'       => 'not-a-number',
			)
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertSame( '', $row->template_name );
		$this->assertSame( 'geo-match region', $row->template_source );
		$this->assertGreaterThan( strtotime( '2020-01-01' ), strtotime( $row->timestamp ), 'A missing timestamp falls back to now' );
	}

	public function test_klaro_config_snapshot_is_sanitized() {
		$receipt = $this->receipt(
			array(
				'klaro_config' => array(
					'mustConsent' => true,
					'services'    => array(
						array( 'name' => 'ga</pre><img src=x onerror=alert(1)>' ),
					),
				),
			)
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertStringNotContainsString( '<', $row->klaro_config );
		$config = json_decode( $row->klaro_config, true );
		$this->assertTrue( $config['mustConsent'] );
		$this->assertSame( 'ga', $config['services'][0]['name'] );
	}

	public function test_oversized_klaro_config_is_dropped_not_truncated() {
		$receipt = $this->receipt(
			array( 'klaro_config' => array( 'blob' => str_repeat( 'a', 70000 ) ) )
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$this->assertNull( $this->stored( $receipt['receipt_id'] )->klaro_config );
	}
}
