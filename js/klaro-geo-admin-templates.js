/**
 * Klaro Geo Template Translations Tabs
 *
 * This file handles the tabbed interface for template translations.
 */
(function($) {
    // Wait for the DOM to be ready
    $(document).ready(function() {
        // Check if the page was just updated (after form submission)
        let urlParams = new URLSearchParams(window.location.search);
        let wasUpdated = urlParams.get('updated') === 'true';

        // After a save, bring the tabs back in line with the JSON editor.
        setTimeout(function() {
            // If the page was just updated, also explicitly update the form from JSON
            // This ensures translations are loaded from the JSON editor
            if (wasUpdated && $('#translations_json_editor').length) {
                setTimeout(function() {
                    // Force update from JSON to ensure deleted languages are removed from tabs
                    updateFormFromJson();

                    // If we have a JSON editor, make sure the form matches the JSON
                    if ($('#translations_json_editor').length) {
                        let jsonText = $('#translations_json_editor').val();
                        try {
                            if (jsonText && jsonText.trim() !== '') {
                                let translations = JSON.parse(jsonText);

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

        // The template being edited, as PHP shipped it.
        function getCurrentTemplate() {
            let currentTemplate = $('#template_selector').val() || $('#current_template').val();
            return klaroGeoTemplates.templates[currentTemplate] || null;
        }

        // Function to add a language tab
        function addLanguageTab(langCode, langName) {
            // Check if this language already exists
            if ($("#tab-" + langCode).length > 0) {
                return; // Tab already exists
            }

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

                // Process each property in the translations
                processTranslationObject(translations, langCode, '');
            } else {
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
                        let langName = klaroGeoAdminUi.languageName(langCode);
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
                        delete translations[langCode];

                        // Update the JSON editor with the modified data
                        $('#translations_json_editor').val(JSON.stringify(translations, null, 2));

                        // Auto-save the changes
                        autoSaveTranslations('Language "' + langCode + '" deleted successfully');
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
            let langName = klaroGeoAdminUi.languageName(langCode);

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
        // Format and Validate both parse the editor's text as it is. Invalid
        // JSON is reported, never rewritten: a "repair" by pattern cannot tell
        // JSON syntax from the text inside a translation.
        function parseJsonEditor() {
            try {
                return JSON.parse($('#translations_json_editor').val());
            } catch (e) {
                alert('Invalid JSON: ' + e.message + '\n\n' +
                    'Common issues to check:\n' +
                    '- Missing quotes around property names\n' +
                    '- Trailing commas at the end of lists or objects\n' +
                    '- Unescaped quotes in strings\n' +
                    '- Missing closing brackets or braces\n\n' +
                    'Fix it in the editor, or use "Update JSON from Form" to rebuild it from the fields above.');
                return null;
            }
        }

        $('#format_json').click(function() {
            let translations = parseJsonEditor();
            if (translations !== null) {
                $('#translations_json_editor').val(JSON.stringify(translations, null, 2));
                alert('JSON formatted successfully!');
            }
        });

        $('#validate_json').click(function() {
            if (parseJsonEditor() !== null) {
                alert('JSON is valid! You can save your changes now.');
            }
        });

        $('#update_form_from_json').click(function() {
            if (updateFormFromJson()) {
                klaroGeoAdminUi.showIndicator('success', 'Form updated from JSON successfully');
            }
        });

        $('#update_json_from_form').click(function() {
            updateJsonFromForm();
            klaroGeoAdminUi.showIndicator('success', 'JSON updated from form successfully');
        });

        // Set up auto-save when users exit translation fields
        $(document).on('blur', '.translation-tab input, .translation-tab textarea', function() {
            // Debounce the auto-save to prevent too many requests
            clearTimeout(window.translationAutoSaveTimer);
            window.translationAutoSaveTimer = setTimeout(function() {
                // Update the JSON from the form
                updateJsonFromForm();

                // Auto-save the translations
                autoSaveTranslations('Translations saved automatically', true); // true = quiet mode
            }, 1500); // Wait 1.5 seconds after the last field change
        });

        // Also auto-save when the JSON editor is changed
        $('#translations_json_editor').on('blur', function() {
            // Debounce the auto-save to prevent too many requests
            clearTimeout(window.translationJsonAutoSaveTimer);
            window.translationJsonAutoSaveTimer = setTimeout(function() {
                // Auto-save the translations
                autoSaveTranslations('JSON saved automatically', true); // true = quiet mode
            }, 1500); // Wait 1.5 seconds after the last edit
        });

        // The server reads the JSON editor before the per-language fields, so
        // bring the editor in step with the fields before the form is submitted
        // (this is what removes a deleted language). With no language fields
        // in the form, the editor is submitted as it was. The server decides
        // what to store when neither holds translations.
        $('form').on('submit', function() {
            let editor = $('#translations_json_editor');
            let editorJson = editor.val();

            if (Object.keys(updateJsonFromForm()).length === 0) {
                klaroGeoWarn('No translations found in the form, submitting the JSON editor as it was');
                editor.val(editorJson);
            }

            return true;
        });

        // AJAX function to save translations
        function saveTranslationsAjax(callback) {
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

            formData.append('nonce', $('#klaro_geo_nonce').val());

            // Send the AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // If we have a new JSON from the server, update our editor
                        if (response.data && response.data.translations_json) {
                            $('#translations_json_editor').val(response.data.translations_json);

                            // Update the form from the new JSON
                            updateFormFromJson();
                        }

                        callback(true, response);
                    } else {
                        klaroGeoError('Error saving translations:', response.data ? response.data.message : 'Unknown error');

                        callback(false, response);
                    }
                },
                error: function(xhr, status, error) {
                    klaroGeoError('AJAX error:', status, error);

                    callback(false, { success: false, data: { message: error } });
                }
            });
        }

        // Save the translations and show the result. In quiet mode a success is
        // shown only as a tick in the admin bar; errors are always shown.
        function autoSaveTranslations(message, quietMode) {
            let savingIndicator = quietMode ? null : klaroGeoAdminUi.showIndicator('saving', 'Saving translations...');

            saveTranslationsAjax(function(success, response) {
                if (savingIndicator) {
                    savingIndicator.remove();
                }

                if (!success) {
                    let errorMessage = response.data && response.data.message ? response.data.message : 'Error saving translations';
                    klaroGeoAdminUi.showIndicator('error', 'Error: ' + errorMessage);
                } else if (!quietMode) {
                    klaroGeoAdminUi.showIndicator('success', message);
                } else {
                    let miniIndicator = $('<div class="klaro-mini-indicator">✓</div>');
                    $('#wpadminbar').append(miniIndicator);
                    setTimeout(function() {
                        miniIndicator.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 1000);
                }
            });
        }

        // ===== Template selector, deletion and creation =====

        // Template page URL from PHP (passed via klaroGeoTemplates)
        let templatePageUrl = klaroGeoTemplates.templatePageUrl;

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
                    addLanguageTab(langCode, klaroGeoAdminUi.languageName(langCode));
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
