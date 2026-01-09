___TERMS_OF_SERVICE___

By creating or modifying this file you agree to Google Tag Manager's Community
Template Gallery Developer Terms of Service available at
https://developers.google.com/tag-manager/gallery-tos (or such other URL as
Google may provide), as modified from time to time.


___INFO___

{
  "type": "TAG",
  "id": "cvt_temp_public_id",
  "version": 1,
  "securityGroups": [],
  "displayName": "Klaro Geo Consent Mode",
  "description": "Integrates Klaro Geo consent management with Google Consent Mode. Sets default consent state and updates consent based on Klaro user choices.",
  "categories": ["TAG_MANAGEMENT", "UTILITY"],
  "brand": {
    "id": "brand_dummy",
    "displayName": "Klaro Geo",
    "thumbnail": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
  },
  "containerContexts": [
    "WEB"
  ]
}


___TEMPLATE_PARAMETERS___

[
  {
    "type": "SELECT",
    "name": "command",
    "displayName": "Command",
    "macrosInSelect": false,
    "selectItems": [
      {
        "value": "default",
        "displayValue": "Default (Consent Initialization)"
      },
      {
        "value": "update",
        "displayValue": "Update (Klaro Consent Update event)"
      }
    ],
    "simpleValueType": true,
    "help": "Use \"Default\" on the Consent Initialization trigger. Use \"Update\" on a trigger for the \"Klaro Consent Update\" event.",
    "defaultValue": "default"
  },
  {
    "type": "GROUP",
    "name": "serviceMappings",
    "displayName": "Service to Consent Type Mappings",
    "groupStyle": "ZIPPY_OPEN",
    "subParams": [
      {
        "type": "TEXT",
        "name": "analyticsService",
        "displayName": "Analytics Storage Service",
        "simpleValueType": true,
        "defaultValue": "google-analytics",
        "help": "Klaro service name that controls analytics_storage consent."
      },
      {
        "type": "TEXT",
        "name": "adService",
        "displayName": "Ad Storage Service",
        "simpleValueType": true,
        "defaultValue": "google-ads",
        "help": "Klaro service name that controls ad_storage, ad_user_data, and ad_personalization consent."
      }
    ]
  },
  {
    "type": "GROUP",
    "name": "defaultConsentGroup",
    "displayName": "Default Consent State",
    "groupStyle": "ZIPPY_OPEN",
    "subParams": [
      {
        "type": "SELECT",
        "name": "defaultAnalyticsStorage",
        "displayName": "analytics_storage",
        "macrosInSelect": false,
        "selectItems": [
          {
            "value": "denied",
            "displayValue": "denied"
          },
          {
            "value": "granted",
            "displayValue": "granted"
          }
        ],
        "simpleValueType": true,
        "defaultValue": "denied"
      },
      {
        "type": "SELECT",
        "name": "defaultAdStorage",
        "displayName": "ad_storage",
        "macrosInSelect": false,
        "selectItems": [
          {
            "value": "denied",
            "displayValue": "denied"
          },
          {
            "value": "granted",
            "displayValue": "granted"
          }
        ],
        "simpleValueType": true,
        "defaultValue": "denied"
      },
      {
        "type": "SELECT",
        "name": "defaultAdUserData",
        "displayName": "ad_user_data",
        "macrosInSelect": false,
        "selectItems": [
          {
            "value": "denied",
            "displayValue": "denied"
          },
          {
            "value": "granted",
            "displayValue": "granted"
          }
        ],
        "simpleValueType": true,
        "defaultValue": "denied"
      },
      {
        "type": "SELECT",
        "name": "defaultAdPersonalization",
        "displayName": "ad_personalization",
        "macrosInSelect": false,
        "selectItems": [
          {
            "value": "denied",
            "displayValue": "denied"
          },
          {
            "value": "granted",
            "displayValue": "granted"
          }
        ],
        "simpleValueType": true,
        "defaultValue": "denied"
      },
      {
        "type": "TEXT",
        "name": "waitForUpdate",
        "displayName": "Wait for Update (ms)",
        "simpleValueType": true,
        "defaultValue": "500",
        "help": "Time in milliseconds to wait for consent update before Google tags fire. Recommended: 500.",
        "valueValidators": [
          {
            "type": "POSITIVE_NUMBER"
          }
        ]
      }
    ],
    "enablingConditions": [
      {
        "paramName": "command",
        "paramValue": "default",
        "type": "EQUALS"
      }
    ]
  },
  {
    "type": "GROUP",
    "name": "customConsentTypes",
    "displayName": "Custom Consent Types",
    "groupStyle": "ZIPPY_OPEN",
    "subParams": [
      {
        "type": "CHECKBOX",
        "name": "enableCustomConsentTypes",
        "checkboxText": "Enable custom service-specific consent types",
        "simpleValueType": true,
        "defaultValue": true,
        "help": "When enabled, the template will also update consent types like google_analytics_consent and google_ads_consent based on Klaro service names."
      }
    ]
  },
  {
    "type": "GROUP",
    "name": "advancedSettings",
    "displayName": "Advanced Settings",
    "groupStyle": "ZIPPY_CLOSED",
    "subParams": [
      {
        "type": "CHECKBOX",
        "name": "adsDataRedaction",
        "checkboxText": "Redact ads data when ad_storage is denied",
        "simpleValueType": true,
        "defaultValue": true
      },
      {
        "type": "CHECKBOX",
        "name": "urlPassthrough",
        "checkboxText": "Pass ad click information through URLs",
        "simpleValueType": true,
        "defaultValue": false
      },
      {
        "type": "CHECKBOX",
        "name": "debugMode",
        "checkboxText": "Enable debug logging",
        "simpleValueType": true,
        "defaultValue": false
      }
    ]
  }
]


___SANDBOXED_JS_FOR_WEB_TEMPLATE___

// Klaro Geo Consent Mode Template
// Integrates Klaro consent management with Google Consent Mode

const setDefaultConsentState = require('setDefaultConsentState');
const updateConsentState = require('updateConsentState');
const copyFromDataLayer = require('copyFromDataLayer');
const createQueue = require('createQueue');
const gtagSet = require('gtagSet');
const logToConsole = require('logToConsole');
const getType = require('getType');
const makeInteger = require('makeInteger');

// Create dataLayer queue for pushing events
const dataLayerPush = createQueue('dataLayer');

// Debug logging helper
const debug = function(message, data) {
  if (data.debugMode) {
    logToConsole('[Klaro Geo GTM] ' + message);
  }
};

// Convert Klaro service name to consent key (e.g., "google-analytics" -> "google_analytics_consent")
const getServiceConsentKey = function(serviceName) {
  return serviceName.split('-').join('_') + '_consent';
};

// Main execution
if (data.command === 'default') {
  // DEFAULT COMMAND - fires on Consent Initialization trigger
  debug('Running default command', data);

  // Build default consent state
  const defaultConsent = {
    analytics_storage: data.defaultAnalyticsStorage,
    ad_storage: data.defaultAdStorage,
    ad_user_data: data.defaultAdUserData,
    ad_personalization: data.defaultAdPersonalization,
    wait_for_update: data.waitForUpdate ? makeInteger(data.waitForUpdate) : 500
  };

  // Add custom consent type defaults if enabled
  if (data.enableCustomConsentTypes) {
    // Add defaults for mapped services
    const analyticsKey = getServiceConsentKey(data.analyticsService);
    const adKey = getServiceConsentKey(data.adService);
    defaultConsent[analyticsKey] = data.defaultAnalyticsStorage;
    defaultConsent[adKey] = data.defaultAdStorage;
  }

  debug('Setting default consent state', data);
  setDefaultConsentState(defaultConsent);

  // Set additional gtag settings
  if (data.adsDataRedaction) {
    gtagSet('ads_data_redaction', true);
  }
  if (data.urlPassthrough) {
    gtagSet('url_passthrough', true);
  }

  // NOTE: We do NOT read the Klaro cookie here.
  // The plugin will fire a "Klaro Consent Update" event with the consent state
  // (either from cookie or from user interaction). This ensures:
  // 1. Only one consent default per page
  // 2. Only one consent update per page (from the Update tag)
  // 3. The plugin is the single source of truth for consent state

} else if (data.command === 'update') {
  // UPDATE COMMAND - fires on Klaro Consent Update event
  debug('Running update command', data);

  // Read consent state from the dataLayer event
  // The Klaro Geo plugin pushes events with consentMode and acceptedServices
  const consentMode = copyFromDataLayer('consentMode');
  const acceptedServices = copyFromDataLayer('acceptedServices');
  const triggerEvent = copyFromDataLayer('triggerEvent');

  debug('Consent mode from dataLayer received', data);
  debug('Trigger event: ' + triggerEvent, data);

  if (consentMode) {
    // The Klaro plugin already builds the consent object, use it directly
    debug('Updating consent state from Klaro event', data);
    updateConsentState(consentMode);

    // Push "Klaro Consent Update" event AFTER updateConsentState() is called
    // This event signals that consent mode is fully configured and ready
    // Use this as the trigger for GA4 and other tags that require consent
    debug('Pushing Klaro Consent Update event', data);
    dataLayerPush({
      'event': 'Klaro Consent Update',
      'consent_trigger': triggerEvent,
      'analytics_storage': consentMode.analytics_storage,
      'ad_storage': consentMode.ad_storage,
      'ad_user_data': consentMode.ad_user_data,
      'ad_personalization': consentMode.ad_personalization,
      'consentMode': consentMode,
      'acceptedServices': acceptedServices
    });
  } else {
    // Fallback: try to read from acceptedServices array
    debug('No consentMode found, trying acceptedServices fallback', data);

    if (acceptedServices && getType(acceptedServices) === 'array') {
      const consentUpdate = {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied'
      };

      // Check if services are in acceptedServices
      for (var i = 0; i < acceptedServices.length; i++) {
        const service = acceptedServices[i];

        if (service === data.analyticsService) {
          consentUpdate.analytics_storage = 'granted';
        }
        if (service === data.adService) {
          consentUpdate.ad_storage = 'granted';
          consentUpdate.ad_user_data = 'granted';
          consentUpdate.ad_personalization = 'granted';
        }

        // Add custom consent type
        if (data.enableCustomConsentTypes) {
          const customKey = getServiceConsentKey(service);
          consentUpdate[customKey] = 'granted';
        }
      }

      debug('Built consent update from acceptedServices', data);
      updateConsentState(consentUpdate);

      // Push "Klaro Consent Update" event AFTER updateConsentState() is called
      debug('Pushing Klaro Consent Update event (from fallback)', data);
      dataLayerPush({
        'event': 'Klaro Consent Update',
        'consent_trigger': triggerEvent,
        'analytics_storage': consentUpdate.analytics_storage,
        'ad_storage': consentUpdate.ad_storage,
        'ad_user_data': consentUpdate.ad_user_data,
        'ad_personalization': consentUpdate.ad_personalization,
        'consentMode': consentUpdate,
        'acceptedServices': acceptedServices
      });
    }
  }
}

// Signal tag completion
data.gtmOnSuccess();


___WEB_PERMISSIONS___

[
  {
    "instance": {
      "key": {
        "publicId": "logging",
        "versionId": "1"
      },
      "param": [
        {
          "key": "environments",
          "value": {
            "type": 1,
            "string": "debug"
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "access_consent",
        "versionId": "1"
      },
      "param": [
        {
          "key": "consentTypes",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "ad_storage"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "analytics_storage"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "ad_user_data"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "ad_personalization"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "google_tag_manager_consent"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "google_analytics_consent"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              },
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "consentType"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "google_ads_consent"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  }
                ]
              }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "read_data_layer",
        "versionId": "1"
      },
      "param": [
        {
          "key": "allowedKeys",
          "value": {
            "type": 1,
            "string": "any"
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "write_data_layer",
        "versionId": "1"
      },
      "param": [
        {
          "key": "keyPatterns",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 1,
                "string": "event"
              },
              {
                "type": 1,
                "string": "consent_trigger"
              },
              {
                "type": 1,
                "string": "analytics_storage"
              },
              {
                "type": 1,
                "string": "ad_storage"
              },
              {
                "type": 1,
                "string": "ads_data_redaction"
              },
              {
                "type": 1,
                "string": "url_passthrough"
              }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "access_globals",
        "versionId": "1"
      },
      "param": [
        {
          "key": "keys",
          "value": {
            "type": 2,
            "listItem": [
              {
                "type": 3,
                "mapKey": [
                  {
                    "type": 1,
                    "string": "key"
                  },
                  {
                    "type": 1,
                    "string": "read"
                  },
                  {
                    "type": 1,
                    "string": "write"
                  },
                  {
                    "type": 1,
                    "string": "execute"
                  }
                ],
                "mapValue": [
                  {
                    "type": 1,
                    "string": "dataLayer"
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": true
                  },
                  {
                    "type": 8,
                    "boolean": false
                  }
                ]
              }
            ]
          }
        }
      ]
    },
    "clientAnnotations": {
      "isEditedByUser": true
    },
    "isRequired": true
  }
]


___TESTS___

scenarios:
- name: Default command sets consent state
  code: |-
    const mockData = {
      command: 'default',
      cookieName: 'klaro',
      analyticsService: 'google-analytics',
      adService: 'google-ads',
      defaultAnalyticsStorage: 'denied',
      defaultAdStorage: 'denied',
      defaultAdUserData: 'denied',
      defaultAdPersonalization: 'denied',
      waitForUpdate: '500',
      enableCustomConsentTypes: true,
      adsDataRedaction: true,
      urlPassthrough: false,
      debugMode: false
    };

    // Run the tag
    runCode(mockData);

    // Verify success callback was called
    assertApi('gtmOnSuccess').wasCalled();
- name: Update command processes Klaro event
  code: |-
    const mockData = {
      command: 'update',
      analyticsService: 'google-analytics',
      adService: 'google-ads',
      enableCustomConsentTypes: true,
      firePageviewOnConsent: true,
      debugMode: false
    };

    // Mock the dataLayer values
    mock('copyFromDataLayer', function(key) {
      if (key === 'consentMode') {
        return {
          analytics_storage: 'granted',
          ad_storage: 'granted',
          ad_user_data: 'granted',
          ad_personalization: 'granted',
          google_analytics_consent: 'granted',
          google_ads_consent: 'granted'
        };
      }
      if (key === 'acceptedServices') {
        return ['google-analytics', 'google-ads'];
      }
      if (key === 'triggerEvent') {
        return 'saveConsents';
      }
      return null;
    });

    // Run the tag
    runCode(mockData);

    // Verify success callback was called
    assertApi('gtmOnSuccess').wasCalled();


___NOTES___

Created on 1/8/2026, 11:00:00 PM
Klaro Geo Consent Mode Template for Google Tag Manager
