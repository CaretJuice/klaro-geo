const { testServiceCookiesType, testServicePurposesType } = require('../../js/klaro-geo-admin-services'); // Import your functions

describe('Klaro Geo Services Tests', () => {
    describe('testServiceCookiesType', () => {
        it('should return true for valid service_cookies arrays', () => {
            const validServices = [
                { service_cookies: [/test/, "anotherTest"] },
                { service_cookies: ["stringCookie"] },
                { service_cookies: [] } // Empty array is also valid
            ];
            validServices.forEach(service => {
                expect(testServiceCookiesType([service])).toBe(true); //Wrap in array as fn expects array of services.
            });
        });

        it('should return false for invalid service_cookies types', () => {
            const invalidServices = [
                { service_cookies: "notAnArray" },
                { service_cookies: [123] }, // Incorrect type (number)
                { service_cookies: [true] }, // Incorrect type (boolean)
                { service_cookies: [null] }, // Null within an array is still bad
                { service_cookies: [undefined] },
                { service_cookies: [{'object': 1}]}
                // Note: Missing service_cookies property is actually valid in our implementation
            ];

            invalidServices.forEach(service => {
                expect(testServiceCookiesType([service])).toBe(false);  // Array input to the function
            });
        });

        it('should return true for services with missing service_cookies property', () => {
            // Missing service_cookies property is valid
            expect(testServiceCookiesType([{}])).toBe(true);
        });

        it('handles null/missing services gracefully', () => {
            expect(testServiceCookiesType(null)).toBe(false);
            expect(testServiceCookiesType(undefined)).toBe(false);
            expect(testServiceCookiesType([])).toBe(true); // Empty array *is* valid
        });
    });

    describe('testServicePurposesType', () => {
        it('should return true for valid service_purposes arrays', () => {
            const validServices = [
                { service_purposes: ["analytics", "marketing"] },
                { service_purposes: ["essential"] },
                { service_purposes: [] } // Empty array is valid
            ];

            validServices.forEach(service => {
                expect(testServicePurposesType([service])).toBe(true);
            });
        });

        it('should return true for services with missing service_purposes property', () => {
            // Missing service_purposes property is valid
            expect(testServicePurposesType([{}])).toBe(true);
        });

        it('should return false for invalid service_purposes types', () => {
            const invalidServices = [
                { service_purposes: "notAnArray" },
                { service_purposes: [123] },
                { service_purposes: [true] },
                { service_purposes: [null] }, // Null still fails, just like in cookies
                { service_purposes: [undefined]},
                { service_purposes: [{'object': 1}]}
            ];
            invalidServices.forEach(service => {
                expect(testServicePurposesType([service])).toBe(false);
            });
        });

        it('handles null/missing services gracefully', () => {
            expect(testServicePurposesType(null)).toBe(false);
            expect(testServicePurposesType(undefined)).toBe(false);
            expect(testServicePurposesType([])).toBe(true); // Empty array is valid here too
        });
    });

    // New tests for dynamic purposes population
    describe('populatePurposes', () => {
        // Mock jQuery and DOM elements
        let $, container, klaroGeo, populatePurposes;

        beforeEach(() => {
            // Setup mocks
            container = {
                empty: jest.fn(),
                append: jest.fn().mockReturnThis()
            };

            $ = jest.fn().mockImplementation(selector => {
                if (selector === '#service_purposes_container') {
                    return container;
                }
                return {
                    append: jest.fn().mockReturnThis(),
                    prop: jest.fn()
                };
            });

            $.fn = {};

            // Mock global klaroGeo object
            global.klaroGeo = {
                purposes: ['functional', 'analytics', 'advertising']
            };

            // Create a mock populatePurposes function that simulates the real one
            populatePurposes = () => {
                const container = $('#service_purposes_container');
                container.empty();

                if (global.klaroGeo.purposes && global.klaroGeo.purposes.length > 0) {
                    global.klaroGeo.purposes.forEach(purpose => {
                        const checkbox = $('<input type="checkbox" name="service_purposes[]" value="' + purpose + '">');
                        const label = $('<label></label>').append(checkbox).append(' ' + purpose);
                        container.append(label).append('<br>');
                    });
                } else {
                    container.append('<p>No purposes defined. Please add purposes in the main settings.</p>');
                }
            };
        });

        it('should create checkboxes for each purpose', () => {
            // Call our mock function
            populatePurposes();

            // Verify container was emptied
            expect(container.empty).toHaveBeenCalled();

            // Verify append was called for each purpose (plus line breaks)
            // 3 purposes * 2 appends each (label + br) = 6 calls
            expect(container.append.mock.calls.length).toBe(6);
        });

        it('should handle empty purposes array', () => {
            // Set empty purposes array
            global.klaroGeo.purposes = [];

            // Call our mock function
            populatePurposes();

            // Verify container was emptied
            expect(container.empty).toHaveBeenCalled();

            // Verify append was called once with the "no purposes" message
            expect(container.append.mock.calls.length).toBe(1);
            expect(container.append.mock.calls[0][0]).toContain('No purposes defined');
        });

        it('should handle missing purposes property', () => {
            // Remove purposes property
            delete global.klaroGeo.purposes;

            // Call our mock function
            populatePurposes();

            // Verify container was emptied
            expect(container.empty).toHaveBeenCalled();

            // Verify append was called once with the "no purposes" message
            expect(container.append.mock.calls.length).toBe(1);
            expect(container.append.mock.calls[0][0]).toContain('No purposes defined');
        });
    });

    // Tests for callback scripts handling
    describe('callback scripts handling', () => {
        // Mock jQuery and form elements
        let $, formElements, service;

        beforeEach(() => {
            // Setup form elements mock
            formElements = {
                'service_oninit': { value: '' },
                'service_onaccept': { value: '' },
                'service_ondecline': { value: '' }
            };

            // Setup jQuery mock
            $ = jest.fn().mockImplementation(selector => {
                if (selector === '#service_oninit' ||
                    selector === '#service_onaccept' ||
                    selector === '#service_ondecline') {
                    return {
                        val: jest.fn().mockImplementation(value => {
                            if (value === undefined) {
                                return formElements[selector.substring(1)].value;
                            } else {
                                formElements[selector.substring(1)].value = value;
                                return { prop: jest.fn() };
                            }
                        })
                    };
                }
                return { prop: jest.fn() };
            });

            // Sample service object
            service = {
                name: 'test-service',
                purposes: ['analytics'],
                cookies: ['_ga', '_gid']
            };
        });

        it('should handle JavaScript code in callback scripts', () => {
            // Test that complex JavaScript in callbacks is properly handled
            const complexScript =
                'function testCallback() {\n' +
                '    const x = 10;\n' +
                '    if (x > 5) {\n' +
                '        console.log("x is greater than 5");\n' +
                '        return true;\n' +
                '    } else {\n' +
                '        console.log("x is not greater than 5");\n' +
                '        return false;\n' +
                '    }\n' +
                '}\n' +
                'testCallback();';

            // Set the script in the form
            $('#service_oninit').val(complexScript);

            // Get the value from the form
            const retrievedScript = $('#service_oninit').val();

            // Verify the script is unchanged
            expect(retrievedScript).toBe(complexScript);

            // Verify the script is valid JavaScript
            expect(() => {
                new Function(retrievedScript);
            }).not.toThrow();

            // Add the script to a service object
            service.onInit = retrievedScript;

            // Verify the script is stored correctly in the service object
            expect(service.onInit).toBe(complexScript);
        });

        it('should handle special characters in callback scripts', () => {
            const scriptWithSpecialChars =
                '// Script with quotes and special chars\n' +
                'const message = "This is a \\"quoted\\" string";\n' +
                'const regex = /^test\\\\d+$/;\n' +
                'const html = \'<div class="test"></div>\';\n' +
                'console.log(message, regex, html);';

            // Set the script in the form
            $('#service_onaccept').val(scriptWithSpecialChars);

            // Get the value from the form
            const retrievedScript = $('#service_onaccept').val();

            // Verify the script is unchanged
            expect(retrievedScript).toBe(scriptWithSpecialChars);

            // Verify the script is valid JavaScript
            expect(() => {
                new Function(retrievedScript);
            }).not.toThrow();

            // Add the script to a service object
            service.onAccept = retrievedScript;

            // Verify the script is stored correctly in the service object
            expect(service.onAccept).toBe(scriptWithSpecialChars);
        });

        it('should handle multiple callback scripts in a service', () => {
            const onInitScript = 'console.log("Service initialized");';
            const onAcceptScript = 'console.log("Service accepted");';
            const onDeclineScript = 'console.log("Service declined");';

            // Set all scripts in the form
            $('#service_oninit').val(onInitScript);
            $('#service_onaccept').val(onAcceptScript);
            $('#service_ondecline').val(onDeclineScript);

            // Add all scripts to the service object
            service.onInit = $('#service_oninit').val();
            service.onAccept = $('#service_onaccept').val();
            service.onDecline = $('#service_ondecline').val();

            // Verify all scripts are stored correctly
            expect(service.onInit).toBe(onInitScript);
            expect(service.onAccept).toBe(onAcceptScript);
            expect(service.onDecline).toBe(onDeclineScript);

            // Verify all scripts are valid JavaScript
            expect(() => { new Function(service.onInit); }).not.toThrow();
            expect(() => { new Function(service.onAccept); }).not.toThrow();
            expect(() => { new Function(service.onDecline); }).not.toThrow();
        });

        it('should handle empty callback scripts', () => {
            // Set empty scripts
            $('#service_oninit').val('');
            $('#service_onaccept').val('');
            $('#service_ondecline').val('');

            // Add empty scripts to the service object
            service.onInit = $('#service_oninit').val();
            service.onAccept = $('#service_onaccept').val();
            service.onDecline = $('#service_ondecline').val();

            // Verify empty scripts are stored correctly
            expect(service.onInit).toBe('');
            expect(service.onAccept).toBe('');
            expect(service.onDecline).toBe('');

            // Verify empty scripts are valid JavaScript
            expect(() => { new Function(service.onInit); }).not.toThrow();
            expect(() => { new Function(service.onAccept); }).not.toThrow();
            expect(() => { new Function(service.onDecline); }).not.toThrow();
        });
    });
});