// Detected/Debug Country Code: UK

var klaroConfig = {
    "version": 1,
    "elementID": "klaro",
    "styling": {
        "theme": [
            "light",
            "top",
            "wide"
        ]
    },
    "htmlTexts": true,
    "embedded": false,
    "groupByPurpose": true,
    "storageMethod": "cookie",
    "cookieName": "klaro",
    "cookieExpiresAfterDays": 365,
    "default": false,
    "required": false,
    "mustConsent": false,
    "acceptAll": true,
    "hideDeclineAll": false,
    "hideLearnMore": false,
    "noticeAsModal": false,
    "additionalClass": "",
    "disablePoweredBy": false,
    "translations": {
        "zz": {
            "privacyPolicyUrl": "/privacy-policy/",
            "consentModal": {
                "title": "Privacy Settings",
                "description": "Here you can assess and customize the services that we'd like to use on this website. You're in charge! Enable or disable services as you see fit."
            },
            "consentNotice": {
                "title": "Cookie Notice",
                "description": "Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.",
                "changeDescription": "There were changes since your last visit, please renew your consent.",
                "learnMore": "Let me choose"
            },
            "acceptAll": "Accept all",
            "acceptSelected": "Accept selected",
            "decline": "I decline",
            "ok": "That's ok",
            "close": "Close",
            "save": "Save",
            "privacyPolicy": {
                "name": "privacy policy",
                "text": "To learn more, please read our {privacyPolicy}."
            },
            "purposes": {
                "functional": {
                    "title": "Functional",
                    "description": "These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise."
                },
                "analytics": {
                    "title": "Analytics",
                    "description": "These services process personal information to help us understand how visitors interact with the website."
                },
                "advertising": {
                    "title": "Advertising",
                    "description": "These services process personal information to show you personalized or interest-based advertisements."
                }
            },
            "purposeItem": {
                "service": "service",
                "services": "services"
            }
        }
    },
    "services": [
        {
            "name": "test-service",
            "required": false,
            "default": false,
            "purposes": [
                "analytics"
            ],
            "cookies": [],
            "onInit": "",
            "onAccept": "",
            "onDecline": ""
        }
    ]
};

// ===== END OF KLARO CONFIG =====

// Initialize consent defaults (if needed)
// Global variable to store the current Klaro opts
window.currentKlaroOpts = null;

// Function to handle consent updates and trigger dataLayer events
function handleConsentUpdate(manager, eventType, data) {
    console.log('Klaro consent update:', eventType, data);

    if (eventType === 'saveConsents') {
        // Get the current consents
        var consents = manager.consents;
        console.log('Klaro consent data:', consents);

        // Store the current opts for use by the consent receipt handler
        window.currentKlaroOpts = { consents: consents };

        // Populate acceptedServices for dataLayer
        var acceptedServices = [];
        for (var serviceName in consents) {
            if (consents[serviceName] === true) {
                acceptedServices.push(serviceName);
            }
        }

        // Get custom template settings
        var customTemplateSettings = window.klaroConsentData || {};
        // Handle the consentMode which might be stored with quotes
        var consentMode = customTemplateSettings.consentMode || 'basic';
        // Remove quotes if they exist
        consentMode = consentMode.replace(/^['"]|['"]$/g, '');

        // Push to dataLayer
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'Klaro Consent',
            'acceptedServices': acceptedServices,
            'consentMode': consentMode,
            'consentType': data.type // 'save', 'accept', or 'decline'
        });

        // Set a timestamp to help prevent duplicate processing
        window.lastWatcherConsentTimestamp = Date.now();

        // Create a synthetic event object for the handleConsentChange function
        var syntheticEvent = {
            detail: {
                manager: manager
            }
        };

        // Call the existing handleConsentChange function from klaro-geo-consent-receipts.js
        if (typeof handleConsentChange === 'function') {
            console.log('Triggering handleConsentChange from watcher');
            handleConsentChange(syntheticEvent);
        } else {
            console.warn('handleConsentChange function not found. Make sure klaro-geo-consent-receipts.js is loaded.');
        }
    }
}

// Initialize the consent manager watcher when Klaro is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Klaro to be available
    var klaroWatcherInterval = setInterval(function() {
        if (window.klaro && typeof window.klaro.getManager === 'function') {
            clearInterval(klaroWatcherInterval);

            // Get the manager and set up the watcher
            var manager = window.klaro.getManager();
            if (manager) {
                manager.watch({
                    update: handleConsentUpdate
                });
                console.log('Klaro consent manager watcher initialized');
            }
        }
    }, 100);
});
// Push debug information to dataLayer
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    "event": "Klaro Config Loaded",
    "klaro_geo_consent_template": "default",
    "klaro_geo_template_source": "default",
    "klaro_geo_detected_country": "UK",
    "klaro_geo_detected_region": null,
    "klaro_geo_admin_override": true,
    "consentMode": "basic"
});

// Initialize gtag function if it doesn't exist
if (typeof gtag !== 'function') {
    function gtag(){dataLayer.push(arguments);}
}// Consent Receipt Configuration
window.klaroConsentData = {
    templateName: "default",
    templateSource: "default",
    detectedCountry: "UK",
    detectedRegion: "",
    adminOverride: true,
    ajaxUrl: "http://example.org/wp-admin/admin-ajax.php",
    nonce: "7aff2e5658",
    enableConsentLogging: true,
    consentMode: 'basic',
    templateSettings: {
        consentModalTitle: "Privacy Settings",
        consentModalDescription: "",
        acceptAllText: "Accept All",
        declineAllText: "Decline All",
        defaultConsent: false,
        requiredConsent: false
    }
};