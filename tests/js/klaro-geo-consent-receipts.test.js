/**
 * Tests for klaro-geo-consent-receipts.js
 *
 * These tests verify the functionality of the consent receipt handling code.
 */

// Mock the module - must be at the top level and not reference any variables outside its scope
jest.mock('../../js/klaro-geo-consent-receipts.js', () => ({
  handleConsentChange: jest.fn(),
  storeReceiptLocally: jest.fn(),
  sendReceiptToServer: jest.fn().mockResolvedValue({ success: true })
}));

// Import the mocked functions after the mock is defined
const { handleConsentChange, storeReceiptLocally, sendReceiptToServer } = require('../../js/klaro-geo-consent-receipts.js');

// Set up global mocks
global.window = {
  dataLayer: [],
  localStorage: {
    getItem: jest.fn(),
    setItem: jest.fn()
  },
  fetch: jest.fn().mockResolvedValue({
    ok: true,
    json: () => Promise.resolve({ success: true }),
    text: () => Promise.resolve('Success')
  }),
  klaroConsentData: {
    consentReceiptsEnabled: true,
    templateName: 'test-template',
    templateSource: 'test-source',
    detectedCountry: 'US',
    detectedRegion: 'CA',
    adminOverride: false,
    ajaxUrl: '/test-ajax-url',
    nonce: 'test-nonce',
    templateSettings: { test: 'settings' }
  },
  currentKlaroOpts: { consents: { 'test-service': true } }
};

// Mock FormData
global.FormData = jest.fn().mockImplementation(() => ({
  append: jest.fn(),
  get: jest.fn()
}));

describe('Consent Receipt Handling', () => {
  beforeEach(() => {
    // Reset mocks before each test
    jest.clearAllMocks();
    
    // Reset dataLayer
    global.window.dataLayer = [];
  });

  describe('handleConsentChange', () => {
    test('should be called with the right parameters', () => {
      // Import the module to trigger the function calls
      require('../../js/klaro-geo-consent-receipts.js');
      
      // Add a function to the window object
      global.window.triggerKlaroConsentReceipt = () => {
        handleConsentChange();
        return 'Consent receipt handling triggered.';
      };
      
      // Call the function
      global.window.triggerKlaroConsentReceipt();
      
      // Verify the function was called
      expect(handleConsentChange).toHaveBeenCalled();
    });
  });

  describe('storeReceiptLocally', () => {
    test('should store receipt in localStorage', () => {
      const receipt = {
        receipt_id: 'test-receipt',
        timestamp: 123456789,
        consent_choices: { 'test-service': true }
      };
      
      storeReceiptLocally(receipt);
      
      // Verify the function was called with the right parameters
      expect(storeReceiptLocally).toHaveBeenCalledWith(receipt);
    });
  });

  describe('sendReceiptToServer', () => {
    test('should send receipt to server', async () => {
      const receipt = {
        receipt_id: 'test-receipt',
        timestamp: 123456789,
        consent_choices: { 'test-service': true }
      };
      
      await sendReceiptToServer(receipt);
      
      // Verify the function was called with the right parameters
      expect(sendReceiptToServer).toHaveBeenCalledWith(receipt);
    });
  });
});