// Export functions for testing first, before any jQuery code
// These functions validate the structure of service cookies and purposes
function testServiceCookiesType(services) {
    // Handle null/undefined services
    if (!services) {
        return false;
    }

    // Empty array is valid
    if (Array.isArray(services) && services.length === 0) {
        return true;
    }

    // Check each service
    return services.every(service => {
        // If service_cookies is missing, that's fine
        if (!service.hasOwnProperty('service_cookies')) {
            return true;
        }

        // service_cookies must be an array
        if (!Array.isArray(service.service_cookies)) {
            return false;
        }

        // Each item in the array must be a string or RegExp
        return service.service_cookies.every(cookie => {
            // Check for null, undefined, or non-string/non-RegExp values
            if (cookie === null || cookie === undefined) {
                return false;
            }

            // Check type
            return typeof cookie === 'string' || cookie instanceof RegExp;
        });
    });
}

function testServicePurposesType(services) {
    // Handle null/undefined services
    if (!services) {
        return false;
    }

    // Empty array is valid
    if (Array.isArray(services) && services.length === 0) {
        return true;
    }

    // Check each service
    return services.every(service => {
        // If service_purposes is missing, that's fine
        if (!service.hasOwnProperty('service_purposes')) {
            return true;
        }

        // service_purposes must be an array
        if (!Array.isArray(service.service_purposes)) {
            return false;
        }

        // Each item in the array must be a string
        return service.service_purposes.every(purpose => {
            // Check for null, undefined, or non-string values
            if (purpose === null || purpose === undefined) {
                return false;
            }

            // Check type
            return typeof purpose === 'string';
        });
    });
}

// Make functions available for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        testServiceCookiesType,
        testServicePurposesType
    };
}

// Only run jQuery code if we're in a browser environment, not during testing
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        // Ensure klaroGeoServices is available
        if (typeof klaroGeoServices === 'undefined') {
            console.error('klaroGeoServices not defined');
            return;
        }

        // Debug log
        console.log('klaroGeoServices loaded:', klaroGeoServices);

    // Function to get language name from code
    function getLanguageName(langCode) {
        var langName;
        switch(langCode) {
            case 'en': langName = 'English'; break;
            case 'de': langName = 'German'; break;
            case 'fr': langName = 'French'; break;
            case 'es': langName = 'Spanish'; break;
            case 'it': langName = 'Italian'; break;
            case 'nl': langName = 'Dutch'; break;
            case 'pt': langName = 'Portuguese'; break;
            default: langName = 'Custom'; break;
        }
        return langName;
    }

    // Function to add language tab for service translations
    function addLanguageTab(langCode) {
        // Check if this language already exists
        if ($("#service-tab-" + langCode).length > 0) {
            return; // Tab already exists
        }

        var langName = getLanguageName(langCode);

        // Add new tab
        var newTab = $('<li><a href="#service-tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
        newTab.appendTo($(".translations-tabs-nav"));

        // Clone the fallback tab content as a starting point
        var newContent = $("#service-tab-zz").clone();
        newContent.attr('id', 'service-tab-' + langCode);
        newContent.find('h4').text(langName + ' Translations (' + langCode + ')');

        // Update all input names and IDs to use the new language code
        newContent.find('input, textarea').each(function() {
            var name = $(this).attr('name');
            var id = $(this).attr('id');

            name = name.replace('[zz]', '[' + langCode + ']');
            id = id.replace('_zz_', '_' + langCode + '_');

            $(this).attr('name', name);
            $(this).attr('id', id);
        });

        // Add the new tab content
        newContent.appendTo("#service-translations-tabs");

        // Refresh tabs
        $("#service-translations-tabs").tabs("refresh");
    }

    // Initialize translation tabs
    $("#service-translations-tabs").tabs();

    // Function to load languages from templates
    function loadLanguagesFromTemplates() {
        console.log('Loading languages from templates...');

        if (typeof window.klaroTemplates === 'undefined') {
            console.error('klaroTemplates not defined');
            return;
        }

        // Extract all language codes from templates
        var languageCodes = new Set(['zz']); // Always include fallback

        Object.values(window.klaroTemplates).forEach(function(template) {
            if (template.config && template.config.translations) {
                Object.keys(template.config.translations).forEach(function(langCode) {
                    if (langCode !== 'zz') { // Skip fallback as we already have it
                        languageCodes.add(langCode);
                    }
                });
            }
        });

        console.log('Found languages in templates:', Array.from(languageCodes));

        // Add tabs for each language
        languageCodes.forEach(function(langCode) {
            if (langCode !== 'zz') { // Skip fallback as we already have it
                addLanguageTab(langCode);
            }
        });
    }

    // Load languages from templates
    loadLanguagesFromTemplates();

    // Make the function available globally for debugging
    window.loadLanguagesFromTemplates = loadLanguagesFromTemplates;

    // Populate purposes checkboxes
    function populatePurposes() {
        var container = $('#service_purposes_container');
        container.empty();

        if (klaroGeoServices.purposes && klaroGeoServices.purposes.length > 0) {
            klaroGeoServices.purposes.forEach(function(purpose) {
                var checkbox = $('<input type="checkbox" name="service_purposes[]" value="' + purpose + '">');
                var label = $('<label></label>').append(checkbox).append(' ' + purpose);
                container.append(label).append('<br>');
            });
        } else {
            container.append('<p>No purposes defined. Please add purposes in the main settings.</p>');
        }
    }

    // Add New Service button click
    $('#add-new-service').click(function() {
        $('#service_index').val('');
        $('#service_name').val('');
        $('#service_required').val('false');
        $('#service_default').val('false');
        
        // Clear purposes
        $('#service_purposes_container input[type="checkbox"]').prop('checked', false);
        
        // Clear cookies
        $('.cookie-group').remove();
        
        // Clear advanced settings
        $('#service_optout').prop('checked', false);
        $('#service_onlyonce').prop('checked', false);
        $('#service_contextual').prop('checked', false);

        // Clear callback scripts
        $('#service_oninit').val('');
        $('#service_onaccept').val('');
        $('#service_ondecline').val('');
        
        // Show the form
        $('#service-form-container').show();
        
        // Populate purposes
        populatePurposes();
    });

    // Edit Service button click
    $(document).on('click', '.edit-service', function() {
        var index = $(this).data('index');
        var service = klaroGeoServices.services[index];
        
        $('#service_index').val(index);
        $('#service_name').val(service.name || '');
        $('#service_required').val(service.required === true ? 'true' : (service.required === false ? 'false' : 'global'));
        $('#service_default').val(service.default === true ? 'true' : (service.default === false ? 'false' : 'global'));
        
        // Clear and repopulate purposes
        populatePurposes();
        
        // Check the appropriate purposes
        if (service.purposes && service.purposes.length > 0) {
            service.purposes.forEach(function(purpose) {
                $('#service_purposes_container input[value="' + purpose + '"]').prop('checked', true);
            });
        }
        
        // Clear and repopulate cookies
        $('.cookie-group').remove();
        
        if (service.cookies && service.cookies.length > 0) {
            service.cookies.forEach(function(cookie, i) {
                if (i > 0) {
                    $('.add-cookie-group').click();
                }
                
                if (typeof cookie === 'object') {
                    $('#cookie_name' + i).val(cookie.name || '');
                    $('#cookie_domain' + i).val(cookie.domain || '');
                    $('#cookie_path' + i).val(cookie.path || '');
                } else {
                    $('#cookie_name' + i).val(cookie);
                }
            });
        }
        
        // Set advanced settings
        $('#service_optout').prop('checked', service.optOut === true);
        $('#service_onlyonce').prop('checked', service.onlyOnce === true);
        $('#service_contextual').prop('checked', service.contextualConsentOnly === true);

        // Set callback scripts
        $('#service_oninit').val(service.onInit || '');
        $('#service_onaccept').val(service.onAccept || '');
        $('#service_ondecline').val(service.onDecline || '');

        // Clear existing translation fields
        $('input[name^="service_translations"], textarea[name^="service_translations"]').val('');

        // Set translations if they exist
        if (service.translations) {
            // Handle existing languages
            Object.keys(service.translations).forEach(function(langCode) {
                var langTranslation = service.translations[langCode];

                // No need to skip any language - we want to allow all languages from templates

                // If this is a language we don't have a tab for yet (except zz), create one
                if (langCode !== 'zz' && !$("#service-tab-" + langCode).length) {
                    // Use our addLanguageTab function to create the tab
                    addLanguageTab(langCode);
                }

                // Set the values for this language
                $('#service_translations_' + langCode + '_title').val(langTranslation.title || '');
                $('#service_translations_' + langCode + '_description').val(langTranslation.description || '');

                // Set nested values if they exist
                if (langTranslation.optOut) {
                    $('#service_translations_' + langCode + '_optOut_title').val(langTranslation.optOut.title || '(opt-out)');
                    $('#service_translations_' + langCode + '_optOut_description').val(langTranslation.optOut.description || 'This services is loaded by default (but you can opt out)');
                }

                if (langTranslation.required) {
                    $('#service_translations_' + langCode + '_required_title').val(langTranslation.required.title || '(always required)');
                    $('#service_translations_' + langCode + '_required_description').val(langTranslation.required.description || 'This services is always required');
                }

                $('#service_translations_' + langCode + '_purpose').val(langTranslation.purpose || 'purpose');
                $('#service_translations_' + langCode + '_purposes').val(langTranslation.purposes || 'purposes');
            });
        }

        // Show the form
        $('#service-form-container').show();
    });

    // Delete Service button click
    $(document).on('click', '.delete-service', function() {
        if (!confirm('Are you sure you want to delete this service?')) {
            return;
        }
        
        var index = $(this).data('index');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_klaro_service',
                index: index,
                _wpnonce: klaroGeoServices.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to delete service: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            }
        });
    });

    // Add Cookie Group button click
    $(document).on('click', '.add-cookie-group', function() {
        var index = parseInt($(this).data('index')) + 1;
        $(this).data('index', index);
        
        var cookieGroup = $('<div class="cookie-group" data-index="' + index + '"></div>');
        cookieGroup.append('<label for="cookie_name' + index + '">Cookie Name:</label><br>');
        cookieGroup.append('<input type="text" id="cookie_name' + index + '" name="cookie_name[]"><br>');
        cookieGroup.append('<label for="cookie_domain' + index + '">Cookie Domain:</label><br>');
        cookieGroup.append('<input type="text" id="cookie_domain' + index + '" name="cookie_domain[]"><br>');
        cookieGroup.append('<label for="cookie_path' + index + '">Cookie Path:</label><br>');
        cookieGroup.append('<input type="text" id="cookie_path' + index + '" name="cookie_path[]"><br>');
        cookieGroup.append('<button type="button" class="button remove-cookie-group">Remove</button><br><br>');
        
        $('#service_cookies_container' + (index - 1)).after(cookieGroup);
        cookieGroup.attr('id', 'service_cookies_container' + index);
    });

    // Remove Cookie Group button click
    $(document).on('click', '.remove-cookie-group', function() {
        $(this).closest('.cookie-group').remove();
    });

    // Form submission
    $('#service-form').submit(function(e) {
        e.preventDefault();
        
        var index = $('#service_index').val();
        var name = $('#service_name').val();
        var required = $('#service_required').val();
        var defaultValue = $('#service_default').val();
        
        // Get selected purposes
        var purposes = [];
        $('#service_purposes_container input:checked').each(function() {
            purposes.push($(this).val());
        });
        
        // Get cookies
        var cookies = [];
        $('.cookie-group').each(function(i) {
            var cookieName = $('#cookie_name' + i).val();
            var cookieDomain = $('#cookie_domain' + i).val();
            var cookiePath = $('#cookie_path' + i).val();
            
            if (cookieName) {
                if (cookieDomain || cookiePath) {
                    cookies.push({
                        name: cookieName,
                        domain: cookieDomain,
                        path: cookiePath
                    });
                } else {
                    cookies.push(cookieName);
                }
            }
        });
        
        // Get advanced settings
        var optOut = $('#service_optout').prop('checked');
        var onlyOnce = $('#service_onlyonce').prop('checked');
        var contextualConsentOnly = $('#service_contextual').prop('checked');

        // Get callback scripts
        var onInit = $('#service_oninit').val();
        var onAccept = $('#service_onaccept').val();
        var onDecline = $('#service_ondecline').val();

        // Collect translations
        var translations = {};
        var hasTranslations = false;

        // Get all language tabs
        $("#service-translations-tabs .translation-tab").each(function() {
            var tabId = $(this).attr('id');
            if (!tabId || tabId === 'service-tab-add') return; // Skip the "Add Language" tab

            var langCode = tabId.replace('service-tab-', '');
            var langTranslation = {};

            // Get basic fields
            var title = $('#service_translations_' + langCode + '_title').val();
            var description = $('#service_translations_' + langCode + '_description').val();

            if (title || description) {
                langTranslation.title = title;
                langTranslation.description = description;
                hasTranslations = true;
            }

            // Get opt-out fields
            var optOutTitle = $('#service_translations_' + langCode + '_optOut_title').val();
            var optOutDescription = $('#service_translations_' + langCode + '_optOut_description').val();

            if (optOutTitle || optOutDescription) {
                langTranslation.optOut = {
                    title: optOutTitle,
                    description: optOutDescription
                };
                hasTranslations = true;
            }

            // Get required fields
            var requiredTitle = $('#service_translations_' + langCode + '_required_title').val();
            var requiredDescription = $('#service_translations_' + langCode + '_required_description').val();

            if (requiredTitle || requiredDescription) {
                langTranslation.required = {
                    title: requiredTitle,
                    description: requiredDescription
                };
                hasTranslations = true;
            }

            // Get purpose fields
            var purpose = $('#service_translations_' + langCode + '_purpose').val();
            var purposes = $('#service_translations_' + langCode + '_purposes').val();

            if (purpose) {
                langTranslation.purpose = purpose;
                hasTranslations = true;
            }

            if (purposes) {
                langTranslation.purposes = purposes;
                hasTranslations = true;
            }

            // Add this language to translations if it has any values
            if (Object.keys(langTranslation).length > 0) {
                translations[langCode] = langTranslation;
            }
        });

        // Create service object
        var service = {
            name: name,
            required: required === 'true' ? true : (required === 'false' ? false : null),
            default: defaultValue === 'true' ? true : (defaultValue === 'false' ? false : null),
            purposes: purposes,
            cookies: cookies,
            onInit: onInit,
            onAccept: onAccept,
            onDecline: onDecline
        };

        // Add translations if we have any
        if (hasTranslations) {
            service.translations = translations;
        }

        // Add optional fields only if they're true
        if (optOut) service.optOut = true;
        if (onlyOnce) service.onlyOnce = true;
        if (contextualConsentOnly) service.contextualConsentOnly = true;
        
        // Update or add service
        if (index !== '') {
            klaroGeoServices.services[index] = service;
        } else {
            klaroGeoServices.services.push(service);
        }
        
        // Save services
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_klaro_services',
                services: JSON.stringify(klaroGeoServices.services),
                _wpnonce: klaroGeoServices.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to save service: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            }
        });
    });

    // Initialize
    populatePurposes();
    });
}