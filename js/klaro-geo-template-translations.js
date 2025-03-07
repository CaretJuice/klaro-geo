/**
 * Klaro Geo Template Translations
 *
 * This file handles the template translations functionality.
 */
(function($) {
    // Wait for the DOM to be ready
    $(document).ready(function() {
        console.log('Klaro Geo Template Translations loaded');

        // Check if we have templates data
        if (typeof klaroGeoTemplates === 'undefined' || !klaroGeoTemplates.templates) {
            console.error('klaroGeoTemplates not defined or empty');
            return;
        }

        // Debug log
        console.log('Templates data:', klaroGeoTemplates);

        // Function to get language name from code
        function getLanguageName(langCode) {
            var langNames = {
                'en': 'English',
                'de': 'German',
                'fr': 'French',
                'es': 'Spanish',
                'it': 'Italian',
                'nl': 'Dutch',
                'pt': 'Portuguese'
            };

            return langNames[langCode] || 'Custom';
        }

        // Function to load existing languages for the current template
        function loadExistingLanguages() {
            // Get current template
            var currentTemplate = $('#current_template').val();

            if (!currentTemplate || !klaroGeoTemplates.templates[currentTemplate]) {
                console.error('Current template not found:', currentTemplate);
                return;
            }

            var template = klaroGeoTemplates.templates[currentTemplate];

            // Check if template has translations
            if (!template.config || !template.config.translations) {
                console.warn('Template has no translations');
                return;
            }

            var translations = template.config.translations;

            // Add tabs for each language
            Object.keys(translations).forEach(function(langCode) {
                // Skip fallback language as it already exists
                if (langCode === 'zz') return;

                // Get language name
                var langName = getLanguageName(langCode);

                // Add language tab
                addLanguageTab(langCode, langName);
            });
        }

        // Function to add a language tab
        function addLanguageTab(langCode, langName) {
            // Check if this language already exists
            if ($("#tab-" + langCode).length > 0) {
                return; // Tab already exists
            }

            console.log('Adding language tab:', langCode, langName);

            // Add new tab
            var newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
            newTab.insertBefore($(".translations-tabs-nav li:last"));

            // Clone the fallback tab content as a starting point
            var newContent = $("#tab-zz").clone();
            newContent.attr('id', 'tab-' + langCode);

            // Add delete button to the heading
            var heading = newContent.find('h4');
            heading.text(langName + ' Translations (' + langCode + ')');
            heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

            // Update all input names to use the new language code
            newContent.find('input, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace('[zz]', '[' + langCode + ']');
                    $(this).attr('name', name);
                }
            });

            // Add the new tab content
            newContent.appendTo("#translations-tabs");

            // Refresh tabs
            $("#translations-tabs").tabs("refresh");

            // Update JSON after adding a new language
            updateJsonFromForm();
        }

        // Make the function available globally
        window.addLanguageTab = addLanguageTab;

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
            });

            // Update the JSON textarea
            $('#translations_json_editor').val(JSON.stringify(translations, null, 2));
        }

        // Function to parse JSON and update form fields
        function updateFormFromJson() {
            try {
                var jsonText = $('#translations_json_editor').val();
                var translations = JSON.parse(jsonText);

                if (typeof translations !== 'object') {
                    throw new Error('Invalid JSON: not an object');
                }

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

        // Handle deleting a language
        $(document).on('click', '.delete-language-btn', function(e) {
            e.preventDefault();
            var langCode = $(this).data('lang');

            if (confirm('Are you sure you want to delete the ' + langCode + ' language? This action cannot be undone.')) {
                // Remove the tab and its content
                var tabIndex = $(".translations-tabs-nav a[href='#tab-" + langCode + "']").parent().index();
                $("#tab-" + langCode).remove();
                $(".translations-tabs-nav li").eq(tabIndex).remove();

                // Refresh tabs and switch to fallback tab
                $("#translations-tabs").tabs("refresh");
                $("#translations-tabs").tabs("option", "active", 0);

                // Update JSON after deleting a language
                updateJsonFromForm();
            }
        });

        // Add delete buttons to existing language tabs (except fallback)
        $(".translation-tab").each(function() {
            var tabId = $(this).attr('id');
            if (tabId && tabId !== 'tab-zz' && tabId !== 'tab-add') {
                var langCode = tabId.replace('tab-', '');
                var heading = $(this).find('h4');
                if (!heading.find('.delete-language-btn').length) {
                    heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');
                }
            }
        });

        // Load existing languages when the page loads
        loadExistingLanguages();

        // Handle template change
        $('#current_template').change(function() {
            // Reload the page with the new template
            var newTemplate = $(this).val();
            window.location.href = window.location.pathname + '?page=klaro-geo-templates&template=' + newTemplate;
        });

        // Handle form field changes to update JSON
        $(document).on('change', '.translation-tab input, .translation-tab textarea', function() {
            updateJsonFromForm();
        });

        // Handle "Update Form from JSON" button click
        $('#update_from_json').click(function() {
            updateFormFromJson();
        });

        // Handle "Update JSON from Form" button click
        $('#update_to_json').click(function() {
            updateJsonFromForm();
        });

        // Initialize JSON on page load
        updateJsonFromForm();
    });
})(jQuery);