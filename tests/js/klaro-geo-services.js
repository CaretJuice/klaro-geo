// klaro-geo-services.test.js
const { testServiceCookiesType, testServicePurposesType } = require('../../js/klaro-geo-services'); // Import your functions

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
                { service_cookies: [{'object': 1}]},
                {} // Missing service_cookies property
            ];

            invalidServices.forEach(service => {
                expect(testServiceCookiesType([service])).toBe(false);  // Array input to the function
            });
        });

        it('handles null/missing services gracefully', () => {
            expect(testServiceCookiesType(null)).toBe(false);
            expect(testServiceCookiesType(undefined)).toBe(false);
            expect(testServiceCookiesType([])).toBe(true); // Empty array *is* valid
        })
    });

    describe('testServicePurposesType', () => {
        it('should return true for valid service_purposes arrays', () => {
            const validServices = [
                { service_purposes: ["analytics", "marketing"] },
                { service_purposes: ["essential"] },
                { service_purposes: [] }, // Empty array is valid
                {} // Missing service_purposes property is also valid
            ];

            validServices.forEach(service => {
                expect(testServicePurposesType([service])).toBe(true);
            });
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
        })
    });
});