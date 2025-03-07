/**
 * JavaScript Tests for Template Translations
 * 
 * This file contains tests for the template translations functionality in the browser.
 */

(function() {
    /**
     * Run all tests
     */
    function runTests() {
        console.group('Klaro Geo Template Translations Tests');
        
        testTabsInitialization();
        testAddLanguage();
        testDeleteLanguage();
        testLanguagePersistence();
        
        console.groupEnd();
    }

    /**
     * Test tabs initialization
     */
    function testTabsInitialization() {
        console.log('Testing tabs initialization...');
        
        // Check if translations tabs exist
        if (!document.getElementById('translations-tabs')) {
            console.error('Translations tabs container not found');
            return;
        }
        
        // Check if fallback tab exists
        if (!document.getElementById('tab-zz')) {
            console.error('Fallback tab not found');
            return;
        }
        
        // Check if add language tab exists
        if (!document.getElementById('tab-add')) {
            console.error('Add language tab not found');
            return;
        }
        
        console.log('✓ Tabs initialized successfully');
    }

    /**
     * Test adding a language
     */
    function testAddLanguage() {
        console.log('Testing adding a language...');
        
        // Get the current number of tabs
        const initialTabCount = document.querySelectorAll('.translations-tabs-nav li').length;
        
        // Set up test language
        const testLang = 'test';
        
        // Remove the test language if it already exists
        const existingTab = document.getElementById('tab-' + testLang);
        if (existingTab) {
            existingTab.remove();
            document.querySelector('.translations-tabs-nav a[href="#tab-' + testLang + '"]').parentNode.remove();
        }
        
        // Simulate adding a language
        // This would normally be done through the UI, but we'll call the function directly
        if (typeof window.addLanguageTab === 'function') {
            window.addLanguageTab(testLang, 'Test');
        } else {
            // Fallback if the function isn't exposed globally
            // Create a custom event that the page can listen for
            const event = new CustomEvent('klaro-geo-test-add-language', {
                detail: {
                    langCode: testLang,
                    langName: 'Test'
                }
            });
            document.dispatchEvent(event);
            
            // Check if the tab was added
            setTimeout(() => {
                const newTabCount = document.querySelectorAll('.translations-tabs-nav li').length;
                if (newTabCount <= initialTabCount) {
                    console.error('Failed to add language tab');
                    return;
                }
                
                if (!document.getElementById('tab-' + testLang)) {
                    console.error('Test language tab not found after adding');
                    return;
                }
                
                console.log('✓ Language added successfully');
            }, 500);
            
            return;
        }
        
        // Check if the tab was added
        const newTabCount = document.querySelectorAll('.translations-tabs-nav li').length;
        if (newTabCount <= initialTabCount) {
            console.error('Failed to add language tab');
            return;
        }
        
        if (!document.getElementById('tab-' + testLang)) {
            console.error('Test language tab not found after adding');
            return;
        }
        
        console.log('✓ Language added successfully');
    }

    /**
     * Test deleting a language
     */
    function testDeleteLanguage() {
        console.log('Testing deleting a language...');
        
        // Set up test language
        const testLang = 'test';
        
        // Add the test language if it doesn't exist
        if (!document.getElementById('tab-' + testLang)) {
            if (typeof window.addLanguageTab === 'function') {
                window.addLanguageTab(testLang, 'Test');
            } else {
                console.error('Cannot add test language for deletion test');
                return;
            }
        }
        
        // Get the current number of tabs
        const initialTabCount = document.querySelectorAll('.translations-tabs-nav li').length;
        
        // Find the delete button
        const deleteButton = document.querySelector('#tab-' + testLang + ' .delete-language-btn');
        if (!deleteButton) {
            console.error('Delete button not found for test language');
            return;
        }
        
        // Store original confirm function
        const originalConfirm = window.confirm;
        
        // Mock confirm to always return true
        window.confirm = function() {
            return true;
        };
        
        // Simulate clicking the delete button
        deleteButton.click();
        
        // Restore original confirm function
        window.confirm = originalConfirm;
        
        // Check if the tab was removed
        const newTabCount = document.querySelectorAll('.translations-tabs-nav li').length;
        if (newTabCount >= initialTabCount) {
            console.error('Failed to delete language tab');
            return;
        }
        
        if (document.getElementById('tab-' + testLang)) {
            console.error('Test language tab still exists after deletion');
            return;
        }
        
        console.log('✓ Language deleted successfully');
    }

    /**
     * Test language persistence
     */
    function testLanguagePersistence() {
        console.log('Testing language persistence...');
        
        // This test requires that the page has been loaded with existing languages
        // We'll check if there are any languages other than fallback and add
        const tabs = document.querySelectorAll('.translations-tabs-nav li a');
        let hasLanguages = false;
        
        for (let i = 0; i < tabs.length; i++) {
            const href = tabs[i].getAttribute('href');
            if (href && href !== '#tab-zz' && href !== '#tab-add') {
                hasLanguages = true;
                break;
            }
        }
        
        if (!hasLanguages) {
            console.warn('No languages found for persistence test');
            return;
        }
        
        // Check if languages match what's in the templates
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
        
        // Check if all template languages have tabs
        let allLanguagesHaveTabs = true;
        templateLanguages.forEach(function(langCode) {
            if (langCode !== 'zz' && !document.getElementById('tab-' + langCode)) {
                console.error('Language ' + langCode + ' from templates does not have a tab');
                allLanguagesHaveTabs = false;
            }
        });
        
        if (!allLanguagesHaveTabs) {
            return;
        }
        
        console.log('✓ Languages persisted successfully');
    }

    // Run tests when the page is loaded
    if (document.readyState === 'complete') {
        runTests();
    } else {
        window.addEventListener('load', runTests);
    }
})();