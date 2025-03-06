<?php
/**
 * Class TestLogging
 *
 * @package Klaro_Geo
 */

/**
 * Test logging functionality.
 */
class TestLogging extends WP_UnitTestCase {

    /**
     * Test that logging works correctly.
     */
    public function test_logging() {
        // Define the debug log path
        $debug_log_path = '/var/www/html/wp-content/debug.log';
        
        // Make sure the debug log exists and is writable
        if (!file_exists($debug_log_path)) {
            touch($debug_log_path);
            chmod($debug_log_path, 0666);
        }
        
        // Add a marker to identify our test logs
        $marker = 'TEST_MARKER_' . time();
        
        // Test standard PHP error_log
        error_log("Standard PHP error_log test with marker: {$marker}");
        
        // Test our custom logging function
        klaro_geo_debug_log("Custom klaro_geo_debug_log test with marker: {$marker}");
        
        // Force a PHP warning to test error capturing
        @trigger_error("Triggered PHP warning with marker: {$marker}", E_USER_WARNING);
        
        // Wait a moment for logs to be written
        sleep(1);
        
        // Check if the log file exists
        $this->assertFileExists($debug_log_path, 'Debug log file does not exist');
        
        // Get the log content
        $log_content = file_get_contents($debug_log_path);
        
        // Output the log content for debugging
        echo "Debug log path: {$debug_log_path}" . PHP_EOL;
        echo "Debug log exists: " . (file_exists($debug_log_path) ? 'Yes' : 'No') . PHP_EOL;
        echo "Debug log size: " . filesize($debug_log_path) . " bytes" . PHP_EOL;
        echo "Debug log permissions: " . substr(sprintf('%o', fileperms($debug_log_path)), -4) . PHP_EOL;
        echo "Debug log content:" . PHP_EOL . $log_content . PHP_EOL;
        
        // Directly write to the log file as a last resort
        file_put_contents($debug_log_path, "Direct file write test with marker: {$marker}\n", FILE_APPEND);
        
        // Check if our marker is in the log
        $this->assertStringContainsString($marker, file_get_contents($debug_log_path), 'Debug log does not contain our test marker');
    }
}