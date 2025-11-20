/**
 * Klaro Geo Consent Receipts
 *
 * Handles the client-side storage and submission of consent receipts
 */

// Make sure window.dataLayer exists
if (typeof window.dataLayer === 'undefined') {
    window.dataLayer = [];
}

// Function to handle consent changes
function handleConsentChange(e) {
    // Initialize klaroConsentData if it doesn't exist
    if (!window.klaroConsentData) {
        window.klaroConsentData = {
            enableConsentLogging: false,
            templateName: 'default',
            templateSource: 'fallback',
            detectedCountry: null,
            detectedRegion: null
        };
    }

    // Only proceed if consent receipts are enabled
    if (window.klaroConsentData.enableConsentLogging === "0" ||
        window.klaroConsentData.enableConsentLogging === false ||
        window.klaroConsentData.enableConsentLogging === undefined) {

        // Get consents from the event or opts
        var consents = {};

        // First try to get consents from the event
        if (e && e.detail && e.detail.manager && e.detail.manager.consents) {
            consents = e.detail.manager.consents;
        }
        // If we have opts available (from button click handlers), use that
        else if (typeof window.currentKlaroOpts !== 'undefined' && window.currentKlaroOpts?.consents) {
            consents = window.currentKlaroOpts.consents;
        }

        // Create a simple receipt for dataLayer
        var consentReceipt = {
            receipt_id: null,
            timestamp: Math.floor(Date.now() / 1000),
            template_name: window.klaroConsentData.templateName || 'default',
            template_source: window.klaroConsentData.templateSource || 'fallback',
            country_code: window.klaroConsentData.detectedCountry || null,
            region_code: window.klaroConsentData.detectedRegion || null,
            consent_choices: {}
        };

        // Add consent choices
        for (var k of Object.keys(consents || {})) {
            consentReceipt.consent_choices[k] = consents[k] === true;
        }

        // Push to dataLayer
        window.dataLayer.push({
            'event': 'Klaro Geo Consent Receipt',
            'klaro_geo_consent_receipt': consentReceipt
        });

        klaroGeoLog('Server-side consent logging is disabled for this template. Receipt stored locally only.');

        return;
    }

    // Generate a unique receipt ID
    var receiptId = 'receipt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    // Log the admin override value for debugging
    klaroGeoLog('Admin override value:', window.klaroConsentData.adminOverride);
    klaroGeoLog('Admin override type:', typeof window.klaroConsentData.adminOverride);

    // Create the consent receipt
    var consentReceipt = {
        receipt_id: receiptId,
        timestamp: Math.floor(Date.now() / 1000), // Unix timestamp
        consent_choices: {},
        template_name: window.klaroConsentData.templateName || 'default',
        template_source: window.klaroConsentData.templateSource || 'fallback',
        country_code: window.klaroConsentData.detectedCountry || null,
        region_code: window.klaroConsentData.detectedRegion || null,
        admin_override: window.klaroConsentData.adminOverride === true,
        template_settings: window.klaroConsentData.templateSettings || {},
        klaro_config: window.klaroConfig || null
    };

    // Log the final admin override value
    klaroGeoLog('Final admin override value:', consentReceipt.admin_override);

    // Get consents from the event or opts
    var consents = {};
    var opts = null;

    // First try to get consents from the event
    if (e && e.detail && e.detail.manager && e.detail.manager.consents) {
        consents = e.detail.manager.consents;
    }
    // If we have opts available (from button click handlers), use that
    else if (typeof window.currentKlaroOpts !== 'undefined' && window.currentKlaroOpts?.consents) {
        consents = window.currentKlaroOpts.consents;
    }
    // If not available, try to get from window.klaro
    else if (window.klaro && window.klaro.getManager && window.klaro.getManager().consents) {
        consents = window.klaro.getManager().consents;
    }
    // If still no consents, create a basic set based on the UI state
    else if (document.querySelector('#klaro')) {
        // Try to determine consent from the UI
        document.querySelectorAll('#klaro input[type="checkbox"]').forEach(function(checkbox) {
            if (checkbox.id && checkbox.id.startsWith('service-item-')) {
                var serviceName = checkbox.id.replace('service-item-', '');
                consents[serviceName] = checkbox.checked;
            }
        });
    }

    // Add consent choices to the receipt using the same pattern as our other code
    for (var k of Object.keys(consents || {})) {
        consentReceipt.consent_choices[k] = consents[k] === true;
    }

    // Store the receipt in localStorage
    storeReceiptLocally(consentReceipt);

    // Check if server-side consent logging is enabled
    var enableConsentLogging = typeof window.klaroConsentData !== 'undefined' &&
        typeof window.klaroConsentData.enableConsentLogging !== 'undefined'
        ? (window.klaroConsentData.enableConsentLogging !== "0" &&
           window.klaroConsentData.enableConsentLogging !== false)
        : true; // Default to true if not set

    // Only send to server if consent logging is enabled
    if (enableConsentLogging) {
        sendReceiptToServer(consentReceipt)
            .catch(function(error) {
                console.error('Error from server when sending receipt:', error);
            });
    } else {
        klaroGeoLog('Server-side consent logging is disabled for this template. Receipt stored locally only.');
    }

    // Push to dataLayer
    window.dataLayer.push({
        'event': 'Klaro Geo Consent Receipt',
        'klaro_geo_consent_receipt': consentReceipt,
        'klaro_geo_template_source': consentReceipt.template_source,
        'klaro_geo_admin_override': consentReceipt.admin_override
    });
}

/**
 * Store the consent receipt in localStorage
 */
function storeReceiptLocally(receipt) {
    try {
        // Get existing receipts
        var existingData = window.localStorage.getItem('klaro_consent_receipts');
        var receipts = [];

        if (existingData) {
            try {
                receipts = JSON.parse(existingData);
            } catch (parseError) {
                console.error('Failed to parse existing receipts:', parseError);
                receipts = [];
            }
        }

        // Ensure receipts is an array
        if (!Array.isArray(receipts)) {
            receipts = [];
        }

        // Add the new receipt
        receipts.push(receipt);

        // Limit to last 10 receipts
        if (receipts.length > 10) {
            receipts = receipts.slice(-10);
        }

        // Store back in localStorage
        window.localStorage.setItem('klaro_consent_receipts', JSON.stringify(receipts));
    } catch (e) {
        console.error('Failed to store consent receipt locally:', e);
    }
}

/**
 * Send the consent receipt to the server
 */
function sendReceiptToServer(receipt) {
    // Get the AJAX URL
    var ajaxUrl = window.klaroConsentData.ajaxUrl || '/wp-admin/admin-ajax.php';

    // Create form data
    var formData = new FormData();
    formData.append('action', 'klaro_geo_store_consent_receipt');

    // Add nonce if available
    if (window.klaroConsentData.nonce) {
        formData.append('nonce', window.klaroConsentData.nonce);
    }

    // Ensure admin_override is explicitly set to a boolean
    receipt.admin_override = receipt.admin_override === true;

    // Log the receipt data before sending
    klaroGeoLog('Sending receipt data to server:', receipt);

    // Stringify the receipt data
    formData.append('receipt_data', JSON.stringify(receipt));

    // Send the request
    return fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            // Handle non-OK responses
            if (typeof response.text === 'function') {
                return response.text().then(function(text) {
                    throw new Error('Server returned status ' + response.status + ': ' +
                        (text ? text.substring(0, 100) : 'No response text'));
                });
            } else {
                throw new Error('Server returned status ' + response.status);
            }
        }
        return response.json();
    })
    .then(function(data) {
        if (!data.success) {
            console.error('Failed to store consent receipt:', data);
        }
        return data;
    })
    .catch(function(error) {
        // In test environments, don't log errors
        if (process.env.NODE_ENV !== 'test') {
            console.error('Error sending consent receipt:', error);
        }
        // Don't throw the error further to prevent unhandled promise rejection
        return { success: false, error: error.message };
    });
}

// Initialize variables from PHP data when the script loads
(function() {
    // This initialization ensures that all required properties are set with proper defaults
    if (typeof window.klaroConsentData !== 'undefined') {
        // Handle enableConsentLogging - convert string "0" to false, string "1" to true
        if (window.klaroConsentData.enableConsentLogging === "0") {
            window.klaroConsentData.enableConsentLogging = false;
        } else if (window.klaroConsentData.enableConsentLogging === "1") {
            window.klaroConsentData.enableConsentLogging = true;
        } else {
            window.klaroConsentData.enableConsentLogging = window.klaroConsentData.enableConsentLogging || false;
        }

        window.klaroConsentData.templateName = window.klaroConsentData.templateName || 'default';
        window.klaroConsentData.templateSource = window.klaroConsentData.templateSource || 'fallback';
        window.klaroConsentData.detectedCountry = window.klaroConsentData.detectedCountry || null;
        window.klaroConsentData.detectedRegion = window.klaroConsentData.detectedRegion || null;
        // Ensure admin override is a proper boolean
        window.klaroConsentData.adminOverride = window.klaroConsentData.adminOverride === true;

        // Also check if the template source already indicates an admin override
        if (window.klaroConsentData.templateSource &&
            window.klaroConsentData.templateSource.indexOf('admin-override') === 0) {
            window.klaroConsentData.adminOverride = true;
            klaroGeoLog('Setting admin override to true based on template source:',
                        window.klaroConsentData.templateSource);
        }
        window.klaroConsentData.templateSettings = window.klaroConsentData.templateSettings || {};
        window.klaroConsentData.ajaxUrl = window.klaroConsentData.ajaxUrl || '';
        window.klaroConsentData.nonce = window.klaroConsentData.nonce || '';

        // Log the admin override value for debugging
        klaroGeoLog('Initializing with admin override:', window.klaroConsentData.adminOverride);
        klaroGeoLog('Admin override type:', typeof window.klaroConsentData.adminOverride);
        klaroGeoLog('Full klaroConsentData:', window.klaroConsentData);
    } else {
        console.error('klaroConsentData is not defined. Consent receipts will not work properly.');
    }
})();

// Track if we've already processed a consent change to prevent duplicates
var consentChangeProcessed = false;
var consentChangeTimeout = null;
var lastConsentTimestamp = 0;

// Function to reset the processed flag after a delay
function resetConsentChangeProcessed() {
    consentChangeProcessed = false;
}

// Function to handle Klaro consent change events
function handleKlaroConsentEvent(e, eventName) {
    var now = Date.now();

    // Check if this event might have been triggered by the watcher
    // If the timestamps are very close (within 500ms), it's likely a duplicate
    if (window.lastWatcherConsentTimestamp && now - window.lastWatcherConsentTimestamp < 500) {
        klaroGeoLog('Ignoring ' + eventName + ' event that appears to be triggered by the watcher');
        return;
    }

    // Only process if we haven't already processed a consent change recently
    // The consentChangeProcessed flag is reset after 2 seconds via setTimeout
    if (!consentChangeProcessed) {
        consentChangeProcessed = true;
        lastConsentTimestamp = now;

        klaroGeoLog('Processing ' + eventName + ' event');

        // Clear any existing timeout
        if (consentChangeTimeout) {
            clearTimeout(consentChangeTimeout);
        }

        // Set a timeout to reset the flag after 2 seconds
        consentChangeTimeout = setTimeout(resetConsentChangeProcessed, 2000);

        // Store the current opts from the event if available
        if (e && e.detail && e.detail.manager) {
            // We don't need to create a full opts object, just the consents
            window.currentKlaroOpts = { consents: e.detail.manager.consents || {} };
        }

        handleConsentChange(e);
    }
}

// Note: We're now primarily using the Klaro manager.watch() method to detect consent changes
// These event listeners are kept for backward compatibility, but they may be redundant
// with the new approach. If you notice duplicate consent processing, you may need to
// disable these event listeners.

// Standard Klaro event
document.addEventListener('klaro:consent-change', function(e) {
    klaroGeoLog('klaro:consent-change event detected - this may be redundant with manager.watch()');
    handleKlaroConsentEvent(e, 'klaro:consent-change');
});

// Alternative event name that might be used
document.addEventListener('consent-change', function(e) {
    klaroGeoLog('consent-change event detected - this may be redundant with manager.watch()');
    handleKlaroConsentEvent(e, 'consent-change');
});

// Note: We've removed the direct button click listeners since they're no longer needed
// with the Klaro manager.watch() approach. The manager.watch() method will reliably
// detect all consent changes, including those triggered by button clicks.

// We're keeping the DOMContentLoaded event listener in klaro-geo-config.js to initialize
// the Klaro manager watcher, which handles all consent changes in a more reliable way.

// Add a function to manually trigger consent receipt handling
window.triggerKlaroConsentReceipt = function() {
    klaroGeoLog('Manually triggering consent receipt handling');

    // If we have currentKlaroOpts, use that
    if (window.currentKlaroOpts) {
        klaroGeoLog('Using currentKlaroOpts for manual consent receipt trigger');
        handleConsentChange();
        return 'Consent receipt handling triggered using currentKlaroOpts.';
    }

    // Otherwise, try to get consents from Klaro manager
    if (window.klaro && window.klaro.getManager) {
        var manager = window.klaro.getManager();
        if (manager && manager.consents) {
            klaroGeoLog('Using klaro.getManager for manual consent receipt trigger');
            handleConsentChange({
                detail: { manager: manager }
            });
            return 'Consent receipt handling triggered using Klaro manager.';
        }
    }

    // Last resort: try to determine from UI
    var consents = {};
    var foundConsents = false;

    document.querySelectorAll('#klaro input[type="checkbox"]').forEach(function(checkbox) {
        if (checkbox.id && checkbox.id.startsWith('service-item-')) {
            var serviceName = checkbox.id.replace('service-item-', '');
            consents[serviceName] = checkbox.checked;
            foundConsents = true;
        }
    });

    if (foundConsents) {
        klaroGeoLog('Using UI checkboxes for manual consent receipt trigger');
        handleConsentChange({
            detail: { manager: { consents: consents } }
        });
        return 'Consent receipt handling triggered using UI checkboxes.';
    }

    console.error('Could not find any consent data to trigger receipt handling');
    return 'Failed to trigger consent receipt handling: No consent data found.';
};

// Add a test function to manually test the AJAX endpoint
window.testKlaroConsentReceipt = function() {
    // Create a test receipt
    var testReceipt = {
        receipt_id: 'test_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
        timestamp: Math.floor(Date.now() / 1000),
        consent_choices: { 'test-service': true },
        template_name: window.klaroConsentData.templateName || 'default',
        template_source: 'test',
        country_code: window.klaroConsentData.detectedCountry || 'XX',
        region_code: window.klaroConsentData.detectedRegion || null,
        template_settings: {}
    };

    // Try to send it to the server
    return sendReceiptToServer(testReceipt)
        .then(function(response) {
            return 'Test completed: ' + (response.success ? 'Success' : 'Failed');
        })
        .catch(function(error) {
            return 'Test failed: ' + error.message;
        });
};

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        handleConsentChange,
        storeReceiptLocally,
        sendReceiptToServer
    };
}