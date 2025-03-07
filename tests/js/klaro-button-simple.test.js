/**
 * Simple tests for the Klaro consent button functionality
 */

describe('Klaro Button', () => {
    let klaroButton;
    
    beforeEach(() => {
        // Set up document body
        document.body.innerHTML = `
            <a href="#" class="open-klaro-modal">Manage Cookies</a>
        `;
        
        // Mock Klaro
        window.klaro = {
            show: jest.fn()
        };
        
        // Load the mock module
        klaroButton = require('../../js/klaro-consent-button.mock.js');
    });
    
    afterEach(() => {
        // Clean up
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete window.klaro;
    });
    
    test('shows Klaro modal when clicked', () => {
        // Create mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler directly
        klaroButton.handleKlaroModalClick(mockEvent);
        
        // Verify preventDefault was called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Verify Klaro.show was called
        expect(window.klaro.show).toHaveBeenCalled();
    });
    
    test('handles missing Klaro object', () => {
        // Remove Klaro
        delete window.klaro;
        
        // Mock console.error
        console.error = jest.fn();
        
        // Create mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler directly
        klaroButton.handleKlaroModalClick(mockEvent);
        
        // Verify preventDefault was called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Verify error was logged
        expect(console.error).toHaveBeenCalledWith('Klaro is not initialized or show() function is missing.');
    });
    
    test('handles missing show method', () => {
        // Set up Klaro without show method
        window.klaro = {};
        
        // Mock console.error
        console.error = jest.fn();
        
        // Create mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler directly
        klaroButton.handleKlaroModalClick(mockEvent);
        
        // Verify preventDefault was called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Verify error was logged
        expect(console.error).toHaveBeenCalledWith('Klaro is not initialized or show() function is missing.');
    });
});