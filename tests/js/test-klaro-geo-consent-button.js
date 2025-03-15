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
        require('../../js/klaro-geo-consent-button.js');
        
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
        require('../../js/klaro-geo-consent-button.js');
        
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
        require('../../js/klaro-geo-consent-button.js');
        
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
        require('../../js/klaro-geo-consent-button.js');
        
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

    test('should create floating button when enabled in settings', function() {
        // Mock klaroGeo object with floating button enabled
        window.klaroGeo = {
            enableFloatingButton: true,
            floatingButtonText: 'Privacy Settings',
            floatingButtonTheme: 'blue',
            floatingButtonPosition: 'bottom-right'
        };
        
        // Track DOM elements added
        let appendedElements = [];
        
        // Create jQuery mock
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function() { return this; }
                };
            } else if (selector === 'body') {
                return {
                    append: function(element) {
                        appendedElements.push(element);
                        return this;
                    }
                };
            } else if (typeof selector === 'object') {
                return selector;
            } else if (selector.startsWith('<')) {
                // Creating a new element
                const element = {
                    type: selector.replace(/[<>]/g, ''),
                    attributes: {},
                    appendTo: function(target) {
                        appendedElements.push(this);
                        return this;
                    }
                };
                return element;
            }
            
            return {
                ready: function(callback) { if (callback) callback(); return this; },
                on: function() { return this; }
            };
        };
        
        // Load the script
        require('../../js/klaro-geo-consent-button.js');
        
        // Check if button was created
        expect(appendedElements.length).toBeGreaterThan(0);
        
        // Find the button element
        const buttonElement = appendedElements.find(el => 
            el.attributes && el.attributes.class && 
            el.attributes.class.includes('klaro-floating-button')
        );
        
        // Verify button properties
        expect(buttonElement).toBeDefined();
        expect(buttonElement.attributes.class).toContain('klaro-theme-blue');
        expect(buttonElement.attributes.class).toContain('klaro-position-bottom-right');
        expect(buttonElement.attributes.text).toBe('Privacy Settings');
    });

    test('should not create floating button when disabled in settings', function() {
        // Mock klaroGeo object with floating button disabled
        window.klaroGeo = {
            enableFloatingButton: false
        };
        
        // Track DOM elements added
        let appendedElements = [];
        
        // Create jQuery mock
        global.$ = global.jQuery = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function() { return this; }
                };
            } else if (selector === 'body') {
                return {
                    append: function(element) {
                        appendedElements.push(element);
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
        require('../../js/klaro-geo-consent-button.js');
        
        // Check that no button was created
        expect(appendedElements.length).toBe(0);
    });
});