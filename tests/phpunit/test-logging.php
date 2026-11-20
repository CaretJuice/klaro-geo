<?php
/**
 * klaro_geo_debug_log() writes only when WP_DEBUG is on and debug logging is
 * enabled in the plugin settings.
 *
 * @package Klaro_Geo
 */

class TestLogging extends WP_UnitTestCase {

    /**
     * Run a callback with error_log() pointed at a temporary file and return
     * what was written.
     */
    private function captured($callback) {
        $file     = tempnam(sys_get_temp_dir(), 'klaro-geo-log');
        $previous = ini_set('error_log', $file);
        $callback();
        ini_set('error_log', $previous);
        $written = file_get_contents($file);
        unlink($file);
        return $written;
    }

    public function test_logs_when_debug_logging_is_enabled() {
        // The bootstrap turns the setting on for the whole test run.
        $this->assertTrue(defined('WP_DEBUG') && WP_DEBUG);

        $written = $this->captured(function () {
            klaro_geo_debug_log('marker <b>one</b>');
            klaro_geo_debug_log(array('key' => 'marker two'));
        });

        $this->assertStringContainsString('[Klaro Geo Debug] marker one', $written);
        $this->assertStringContainsString('{"key":"marker two"}', $written);
    }

    public function test_is_silent_when_debug_logging_is_disabled() {
        remove_filter('pre_option_klaro_geo_enable_debug_logging', '__return_true');
        update_option('klaro_geo_enable_debug_logging', false);

        $written = $this->captured(function () {
            klaro_geo_debug_log('marker three');
        });

        add_filter('pre_option_klaro_geo_enable_debug_logging', '__return_true');

        $this->assertSame('', $written);
    }
}
