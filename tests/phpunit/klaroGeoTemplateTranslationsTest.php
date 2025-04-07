<?php
/**
 * Test Template Translations
 * 
 * This file contains tests for the template translations functionality.
 */

// Exit if accessed directly
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Test Template Translations
 */
class Klaro_Geo_Template_Translations_Test {
    /**
     * Run all tests
     */
    public static function run_tests() {
        self::test_save_template_translations();
        self::test_load_template_translations();
        self::test_template_translations_consistency();
        self::test_json_to_form_conversion();
        self::test_form_to_json_conversion();
    }

    /**
     * Test saving template translations
     */
    private static function test_save_template_translations() {
        // Get current templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Make sure we have a default template
        if (!isset($templates['default'])) {
            self::log_error('Default template not found');
            return;
        }
        
        // Create comprehensive test translations with all default keys
        $test_translations = array(
            'zz' => array(
                'privacyPolicyUrl' => '/privacy-policy-zz/',
                'consentModal' => array(
                    'title' => 'Test Title ZZ',
                    'description' => 'Test Description ZZ'
                ),
                'consentNotice' => array(
                    'title' => 'Notice Title ZZ',
                    'changeDescription' => 'Change Description ZZ',
                    'description' => 'Notice Description ZZ',
                    'learnMore' => 'Learn More ZZ'
                ),
                'acceptAll' => 'Accept All ZZ',
                'acceptSelected' => 'Accept Selected ZZ',
                'decline' => 'Decline ZZ',
                'close' => 'Close ZZ',
                'purposes' => array(
                    'functional' => array(
                        'title' => 'Functional ZZ',
                        'description' => 'Functional Description ZZ'
                    ),
                    'analytics' => array(
                        'title' => 'Analytics ZZ',
                        'description' => 'Analytics Description ZZ'
                    ),
                    'advertising' => array(
                        'title' => 'Advertising ZZ',
                        'description' => 'Advertising Description ZZ'
                    )
                ),
                'purposeItem' => array(
                    'service' => 'service ZZ',
                    'services' => 'services ZZ'
                ),
                'service' => array(
                    'disableAll' => array(
                        'title' => 'Disable All Title ZZ',
                        'description' => 'Disable All Description ZZ'
                    ),
                    'optOut' => array(
                        'title' => 'Opt Out Title ZZ',
                        'description' => 'Opt Out Description ZZ'
                    ),
                    'required' => array(
                        'title' => 'Required Title ZZ',
                        'description' => 'Required Description ZZ'
                    ),
                    'purpose' => 'purpose ZZ',
                    'purposes' => 'purposes ZZ',
                    'contextualConsent' => array(
                        'description' => 'Contextual Consent Description ZZ',
                        'acceptOnce' => 'Accept Once ZZ',
                        'acceptAlways' => 'Accept Always ZZ'
                    )
                ),
                'ok' => 'OK ZZ',
                'save' => 'Save ZZ',
                'poweredBy' => 'Powered By ZZ'
            ),
            'en' => array(
                'privacyPolicyUrl' => '/privacy-policy-en/',
                'consentModal' => array(
                    'title' => 'Test Title EN',
                    'description' => 'Test Description EN'
                ),
                'consentNotice' => array(
                    'title' => 'Notice Title EN',
                    'changeDescription' => 'Change Description EN',
                    'description' => 'Notice Description EN',
                    'learnMore' => 'Learn More EN'
                ),
                'acceptAll' => 'Accept All EN',
                'acceptSelected' => 'Accept Selected EN',
                'decline' => 'Decline EN',
                'close' => 'Close EN',
                'purposes' => array(
                    'functional' => array(
                        'title' => 'Functional EN',
                        'description' => 'Functional Description EN'
                    ),
                    'analytics' => array(
                        'title' => 'Analytics EN',
                        'description' => 'Analytics Description EN'
                    ),
                    'advertising' => array(
                        'title' => 'Advertising EN',
                        'description' => 'Advertising Description EN'
                    )
                ),
                'purposeItem' => array(
                    'service' => 'service EN',
                    'services' => 'services EN'
                ),
                'service' => array(
                    'disableAll' => array(
                        'title' => 'Disable All Title EN',
                        'description' => 'Disable All Description EN'
                    ),
                    'optOut' => array(
                        'title' => 'Opt Out Title EN',
                        'description' => 'Opt Out Description EN'
                    ),
                    'required' => array(
                        'title' => 'Required Title EN',
                        'description' => 'Required Description EN'
                    ),
                    'purpose' => 'purpose EN',
                    'purposes' => 'purposes EN',
                    'contextualConsent' => array(
                        'description' => 'Contextual Consent Description EN',
                        'acceptOnce' => 'Accept Once EN',
                        'acceptAlways' => 'Accept Always EN'
                    )
                ),
                'ok' => 'OK EN',
                'save' => 'Save EN',
                'poweredBy' => 'Powered By EN'
            ),
            'fr' => array(
                'privacyPolicyUrl' => '/privacy-policy-fr/',
                'consentModal' => array(
                    'title' => 'Test Title FR',
                    'description' => 'Test Description FR'
                ),
                'consentNotice' => array(
                    'title' => 'Notice Title FR',
                    'changeDescription' => 'Change Description FR',
                    'description' => 'Notice Description FR',
                    'learnMore' => 'Learn More FR'
                ),
                'acceptAll' => 'Accept All FR',
                'acceptSelected' => 'Accept Selected FR',
                'decline' => 'Decline FR',
                'close' => 'Close FR',
                'purposes' => array(
                    'functional' => array(
                        'title' => 'Functional FR',
                        'description' => 'Functional Description FR'
                    ),
                    'analytics' => array(
                        'title' => 'Analytics FR',
                        'description' => 'Analytics Description FR'
                    ),
                    'advertising' => array(
                        'title' => 'Advertising FR',
                        'description' => 'Advertising Description FR'
                    )
                ),
                'purposeItem' => array(
                    'service' => 'service FR',
                    'services' => 'services FR'
                ),
                'service' => array(
                    'disableAll' => array(
                        'title' => 'Disable All Title FR',
                        'description' => 'Disable All Description FR'
                    ),
                    'optOut' => array(
                        'title' => 'Opt Out Title FR',
                        'description' => 'Opt Out Description FR'
                    ),
                    'required' => array(
                        'title' => 'Required Title FR',
                        'description' => 'Required Description FR'
                    ),
                    'purpose' => 'purpose FR',
                    'purposes' => 'purposes FR',
                    'contextualConsent' => array(
                        'description' => 'Contextual Consent Description FR',
                        'acceptOnce' => 'Accept Once FR',
                        'acceptAlways' => 'Accept Always FR'
                    )
                ),
                'ok' => 'OK FR',
                'save' => 'Save FR',
                'poweredBy' => 'Powered By FR'
            )
        );
        
        // Save test translations
        $templates['default']['config']['translations'] = $test_translations;
        update_option('klaro_geo_templates', $templates);
        
        // Verify save
        $updated_templates = get_option('klaro_geo_templates', array());
        if (!isset($updated_templates['default']['config']['translations'])) {
            self::log_error('Failed to save template translations');
            return;
        }
        
        $saved_translations = $updated_templates['default']['config']['translations'];
        
        // Check if all languages were saved
        foreach (array('zz', 'en', 'fr') as $lang) {
            if (!isset($saved_translations[$lang])) {
                self::log_error("Language $lang not saved in template translations");
                return;
            }
        }
        
        // Check specific values
        if ($saved_translations['fr']['consentModal']['title'] !== 'Test Title FR') {
            self::log_error('French translation title not saved correctly');
            return;
        }
        
        self::log_success('Template translations saved successfully');
    }

    /**
     * Test loading template translations
     */
    private static function test_load_template_translations() {
        // Get templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Make sure we have a default template
        if (!isset($templates['default'])) {
            self::log_error('Default template not found');
            return;
        }
        
        // Check if translations exist
        if (!isset($templates['default']['config']['translations'])) {
            self::log_error('Template translations not found');
            return;
        }
        
        $translations = $templates['default']['config']['translations'];
        
        // Check if all expected languages exist
        foreach (array('zz', 'en', 'fr') as $lang) {
            if (!isset($translations[$lang])) {
                self::log_error("Language $lang not found in template translations");
                return;
            }
        }
        
        // Check specific values for each language and key
        $test_keys = array(
            'consentModal.title' => 'Test Title FR',
            'consentModal.description' => 'Test Description FR',
            'consentNotice.title' => 'Notice Title FR',
            'consentNotice.description' => 'Notice Description FR',
            'consentNotice.changeDescription' => 'Change Description FR',
            'consentNotice.learnMore' => 'Learn More FR',
            'acceptAll' => 'Accept All FR',
            'acceptSelected' => 'Accept Selected FR',
            'decline' => 'Decline FR',
            'close' => 'Close FR',
            'purposes.functional.title' => 'Functional FR',
            'purposes.functional.description' => 'Functional Description FR',
            'purposes.analytics.title' => 'Analytics FR',
            'purposes.analytics.description' => 'Analytics Description FR',
            'purposes.advertising.title' => 'Advertising FR',
            'purposes.advertising.description' => 'Advertising Description FR',
            'purposeItem.service' => 'service FR',
            'purposeItem.services' => 'services FR',
            'service.disableAll.title' => 'Disable All Title FR',
            'service.disableAll.description' => 'Disable All Description FR',
            'service.optOut.title' => 'Opt Out Title FR',
            'service.optOut.description' => 'Opt Out Description FR',
            'service.required.title' => 'Required Title FR',
            'service.required.description' => 'Required Description FR',
            'service.purpose' => 'purpose FR',
            'service.purposes' => 'purposes FR',
            'service.contextualConsent.description' => 'Contextual Consent Description FR',
            'service.contextualConsent.acceptOnce' => 'Accept Once FR',
            'service.contextualConsent.acceptAlways' => 'Accept Always FR',
            'ok' => 'OK FR',
            'save' => 'Save FR',
            'poweredBy' => 'Powered By FR'
        );

        foreach ($test_keys as $key_path => $expected_value) {
            $parts = explode('.', $key_path);
            $actual_value = $translations['fr'];

            foreach ($parts as $part) {
                if (!isset($actual_value[$part])) {
                    self::log_error("French translation key '$key_path' not found");
                    return;
                }
                $actual_value = $actual_value[$part];
            }

            if ($actual_value !== $expected_value) {
                self::log_error("French translation key '$key_path' has incorrect value. Expected: '$expected_value', Got: '$actual_value'");
                return;
            }
        }
        
        self::log_success('Template translations loaded successfully');
    }

    /**
     * Test template translations consistency across page loads
     */
    private static function test_template_translations_consistency() {
        // Get templates
        $templates = get_option('klaro_geo_templates', array());
        
        // Make sure we have a default template
        if (!isset($templates['default'])) {
            self::log_error('Default template not found');
            return;
        }
        
        // Save current translations
        $original_translations = isset($templates['default']['config']['translations']) ? 
            $templates['default']['config']['translations'] : array();
        
        // Add a new language
        if (!isset($original_translations['de'])) {
            $original_translations['de'] = array(
                'consentModal' => array(
                    'title' => 'Test Title DE',
                    'description' => 'Test Description DE'
                ),
                'acceptAll' => 'Accept All DE',
                'declineAll' => 'Decline All DE'
            );
            
            // Save updated translations
            $templates['default']['config']['translations'] = $original_translations;
            update_option('klaro_geo_templates', $templates);
        }
        
        // Get templates again (simulating a page reload)
        $reloaded_templates = get_option('klaro_geo_templates', array());
        
        // Check if translations still exist
        if (!isset($reloaded_templates['default']['config']['translations'])) {
            self::log_error('Template translations lost after reload');
            return;
        }
        
        $reloaded_translations = $reloaded_templates['default']['config']['translations'];
        
        // Check if all languages still exist
        foreach (array('zz', 'en', 'fr', 'de') as $lang) {
            if (!isset($reloaded_translations[$lang])) {
                self::log_error("Language $lang lost after reload");
                return;
            }
        }
        
        // Check specific values
        if ($reloaded_translations['de']['consentModal']['title'] !== 'Test Title DE') {
            self::log_error('German translation title changed after reload');
            return;
        }
        
        self::log_success('Template translations consistent across page loads');
    }

    /**
     * Log an error message
     */
    private static function log_error($message) {
        error_log('[Klaro Geo Test Error] ' . $message);
        echo '<div class="notice notice-error"><p><strong>Klaro Geo Test Error:</strong> ' . esc_html($message) . '</p></div>';
    }

    /**
     * Test JSON to form conversion
     */
    private static function test_json_to_form_conversion() {
        // Create test JSON
        $test_json = json_encode(array(
            'zz' => array(
                'consentModal' => array(
                    'title' => 'JSON Title ZZ',
                    'description' => 'JSON Description ZZ'
                ),
                'acceptAll' => 'JSON Accept All ZZ',
                'declineAll' => 'JSON Decline All ZZ'
            ),
            'it' => array(
                'consentModal' => array(
                    'title' => 'JSON Title IT',
                    'description' => 'JSON Description IT'
                ),
                'acceptAll' => 'JSON Accept All IT',
                'declineAll' => 'JSON Decline All IT'
            )
        ));

        // Create mock POST data
        $_POST['template_config'] = array(
            'translations_json' => $test_json
        );

        // Process the JSON (simulating what happens in klaro-admin.php)
        $template_config = array();

        if (isset($_POST['template_config']['translations_json'])) {
            $translations_json = $_POST['template_config']['translations_json'];

            // Try to decode it to use in the config
            $decoded_translations = json_decode($translations_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_translations)) {
                // If valid JSON, use it for the translations
                $template_config['translations'] = $decoded_translations;
            } else {
                // If invalid JSON, log an error
                self::log_error('Invalid JSON in translations_json: ' . json_last_error_msg());
                return;
            }

            // Remove from the array so we don't process it again
            unset($_POST['template_config']['translations_json']);
        }

        // Check if the translations were properly decoded
        if (!isset($template_config['translations'])) {
            self::log_error('Failed to decode JSON to translations');
            return;
        }

        // Check if all languages were decoded
        foreach (array('zz', 'it') as $lang) {
            if (!isset($template_config['translations'][$lang])) {
                self::log_error("Language $lang not decoded from JSON");
                return;
            }
        }

        // Check specific values
        if ($template_config['translations']['it']['consentModal']['title'] !== 'JSON Title IT') {
            self::log_error('Italian translation title not decoded correctly from JSON');
            return;
        }

        self::log_success('JSON to form conversion successful');
    }

    /**
     * Test form to JSON conversion
     */
    private static function test_form_to_json_conversion() {
        // Create mock form data
        $_POST['template_config'] = array(
            'translations' => array(
                'zz' => array(
                    'consentModal' => array(
                        'title' => 'Form Title ZZ',
                        'description' => 'Form Description ZZ'
                    ),
                    'acceptAll' => 'Form Accept All ZZ',
                    'declineAll' => 'Form Decline All ZZ'
                ),
                'es' => array(
                    'consentModal' => array(
                        'title' => 'Form Title ES',
                        'description' => 'Form Description ES'
                    ),
                    'acceptAll' => 'Form Accept All ES',
                    'declineAll' => 'Form Decline All ES'
                )
            )
        );

        // Convert form data to JSON (simulating what happens in JavaScript)
        $translations = $_POST['template_config']['translations'];
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Check if the JSON was properly encoded
        if (empty($json)) {
            self::log_error('Failed to encode translations to JSON');
            return;
        }

        // Decode the JSON to verify its contents
        $decoded = json_decode($json, true);

        // Check if all languages were encoded
        foreach (array('zz', 'es') as $lang) {
            if (!isset($decoded[$lang])) {
                self::log_error("Language $lang not encoded to JSON");
                return;
            }
        }

        // Check specific values
        if ($decoded['es']['consentModal']['title'] !== 'Form Title ES') {
            self::log_error('Spanish translation title not encoded correctly to JSON');
            return;
        }

        self::log_success('Form to JSON conversion successful');
    }

    /**
     * Log a success message
     */
    private static function log_success($message) {
        error_log('[Klaro Geo Test Success] ' . $message);
        echo '<div class="notice notice-success"><p><strong>Klaro Geo Test Success:</strong> ' . esc_html($message) . '</p></div>';
    }
}

// Run tests if this is a test request
if (isset($_GET['klaro_geo_run_tests']) && current_user_can('manage_options')) {
    add_action('admin_notices', function() {
        Klaro_Geo_Template_Translations_Test::run_tests();
    });
}