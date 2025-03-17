<?php
/**
 * PHPUnit bootstrap file
 */
echo "Bootstrap file loaded\n";
ob_start();

// Set up global $table_prefix early
global $table_prefix;
$table_prefix = 'wp_';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/var/www/html/wp-content/debug.log');

// Make sure debug.log exists and is writable
$debug_log = '/var/www/html/wp-content/debug.log';
if (!file_exists($debug_log)) {
    touch($debug_log);
    chmod($debug_log, 0666);
}

// Add a test log message
error_log('PHPUnit bootstrap file loaded - ' . date('Y-m-d H:i:s'));

// Set WP_TESTS_DIR if not already set
if (!getenv('WP_TESTS_DIR')) {
    putenv('WP_TESTS_DIR=/tmp/wordpress-tests-lib');
}
$_tests_dir = getenv('WP_TESTS_DIR');

// Verify and create test directories if they don't exist
if (!is_dir($_tests_dir)) {
    echo "Creating WordPress test library directory...\n";
    mkdir($_tests_dir, 0777, true);
}

if (!is_dir($_tests_dir . '/includes')) {
    echo "Setting up WordPress test includes...\n";
    exec('svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/includes/ ' . $_tests_dir . '/includes');
}

if (!is_dir($_tests_dir . '/data')) {
    echo "Setting up WordPress test data...\n";
    exec('svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/data/ ' . $_tests_dir . '/data');
}

// Load PHPUnit Polyfills
$polyfills_path = dirname(dirname(__DIR__)) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if (file_exists($polyfills_path)) {
    require_once $polyfills_path;
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    // Load main plugin file
    require dirname(dirname(__DIR__)) . '/klaro-geo.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Load WordPress test bootstrap
require $_tests_dir . '/includes/bootstrap.php';

// Disable deprecation notices after WordPress is loaded
add_filter('deprecated_function_trigger_error', '__return_false');

// Define a debug log function if it doesn't exist
if (!function_exists('klaro_geo_debug_log')) {
    function klaro_geo_debug_log($message) {
        // Do nothing in tests
    }
}
