<?php
/**
 * Default values for Klaro Geo
 *
 * This file contains functions that return default values for templates and services.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Get default templates for Klaro Geo
 *
 * @return array The default templates
 */
function klaro_geo_get_default_templates() {
    return [
        'default' => [
            'name' => 'Default Template',
            'description' => 'The default template used when no location-specific template is found',
            'config' => [
                'version' => 1,
                'elementID' => 'klaro',
                'styling' => [
                    'theme' => [
                        'color' => 'light',
                        'position' => 'top',
                        'width' => 'wide'
                    ]
                ],
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'cookieDomain' => '', // Empty string means auto-detect with leading dot for subdomain sharing
                'default' => false,
                'mustConsent' => true,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => true,
                'disablePoweredBy' => false,
                'consent_mode' => 'none',
                'translations' => [
                    'zz' => [
                        'privacyPolicyUrl' => '/privacy-policy/',
                        'consentModal' => [
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ],
                        'consentNotice' => [
                            'title' => 'Privacy Settings',
                            'changeDescription' => 'There were changes since your last visit, please update your consent.',
                            'description' => 'We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.',
                            'learnMore' => 'Learn more'
                        ],
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'close' => 'Close',
                        'purposes' => [
                            'functional' => [
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them as the website would not work properly.'
                            ],
                            'analytics' => [
                                'title' => 'Analytics',
                                'description' => 'These services allow us to analyze website usage to evaluate and improve its performance.'
                            ],
                            'advertising' => [
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized advertisements.'
                            ]
                        ],
                        'purposeItem' => [
                            'service' => 'service',
                            'services' => 'services'
                        ],
                        'service' => [
                            'disableAll' => [
                                'title' => 'Enable or disable all services',
                                'description' => 'Use this switch to enable or disable all services.'
                            ],
                            'optOut' => [
                                'title' => '(opt-out)',
                                'description' => 'This services is loaded by default (but you can opt out)'
                            ],
                            'required' => [
                                'title' => '(always required)',
                                'description' => 'This services is always required'
                            ],
                            'purpose' => 'purpose',
                            'purposes' => 'purposes',
                            'contextualConsent' => [
                                'description' => 'Would you like to consent to {title}?',
                                'acceptOnce' => 'Yes',
                                'acceptAlways' => 'Always'
                            ]
                        ],
                        'ok' => 'OK',
                        'save' => 'Save',
                        'decline' => 'Decline',
                        'poweredBy' => 'Realized with Klaro!'
                    ]
                ]
            ]
        ],
        'strict' => [
            'name' => 'Strict Opt-In',
            'description' => 'Requires explicit consent for all services (opt-in)',
            'config' => [
                'version' => 1,
                'elementID' => 'klaro',
                'styling' => [
                    'theme' => [
                        'color' => 'light',
                        'position' => 'top',
                        'width' => 'wide'
                    ]
                ],
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'cookieDomain' => '', // Empty string means auto-detect with leading dot for subdomain sharing
                'default' => false,
                'mustConsent' => true,
                'acceptAll' => true,
                'hideDeclineAll' => false,
                'hideLearnMore' => false,
                'noticeAsModal' => true,
                'disablePoweredBy' => false,
                'consent_mode' => 'none',
                'translations' => [
                    'zz' => [
                        'privacyPolicyUrl' => '/privacy-policy/',
                        'consentModal' => [
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ],
                        'consentNotice' => [
                            'title' => 'Privacy Settings',
                            'changeDescription' => 'There were changes since your last visit, please update your consent.',
                            'description' => 'We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.',
                            'learnMore' => 'Learn more'
                        ],
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'close' => 'Close',
                        'purposes' => [
                            'functional' => [
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them as the website would not work properly.'
                            ],
                            'analytics' => [
                                'title' => 'Analytics',
                                'description' => 'These services allow us to analyze website usage to evaluate and improve its performance.'
                            ],
                            'advertising' => [
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized advertisements.'
                            ]
                        ],
                        'purposeItem' => [
                            'service' => 'service',
                            'services' => 'services'
                        ],
                        'service' => [
                            'disableAll' => [
                                'title' => 'Enable or disable all services',
                                'description' => 'Use this switch to enable or disable all services.'
                            ],
                            'optOut' => [
                                'title' => '(opt-out)',
                                'description' => 'This services is loaded by default (but you can opt out)'
                            ],
                            'required' => [
                                'title' => '(always required)',
                                'description' => 'This services is always required'
                            ],
                            'purpose' => 'purpose',
                            'purposes' => 'purposes',
                            'contextualConsent' => [
                                'description' => 'Would you like to consent to {title}?',
                                'acceptOnce' => 'Yes',
                                'acceptAlways' => 'Always'
                            ]
                        ],
                        'ok' => 'OK',
                        'save' => 'Save',
                        'decline' => 'Decline',
                        'poweredBy' => 'Realized with Klaro!'
                    ]
                ]
            ]
        ],
        'relaxed' => [
            'name' => 'Relaxed Opt-Out',
            'description' => 'Assumes consent for all services (opt-out)',
            'config' => [
                'version' => 1,
                'elementID' => 'klaro',
                'styling' => [
                    'theme' => [
                        'color' => 'light',
                        'position' => 'top',
                        'width' => 'wide'
                    ]
                ],
                'htmlTexts' => true,
                'embedded' => false,
                'groupByPurpose' => true,
                'storageMethod' => 'cookie',
                'cookieName' => 'klaro',
                'cookieExpiresAfterDays' => 365,
                'cookieDomain' => '', // Empty string means auto-detect with leading dot for subdomain sharing
                'default' => true,
                'mustConsent' => false,
                'acceptAll' => true,
                'hideDeclineAll' => true,
                'hideLearnMore' => false,
                'noticeAsModal' => false,
                'disablePoweredBy' => false,
                'consent_mode' => 'none',
                'translations' => [
                    'zz' => [
                        'privacyPolicyUrl' => '/privacy-policy/',
                        'consentModal' => [
                            'title' => 'Privacy Settings',
                            'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                        ],
                        'consentNotice' => [
                            'title' => 'Privacy Settings',
                            'changeDescription' => 'There were changes since your last visit, please update your consent.',
                            'description' => 'We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.',
                            'learnMore' => 'Learn more'
                        ],
                        'acceptAll' => 'Accept all',
                        'acceptSelected' => 'Accept selected',
                        'decline' => 'I decline',
                        'close' => 'Close',
                        'purposes' => [
                            'functional' => [
                                'title' => 'Functional',
                                'description' => 'These services are essential for the correct functioning of this website. You cannot disable them as the website would not work properly.'
                            ],
                            'analytics' => [
                                'title' => 'Analytics',
                                'description' => 'These services allow us to analyze website usage to evaluate and improve its performance.'
                            ],
                            'advertising' => [
                                'title' => 'Advertising',
                                'description' => 'These services process personal information to show you personalized advertisements.'
                            ]
                        ],
                        'purposeItem' => [
                            'service' => 'service',
                            'services' => 'services'
                        ],
                        'service' => [
                            'disableAll' => [
                                'title' => 'Enable or disable all services',
                                'description' => 'Use this switch to enable or disable all services.'
                            ],
                            'optOut' => [
                                'title' => '(opt-out)',
                                'description' => 'This services is loaded by default (but you can opt out)'
                            ],
                            'required' => [
                                'title' => '(always required)',
                                'description' => 'This services is always required'
                            ],
                            'purpose' => 'purpose',
                            'purposes' => 'purposes',
                            'contextualConsent' => [
                                'description' => 'Would you like to consent to {title}?',
                                'acceptOnce' => 'Yes',
                                'acceptAlways' => 'Always'
                            ]
                        ],
                        'ok' => 'OK',
                        'save' => 'Save',
                        'decline' => 'Decline',
                        'poweredBy' => 'Realized with Klaro!'
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Get default services for Klaro Geo
 *
 * @param array $defaults Optional. Default values for callbacks.
 * @return array The default services
 */
function klaro_geo_get_default_services($defaults = null) {
    // Define the default services
    return [
        [
            "name" => "google-tag-manager",
            "title" => "Google Tag Manager",
            "required" => false,
            "purposes" => ["analytics", "advertising"],
            "default" => false,
            "cookies" => [],
            "onInit" => '',
            "onAccept" => '',
            "onDecline" => '',
            "translations" => [
                "zz" => [
                    "title" => "Google Tag Manager",
                    "description" => "Google Tag Manager is a tag management system that allows you to quickly and easily update tracking codes and related code fragments collectively known as tags on your website or mobile app."
                ]
            ]
        ],
        [
            "name" => "google-analytics",
            "title" => "Google Analytics",
            "required" => false,
            "purposes" => ["analytics"],
            "default" => false,
            "cookies" => [],
            "onInit" => '',
            "onAccept" => '',
            "onDecline" => '',
            "translations" => [
                "zz" => [
                    "title" => "Google Analytics",
                    "description" => "Google Analytics is a web analytics service that tracks and reports website traffic to help you understand how visitors interact with your website."
                ]
            ]
        ],
        [
            "name" => "google-ads",
            "title" => "Google Ads",
            "required" => false,
            "purposes" => ["advertising"],
            "default" => false,
            "cookies" => [],
            "onInit" => '',
            "onAccept" => '',
            "onDecline" => '',
            "translations" => [
                "zz" => [
                    "title" => "Google Ads",
                    "description" => "Google Ads is an online advertising platform developed by Google, where advertisers pay to display brief advertisements, service offerings, product listings, and video content to web users."
                ]
            ]
        ]
    ];
}