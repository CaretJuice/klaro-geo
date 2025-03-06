/**
 * Klaro Geo Consent Button JavaScript Tests
 */

describe('Klaro Consent Button', function() {
    
    let consentButtonModule;
    
    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = `
            <div id="menu-container">
                <ul id="menu">
                    <li class="menu-item"><a href="#">Home</a></li>
                    <li class="menu-item"><a href="#">About</a></li>
                    <li class="menu-item klaro-menu-item"><a href="#" class="klaro-menu-consent-button">Manage Consent Settings</a></li>
                </ul>
            </div>
        `;
        
        // Mock Klaro object
        window.klaro = {
            show: jest.fn()
        };
        
        // Set up Klaro consent button variables
        window.klaroConsentButtonData = {
            floatingButtonEnabled: true,
            buttonText: 'Manage Consent Settings',
            theme: 'light'
        };
        
        // Clear any previous module cache
        jest.resetModules();
        
        // Load the script
        consentButtonModule = require('../../js/klaro-consent-button.js');
        
        // Initialize the buttons
        consentButtonModule.initConsentButtons();
    });
    
    afterEach(function() {
        // Clean up
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete window.klaroConsentButtonData;
        delete window.klaro;
    });
    
    test('should create floating button when enabled', function() {
        // The floating button should be created
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        expect(floatingButton.textContent).toBe('Manage Consent Settings');
        expect(floatingButton.classList.contains('light')).toBe(true);
    });
    
    test('should not create floating button when disabled', function() {
        // Clean up and set disabled
        document.body.innerHTML = '';
        window.klaroConsentButtonData.floatingButtonEnabled = false;
        
        // Re-initialize
        consentButtonModule.initConsentButtons();
        
        // The floating button should not be created
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).toBeNull();
    });
    
    test('should apply correct theme class to floating button', function() {
        // Clean up and set dark theme
        document.body.innerHTML = '';
        window.klaroConsentButtonData.theme = 'dark';
        
        // Re-initialize
        consentButtonModule.initConsentButtons();
        
        // The floating button should have dark theme
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        expect(floatingButton.classList.contains('dark')).toBe(true);
        expect(floatingButton.classList.contains('light')).toBe(false);
    });
    
    test('should show Klaro modal when floating button is clicked', function() {
        // Get the floating button
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        
        // Simulate click
        floatingButton.click();
        
        // Klaro.show should be called
        expect(window.klaro.show).toHaveBeenCalled();
    });
    
    test('should show Klaro modal when menu button is clicked', function() {
        // Get the menu button
        const menuButton = document.querySelector('.klaro-menu-consent-button');
        expect(menuButton).not.toBeNull();
        
        // Simulate click
        menuButton.click();
        
        // Klaro.show should be called
        expect(window.klaro.show).toHaveBeenCalled();
    });
    
    test('should handle case when Klaro is not loaded', function() {
        // Remove Klaro object
        delete window.klaro;
        
        // Mock console.error
        console.error = jest.fn();
        
        // Get the floating button
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        
        // Simulate click
        floatingButton.click();
        
        // Console.error should be called
        expect(console.error).toHaveBeenCalledWith('Klaro is not loaded or available');
    });
    
    test('should use custom button text', function() {
        // Clean up and set custom text
        document.body.innerHTML = '';
        window.klaroConsentButtonData.buttonText = 'Custom Consent Text';
        
        // Re-initialize
        consentButtonModule.initConsentButtons();
        
        // The floating button should have custom text
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        expect(floatingButton.textContent).toBe('Custom Consent Text');
    });
    
    test('should apply success theme class', function() {
        // Clean up and set success theme
        document.body.innerHTML = '';
        window.klaroConsentButtonData.theme = 'success';
        
        // Re-initialize
        consentButtonModule.initConsentButtons();
        
        // The floating button should have success theme
        const floatingButton = document.querySelector('.klaro-consent-button');
        expect(floatingButton).not.toBeNull();
        expect(floatingButton.classList.contains('success')).toBe(true);
        expect(floatingButton.classList.contains('light')).toBe(false);
        expect(floatingButton.classList.contains('dark')).toBe(false);
    });
});