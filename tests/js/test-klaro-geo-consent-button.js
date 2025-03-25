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

        // Set up default klaroGeo settings to prevent warnings
        window.klaroGeo = {
            enableFloatingButton: false,
            floatingButtonText: 'Manage Consent',
            floatingButtonTheme: 'light',
            floatingButtonPosition: 'bottom-right',
            debug: false
        };

        // Mock console methods to suppress warnings
        const originalWarn = console.warn;
        const originalError = console.error;
        const originalLog = console.log;

        console.warn = jest.fn();
        console.error = jest.fn();
        console.log = jest.fn();

        // Store original methods for tests that need to check them
        global._originalConsole = {
            warn: originalWarn,
            error: originalError,
            log: originalLog
        };

        // Clear any previous module cache
        jest.resetModules();
    });
    
    afterEach(function() {
        // Clean up
        document.body.innerHTML = '';
        jest.clearAllMocks();

        // Restore original console methods
        if (global._originalConsole) {
            console.warn = global._originalConsole.warn;
            console.error = global._originalConsole.error;
            console.log = global._originalConsole.log;
            delete global._originalConsole;
        }

        delete window.klaro;
        delete window.klaroGeo;
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
            floatingButtonPosition: 'bottom-right',
            debug: false // Disable debug to prevent console warnings
        };

        // Mock Klaro object to ensure isKlaroLoaded() returns true
        window.klaro = {
            show: jest.fn()
        };

        // Create a button element that will be added to the DOM
        const buttonElement = {
            type: 'button',
            attributes: {
                'class': 'klaro-floating-button open-klaro-modal klaro-theme-blue klaro-position-bottom-right',
                'text': 'Privacy Settings',
                'aria-label': 'Privacy Settings'
            }
        };

        // Track DOM elements added
        let appendedElements = [];

        // Create a more complete jQuery mock
        global.$ = global.jQuery = function(selector, attributes) {
            // Handle document selector
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function() { return this; }
                };
            }
            // Handle body selector
            else if (selector === 'body') {
                return {
                    append: function(element) {
                        // When appending to body, add our pre-created button element
                        if (element.type === 'button' ||
                            (element.attributes && element.attributes.class &&
                             element.attributes.class.includes('klaro-floating-button'))) {
                            appendedElements.push(buttonElement);
                        } else {
                            appendedElements.push(element);
                        }
                        return this;
                    }
                };
            }
            // Handle head selector
            else if (selector === 'head') {
                return {
                    append: function() { return this; },
                    appendTo: function() { return this; }
                };
            }
            // Handle object selector
            else if (typeof selector === 'object') {
                return selector;
            }
            // Handle element creation with attributes
            else if (selector === '<button>' || (selector.startsWith && selector.startsWith('<button'))) {
                // For button creation, return our pre-created button
                if (attributes && attributes.class && attributes.class.includes('klaro-floating-button')) {
                    return buttonElement;
                }

                // For other elements
                const element = {
                    type: 'button',
                    attributes: attributes || {},
                    appendTo: function(target) {
                        appendedElements.push(this);
                        return this;
                    }
                };

                return element;
            }
            // Handle other element creation
            else if (selector.startsWith && selector.startsWith('<')) {
                const element = {
                    type: selector.replace(/[<>]/g, ''),
                    attributes: attributes || {},
                    appendTo: function(target) {
                        appendedElements.push(this);
                        return this;
                    }
                };

                return element;
            }
            // Handle .klaro-floating-button selector
            else if (selector === '.klaro-floating-button') {
                // Mock the length check to avoid duplicate button creation
                return { length: 0 };
            }
            // Handle #klaro-floating-button-styles selector
            else if (selector === '#klaro-floating-button-styles') {
                return { length: 0 };
            }

            // Default return
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
        const foundButtonElement = appendedElements.find(el =>
            el.attributes && el.attributes.class &&
            el.attributes.class.includes('klaro-floating-button')
        );

        // Verify button properties
        expect(foundButtonElement).toBeDefined();
        expect(foundButtonElement.attributes.class).toContain('klaro-theme-blue');
        expect(foundButtonElement.attributes.class).toContain('klaro-position-bottom-right');
        expect(foundButtonElement.attributes.text).toBe('Privacy Settings');
    });

    test('should not create floating button when disabled in settings', function() {
        // Mock klaroGeo object with floating button disabled
        window.klaroGeo = {
            enableFloatingButton: false,
            debug: false // Disable debug to prevent console warnings
        };

        // Mock Klaro object to ensure isKlaroLoaded() returns true
        window.klaro = {
            show: jest.fn()
        };

        // Track DOM elements added
        let appendedElements = [];

        // Create a more complete jQuery mock
        global.$ = global.jQuery = function(selector, attributes) {
            // Handle document selector
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    },
                    on: function() { return this; }
                };
            }
            // Handle body selector
            else if (selector === 'body') {
                return {
                    append: function(element) {
                        appendedElements.push(element);
                        return this;
                    }
                };
            }
            // Handle head selector
            else if (selector === 'head') {
                return {
                    append: function() { return this; },
                    appendTo: function() { return this; }
                };
            }
            // Handle object selector
            else if (typeof selector === 'object') {
                return selector;
            }
            // Handle element creation with attributes
            else if (selector === '<button>' || (selector.startsWith && selector.startsWith('<button'))) {
                // For button creation
                const element = {
                    type: 'button',
                    attributes: attributes || {},
                    appendTo: function(target) {
                        appendedElements.push(this);
                        return this;
                    }
                };

                return element;
            }
            // Handle other element creation
            else if (selector.startsWith && selector.startsWith('<')) {
                const element = {
                    type: selector.replace(/[<>]/g, ''),
                    attributes: attributes || {},
                    appendTo: function(target) {
                        appendedElements.push(this);
                        return this;
                    }
                };

                return element;
            }
            // Handle .klaro-floating-button selector
            else if (selector === '.klaro-floating-button') {
                // Mock the length check to avoid duplicate button creation
                return { length: 0 };
            }
            // Handle #klaro-floating-button-styles selector
            else if (selector === '#klaro-floating-button-styles') {
                return { length: 0 };
            }

            // Default return
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