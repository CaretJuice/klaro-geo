<?php
/**
 * Test GTM defaults
 */

class gtmDefaultsTest extends WP_UnitTestCase {
    
    /**
     * Test that the default GTM values are set correctly
     */
    public function test_gtm_default_values() {
        // Get the default values
        $defaults = get_klaro_default_values();
        
        // Check that the GTM defaults exist
        $this->assertArrayHasKey('gtm_oninit', $defaults);
        $this->assertArrayHasKey('gtm_onaccept', $defaults);
        $this->assertArrayHasKey('gtm_ondecline', $defaults);


        // Check that the GTM defaults are not empty
        $this->assertNotEmpty($defaults['gtm_oninit']);
        $this->assertNotEmpty($defaults['gtm_onaccept']);
        $this->assertNotEmpty($defaults['gtm_ondecline']);

        
        // Check that the GTM defaults contain expected content
        $this->assertStringContainsString('window.dataLayer', $defaults['gtm_oninit']);
        $this->assertStringContainsString('gtag(', $defaults['gtm_oninit']);
        $this->assertStringContainsString('consent', $defaults['gtm_oninit']);
        $this->assertStringContainsString('dataLayer.push', $defaults['gtm_onaccept']);
    }
    
    /**
     * Test that the default services are set up correctly with GTM callbacks
     */
    public function test_default_services_with_gtm_callbacks() {
        // Reset the default services
        global $default_services;
        $default_services = null;
        
        // Call the activation function to set up default services
        klaro_geo_activate();
        
        // Get the services from the database
        $services_json = get_option('klaro_geo_services', '');
        $services = json_decode($services_json, true);
        
        // Check that the services exist
        $this->assertNotEmpty($services);
        
        // Find the GTM service
        $gtm_service = null;
        foreach ($services as $service) {
            if ($service['name'] === 'google-tag-manager') {
                $gtm_service = $service;
                break;
            }
        }
        
        // Check that the GTM service exists
        $this->assertNotNull($gtm_service);
        
        // Check that the GTM service has the callbacks
        $this->assertArrayHasKey('onInit', $gtm_service);
        $this->assertArrayHasKey('onAccept', $gtm_service);
        
        // Check that the callbacks are not empty
        $this->assertNotEmpty($gtm_service['onInit']);
        $this->assertNotEmpty($gtm_service['onAccept']);
        
        // Check that the callbacks contain expected content
        $this->assertStringContainsString('window.dataLayer', $gtm_service['onInit']);
        $this->assertStringContainsString('window.currentKlaroOpts', $gtm_service['onInit']);
        $this->assertStringContainsString('dataLayer.push', $gtm_service['onAccept']);
        $this->assertStringContainsString('acceptedServices', $gtm_service['onAccept']);
    }
}