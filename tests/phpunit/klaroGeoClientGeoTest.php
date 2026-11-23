<?php
/**
 * Class KlaroGeoClientGeoTest
 *
 * Tests for the cache-safe client-side geo resolution mode:
 *   - PHP side of the PHP↔JS parity fixture (tests/fixtures/geo-resolution-parity.json)
 *   - Cache safety: client-mode config output is identical regardless of the
 *     visitor's geo lookup result
 *   - Client payload structure
 *   - geoip-detect AJAX endpoint detection (layered, fail-open)
 *
 * Spec: specs/client-side-geo.spec.yaml
 *
 * @package Klaro_Geo
 */

// Test-only mock of geoip-detect's lookup function. Returns a record only when
// $GLOBALS['klaro_geo_test_geoip_record'] is set (tearDown unsets it), so other
// tests keep their "no geo detected" behavior.
if ( ! function_exists( 'geoip_detect2_get_info_from_current_ip' ) ) {
    function geoip_detect2_get_info_from_current_ip() {
        return isset( $GLOBALS['klaro_geo_test_geoip_record'] )
            ? $GLOBALS['klaro_geo_test_geoip_record']
            : null;
    }
}

class KlaroGeoClientGeoTest extends WP_UnitTestCase {

    private $fixture;

    public function setUp(): void {
        parent::setUp();

        $fixture_path  = dirname( __DIR__ ) . '/fixtures/geo-resolution-parity.json';
        $this->fixture = json_decode( file_get_contents( $fixture_path ), true );
        $this->assertNotEmpty( $this->fixture, 'Parity fixture must load' );

        // Country settings from the fixture
        $country_settings = array_merge(
            array( 'default_template' => $this->fixture['defaultTemplate'] ),
            $this->fixture['countrySettings']
        );
        update_option( 'klaro_geo_country_settings', $country_settings );
        update_option( 'klaro_geo_visible_countries', $this->fixture['visibleCountries'] );

        // Templates from the fixture (built from the shipped default template config)
        $base      = klaro_geo_get_default_templates()['default'];
        $templates = array();
        foreach ( $this->fixture['availableTemplates'] as $key ) {
            $template         = $base;
            $template['name'] = $key;
            $templates[ $key ] = $template;
        }
        update_option( 'klaro_geo_templates', $templates );

        // Singletons cache option values; reset them after changing options
        Klaro_Geo_Country_Settings::clear_instance_cache();
        Klaro_Geo_Template_Settings::clear_instance_cache();
        Klaro_Geo_Service_Settings::clear_instance_cache();
    }

    public function tearDown(): void {
        unset( $GLOBALS['klaro_geo_test_geoip_record'] );
        delete_option( 'klaro_geo_geo_resolution_mode' );
        delete_option( 'klaro_geo_client_geo_timeout_ms' );
        delete_option( 'klaro_geo_client_geo_timezone_fallback' );
        delete_option( 'klaro_geo_enable_consent_receipts' );
        delete_option( 'geoip-detect-ajax_enabled' );
        delete_transient( 'klaro_geo_geoip_ajax_probe' );
        remove_all_filters( 'pre_http_request' );
        Klaro_Geo_Country_Settings::clear_instance_cache();
        Klaro_Geo_Template_Settings::clear_instance_cache();
        Klaro_Geo_Service_Settings::clear_instance_cache();
        parent::tearDown();
    }

    private function mock_geoip_record( $country, $region = '' ) {
        $record          = new stdClass();
        $record->country = new stdClass();
        $record->country->isoCode = $country;
        $record->mostSpecificSubdivision = null;
        if ( $region !== '' ) {
            $record->mostSpecificSubdivision = new stdClass();
            $record->mostSpecificSubdivision->isoCode = $region;
        }
        $GLOBALS['klaro_geo_test_geoip_record'] = $record;
    }

    private function extract_client_payload( $content ) {
        $this->assertMatchesRegularExpression(
            '/window\.klaroGeoTemplatePayload = (\{.*?\});\n/s',
            $content,
            'Client content must define window.klaroGeoTemplatePayload'
        );
        preg_match( '/window\.klaroGeoTemplatePayload = (\{.*?\});\n/s', $content, $matches );
        $payload = json_decode( $matches[1], true );
        $this->assertIsArray( $payload, 'Client payload must be valid JSON' );
        return $payload;
    }

    // -------------------------------------------------------------------------
    // PHP side of the PHP↔JS parity fixture
    // -------------------------------------------------------------------------

    public function test_parity_fixture_php_side() {
        foreach ( $this->fixture['cases'] as $case ) {
            $result = klaro_geo_get_effective_settings( $case['location'] );

            $this->assertEquals(
                $case['expected']['template'],
                $result['template'],
                'Template mismatch for case: ' . $case['name'] . ' (location "' . $case['location'] . '")'
            );
            $this->assertEquals(
                $case['expected']['source'],
                $result['source'],
                'Source mismatch for case: ' . $case['name'] . ' (location "' . $case['location'] . '")'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Cache safety
    // -------------------------------------------------------------------------

    public function test_client_mode_output_is_identical_across_geo_results() {
        update_option( 'klaro_geo_geo_resolution_mode', 'client' );
        update_option( 'klaro_geo_enable_consent_receipts', true );

        $this->mock_geoip_record( 'US', 'CA' );
        $content_us = klaro_geo_frontend_inline_script();

        $this->mock_geoip_record( 'DE' );
        $content_de = klaro_geo_frontend_inline_script();

        $this->assertSame(
            $content_us,
            $content_de,
            'Client-mode config must be byte-identical regardless of the visitor geo'
        );

        // No pre-selected config or baked geo
        $this->assertArrayNotHasKey( 'resolved', $this->extract_client_payload( $content_us ) );
    }

    public function test_server_mode_ships_one_resolved_template() {
        update_option( 'klaro_geo_geo_resolution_mode', 'server' );

        $this->mock_geoip_record( 'US', 'CA' );
        $payload = $this->extract_client_payload( klaro_geo_frontend_inline_script() );

        $this->assertSame( 'server', $payload['mode'] );
        $this->assertSame( 'US', $payload['resolved']['country'] );
        $this->assertSame( 'CA', $payload['resolved']['region'] );
        $this->assertSame( 'server', $payload['resolved']['geoSource'] );
        $this->assertFalse( $payload['resolved']['adminOverride'] );

        // Exactly the template PHP resolved, and it is the one the browser applies
        $expected = klaro_geo_get_effective_settings( 'US-CA' );
        $this->assertSame( $expected['template'], $payload['resolved']['template'] );
        $this->assertSame( $expected['source'], $payload['resolved']['templateSource'] );
        $this->assertSame( array( $expected['template'] ), array_keys( $payload['templates'] ) );
        $this->assertArrayHasKey( 'services', $payload['templates'][ $expected['template'] ]['config'] );

        // No client-mode lookup data
        $this->assertArrayNotHasKey( 'geoAjax', $payload );
    }

    public function test_default_mode_is_server() {
        delete_option( 'klaro_geo_geo_resolution_mode' );
        $this->mock_geoip_record( 'US' );
        $payload = $this->extract_client_payload( klaro_geo_frontend_inline_script() );
        $this->assertSame( 'server', $payload['mode'], 'Server mode must be the default' );
    }

    /**
     * The generated script is data only. Behaviour (GPC, dataLayer events,
     * starting Klaro) lives in js/klaro-geo-bootstrap.js for both modes.
     */
    public function test_generated_content_contains_no_logic() {
        foreach ( array( 'server', 'client' ) as $mode ) {
            update_option( 'klaro_geo_geo_resolution_mode', $mode );
            $content = klaro_geo_frontend_inline_script();

            $this->assertStringNotContainsString( 'klaroConfigLoaded', $content, "$mode mode" );
            $this->assertStringNotContainsString( 'globalPrivacyControl', $content, "$mode mode" );
            $this->assertStringNotContainsString( 'var klaroConfig', $content, "$mode mode" );
            $this->assertSame( 1, substr_count( $content, 'window.klaroGeoTemplatePayload = ' ), "$mode mode" );
            $this->assertSame( 1, substr_count( $content, 'window.klaroConsentData = ' ), "$mode mode" );
        }
    }

    /**
     * A stored value containing a closing script tag must not be able to end
     * the inline script it is printed in.
     */
    public function test_payload_cannot_close_the_inline_script() {
        $templates = get_option( 'klaro_geo_templates' );
        $templates['default']['config']['translations']['zz']['consentModal']['title'] = 'x</script><script>alert(1)</script>';
        update_option( 'klaro_geo_templates', $templates );
        Klaro_Geo_Template_Settings::clear_instance_cache();

        $content = klaro_geo_frontend_inline_script();

        $this->assertStringNotContainsString( '</script', $content );
    }

    // -------------------------------------------------------------------------
    // Client payload structure
    // -------------------------------------------------------------------------

    public function test_client_payload_structure() {
        update_option( 'klaro_geo_geo_resolution_mode', 'client' );
        update_option( 'klaro_geo_enable_consent_receipts', true );

        $content = klaro_geo_frontend_inline_script();
        $payload = $this->extract_client_payload( $content );

        $this->assertEquals( 'client', $payload['mode'] );
        $this->assertEquals( 'geoip_detect2_get_info_from_current_ip', $payload['geoAjax']['action'] );
        $this->assertEquals( 2500, $payload['timeoutMs'], 'Default timeout' );
        $this->assertTrue( $payload['timezoneFallback'], 'Timezone fallback defaults on' );

        // Rules mirror the fixture
        $this->assertEquals( 'default', $payload['rules']['defaultTemplate'] );
        $this->assertEquals( $this->fixture['visibleCountries'], $payload['rules']['visibleCountries'] );
        $this->assertArrayHasKey( 'US', $payload['rules']['countries'] );
        $this->assertEquals( 'strict', $payload['rules']['countries']['US']['regions']['CA'] );

        // All referenced-and-existing templates ship with full configs
        foreach ( array( 'default', 'strict', 'relaxed' ) as $key ) {
            $this->assertArrayHasKey( $key, $payload['templates'], "Template '$key' must ship" );
            $this->assertArrayHasKey( 'config', $payload['templates'][ $key ] );
            $this->assertArrayHasKey( 'services', $payload['templates'][ $key ]['config'] );
            $this->assertArrayHasKey( 'consentDefaults', $payload['templates'][ $key ] );
        }
        // The missing template referenced by BR must NOT ship (resolver falls back)
        $this->assertArrayNotHasKey( 'no-such-template', $payload['templates'] );

        // klaroConsentData ships with EMPTY geo/template fields (resolver fills them)
        $this->assertStringContainsString( 'window.klaroConsentData', $content );
        $this->assertMatchesRegularExpression(
            '/window\.klaroConsentData = (\{.*?\});\n/s',
            $content
        );
        preg_match( '/window\.klaroConsentData = (\{.*?\});\n/s', $content, $matches );
        $consent_data = json_decode( $matches[1], true );
        $this->assertSame( '', $consent_data['detectedCountry'] );
        $this->assertSame( '', $consent_data['detectedRegion'] );
        $this->assertSame( '', $consent_data['templateName'] );
    }

    public function test_client_payload_respects_timeout_setting() {
        update_option( 'klaro_geo_geo_resolution_mode', 'client' );
        update_option( 'klaro_geo_client_geo_timeout_ms', 5000 );
        update_option( 'klaro_geo_client_geo_timezone_fallback', 0 );

        $payload = $this->extract_client_payload( klaro_geo_frontend_inline_script() );
        $this->assertEquals( 5000, $payload['timeoutMs'] );
        $this->assertFalse( $payload['timezoneFallback'] );
    }

    // -------------------------------------------------------------------------
    // Admin debug override forces server-side resolution
    // -------------------------------------------------------------------------

    public function test_client_mode_inactive_for_admin_override() {
        update_option( 'klaro_geo_geo_resolution_mode', 'client' );
        $this->assertTrue( klaro_geo_is_client_geo_active( false ) );
        $this->assertFalse( klaro_geo_is_client_geo_active( true ), 'Admin override must force server-side resolution' );

        update_option( 'klaro_geo_geo_resolution_mode', 'server' );
        $this->assertFalse( klaro_geo_is_client_geo_active( false ) );
    }

    // -------------------------------------------------------------------------
    // geoip-detect AJAX endpoint detection (admin warning; layered, fail-open)
    // -------------------------------------------------------------------------

    public function test_ajax_status_option_disabled_is_conclusive() {
        update_option( 'geoip-detect-ajax_enabled', '0' );
        $this->assertEquals( 'disabled', klaro_geo_geoip_ajax_endpoint_status() );
    }

    public function test_ajax_status_option_enabled_is_conclusive() {
        update_option( 'geoip-detect-ajax_enabled', '1' );
        $this->assertEquals( 'ok', klaro_geo_geoip_ajax_endpoint_status() );
    }

    public function test_ajax_status_probe_ok_when_endpoint_answers_with_record() {
        delete_option( 'geoip-detect-ajax_enabled' );
        delete_transient( 'klaro_geo_geoip_ajax_probe' );

        add_filter(
            'pre_http_request',
            function () {
                return array(
                    'headers'  => array(),
                    'response' => array(
                        'code'    => 200,
                        'message' => 'OK',
                    ),
                    'body'     => wp_json_encode(
                        array(
                            'is_empty' => false,
                            'country'  => array( 'iso_code' => 'CA' ),
                        )
                    ),
                    'cookies'  => array(),
                );
            }
        );

        $this->assertEquals( 'ok', klaro_geo_geoip_ajax_endpoint_status() );
    }

    public function test_ajax_status_probe_disabled_on_error_response() {
        delete_option( 'geoip-detect-ajax_enabled' );
        delete_transient( 'klaro_geo_geoip_ajax_probe' );

        add_filter(
            'pre_http_request',
            function () {
                return array(
                    'headers'  => array(),
                    'response' => array(
                        'code'    => 400,
                        'message' => 'Bad Request',
                    ),
                    'body'     => '0',
                    'cookies'  => array(),
                );
            }
        );

        $this->assertEquals( 'disabled', klaro_geo_geoip_ajax_endpoint_status() );
    }

    public function test_ajax_status_fails_open_on_transport_failure() {
        delete_option( 'geoip-detect-ajax_enabled' );
        delete_transient( 'klaro_geo_geoip_ajax_probe' );

        add_filter(
            'pre_http_request',
            function () {
                return new WP_Error( 'http_request_failed', 'Loopback blocked' );
            }
        );

        $this->assertEquals(
            'unknown',
            klaro_geo_geoip_ajax_endpoint_status(),
            'Inconclusive probes must fail open (no warning)'
        );
    }

    public function test_ajax_status_probe_result_is_cached() {
        delete_option( 'geoip-detect-ajax_enabled' );
        delete_transient( 'klaro_geo_geoip_ajax_probe' );

        $calls = 0;
        add_filter(
            'pre_http_request',
            function () use ( &$calls ) {
                $calls++;
                return array(
                    'headers'  => array(),
                    'response' => array(
                        'code'    => 400,
                        'message' => 'Bad Request',
                    ),
                    'body'     => '0',
                    'cookies'  => array(),
                );
            }
        );

        klaro_geo_geoip_ajax_endpoint_status();
        klaro_geo_geoip_ajax_endpoint_status();
        $this->assertEquals( 1, $calls, 'Probe result must be transient-cached' );
    }
}
