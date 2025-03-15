/**
 * Klaro Geo Template Translations Tabs
 *
 * This file handles the tabbed interface for template translations.
 */
(function($) {
    // Wait for the DOM to be ready
    $(document).ready(function() {
        console.log('Klaro Geo Template Translations Tabs loaded');

        // Check if the page was just updated (after form submission)
        var urlParams = new URLSearchParams(window.location.search);
        var wasUpdated = urlParams.get('updated') === 'true';
        var needsReload = urlParams.get('needs_reload') === 'true';

        if (wasUpdated) {
            console.log('Page was just updated, ensuring tabs are properly initialized');

            // Check if we have deleted languages stored in localStorage
            var deletedLanguagesJson = localStorage.getItem('klaro_deleted_languages');
            if (deletedLanguagesJson) {
                try {
                    var deletedLanguages = JSON.parse(deletedLanguagesJson);
                    console.log('Found deleted languages in localStorage:', deletedLanguages);

                    // If we have deleted languages and this is the first load after saving,
                    // we'll need to reload the page again to ensure they're properly removed
                    if (deletedLanguages.length > 0 && needsReload) {
                        console.log('Reloading page to ensure deleted languages are removed');
                        // Clear the localStorage item to prevent infinite reloads
                        localStorage.removeItem('klaro_deleted_languages');
                        // Remove the needs_reload parameter to prevent infinite reloads
                        var newUrl = window.location.href.replace(/[?&]needs_reload=true/, '');
                        window.location.href = newUrl;
                        return; // Stop execution to allow the reload
                    }

                    // Even if we don't need to reload, we should still remove the deleted languages
                    // from the JSON data to ensure they don't reappear in the tabs
                    if (deletedLanguages.length > 0) {
                        console.log('Removing deleted languages from JSON data:', deletedLanguages);

                        try {
                            // Get the current JSON from the editor
                            var jsonText = $('#translations_json_editor').val();
                            if (jsonText && jsonText.trim() !== '') {
                                var translations = JSON.parse(jsonText);

                                // Remove each deleted language
                                deletedLanguages.forEach(function(langCode) {
                                    if (translations[langCode]) {
                                        console.log('Removing language from JSON:', langCode);
                                        delete translations[langCode];
                                    }
                                });

                                // Update the JSON editor with the modified data
                                $('#translations_json_editor').val(JSON.stringify(translations, null, 2));
                            }
                        } catch (e) {
                            console.error('Error removing deleted languages from JSON:', e);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing deleted languages from localStorage:', e);
                }

                // Clear the localStorage item
                localStorage.removeItem('klaro_deleted_languages');
            }
        }

        // Initialize translations tabs with a longer delay to ensure DOM is fully ready
        // Use an even longer delay if the page was just updated
        setTimeout(function() {
            initTranslationsTabs();

            // If the page was just updated, also explicitly update the form from JSON
            // This ensures translations are loaded from the JSON editor
            if (wasUpdated && $('#translations_json_editor').length) {
                console.log('Page was updated, explicitly loading translations from JSON');
                setTimeout(function() {
                    // Force update from JSON to ensure deleted languages are removed from tabs
                    var result = updateFormFromJson();
                    console.log('Form updated from JSON:', result);

                    // If we have a JSON editor, make sure the form matches the JSON
                    if ($('#translations_json_editor').length) {
                        var jsonText = $('#translations_json_editor').val();
                        try {
                            if (jsonText && jsonText.trim() !== '') {
                                var translations = JSON.parse(jsonText);
                                console.log('Ensuring tabs match JSON data');

                                // Check if any tabs exist for languages not in the JSON
                                $('.translation-tab').each(function() {
                                    var tabId = $(this).attr('id');
                                    if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                                        var langCode = tabId.replace('tab-', '');

                                        // If this language is not in the JSON, remove the tab
                                        if (!translations[langCode]) {
                                            console.log('Removing tab for language not in JSON:', langCode);

                                            // Remove the tab and its content
                                            var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                                            $("#tab-" + langCode).remove();
                                            $(".translations-tabs-nav li").eq(tabIndex).remove();
                                        }
                                    }
                                });

                                // Refresh tabs
                                $("#translations-tabs").tabs("refresh");
                            }
                        } catch (e) {
                            console.error('Error checking JSON data:', e);
                        }
                    }
                }, 500);
            }
        }, wasUpdated ? 600 : 300);

        // Function to initialize the translations tabs
        function initTranslationsTabs() {
            console.log('Initializing translation tabs');

            // Check if translations tabs exist
            if ($('#translations-tabs').length) {
                console.log('Translations tabs container found');

                // Initialize the tabs
                $('#translations-tabs').tabs({
                    // Add a callback for when tabs are activated
                    activate: function(event, ui) {
                        console.log('Tab activated:', ui.newTab.text());
                    },
                    // Add a callback for when tabs are created
                    create: function(event, ui) {
                        console.log('Tabs created');
                        // Add existing translations as tabs after a short delay
                        // This ensures the DOM is fully ready
                        setTimeout(function() {
                            addExistingTranslationTabs();
                        }, 100);
                    }
                });

                console.log('Translations tabs initialized');
            } else {
                console.error('Translations tabs container not found');
            }
        }
        
        // Function to add existing translations as tabs
        function addExistingTranslationTabs() {
            console.log('Adding existing translation tabs');

            // Make sure the "Add Language" tab is still there
            if (!$("#tab-add").length) {
                console.log("Add Language tab not found, it may have been removed or not yet added");
                // We'll check for this again after processing all tabs
            }

            // Check if we have templates data (try both variable names)
            var templatesData = null;
            if (typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.templates) {
                templatesData = klaroGeoTemplates.templates;
                console.log('Using klaroGeoTemplates for template data');
            } else if (typeof window.klaroTemplates !== 'undefined') {
                templatesData = window.klaroTemplates;
                console.log('Using window.klaroTemplates for template data');
            } else {
                console.error('No templates data found');
                return;
            }

            // Get current template
            var currentTemplate = $('#template_selector').val() || $('#current_template').val();
            console.log('Current template:', currentTemplate);

            if (!currentTemplate) {
                console.error('Current template selector not found');
                // Try to get the first template key as a fallback
                var templateKeys = Object.keys(templatesData);
                if (templateKeys.length > 0) {
                    currentTemplate = templateKeys[0];
                    console.log('Using first template as fallback:', currentTemplate);
                } else {
                    console.error('No templates available');
                    return;
                }
            }

            if (!templatesData[currentTemplate]) {
                console.error('Template not found:', currentTemplate);
                return;
            }

            var template = templatesData[currentTemplate];
            console.log('Template data:', template);

            // Check if template has translations
            if (!template.config || !template.config.translations) {
                console.warn('Template has no translations');
                return;
            }

            var translations = template.config.translations;
            console.log('Found translations:', translations);

            // Add tabs for each language
            Object.keys(translations).forEach(function(langCode) {
                // Skip fallback language as it already exists
                if (langCode === 'zz') {
                    console.log('Skipping fallback language (zz)');
                    return;
                }

                console.log('Processing language:', langCode);

                // Get language name
                var langName = getLanguageName(langCode);
                console.log('Language name:', langName);

                // Add language tab if it doesn't already exist
                if (!$("#tab-" + langCode).length) {
                    console.log('Adding tab for language:', langCode);
                    addLanguageTab(langCode, langName);
                } else {
                    console.log('Tab already exists for language:', langCode);
                }
            });

            // Refresh tabs
            $("#translations-tabs").tabs("refresh");

            // Final check to ensure the "Add Language" tab is present
            if (!$("#tab-add").length && $(".translations-tabs-nav").length) {
                console.log("Ensuring Add Language tab is present after processing all tabs");

                // Check if we need to add the "Add Language" tab
                var addTabExists = false;
                $(".translations-tabs-nav li a").each(function() {
                    if ($(this).attr('href') === '#tab-add') {
                        addTabExists = true;
                        return false; // break the loop
                    }
                });

                if (!addTabExists) {
                    console.log("Re-adding the Add Language tab navigation");
                    // Add the "Add Language" tab navigation
                    var addTabNav = $('<li><a href="#tab-add">Add Language</a></li>');
                    $(".translations-tabs-nav").append(addTabNav);

                    // Check if the content exists
                    if (!$("#tab-add").length) {
                        console.log("Re-adding the Add Language tab content");
                        // Create a basic structure for the "Add Language" tab
                        var addTabContent = $('<div id="tab-add" class="tab-content"></div>');
                        var addTabHtml = '<h4>Add New Language Translation</h4>' +
                                        '<p>Select a language code and click "Add Language Translation" to add a new language.</p>' +
                                        '<select id="new_language_code">' +
                                        '<option value="">Select Language...</option>';

                        // Add options for common languages
                        var langNames = {
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

                        for (var code in langNames) {
                            addTabHtml += '<option value="' + code + '">' + langNames[code] + ' (' + code + ')</option>';
                        }

                        addTabHtml += '</select>' +
                                    '<p>Copy content from:</p>' +
                                    '<select id="copy_from_language">' +
                                    '<option value="">None (Empty)</option>' +
                                    '<option value="zz">Default (zz)</option>';

                        // Add options for existing languages
                        $('.translation-tab').each(function() {
                            var tabId = $(this).attr('id');
                            if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                                var langCode = tabId.replace('tab-', '');
                                var langName = getLanguageName(langCode);
                                addTabHtml += '<option value="' + langCode + '">' + langName + ' (' + langCode + ')</option>';
                            }
                        });

                        addTabHtml += '</select>' +
                                    '<p><button type="button" id="add_language_translation" class="button button-primary">Add Language Translation</button></p>';

                        addTabContent.html(addTabHtml);
                        $("#translations-tabs").append(addTabContent);
                    }

                    // Refresh tabs again
                    $("#translations-tabs").tabs("refresh");
                }
            }
        }
        
        // Function to get language name from code
        function getLanguageName(langCode) {
            var langNames = {
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
        
        // Function to add a language tab
        function addLanguageTab(langCode, langName) {
            // Check if this language already exists
            if ($("#tab-" + langCode).length > 0) {
                console.log('Tab already exists for language:', langCode);
                return; // Tab already exists
            }

            console.log('Adding language tab:', langCode, langName);

            // Add new tab
            var newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
            newTab.insertBefore($(".translations-tabs-nav li:last"));

            // Clone the fallback tab content as a starting point
            var newContent = $("#tab-zz").clone();
            newContent.attr('id', 'tab-' + langCode);
            newContent.addClass('translation-tab'); // Make sure the class is added

            // Add delete button to the heading
            var heading = newContent.find('h4:first');
            heading.text(langName + ' Translations (' + langCode + ')');
            heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

            // Get the purposes from the page
            var purposes = [];
            $('#tab-zz h5').each(function() {
                purposes.push($(this).text().toLowerCase());
            });
            console.log('Found purposes for new tab:', purposes);

            // Update all input names and IDs to use the new language code
            newContent.find('input, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace('[zz]', '[' + langCode + ']');
                    $(this).attr('name', name);
                }

                var id = $(this).attr('id');
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

            // Get the translations data for this language
            var templatesData = null;
            if (typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.templates) {
                templatesData = klaroGeoTemplates.templates;
            } else if (typeof window.klaroTemplates !== 'undefined') {
                templatesData = window.klaroTemplates;
            } else {
                console.error('No templates data found');
                return;
            }

            var currentTemplate = $('#template_selector').val() || $('#current_template').val();
            if (!currentTemplate || !templatesData[currentTemplate]) {
                console.error('Current template not found');
                return;
            }

            var template = templatesData[currentTemplate];
            if (!template.config || !template.config.translations) {
                console.warn('No translations found in template');
                return;
            }

            // If we have translations for this language, use them
            if (template.config.translations[langCode]) {
                // Fill in the form fields with the translation values
                var translations = template.config.translations[langCode];
                console.log('Setting translation values for language:', langCode, translations);

                // Process each property in the translations
                processTranslationObject(translations, langCode, '');
            } else {
                console.log('No existing translations found for language:', langCode);
                // For new languages, we'll initialize with empty values
                // The form fields are already cleared above
            }

            // Update JSON after adding a new language
            updateJsonFromForm();
        }

        // Helper function to process translation objects and update form fields
        function processTranslationObject(obj, langCode, prefix) {
            Object.keys(obj).forEach(function(key) {
                var value = obj[key];
                var fieldName = prefix ? prefix + '[' + key + ']' : key;

                if (typeof value === 'object' && value !== null) {
                    // Recursively process nested objects
                    processTranslationObject(value, langCode, fieldName);
                } else {
                    // Find and update the form field
                    var selector = 'input[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"], ' +
                                  'textarea[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"]';

                    console.log('Setting field:', selector, 'to value:', value);
                    $(selector).val(value);
                }
            });
        }
        
        // Function to collect form data and update JSON
        function updateJsonFromForm() {
            var translations = {};

            // Get all language tabs
            $('.translation-tab').each(function() {
                var tabId = $(this).attr('id');
                if (!tabId || tabId === 'tab-add') return; // Skip the "Add Language" tab

                var langCode = tabId.replace('tab-', '');
                translations[langCode] = {};

                // Process all inputs and textareas in this tab
                $(this).find('input, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (!name) return;

                    // Extract the field path from the name attribute
                    // Example: template_config[translations][zz][acceptAll] -> acceptAll
                    var matches = name.match(/\[translations\]\[([^\]]+)\]\[([^\]]+)(?:\]\[([^\]]+))?(?:\]\[([^\]]+))?/);
                    if (!matches) return;

                    var lang = matches[1];
                    var key1 = matches[2];
                    var key2 = matches[3];
                    var key3 = matches[4];

                    var value = $(this).val();

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
            var jsonString = JSON.stringify(translations, null, 2);

            // Don't try to fix escaping issues here - it's safer to let the browser handle it
            $('#translations_json_editor').val(jsonString);

            console.log('Updated JSON from form:', translations);
            return translations;
        }
        
        // Function to parse JSON and update form fields
        function updateFormFromJson() {
            try {
                var jsonText = $('#translations_json_editor').val();
                var translations = JSON.parse(jsonText);

                if (typeof translations !== 'object') {
                    throw new Error('Invalid JSON: not an object');
                }

                // First, check if there are any tabs that need to be removed
                // (languages that are in the tabs but not in the JSON)
                $('.translation-tab').each(function() {
                    var tabId = $(this).attr('id');
                    if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                        var langCode = tabId.replace('tab-', '');

                        // If this language is not in the JSON, remove the tab
                        if (!translations[langCode]) {
                            console.log('Removing tab for language not in JSON:', langCode);

                            // Remove the tab and its content
                            var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
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
                        var langName = getLanguageName(langCode);
                        addLanguageTab(langCode, langName);
                    }
                });

                // Update form fields with values from JSON
                Object.keys(translations).forEach(function(langCode) {
                    var langData = translations[langCode];

                    // Process each property in the language data
                    processJsonObject(langData, langCode, '');
                });

                // Make sure the "Add Language" tab is still there
                if (!$("#tab-add").length && $(".translations-tabs-nav").length) {
                    console.log("Ensuring Add Language tab is present after updating from JSON");

                    // Check if we need to add the "Add Language" tab
                    var addTabExists = false;
                    $(".translations-tabs-nav li a").each(function() {
                        if ($(this).attr('href') === '#tab-add') {
                            addTabExists = true;
                            return false; // break the loop
                        }
                    });

                    if (!addTabExists) {
                        console.log("Re-adding the Add Language tab navigation");
                        // Add the "Add Language" tab navigation
                        var addTabNav = $('<li><a href="#tab-add">Add Language</a></li>');
                        $(".translations-tabs-nav").append(addTabNav);

                        // Check if the content exists
                        if (!$("#tab-add").length) {
                            console.log("Re-adding the Add Language tab content");
                            // Create a basic structure for the "Add Language" tab
                            var addTabContent = $('<div id="tab-add" class="tab-content"></div>');
                            var addTabHtml = '<h4>Add New Language Translation</h4>' +
                                            '<p>Select a language code and click "Add Language Translation" to add a new language.</p>' +
                                            '<select id="new_language_code">' +
                                            '<option value="">Select Language...</option>';

                            // Add options for common languages
                            var langNames = {
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

                            for (var code in langNames) {
                                addTabHtml += '<option value="' + code + '">' + langNames[code] + ' (' + code + ')</option>';
                            }

                            addTabHtml += '</select>' +
                                        '<p>Copy content from:</p>' +
                                        '<select id="copy_from_language">' +
                                        '<option value="">None (Empty)</option>' +
                                        '<option value="zz">Default (zz)</option>';

                            // Add options for existing languages
                            $('.translation-tab').each(function() {
                                var tabId = $(this).attr('id');
                                if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                                    var langCode = tabId.replace('tab-', '');
                                    var langName = getLanguageName(langCode);
                                    addTabHtml += '<option value="' + langCode + '">' + langName + ' (' + langCode + ')</option>';
                                }
                            });

                            addTabHtml += '</select>' +
                                        '<p><button type="button" id="add_language_translation" class="button button-primary">Add Language Translation</button></p>';

                            addTabContent.html(addTabHtml);
                            $("#translations-tabs").append(addTabContent);
                        }
                    }
                }

                // Refresh tabs
                $("#translations-tabs").tabs("refresh");

                return true;
            } catch (e) {
                alert('Error parsing JSON: ' + e.message);
                console.error('Error parsing JSON:', e);
                return false;
            }
        }
        
        // Helper function to process nested JSON objects and update form fields
        function processJsonObject(obj, langCode, prefix) {
            Object.keys(obj).forEach(function(key) {
                var value = obj[key];
                var fieldName = prefix ? prefix + '[' + key + ']' : key;
                
                if (typeof value === 'object' && value !== null) {
                    // Recursively process nested objects
                    processJsonObject(value, langCode, fieldName);
                } else {
                    // Find and update the form field
                    var selector = 'input[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"], ' +
                                  'textarea[name="template_config[translations][' + langCode + ']' +
                                  (prefix ? '[' + prefix + ']' : '') +
                                  '[' + key + ']"]';
                    
                    $(selector).val(value);
                }
            });
        }
        
        // Handle deleting a language - use .off() first to prevent multiple bindings
        $(document).off('click', '.delete-language-btn').on('click', '.delete-language-btn', function(e) {
            e.preventDefault();
            var langCode = $(this).data('lang');

            if (confirm('Are you sure you want to delete the ' + langCode + ' language?')) {
                console.log('Deleting language:', langCode);

                // Make sure we're not removing the "Add Language" tab
                // First, store a reference to the "Add Language" tab if it exists
                var addLanguageTab = $("#tab-add");
                var addLanguageTabNav = $(".translations-tabs-nav a[href='#tab-add']").parent();

                // Remove the tab and its content
                var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                $("#tab-" + langCode).remove();
                $(".translations-tabs-nav li").eq(tabIndex).remove();

                // Make sure the "Add Language" tab is still there
                if (addLanguageTab.length && !$("#tab-add").length) {
                    console.log("Re-adding the Add Language tab that was accidentally removed");
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
                    var jsonText = $('#translations_json_editor').val();
                    var translations = JSON.parse(jsonText);

                    if (translations[langCode]) {
                        console.log('Removing language from JSON:', langCode);
                        delete translations[langCode];

                        // Update the JSON editor with the modified data
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));

                        // Auto-save the changes
                        autoSaveTranslations('Language "' + langCode + '" deleted successfully');

                        console.log('Language successfully removed from JSON');
                    } else {
                        console.warn('Language not found in JSON:', langCode);
                    }
                } catch (e) {
                    console.error('Error updating JSON after deleting language:', e);
                }
            }
        });
        
        // Handle "Add Language Translation" button click
        $('#add_language_translation').click(function() {
            var langCode = $('#new_language_code').val();
            var copyFrom = $('#copy_from_language').val();

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
            var langName = getLanguageName(langCode);

            // Add the new language tab
            addLanguageTab(langCode, langName);

            // Copy content from the selected language
            if (copyFrom && copyFrom !== langCode) {
                // Get the JSON
                var jsonText = $('#translations_json_editor').val();
                try {
                    var translations = JSON.parse(jsonText);

                    if (translations[copyFrom]) {
                        // Copy the translations
                        translations[langCode] = JSON.parse(JSON.stringify(translations[copyFrom]));

                        // Update the JSON
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));

                        // Update the form
                        updateFormFromJson();
                    }
                } catch (e) {
                    console.error('Error parsing JSON when copying language:', e);
                }
            }

            // Switch to the new tab immediately
            var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
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

                var translations = JSON.parse(jsonText);

                // Format with proper options
                // Use a simpler approach - just stringify with nice formatting
                var jsonString = JSON.stringify(translations, null, 2);

                // Don't try to fix escaping issues here - it's safer to let the browser handle it

                $('#translations_json_editor').val(jsonString);

                alert('JSON formatted successfully!');
            } catch (e) {
                alert('Error formatting JSON: ' + e.message + '\n\nTry fixing the JSON manually or use the "Update JSON from Form" button.');
                console.error('JSON formatting error:', e);
                console.log('Problematic JSON:', jsonText);
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
                var errorMsg = 'Invalid JSON: ' + e.message + '\n\n';
                errorMsg += 'Common issues to check:\n';
                errorMsg += '- Missing quotes around property names\n';
                errorMsg += '- Trailing commas at the end of lists or objects\n';
                errorMsg += '- Unescaped quotes in strings\n';
                errorMsg += '- Missing closing brackets or braces\n\n';
                errorMsg += 'Try using the "Format JSON" button to fix common issues automatically.';

                alert(errorMsg);
                console.error('JSON validation error:', e);
                console.log('Problematic JSON:', jsonText);
            }
        });
        
        $('#update_form_from_json').click(function() {
            var result = updateFormFromJson();
            if (result) {
                // Show a success message
                var successIndicator = $('<div class="klaro-success-indicator">Form updated from JSON successfully</div>');
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
            var translations = updateJsonFromForm();

            // Show a success message
            var successIndicator = $('<div class="klaro-success-indicator">JSON updated from form successfully</div>');
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

        // Debug translations button
        $('#debug_translations').click(function() {
            console.group('Translations Debug Info');

            // Log form data
            console.log('Form data:');
            var formData = {};
            $('form').serializeArray().forEach(function(item) {
                formData[item.name] = item.value;
            });
            console.log(formData);

            // Log translations from form
            console.log('Translations from form:');
            var translations = updateJsonFromForm();
            console.log(translations);

            // Log JSON editor content
            console.log('JSON editor content:');
            var jsonText = $('#translations_json_editor').val();
            console.log(jsonText);

            try {
                var parsedJson = JSON.parse(jsonText);
                console.log('Parsed JSON:', parsedJson);
            } catch (e) {
                console.error('Error parsing JSON:', e);
            }

            // Log template data
            console.log('Template data:');
            if (typeof klaroGeoTemplates !== 'undefined') {
                console.log('klaroGeoTemplates:', klaroGeoTemplates);
            }
            if (typeof window.klaroTemplates !== 'undefined') {
                console.log('window.klaroTemplates:', window.klaroTemplates);
            }

            // Log current template
            console.log('Current template:');
            var currentTemplate = $('#template_selector').val() || $('#current_template').val();
            console.log(currentTemplate);

            console.groupEnd();

            alert('Debug information has been logged to the console. Please open the browser developer tools to view it.');
        });

        // Update JSON before form submission
        $('form').on('submit', function() {
            console.log('Form is being submitted, updating JSON from form...');

            try {
                // First, check if we have a JSON editor
                if ($('#translations_json_editor').length) {
                    // Get the current JSON from the editor
                    var jsonText = $('#translations_json_editor').val();
                    var currentTranslations = {};

                    try {
                        // Parse the current JSON
                        if (jsonText && jsonText.trim() !== '') {
                            currentTranslations = JSON.parse(jsonText);
                            console.log('Current translations from JSON editor:', currentTranslations);
                        }
                    } catch (e) {
                        console.error('Error parsing current JSON:', e);
                    }

                    // Now update the JSON from the form
                    // This will ensure any deleted languages are properly removed
                    var translations = updateJsonFromForm();

                    // Debug log the translations being submitted
                    console.log('Translations being submitted:', translations);

                    // Check if translations are empty
                    if (!translations || Object.keys(translations).length === 0) {
                        console.error('No translations found in form!');

                        // If we have current translations, use those
                        if (currentTranslations && Object.keys(currentTranslations).length > 0) {
                            console.log('Using current translations from JSON editor');
                            // No need to update the JSON editor as it already has these values
                        } else {
                            console.error('No translations found in JSON editor either!');

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
                    console.log('No JSON editor found, skipping JSON update');
                }

                // Store information about deleted languages in localStorage
                // This will be used after the page reloads to ensure deleted languages stay deleted
                var deletedLanguages = [];
                $('.delete-language-btn').each(function() {
                    if ($(this).data('deleted') === true) {
                        deletedLanguages.push($(this).data('lang'));
                    }
                });

                if (deletedLanguages.length > 0) {
                    console.log('Storing deleted languages for post-reload cleanup:', deletedLanguages);
                    localStorage.setItem('klaro_deleted_languages', JSON.stringify(deletedLanguages));

                    // Add a parameter to the form action URL to indicate we need to reload
                    // This will help us detect if we need to force a second reload
                    var formAction = $(this).attr('action');
                    if (formAction.indexOf('?') > -1) {
                        formAction += '&needs_reload=true';
                    } else {
                        formAction += '?needs_reload=true';
                    }
                    $(this).attr('action', formAction);
                }
            } catch (e) {
                console.error('Error updating JSON from form:', e);

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
            console.log('Saving translations via AJAX...');

            // First update the JSON from the form to ensure it's current
            updateJsonFromForm();

            // Get the form data
            var formData = new FormData($('form')[0]);

            // Add action for WordPress AJAX
            formData.append('action', 'klaro_geo_save_translations');

            // Get the current template ID
            var templateId = $('#template_id').val() || $('#current_template').val();
            if (templateId) {
                formData.append('template_id', templateId);
            }

            // Get the JSON data
            var jsonText = $('#translations_json_editor').val();
            if (jsonText) {
                formData.append('translations_json', jsonText);
            }

            // Add a nonce for security - try multiple sources
            var nonce = $('#klaro_geo_nonce').val();
            if (!nonce && typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.nonce) {
                nonce = klaroGeoTemplates.nonce;
            }
            if (nonce) {
                formData.append('nonce', nonce);
            } else {
                console.error('No nonce found for AJAX request');
            }

            // Send the AJAX request
            $.ajax({
                url: typeof klaroGeoTemplates !== 'undefined' && klaroGeoTemplates.ajax_url ? klaroGeoTemplates.ajax_url : ajaxurl, // Use localized URL or fallback to WordPress global
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('AJAX save response:', response);

                    if (response.success) {
                        console.log('Translations saved successfully');

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
                        console.error('Error saving translations:', response.data ? response.data.message : 'Unknown error');

                        // Call the callback if provided
                        if (typeof callback === 'function') {
                            callback(false, response);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);

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
            var savingIndicator;
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
                        var successIndicator = $('<div class="klaro-success-indicator">' + (message || 'Translations saved successfully') + '</div>');
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
                        var adminBar = $('#wpadminbar');
                        if (adminBar.length) {
                            var miniIndicator = $('<div class="klaro-mini-indicator">✓</div>');
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
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Error saving translations';
                    var errorIndicator = $('<div class="klaro-error-indicator">Error: ' + errorMessage + '</div>');
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

        // Make functions available globally
        window.addLanguageTab = addLanguageTab;
        window.updateJsonFromForm = updateJsonFromForm;
        window.updateFormFromJson = updateFormFromJson;
        window.saveTranslationsAjax = saveTranslationsAjax;
        window.autoSaveTranslations = autoSaveTranslations;
    });
})(jQuery);