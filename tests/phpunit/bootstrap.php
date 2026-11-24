<?php
/**
 * PHPUnit bootstrap file.
 *
 * Run the suite with bin/phpunit.sh. WordPress, PHPUnit and WordPress's test
 * library are in the Docker image; the library's path is in WP_TESTS_DIR and its configuration is
 * wp-tests-config.php beside this file.
 */

// Printed before output buffering starts, on purpose: from here on
// headers_sent() is true, as it is when WordPress calls an admin page's render
// function. The Templates page saves from its render function and redirects
// (and exits) only if headers have not been sent; without this line that exit
// ends the whole PHPUnit run, silently and with exit status 0.
echo "Klaro Geo PHPUnit bootstrap\n";
ob_start();

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir || !getenv('WP_PHPUNIT__TESTS_CONFIG')) {
    fwrite(STDERR, "The WordPress test library is not configured. Run the tests with bin/phpunit.sh.\n");
    exit(1);
}

// PHP errors and the plugin's debug log of this run go to one file, written
// as the run goes, so it is there whether the tests pass or fail. It holds the
// last run only.
$debug_log = dirname(__DIR__, 2) . '/docker/logs/debug_test.log';
file_put_contents($debug_log, '=== Klaro Geo test log, ' . gmdate('Y-m-d H:i:s') . " UTC ===\n");

ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', $debug_log);

// The polyfills let tests written for PHPUnit 9 run on the older PHPUnit an
// old PHP needs. WordPress's test library loads them itself from 5.9 on.
require_once getenv('WP_TESTS_POLYFILLS_DIR') . '/phpunitpolyfills-autoload.php';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    $plugin_dir = dirname(dirname(__DIR__));

    // Load main plugin file
    require $plugin_dir . '/klaro-geo.php';

    // The plugin loads its admin pages only when is_admin(). Tests call their
    // functions directly, so load them here instead of branching on the test
    // environment in the plugin.
    foreach (array('countries', 'services', 'settings', 'templates', 'scripts') as $page) {
        require_once $plugin_dir . '/includes/admin/klaro-geo-admin-' . $page . '.php';
    }

    // Send the plugin's debug log to debug.log during tests (WP_DEBUG is on in
    // the test config; this is the plugin setting that goes with it).
    add_filter('pre_option_klaro_geo_enable_debug_logging', '__return_true');
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Load WordPress test bootstrap
require $_tests_dir . '/includes/bootstrap.php';

// Disable deprecation notices after WordPress is loaded
add_filter('deprecated_function_trigger_error', '__return_false');

