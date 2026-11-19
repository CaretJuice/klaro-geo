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
     * Decode the window.klaroConsentData object from generated config content.
     *
     * @param string $config_content Output of klaro_geo_generate_config_file().
     * @return array
     */
    private function extract_consent_data($config_content) {
        $this->assertSame(1, substr_count($config_content, 'window.klaroConsentData = '), 'klaroConsentData must be defined exactly once');
        $this->assertSame(1, preg_match('/^window\.klaroConsentData = (\{.*\});$/m', $config_content, $matches), 'klaroConsentData should be a single JSON line');
        $data = json_decode($matches[1], true);
        $this->assertIsArray($data, 'klaroConsentData should be valid JSON');
        return $data;
    }

    /**
     * klaroConsentData reaches the page once, as part of the config attached to
     * klaro-js. Nothing is localized onto the receipts script: a second copy
     * there would overwrite the first with a smaller object.
     */
    public function test_consent_data_is_emitted_once_with_the_config() {
        // Fresh script registry so an earlier test's enqueue cannot mask this one
        unset($GLOBALS['wp_scripts']);

        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));
        do_action('wp_enqueue_scripts');

        $before = implode("\n", (array) wp_scripts()->get_data('klaro-js', 'before'));
        $this->assertSame(1, substr_count($before, 'window.klaroConsentData = '), 'klaro-js config should define klaroConsentData exactly once');

        $this->assertTrue(wp_script_is('klaro-consent-receipts-js', 'enqueued'), 'Receipts script should be enqueued when receipts are enabled');
        $this->assertEmpty(wp_scripts()->get_data('klaro-consent-receipts-js', 'data'), 'Nothing should be localized onto the receipts script');
    }

    /**
     * With receipts enabled, klaroConsentData carries everything the frontend
     * scripts read, with real booleans.
     */
    public function test_consent_data_contents_when_receipts_enabled() {
        update_option('klaro_geo_gtm_id', 'GTM-TEST123');
        update_option('klaro_geo_consent_mode_type', 'advanced');

        $data = $this->extract_consent_data(klaro_geo_generate_config_file());

        delete_option('klaro_geo_gtm_id');
        delete_option('klaro_geo_consent_mode_type');

        $this->assertSame('GTM-TEST123', $data['gtmId']);
        $this->assertSame('advanced', $data['consentModeType']);
        $this->assertTrue($data['consentReceipts']);
        $this->assertIsBool($data['enableConsentLogging']);
        $this->assertIsBool($data['adminOverride']);
        $this->assertIsBool($data['suppressConsentsEvents']);
        $this->assertArrayHasKey('consentModeServices', $data);
        $this->assertArrayHasKey('parentChildMap', $data);
        $this->assertArrayHasKey('consent_defaults', $data['templateSettings']['config']['consent_mode_settings']);
        $this->assertNotEmpty($data['nonce']);
        $this->assertStringContainsString('admin-ajax.php', $data['ajaxUrl']);
    }

    /**
     * enableConsentLogging is the effective value: receipts enabled globally
     * AND consent logging enabled on the template.
     */
    public function test_enable_consent_logging_is_the_effective_value() {
        $payload = array(
            'enable_consent_logging'  => true,
            'consent_defaults'        => array(),
            'gtag_settings'           => array('ads_data_redaction' => 'true', 'url_passthrough' => 'false'),
            'template_settings_block' => array(),
        );

        update_option('klaro_geo_enable_consent_receipts', true);
        $this->assertTrue(klaro_geo_build_consent_data($payload, array())['enableConsentLogging'], 'global on + template on');

        $payload['enable_consent_logging'] = false;
        $this->assertFalse(klaro_geo_build_consent_data($payload, array())['enableConsentLogging'], 'global on + template off');

        $payload['enable_consent_logging'] = true;
        update_option('klaro_geo_enable_consent_receipts', false);
        $this->assertFalse(klaro_geo_build_consent_data($payload, array())['enableConsentLogging'], 'global off + template on');
    }

    /**
     * Test that the necessary consent receipt code exists in the JS files.
     */
    public function test_config_file_contains_receipt_code() {
        update_option('klaro_geo_enable_consent_receipts', true);

        // Generate the config content (returned as string since 0.3.5; no longer written to disk)
        $config_content = klaro_geo_generate_config_file();
        $this->assertIsString($config_content, 'Config generation failed');

        // klaro-geo.js is still a static file shipped with the plugin
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $klaro_geo_js_file = $plugin_dir . 'js/klaro-geo.js';
        $this->assertFileExists($klaro_geo_js_file, 'klaro-geo.js file should exist');
        $geo_js_content = file_get_contents($klaro_geo_js_file);

        // Check that the config file contains the klaroConsentData object
        $this->assertStringContainsString('window.klaroConsentData', $config_content, 'Config should include klaroConsentData object');

        // Check that the klaro-geo.js file contains the manager.watch method
        $watchMethodFound = strpos($geo_js_content, 'manager.watch') !== false ||
                           strpos($geo_js_content, 'manager.watch(') !== false;
        $this->assertTrue($watchMethodFound, 'klaro-geo.js should include manager.watch method');

        // Check that the klaro-geo.js file contains the handleConsentUpdate function
        $this->assertStringContainsString('update:', $geo_js_content, 'klaro-geo.js should include update function in manager.watch');
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
     * Test that the Klaro manager watcher is properly implemented.
     */
    public function test_klaro_manager_watcher() {
        // Generate the config file
        klaro_geo_generate_config_file();

        // Get the file path
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $klaro_geo_js_file = $plugin_dir . 'js/klaro-geo.js';

        // Check if the file exists
        $this->assertFileExists($klaro_geo_js_file, 'klaro-geo.js file should exist');

        // Get the file contents
        $geo_js_content = file_get_contents($klaro_geo_js_file);

        // Check for the manager watcher implementation
        $this->assertStringContainsString('manager.watch({', $geo_js_content, 'klaro-geo.js should include manager.watch setup');
        $this->assertStringContainsString('update:', $geo_js_content, 'klaro-geo.js should connect update function to the watcher');
        $this->assertStringContainsString('saveConsents', $geo_js_content, 'klaro-geo.js should check for saveConsents event');
        $this->assertStringContainsString('window.lastWatcherConsentTimestamp', $geo_js_content, 'klaro-geo.js should set timestamp to prevent duplicate processing');

        // Check for dataLayer integration
        $this->assertStringContainsString('event', $geo_js_content, 'klaro-geo.js should push to dataLayer');
        $this->assertStringContainsString('Klaro Event', $geo_js_content, 'klaro-geo.js should include event name in dataLayer push');
    }

    /**
     * With receipts disabled, klaroConsentData is still emitted (the frontend
     * needs gtmId and consentModeType regardless), but logging is off and the
     * AJAX endpoint details are withheld.
     */
    public function test_disabled_consent_receipts() {
        update_option('klaro_geo_enable_consent_receipts', false);
        update_option('klaro_geo_consent_mode_type', 'advanced');

        $data = $this->extract_consent_data(klaro_geo_generate_config_file());

        delete_option('klaro_geo_consent_mode_type');

        $this->assertFalse($data['consentReceipts']);
        $this->assertFalse($data['enableConsentLogging']);
        $this->assertSame('advanced', $data['consentModeType'], 'consentModeType must not depend on the receipts setting');
        $this->assertArrayNotHasKey('nonce', $data);
        $this->assertArrayNotHasKey('ajaxUrl', $data);

        // The receipts script is not enqueued
        unset($GLOBALS['wp_scripts']);
        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));
        do_action('wp_enqueue_scripts');
        $this->assertFalse(wp_script_is('klaro-consent-receipts-js', 'enqueued'));
    }
}
