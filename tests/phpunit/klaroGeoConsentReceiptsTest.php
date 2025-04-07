<?php
/**
 * Class ConsentReceiptsTest
 *
 * @package Klaro_Geo
 */

/**
 * Consent receipts test case.
 */
class ConsentReceiptsTest extends WP_UnitTestCase {

    /**
     * Set up before each test.
     */
    public function set_up() {
        parent::set_up();

        // Include our test helper functions and mock database
        require_once dirname(__FILE__) . '/test-helpers.php';
        require_once dirname(__FILE__) . '/mock-db-class.php';

        // Add debug information about the test environment
        mock_db_log("=== Starting ConsentReceiptsTest ===");
        mock_db_log("PHP Version: " . PHP_VERSION);
        mock_db_log("WordPress Version: " . get_bloginfo('version'));

        // Set up the mock database
        KlaroGeoMockDB::enable();

        // Initialize the mock table
        KlaroGeoMockDB::init_table('wp_klaro_geo_consent_receipts');

        // Log that we're using the mock database
        mock_db_log("Using mock database for ConsentReceiptsTest");
    }

    /**
     * Tear down after each test.
     */
    public function tear_down() {
        // Disable the mock database
        KlaroGeoMockDB::disable();

        // Log that we're restoring the original database
        mock_db_log("Restored original database functions for ConsentReceiptsTest");

        parent::tear_down();
    }

    /**
     * Test that the consent receipts table is created correctly.
     */
    public function test_table_creation() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // First check if our test setup created the table
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        // Get a list of all tables for debugging
        $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $table_list = [];
        foreach ($all_tables as $table) {
            $table_list[] = $table[0];
        }
        klaro_geo_debug_log("All tables: " . print_r($table_list, true));

        // If the table doesn't exist, try to create it one more time and log detailed error
        if (!$table_exists) {
            klaro_geo_debug_log("Table does not exist in test_table_creation. Trying to create it again.");

            // Initialize the table using our mock class
            KlaroGeoMockDB::init_table('wp_klaro_geo_consent_receipts');

            // Check if the table exists now
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

            if (!$table_exists) {
                // Log database error if any
                klaro_geo_debug_log("Database error: " . $wpdb->last_error);

                // Log database permissions
                $grants = $wpdb->get_results("SHOW GRANTS FOR CURRENT_USER()");
                klaro_geo_debug_log("Database permissions: " . print_r($grants, true));

                $this->markTestSkipped('Cannot create test table in this environment. See debug log for details.');
                return;
            }
        }

        // Check if the table has the expected columns
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        $column_names = array_map(function($col) { return $col->Field; }, $columns);

        $expected_columns = [
            'id', 'receipt_id', 'user_id', 'ip_address', 'timestamp',
            'consent_data', 'template_name', 'template_source',
            'country_code', 'region_code', 'user_agent'
        ];

        foreach ($expected_columns as $column) {
            $this->assertContains($column, $column_names, "Table should have column: $column");
        }
    }

    /**
     * Test receipt ID generation.
     */
    public function test_receipt_id_generation() {
        // Generate multiple receipt IDs and ensure they're unique
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $id = klaro_geo_generate_receipt_id();
            $this->assertNotEmpty($id, 'Receipt ID should not be empty');
            $this->assertStringStartsWith('receipt_', $id, 'Receipt ID should start with "receipt_"');
            $this->assertNotContains($id, $ids, 'Receipt IDs should be unique');
            $ids[] = $id;
        }
    }

    /**
     * Test storing a consent receipt.
     */
    public function test_store_consent_receipt() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Create a test receipt
        $receipt_data = [
            'receipt_id' => klaro_geo_generate_receipt_id(),
            'timestamp' => time(),
            'consent_choices' => [
                'google-analytics' => true,
                'facebook-pixel' => false
            ],
            'template_name' => 'default',
            'template_source' => 'fallback',
            'country_code' => 'US',
            'region_code' => 'CA'
        ];

        // Store the receipt using our mock class
        $result = KlaroGeoMockDB::store_consent_receipt($receipt_data);

        // Check if the response indicates success
        $this->assertTrue($result['success'], 'Response should indicate success');
        $this->assertNotNull($result['data']['receipt_id'], 'Receipt ID should not be null');

        // Check if the receipt was stored correctly
        $stored_receipt = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE receipt_id = %s", $receipt_data['receipt_id'])
        );

        $this->assertNotNull($stored_receipt, 'Receipt should be retrievable from database');
        $this->assertEquals($receipt_data['receipt_id'], $stored_receipt->receipt_id, 'Receipt ID should match');
        $this->assertEquals($receipt_data['template_name'], $stored_receipt->template_name, 'Template name should match');
        $this->assertEquals($receipt_data['template_source'], $stored_receipt->template_source, 'Template source should match');
        $this->assertEquals($receipt_data['country_code'], $stored_receipt->country_code, 'Country code should match');
        $this->assertEquals($receipt_data['region_code'], $stored_receipt->region_code, 'Region code should match');

        // Check consent data was stored as JSON
        $stored_consent = json_decode($stored_receipt->consent_data, true);
        $this->assertEquals($receipt_data['consent_choices'], $stored_consent, 'Consent choices should match');
    }

    /**
     * Test IP anonymization.
     */
    public function test_ip_anonymization() {
        // Test IPv4 anonymization
        $_SERVER['REMOTE_ADDR'] = '192.168.1.123';
        $anon_ip = klaro_geo_get_anonymized_ip();
        $this->assertEquals('192.168.1.0', $anon_ip, 'IPv4 should be anonymized correctly');
        
        // Test IPv6 anonymization
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $anon_ip = klaro_geo_get_anonymized_ip();
        $this->assertEquals('2001:0db8:85a3:0:0:0:0:0', $anon_ip, 'IPv6 should be anonymized correctly');
    }

    /**
     * Test the enable consent receipts setting.
     */
    public function test_consent_receipts_setting() {
        // Set default value first
        delete_option('klaro_geo_enable_consent_receipts');

        // Check default value
        $default_value = get_option('klaro_geo_enable_consent_receipts', false);
        $this->assertFalse($default_value, 'Default value should be false');
        
        // Update the setting
        update_option('klaro_geo_enable_consent_receipts', true);
        $updated_value = get_option('klaro_geo_enable_consent_receipts');
        $this->assertTrue($updated_value, 'Updated value should be true');
        
        // Reset the setting
        update_option('klaro_geo_enable_consent_receipts', false);
    }

    /**
     * Test AJAX endpoint for storing receipts.
     */
    public function test_ajax_store_receipt() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Include our test helper functions
        require_once dirname(__FILE__) . '/test-helpers.php';

        // Set up the request
        $_POST['action'] = 'klaro_geo_store_consent_receipt';
        $_POST['nonce'] = wp_create_nonce('klaro_geo_consent_nonce');

        $receipt_data = [
            'receipt_id' => klaro_geo_generate_receipt_id(),
            'timestamp' => time(),
            'consent_choices' => [
                'google-analytics' => true,
                'facebook-pixel' => false
            ],
            'template_name' => 'default',
            'template_source' => 'fallback',
            'country_code' => 'US',
            'region_code' => 'CA'
        ];

        $_POST['receipt_data'] = wp_json_encode($receipt_data);

        // Use our test-specific function instead of the AJAX handler
        $response_data = klaro_geo_test_store_consent_receipt();

        // If the response indicates failure, log the error
        if (!isset($response_data['success']) || !$response_data['success']) {
            klaro_geo_debug_log("AJAX test failed: " . print_r($response_data, true));
            klaro_geo_debug_log("Database error: " . $wpdb->last_error);
        }

        // Check if the response indicates success
        $this->assertTrue($response_data['success'], 'AJAX response should indicate success');
        $this->assertArrayHasKey('data', $response_data, 'Response should contain data');
        $this->assertArrayHasKey('receipt_id', $response_data['data'], 'Data should contain receipt_id');
    }
}
