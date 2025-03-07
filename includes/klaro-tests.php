<?php
/**
 * Klaro Geo Tests
 * 
 * This file contains functionality for running tests.
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add tests page to admin menu
 */
function klaro_geo_add_tests_page() {
    add_submenu_page(
        'klaro-geo',
        'Klaro Geo Tests',
        'Tests',
        'manage_options',
        'klaro-geo-tests',
        'klaro_geo_tests_page_content'
    );
}
add_action('admin_menu', 'klaro_geo_add_tests_page');

/**
 * Tests page content
 */
function klaro_geo_tests_page_content() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Enqueue test scripts
    wp_enqueue_script(
        'klaro-geo-template-translations-test',
        plugins_url('../js/tests/test-template-translations.js', __FILE__),
        array('jquery'),
        time(), // Use time for cache busting during development
        true
    );
    
    wp_enqueue_script(
        'klaro-geo-service-translations-test',
        plugins_url('../js/tests/test-service-translations.js', __FILE__),
        array('jquery'),
        time(), // Use time for cache busting during development
        true
    );
    
    // Include PHP test files
    require_once plugin_dir_path(__FILE__) . '../tests/test-template-translations.php';
    require_once plugin_dir_path(__FILE__) . '../tests/test-service-translations.php';
    
    // Get templates for JavaScript tests
    $templates = get_option('klaro_geo_templates', array());
    
    // Pass templates to JavaScript
    wp_localize_script(
        'klaro-geo-template-translations-test',
        'klaroTemplates',
        $templates
    );
    
    // Get services for JavaScript tests
    $services_json = get_option('klaro_geo_services', '[]');
    $services = json_decode($services_json, true);
    
    // Pass services to JavaScript
    wp_localize_script(
        'klaro-geo-service-translations-test',
        'klaroGeo',
        array(
            'services' => $services
        )
    );
    
    // Output the page content
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <h2>Klaro Geo Tests</h2>
        <p>This page allows you to run tests for the Klaro Geo plugin.</p>
        
        <div class="card">
            <h2>PHP Tests</h2>
            <p>Run PHP tests to verify server-side functionality.</p>
            <a href="<?php echo esc_url(add_query_arg('klaro_geo_run_tests', '1')); ?>" class="button button-primary">Run PHP Tests</a>
        </div>
        
        <div class="card">
            <h2>JavaScript Tests</h2>
            <p>JavaScript tests are automatically run when this page loads. Check the browser console for results.</p>
            <button id="run-js-tests" class="button button-primary">Run JavaScript Tests Again</button>
        </div>
        
        <div class="card">
            <h2>Manual Tests</h2>
            <p>Use these links to manually test specific functionality:</p>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=klaro-geo-templates')); ?>">Test Templates Page</a></li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=klaro-geo-services')); ?>">Test Services Page</a></li>
            </ul>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('#run-js-tests').click(function() {
                    // Re-run JavaScript tests
                    if (typeof runKlaroGeoTests === 'function') {
                        runKlaroGeoTests();
                    } else {
                        // If the global function isn't available, reload the scripts
                        $.getScript('<?php echo esc_url(plugins_url('../js/tests/test-template-translations.js', __FILE__)); ?>');
                        $.getScript('<?php echo esc_url(plugins_url('../js/tests/test-service-translations.js', __FILE__)); ?>');
                    }
                });
            });
        </script>
    </div>
    <?php
}

/**
 * Add a global function to run all JavaScript tests
 */
function klaro_geo_add_test_runner() {
    ?>
    <script>
        function runKlaroGeoTests() {
            console.group('Klaro Geo Tests');
            console.log('Running all tests...');
            
            // Dispatch events to trigger tests
            document.dispatchEvent(new Event('klaro-geo-run-template-tests'));
            document.dispatchEvent(new Event('klaro-geo-run-service-tests'));
            
            console.groupEnd();
        }
    </script>
    <?php
}
add_action('admin_footer', 'klaro_geo_add_test_runner');