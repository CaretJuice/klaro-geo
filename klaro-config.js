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
            "onInit": "window.dataLayer = window.dataLayer || []; window.gtag = function(){dataLayer.push(arguments)} gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'}) gtag('set', 'ads_data_redaction', true)",
            "onAccept": "if (opts.consents.analytics || opts.consents.advertising) { for(let k of Object.keys(opts.consents)){ if (opts.consents[k]){ let eventName = 'klaro-'+k+'-accepted'; dataLayer.push({'event': eventName}) } } }",
            "onDecline": "gtag('consent', 'update', {'analytics_storage': 'denied'});"
        }
    ]
};

// Push debug information to dataLayer
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    "event": "klaro_geo_klaro_config_loaded",
    "klaro_geo_consent_template": "default",
    "klaro_geo_template_source": "fallback",
    "klaro_geo_detected_country": null,
    "klaro_geo_detected_region": "-"
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
