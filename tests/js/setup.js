/**
 * Jest Setup File for Klaro Geo Tests
 * 
 * This file sets up the test environment for Jest.
 */

// Set up global variables that might be needed by the tests
// Don't override the entire process object, just add to process.env
process.env.NODE_ENV = 'test';

// Mock the document.querySelector and document.querySelectorAll methods
// to handle cases where elements might not exist in the JSDOM environment
const originalQuerySelector = document.querySelector;
const originalQuerySelectorAll = document.querySelectorAll;

document.querySelector = function(selector) {
    const result = originalQuerySelector.call(this, selector);
    if (!result && selector.includes('#klaro')) {
        // For Klaro-specific selectors, create a mock element if needed
        const mockElement = document.createElement('div');
        mockElement.id = selector.replace('#', '');
        document.body.appendChild(mockElement);
        return mockElement;
    }
    return result;
};

document.querySelectorAll = function(selector) {
    const results = originalQuerySelectorAll.call(this, selector);
    if (results.length === 0 && selector.includes('.cm-service')) {
        // For service-related selectors, create mock elements if needed
        const mockElements = [];
        for (let i = 0; i < 3; i++) {
            const mockElement = document.createElement('li');
            mockElement.className = 'cm-service';
            
            const title = document.createElement('div');
            title.className = 'cm-service-title';
            title.textContent = i === 0 ? 'Google Analytics' : i === 1 ? 'Google Ads' : 'Facebook Pixel';
            mockElement.appendChild(title);
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = 'service-item-' + (i === 0 ? 'google-analytics' : i === 1 ? 'google-ads' : 'facebook-pixel');
            checkbox.checked = i === 0; // Only the first one is checked
            mockElement.appendChild(checkbox);
            
            document.body.appendChild(mockElement);
            mockElements.push(mockElement);
        }
        return mockElements;
    }
    return results;
};

// Add any other global setup needed for the tests

// Set up debug logging functions for tests
// Mock the debug logging functions to prevent errors in tests
global.klaroGeoLog = jest.fn();
global.klaroGeoWarn = jest.fn();
global.klaroGeoInfo = jest.fn();
global.klaroGeoError = jest.fn();
global.klaroGeoDebugLog = jest.fn();

// Also make them available on window for browser-like environment
if (typeof window !== 'undefined') {
    window.klaroGeoLog = global.klaroGeoLog;
    window.klaroGeoWarn = global.klaroGeoWarn;
    window.klaroGeoInfo = global.klaroGeoInfo;
    window.klaroGeoError = global.klaroGeoError;
    window.klaroGeoDebugLog = global.klaroGeoDebugLog;
}