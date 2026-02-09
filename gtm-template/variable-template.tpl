___TERMS_OF_SERVICE___

By creating or modifying this file you agree to Google Tag Manager's Community
Template Gallery Developer Terms of Service available at
https://developers.google.com/tag-manager/gallery-tos (or such other URL as
Google may provide), as modified from time to time.


___INFO___

{
  "type": "MACRO",
  "id": "cvt_temp_public_id",
  "version": 1,
  "securityGroups": [],
  "displayName": "Klaro Geo Consent Gate",
  "description": "Returns the input value when the required consent type is granted, or a redacted value when denied. Use this to gate user data (email, phone, etc.) behind consent.",
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
    "type": "TEXT",
    "name": "inputValue",
    "displayName": "Input Value",
    "simpleValueType": true,
    "help": "The value to return when consent is granted. Use the variable picker to select a GTM variable (e.g., {{User Email}}).",
    "valueValidators": [
      {
        "type": "NON_EMPTY"
      }
    ]
  },
  {
    "type": "SELECT",
    "name": "consentType",
    "displayName": "Required Consent Type",
    "macrosInSelect": false,
    "selectItems": [
      {
        "value": "ad_user_data",
        "displayValue": "ad_user_data"
      },
      {
        "value": "ad_personalization",
        "displayValue": "ad_personalization"
      },
      {
        "value": "ad_storage",
        "displayValue": "ad_storage"
      },
      {
        "value": "analytics_storage",
        "displayValue": "analytics_storage"
      }
    ],
    "simpleValueType": true,
    "defaultValue": "ad_user_data",
    "help": "The consent type that must be granted for the input value to pass through."
  },
  {
    "type": "SELECT",
    "name": "redactedValue",
    "displayName": "Value When Denied",
    "macrosInSelect": false,
    "selectItems": [
      {
        "value": "undefined",
        "displayValue": "undefined (parameter not sent)"
      },
      {
        "value": "empty_string",
        "displayValue": "\"\" (empty string)"
      },
      {
        "value": "zero",
        "displayValue": "0 (zero)"
      },
      {
        "value": "null",
        "displayValue": "null"
      },
      {
        "value": "custom",
        "displayValue": "Custom..."
      }
    ],
    "simpleValueType": true,
    "defaultValue": "undefined",
    "help": "What to return when the required consent type is denied."
  },
  {
    "type": "TEXT",
    "name": "customRedactedValue",
    "displayName": "Custom Redacted Value",
    "simpleValueType": true,
    "help": "A custom string to return when consent is denied.",
    "enablingConditions": [
      {
        "paramName": "redactedValue",
        "paramValue": "custom",
        "type": "EQUALS"
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
        "name": "debugMode",
        "checkboxText": "Enable debug logging",
        "simpleValueType": true,
        "defaultValue": false
      }
    ]
  }
]


___SANDBOXED_JS_FOR_WEB_TEMPLATE___

// Klaro Geo Consent Gate Variable Template
// Returns the input value when consent is granted, or a redacted value when denied.

const isConsentGranted = require('isConsentGranted');
const logToConsole = require('logToConsole');
const makeNumber = require('makeNumber');

// Debug logging helper
const debug = function(message) {
  if (data.debugMode) {
    logToConsole('[Klaro Geo Consent Gate] ' + message);
  }
};

const consentType = data.consentType;
const inputValue = data.inputValue;

debug('Checking consent for: ' + consentType);

if (isConsentGranted(consentType)) {
  debug('Consent granted for ' + consentType + ', returning input value');
  return inputValue;
}

// Consent is denied — return the redacted value
debug('Consent denied for ' + consentType + ', returning redacted value');

const redactedValue = data.redactedValue;

if (redactedValue === 'undefined') {
  return undefined;
}

if (redactedValue === 'empty_string') {
  return '';
}

if (redactedValue === 'zero') {
  return makeNumber(0);
}

if (redactedValue === 'null') {
  return null;
}

if (redactedValue === 'custom') {
  return data.customRedactedValue;
}

// Fallback (should not reach here)
return undefined;


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
                    "string": "ad_user_data"
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
                    "boolean": false
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
                    "string": "ad_storage"
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
- name: Returns input value when consent is granted
  code: |-
    mock('isConsentGranted', function(type) {
      return true;
    });

    const mockData = {
      inputValue: 'user@example.com',
      consentType: 'ad_user_data',
      redactedValue: 'undefined',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo('user@example.com');
- name: Returns undefined when consent is denied (default)
  code: |-
    mock('isConsentGranted', function(type) {
      return false;
    });

    const mockData = {
      inputValue: 'user@example.com',
      consentType: 'ad_user_data',
      redactedValue: 'undefined',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo(undefined);
- name: Returns empty string when consent is denied
  code: |-
    mock('isConsentGranted', function(type) {
      return false;
    });

    const mockData = {
      inputValue: 'user@example.com',
      consentType: 'ad_user_data',
      redactedValue: 'empty_string',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo('');
- name: Returns zero when consent is denied
  code: |-
    mock('isConsentGranted', function(type) {
      return false;
    });

    const mockData = {
      inputValue: '99.99',
      consentType: 'ad_user_data',
      redactedValue: 'zero',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo(0);
- name: Returns null when consent is denied
  code: |-
    mock('isConsentGranted', function(type) {
      return false;
    });

    const mockData = {
      inputValue: 'user@example.com',
      consentType: 'ad_user_data',
      redactedValue: 'null',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo(null);
- name: Returns custom string when consent is denied
  code: |-
    mock('isConsentGranted', function(type) {
      return false;
    });

    const mockData = {
      inputValue: 'user@example.com',
      consentType: 'ad_user_data',
      redactedValue: 'custom',
      customRedactedValue: 'REDACTED',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo('REDACTED');
- name: Passes through falsy input when consent is granted
  code: |-
    mock('isConsentGranted', function(type) {
      return true;
    });

    const mockData = {
      inputValue: '',
      consentType: 'ad_user_data',
      redactedValue: 'undefined',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo('');
- name: Works with analytics_storage consent type
  code: |-
    mock('isConsentGranted', function(type) {
      assertThat(type).isEqualTo('analytics_storage');
      return true;
    });

    const mockData = {
      inputValue: 'some-tracking-id',
      consentType: 'analytics_storage',
      redactedValue: 'undefined',
      debugMode: false
    };

    const result = runCode(mockData);
    assertThat(result).isEqualTo('some-tracking-id');


___NOTES___

Created on 2/9/2026, 12:00:00 AM
Klaro Geo Consent Gate Variable Template for Google Tag Manager
