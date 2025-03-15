// Detected/Debug Country Code: 

var klaroConfig = {
    "version": 1,
    "elementID": "klaro",
    "styling": {
        "theme": {
            "color": "light",
            "position": "top",
            "width": "wide"
        }
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
    "translations": {
        "zz": {
            "privacyPolicyUrl": "/privacy",
            "consentModal": {
                "title": "Privacy Settings",
                "description": "Here you can assess and customize the services that we'd like to use on this website. You're in charge! Enable or disable services as you see fit."
            },
            "consentNotice": {
                "title": "Cookie Consent",
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
            "onDecline": "gtag('consent', 'update', {'analytics_storage': 'denied'});",
            "translations": {
                "zz": {
                    "title": "Google Tag Manager",
                    "description": ""
                }
            }
        },
        {
            "name": "google-analytics",
            "required": false,
            "default": false,
            "purposes": [
                "analytics"
            ],
            "cookies": [],
            "onInit": "",
            "onAccept": "gtag('consent', 'update', {'analytics_storage': 'granted'});",
            "onDecline": "gtag('consent', 'update', {'analytics_storage': 'denied'});",
            "translations": {
                "zz": {
                    "title": "Google Analytics",
                    "description": "",
                    "optOut": {
                        "title": "(opt-out)",
                        "description": "This services is loaded by default (but you can opt out)"
                    },
                    "required": {
                        "title": "(always required)",
                        "description": "This services is always required"
                    },
                    "purpose": "purpose",
                    "purposes": "purposes"
                },
                "fr": {
                    "title": "Analytics de Google",
                    "description": "",
                    "optOut": {
                        "title": "(opt-out)",
                        "description": "This services is loaded by default (but you can opt out)"
                    },
                    "required": {
                        "title": "(always required)",
                        "description": "This services is always required"
                    },
                    "purpose": "purpose",
                    "purposes": "purposes"
                },
                "de": {
                    "optOut": {
                        "title": "(opt-out)",
                        "description": "This services is loaded by default (but you can opt out)"
                    },
                    "required": {
                        "title": "(always required)",
                        "description": "This services is always required"
                    },
                    "purpose": "purpose",
                    "purposes": "purposes"
                },
                "es": {
                    "optOut": {
                        "title": "(opt-out)",
                        "description": "This services is loaded by default (but you can opt out)"
                    },
                    "required": {
                        "title": "(always required)",
                        "description": "This services is always required"
                    },
                    "purpose": "purpose",
                    "purposes": "purposes"
                }
            }
        }
    ]
};

// ===== END OF KLARO CONFIG =====

// Push debug information to dataLayer
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    "event": "klaro_geo_klaro_config_loaded",
    "klaro_geo_consent_template": "default",
    "klaro_geo_template_source": "default",
    "klaro_geo_detected_country": null,
    "klaro_geo_detected_region": null
});

// Initialize gtag function
function gtag(){dataLayer.push(arguments);}

// Set default consent state
gtag('consent', 'default', {
    'ad_storage': 'denied',
    'analytics_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied'
});

// Enable data redaction by default
gtag('set', 'ads_data_redaction', true);

// Function to handle consent updates
function handleConsentUpdate(type, granted) {
    gtag('consent', 'update', {
        [type]: granted ? 'granted' : 'denied'
    });
}
// Consent Receipt Configuration
window.klaroConsentReceiptsEnabled = true;
window.klaroConsentTemplateName = "default";
window.klaroConsentTemplateSource = "default";
window.klaroDetectedCountry = "";
window.klaroDetectedRegion = "";
window.klaroAjaxUrl = "http://localhost:8000/wp-admin/admin-ajax.php";
window.klaroNonce = "ee78bc95dc";

// Template settings for consent receipt
window.klaroTemplateSettings = {
    consentModalTitle: "Privacy Settings",
    consentModalDescription: "",
    acceptAllText: "Accept All",
    declineAllText: "Decline All",
    defaultConsent: false,
    requiredConsent: false
};

// Basic dataLayer push for consent changes
document.addEventListener('klaro:consent-change', function(e) {
    // Create a simple consent receipt for dataLayer
    var consentReceipt = {
        'event': 'klaro_geo_consent_receipt',
        'klaro_geo_consent_timestamp': new Date().toISOString(),
        'klaro_geo_consent_choices': {}
    };

    // Add each service consent choice to the receipt
    for (var serviceName in e.detail.manager.consents) {
        consentReceipt.klaro_geo_consent_choices[serviceName] = e.detail.manager.consents[serviceName];
    }

    // Push the consent receipt to the dataLayer
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(consentReceipt);
});
