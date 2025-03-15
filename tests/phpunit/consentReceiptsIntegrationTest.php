<?php
/**
 * Class ConsentReceiptsIntegrationTest
 *
 * @package Klaro_Geo
 */

// Include the custom test case
require_once dirname(__FILE__) . '/ignoreDeprecatedTestCase.php';

/**
 * Consent receipts integration test case.
 */
class ConsentReceiptsIntegrationTest extends IgnoreDeprecatedTestCase {


    /**
     * Set up before each test.
     */
    public function set_up() {
        parent::set_up();

        // Include our test helper functions and mock database
        require_once dirname(__FILE__) . '/test-helpers.php';
        require_once dirname(__FILE__) . '/mock-db.php';

        // Set up the mock database
        KlaroGeoMockDB::enable();

        // Initialize the mock table
        KlaroGeoMockDB::init_table('wp_klaro_geo_consent_receipts');

        // Enable consent receipts
        update_option('klaro_geo_enable_consent_receipts', true);

        // Log that we're using the mock database
        mock_db_log("Using mock database for ConsentReceiptsIntegrationTest");
    }

    /**
     * Tear down after each test.
     */
    public function tear_down() {
        // Disable consent receipts
        update_option('klaro_geo_enable_consent_receipts', false);

        // Disable the mock database
        KlaroGeoMockDB::disable();

        // Log that we're restoring the original database
        mock_db_log("Restored original database functions for ConsentReceiptsIntegrationTest");

        parent::tear_down();
    }

    /**
     * Test that the JavaScript variables are added to the page when consent receipts are enabled.
     */
    public function test_js_variables_added() {
        // Set up a test post
        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));

        // Capture the output of wp_head
        ob_start();
        do_action('wp_head');
        $head_output = ob_get_clean();

        // Capture the output of wp_footer
        ob_start();
        do_action('wp_footer');
        $footer_output = ob_get_clean();

        // Check if the consent receipts script is enqueued
        $this->assertStringContainsString('klaro-geo-consent-receipts.js', $footer_output, 'Consent receipts script should be enqueued');

        // Check if the klaroConsentData object is added
        $this->assertStringContainsString('klaroConsentData', $head_output . $footer_output, 'klaroConsentData object should be added');
    }

    /**
     * Test that the klaro-config.js file contains the necessary consent receipt code.
     */
    public function test_config_file_contains_receipt_code() {
        // Generate the config file
        klaro_geo_generate_config_file();

        // Get the file path - need to go up one more directory to reach the plugin root
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $klaro_config_file = $plugin_dir . 'klaro-config.js';

        // Check if the file exists
        $this->assertFileExists($klaro_config_file, 'klaro-config.js file should exist');

        // Get the file contents
        $config_content = file_get_contents($klaro_config_file);

        // Check if the consent receipt code is included
        $this->assertStringContainsString('klaroConsentReceiptsEnabled = true', $config_content, 'Config should enable consent receipts');
        $this->assertStringContainsString('klaro:consent-change', $config_content, 'Config should include event listener');
        $this->assertStringContainsString('klaro_geo_consent_receipt', $config_content, 'Config should include dataLayer event name');
    }

    /**
     * Test the full consent receipt flow using the AJAX endpoint.
     */
    public function test_full_consent_receipt_flow() {
        // Set up the AJAX request
        $_POST['action'] = 'klaro_geo_store_consent_receipt';
        $_POST['nonce'] = wp_create_nonce('klaro_geo_consent_nonce');

        $receipt_data = [
            'receipt_id' => 'integration_test_receipt_' . time(),
            'timestamp' => time(),
            'consent_choices' => [
                'google-analytics' => true,
                'facebook-pixel' => false,
                'google-tag-manager' => true
            ],
            'template_name' => 'default',
            'template_source' => 'fallback',
            'country_code' => 'US',
            'region_code' => 'CA',
            'template_settings' => [
                'consentModalTitle' => 'Privacy Settings',
                'consentModalDescription' => 'Test description',
                'acceptAllText' => 'Accept All',
                'declineAllText' => 'Decline All',
                'defaultConsent' => false,
                'requiredConsent' => false
            ]
        ];

        $_POST['receipt_data'] = wp_json_encode($receipt_data);

        // Include our test helper functions
        require_once dirname(__FILE__) . '/test-helpers.php';

        // Use our test-specific function instead of the AJAX handler
        $response_data = klaro_geo_test_store_consent_receipt();

        // Check if the response indicates success
        $this->assertTrue($response_data['success'], 'AJAX response should indicate success');
        $this->assertArrayHasKey('data', $response_data, 'Response should contain data');
        $this->assertArrayHasKey('receipt_id', $response_data['data'], 'Data should contain receipt_id');

        // Get the stored receipt from the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        $stored_receipt = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE receipt_id = %s", $receipt_data['receipt_id'])
        );

        // Check if the receipt was stored correctly
        $this->assertNotNull($stored_receipt, 'Receipt should be stored in the database');
        $this->assertEquals($receipt_data['receipt_id'], $stored_receipt->receipt_id, 'Receipt ID should match');
        $this->assertEquals($receipt_data['template_name'], $stored_receipt->template_name, 'Template name should match');
        $this->assertEquals($receipt_data['country_code'], $stored_receipt->country_code, 'Country code should match');

        // Check consent data
        $stored_consent = json_decode($stored_receipt->consent_data, true);
        $this->assertEquals($receipt_data['consent_choices'], $stored_consent, 'Consent choices should match');

        // Now test retrieving the receipt via the admin AJAX endpoint
        $_POST = array();
        $_POST['action'] = 'klaro_geo_get_receipt_details';
        $_POST['nonce'] = wp_create_nonce('klaro_geo_admin_nonce');
        $_POST['receipt_id'] = $stored_receipt->id;

        // Set current user as admin
        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);

        // Use our test-specific function instead of the AJAX handler
        $response_data = klaro_geo_test_get_receipt_details($stored_receipt->id);

        // Check if the response indicates success
        $this->assertTrue($response_data['success'], 'Admin AJAX response should indicate success');
        $this->assertEquals($receipt_data['receipt_id'], $response_data['data']->receipt_id, 'Retrieved receipt ID should match');

        // Check consent data in retrieved receipt
        $retrieved_consent = json_decode($response_data['data']->consent_data, true);
        $this->assertEquals($receipt_data['consent_choices'], $retrieved_consent, 'Retrieved consent choices should match');
    }

    /**
     * Test that consent receipts are not processed when the feature is disabled.
     *
     * @expectedDeprecated the_block_template_skip_link
     */
    public function test_disabled_consent_receipts() {
        // Disable consent receipts
        update_option('klaro_geo_enable_consent_receipts', false);

        // Generate the config file
        klaro_geo_generate_config_file();

        // Get the file path - need to go up one more directory to reach the plugin root
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $klaro_config_file = $plugin_dir . 'klaro-config.js';

        // Get the file contents
        $config_content = file_get_contents($klaro_config_file);

        // Check that consent receipt code is not included
        $this->assertStringNotContainsString('klaroConsentReceiptsEnabled = true', $config_content, 'Config should not enable consent receipts');

        // Set up a test post
        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));

        // Temporarily suppress deprecation notices
        $old_filter = $GLOBALS['wp_filter']['deprecated_function_trigger_error'];
        add_filter('deprecated_function_trigger_error', '__return_false', 999);

        // Capture the output of wp_footer
        ob_start();
        do_action('wp_footer');
        $footer_output = ob_get_clean();

        // Restore the original filter
        $GLOBALS['wp_filter']['deprecated_function_trigger_error'] = $old_filter;

        // Check that the consent receipts script is not enqueued
        $this->assertStringNotContainsString('klaro-geo-consent-receipts.js', $footer_output, 'Consent receipts script should not be enqueued');
    }
}