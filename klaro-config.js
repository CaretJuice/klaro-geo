// Detected/Debug Country Code: 

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
            "name": "google-tag-manager",
            "required": false,
            "default": false,
            "purposes": [
                "analytics",
                "advertising"
            ],
            "cookies": [],
            "onInit": "window.dataLayer = window.dataLayer || []; window.gtag = function() { dataLayer.push(arguments); }; gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'}); gtag('set', 'ads_data_redaction', true);",
            "onAccept": "if (opts.consents.analytics || opts.consents.advertising) {     for(let k of Object.keys(opts.consents)){         if (opts.consents[k]){             let eventName = 'klaro-'+k+'-accepted';             dataLayer.push({'event': eventName});         }     } }",
            "onDecline": "// Store the current opts for use by other scripts\nwindow.currentKlaroOpts = opts;\n\n// Initialize dataLayer if it doesn't exist\nwindow.dataLayer = window.dataLayer || [];\n\n// Set up consent updates for Google Consent Mode\nconst consentUpdates = {\n    'ad_storage': 'denied',\n    'analytics_storage': 'denied',\n    'ad_user_data': 'denied',\n    'ad_personalization': 'denied'\n};\n\n// Get any remaining accepted services from opts.consents\nconst acceptedServices = [];\nfor (let k of Object.keys(opts?.consents || {})) {\n    if (opts?.consents?.[k]) {\n        acceptedServices.push(k);\n    }\n}\n\n// Push to dataLayer\ndataLayer.push({\n    'event': 'Klaro Consent',\n    'acceptedServices': acceptedServices\n});\n\n// Update Google Consent Mode\ngtag('consent', 'update', consentUpdates);"
        }
    ]
};

// ===== END OF KLARO CONFIG =====


// Global variable to store the current Klaro opts
window.currentKlaroOpts = null;

// Global consent update handler
document.addEventListener('klaro:consent-change', function(e) {
    // Get consents and services from the event detail
    var consents = e.detail.manager.consents || {};
    var services = e.detail.manager.services || [];
    var consentUpdates = {};
    console.log('Klaro consent change event:', e.detail);

    // Check for analytics consent
    var analyticsPurposes = ["analytics"];
    var hasAnalyticsConsent = false;
    // Check direct purpose consent
    analyticsPurposes.forEach(function(purpose) {
        if (consents[purpose] === true) {
            console.log('Analytics purpose granted directly:', purpose);
            hasAnalyticsConsent = true;
        }
    });

    // Check service-based consent
    if (!hasAnalyticsConsent) {
        services.forEach(function(service) {
            if (consents[service.name] === true && service.purposes) {
                // Check if this service has any analytics purposes
                var servicePurposes = Array.isArray(service.purposes) ? service.purposes : [service.purposes];
                var hasAnalyticsPurpose = servicePurposes.some(function(purpose) {
                    return analyticsPurposes.indexOf(purpose) !== -1;
                });
                if (hasAnalyticsPurpose) {
                    console.log('Analytics consent granted via service:', service.name);
                    hasAnalyticsConsent = true;
                }
            }
        });
    }
    consentUpdates['analytics_storage'] = hasAnalyticsConsent ? 'granted' : 'denied';

    // Check for advertising consent
    var adPurposes = ["advertising"];
    var hasAdConsent = false;
    // Check direct purpose consent
    adPurposes.forEach(function(purpose) {
        if (consents[purpose] === true) {
            console.log('Ad purpose granted directly:', purpose);
            hasAdConsent = true;
        }
    });

    // Check service-based consent
    if (!hasAdConsent) {
        services.forEach(function(service) {
            if (consents[service.name] === true && service.purposes) {
                // Check if this service has any ad purposes
                var servicePurposes = Array.isArray(service.purposes) ? service.purposes : [service.purposes];
                var hasAdPurpose = servicePurposes.some(function(purpose) {
                    return adPurposes.indexOf(purpose) !== -1;
                });
                if (hasAdPurpose) {
                    console.log('Ad consent granted via service:', service.name);
                    hasAdConsent = true;
                }
            }
        });
    }
    consentUpdates['ad_storage'] = hasAdConsent ? 'granted' : 'denied';
    consentUpdates['ad_user_data'] = hasAdConsent ? 'granted' : 'denied';
    consentUpdates['ad_personalization'] = hasAdConsent ? 'granted' : 'denied';

    // Update consent state in Google Consent Mode
    if (Object.keys(consentUpdates).length > 0) {
        console.log('Updating Google consent mode with:', consentUpdates);
        gtag('consent', 'update', consentUpdates);
    }

    // Push consent data to dataLayer
    var acceptedServices = [];
    for (var serviceName in consents) {
        if (consents[serviceName] === true) {
            acceptedServices.push(serviceName);
        }
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event': 'Klaro Consent',
        'acceptedServices': acceptedServices
    });
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

// Initialize gtag function if it doesn't exist
if (typeof gtag !== 'function') {
    function gtag(){dataLayer.push(arguments);}
}

// Function to handle consent updates
function handleConsentUpdate(type, granted) {
    gtag('consent', 'update', {
        [type]: granted ? 'granted' : 'denied'
    });
}
// Consent Receipt Configuration
window.klaroConsentData = {
    consentReceiptsEnabled: true,
    templateName: "default",
    templateSource: "default",
    detectedCountry: "",
    detectedRegion: "",
    adminOverride: false,
    ajaxUrl: "http://localhost:8000/wp-admin/admin-ajax.php",
    nonce: "35a56da638",
    enableConsentLogging: true,
    templateSettings: {
        consentModalTitle: "Privacy Settings",
        consentModalDescription: "",
        acceptAllText: "Accept All",
        declineAllText: "Decline All",
        defaultConsent: false,
        requiredConsent: false
    }
};

// Note: We're not adding a klaro:consent-change event listener here
// because it's already handled in klaro-geo-consent-receipts.js
// This prevents duplicate event handling
