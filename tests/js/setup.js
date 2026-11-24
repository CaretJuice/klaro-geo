/**
 * Jest Setup File for Klaro Geo Tests
 * 
 * This file sets up the test environment for Jest.
 */

// Set up global variables that might be needed by the tests
// Don't override the entire process object, just add to process.env
process.env.NODE_ENV = 'test';

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

// klaro-geo.js depends on the events module (it is a script dependency in
// production), so load it for every test file. It only reads window state when
// its functions are called, so tests can still set up their own globals.
jest.requireActual('../../js/klaro-geo-events.js');
