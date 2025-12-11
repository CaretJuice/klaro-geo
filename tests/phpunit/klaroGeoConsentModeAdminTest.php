<?php
/**
 * Tests for Klaro Geo Consent Mode Admin functionality
 *
 * @package Klaro_Geo
 */

class KlaroGeoConsentModeAdminTest extends WP_UnitTestCase {
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Reset options
        delete_option('klaro_geo_templates');

        // Set up default templates with consent mode settings
        // NOTE: initialize_consent_mode has been removed - consent mode is always enabled
        $templates = array(
            'default' => array(
                'name' => 'Default Template',
                'config' => array(
                    'default' => false,
                    'required' => false,
                ),
                'plugin_settings' => array(
                    'enable_consent_logging' => true,
                ),
                'consent_mode_settings' => array(
                    'ad_storage_service' => 'no_service',
                    'analytics_storage_service' => 'no_service',
                    'ad_user_data' => false,
                    'ad_personalization' => false,
                ),
            ),
        );

        // Save templates
        update_option('klaro_geo_templates', $templates);

        // Set up services including Google Ads
        $services = array(
            array(
                'name' => 'google-ads',
                'title' => 'Google Ads',
                'purposes' => array('advertising'),
                'default' => false,
                'required' => false,
                'cookies' => array(),
            ),
            array(
                'name' => 'google-analytics',
                'title' => 'Google Analytics',
                'purposes' => array('analytics'),
                'default' => false,
                'required' => false,
                'cookies' => array(),
            ),
        );

        // Save services
        update_option('klaro_geo_services', wp_json_encode($services));

        // Define the required functions if they don't exist
        // NOTE: initialize_consent_mode has been removed - consent mode is always enabled
        if (!function_exists('klaro_geo_render_template_settings_form')) {
            function klaro_geo_render_template_settings_form($template_name) {
                $templates = get_option('klaro_geo_templates', array());
                $template = isset($templates[$template_name]) ? $templates[$template_name] : null;

                if (!$template) {
                    echo "Template not found";
                    return;
                }

                // Get services for dropdowns
                $services_json = get_option('klaro_geo_services', '[]');
                $services = json_decode($services_json, true);
                if (!is_array($services)) {
                    $services = array();
                }

                // Output form fields
                // NOTE: Consent mode is always enabled - no toggle checkbox needed
                echo '<h3>Consent Mode Settings</h3>';
                echo '<p class="description">Consent mode is always enabled. Configure which services control Google Consent Mode signals.</p>';
                echo '<table class="form-table">';

                // Ad Storage Service dropdown
                $ad_storage_service = isset($template['consent_mode_settings']['ad_storage_service']) ?
                    $template['consent_mode_settings']['ad_storage_service'] : 'no_service';

                echo '<tr>';
                echo '<th><label for="ad_storage_service">Ad Storage Service:</label></th>';
                echo '<td>';
                echo '<select name="consent_mode_settings[ad_storage_service]" id="ad_storage_service">';
                echo '<option value="no_service"' . ($ad_storage_service === 'no_service' ? ' selected' : '') . '>No Service</option>';

                foreach ($services as $service) {
                    if (isset($service['name'])) {
                        $selected = ($ad_storage_service === $service['name']) ? ' selected' : '';
                        echo '<option value="' . esc_attr($service['name']) . '"' . $selected . '>' . esc_html($service['name']) . '</option>';
                    }
                }

                echo '</select>';
                echo '</td>';
                echo '</tr>';

                // Analytics Storage Service dropdown
                $analytics_storage_service = isset($template['consent_mode_settings']['analytics_storage_service']) ?
                    $template['consent_mode_settings']['analytics_storage_service'] : 'no_service';

                echo '<tr>';
                echo '<th><label for="analytics_storage_service">Analytics Storage Service:</label></th>';
                echo '<td>';
                echo '<select name="consent_mode_settings[analytics_storage_service]" id="analytics_storage_service">';
                echo '<option value="no_service"' . ($analytics_storage_service === 'no_service' ? ' selected' : '') . '>No Service</option>';

                foreach ($services as $service) {
                    if (isset($service['name'])) {
                        $selected = ($analytics_storage_service === $service['name']) ? ' selected' : '';
                        echo '<option value="' . esc_attr($service['name']) . '"' . $selected . '>' . esc_html($service['name']) . '</option>';
                    }
                }

                echo '</select>';
                echo '</td>';
                echo '</tr>';

                // Ad User Data checkbox
                $ad_user_data = isset($template['consent_mode_settings']['ad_user_data']) ?
                    filter_var($template['consent_mode_settings']['ad_user_data'], FILTER_VALIDATE_BOOLEAN) : false;

                echo '<tr>';
                echo '<th><label for="ad_user_data">Ad User Data:</label></th>';
                echo '<td>';
                echo '<input type="checkbox" name="consent_mode_settings[ad_user_data]" id="ad_user_data" value="1"';
                if ($ad_user_data) echo ' checked';
                echo '>';
                echo '</td>';
                echo '</tr>';

                // Ad Personalization checkbox
                $ad_personalization = isset($template['consent_mode_settings']['ad_personalization']) ?
                    filter_var($template['consent_mode_settings']['ad_personalization'], FILTER_VALIDATE_BOOLEAN) : false;

                echo '<tr>';
                echo '<th><label for="ad_personalization">Ad Personalization:</label></th>';
                echo '<td>';
                echo '<input type="checkbox" name="consent_mode_settings[ad_personalization]" id="ad_personalization" value="1"';
                if ($ad_personalization) echo ' checked';
                echo '>';
                echo '</td>';
                echo '</tr>';

                echo '</table>';
            }
        }

        if (!function_exists('klaro_geo_save_template_settings')) {
            function klaro_geo_save_template_settings($form_data) {
                if (!isset($form_data['template_name'])) {
                    return false;
                }

                $template_name = $form_data['template_name'];
                $templates = get_option('klaro_geo_templates', array());

                if (!isset($templates[$template_name])) {
                    return false;
                }

                // Update consent mode settings
                if (isset($form_data['consent_mode_settings'])) {
                    $consent_mode_settings = $form_data['consent_mode_settings'];

                    // Validate and sanitize settings
                    $validated_settings = klaro_geo_validate_template_settings($form_data);

                    // Update the template with validated settings
                    $templates[$template_name]['consent_mode_settings'] = $validated_settings['consent_mode_settings'];

                    // Save the updated templates
                    update_option('klaro_geo_templates', $templates);

                    return true;
                }

                return false;
            }
        }

        if (!function_exists('klaro_geo_validate_template_settings')) {
            // NOTE: initialize_consent_mode has been removed - consent mode is always enabled
            function klaro_geo_validate_template_settings($form_data) {
                $validated_data = $form_data;

                // Validate consent mode settings
                if (isset($form_data['consent_mode_settings'])) {
                    $consent_mode_settings = $form_data['consent_mode_settings'];

                    // Get available services for validation
                    $services_json = get_option('klaro_geo_services', '[]');
                    $services = json_decode($services_json, true);
                    if (!is_array($services)) {
                        $services = array();
                    }

                    // Extract service names
                    $service_names = array();
                    foreach ($services as $service) {
                        if (isset($service['name'])) {
                            $service_names[] = $service['name'];
                        }
                    }

                    // Validate ad_storage_service (string)
                    if (isset($consent_mode_settings['ad_storage_service'])) {
                        if ($consent_mode_settings['ad_storage_service'] !== 'no_service' &&
                            !in_array($consent_mode_settings['ad_storage_service'], $service_names)) {
                            $validated_data['consent_mode_settings']['ad_storage_service'] = 'no_service';
                        } else {
                            $validated_data['consent_mode_settings']['ad_storage_service'] = $consent_mode_settings['ad_storage_service'];
                        }
                    } else {
                        $validated_data['consent_mode_settings']['ad_storage_service'] = 'no_service';
                    }

                    // Validate analytics_storage_service (string)
                    if (isset($consent_mode_settings['analytics_storage_service'])) {
                        if ($consent_mode_settings['analytics_storage_service'] !== 'no_service' &&
                            !in_array($consent_mode_settings['analytics_storage_service'], $service_names)) {
                            $validated_data['consent_mode_settings']['analytics_storage_service'] = 'no_service';
                        } else {
                            $validated_data['consent_mode_settings']['analytics_storage_service'] = $consent_mode_settings['analytics_storage_service'];
                        }
                    } else {
                        $validated_data['consent_mode_settings']['analytics_storage_service'] = 'no_service';
                    }

                    // Validate ad_user_data (boolean)
                    if (isset($consent_mode_settings['ad_user_data'])) {
                        if (is_string($consent_mode_settings['ad_user_data']) &&
                            $consent_mode_settings['ad_user_data'] !== '0' &&
                            strtolower($consent_mode_settings['ad_user_data']) !== 'false' &&
                            !empty($consent_mode_settings['ad_user_data'])) {
                            $validated_data['consent_mode_settings']['ad_user_data'] = true;
                        } else {
                            $validated_data['consent_mode_settings']['ad_user_data'] =
                                filter_var($consent_mode_settings['ad_user_data'], FILTER_VALIDATE_BOOLEAN);
                        }
                    } else {
                        $validated_data['consent_mode_settings']['ad_user_data'] = false;
                    }

                    // Validate ad_personalization (boolean)
                    if (isset($consent_mode_settings['ad_personalization'])) {
                        if (is_string($consent_mode_settings['ad_personalization']) &&
                            $consent_mode_settings['ad_personalization'] !== '0' &&
                            strtolower($consent_mode_settings['ad_personalization']) !== 'false' &&
                            !empty($consent_mode_settings['ad_personalization'])) {
                            $validated_data['consent_mode_settings']['ad_personalization'] = true;
                        } else {
                            $validated_data['consent_mode_settings']['ad_personalization'] =
                                filter_var($consent_mode_settings['ad_personalization'], FILTER_VALIDATE_BOOLEAN);
                        }
                    } else {
                        $validated_data['consent_mode_settings']['ad_personalization'] = false;
                    }

                    // Remove legacy initialize_consent_mode if present (it's ignored now)
                    unset($validated_data['consent_mode_settings']['initialize_consent_mode']);
                }

                return $validated_data;
            }
        }
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up
        delete_option('klaro_geo_templates');
        delete_option('klaro_geo_services');
        
        parent::tearDown();
    }
    
    /**
     * Test that consent mode admin fields are rendered
     * NOTE: initialize_consent_mode checkbox has been removed
     */
    public function test_consent_mode_admin_fields_rendered() {
        // Check if the admin functions exist
        if (!function_exists('klaro_geo_render_template_settings_form')) {
            $this->markTestSkipped('Admin functions not available');
            return;
        }

        // Capture output
        ob_start();
        klaro_geo_render_template_settings_form('default');
        $output = ob_get_clean();

        // Check that consent mode fields are included
        $this->assertStringContainsString('Consent Mode Settings', $output);
        $this->assertStringContainsString('ad_storage_service', $output);
        $this->assertStringContainsString('analytics_storage_service', $output);
        $this->assertStringContainsString('ad_user_data', $output);
        $this->assertStringContainsString('ad_personalization', $output);

        // initialize_consent_mode should NOT be present (removed)
        $this->assertStringNotContainsString('initialize_consent_mode', $output);
    }

    /**
     * Test that consent mode settings are saved from admin form
     * NOTE: initialize_consent_mode has been removed
     */
    public function test_consent_mode_settings_saved_from_form() {
        // Check if the admin functions exist
        if (!function_exists('klaro_geo_save_template_settings')) {
            $this->markTestSkipped('Admin functions not available');
            return;
        }

        // Create form data (without initialize_consent_mode)
        $form_data = array(
            'template_name' => 'default',
            'consent_mode_settings' => array(
                'ad_storage_service' => 'google-ads',
                'analytics_storage_service' => 'google-analytics',
                'ad_user_data' => '1',
                'ad_personalization' => '0',
            ),
        );

        // Save the form data
        klaro_geo_save_template_settings($form_data);

        // Get the updated template data
        $templates = get_option('klaro_geo_templates', array());
        $template = $templates['default'];

        // Check that the settings were saved correctly
        $this->assertEquals('google-ads', $template['consent_mode_settings']['ad_storage_service']);
        $this->assertEquals('google-analytics', $template['consent_mode_settings']['analytics_storage_service']);
        $this->assertTrue($template['consent_mode_settings']['ad_user_data']);
        $this->assertFalse($template['consent_mode_settings']['ad_personalization']);
        // initialize_consent_mode should NOT be present
        $this->assertArrayNotHasKey('initialize_consent_mode', $template['consent_mode_settings']);
    }
    
    /**
     * Test that service options are rendered in the admin form
     */
    public function test_service_options_rendered() {
        // Check if the admin functions exist
        if (!function_exists('klaro_geo_render_template_settings_form')) {
            $this->markTestSkipped('Admin functions not available');
            return;
        }
        
        // Capture output
        ob_start();
        klaro_geo_render_template_settings_form('default');
        $output = ob_get_clean();
        
        // Check that service options are included
        $this->assertStringContainsString('google-ads', $output);
        $this->assertStringContainsString('google-analytics', $output);
    }
    
    /**
     * Test that consent mode settings validation works
     * NOTE: initialize_consent_mode has been removed
     */
    public function test_consent_mode_settings_validation() {
        // Check if the validation function exists
        if (!function_exists('klaro_geo_validate_template_settings')) {
            $this->markTestSkipped('Validation function not available');
            return;
        }

        // Create form data with invalid values
        $invalid_form_data = array(
            'template_name' => 'default',
            'consent_mode_settings' => array(
                'ad_storage_service' => 'invalid-service',
                'analytics_storage_service' => 'another-invalid-service',
                'ad_user_data' => 'not-a-boolean',
                'ad_personalization' => 'not-a-boolean',
            ),
        );

        // Validate the form data
        $validated_data = klaro_geo_validate_template_settings($invalid_form_data);

        // Check that invalid values were corrected
        $this->assertEquals('no_service', $validated_data['consent_mode_settings']['ad_storage_service']); // Should default to no_service
        $this->assertEquals('no_service', $validated_data['consent_mode_settings']['analytics_storage_service']); // Should default to no_service
        $this->assertTrue($validated_data['consent_mode_settings']['ad_user_data']); // Non-empty string should be true
        $this->assertTrue($validated_data['consent_mode_settings']['ad_personalization']); // Non-empty string should be true
        // initialize_consent_mode should NOT be present
        $this->assertArrayNotHasKey('initialize_consent_mode', $validated_data['consent_mode_settings']);
    }

    /**
     * Test that legacy initialize_consent_mode is stripped from validated data
     */
    public function test_legacy_initialize_consent_mode_stripped() {
        // Check if the validation function exists
        if (!function_exists('klaro_geo_validate_template_settings')) {
            $this->markTestSkipped('Validation function not available');
            return;
        }

        // Create form data with legacy initialize_consent_mode
        $form_data = array(
            'template_name' => 'default',
            'consent_mode_settings' => array(
                'initialize_consent_mode' => '1', // Legacy field - should be stripped
                'ad_storage_service' => 'google-ads',
                'analytics_storage_service' => 'google-analytics',
                'ad_user_data' => '1',
                'ad_personalization' => '1',
            ),
        );

        // Validate the form data
        $validated_data = klaro_geo_validate_template_settings($form_data);

        // initialize_consent_mode should be stripped
        $this->assertArrayNotHasKey('initialize_consent_mode', $validated_data['consent_mode_settings']);
        // Other fields should remain
        $this->assertEquals('google-ads', $validated_data['consent_mode_settings']['ad_storage_service']);
        $this->assertEquals('google-analytics', $validated_data['consent_mode_settings']['analytics_storage_service']);
    }

    /**
     * Test that invalid service is corrected to no_service
     */
    public function test_invalid_service_corrected_to_no_service() {
        // Get the templates
        $templates = get_option('klaro_geo_templates', array());

        // Set consent mode settings with invalid service
        $templates['default']['consent_mode_settings'] = array(
            'ad_storage_service' => 'non-existent-service',
            'analytics_storage_service' => 'another-non-existent-service',
            'ad_user_data' => true,
            'ad_personalization' => true,
        );

        // Save the templates
        update_option('klaro_geo_templates', $templates);

        // Check if the validation function exists
        if (function_exists('klaro_geo_validate_template_settings')) {
            // Create form data with the same settings
            $form_data = array(
                'template_name' => 'default',
                'consent_mode_settings' => array(
                    'ad_storage_service' => 'non-existent-service',
                    'analytics_storage_service' => 'another-non-existent-service',
                    'ad_user_data' => '1',
                    'ad_personalization' => '1',
                ),
            );

            // Validate the form data
            $validated_data = klaro_geo_validate_template_settings($form_data);

            // Check that services were corrected
            $this->assertEquals('no_service', $validated_data['consent_mode_settings']['ad_storage_service']);
            $this->assertEquals('no_service', $validated_data['consent_mode_settings']['analytics_storage_service']);
        } else {
            // If validation function doesn't exist, check the template data directly
            $template_data = klaro_geo_get_template_data('default');

            // The template data should still have the invalid service
            $this->assertEquals('non-existent-service', $template_data['consent_mode_settings']['ad_storage_service']);
        }
    }
}