<?php
/**
 * Contract Verification Tests
 *
 * These tests verify the contracts defined in specs/*.spec.yaml files.
 * Each test method maps to a specific contract or business rule.
 *
 * @see specs/klaro-geo-config.spec.yaml
 * @see specs/class-klaro-geo-template-settings.spec.yaml
 * @see specs/class-klaro-geo-country-settings.spec.yaml
 */

class ContractVerificationTest extends WP_UnitTestCase {

    /**
     * Template settings instance
     */
    private $template_settings;

    /**
     * Country settings instance
     */
    private $country_settings;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Clean up any existing options
        delete_option('klaro_geo_templates');
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_visible_countries');

        // Initialize fresh instances
        $this->template_settings = new Klaro_Geo_Template_Settings();
        $this->country_settings = new Klaro_Geo_Country_Settings();
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        delete_option('klaro_geo_templates');
        delete_option('klaro_geo_country_settings');
        delete_option('klaro_geo_visible_countries');
        parent::tearDown();
    }

    // =========================================================================
    // CONTRACT: Template Structure (class-klaro-geo-template-settings.spec.yaml)
    // =========================================================================

    /**
     * Contract: Every template MUST have 'name' and 'config' keys
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @rule template_structure
     */
    public function test_contract_template_structure_has_required_keys() {
        $templates = $this->template_settings->get();

        $this->assertIsArray($templates, 'get() must return an array');
        $this->assertNotEmpty($templates, 'get() must return at least one template');

        foreach ($templates as $key => $template) {
            $this->assertArrayHasKey('name', $template,
                "Template '$key' must have 'name' key");
            $this->assertArrayHasKey('config', $template,
                "Template '$key' must have 'config' key");
            $this->assertIsArray($template['config'],
                "Template '$key' config must be an array");
        }
    }

    /**
     * Contract: get() ALWAYS returns array (never null)
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @rule get() contract
     */
    public function test_contract_get_always_returns_array() {
        // Even with empty database, should return array with defaults
        delete_option('klaro_geo_templates');
        $fresh_settings = new Klaro_Geo_Template_Settings();

        $templates = $fresh_settings->get();

        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates, 'Should have at least default templates');
    }

    /**
     * Contract: get_template() returns null if not found (doesn't auto-create)
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @method get_template
     */
    public function test_contract_get_template_returns_null_for_missing() {
        $result = $this->template_settings->get_template('nonexistent_template_xyz');

        $this->assertNull($result,
            'get_template() must return null for non-existent template');
    }

    // =========================================================================
    // CONTRACT: Database First (class-klaro-geo-template-settings.spec.yaml)
    // =========================================================================

    /**
     * Contract: Database values MUST be preferred over hardcoded defaults
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @rule database_first
     */
    public function test_contract_database_values_preferred_over_defaults() {
        // Set up a custom template in database
        $custom_template = array(
            'name' => 'Custom Database Template',
            'description' => 'This came from database',
            'config' => array(
                'cookieDomain' => '.custom-domain.com',
                'default' => true,
                'mustConsent' => false
            )
        );

        // Save to database
        update_option('klaro_geo_templates', array(
            'custom' => $custom_template,
            'default' => array(
                'name' => 'Default From DB',
                'config' => array(
                    'cookieDomain' => '.database-domain.com'
                )
            )
        ));

        // Create new instance to load from database
        $fresh_settings = new Klaro_Geo_Template_Settings();
        $templates = $fresh_settings->get();

        // Should have our custom template from database
        $this->assertArrayHasKey('custom', $templates,
            'Database template should be loaded');
        $this->assertEquals('Custom Database Template', $templates['custom']['name'],
            'Database template name should be preserved');
        $this->assertEquals('.custom-domain.com', $templates['custom']['config']['cookieDomain'],
            'Database cookieDomain should be preserved');
    }

    /**
     * Contract: User-set cookieDomain MUST NOT be overwritten by defaults
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @rule preserve_user_data
     */
    public function test_contract_user_cookieDomain_preserved() {
        // Set up template with explicit cookieDomain
        $this->template_settings->set_template('opt-out', array(
            'name' => 'Opt-Out Template',
            'config' => array(
                'cookieDomain' => '.caretjuice.com',
                'default' => true
            )
        ));
        $this->template_settings->save();

        // Create new instance (simulating page reload)
        $fresh_settings = new Klaro_Geo_Template_Settings();
        $template = $fresh_settings->get_template('opt-out');

        $this->assertNotNull($template);
        $this->assertEquals('.caretjuice.com', $template['config']['cookieDomain'],
            'User-configured cookieDomain must be preserved after reload');
    }

    // =========================================================================
    // CONTRACT: cookieDomain Handling (klaro-geo-config.spec.yaml)
    // =========================================================================

    /**
     * Contract: If template has explicit cookieDomain (non-empty string), use it exactly
     *
     * @spec klaro-geo-config.spec.yaml
     * @rule cookieDomain_handling
     */
    public function test_contract_explicit_cookieDomain_used_exactly() {
        // Set up template with explicit cookieDomain
        $this->template_settings->set_template('test', array(
            'name' => 'Test Template',
            'config' => array(
                'cookieDomain' => '.explicit-domain.com',
                'default' => false
            )
        ));
        $this->template_settings->save();

        // Get template config
        $config = $this->template_settings->get_template_config('test');

        $this->assertEquals('.explicit-domain.com', $config['cookieDomain'],
            'Explicit cookieDomain must be used exactly as specified');
    }

    /**
     * Contract: If template cookieDomain is empty string '', it should trigger auto-detect
     *
     * This test verifies the template config, not the actual auto-detection
     * (which happens in klaro_geo_generate_config_file)
     *
     * @spec klaro-geo-config.spec.yaml
     * @rule cookieDomain_handling
     */
    public function test_contract_empty_cookieDomain_in_template() {
        // Default templates should have empty cookieDomain
        $default_templates = $this->template_settings->get_default_templates();

        $this->assertEquals('', $default_templates['default']['config']['cookieDomain'],
            "Default template should have empty cookieDomain (triggers auto-detect)");
        $this->assertEquals('', $default_templates['strict']['config']['cookieDomain'],
            "Strict template should have empty cookieDomain (triggers auto-detect)");
        $this->assertEquals('', $default_templates['relaxed']['config']['cookieDomain'],
            "Relaxed template should have empty cookieDomain (triggers auto-detect)");
    }

    // =========================================================================
    // CONTRACT: Effective Settings (class-klaro-geo-country-settings.spec.yaml)
    // =========================================================================

    /**
     * Contract: get_effective_settings() MUST return array with 'template' key
     *
     * @spec class-klaro-geo-country-settings.spec.yaml
     * @function get_effective_settings
     */
    public function test_contract_effective_settings_returns_template_key() {
        $effective = $this->country_settings->get_effective_settings('US');

        $this->assertIsArray($effective);
        $this->assertArrayHasKey('template', $effective,
            'Effective settings must have template key');
        $this->assertIsString($effective['template'],
            'Template value must be a string');
        $this->assertArrayHasKey('source', $effective,
            'Effective settings must have source key');
    }

    /**
     * Contract: Returned template key MUST exist in templates database
     *
     * @spec class-klaro-geo-country-settings.spec.yaml
     * @rule template_key_validation
     */
    public function test_contract_effective_settings_template_exists() {
        // Set up country to use a specific template
        $this->country_settings->set_country('US', array(
            'template' => 'opt-out'
        ));

        // Make sure the template exists
        $this->template_settings->set_template('opt-out', array(
            'name' => 'Opt-Out Template',
            'config' => array('default' => true)
        ));
        $this->template_settings->save();

        // Set visible countries
        $this->country_settings->set_visible_countries(array('US'));

        $effective = $this->country_settings->get_effective_settings('US');
        $templates = $this->template_settings->get();

        $this->assertArrayHasKey($effective['template'], $templates,
            "Returned template '{$effective['template']}' must exist in templates database");
    }

    /**
     * Contract: If configured template doesn't exist, fall back to default_template
     *
     * @spec class-klaro-geo-country-settings.spec.yaml
     * @rule template_key_validation
     */
    public function test_contract_missing_template_falls_back() {
        // Set up country to use a template that doesn't exist
        $this->country_settings->set_country('US', array(
            'template' => 'nonexistent_template'
        ));

        // Set fallback template
        $this->country_settings->set_default_template('default');

        // Set visible countries
        $this->country_settings->set_visible_countries(array('US'));

        $effective = $this->country_settings->get_effective_settings('US');
        $templates = $this->template_settings->get();

        // Either the returned template exists, or it fell back to default
        $template_exists = isset($templates[$effective['template']]);

        $this->assertTrue($template_exists,
            "Effective template must exist in database, even after fallback");
    }

    /**
     * Contract: Template resolution order - Region > Country > Fallback
     *
     * @spec class-klaro-geo-country-settings.spec.yaml
     * @rule template_resolution_order
     */
    public function test_contract_template_resolution_order() {
        // Set up templates
        $this->template_settings->set_template('country-template', array(
            'name' => 'Country Template',
            'config' => array('default' => false)
        ));
        $this->template_settings->set_template('region-template', array(
            'name' => 'Region Template',
            'config' => array('default' => true)
        ));
        $this->template_settings->set_template('fallback-template', array(
            'name' => 'Fallback Template',
            'config' => array('default' => false)
        ));
        $this->template_settings->save();

        // Set fallback
        $this->country_settings->set_default_template('fallback-template');

        // Set country with region override
        $this->country_settings->set_country('US', array(
            'template' => 'country-template',
            'regions' => array(
                'CA' => 'region-template'
            )
        ));

        // Set visible countries
        $this->country_settings->set_visible_countries(array('US', 'UK'));

        // Test region takes precedence
        $us_ca = $this->country_settings->get_effective_settings('US-CA');
        $this->assertEquals('region-template', $us_ca['template'],
            'Region template should take precedence');
        $this->assertEquals('region', $us_ca['source']);

        // Test country takes precedence over fallback
        $us = $this->country_settings->get_effective_settings('US');
        $this->assertEquals('country-template', $us['template'],
            'Country template should be used when no region match');
        $this->assertEquals('country', $us['source']);

        // Test fallback is used for unconfigured country
        $uk = $this->country_settings->get_effective_settings('UK');
        $this->assertEquals('fallback-template', $uk['template'],
            'Fallback template should be used for unconfigured country');
    }

    // =========================================================================
    // CONTRACT: Config Generation Flow (klaro-geo-config.spec.yaml)
    // =========================================================================

    /**
     * Contract: All template config values MUST be copied to klaroConfig
     *
     * This tests that when a template is applied, all its config values
     * are properly transferred.
     *
     * @spec klaro-geo-config.spec.yaml
     * @step step_5_config_application
     */
    public function test_contract_all_config_values_copied() {
        // Set up a template with multiple config values
        $template_config = array(
            'cookieDomain' => '.test-domain.com',
            'cookiePath' => '/app',
            'cookieName' => 'my_consent',
            'cookieExpiresAfterDays' => 180,
            'default' => true,
            'mustConsent' => false,
            'acceptAll' => true,
            'hideDeclineAll' => false,
            'noticeAsModal' => true,
            'styling' => array(
                'theme' => array('light', 'top', 'wide')
            )
        );

        $this->template_settings->set_template('complete-test', array(
            'name' => 'Complete Test Template',
            'config' => $template_config
        ));
        $this->template_settings->save();

        // Retrieve and verify
        $retrieved = $this->template_settings->get_template_config('complete-test');

        foreach ($template_config as $key => $expected_value) {
            $this->assertArrayHasKey($key, $retrieved,
                "Config key '$key' must be present after retrieval");
            $this->assertEquals($expected_value, $retrieved[$key],
                "Config value for '$key' must match original");
        }
    }

    // =========================================================================
    // CONTRACT: Integration - Template + Country Settings
    // =========================================================================

    /**
     * Contract: Full flow - Geo detection → Template selection → Config with cookieDomain
     *
     * This is the key integration test that verifies the full contract flow.
     *
     * @spec klaro-geo-config.spec.yaml
     * @spec class-klaro-geo-country-settings.spec.yaml
     */
    public function test_contract_full_template_application_flow() {
        // 1. Set up templates with specific cookieDomain values
        $this->template_settings->set_template('opt-in', array(
            'name' => 'Opt-In Template',
            'config' => array(
                'cookieDomain' => '.optin-domain.com',
                'default' => false,
                'mustConsent' => true
            )
        ));

        $this->template_settings->set_template('opt-out', array(
            'name' => 'Opt-Out Template',
            'config' => array(
                'cookieDomain' => '.optout-domain.com',
                'default' => true,
                'mustConsent' => false
            )
        ));
        $this->template_settings->save();

        // 2. Set up country settings
        $this->country_settings->set_default_template('opt-in');  // Fallback
        $this->country_settings->set_country('US', array(
            'template' => 'opt-out'  // US uses opt-out
        ));
        $this->country_settings->set_visible_countries(array('US', 'CA', 'UK'));
        $this->country_settings->save();

        // 3. Get effective settings for US (should be opt-out)
        $us_effective = $this->country_settings->get_effective_settings('US');
        $this->assertEquals('opt-out', $us_effective['template']);
        $this->assertEquals('country', $us_effective['source']);

        // 4. Verify the template has the correct cookieDomain
        $opt_out_template = $this->template_settings->get_template('opt-out');
        $this->assertEquals('.optout-domain.com', $opt_out_template['config']['cookieDomain'],
            'Opt-out template must have its configured cookieDomain');

        // 5. Get effective settings for CA (should fall back to opt-in)
        $ca_effective = $this->country_settings->get_effective_settings('CA');
        $this->assertEquals('opt-in', $ca_effective['template']);

        // 6. Verify fallback template has its cookieDomain
        $opt_in_template = $this->template_settings->get_template('opt-in');
        $this->assertEquals('.optin-domain.com', $opt_in_template['config']['cookieDomain'],
            'Opt-in (fallback) template must have its configured cookieDomain');
    }

    /**
     * Contract: Template changes persist across save/load cycles
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @rule preserve_user_data
     */
    public function test_contract_template_persistence() {
        // Set up a template
        $this->template_settings->set_template('persistent-test', array(
            'name' => 'Persistent Template',
            'config' => array(
                'cookieDomain' => '.persistent-domain.com',
                'default' => true,
                'customField' => 'custom-value'
            )
        ));
        $this->template_settings->save();

        // Create a completely new instance (simulating new request)
        unset($this->template_settings);
        $fresh_settings = new Klaro_Geo_Template_Settings();

        $template = $fresh_settings->get_template('persistent-test');

        $this->assertNotNull($template, 'Template must persist after save');
        $this->assertEquals('Persistent Template', $template['name']);
        $this->assertEquals('.persistent-domain.com', $template['config']['cookieDomain'],
            'cookieDomain must persist after save/load cycle');
        $this->assertEquals('custom-value', $template['config']['customField']);
    }

    // =========================================================================
    // CONTRACT: Edge Cases and Failure Scenarios
    // =========================================================================

    /**
     * Contract: Handle corrupted/invalid database data gracefully
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @edge_case Database returns corrupted data
     */
    public function test_contract_handles_corrupted_database() {
        // Simulate corrupted data in database
        update_option('klaro_geo_templates', 'not_an_array');

        // Should not throw, should fall back to defaults
        $fresh_settings = new Klaro_Geo_Template_Settings();
        $templates = $fresh_settings->get();

        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
    }

    /**
     * Contract: Handle empty database gracefully
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @edge_case Database is empty
     */
    public function test_contract_handles_empty_database() {
        delete_option('klaro_geo_templates');

        $fresh_settings = new Klaro_Geo_Template_Settings();
        $templates = $fresh_settings->get();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('default', $templates,
            'Default template must exist even with empty database');
    }

    /**
     * Contract: Template without 'config' key is handled
     *
     * @spec class-klaro-geo-template-settings.spec.yaml
     * @edge_case Template exists but has no 'config' key
     */
    public function test_contract_handles_template_without_config() {
        // Set up malformed template
        update_option('klaro_geo_templates', array(
            'malformed' => array(
                'name' => 'Malformed Template'
                // Missing 'config' key
            )
        ));

        $fresh_settings = new Klaro_Geo_Template_Settings();
        $config = $fresh_settings->get_template_config('malformed');

        // Should return null for missing config, not throw
        $this->assertNull($config,
            'get_template_config should return null for template without config');
    }
}
