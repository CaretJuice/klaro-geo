<?php
/**
 * Bootstrap file for tests with mock database
 * 
 * This file is loaded before the tests run and sets up the mock database.
 */

// First, load the original bootstrap file
require_once dirname(__FILE__) . '/bootstrap.php';

// Then, load our mock database implementation
require_once dirname(__FILE__) . '/mock-db.php';

// Configure error_log to write to debug.log
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/wp-content/debug.log');

// Helper function to log to debug.log
function bootstrap_log($message) {
    // Write to debug.log
    error_log('[Mock Bootstrap] ' . $message);
}

// Log that we're using the mock database
bootstrap_log("Mock database bootstrap loaded");

// We don't need rename_function anymore with our class-based approach

// Create a simple test to verify the mock database is working
function test_mock_db() {
    bootstrap_log("Testing mock database implementation");

    // Set up the mock database
    KlaroGeoMockDB::enable();

    // Initialize the mock table
    $result = KlaroGeoMockDB::init_table('wp_klaro_geo_consent_receipts');

    if ($result) {
        bootstrap_log("Mock table created successfully");
    } else {
        bootstrap_log("Failed to create mock table");
    }

    // Create a test receipt
    $receipt_data = array(
        'receipt_id' => 'test_receipt_' . time(),
        'timestamp' => time(),
        'consent_choices' => array(
            'google-analytics' => true,
            'facebook-pixel' => false
        ),
        'template_name' => 'default',
        'template_source' => 'fallback',
        'country_code' => 'US',
        'region_code' => 'CA'
    );

    $result = KlaroGeoMockDB::store_consent_receipt($receipt_data);

    if ($result && $result['success']) {
        bootstrap_log("Mock receipt stored successfully with ID: " . $result['data']['receipt_id']);
    } else {
        bootstrap_log("Failed to store mock receipt");
    }

    // Restore the original database functions
    KlaroGeoMockDB::disable();

    bootstrap_log("Mock database test completed");
}

// Run the test
test_mock_db();