/**
 * Jest setup file for Klaro Geo tests
 * This file contains global mocks and setup code for all tests
 */

// Mock the document.addEventListener
document.addEventListener = jest.fn((event, callback) => {
  if (event === 'DOMContentLoaded') {
    callback();
  }
});

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
  return {};
});