// Detected/Debug Country Code: 

var klaroConfig = {
    "version": "1",
    "elementID": "klaro",
    "styling": {
        "theme": [
            "light",
            "top",
            "wide"
        ]
    },
    "default": false,
    "required": true,
    "htmlTexts": true,
    "embedded": false,
    "noAutoLoad": false,
    "autoFocus": false,
    "groupByPurpose": true,
    "storageMethod": "cookie",
    "cookieName": "klaro",
    "cookieExpiresAfterDays": "365",
    "cookieDomain": "",
    "cookiePath": "/",
    "mustConsent": false,
    "acceptAll": true,
    "hideDeclineAll": false,
    "hideLearnMore": false,
    "showNoticeTitle": false,
    "showDescriptionEmptyStore": true,
    "disablePoweredBy": false,
    "additionalClass": "",
    "lang": "",
    "consent_mode_settings": {
        "initialize_consent_mode": true,
        "analytics_storage_service": "google-analytics",
        "ad_storage_service": "google-ads",
        "initialization_code": "window.dataLayer = window.dataLayer || [];\r\nwindow.gtag = function(){dataLayer.push(arguments)}\r\ngtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'})\r\ngtag('set', 'ads_data_redaction', true)"
    },
    "translations": {
        "zz": {
            "consentModal": {
                "title": "Privacy Settings",
                "description": "Here you can assess and customize the services that we'd like to use on this website. You're in charge! Enable or disable services as you see fit."
            },
            "consentNotice": {
                "title": "Privacy Settings",
                "description": "We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.",
                "changeDescription": "There were changes since your last visit, please update your consent.",
                "learnMore": "Learn more"
            },
            "privacyPolicyUrl": "/privacy-policy/",
            "privacyPolicy": {
                "name": "privacy policy",
                "text": "To learn more, please read our {privacyPolicy}."
            },
            "acceptAll": "Accept all",
            "acceptSelected": "Accept selected",
            "decline": "Decline",
            "ok": "OK",
            "save": "Save",
            "close": "Close",
            "poweredBy": "Realized with Klaro!",
            "purposes": {
                "functional": {
                    "title": "Functional",
                    "description": "These services are essential for the correct functioning of this website. You cannot disable them as the website would not work properly."
                },
                "analytics": {
                    "title": "Analytics",
                    "description": "These services allow us to analyze website usage to evaluate and improve its performance."
                },
                "advertising": {
                    "title": "Advertising",
                    "description": "These services process personal information to show you personalized advertisements."
                }
            },
            "service": {
                "disableAll": {
                    "title": "Enable or disable all services",
                    "description": "Use this switch to enable or disable all services."
                },
                "optOut": {
                    "title": "(opt-out)",
                    "description": "This services is loaded by default (but you can opt out)"
                },
                "required": {
                    "title": "(always required)",
                    "description": "This services is always required"
                },
                "purpose": "purpose",
                "purposes": "purposes",
                "contextualConsent": {
                    "description": "Would you like to consent to {title}?",
                    "acceptOnce": "Yes",
                    "acceptAlways": "Always"
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
            "name": "google-tag-manager",
            "purposes": [
                "functinal"
            ],
            "cookies": [],
            "onInit": "\nwindow.dataLayer = window.dataLayer || [];\r\nwindow.gtag = function(){dataLayer.push(arguments)}\r\ngtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'})\r\ngtag('set', 'ads_data_redaction', true)",
            "onAccept": "",
            "onDecline": "",
            "required": true,
            "default": true,
            "translations": {
                "zz": {
                    "title": "Google Tag Manager",
                    "description": "Google Tag Manager is a tag management system that allows you to quickly and easily update tracking codes and related code fragments collectively known as tags on your website or mobile app."
                }
            }
        },
        {
            "name": "google-analytics",
            "purposes": [
                "analytics"
            ],
            "cookies": [],
            "onInit": "",
            "onAccept": "\n// Note: Consent updates are now handled by klaro-geo-consent-mode.js\n\nconst adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\nconst adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\nif (adPersonalizationCheckbox) {\n    adPersonalizationCheckbox.checked = true;\n}\nif (adUserDataCheckbox) {\n    adUserDataCheckbox.checked = true;\n}\n// Remove disabled class from controls container\nconst controlsContainer = document.querySelector('.klaro-geo-ad-controls');\nif (controlsContainer) {\n    controlsContainer.classList.remove('klaro-geo-controls-disabled');\n}\n",
            "onDecline": "\n// Note: Consent updates are now handled by klaro-geo-consent-mode.js\n\nconst adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\nconst adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\nif (adPersonalizationCheckbox) {\n    adPersonalizationCheckbox.checked = false;\n}\nif (adUserDataCheckbox) {\n    adUserDataCheckbox.checked = false;\n}\n// Add disabled class to controls container\nconst controlsContainer = document.querySelector('.klaro-geo-ad-controls');\nif (controlsContainer) {\n    controlsContainer.classList.add('klaro-geo-controls-disabled');\n}\n",
            "required": false,
            "default": false,
            "translations": {
                "zz": {
                    "title": "Google Analytics",
                    "description": "Google Analytics is a web analytics service that tracks and reports website traffic to help you understand how visitors interact with your website."
                }
            }
        },
        {
            "name": "google-ads",
            "purposes": [
                "advertising"
            ],
            "cookies": [],
            "onInit": "",
            "onAccept": "\n// Note: Consent updates are now handled by klaro-geo-consent-mode.js\n\nconst adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\nconst adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\nif (adPersonalizationCheckbox) {\n    adPersonalizationCheckbox.checked = true;\n}\nif (adUserDataCheckbox) {\n    adUserDataCheckbox.checked = true;\n}\n// Remove disabled class from controls container\nconst controlsContainer = document.querySelector('.klaro-geo-ad-controls');\nif (controlsContainer) {\n    controlsContainer.classList.remove('klaro-geo-controls-disabled');\n}\n",
            "onDecline": "\n// Note: Consent updates are now handled by klaro-geo-consent-mode.js\n\nconst adPersonalizationCheckbox = document.querySelector('#klaro-geo-ad-personalization');\nconst adUserDataCheckbox = document.querySelector('#klaro-geo-ad-user-data');\nif (adPersonalizationCheckbox) {\n    adPersonalizationCheckbox.checked = false;\n}\nif (adUserDataCheckbox) {\n    adUserDataCheckbox.checked = false;\n}\n// Add disabled class to controls container\nconst controlsContainer = document.querySelector('.klaro-geo-ad-controls');\nif (controlsContainer) {\n    controlsContainer.classList.add('klaro-geo-controls-disabled');\n}\n",
            "required": false,
            "default": false,
            "translations": {
                "zz": {
                    "title": "Google Ads",
                    "description": "Google Ads is an online advertising platform developed by Google, where advertisers pay to display brief advertisements, service offerings, product listings, and video content to web users."
                }
            }
        }
    ]
};

// ===== END OF KLARO CONFIG =====

// Global variable to store the current Klaro opts
window.currentKlaroOpts = null;

// Function to handle consent updates and trigger dataLayer events
function handleConsentUpdate(manager, eventType, data) {
    console.log('Klaro consent update:', eventType, data);

    // -------------------- MERGED safeUpdateConsent LOGIC --------------------

    // Check if gtag is available (PHP needs to generate this check)
    if (typeof window.gtag !== 'function') {
        console.log('DEBUG: gtag not available, skipping consent update');
        return;
    }

    // Check for duplicate update (PHP needs to generate this check)
    if (window.lastConsentUpdate && 
        JSON.stringify(window.lastConsentUpdate) === JSON.stringify(data)) { // Assuming 'data' is the update
        console.log('DEBUG: Skipping duplicate consent update');
        return;
    }

    // Clear any pending update timer (PHP needs to manage this)
    if (window.consentUpdateTimer) {
        console.log('DEBUG: Clearing pending consent update timer');
        clearTimeout(window.consentUpdateTimer);
    }

    // Set a new timer to debounce (PHP needs to generate this)
    console.log('DEBUG: Setting debounced consent update timer');
    window.consentUpdateTimer = setTimeout(function() {

        // Get consent state from Klaro (PHP needs to generate this)
        let adServiceEnabled = false;
        let analyticsServiceEnabled = false;
        try {
            if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
                const klaroManager = window.klaro.getManager();
                if (klaroManager && klaroManager.consents && window.adStorageServiceName) {
                    adServiceEnabled = klaroManager.consents[window.adStorageServiceName] === true;
                    // ... (Get analyticsServiceEnabled similarly)
                }
            }
        } catch (e) {
            console.error('DEBUG: Error getting consent state from Klaro manager:', e);
            adServiceEnabled = data.ad_storage === 'granted'; // Fallback
            analyticsServiceEnabled = data.analytics_storage === 'granted'; // Fallback
        }

        // Create complete update (PHP needs to generate this)
        const completeUpdate = {
            'ad_storage': adServiceEnabled ? 'granted' : 'denied',
            'analytics_storage': analyticsServiceEnabled ? 'granted' : 'denied',
            'ad_user_data': (adServiceEnabled && window.adUserDataConsent) ? 'granted' : 'denied',
            'ad_personalization': (adServiceEnabled && window.adPersonalizationConsent) ? 'granted' : 'denied'
        };

        // Store last update (PHP needs to generate this)
        window.lastConsentUpdate = completeUpdate;

        // Send to gtag (PHP needs to generate this)
        window.gtag('consent', 'update', completeUpdate);
        console.log('DEBUG: Consent state updated with:', completeUpdate);

        // Reset timer (PHP needs to generate this)
        window.consentUpdateTimer = null;

    }, window.consentUpdateDelay || 50); // Default delay

    // -------------------- ORIGINAL handleConsentUpdate LOGIC --------------------

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

        // Push to dataLayer
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'Klaro Consent',
            'acceptedServices': acceptedServices,
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
    "klaro_geo_detected_country": null,
    "klaro_geo_detected_region": null,
    "klaro_geo_admin_override": false
});

// Consent Receipt Configuration
window.klaroConsentData = {
    templateName: "default",
    templateSource: "default",
    detectedCountry: "",
    detectedRegion: "",
    adminOverride: false,
    ajaxUrl: "http://localhost:8000/wp-admin/admin-ajax.php",
    nonce: "dd78634f91",
    enableConsentLogging: true,
    consentMode: "v2",
    templateSettings: {
        consentModalTitle: "Privacy Settings",
        consentModalDescription: "",
        acceptAllText: "Accept All",
        declineAllText: "Decline All",
        defaultConsent: false,
        requiredConsent: true,
        config: {
            consent_mode_settings: {
                initialize_consent_mode: true,
                analytics_storage_service: "google-analytics",
                ad_storage_service: "google-ads",
                initialization_code: `window.dataLayer = window.dataLayer || [];
window.gtag = function(){dataLayer.push(arguments)}
gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'})
gtag('set', 'ads_data_redaction', true)`
            }
        }
    }
};