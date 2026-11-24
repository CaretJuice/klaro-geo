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
				'receipt_id'      => 'receipt_' . wp_generate_password( 16, false ),
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

		foreach ( array( 'id', 'receipt_id', 'user_id', 'ip_address', 'timestamp', 'consent_data', 'template_name', 'template_source', 'country_code', 'region_code', 'user_agent', 'klaro_config', 'gpc_signal', 'gpc_affected_services', 'receipt_type' ) as $column ) {
			$this->assertContains( $column, $columns );
		}
	}

	public function test_ip_anonymization() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.123';
		$this->assertSame( '192.168.1.0', klaro_geo_get_anonymized_ip() );

		$_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
		$this->assertSame( '2001:db8:85a3::', klaro_geo_get_anonymized_ip() );
	}

	/**
	 * An IPv6 address has many spellings. Every one of them is cut to its /48;
	 * the shortened forms used to be stored whole.
	 *
	 * @dataProvider ipv6_spellings
	 */
	public function test_ipv6_is_anonymized_however_it_is_written( $address, $expected ) {
		$_SERVER['REMOTE_ADDR'] = $address;
		$this->assertSame( $expected, klaro_geo_get_anonymized_ip() );
	}

	public function ipv6_spellings() {
		return array(
			'full'                 => array( '2a02:1234:5678:9abc:1122:3344:5566:7788', '2a02:1234:5678::' ),
			'zeros in the middle'  => array( '2001:db8:1:2::abcd', '2001:db8:1::' ),
			'short'                => array( '2001:db8::1', '2001:db8::' ),
			'zeros near the end'   => array( '2001:db8:aaaa:bbbb:1::2', '2001:db8:aaaa::' ),
			'upper case'           => array( '2001:DB8:AAAA:BBBB:1::2', '2001:db8:aaaa::' ),
			'IPv4 written as IPv6' => array( '::ffff:203.0.113.77', '203.0.113.0' ),
		);
	}

	public function test_a_value_that_is_not_an_ip_address_is_not_stored() {
		$_SERVER['REMOTE_ADDR'] = 'not-an-address';
		$this->assertSame( '', klaro_geo_get_anonymized_ip() );

		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertSame( '', klaro_geo_get_anonymized_ip() );
	}

	/**
	 * A site behind a proxy supplies the visitor's address with a filter; it is
	 * anonymized like any other.
	 */
	public function test_client_ip_filter_replaces_the_proxy_address() {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.5';
		$from_proxy_header      = function () {
			return '198.51.100.77';
		};
		add_filter( 'klaro_geo_client_ip', $from_proxy_header );

		$this->assertSame( '198.51.100.0', klaro_geo_get_anonymized_ip() );

		remove_filter( 'klaro_geo_client_ip', $from_proxy_header );
		$this->assertSame( '10.0.0.0', klaro_geo_get_anonymized_ip() );
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
		$this->assertEqualsWithDelta( time(), strtotime( $row->timestamp . ' UTC' ), 5, 'The time recorded is the server\'s' );
		$this->assertSame( $receipt['consent_choices'], json_decode( $row->consent_data, true ) );
	}

	/**
	 * The time of a consent record is when the server received it. A time
	 * sent by the browser could back-date the record, or be one the database
	 * cannot store.
	 *
	 * @dataProvider submitted_timestamps
	 */
	public function test_submitted_timestamp_is_ignored( $timestamp ) {
		$receipt = $this->receipt( array( 'timestamp' => $timestamp ) );

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertEqualsWithDelta( time(), strtotime( $row->timestamp . ' UTC' ), 5 );
	}

	public function submitted_timestamps() {
		return array(
			'in the past'         => array( 946684800 ),
			'in the future'       => array( 4102444800 ),
			'past the year 9999'  => array( '99999999999999999999' ),
			'not a number'        => array( 'not-a-number' ),
		);
	}

	/**
	 * The template named must exist. Anything else is a receipt for a banner
	 * no visitor was shown.
	 *
	 * @dataProvider unknown_template_names
	 */
	public function test_receipt_for_an_unknown_template_is_refused( $template_name ) {
		$before  = $this->count_rows();
		$refused = klaro_geo_store_consent_receipt( $this->receipt( array( 'template_name' => $template_name ) ) );

		$this->assertWPError( $refused );
		$this->assertSame( 'klaro_geo_unknown_template', $refused->get_error_code() );
		$this->assertSame( $before, $this->count_rows() );
	}

	public function unknown_template_names() {
		return array(
			'made up' => array( 'no_such_template' ),
			'empty'   => array( '' ),
			'array'   => array( array( 'default' ) ),
		);
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

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertSame( 'default', $row->template_name );
		$this->assertSame( 'US', $row->country_code );
	}

	public function test_non_string_fields_do_not_cause_errors() {
		$receipt = $this->receipt(
			array(
				'template_source' => array( 'admin-override' ),
				'country_code'    => null,
				'timestamp'       => array( 1795000000 ),
			)
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row = $this->stored( $receipt['receipt_id'] );
		$this->assertSame( '', $row->country_code );
		$this->assertSame( 'geo-match region', $row->template_source );
	}

	/**
	 * The Klaro config stored with a receipt is the one the server gives
	 * visitors for that template. A copy sent with the receipt is not stored:
	 * it would be tens of kilobytes chosen by whoever sent the request.
	 */
	public function test_klaro_config_snapshot_is_built_by_the_server() {
		$receipt = $this->receipt(
			array(
				'klaro_config' => array(
					'injected' => str_repeat( 'a', 60000 ),
					'services' => array( array( 'name' => 'ga</pre><img src=x onerror=alert(1)>' ) ),
				),
			)
		);

		$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );

		$row      = $this->stored( $receipt['receipt_id'] );
		$expected = klaro_geo_build_template_payload(
			'default',
			Klaro_Geo_Template_Settings::get_instance()->get(),
			Klaro_Geo_Service_Settings::get_instance()->get()
		);

		$this->assertStringNotContainsString( 'injected', $row->klaro_config );
		$this->assertStringNotContainsString( 'onerror', $row->klaro_config );
		$this->assertSame( wp_json_encode( $expected['config'] ), $row->klaro_config );
		$this->assertNotEmpty( json_decode( $row->klaro_config, true )['services'] );
	}

	/**
	 * The receipt records whether the browser sent Global Privacy Control and
	 * which services were switched off because of it.
	 */
	public function test_gpc_signal_and_affected_services_are_stored() {
		$with = $this->receipt(
			array(
				'gpc_signal'            => true,
				'gpc_affected_services' => array( 'google-ads', 'facebook-pixel' ),
			)
		);
		$without = $this->receipt();

		$this->assertIsInt( klaro_geo_store_consent_receipt( $with ) );
		$this->assertIsInt( klaro_geo_store_consent_receipt( $without ) );

		$row = $this->stored( $with['receipt_id'] );
		$this->assertSame( '1', (string) $row->gpc_signal );
		$this->assertSame( array( 'google-ads', 'facebook-pixel' ), json_decode( $row->gpc_affected_services, true ) );
		$this->assertSame( 'Yes', klaro_geo_receipt_gpc_label( $row ) );

		$row = $this->stored( $without['receipt_id'] );
		$this->assertSame( '0', (string) $row->gpc_signal );
		$this->assertSame( array(), json_decode( $row->gpc_affected_services, true ) );
		$this->assertSame( 'No', klaro_geo_receipt_gpc_label( $row ) );
	}

	/**
	 * A receipt written because GPC was applied, with no choice made, is
	 * marked as such so it is not read as the visitor having chosen.
	 */
	public function test_receipt_type_is_choice_unless_the_receipt_says_gpc() {
		$gpc     = $this->receipt( array( 'receipt_type' => 'gpc', 'gpc_signal' => true ) );
		$choice  = $this->receipt( array( 'receipt_type' => 'choice' ) );
		$missing = $this->receipt();
		$hostile = $this->receipt( array( 'receipt_type' => '<script>alert(1)</script>' ) );

		foreach ( array( $gpc, $choice, $missing, $hostile ) as $receipt ) {
			$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt ) );
		}

		$this->assertSame( 'gpc', $this->stored( $gpc['receipt_id'] )->receipt_type );
		$this->assertSame( 'GPC signal, no choice made', klaro_geo_receipt_type_label( $this->stored( $gpc['receipt_id'] ) ) );
		foreach ( array( $choice, $missing, $hostile ) as $receipt ) {
			$this->assertSame( 'choice', $this->stored( $receipt['receipt_id'] )->receipt_type );
			$this->assertSame( 'Visitor saved a choice', klaro_geo_receipt_type_label( $this->stored( $receipt['receipt_id'] ) ) );
		}
	}

	/**
	 * The browser's Sec-GPC request header counts even when the receipt body
	 * does not report the signal.
	 */
	public function test_gpc_request_header_is_recorded() {
		$_SERVER['HTTP_SEC_GPC'] = '1';
		$receipt                 = $this->receipt();
		$result                  = klaro_geo_store_consent_receipt( $receipt );
		unset( $_SERVER['HTTP_SEC_GPC'] );

		$this->assertIsInt( $result );
		$this->assertSame( '1', (string) $this->stored( $receipt['receipt_id'] )->gpc_signal );
	}

	public function test_gpc_fields_accept_only_a_boolean_and_service_names() {
		$receipt = klaro_geo_sanitize_receipt(
			$this->receipt(
				array(
					'gpc_signal'            => 'true',
					'gpc_affected_services' => array( 'google-ads<script>alert(1)</script>', array( 'nested' ), 42, '', str_repeat( 'a', 101 ) ),
				)
			)
		);

		$this->assertFalse( $receipt['gpc_signal'], 'Only a real boolean true counts' );
		$this->assertSame( array( 'google-ads' ), $receipt['gpc_affected_services'] );

		$receipt = klaro_geo_sanitize_receipt( $this->receipt( array( 'gpc_affected_services' => 'google-ads' ) ) );
		$this->assertSame( array(), $receipt['gpc_affected_services'] );
	}

	/**
	 * A receipt is never refused for its nonce: a nonce in a cached page has
	 * expired by the time a visitor uses it, and the receipt is the record of
	 * their choice.
	 */
	public function test_anonymous_receipt_needs_no_nonce() {
		wp_set_current_user( 0 );

		foreach ( array( '', 'expired-or-forged' ) as $nonce ) {
			$receipt = $this->receipt();
			$this->assertSame( 0, klaro_geo_receipt_user_id( $nonce ) );
			$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt, klaro_geo_receipt_user_id( $nonce ) ) );
			$this->assertNull( $this->stored( $receipt['receipt_id'] )->user_id );
		}
	}

	/**
	 * A user ID is recorded only with that user's valid nonce. Without one the
	 * receipt is still stored, as anonymous, so a forged cross-site request
	 * cannot put a receipt on someone's account.
	 */
	public function test_user_id_is_recorded_only_with_a_valid_nonce() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$with_nonce = $this->receipt();
		$valid      = klaro_geo_receipt_user_id( wp_create_nonce( 'klaro_geo_consent_nonce' ) );
		$this->assertSame( $user_id, $valid );
		$this->assertIsInt( klaro_geo_store_consent_receipt( $with_nonce, $valid ) );
		$this->assertSame( $user_id, (int) $this->stored( $with_nonce['receipt_id'] )->user_id );

		foreach ( array( '', 'expired-or-forged', wp_create_nonce( 'some_other_action' ) ) as $nonce ) {
			$receipt = $this->receipt();
			$this->assertSame( 0, klaro_geo_receipt_user_id( $nonce ) );
			$this->assertIsInt( klaro_geo_store_consent_receipt( $receipt, klaro_geo_receipt_user_id( $nonce ) ) );
			$this->assertNull( $this->stored( $receipt['receipt_id'] )->user_id );
		}
	}

	public function test_rate_limit_refuses_requests_over_the_limit() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
		$limit                  = function () {
			return 3;
		};
		add_filter( 'klaro_geo_receipt_rate_limit', $limit );

		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		$this->assertTrue( klaro_geo_receipt_rate_limit_exceeded(), 'The fourth request in the window is refused' );

		// Another network is counted separately
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );

		// The same /24 shares one counter (IPs are anonymized before counting)
		$_SERVER['REMOTE_ADDR'] = '198.51.100.200';
		$this->assertTrue( klaro_geo_receipt_rate_limit_exceeded() );

		remove_filter( 'klaro_geo_receipt_rate_limit', $limit );
	}

	/**
	 * One IPv6 network is one bucket, however its addresses are written. The
	 * shortened forms used to be counted one address at a time, so a single
	 * machine had as many buckets as it has addresses.
	 */
	public function test_rate_limit_counts_an_ipv6_network_as_one_address() {
		$limit = function () {
			return 2;
		};
		add_filter( 'klaro_geo_receipt_rate_limit', $limit );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:77::1';
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		$_SERVER['REMOTE_ADDR'] = '2001:db8:77::2';
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		$_SERVER['REMOTE_ADDR'] = '2001:db8:77:5::abcd';
		$this->assertTrue( klaro_geo_receipt_rate_limit_exceeded(), 'A third address in the same /48 is over the limit' );

		$_SERVER['REMOTE_ADDR'] = '2001:db8:78::1';
		$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded(), 'Another network has its own count' );

		remove_filter( 'klaro_geo_receipt_rate_limit', $limit );
	}

	public function test_rate_limit_can_be_disabled() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.24';
		add_filter( 'klaro_geo_receipt_rate_limit', '__return_zero' );

		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertFalse( klaro_geo_receipt_rate_limit_exceeded() );
		}

		remove_filter( 'klaro_geo_receipt_rate_limit', '__return_zero' );
	}
}
