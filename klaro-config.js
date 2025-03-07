// Detected/Debug Country Code: 

var klaroConfig = {
    "required": false,
    "acceptAll": true,
    "hideDeclineAll": false,
    "hideLearnMore": false,
    "noticeAsModal": false,
    "default": false,
    "translations": [],
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
            "onInit": "window.dataLayer = window.dataLayer || [];\nwindow.gtag = function() { dataLayer.push(arguments); };\ngtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'});\ngtag('set', 'ads_data_redaction', true);",
            "onAccept": "if (opts.consents.analytics || opts.consents.advertising) {\n    for(let k of Object.keys(opts.consents)){\n        if (opts.consents[k]){\n            let eventName = 'klaro-'+k+'-accepted';\n            dataLayer.push({'event': eventName});\n        }\n    }\n}",
            "onDecline": "gtag('consent', 'update', {'analytics_storage': 'denied'});"
        }
    ]
};

// ===== END OF KLARO CONFIG =====

// Push debug information to dataLayer
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    "event": "klaro_geo_klaro_config_loaded",
    "klaro_geo_consent_template": "default",
    "klaro_geo_template_source": "fallback",
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
window.klaroConsentTemplateSource = "fallback";
window.klaroDetectedCountry = "";
window.klaroDetectedRegion = "";
window.klaroAjaxUrl = "http://example.org/wp-admin/admin-ajax.php";
window.klaroNonce = "dcf4452bb4";

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
