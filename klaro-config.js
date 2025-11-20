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
    "mustConsent": true,
    "acceptAll": true,
    "hideDeclineAll": false,
    "hideLearnMore": false,
    "noticeAsModal": true,
    "disablePoweredBy": false,
    "consent_mode": "none",
    "translations": {
        "zz": {
            "privacyPolicyUrl": "/privacy-policy/",
            "consentModal": {
                "title": "Privacy Settings",
                "description": "Here you can assess and customize the services that we'd like to use on this website. You're in charge! Enable or disable services as you see fit."
            },
            "consentNotice": {
                "title": "Privacy Settings",
                "changeDescription": "There were changes since your last visit, please update your consent.",
                "description": "We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.",
                "learnMore": "Learn more"
            },
            "acceptAll": "Accept all",
            "acceptSelected": "Accept selected",
            "decline": "Decline",
            "close": "Close",
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
            "purposeItem": {
                "service": "service",
                "services": "services"
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
            "ok": "OK",
            "save": "Save",
            "poweredBy": "Realized with Klaro!"
        }
    },
    "services": [
        {
            "name": "google-tag-manager",
            "purposes": [
                "functional"
            ],
            "cookies": [],
            "onInit": "",
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
            "onAccept": "",
            "onDecline": "",
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
            "onAccept": "",
            "onDecline": "",
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

// Push debug information to dataLayer with latest consent receipt
window.dataLayer = window.dataLayer || [];

// Get the latest consent receipt if available
var latestReceipt = null;
try {
    if (typeof getLatestConsentReceipt === 'function') {
        latestReceipt = getLatestConsentReceipt();
    } else {
        // Fallback if the function isn't loaded yet
        var existingData = window.localStorage.getItem('klaro_consent_receipts');
        if (existingData) {
            var receipts = JSON.parse(existingData);
            if (Array.isArray(receipts) && receipts.length > 0) {
                latestReceipt = receipts[receipts.length - 1];
            }
        }
    }
} catch (e) {
    console.error('Error retrieving latest consent receipt:', e);
}

// Create the dataLayer push object
var klaroConfigLoadedData = {
    'event': 'Klaro Event',
    'eventSource': 'klaro-geo',
    'klaroEventName': 'klaroConfigLoaded',
    'klaroGeoConsentTemplate': "",
    'klaroGeoTemplateSource': "fallback",
    'klaroGeoDetectedCountry': null,
    'klaroGeoDetectedRegion': null,
    'klaroGeoAdminOverride': false,
    'klaroGeoEnableConsentLogging': false
};

// Add the consent receipt if available
if (latestReceipt) {
    klaroConfigLoadedData.klaroGeoConsentReceipt = latestReceipt;
    console.log('Adding latest consent receipt to klaroConfigLoaded event:', latestReceipt.receipt_id);
}

// Push to dataLayer
window.dataLayer.push(klaroConfigLoadedData);

