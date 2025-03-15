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

    // Create the consent receipt
    var consentReceipt = {
        receipt_id: receiptId,
        timestamp: Math.floor(Date.now() / 1000), // Unix timestamp
        consent_choices: {},
        template_name: window.klaroConsentTemplateName || 'default',
        template_source: window.klaroConsentTemplateSource || 'fallback',
        country_code: window.klaroDetectedCountry || null,
        region_code: window.klaroDetectedRegion || null,
        template_settings: window.klaroTemplateSettings || {}
    };

    // Add consent choices
    for (var serviceName in e.detail.manager.consents) {
        consentReceipt.consent_choices[serviceName] = e.detail.manager.consents[serviceName];
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
        sendReceiptToServer(consentReceipt);
    } else {
        console.log('Server-side consent logging is disabled for this template. Receipt stored locally only.');
    }

    // Push to dataLayer
    window.dataLayer.push({
        'event': 'klaro_geo_consent_receipt',
        'klaro_geo_consent_receipt': consentReceipt
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
    // Always try to send in test environment
    var ajaxUrl = window.klaroAjaxUrl || '/wp-admin/admin-ajax.php';

    // Create form data
    var formData = new FormData();
    formData.append('action', 'klaro_geo_store_consent_receipt');

    // Add nonce if available
    if (window.klaroNonce) {
        formData.append('nonce', window.klaroNonce);
    }

    formData.append('receipt_data', JSON.stringify(receipt));

    // Send the request
    return fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            console.log('Consent receipt stored successfully:', data);
        } else {
            console.error('Failed to store consent receipt:', data);
        }
        return data;
    })
    .catch(function(error) {
        console.error('Error sending consent receipt:', error);
        throw error;
    });
}

// Wait for Klaro to be initialized
document.addEventListener('klaro:ready', function(e) {
    // Get the klaro instance
    var klaro = e.detail.manager;

    // Initialize variables from PHP data
    if (typeof window.klaroConsentData !== 'undefined') {
        window.klaroConsentReceiptsEnabled = window.klaroConsentData.consentReceiptsEnabled || false;
        window.klaroConsentTemplateName = window.klaroConsentData.templateName || 'default';
        window.klaroConsentTemplateSource = window.klaroConsentData.templateSource || 'fallback';
        window.klaroDetectedCountry = window.klaroConsentData.detectedCountry || null;
        window.klaroDetectedRegion = window.klaroConsentData.detectedRegion || null;
        window.klaroTemplateSettings = window.klaroConsentData.templateSettings || {};
        window.klaroAjaxUrl = window.klaroConsentData.ajaxUrl || '';
        window.klaroNonce = window.klaroConsentData.nonce || '';

        console.log('Klaro consent receipts initialized with template: ' + window.klaroConsentTemplateName);
        console.log('Consent logging enabled: ' + (window.klaroConsentData.enableConsentLogging ? 'Yes' : 'No'));
    }
});

// Listen for consent changes
document.addEventListener('klaro:consent-change', handleConsentChange);

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        handleConsentChange,
        storeReceiptLocally,
        sendReceiptToServer
    };
}