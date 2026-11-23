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


    public static function wpSetUpBeforeClass($factory) {
        // Real table, created outside the per-test transaction (see KlaroGeoConsentReceiptStorageTest)
        klaro_geo_create_consent_receipts_table();
    }

    public static function wpTearDownAfterClass() {
        klaro_geo_drop_consent_receipts_table();
    }

    public function set_up() {
        parent::set_up();
        update_option('klaro_geo_enable_consent_receipts', true);
    }

    public function tear_down() {
        update_option('klaro_geo_enable_consent_receipts', false);
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
     * klaroConsentData reaches the page once, as part of the payload attached in
     * front of the bootstrap script. Nothing may be localized under the same name: a second copy
     * would overwrite the first.
     */
    public function test_consent_data_is_emitted_once_with_the_config() {
        // Fresh script registry so an earlier test's enqueue cannot mask this one
        unset($GLOBALS['wp_scripts']);

        $post_id = $this->factory->post->create();
        $this->go_to(get_permalink($post_id));
        do_action('wp_enqueue_scripts');

        $before = implode("\n", (array) wp_scripts()->get_data('klaro-geo-bootstrap-js', 'before'));
        $this->assertSame(1, substr_count($before, 'window.klaroConsentData = '), 'The payload script should define klaroConsentData exactly once');

        // No script carries a localized copy that would overwrite it
        foreach (wp_scripts()->registered as $handle => $script) {
            $this->assertStringNotContainsString('klaroConsentData', (string) wp_scripts()->get_data($handle, 'data'), $handle . ' must not localize klaroConsentData');
        }
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
        $this->assertStringContainsString('admin-ajax.php', $data['ajaxUrl']);
    }

    /**
     * A page for an anonymous visitor may sit in a page cache for days, so it
     * must not carry a nonce: there would be nothing valid left to send.
     */
    public function test_nonce_is_emitted_only_for_logged_in_users() {
        wp_set_current_user(0);
        $anonymous = $this->extract_consent_data(klaro_geo_generate_config_file());

        wp_set_current_user(self::factory()->user->create(array('role' => 'subscriber')));
        $logged_in = $this->extract_consent_data(klaro_geo_generate_config_file());

        $this->assertArrayNotHasKey('nonce', $anonymous);
        $this->assertSame(1, wp_verify_nonce($logged_in['nonce'], 'klaro_geo_consent_nonce'));
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
     * Store a receipt the way the public endpoint does, then read it back the
     * way the admin details endpoint does.
     */
    public function test_full_consent_receipt_flow() {
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
        ];

        $row_id = klaro_geo_store_consent_receipt($receipt_data);
        $this->assertIsInt($row_id, 'Receipt should be stored');

        $stored_receipt = klaro_geo_receipts_get_by_id($row_id);
        $this->assertNotNull($stored_receipt, 'Receipt should be retrievable by row ID');
        $this->assertEquals($receipt_data['receipt_id'], $stored_receipt->receipt_id, 'Receipt ID should match');
        $this->assertEquals($receipt_data['template_name'], $stored_receipt->template_name, 'Template name should match');
        $this->assertEquals($receipt_data['country_code'], $stored_receipt->country_code, 'Country code should match');
        $this->assertEquals($receipt_data['consent_choices'], json_decode($stored_receipt->consent_data, true), 'Consent choices should match');
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
    }
}
