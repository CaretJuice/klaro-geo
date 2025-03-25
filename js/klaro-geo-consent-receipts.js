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
    // Only proceed if consent receipts are enabled
    if (!window.klaroConsentReceiptsEnabled) {
        return;
    }

    // Generate a unique receipt ID
    var receiptId = 'receipt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    // Log the admin override value for debugging
    console.log('Admin override value:', window.klaroAdminOverride);
    console.log('Admin override type:', typeof window.klaroAdminOverride);

    // Create the consent receipt
    var consentReceipt = {
        receipt_id: receiptId,
        timestamp: Math.floor(Date.now() / 1000), // Unix timestamp
        consent_choices: {},
        template_name: window.klaroConsentTemplateName || 'default',
        template_source: window.klaroConsentTemplateSource || 'fallback',
        country_code: window.klaroDetectedCountry || null,
        region_code: window.klaroDetectedRegion || null,
        admin_override: window.klaroAdminOverride === true,
        template_settings: window.klaroTemplateSettings || {},
        klaro_config: window.klaroConfig || null
    };

    // Log the final admin override value
    console.log('Final admin override value:', consentReceipt.admin_override);

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
        ? window.klaroConsentData.enableConsentLogging
        : true; // Default to true if not set

    // Only send to server if consent logging is enabled
    if (enableConsentLogging) {
        sendReceiptToServer(consentReceipt)
            .catch(function(error) {
                console.error('Error from server when sending receipt:', error);
            });
    } else {
        console.log('Server-side consent logging is disabled for this template. Receipt stored locally only.');
    }

    // Push to dataLayer
    window.dataLayer.push({
        'event': 'klaro_geo_consent_receipt',
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
    var ajaxUrl = window.klaroAjaxUrl || '/wp-admin/admin-ajax.php';

    // Create form data
    var formData = new FormData();
    formData.append('action', 'klaro_geo_store_consent_receipt');

    // Add nonce if available
    if (window.klaroNonce) {
        formData.append('nonce', window.klaroNonce);
    }

    // Ensure admin_override is explicitly set to a boolean
    receipt.admin_override = receipt.admin_override === true;

    // Log the receipt data before sending
    console.log('Sending receipt data to server:', receipt);

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
        console.error('Error sending consent receipt:', error);
        // Don't throw the error further to prevent unhandled promise rejection
        return { success: false, error: error.message };
    });
}

// Wait for Klaro to be initialized
document.addEventListener('klaro:ready', function(e) {
    // Initialize variables from PHP data
    if (typeof window.klaroConsentData !== 'undefined') {
        window.klaroConsentReceiptsEnabled = window.klaroConsentData.consentReceiptsEnabled || false;
        window.klaroConsentTemplateName = window.klaroConsentData.templateName || 'default';
        window.klaroConsentTemplateSource = window.klaroConsentData.templateSource || 'fallback';
        window.klaroDetectedCountry = window.klaroConsentData.detectedCountry || null;
        window.klaroDetectedRegion = window.klaroConsentData.detectedRegion || null;
        // Ensure admin override is a proper boolean
        window.klaroAdminOverride = window.klaroConsentData.adminOverride === true;

        // Also check if the template source already indicates an admin override
        if (window.klaroConsentData.templateSource &&
            window.klaroConsentData.templateSource.indexOf('admin-override') === 0) {
            window.klaroAdminOverride = true;
            console.log('Setting admin override to true based on template source:',
                        window.klaroConsentData.templateSource);
        }
        window.klaroTemplateSettings = window.klaroConsentData.templateSettings || {};
        window.klaroAjaxUrl = window.klaroConsentData.ajaxUrl || '';
        window.klaroNonce = window.klaroConsentData.nonce || '';

        // Log the admin override value for debugging
        console.log('Initializing with admin override:', window.klaroAdminOverride);
        console.log('Admin override type:', typeof window.klaroAdminOverride);
        console.log('Full klaroConsentData:', window.klaroConsentData);
    } else {
        console.error('klaroConsentData is not defined. Consent receipts will not work properly.');
    }
});

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
    // Check if this event is too close to the previous one (within 3 seconds)
    var now = Date.now();
    if (now - lastConsentTimestamp < 3000) {
        console.log('Ignoring duplicate ' + eventName + ' event (too close to previous event)');
        return;
    }

    // Only process if we haven't already processed a consent change recently
    if (!consentChangeProcessed) {
        consentChangeProcessed = true;
        lastConsentTimestamp = now;

        console.log('Processing ' + eventName + ' event');

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

// Standard Klaro event
document.addEventListener('klaro:consent-change', function(e) {
    handleKlaroConsentEvent(e, 'klaro:consent-change');
});

// Alternative event name that might be used
document.addEventListener('consent-change', function(e) {
    handleKlaroConsentEvent(e, 'consent-change');
});

// Track which buttons we've already attached listeners to
var processedButtons = new WeakSet();

// Direct button click listeners for Klaro modal
function setupDirectButtonListeners() {
    console.log('Setting up direct button listeners');

    // Find all save/accept buttons in the Klaro modal
    var saveButtons = document.querySelectorAll('#klaro .cm-btn-success, #klaro .cm-btn-accept');

    // Also find buttons by their text content
    document.querySelectorAll('#klaro button').forEach(function(button) {
        if (button.textContent.includes('Save') || button.textContent.includes('That\'s ok') ||
            button.textContent.includes('Accept')) {
            saveButtons = Array.from(saveButtons).concat(button);
        }
    });

    saveButtons.forEach(function(button) {
        // Skip if we've already processed this button
        if (processedButtons.has(button)) {
            return;
        }

        // Mark this button as processed
        processedButtons.add(button);

        // Add a data attribute to mark this button
        button.setAttribute('data-klaro-geo-processed', 'true');

        button.addEventListener('click', function(e) {
            // We don't need to do anything here - the consent-change event will be fired by Klaro
            // and our event listener will handle it using the same consistent approach
            console.log('Button click detected - waiting for Klaro consent-change event');

            // The event listener will use window.currentKlaroOpts which is set by the Klaro callbacks
        });
    });

    // Also try to find the decline button
    var declineButtons = document.querySelectorAll('#klaro .cm-btn-danger, #klaro .cn-decline');

    // Also find buttons by their text content
    document.querySelectorAll('#klaro button').forEach(function(button) {
        if (button.textContent.includes('I decline') || button.textContent.includes('Decline')) {
            declineButtons = Array.from(declineButtons).concat(button);
        }
    });

    declineButtons.forEach(function(button) {
        // Skip if we've already processed this button
        if (processedButtons.has(button)) {
            return;
        }

        // Mark this button as processed
        processedButtons.add(button);

        // Add a data attribute to mark this button
        button.setAttribute('data-klaro-geo-processed', 'true');

        button.addEventListener('click', function(e) {
            // We don't need to do anything here - the consent-change event will be fired by Klaro
            // and our event listener will handle it using the same consistent approach
            console.log('Decline button click detected - waiting for Klaro consent-change event');

            // The event listener will use window.currentKlaroOpts which is set by the Klaro callbacks
        });
    });
}

// Set up event listeners to capture consent changes
// Set up button listeners when Klaro is ready
document.addEventListener('klaro:ready', function(e) {
    console.log('Klaro is ready, setting up button listeners');
    setupDirectButtonListeners();
});

// Also set up listeners when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up button listeners');
    setupDirectButtonListeners();
});

// And set up listeners if the page is already loaded
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log('Page already loaded, setting up button listeners');
    setupDirectButtonListeners();
}

// Add a function to manually trigger consent receipt handling
window.triggerKlaroConsentReceipt = function() {
    console.log('Manually triggering consent receipt handling');

    // If we have currentKlaroOpts, use that
    if (window.currentKlaroOpts) {
        console.log('Using currentKlaroOpts for manual consent receipt trigger');
        handleConsentChange();
        return 'Consent receipt handling triggered using currentKlaroOpts.';
    }

    // Otherwise, try to get consents from Klaro manager
    if (window.klaro && window.klaro.getManager) {
        var manager = window.klaro.getManager();
        if (manager && manager.consents) {
            console.log('Using klaro.getManager for manual consent receipt trigger');
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
        console.log('Using UI checkboxes for manual consent receipt trigger');
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
        template_name: window.klaroConsentTemplateName || 'default',
        template_source: 'test',
        country_code: window.klaroDetectedCountry || 'XX',
        region_code: window.klaroDetectedRegion || null,
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