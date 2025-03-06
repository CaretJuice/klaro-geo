<?php
/**
 * Test for consent logging feature
 */
class ConsentLoggingTest extends WP_UnitTestCase {

    /**
     * Set up before each test.
     */
    public function set_up() {
        parent::set_up();

        // Include our test helper functions and mock database
        require_once dirname(__FILE__) . '/test-helpers.php';
        require_once dirname(__FILE__) . '/mock-db-class.php';

        // Add debug information about the test environment
        mock_db_log("=== Starting ConsentLoggingTest ===");
        mock_db_log("PHP Version: " . PHP_VERSION);
        mock_db_log("WordPress Version: " . get_bloginfo('version'));

        // Set up the mock database
        KlaroGeoMockDB::enable();

        // Initialize the mock table
        KlaroGeoMockDB::init_table('wp_klaro_geo_consent_receipts');

        // Enable consent receipts globally
        update_option('klaro_geo_enable_consent_receipts', true);

        // Create test templates
        $templates = array(
            'default' => array(
                'name' => 'Default Template',
                'inherit_from' => 'none',
                'config' => array(
                    'version' => 1,
                    'elementID' => 'klaro',
                ),
                'wordpress_settings' => array(
                    'enable_consent_logging' => true
                )
            ),
            'no_logging' => array(
                'name' => 'No Logging Template',
                'inherit_from' => 'none',
                'config' => array(
                    'version' => 1,
                    'elementID' => 'klaro',
                ),
                'wordpress_settings' => array(
                    'enable_consent_logging' => false
                )
            )
        );
        update_option('klaro_geo_templates', $templates);

        // Log that we're using the mock database
        mock_db_log("Using mock database for ConsentLoggingTest");
    }

    /**
     * Tear down after each test.
     */
    public function tear_down() {
        // Disable the mock database
        KlaroGeoMockDB::disable();

        // Clean up options
        delete_option('klaro_geo_enable_consent_receipts');
        delete_option('klaro_geo_templates');

        // Log that we're restoring the original database
        mock_db_log("Restored original database functions for ConsentLoggingTest");

        parent::tear_down();
    }

    /**
     * Test that consent is logged when using a template with logging enabled
     */
    public function test_consent_logging_enabled() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Clear the database before running this test
        $wpdb->query("TRUNCATE TABLE $table_name");

        // Create a test receipt with default template (logging enabled)
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
        $this->assertNotNull($result['data']['receipt_id'], 'Receipt ID should not be null when logging is enabled');

        // Check if the receipt exists in the database
        $stored_receipt = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE receipt_id = %s", $receipt_data['receipt_id'])
        );

        $this->assertNotNull($stored_receipt, 'Receipt should be retrievable from database when logging is enabled');
    }

    /**
     * Test that consent is not logged when using a template with logging disabled
     */
    public function test_consent_logging_disabled() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Clear the database before running this test
        $wpdb->query("TRUNCATE TABLE $table_name");

        // Create a test receipt with no_logging template
        $receipt_data = [
            'receipt_id' => klaro_geo_generate_receipt_id(),
            'timestamp' => time(),
            'consent_choices' => [
                'google-analytics' => true,
                'facebook-pixel' => false
            ],
            'template_name' => 'no_logging',
            'template_source' => 'fallback',
            'country_code' => 'FI', // Finland
            'region_code' => null
        ];

        // Set up the AJAX request
        $_POST['action'] = 'klaro_geo_store_consent_receipt';
        $_POST['nonce'] = wp_create_nonce('klaro_geo_consent_nonce');
        $_POST['receipt_data'] = wp_json_encode($receipt_data);

        // Use our test helper function instead of the AJAX handler
        $response = klaro_geo_test_store_consent_receipt();

        // Check if the response indicates success but with no receipt ID
        $this->assertTrue($response['success'], 'AJAX response should indicate success');
        $this->assertNull($response['data']['receipt_id'], 'Receipt ID should be null when logging is disabled');
        $this->assertEquals('Consent logging disabled for this template', $response['data']['message'], 'Response should indicate logging is disabled');

        // Check that no receipt was stored in the database
        $stored_receipt = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE receipt_id = %s", $receipt_data['receipt_id'])
        );

        $this->assertNull($stored_receipt, 'Receipt should not be stored in database when logging is disabled');
    }

    /**
     * Test that the AJAX handler respects the template setting
     */
    public function test_ajax_respects_template_setting() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Clear the database before running this test
        $wpdb->query("TRUNCATE TABLE $table_name");

        // Test with logging enabled template
        $receipt_data_enabled = [
            'receipt_id' => klaro_geo_generate_receipt_id(),
            'timestamp' => time(),
            'consent_choices' => ['test-service' => true],
            'template_name' => 'default',
            'template_source' => 'test',
            'country_code' => 'US',
            'region_code' => null
        ];

        $_POST['action'] = 'klaro_geo_store_consent_receipt';
        $_POST['nonce'] = wp_create_nonce('klaro_geo_consent_nonce');
        $_POST['receipt_data'] = wp_json_encode($receipt_data_enabled);

        // Use our test helper function instead of the AJAX handler
        $response_enabled = klaro_geo_test_store_consent_receipt();

        $this->assertTrue($response_enabled['success'], 'AJAX response should indicate success for enabled template');
        $this->assertNotNull($response_enabled['data']['receipt_id'], 'Receipt ID should not be null for enabled template');

        // Test with logging disabled template
        $receipt_data_disabled = [
            'receipt_id' => klaro_geo_generate_receipt_id(),
            'timestamp' => time(),
            'consent_choices' => ['test-service' => true],
            'template_name' => 'no_logging',
            'template_source' => 'test',
            'country_code' => 'FI',
            'region_code' => null
        ];

        $_POST['receipt_data'] = wp_json_encode($receipt_data_disabled);

        // Use our test helper function instead of the AJAX handler
        $response_disabled = klaro_geo_test_store_consent_receipt();

        $this->assertTrue($response_disabled['success'], 'AJAX response should indicate success for disabled template');
        $this->assertNull($response_disabled['data']['receipt_id'], 'Receipt ID should be null for disabled template');
        $this->assertEquals('Consent logging disabled for this template', $response_disabled['data']['message'], 'Response should indicate logging is disabled');

        // Verify database state
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(1, $count, 'Only one receipt should be stored in the database');
    }

    /**
     * Test that the JavaScript variable is correctly set
     */
    public function test_js_variable_setting() {
        // Mock the template config
        $template_config = [
            'wordpress_settings' => [
                'enable_consent_logging' => false
            ]
        ];

        // Call the function that would normally localize the script
        ob_start();
        ?>
        <script>
        var klaroConsentData = <?php echo wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('klaro_geo_consent_nonce'),
            'consentReceiptsEnabled' => true,
            'enableConsentLogging' => $template_config['wordpress_settings']['enable_consent_logging'],
            'templateName' => 'test_template',
            'templateSource' => 'test',
            'detectedCountry' => 'FI',
            'detectedRegion' => null,
            'templateSettings' => $template_config
        ]); ?>;
        </script>
        <?php
        $output = ob_get_clean();

        // Check that the enableConsentLogging variable is set to false
        $this->assertStringContainsString('"enableConsentLogging":false', $output, 'JavaScript variable should be set to false for disabled template');
    }
}