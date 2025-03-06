[
    {
        "name": "google-tag-manager",
        "required": false,
        "purposes": ["analytics", "advertising"],
        "default": false,
        "onAccept": `
            // Check for consent to either analytics or advertising
            if (opts.consents.analytics || opts.consents.advertising) { 
                for(let k of Object.keys(opts.consents)){
                    if (opts.consents[k]){
                        let eventName = 'klaro-'+k+'-accepted'
                        dataLayer.push({'event': eventName})
                    }
                }
            }
        `,
        "onInit": `
            // initialization code here (will be executed only once per page-load)
            window.dataLayer = window.dataLayer || [];
            window.gtag = function(){dataLayer.push(arguments)}
            gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'})
            gtag('set', 'ads_data_redaction', true)
        `
    },
    {
        "name": "google-analytics",
        "cookies": [
            "/^_ga(_.*)?/"
        ],
        "purposes": ["analytics"],
        "default": false,
        "onAccept": `
            // we grant analytics storage
            gtag('consent', 'update', {
                'analytics_storage': 'granted',
            })
        `,
        "onDecline": `
            // we deny analytics storage
            gtag('consent', 'update', {
                'analytics_storage': 'denied',
            })
        `
    },
    {
        "name": "google-ads",
        "cookies": [],
        "default": false,
        "onAccept": `
            // we grant ad storage and personalization
            gtag('consent', 'update', {
                'ad_storage': 'granted',
                'ad_user_data': 'granted',
                'ad_personalization': 'granted'
            })
        `,
        "onDecline": `
            // we decline ad storage and personalization
            gtag('consent', 'update', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied'
            })
        `,
        "purposes": ["advertising"]
    }
]
