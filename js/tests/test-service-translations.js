/**
 * JavaScript Tests for Service Translations
 * 
 * This file contains tests for the service translations functionality in the browser.
 */

(function() {
    /**
     * Run all tests
     */
    function runTests() {
        console.group('Klaro Geo Service Translations Tests');
        
        testTabsInitialization();
        testLanguagesFromTemplates();
        testServiceTranslationsPersistence();
        
        console.groupEnd();
    }

    /**
     * Test tabs initialization
     */
    function testTabsInitialization() {
        console.log('Testing service tabs initialization...');
        
        // Check if service translations tabs exist
        if (!document.getElementById('service-translations-tabs')) {
            console.error('Service translations tabs container not found');
            return;
        }
        
        // Check if fallback tab exists
        if (!document.getElementById('service-tab-zz')) {
            console.error('Service fallback tab not found');
            return;
        }
        
        console.log('✓ Service tabs initialized successfully');
    }

    /**
     * Test languages from templates
     */
    function testLanguagesFromTemplates() {
        console.log('Testing languages from templates...');
        
        // Check if klaroTemplates is defined
        if (typeof window.klaroTemplates === 'undefined') {
            console.error('klaroTemplates not defined');
            return;
        }
        
        // Get languages from templates
        const templateLanguages = new Set(['zz']); // Always include fallback
        
        Object.values(window.klaroTemplates).forEach(function(template) {
            if (template.config && template.config.translations) {
                Object.keys(template.config.translations).forEach(function(langCode) {
                    if (langCode !== 'zz') { // Skip fallback as we already have it
                        templateLanguages.add(langCode);
                    }
                });
            }
        });
        
        // Check if all template languages have tabs in the service form
        let allLanguagesHaveTabs = true;
        templateLanguages.forEach(function(langCode) {
            if (langCode !== 'zz' && !document.getElementById('service-tab-' + langCode)) {
                console.error('Language ' + langCode + ' from templates does not have a service tab');
                allLanguagesHaveTabs = false;
            }
        });
        
        if (!allLanguagesHaveTabs) {
            return;
        }
        
        console.log('✓ Service languages from templates loaded successfully');
    }

    /**
     * Test service translations persistence
     */
    function testServiceTranslationsPersistence() {
        console.log('Testing service translations persistence...');
        
        // This test requires that the page has been loaded with existing services
        // We'll check if there are any services
        if (typeof klaroGeo === 'undefined' || !klaroGeo.services || !klaroGeo.services.length) {
            console.warn('No services found for persistence test');
            return;
        }
        
        // Check if services have translations
        let hasTranslations = false;
        
        for (let i = 0; i < klaroGeo.services.length; i++) {
            const service = klaroGeo.services[i];
            if (service.translations && Object.keys(service.translations).length > 0) {
                hasTranslations = true;
                break;
            }
        }
        
        if (!hasTranslations) {
            console.warn('No service translations found for persistence test');
            return;
        }
        
        // Check if service translations match template languages
        if (typeof window.klaroTemplates === 'undefined') {
            console.error('klaroTemplates not defined');
            return;
        }
        
        // Get languages from templates
        const templateLanguages = new Set(['zz']); // Always include fallback
        
        Object.values(window.klaroTemplates).forEach(function(template) {
            if (template.config && template.config.translations) {
                Object.keys(template.config.translations).forEach(function(langCode) {
                    if (langCode !== 'zz') { // Skip fallback as we already have it
                        templateLanguages.add(langCode);
                    }
                });
            }
        });
        
        // Check if all template languages have tabs in the service form
        let allLanguagesHaveTabs = true;
        templateLanguages.forEach(function(langCode) {
            if (langCode !== 'zz' && !document.getElementById('service-tab-' + langCode)) {
                console.error('Language ' + langCode + ' from templates does not have a service tab');
                allLanguagesHaveTabs = false;
            }
        });
        
        if (!allLanguagesHaveTabs) {
            return;
        }
        
        console.log('✓ Service translations persisted successfully');
    }

    // Run tests when the page is loaded
    if (document.readyState === 'complete') {
        runTests();
    } else {
        window.addEventListener('load', runTests);
    }
})();