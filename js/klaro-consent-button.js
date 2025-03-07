/**
 * Klaro Geo Consent Button JavaScript Tests
 */

describe('Klaro Consent Button', function() {
    
    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = `
            <div id="menu-container">
                <ul id="menu">
                    <li class="menu-item"><a href="#">Home</a></li>
                    <li class="menu-item"><a href="#">About</a></li>
                    <li class="menu-item"><a href="#" class="open-klaro-modal">Manage Consent Settings</a></li>
                </ul>
            </div>
        `;

        // Mock Klaro object
        window.klaro = {
            show: jest.fn()
        };

        // Clear any previous module cache
        jest.resetModules();
    });
    
    afterEach(function() {
        // Clean up
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete window.klaro;
        delete global.jQuery;
        delete global.$;
        delete global.clickHandler;
    });
    
    test('should register click handler for open-klaro-modal class', function() {
        // Create a completely new jQuery mock that tracks the document.on call
        let onCalled = false;
        let onEvent = '';
        let onSelector = '';
        
        // Create a new jQuery mock
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function(event, selector, handler) {
                        onCalled = true;
                        onEvent = event;
                        onSelector = selector;
                        global.clickHandler = handler;
                        return this;
                    }
                };
            }
            
            // Default object with common methods
            return {
                ready: function(callback) {
                    if (callback) callback();
                    return this;
                },
                on: function() { return this; }
            };
        };
        
        // Load the script
        require('../../js/klaro-consent-button.js');
        
        // The on method should be called with click and .open-klaro-modal
        expect(onCalled).toBe(true);
        expect(onEvent).toBe('click');
        expect(onSelector).toBe('.open-klaro-modal');
    });

    test('should show Klaro modal when open-klaro-modal link is clicked', function() {
        // Create a jQuery mock that stores the click handler
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function(event, selector, handler) {
                        global.clickHandler = handler;
                        return this;
                    }
                };
            }
            return {
                ready: function(callback) { if (callback) callback(); return this; },
                on: function() { return this; }
            };
        };
        
        // Load the script
        require('../../js/klaro-consent-button.js');
        
        // Create a mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler
        global.clickHandler(mockEvent);
        
        // preventDefault should be called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Klaro.show should be called
        expect(window.klaro.show).toHaveBeenCalled();
    });

    test('should handle case when Klaro is not loaded', function() {
        // Remove Klaro object
        delete window.klaro;
        
        // Create a jQuery mock that stores the click handler
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function(event, selector, handler) {
                        global.clickHandler = handler;
                        return this;
                    }
                };
            }
            return {
                ready: function(callback) { if (callback) callback(); return this; },
                on: function() { return this; }
            };
        };
        
        // Mock console.error
        console.error = jest.fn();
        
        // Load the script
        require('../../js/klaro-consent-button.js');
        
        // Create a mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler
        global.clickHandler(mockEvent);
        
        // preventDefault should be called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Console.error should be called
        expect(console.error).toHaveBeenCalledWith('Klaro is not initialized or show() function is missing.');
    });

    test('should handle case when Klaro is loaded but show method is missing', function() {
        // Set up Klaro without show method
        window.klaro = {};
        
        // Create a jQuery mock that stores the click handler
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function(event, selector, handler) {
                        global.clickHandler = handler;
                        return this;
                    }
                };
            }
            return {
                ready: function(callback) { if (callback) callback(); return this; },
                on: function() { return this; }
            };
        };
        
        // Mock console.error
        console.error = jest.fn();
        
        // Load the script
        require('../../js/klaro-consent-button.js');
        
        // Create a mock event
        const mockEvent = {
            preventDefault: jest.fn()
        };
        
        // Call the click handler
        global.clickHandler(mockEvent);
        
        // preventDefault should be called
        expect(mockEvent.preventDefault).toHaveBeenCalled();
        
        // Console.error should be called
        expect(console.error).toHaveBeenCalledWith('Klaro is not initialized or show() function is missing.');
    });
});