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
            '/window\.klaroGeoClientGeo = (\{.*?\});\n/s',
            $content,
            'Client content must define window.klaroGeoClientGeo'
        );
        preg_match( '/window\.klaroGeoClientGeo = (\{.*?\});\n/s', $content, $matches );
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
        $content_us = klaro_geo_generate_config_file();

        $this->mock_geoip_record( 'DE' );
        $content_de = klaro_geo_generate_config_file();

        $this->assertSame(
            $content_us,
            $content_de,
            'Client-mode config must be byte-identical regardless of the visitor geo'
        );

        // No pre-selected config or baked geo
        $this->assertStringNotContainsString( 'var klaroConfig =', $content_us );
        $this->assertStringNotContainsString( 'Detected/Debug Country Code', $content_us );
    }

    public function test_server_mode_still_bakes_geo() {
        update_option( 'klaro_geo_geo_resolution_mode', 'server' );

        $this->mock_geoip_record( 'US', 'CA' );
        $content = klaro_geo_generate_config_file();

        $this->assertStringContainsString( 'var klaroConfig =', $content );
        $this->assertStringContainsString( "'klaroGeoDetectedCountry': \"US\"", $content );
        $this->assertStringContainsString( "'klaroGeoResolutionMode': 'server'", $content );
        $this->assertStringContainsString( "'klaroGeoGeoSource': 'server'", $content );
        $this->assertStringNotContainsString( 'window.klaroGeoClientGeo', $content );
    }

    public function test_default_mode_is_server() {
        delete_option( 'klaro_geo_geo_resolution_mode' );
        $this->mock_geoip_record( 'US' );
        $content = klaro_geo_generate_config_file();
        $this->assertStringContainsString( 'var klaroConfig =', $content, 'Server mode must be the default' );
    }

    // -------------------------------------------------------------------------
    // Client payload structure
    // -------------------------------------------------------------------------

    public function test_client_payload_structure() {
        update_option( 'klaro_geo_geo_resolution_mode', 'client' );
        update_option( 'klaro_geo_enable_consent_receipts', true );

        $content = klaro_geo_generate_config_file();
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

        $payload = $this->extract_client_payload( klaro_geo_generate_config_file() );
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
