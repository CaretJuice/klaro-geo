/**
 * Klaro Geo Template Translations Tabs
 *
 * This file handles the tabbed interface for template translations.
 */
(function($) {
    // Wait for the DOM to be ready
    $(document).ready(function() {
        klaroGeoLog('Klaro Geo Template Translations Tabs loaded');

        // Check if the page was just updated (after form submission)
        let urlParams = new URLSearchParams(window.location.search);
        let wasUpdated = urlParams.get('updated') === 'true';

        // After a save, bring the tabs back in line with the JSON editor.
        setTimeout(function() {
            // If the page was just updated, also explicitly update the form from JSON
            // This ensures translations are loaded from the JSON editor
            if (wasUpdated && $('#translations_json_editor').length) {
                klaroGeoLog('Page was updated, explicitly loading translations from JSON');
                setTimeout(function() {
                    // Force update from JSON to ensure deleted languages are removed from tabs
                    let result = updateFormFromJson();
                    klaroGeoLog('Form updated from JSON:', result);

                    // If we have a JSON editor, make sure the form matches the JSON
                    if ($('#translations_json_editor').length) {
                        let jsonText = $('#translations_json_editor').val();
                        try {
                            if (jsonText && jsonText.trim() !== '') {
                                let translations = JSON.parse(jsonText);
                                klaroGeoLog('Ensuring tabs match JSON data');

                                // Check if any tabs exist for languages not in the JSON
                                $('.translation-tab').each(function() {
                                    let tabId = $(this).attr('id');
                                    if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                                        let langCode = tabId.replace('tab-', '');

                                        // If this language is not in the JSON, remove the tab
                                        if (!translations[langCode]) {
                                            klaroGeoLog('Removing tab for language not in JSON:', langCode);

                                            // Remove the tab and its content
                                            let tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                                            $("#tab-" + langCode).remove();
                                            $(".translations-tabs-nav li").eq(tabIndex).remove();
                                        }
                                    }
                                });

                                // Refresh tabs
                                $("#translations-tabs").tabs("refresh");
                            }
                        } catch (e) {
                            klaroGeoError('Error checking JSON data:', e);
                        }
                    }
                }, 500);
            }
        }, wasUpdated ? 600 : 300);

        // Function to get language name from code
        function getLanguageName(langCode) {
            let langNames = {
                'en': 'English',
                'de': 'German',
                'fr': 'French',
                'es': 'Spanish',
                'it': 'Italian',
                'nl': 'Dutch',
                'pt': 'Portuguese',
                'sv': 'Swedish',
                'no': 'Norwegian',
                'da': 'Danish',
                'fi': 'Finnish',
                'pl': 'Polish',
                'ru': 'Russian',
                'ja': 'Japanese',
                'zh': 'Chinese',
                'ar': 'Arabic'
            };

            return langNames[langCode] || 'Custom';
        }

        // The template being edited, as PHP shipped it.
        function getCurrentTemplate() {
            let currentTemplate = $('#template_selector').val() || $('#current_template').val();
            return (klaroGeoTemplates.templates || {})[currentTemplate] || null;
        }

        // Function to add a language tab
        function addLanguageTab(langCode, langName) {
            // Check if this language already exists
            if ($("#tab-" + langCode).length > 0) {
                klaroGeoLog('Tab already exists for language:', langCode);
                return; // Tab already exists
            }

            klaroGeoLog('Adding language tab:', langCode, langName);

            // Add new tab
            let newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
            newTab.insertBefore($(".translations-tabs-nav li:last"));

            // Clone the fallback tab content as a starting point
            let newContent = $("#tab-zz").clone();
            newContent.attr('id', 'tab-' + langCode);
            newContent.addClass('translation-tab'); // Make sure the class is added

            // Add delete button to the heading
            let heading = newContent.find('h4:first');
            heading.text(langName + ' Translations (' + langCode + ')');
            heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

            // Get the purposes from the page
            let purposes = [];
            $('#tab-zz h5').each(function() {
                purposes.push($(this).text().toLowerCase());
            });
            klaroGeoLog('Found purposes for new tab:', purposes);

            // Update all input names and IDs to use the new language code
            newContent.find('input, textarea').each(function() {
                let name = $(this).attr('name');
                if (name) {
                    name = name.replace('[zz]', '[' + langCode + ']');
                    $(this).attr('name', name);
                }

                let id = $(this).attr('id');
                if (id) {
                    id = id.replace('_zz_', '_' + langCode + '_');
                    $(this).attr('id', id);
                }

                // Clear the value - we'll set it from the translations data later
                $(this).val('');
            });

            // Add the new tab content
            newContent.appendTo("#translations-tabs");

            // Refresh tabs
            $("#translations-tabs").tabs("refresh");

            // Fill it from the template's stored translations, if it has any
            let template = getCurrentTemplate();
            if (!template) {
                klaroGeoError('Current template not found');
                return;
            }

            if (!template.config || !template.config.translations) {
                klaroGeoWarn('No translations found in template');
                return;
            }

            // If we have translations for this language, use them
            if (template.config.translations[langCode]) {
                // Fill in the form fields with the translation values
                let translations = template.config.translations[langCode];
                klaroGeoLog('Setting translation values for language:', langCode, translations);

                // Process each property in the translations
                processTranslationObject(translations, langCode, '');
            } else {
                klaroGeoLog('No existing translations found for language:', langCode);
                // For new languages, we'll initialize with empty values
                // The form fields are already cleared above
            }

            // Update JSON after adding a new language
            updateJsonFromForm();
        }

        // Helper function to process translation objects and update form fields
        function processTranslationObject(obj, langCode, prefix) {
            Object.keys(obj).forEach(function(key) {
                let value = obj[key];
                let fieldName = prefix ? prefix + '[' + key + ']' : key;

                if (typeof value === 'object' && value !== null) {
                    // Recursively process nested objects
                    processTranslationObject(value, langCode, fieldName);
                } else {
                    // Find and update the form field
                    let selector = 'input[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"], ' +
                                  'textarea[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"]';

                    klaroGeoLog('Setting field:', selector, 'to value:', value);
                    $(selector).val(value);
                }
            });
        }

        // Function to collect form data and update JSON
        function updateJsonFromForm() {
            let translations = {};

            // Get all language tabs
            $('.translation-tab').each(function() {
                let tabId = $(this).attr('id');
                if (!tabId || tabId === 'tab-add') return; // Skip the "Add Language" tab

                let langCode = tabId.replace('tab-', '');
                translations[langCode] = {};

                // Process all inputs and textareas in this tab
                $(this).find('input, textarea').each(function() {
                    let name = $(this).attr('name');
                    if (!name) return;

                    // Extract the field path from the name attribute
                    // Example: template_config[translations][zz][acceptAll] -> acceptAll
                    let matches = name.match(/\[translations\]\[([^\]]+)\]\[([^\]]+)(?:\]\[([^\]]+))?(?:\]\[([^\]]+))?/);
                    if (!matches) return;

                    let lang = matches[1];
                    let key1 = matches[2];
                    let key2 = matches[3];
                    let key3 = matches[4];

                    let value = $(this).val();

                    // Build the nested structure
                    if (!translations[lang]) {
                        translations[lang] = {};
                    }

                    if (key2 && key3) {
                        // Three levels deep (e.g., consentModal.title)
                        if (!translations[lang][key1]) {
                            translations[lang][key1] = {};
                        }
                        if (!translations[lang][key1][key2]) {
                            translations[lang][key1][key2] = {};
                        }
                        translations[lang][key1][key2][key3] = value;
                    } else if (key2) {
                        // Two levels deep (e.g., privacyPolicy.name)
                        if (!translations[lang][key1]) {
                            translations[lang][key1] = {};
                        }
                        translations[lang][key1][key2] = value;
                    } else {
                        // One level deep (e.g., acceptAll)
                        translations[lang][key1] = value;
                    }
                });

                // Check if the language tab has any content
                if (Object.keys(translations[langCode]).length === 0) {
                    // If no form fields were found, this might be a new language tab
                    // Add some default structure to prevent empty objects
                    translations[langCode] = {
                        consentModal: {
                            title: '',
                            description: ''
                        },
                        consentNotice: {
                            title: '',
                            description: '',
                            learnMore: ''
                        },
                        acceptAll: '',
                        decline: '',
                        close: ''
                    };
                }
            });

            // Update the JSON textarea with proper formatting options
            // Use a simpler approach - just stringify with nice formatting
            let jsonString = JSON.stringify(translations, null, 2);

            // Don't try to fix escaping issues here - it's safer to let the browser handle it
            $('#translations_json_editor').val(jsonString);

            klaroGeoLog('Updated JSON from form:', translations);
            return translations;
        }

        // Function to parse JSON and update form fields
        function updateFormFromJson() {
            try {
                let jsonText = $('#translations_json_editor').val();
                let translations = JSON.parse(jsonText);

                if (typeof translations !== 'object') {
                    throw new Error('Invalid JSON: not an object');
                }

                // First, check if there are any tabs that need to be removed
                // (languages that are in the tabs but not in the JSON)
                $('.translation-tab').each(function() {
                    let tabId = $(this).attr('id');
                    if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                        let langCode = tabId.replace('tab-', '');

                        // If this language is not in the JSON, remove the tab
                        if (!translations[langCode]) {
                            klaroGeoLog('Removing tab for language not in JSON:', langCode);

                            // Remove the tab and its content
                            let tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                            $("#tab-" + langCode).remove();
                            $(".translations-tabs-nav li").eq(tabIndex).remove();
                        }
                    }
                });

                // Add tabs for each language in the JSON
                Object.keys(translations).forEach(function(langCode) {
                    // Skip if this is the fallback language (already exists)
                    if (langCode === 'zz') return;

                    // Add tab if it doesn't exist
                    if (!$("#tab-" + langCode).length) {
                        let langName = getLanguageName(langCode);
                        addLanguageTab(langCode, langName);
                    }
                });

                // Update form fields with values from JSON
                Object.keys(translations).forEach(function(langCode) {
                    let langData = translations[langCode];

                    // Process each property in the language data
                    processTranslationObject(langData, langCode, '');
                });

                // Refresh tabs
                $("#translations-tabs").tabs("refresh");

                return true;
            } catch (e) {
                alert('Error parsing JSON: ' + e.message);
                klaroGeoError('Error parsing JSON:', e);
                return false;
            }
        }

        // Handle deleting a language - use .off() first to prevent multiple bindings
        $(document).off('click', '.delete-language-btn').on('click', '.delete-language-btn', function(e) {
            e.preventDefault();
            let langCode = $(this).data('lang');

            if (confirm('Are you sure you want to delete the ' + langCode + ' language?')) {
                klaroGeoLog('Deleting language:', langCode);

                // Make sure we're not removing the "Add Language" tab
                // First, store a reference to the "Add Language" tab if it exists
                let addLanguageTab = $("#tab-add");
                let addLanguageTabNav = $(".translations-tabs-nav a[href='#tab-add']").parent();

                // Remove the tab and its content
                let tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                $("#tab-" + langCode).remove();
                $(".translations-tabs-nav li").eq(tabIndex).remove();

                // Make sure the "Add Language" tab is still there
                if (addLanguageTab.length && !$("#tab-add").length) {
                    klaroGeoLog("Re-adding the Add Language tab that was accidentally removed");
                    // Re-append the "Add Language" tab if it was removed
                    addLanguageTab.appendTo("#translations-tabs");
                    if (addLanguageTabNav.length) {
                        addLanguageTabNav.appendTo(".translations-tabs-nav");
                    }
                }

                // Refresh tabs and switch to fallback tab
                $("#translations-tabs").tabs("refresh");
                $("#translations-tabs").tabs("option", "active", 0);

                // Delete the language from the JSON data
                try {
                    let jsonText = $('#translations_json_editor').val();
                    let translations = JSON.parse(jsonText);

                    if (translations[langCode]) {
                        klaroGeoLog('Removing language from JSON:', langCode);
                        delete translations[langCode];

                        // Update the JSON editor with the modified data
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));

                        // Auto-save the changes
                        autoSaveTranslations('Language "' + langCode + '" deleted successfully');

                        klaroGeoLog('Language successfully removed from JSON');
                    } else {
                        klaroGeoWarn('Language not found in JSON:', langCode);
                    }
                } catch (e) {
                    klaroGeoError('Error updating JSON after deleting language:', e);
                }
            }
        });

        // Handle "Add Language Translation" button click
        $('#add_language_translation').click(function() {
            let langCode = $('#new_language_code').val();
            let copyFrom = $('#copy_from_language').val();

            if (!langCode) {
                alert('Please select a language code.');
                return;
            }

            // Check if this language already exists
            if ($("#tab-" + langCode).length > 0) {
                alert('This language already exists. Please select a different language.');
                return;
            }

            // Get language name
            let langName = getLanguageName(langCode);

            // Add the new language tab
            addLanguageTab(langCode, langName);

            // Copy content from the selected language
            if (copyFrom && copyFrom !== langCode) {
                // Get the JSON
                let jsonText = $('#translations_json_editor').val();
                try {
                    let translations = JSON.parse(jsonText);

                    if (translations[copyFrom]) {
                        // Copy the translations
                        translations[langCode] = JSON.parse(JSON.stringify(translations[copyFrom]));

                        // Update the JSON
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));

                        // Update the form
                        updateFormFromJson();
                    }
                } catch (e) {
                    klaroGeoError('Error parsing JSON when copying language:', e);
                }
            }

            // Switch to the new tab immediately
            let tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
            $("#translations-tabs").tabs("option", "active", tabIndex);

            // Store the current tab index in case the user submits the form
            localStorage.setItem('klaro_active_tab_index', tabIndex);

            // Reset the form
            $('#new_language_code').val('');

            // Update the JSON from the form to ensure it's in sync
            updateJsonFromForm();

            // Refresh tabs to ensure proper rendering
            $("#translations-tabs").tabs("refresh");

            // Auto-save the changes after switching to the new tab
            autoSaveTranslations('Language "' + langName + ' (' + langCode + ')" added successfully');
        });

        // Handle JSON buttons
        $('#format_json').click(function() {
            try {
                var jsonText = $('#translations_json_editor').val();

                // Pre-process the JSON to handle common issues
                jsonText = jsonText.trim();

                // Take a simpler approach to fix JSON issues

                // 1. Fix unquoted property names
                jsonText = jsonText.replace(/([{,])\s*([a-zA-Z0-9_]+)\s*:/g, '$1"$2":');

                // 2. Remove trailing commas
                jsonText = jsonText.replace(/,\s*}/g, '}');
                jsonText = jsonText.replace(/,\s*]/g, ']');

                // 3. Fix common contractions with apostrophes - using simple string replacements
                // This is safer than complex regex patterns
                jsonText = jsonText.replace(/"we"d/g, '"we\'d');
                jsonText = jsonText.replace(/"you"re/g, '"you\'re');
                jsonText = jsonText.replace(/"don"t/g, '"don\'t');
                jsonText = jsonText.replace(/"can"t/g, '"can\'t');
                jsonText = jsonText.replace(/"won"t/g, '"won\'t');
                jsonText = jsonText.replace(/"it"s/g, '"it\'s');
                jsonText = jsonText.replace(/"that"s/g, '"that\'s');
                jsonText = jsonText.replace(/"there"s/g, '"there\'s');
                jsonText = jsonText.replace(/"what"s/g, '"what\'s');
                jsonText = jsonText.replace(/"let"s/g, '"let\'s');

                // 4. Fix specific phrases that commonly have apostrophes
                jsonText = jsonText.replace(/we"d like to use/g, 'we\'d like to use');
                jsonText = jsonText.replace(/You"re in charge/g, 'You\'re in charge');
                jsonText = jsonText.replace(/That"s ok/g, 'That\'s ok');

                let translations = JSON.parse(jsonText);

                // Format with proper options
                // Use a simpler approach - just stringify with nice formatting
                let jsonString = JSON.stringify(translations, null, 2);

                // Don't try to fix escaping issues here - it's safer to let the browser handle it

                $('#translations_json_editor').val(jsonString);

                alert('JSON formatted successfully!');
            } catch (e) {
                alert('Error formatting JSON: ' + e.message + '\n\nTry fixing the JSON manually or use the "Update JSON from Form" button.');
                klaroGeoError('JSON formatting error:', e);
                klaroGeoLog('Problematic JSON:', jsonText);
            }
        });

        $('#validate_json').click(function() {
            try {
                var jsonText = $('#translations_json_editor').val();

                // Pre-process the JSON to handle common issues
                jsonText = jsonText.trim();

                // Try to parse the JSON
                JSON.parse(jsonText);

                alert('JSON is valid! You can save your changes now.');
            } catch (e) {
                // Provide more helpful error message
                let errorMsg = 'Invalid JSON: ' + e.message + '\n\n';
                errorMsg += 'Common issues to check:\n';
                errorMsg += '- Missing quotes around property names\n';
                errorMsg += '- Trailing commas at the end of lists or objects\n';
                errorMsg += '- Unescaped quotes in strings\n';
                errorMsg += '- Missing closing brackets or braces\n\n';
                errorMsg += 'Try using the "Format JSON" button to fix common issues automatically.';

                alert(errorMsg);
                klaroGeoError('JSON validation error:', e);
                klaroGeoLog('Problematic JSON:', jsonText);
            }
        });

        $('#update_form_from_json').click(function() {
            let result = updateFormFromJson();
            if (result) {
                // Show a success message
                let successIndicator = $('<div class="klaro-success-indicator">Form updated from JSON successfully</div>');
                $('body').append(successIndicator);

                // Remove the success indicator after 2 seconds
                setTimeout(function() {
                    successIndicator.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 2000);
            }
        });

        $('#update_json_from_form').click(function() {
            let translations = updateJsonFromForm();

            // Show a success message
            let successIndicator = $('<div class="klaro-success-indicator">JSON updated from form successfully</div>');
            $('body').append(successIndicator);

            // Remove the success indicator after 2 seconds
            setTimeout(function() {
                successIndicator.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 2000);
        });

        // Set up auto-save when users exit translation fields
        $(document).on('blur', '.translation-tab input, .translation-tab textarea', function() {
            // Debounce the auto-save to prevent too many requests
            clearTimeout(window.translationAutoSaveTimer);
            window.translationAutoSaveTimer = setTimeout(function() {
                // Update the JSON from the form
                updateJsonFromForm();

                // Auto-save the translations
                autoSaveTranslations('Translations saved automatically', null, true); // true = quiet mode
            }, 1500); // Wait 1.5 seconds after the last field change
        });

        // Also auto-save when the JSON editor is changed
        $('#translations_json_editor').on('blur', function() {
            // Debounce the auto-save to prevent too many requests
            clearTimeout(window.translationJsonAutoSaveTimer);
            window.translationJsonAutoSaveTimer = setTimeout(function() {
                // Auto-save the translations
                autoSaveTranslations('JSON saved automatically', null, true); // true = quiet mode
            }, 1500); // Wait 1.5 seconds after the last edit
        });

        // Update JSON before form submission
        $('form').on('submit', function() {
            klaroGeoLog('Form is being submitted, updating JSON from form...');

            try {
                // First, check if we have a JSON editor
                if ($('#translations_json_editor').length) {
                    // Get the current JSON from the editor
                    let jsonText = $('#translations_json_editor').val();
                    let currentTranslations = {};

                    try {
                        // Parse the current JSON
                        if (jsonText && jsonText.trim() !== '') {
                            currentTranslations = JSON.parse(jsonText);
                            klaroGeoLog('Current translations from JSON editor:', currentTranslations);
                        }
                    } catch (e) {
                        klaroGeoError('Error parsing current JSON:', e);
                    }

                    // Now update the JSON from the form
                    // This will ensure any deleted languages are properly removed
                    let translations = updateJsonFromForm();

                    // Debug log the translations being submitted
                    klaroGeoLog('Translations being submitted:', translations);

                    // Check if translations are empty
                    if (!translations || Object.keys(translations).length === 0) {
                        klaroGeoError('No translations found in form!');

                        // If we have current translations, use those
                        if (currentTranslations && Object.keys(currentTranslations).length > 0) {
                            klaroGeoLog('Using current translations from JSON editor');
                            // No need to update the JSON editor as it already has these values
                        } else {
                            klaroGeoError('No translations found in JSON editor either!');

                            // Set a default JSON value to prevent syntax errors
                            $('#translations_json_editor').val(JSON.stringify({
                                "zz": {
                                    "consentModal": {
                                        "title": "Privacy Settings",
                                        "description": "Here you can assess and customize the services that we'd like to use on this website."
                                    },
                                    "acceptAll": "Accept all",
                                    "decline": "I decline",
                                    "close": "Close"
                                }
                            }, null, 2));
                        }
                    } else {
                        // We have translations from the form, update the JSON editor
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));
                    }
                } else {
                    klaroGeoLog('No JSON editor found, skipping JSON update');
                }

            } catch (e) {
                klaroGeoError('Error updating JSON from form:', e);

                // Set a default JSON value to prevent syntax errors if we have a JSON editor
                if ($('#translations_json_editor').length) {
                    $('#translations_json_editor').val(JSON.stringify({
                        "zz": {
                            "consentModal": {
                                "title": "Privacy Settings",
                                "description": "Here you can assess and customize the services that we'd like to use on this website."
                            },
                            "acceptAll": "Accept all",
                            "decline": "I decline",
                            "close": "Close"
                        }
                    }, null, 2));
                }
            }

            return true;
        });

        // AJAX function to save translations
        function saveTranslationsAjax(callback) {
            klaroGeoLog('Saving translations via AJAX...');

            // First update the JSON from the form to ensure it's current
            updateJsonFromForm();

            // Get the form data
            let formData = new FormData($('form')[0]);

            // Add action for WordPress AJAX
            formData.append('action', 'klaro_geo_save_translations');

            // Get the current template ID
            let templateId = $('#template_id').val() || $('#current_template').val();
            if (templateId) {
                formData.append('template_id', templateId);
            }

            // Get the JSON data
            let jsonText = $('#translations_json_editor').val();
            if (jsonText) {
                formData.append('translations_json', jsonText);
            }

            // Add a nonce for security - try multiple sources
            let nonce = $('#klaro_geo_nonce').val();
            if (!nonce && typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.nonce) {
                nonce = klaroGeoTemplates.nonce;
            }
            if (nonce) {
                formData.append('nonce', nonce);
            } else {
                klaroGeoError('No nonce found for AJAX request');
            }

            // Send the AJAX request
            $.ajax({
                url: typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.ajax_url ? klaroGeoTemplates.ajax_url : ajaxurl, // Use localized URL or fallback to WordPress global
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    klaroGeoLog('AJAX save response:', response);

                    if (response.success) {
                        klaroGeoLog('Translations saved successfully');

                        // If we have a new JSON from the server, update our editor
                        if (response.data && response.data.translations_json) {
                            $('#translations_json_editor').val(response.data.translations_json);

                            // Update the form from the new JSON
                            updateFormFromJson();
                        }

                        // Call the callback if provided
                        if (typeof callback === 'function') {
                            callback(true, response);
                        }
                    } else {
                        klaroGeoError('Error saving translations:', response.data ? response.data.message : 'Unknown error');

                        // Call the callback if provided
                        if (typeof callback === 'function') {
                            callback(false, response);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    klaroGeoError('AJAX error:', status, error);

                    // Call the callback if provided
                    if (typeof callback === 'function') {
                        callback(false, { success: false, data: { message: error } });
                    }
                }
            });
        }

        // Function to auto-save translations with visual feedback
        function autoSaveTranslations(message, callback, quietMode) {
            // If in quiet mode, don't show the saving indicator
            let savingIndicator;
            if (!quietMode) {
                // Show a saving indicator
                savingIndicator = $('<div class="klaro-saving-indicator">Saving translations...</div>');
                $('body').append(savingIndicator);
            }

            // Save the translations
            saveTranslationsAjax(function(success, response) {
                // Remove the saving indicator if it exists
                if (savingIndicator) {
                    savingIndicator.remove();
                }

                if (success) {
                    if (!quietMode) {
                        // Show a success message
                        let successIndicator = $('<div class="klaro-success-indicator">' + (message || 'Translations saved successfully') + '</div>');
                        $('body').append(successIndicator);

                        // Remove the success indicator after 2 seconds
                        setTimeout(function() {
                            successIndicator.fadeOut(500, function() {
                                $(this).remove();

                                // Call the callback if provided
                                if (typeof callback === 'function') {
                                    callback(true, response);
                                }
                            });
                        }, 2000);
                    } else {
                        // In quiet mode, just call the callback if provided
                        if (typeof callback === 'function') {
                            callback(true, response);
                        }

                        // Show a small indicator in the admin bar
                        let adminBar = $('#wpadminbar');
                        if (adminBar.length) {
                            let miniIndicator = $('<div class="klaro-mini-indicator">✓</div>');
                            adminBar.append(miniIndicator);
                            setTimeout(function() {
                                miniIndicator.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }, 1000);
                        }
                    }
                } else {
                    // Always show errors, even in quiet mode
                    let errorMessage = response.data && response.data.message ? response.data.message : 'Error saving translations';
                    let errorIndicator = $('<div class="klaro-error-indicator">Error: ' + errorMessage + '</div>');
                    $('body').append(errorIndicator);

                    // Remove the error indicator after 3 seconds
                    setTimeout(function() {
                        errorIndicator.fadeOut(500, function() {
                            $(this).remove();

                            // Call the callback if provided
                            if (typeof callback === 'function') {
                                callback(false, response);
                            }
                        });
                    }, 3000);
                }
            });
        }

        // Add CSS for the indicators
        $('<style>')
            .text(`
                .klaro-saving-indicator, .klaro-success-indicator, .klaro-error-indicator {
                    position: fixed;
                    top: 50px;
                    right: 20px;
                    padding: 10px 15px;
                    border-radius: 4px;
                    z-index: 9999;
                    font-weight: bold;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }
                .klaro-saving-indicator {
                    background-color: #f8f9fa;
                    border: 1px solid #dee2e6;
                    color: #495057;
                }
                .klaro-success-indicator {
                    background-color: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                }
                .klaro-error-indicator {
                    background-color: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                .klaro-mini-indicator {
                    position: absolute;
                    top: 5px;
                    right: 20px;
                    background-color: #28a745;
                    color: white;
                    border-radius: 50%;
                    width: 16px;
                    height: 16px;
                    text-align: center;
                    line-height: 16px;
                    font-size: 10px;
                    z-index: 99999;
                }
            `)
            .appendTo('head');

        // ===== Template selector, deletion and creation =====

        // Template page URL from PHP (passed via klaroGeoTemplates)
        let templatePageUrl = (typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.templatePageUrl)
            ? klaroGeoTemplates.templatePageUrl
            : '';

        // Handle template selection change
        $('#template_selector').on('change', function() {
            let selectedTemplate = $(this).val();

            // Update the hidden input
            $('#current_template').val(selectedTemplate);

            // Redirect to the selected template
            window.location.href = templatePageUrl + '&template=' + selectedTemplate;
        });

        // Handle delete template button
        $('#delete_template').on('click', function() {
            let selectedTemplate = $('#template_selector').val();

            // Don't allow deleting the default template
            if (selectedTemplate === 'default') {
                alert('The default template cannot be deleted.');
                return;
            }

            if (confirm('Are you sure you want to delete the template "' + selectedTemplate + '"? This action cannot be undone.')) {
                // Send AJAX request to delete the template
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'klaro_geo_delete_template',
                        template_id: selectedTemplate,
                        nonce: $('#klaro_geo_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Template deleted successfully.');
                            // Redirect to the templates page with the default template selected
                            window.location.href = templatePageUrl;
                        } else {
                            // Create a more user-friendly error message
                            let errorMessage = 'Error deleting template: ' + response.data.message;

                            // Show the error in a dialog for better readability
                            $('<div></div>')
                                .text(errorMessage)
                                .dialog({
                                    title: 'Template Deletion Error',
                                    modal: true,
                                    width: 500,
                                    buttons: {
                                        Ok: function() {
                                            $(this).dialog('close');
                                        }
                                    }
                                });
                        }
                    },
                    error: function(xhr, status, error) {
                        $('<div></div>')
                            .text('An error occurred while deleting the template: ' + error)
                            .dialog({
                                title: 'Template Deletion Error',
                                modal: true,
                                width: 400,
                                buttons: {
                                    Ok: function() {
                                        $(this).dialog('close');
                                    }
                                }
                            });
                    }
                });
            }
        });

        // Build the tabs: one per language the template has, then return to
        // the tab that was open when the template was saved.
        if ($('#translations-tabs').length) {
            $('#translations-tabs').tabs();

            let template = getCurrentTemplate();
            let translations = (template && template.config && template.config.translations) || {};
            Object.keys(translations).forEach(function(langCode) {
                if (langCode !== 'zz') {
                    addLanguageTab(langCode, getLanguageName(langCode));
                }
            });

            let savedTabIndex = localStorage.getItem('klaro_active_tab_index');
            if (savedTabIndex !== null) {
                $('#translations-tabs').tabs('option', 'active', parseInt(savedTabIndex, 10));
                localStorage.removeItem('klaro_active_tab_index');
            }
        }

        // Handle form submission
        $('#template-form').on('submit', function() {
            // The JSON editor is brought up to date by the form's other submit
            // handler; this one only remembers which tab was open.
            // Store the current active tab index to restore it after page reload
            if ($('#translations-tabs').length) {
                let activeTabIndex = $('#translations-tabs').tabs('option', 'active');
                localStorage.setItem('klaro_active_tab_index', activeTabIndex);
            }

            // Continue with form submission
            return true;
        });

        // Handle Add Template button - creates new template form and AJAX handler
        $('#add_template').click(function() {
            // Hide the main template config temporarily
            $('#template_config').hide();

            // Show the new template form
            let newTemplateForm = $('<div id="new_template_form" class="template-creation-form">' +
                '<h3>Create New Template</h3>' +
                '<table class="form-table">' +
                '<tr>' +
                '<th><label for="new_template_name">Template Name:</label></th>' +
                '<td><input type="text" id="new_template_name" name="new_template_name" required></td>' +
                '</tr>' +
                '<tr>' +
                '<th><label for="inherit_from_template">Copy Settings From:</label></th>' +
                '<td><select id="inherit_from_template" name="inherit_from_template">' +
                $('#template_selector').html() + // Reuse existing templates list
                '</select></td>' +
                '</tr>' +
                '</table>' +
                '<div class="template-creation-buttons">' +
                '<button type="button" class="button button-primary" id="create_template">Create Template</button>' +
                '<button type="button" class="button" id="cancel_template">Cancel</button>' +
                '</div>' +
                '</div>');

            // Insert the form after the Add Template button
            $(this).after(newTemplateForm);
            $(this).hide();

            // Handle Cancel
            $('#cancel_template').click(function() {
                $('#new_template_form').remove();
                $('#add_template').show();
                $('#template_config').show();
            });

            // Handle Create
            $('#create_template').click(function() {
                let templateName = $('#new_template_name').val();
                let inheritFrom = $('#inherit_from_template').val();

                if (!templateName) {
                    alert('Please enter a template name');
                    return;
                }

                // Create new template by copying selected template's settings
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'klaro_geo_create_template',
                        template_name: templateName,
                        inherit_from: inheritFrom,
                        nonce: $('#klaro_geo_template_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Add new template to selector and select it
                            let option = new Option(templateName, response.data.template_key);
                            $('#template_selector').append(option);
                            $('#template_selector').val(response.data.template_key).trigger('change');

                            // Clean up creation form
                            $('#new_template_form').remove();
                            $('#add_template').show();
                            $('#template_config').show();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error creating template. Please try again.');
                    }
                });
            });
        });
    });
})(jQuery);
