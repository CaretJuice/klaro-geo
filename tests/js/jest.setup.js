/**
 * Jest setup file for Klaro Geo tests
 * This file contains global mocks and setup code for all tests
 */

// We'll handle document.addEventListener mocking in each test

// Mock jQuery if needed
global.jQuery = jest.fn(() => {
  return {
    ready: jest.fn(callback => callback())
  };
});
global.$ = global.jQuery;

// Mock require function for loading scripts
global.require = jest.fn(path => {
  // This is a simplified mock - in a real setup you might want to actually load the modules
  try {
    // Try to actually require the module if it exists
    return jest.requireActual(path);
  } catch (e) {
    // If it doesn't exist, return an empty object
    return {};
  }
});

// We'll restore the original document.addEventListener in the individual tests
// instead of using afterAll here, as it's not available in the setup file