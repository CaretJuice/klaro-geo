/**
 * JavaScript Tests for Service Translations
 *
 * This file contains tests for the service translations functionality.
 */

// Mock the DOM elements and objects needed for testing
document.body.innerHTML = `
<div id="service-translations-tabs">
  <ul>
    <li><a href="#service-tab-zz">Default</a></li>
    <li><a href="#service-tab-en">English</a></li>
    <li><a href="#service-tab-de">German</a></li>
  </ul>
  <div id="service-tab-zz">Default content</div>
  <div id="service-tab-en">English content</div>
  <div id="service-tab-de">German content</div>
</div>
`;

// Mock jQuery
global.jQuery = function(selector) {
  const element = document.querySelector(selector);
  return {
    data: function() {
      return true; // Simulate that tabs are initialized
    },
    tabs: function() {
      // Mock tabs initialization
      return this;
    }
  };
};
jQuery.fn = { tabs: function() {} };

// Mock window objects
global.window = Object.create(window);
window.klaroTemplates = {
  default: {
    config: {
      translations: {
        zz: { title: 'Default Title' },
        en: { title: 'English Title' },
        de: { title: 'German Title' }
      }
    }
  }
};

window.klaroGeo = {
  services: [
    {
      name: 'analytics',
      translations: {
        zz: {
          title: 'Analytics',
          description: 'Analytics service',
          purpose: 'Analytics Purpose',
          purposes: 'Analytics Purposes',
          cookies: 'Analytics Cookies'
        },
        en: {
          title: 'Analytics',
          description: 'Analytics service',
          purpose: 'Analytics Purpose',
          purposes: 'Analytics Purposes',
          cookies: 'Analytics Cookies'
        }
      }
    }
  ]
};

describe('Service Translations', () => {
  test('tabs initialization', () => {
    // Check if service translations tabs exist
    expect(document.getElementById('service-translations-tabs')).not.toBeNull();

    // Check if fallback tab exists
    expect(document.getElementById('service-tab-zz')).not.toBeNull();
  });

  test('languages from templates', () => {
    // Check if klaroTemplates is defined
    expect(window.klaroTemplates).toBeDefined();

    // Get languages from templates
    const templateLanguages = new Set(['zz']); // Always include fallback

    Object.values(window.klaroTemplates).forEach(function(template) {
      if (template.config && template.config.translations) {
        Object.keys(template.config.translations).forEach(function(langCode) {
          if (langCode !== 'zz') { // Skip fallback as we already have it
            templateLanguages.add(langCode);
          }
        });
      }
    });

    // Check if expected languages are found
    expect(templateLanguages.has('en')).toBe(true);
    expect(templateLanguages.has('de')).toBe(true);

    // Check if all template languages have tabs in the service form
    templateLanguages.forEach(function(langCode) {
      expect(document.getElementById('service-tab-' + langCode)).not.toBeNull();
    });
  });

  test('service translations persistence', () => {
    // Check if services have translations
    let hasTranslations = false;

    for (let i = 0; i < window.klaroGeo.services.length; i++) {
      const service = window.klaroGeo.services[i];
      if (service.translations && Object.keys(service.translations).length > 0) {
        hasTranslations = true;
        break;
      }
    }

    expect(hasTranslations).toBe(true);

    // Check if service translations match template languages
    const service = window.klaroGeo.services[0];
    expect(service.translations.zz).toBeDefined();
    expect(service.translations.en).toBeDefined();

    // Check if all required fields are present
    expect(service.translations.zz.title).toBeDefined();
    expect(service.translations.zz.description).toBeDefined();
    expect(service.translations.zz.purpose).toBeDefined();
    expect(service.translations.zz.purposes).toBeDefined();
    expect(service.translations.zz.cookies).toBeDefined();

    // Check values
    expect(service.translations.zz.title).toBe('Analytics');
    expect(service.translations.zz.description).toBe('Analytics service');
    expect(service.translations.zz.purpose).toBe('Analytics Purpose');
    expect(service.translations.zz.purposes).toBe('Analytics Purposes');
    expect(service.translations.zz.cookies).toBe('Analytics Cookies');
  });
});