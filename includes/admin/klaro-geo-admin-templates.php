<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include the template settings class if not already included
if (!class_exists('Klaro_Geo_Template_Settings')) {
    require_once dirname(dirname(__FILE__)) . '/class-klaro-geo-template-settings.php';
}

/**
 * Sanitize an array recursively, properly handling boolean values
 *
 * @param array $array The array to sanitize
 * @return array The sanitized array
 */
function klaro_geo_sanitize_array($array) {
    $sanitized = array();

    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = klaro_geo_sanitize_array($value);
        } else {
            // Convert boolean values properly
            if ($value === 'true') {
                $sanitized[$key] = true;
            } else if ($value === 'false') {
                $sanitized[$key] = false;
            } else if ($value === 'on') {
                // Checkbox values come as 'on' when checked
                $sanitized[$key] = true;
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
    }

    return $sanitized;
}

// Templates page content
function klaro_geo_templates_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // Ensure the template settings are saved
    $template_settings->save();

    // Scripts are now enqueued in klaro-geo-admin-scripts.php

    // Add custom CSS for the template translations
    wp_add_inline_style('wp-admin', '
        .translation-json-buttons {
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .translation-json-buttons .button {
            margin-right: 10px;
        }
        #translations_json_editor {
            font-family: monospace;
            resize: vertical;
        }
        .translations-container {
            margin-bottom: 20px;
        }
        .template-config-container {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .template-creation-form {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .template-creation-buttons {
            margin-top: 15px;
        }
        .template-creation-buttons .button {
            margin-right: 10px;
        }
        #template_selector {
            margin-right: 10px;
        }
    ');

    // Get templates for JavaScript
    $templates = $template_settings->get();

    // Pass templates to JavaScript
    wp_localize_script(
        'klaro-geo-template-translations',
        'klaroGeoTemplates',
        array(
            'templates' => $templates,
            'timestamp' => time(), // Add timestamp to prevent caching issues
            'current_template' => $current_template, // Pass the current template ID
            'ajax_url' => admin_url('admin-ajax.php'), // Add AJAX URL for JavaScript
            'nonce' => wp_create_nonce('klaro_geo_template_nonce') // Add nonce for AJAX security
        )
    );

    // Save template changes
    if (isset($_POST['submit_template'])) {
        check_admin_referer('klaro_geo_template_nonce');
        klaro_geo_debug_log('Saving template changes...');

        $current_template = sanitize_text_field($_POST['current_template']);
        klaro_geo_debug_log('Current template: ' . $current_template);

        // Get the template configuration from POST data
        if (isset($_POST['template_config']) && is_array($_POST['template_config'])) {
            $template_config = $_POST['template_config']; // Assign the whole array
            klaro_geo_debug_log('Received template config: ' . print_r($template_config, true));

            // Check if translations_json is present in the POST data
            if (isset($template_config['translations_json']) && !empty($template_config['translations_json'])) {
                // Parse the JSON data
                $translations_json = $template_config['translations_json'];
                klaro_geo_debug_log('Translations JSON received: ' . $translations_json);

                // Clean the JSON string to prevent syntax errors
                $translations_json = trim($translations_json);

                // Fix escaped quotes that might be causing issues - but be careful not to strip too much
                $translations_json = stripslashes($translations_json);

                // Let's take a simpler approach to fix the JSON
                // First, let's make sure we have valid JSON structure

                // 1. Replace unquoted property names with quoted ones (only at the beginning of properties)
                $translations_json = preg_replace('/([{,])\s*([a-zA-Z0-9_]+)\s*:/', '$1"$2":', $translations_json);

                // 2. Remove trailing commas in objects and arrays
                $translations_json = preg_replace('/,\s*}/', '}', $translations_json);
                $translations_json = preg_replace('/,\s*]/', ']', $translations_json);

                // 3. Fix common contractions with apostrophes
                // Instead of using regex which can be error-prone, let's use simple string replacements
                $translations_json = str_replace('"we"d', '"we\'d', $translations_json);
                $translations_json = str_replace('"you"re', '"you\'re', $translations_json);
                $translations_json = str_replace('"don"t', '"don\'t', $translations_json);
                $translations_json = str_replace('"can"t', '"can\'t', $translations_json);
                $translations_json = str_replace('"won"t', '"won\'t', $translations_json);
                $translations_json = str_replace('"it"s', '"it\'s', $translations_json);
                $translations_json = str_replace('"that"s', '"that\'s', $translations_json);
                $translations_json = str_replace('"there"s', '"there\'s', $translations_json);
                $translations_json = str_replace('"what"s', '"what\'s', $translations_json);
                $translations_json = str_replace('"let"s', '"let\'s', $translations_json);

                // 4. Fix specific phrases in the description that commonly have apostrophes
                $translations_json = str_replace('we"d like to use', 'we\'d like to use', $translations_json);
                $translations_json = str_replace('You"re in charge', 'You\'re in charge', $translations_json);
                $translations_json = str_replace('That"s ok', 'That\'s ok', $translations_json);


                try {
                    // Log the JSON before attempting to decode
                    klaro_geo_debug_log('Attempting to parse JSON: ' . substr($translations_json, 0, 500) . (strlen($translations_json) > 500 ? '...' : ''));

                    // Try to decode the JSON
                    $translations = json_decode($translations_json, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($translations)) {
                        // Successfully parsed JSON, use it for translations
                        $template_config['translations'] = $translations;
                        klaro_geo_debug_log('Translations JSON parsed successfully: ' . print_r($translations, true));
                    } else {
                        // Try one more approach - use the built-in json_decode with ignoring errors
                        $translations = @json_decode($translations_json, true);
                        if ($translations !== null && is_array($translations)) {
                            $template_config['translations'] = $translations;
                            klaro_geo_debug_log('Translations JSON parsed successfully with error suppression');
                        } else {
                            // JSON parsing error
                            $error = json_last_error_msg();
                            klaro_geo_debug_log('Error parsing translations JSON: ' . $error);
                            klaro_geo_debug_log('JSON that failed to parse: ' . $translations_json);

                            // Add admin notice about JSON error
                            add_settings_error(
                                'klaro_geo_templates',
                                'json_parse_error',
                                'Error parsing translations JSON: ' . $error . '. Using fallback translations.',
                                'error'
                            );

                            // Try to fix common JSON errors
                            $fixed_json = $translations_json;

                            // Fix common contractions where apostrophes might be problematic
                            $common_contractions = array("we\"d", "you\"re", "don\"t", "can\"t", "won\"t", "it\"s", "that\"s", "there\"s", "he\"s", "she\"s", "what\"s", "let\"s", "who\"s", "here\"s", "didn\"t");
                            foreach ($common_contractions as $contraction) {
                                $fixed_json = str_replace($contraction, str_replace('"', "'", $contraction), $fixed_json);
                            }

                            // Properly escape apostrophes
                            $fixed_json = preg_replace('/"([^"]*?)\'([^"]*?)"/', '"$1\\\'$2"', $fixed_json);

                            // Replace single quotes with double quotes (only for string delimiters)
                            $fixed_json = preg_replace("/([{,]\s*\"[^\"]+\")\s*:\s*'([^']*)'/", '$1:"$2"', $fixed_json);

                            // Remove trailing commas
                            $fixed_json = preg_replace('/,\s*}/', '}', $fixed_json);
                            $fixed_json = preg_replace('/,\s*]/', ']', $fixed_json);

                            // Try parsing again
                            $translations = json_decode($fixed_json, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($translations)) {
                                // Successfully parsed fixed JSON
                                $template_config['translations'] = $translations;
                                klaro_geo_debug_log('Fixed JSON parsed successfully: ' . print_r($translations, true));
                            } else {
                                // Keep existing translations if available
                                $existing_template = $template_settings->get_template($current_template);
                                if ($existing_template && isset($existing_template['config']['translations'])) {
                                    $template_config['translations'] = $existing_template['config']['translations'];
                                    klaro_geo_debug_log('Using existing translations due to JSON parse error');
                                } else {
                                    // Create default translations
                                    $template_config['translations'] = array(
                                        'zz' => array(
                                            'consentModal' => array(
                                                'title' => 'Privacy Settings',
                                                'description' => 'Here you can assess and customize the services that we\'d like to use on this website.'
                                            ),
                                            'acceptAll' => 'Accept all',
                                            'decline' => 'I decline',
                                            'close' => 'Close'
                                        )
                                    );
                                    klaro_geo_debug_log('Created default translations due to JSON parse error');
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    klaro_geo_debug_log('Exception parsing translations JSON: ' . $e->getMessage());
                    // Keep existing translations if available
                    $existing_template = $template_settings->get_template($current_template);
                    if ($existing_template && isset($existing_template['config']['translations'])) {
                        $template_config['translations'] = $existing_template['config']['translations'];
                        klaro_geo_debug_log('Using existing translations due to exception');
                    } else {
                        // Create default translations
                        $template_config['translations'] = array(
                            'zz' => array(
                                'consentModal' => array(
                                    'title' => 'Privacy Settings',
                                    'description' => 'Here you can assess and customize the services that we\'d like to use on this website.'
                                ),
                                'acceptAll' => 'Accept all',
                                'decline' => 'I decline',
                                'close' => 'Close'
                            )
                        );
                        klaro_geo_debug_log('Created default translations due to exception');
                    }
                }
            } else if (isset($template_config['translations']) && is_array($template_config['translations'])) {
                // Use the translations array directly if available
                klaro_geo_debug_log('Translations array received: ' . print_r($template_config['translations'], true));
            } else {
                klaro_geo_debug_log('No translations received. Using existing translations if available.');
                // Keep existing translations if available
                $existing_template = $template_settings->get_template($current_template);
                if ($existing_template && isset($existing_template['config']['translations'])) {
                    $template_config['translations'] = $existing_template['config']['translations'];
                    klaro_geo_debug_log('Using existing translations');
                } else {
                    // Create default translations if none exist
                    $template_config['translations'] = array(
                        'zz' => array(
                            'consentModal' => array(
                                'title' => 'Privacy Settings',
                                'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'
                            ),
                            'acceptAll' => 'Accept all',
                            'decline' => 'I decline',
                            'close' => 'Close'
                        )
                    );
                    klaro_geo_debug_log('Created default translations');
                }
            }

            // Debug log the final translations
            klaro_geo_debug_log('Final translations to save: ' . print_r($template_config['translations'], true));

            // Remove translations_json, it is not needed in the saved config
            unset($template_config['translations_json']);

            // Sanitize the rest of the config values
            foreach ($_POST['template_config'] as $key => $value) {
                // Skip translations_json as we already processed it
                if ($key === 'translations_json') continue;
                // Skip translations as we already processed it
                if ($key === 'translations') continue;

                if (is_array($value)) {
                    // Special handling for consent mode settings
                    if ($key === 'consent_mode_settings') {
                        $template_config[$key] = array();

                        // Process each consent mode setting
                        foreach ($value as $setting_key => $setting_value) {
                            if ($setting_key === 'initialize_consent_mode') {
                                // Handle checkbox value
                                $template_config[$key][$setting_key] = isset($value['initialize_consent_mode']);
                            } else if ($setting_key === 'initialization_code') {
                                // Handle JavaScript code - don't sanitize too aggressively
                                $template_config[$key][$setting_key] = stripslashes($setting_value);
                            } else {
                                // Handle other settings normally
                                $template_config[$key][$setting_key] = sanitize_text_field($setting_value);
                            }
                        }
                    } else {
                        // Handle other nested arrays with recursive sanitization
                        $template_config[$key] = klaro_geo_sanitize_array($value);
                    }
                } else {
                    // Handle simple values
                    // Convert boolean values properly
                    if ($value === 'true') {
                        $template_config[$key] = true;
                    } else if ($value === 'false') {
                        $template_config[$key] = false;
                    } else if ($value === 'on') {
                        // Checkbox values come as 'on' when checked
                        $template_config[$key] = true;
                    } else {
                        $template_config[$key] = sanitize_text_field($value);
                    }
                }
            }

            // Update the template configuration using the class method
            $template_settings->set_template_config($current_template, $template_config);
        }

        // Get Plugin settings
        if (isset($_POST['plugin_settings']) && is_array($_POST['plugin_settings'])) {
            $plugin_settings = array();

            // Process enable_consent_logging setting
            $plugin_settings['enable_consent_logging'] = isset($_POST['plugin_settings']['enable_consent_logging']);

            // Get the existing template
            $template = $template_settings->get_template($current_template);

            // Update the plugin settings
            if ($template) {
                $template['plugin_settings'] = $plugin_settings;
                $template_settings->set_template($current_template, $template);
                klaro_geo_debug_log('Updated plugin_settings: ' . print_r($plugin_settings, true));
            }
        } else {
            // Set default plugin settings if not provided
            $template = $template_settings->get_template($current_template);
            if ($template) {
                $template['plugin_settings'] = array(
                    'enable_consent_logging' => true
                );
                $template_settings->set_template($current_template, $template);
                klaro_geo_debug_log('Set default plugin_settings');
            }
        }

        // Update inheritance
        if (isset($_POST['inherit_from'])) {
            $template = $template_settings->get_template($current_template);
            if ($template) {
                $template['inherit_from'] = sanitize_text_field($_POST['inherit_from']);
                $template_settings->set_template($current_template, $template);
            }
        }

        // Save all changes
        $template_settings->save();

        // Add success message
        add_settings_error(
            'klaro_geo_templates',
            'templates_updated',
            'Template settings saved successfully.',
            'updated'
        );

        // Set the current template to the one that was just saved
        $current_template = sanitize_text_field($_POST['current_template']);

        // Ensure the template data is properly loaded after saving
        $templates = $template_settings->get();

        // Force a redirect to ensure the page is properly refreshed with the new data
        if (!headers_sent()) {
            wp_redirect(add_query_arg(array(
                'page' => 'klaro-geo-templates',
                'template' => $current_template,
                'updated' => 'true',
                '_wpnonce' => wp_create_nonce('klaro_geo_template_nonce')
            ), admin_url('admin.php')));
            exit;
        }
    }

    // Get current templates
    $templates = $template_settings->get();
    $current_template = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'default';

    ?>
    <script type="text/javascript">
    // Make templates data available to JavaScript with timestamp for cache busting
    var klaroGeoTemplates = <?php echo json_encode(array('templates' => $templates, 'timestamp' => time())); ?>;
    </script>

    <div class="wrap">
        <h1>Klaro Templates</h1>
        <?php settings_errors('klaro_geo_templates'); ?>
        <form method="post" action="" id="template-form">
            <?php wp_nonce_field('klaro_geo_template_nonce'); ?>

            <!-- Add a hidden nonce field for AJAX requests -->
            <input type="hidden" name="klaro_geo_nonce" id="klaro_geo_nonce" value="<?php echo wp_create_nonce('klaro_geo_template_nonce'); ?>">

            <input type="hidden" name="current_template" id="current_template" value="<?php echo esc_attr($current_template); ?>">
            <input type="hidden" name="template_id" id="template_id" value="<?php echo esc_attr($current_template); ?>">
            <select name="template_selector" id="template_selector">
                <?php
                // Ensure default template is always first in the list
                if (isset($templates['default'])) {
                    $default_name = isset($templates['default']['name']) ? $templates['default']['name'] : 'Default Template';
                    ?>
                    <option value="default" <?php selected($current_template, 'default'); ?>>
                        <?php echo esc_html($default_name); ?>
                    </option>
                    <?php
                }

                // Add all other templates
                foreach ($templates as $key => $template) :
                    if ($key === 'default') continue; // Skip default as we already added it
                ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_template, $key); ?>>
                        <?php echo esc_html($template['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="add_template" class="button">Add New Template</button>
            <button type="button" id="delete_template" class="button button-secondary" style="margin-left: 10px;">Delete Template</button>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle template selection change
                $('#template_selector').on('change', function() {
                    var selectedTemplate = $(this).val();
                    console.log('Template selected:', selectedTemplate);

                    // Update the hidden input
                    $('#current_template').val(selectedTemplate);

                    // Redirect to the selected template
                    window.location.href = '<?php echo admin_url('admin.php?page=klaro-geo-templates'); ?>&template=' + selectedTemplate;
                });

                // Handle delete template button
                $('#delete_template').on('click', function() {
                    var selectedTemplate = $('#template_selector').val();

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
                                    window.location.href = '<?php echo admin_url('admin.php?page=klaro-geo-templates'); ?>';
                                } else {
                                    // Create a more user-friendly error message
                                    var errorMessage = 'Error deleting template: ' + response.data.message;

                                    // Show the error in a dialog for better readability
                                    var $dialog = $('<div></div>')
                                        .html(errorMessage)
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
                                var $dialog = $('<div></div>')
                                    .html('An error occurred while deleting the template: ' + error)
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

                // Initialize tabs when the page loads
                if ($('#translations-tabs').length) {
                    console.log('Initializing tabs on page load');
                    $('#translations-tabs').tabs();

                    // Add a small delay to ensure the DOM is ready
                    setTimeout(function() {
                        // Get the current template data
                        var currentTemplate = $('#current_template').val();
                        console.log('Current template on page load:', currentTemplate);

                        if (typeof klaroGeoTemplates !== 'undefined' &&
                            klaroGeoTemplates.templates &&
                            klaroGeoTemplates.templates[currentTemplate] &&
                            klaroGeoTemplates.templates[currentTemplate].config &&
                            klaroGeoTemplates.templates[currentTemplate].config.translations) {

                            var translations = klaroGeoTemplates.templates[currentTemplate].config.translations;
                            console.log('Found translations on page load:', translations);

                            // Get the purposes from the page
                            var purposes = [];
                            $('#tab-zz h5').each(function() {
                                purposes.push($(this).text().toLowerCase());
                            });
                            console.log('Found purposes on page:', purposes);

                            // Add tabs for each language
                            Object.keys(translations).forEach(function(langCode) {
                                if (langCode === 'zz') return; // Skip fallback language

                                // Get language name
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
                                var langName = langNames[langCode] || 'Custom';

                                // Check if tab already exists
                                if (!$('#tab-' + langCode).length) {
                                    console.log('Adding tab for language on page load:', langCode);

                                    // Add new tab
                                    var newTab = $('<li><a href="#tab-' + langCode + '">' + langName + ' (' + langCode + ')</a></li>');
                                    newTab.insertBefore($(".translations-tabs-nav li:last"));

                                    // Clone the fallback tab content
                                    var newContent = $("#tab-zz").clone();
                                    newContent.attr('id', 'tab-' + langCode);
                                    newContent.addClass('translation-tab'); // Make sure the class is added

                                    // Update heading
                                    var heading = newContent.find('h4:first');
                                    heading.text(langName + ' Translations (' + langCode + ')');
                                    heading.append(' <button type="button" class="button button-small delete-language-btn" data-lang="' + langCode + '">Delete Language</button>');

                                    // Update input names and IDs
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
                                    });

                                    // Add to DOM
                                    newContent.appendTo("#translations-tabs");
                                }

                                // Fill in values from translations
                                var langTranslations = translations[langCode];
                                fillTranslationValues(langCode, langTranslations);
                            });

                            // Refresh tabs
                            $('#translations-tabs').tabs('refresh');

                            // Restore active tab if it was saved
                            var savedTabIndex = localStorage.getItem('klaro_active_tab_index');
                            if (savedTabIndex !== null) {
                                try {
                                    $('#translations-tabs').tabs('option', 'active', parseInt(savedTabIndex));
                                    console.log('Restored active tab to index:', savedTabIndex);
                                    // Clear the saved index after using it
                                    localStorage.removeItem('klaro_active_tab_index');
                                } catch (e) {
                                    console.error('Error restoring active tab:', e);
                                }
                            }
                        }
                    }, 200);
                }

                // Helper function to fill in translation values
                function fillTranslationValues(langCode, translations, prefix) {
                    prefix = prefix || '';

                    for (var key in translations) {
                        if (translations.hasOwnProperty(key)) {
                            var value = translations[key];

                            if (typeof value === 'object' && value !== null) {
                                // Recursively process nested objects
                                fillTranslationValues(langCode, value, prefix ? prefix + '[' + key + ']' : key);
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
                        }
                    }
                }

                // Handle delete language button
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

                        // Update the JSON textarea
                        updateJsonFromForm();
                    }
                });

                // Function to update JSON from form
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

                    return translations;
                }

                // Handle form submission
                $('#template-form').on('submit', function(e) {
                    // Update the JSON from the form before submitting
                    updateJsonFromForm();

                    // Store the current active tab index to restore it after page reload
                    if ($('#translations-tabs').length) {
                        var activeTabIndex = $('#translations-tabs').tabs('option', 'active');
                        localStorage.setItem('klaro_active_tab_index', activeTabIndex);
                    }

                    // Continue with form submission
                    return true;
                });

                // Restore active tab after page load if it was saved
                if ($('#translations-tabs').length) {
                    var savedTabIndex = localStorage.getItem('klaro_active_tab_index');
                    if (savedTabIndex !== null) {
                        // Set a timeout to ensure tabs are fully initialized
                        setTimeout(function() {
                            $('#translations-tabs').tabs('option', 'active', parseInt(savedTabIndex));
                            // Clear the saved index after using it
                            localStorage.removeItem('klaro_active_tab_index');
                        }, 300);
                    }
                }
            });
            </script>

            <div id="template_creation_container">
                     <?php
                    // Add nonce for AJAX
                    wp_nonce_field('klaro_geo_template_nonce', 'klaro_geo_template_nonce');
                    ?>
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#add_template').click(function() {
                            // Hide the main template config temporarily
                            $('#template_config').hide();

                            // Show the new template form
                            var newTemplateForm = $('<div id="new_template_form" class="template-creation-form">' +
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
                                var templateName = $('#new_template_name').val();
                                var inheritFrom = $('#inherit_from_template').val();

                                if (!templateName) {
                                    alert('Please enter a template name');
                                    return;
                                }

                                // Create new template by copying selected template's settings
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'create_klaro_template',
                                        template_name: templateName,
                                        inherit_from: inheritFrom,
                                        nonce: $('#klaro_geo_template_nonce').val()
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            // Add new template to selector and select it
                                            var option = new Option(templateName, response.data.template_key);
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
                    </script>
                    <?php
                     $current_config = $templates[$current_template]['config'];
                     ?>
            </div>

            <!-- Template configuration form -->
            <div id="template_config" class="template-config-container">
                <h2>Template Configuration: <?php echo esc_html($templates[$current_template]['name']); ?></h2>

                <?php if (isset($templates[$current_template]['inherit_from'])): ?>
                <div class="notice notice-info">
                    <p>This template inherits settings from:
                        <?php echo esc_html($templates[$templates[$current_template]['inherit_from']]['name']); ?>
                    </p>
                </div>
                <?php endif; ?>

                <h3>Basic Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="template_config_version">Version:</label></th>
                        <td>
                            <input type="number" name="template_config[version]" id="template_config_version" class="small-text"
                                value="<?php echo esc_attr(isset($current_config['version']) ? $current_config['version'] : 1); ?>">
                            <p class="description">Klaro configuration version.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_elementID">Element ID:</label></th>
                        <td>
                            <input type="text" name="template_config[elementID]" id="template_config_elementID" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['elementID']) ? $current_config['elementID'] : 'klaro'); ?>">
                            <p class="description">HTML element ID for the Klaro container.</p>
                        </td>
                    </tr>
                </table>

                <h3>Styling</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="template_config_styling_theme_color">Theme Color:</label></th>
                        <td>
                            <select name="template_config[styling][theme][color]" id="template_config_styling_theme_color">
                                <option value="light" <?php selected(isset($current_config['styling']['theme']['color']) ? $current_config['styling']['theme']['color'] : 'light', 'light'); ?>>Light</option>
                                <option value="dark" <?php selected(isset($current_config['styling']['theme']['color']) ? $current_config['styling']['theme']['color'] : 'light', 'dark'); ?>>Dark</option>
                            </select>
                            <p class="description">Choose between light and dark theme.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_styling_theme_position">Position:</label></th>
                        <td>
                            <select name="template_config[styling][theme][position]" id="template_config_styling_theme_position">
                                <option value="top" <?php selected(isset($current_config['styling']['theme']['position']) ? $current_config['styling']['theme']['position'] : 'top', 'top'); ?>>Top</option>
                                <option value="bottom" <?php selected(isset($current_config['styling']['theme']['position']) ? $current_config['styling']['theme']['position'] : 'top', 'bottom'); ?>>Bottom</option>
                            </select>
                            <p class="description">Position of the consent notice.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_styling_theme_width">Width:</label></th>
                        <td>
                            <select name="template_config[styling][theme][width]" id="template_config_styling_theme_width">
                                <option value="wide" <?php selected(isset($current_config['styling']['theme']['width']) ? $current_config['styling']['theme']['width'] : 'wide', 'wide'); ?>>Wide</option>
                                <option value="narrow" <?php selected(isset($current_config['styling']['theme']['width']) ? $current_config['styling']['theme']['width'] : 'wide', 'narrow'); ?>>Narrow</option>
                            </select>
                            <p class="description">Width of the consent modal.</p>
                        </td>
                    </tr>
                </table>

                <h3>Behavior Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="template_config_default">Default State:</label></th>
                        <td>
                            <select name="template_config[default]" id="template_config_default">
                                <option value="true" <?php selected(isset($current_config['default']) && ($current_config['default'] === true || $current_config['default'] === 'true'), true); ?>>Accepted (Opt-Out)</option>
                                <option value="false" <?php selected(isset($current_config['default']) && ($current_config['default'] === false || $current_config['default'] === 'false'), true); ?>>Declined (Opt-In)</option>
                            </select>
                            <p class="description">Default state for services if the user doesn't make a choice.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_required">Required:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[required]" value="false">
                            <input type="checkbox" name="template_config[required]" id="template_config_required" value="true"
                                <?php checked(isset($current_config['required']) ? $current_config['required'] : true); ?>>
                            <p class="description">	
                            When enabled, users cannot decline services. Only use for essential services that are required for your website to function. This setting can also be overridden per-service.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_htmlTexts">HTML Texts:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[htmlTexts]" value="false">
                            <input type="checkbox" name="template_config[htmlTexts]" id="template_config_htmlTexts" value="true"
                                <?php checked(isset($current_config['htmlTexts']) ? $current_config['htmlTexts'] : true); ?>>
                            <p class="description">Allow HTML in text fields.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_embedded">Embedded Mode:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[embedded]" value="false">
                            <input type="checkbox" name="template_config[embedded]" id="template_config_embedded" value="true"
                                <?php checked(isset($current_config['embedded']) ? $current_config['embedded'] : false); ?>>
                            <p class="description">If enabled, Klaro will render without the modal background, allowing you to embed it into a specific element of your website, such as your privacy notice.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_noAutoLoad">No Auto Load:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[noAutoLoad]" value="false">
                            <input type="checkbox" name="template_config[noAutoLoad]" id="template_config_noAutoLoad" value="true"
                                <?php checked(isset($current_config['noAutoLoad']) ? $current_config['noAutoLoad'] : false); ?>>
                            <p class="description">If enabled, Klaro will not automatically load itself when the page is being loaded. You'll need to manually trigger it.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_autoFocus">Auto Focus:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[autoFocus]" value="false">
                            <input type="checkbox" name="template_config[autoFocus]" id="template_config_autoFocus" value="true"
                                <?php checked(isset($current_config['autoFocus']) ? $current_config['autoFocus'] : false); ?>>
                            <p class="description">Automatically focus the consent modal when it appears.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_groupByPurpose">Group by Purpose:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[groupByPurpose]" value="false">
                            <input type="checkbox" name="template_config[groupByPurpose]" id="template_config_groupByPurpose" value="true"
                                <?php checked(isset($current_config['groupByPurpose']) ? $current_config['groupByPurpose'] : true); ?>>
                            <p class="description">Group services by their purpose in the consent modal.</p>
                        </td>
                    </tr>
                </table>

                <h3>Cookie Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="template_config_storageMethod">Storage Method:</label></th>
                        <td>
                            <select name="template_config[storageMethod]" id="template_config_storageMethod">
                                <option value="cookie" <?php selected(isset($current_config['storageMethod']) ? $current_config['storageMethod'] : 'cookie', 'cookie'); ?>>Cookie</option>
                                <option value="localStorage" <?php selected(isset($current_config['storageMethod']) ? $current_config['storageMethod'] : 'cookie', 'localStorage'); ?>>Local Storage</option>
                            </select>
                            <p class="description">Method used to store consent information.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_cookieName">Cookie Name:</label></th>
                        <td>
                            <input type="text" name="template_config[cookieName]" id="template_config_cookieName" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['cookieName']) ? $current_config['cookieName'] : 'klaro'); ?>">
                            <p class="description">Name of the cookie used to store consent information.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_cookieExpiresAfterDays">Cookie Expires (days):</label></th>
                        <td>
                            <input type="number" name="template_config[cookieExpiresAfterDays]" id="template_config_cookieExpiresAfterDays" class="small-text"
                                value="<?php echo esc_attr(isset($current_config['cookieExpiresAfterDays']) ? $current_config['cookieExpiresAfterDays'] : 365); ?>">
                            <p class="description">Number of days after which the cookie expires.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_cookieDomain">Cookie Domain:</label></th>
                        <td>
                            <input type="text" name="template_config[cookieDomain]" id="template_config_cookieDomain" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['cookieDomain']) ? $current_config['cookieDomain'] : ''); ?>">
                            <p class="description">Domain for the consent cookie. Leave empty to auto-detect from site URL with a leading dot (e.g., ".example.com") for subdomain sharing. Enter a value to use it exactly as specified.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_cookiePath">Cookie Path:</label></th>
                        <td>
                            <input type="text" name="template_config[cookiePath]" id="template_config_cookiePath" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['cookiePath']) ? $current_config['cookiePath'] : '/'); ?>">
                            <p class="description">Path for the consent cookie (default: '/').</p>
                        </td>
                    </tr>
                </table>

                <h3>Consent Modal Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="template_config_mustConsent">Must Consent:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[mustConsent]" value="false">
                            <input type="checkbox" name="template_config[mustConsent]" id="template_config_mustConsent" value="true"
                                <?php checked(isset($current_config['mustConsent']) ? $current_config['mustConsent'] : false); ?>>
                            <p class="description">If enabled, users must make a choice before using the site.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_acceptAll">Accept All:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[acceptAll]" value="false">
                            <input type="checkbox" name="template_config[acceptAll]" id="template_config_acceptAll" value="true"
                                <?php checked(isset($current_config['acceptAll']) ? $current_config['acceptAll'] : true); ?>>
                            <p class="description">Show an "Accept All" button.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_hideDeclineAll">Hide Decline All:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[hideDeclineAll]" value="false">
                            <input type="checkbox" name="template_config[hideDeclineAll]" id="template_config_hideDeclineAll" value="true"
                                <?php checked(isset($current_config['hideDeclineAll']) ? $current_config['hideDeclineAll'] : false); ?>>
                            <p class="description">Hide the "Decline All" button.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_hideLearnMore">Hide Learn More:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[hideLearnMore]" value="false">
                            <input type="checkbox" name="template_config[hideLearnMore]" id="template_config_hideLearnMore" value="true"
                                <?php checked(isset($current_config['hideLearnMore']) ? $current_config['hideLearnMore'] : false); ?>>
                            <p class="description">Hide the "Learn More" link.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_showNoticeTitle">Show Notice Title:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[showNoticeTitle]" value="false">
                            <input type="checkbox" name="template_config[showNoticeTitle]" id="template_config_showNoticeTitle" value="true"
                                <?php checked(isset($current_config['showNoticeTitle']) ? $current_config['showNoticeTitle'] : false); ?>>
                            <p class="description">Show the title in the consent notice.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_showDescriptionEmptyStore">Show Description for Empty Store:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[showDescriptionEmptyStore]" value="false">
                            <input type="checkbox" name="template_config[showDescriptionEmptyStore]" id="template_config_showDescriptionEmptyStore" value="true"
                                <?php checked(isset($current_config['showDescriptionEmptyStore']) ? $current_config['showDescriptionEmptyStore'] : true); ?>>
                            <p class="description">Show description text even when no services are defined.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_disablePoweredBy">Disable Powered By:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[disablePoweredBy]" value="false">
                            <input type="checkbox" name="template_config[disablePoweredBy]" id="template_config_disablePoweredBy" value="true"
                                <?php checked(isset($current_config['disablePoweredBy']) ? $current_config['disablePoweredBy'] : false); ?>>
                            <p class="description">Hide the "Powered by Klaro" text.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_additionalClass">Additional CSS Class:</label></th>
                        <td>
                            <input type="text" name="template_config[additionalClass]" id="template_config_additionalClass" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['additionalClass']) ? $current_config['additionalClass'] : ''); ?>">
                            <p class="description">Additional CSS class to add to the consent modal.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="template_config_lang">Default Language:</label></th>
                        <td>
                            <input type="text" name="template_config[lang]" id="template_config_lang" class="regular-text"
                                value="<?php echo esc_attr(isset($current_config['lang']) ? $current_config['lang'] : ''); ?>">
                            <p class="description">Default language code (e.g., 'en', 'de'). Leave empty to use the fallback language and translation settings ('zz').</p>
                        </td>
                    </tr>
                </table>
                <h3>Consent Mode Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="consent_mode_settings_initialize_consent_mode">Initialize Consent Mode:</label></th>
                        <td>
                            <!-- Hidden field to ensure the value is sent even when unchecked -->
                            <input type="hidden" name="template_config[consent_mode_settings][initialize_consent_mode]" value="false">
                            <input type="checkbox" name="template_config[consent_mode_settings][initialize_consent_mode]" id="consent_mode_settings_initialize_consent_mode" value="true"
                                <?php checked(isset($current_config['consent_mode_settings']['initialize_consent_mode']) ? filter_var($current_config['consent_mode_settings']['initialize_consent_mode'], FILTER_VALIDATE_BOOLEAN) : false); ?>>
                            <p class="description">Initialize all consent signals (denied) when Google Tag Manager loads and enable other consent mode operations.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="consent_mode_settings_analytics_storage_service">Map analytics_storage to service:</label></th>
                        <td>
                            <select name="template_config[consent_mode_settings][analytics_storage_service]" id="consent_mode_settings_analytics_storage_service">
                                <option value="no_service" <?php selected(isset($current_config['consent_mode_settings']['analytics_storage_service']) ? $current_config['consent_mode_settings']['analytics_storage_service'] : 'no_service', 'no_service'); ?>>No service</option>
                                <?php
                                // Get services for dropdown
                                $service_settings = new Klaro_Geo_Service_Settings();
                                $services = $service_settings->get();
                                foreach ($services as $service) {
                                    if (isset($service['name'])) {
                                        echo '<option value="' . esc_attr($service['name']) . '" ' .
                                            selected(isset($current_config['consent_mode_settings']['analytics_storage_service']) ? $current_config['consent_mode_settings']['analytics_storage_service'] : 'no_service', $service['name'], false) . '>' .
                                            esc_html($service['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">Select the service that enables or disables analytics_storage.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ad_storage_service">Map ad signals to service:</label></th>
                        <td>
                            <select name="template_config[consent_mode_settings][ad_storage_service]" id="ad_storage_service">
                                <option value="no_service" <?php selected(isset($current_config['consent_mode_settings']['ad_storage_service']) ? $current_config['consent_mode_settings']['ad_storage_service'] : 'no_service', 'no_service'); ?>>No service</option>
                                <?php
                                // Reuse services from above
                                foreach ($services as $service) {
                                    if (isset($service['name'])) {
                                        echo '<option value="' . esc_attr($service['name']) . '" ' .
                                            selected(isset($current_config['consent_mode_settings']['ad_storage_service']) ? $current_config['consent_mode_settings']['ad_storage_service'] : 'no_service', $service['name'], false) . '>' .
                                            esc_html($service['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">Select the service that enables or disables `ads_storage` and under which `ad_personalization` and `ad_user_data` controls get injected.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="consent_mode_settings_initialization_code">Consent Mode Initialization Code:</label></th>
                        <td>
                            <textarea name="template_config[consent_mode_settings][initialization_code]" id="consent_mode_settings_initialization_code" rows="8" cols="50" class="large-text code"><?php
                                $default_code = "window.dataLayer = window.dataLayer || [];\nwindow.gtag = function(){dataLayer.push(arguments)};\ngtag('consent', 'default', {\n'ad_storage': 'denied', \n'analytics_storage': 'denied', \n'ad_user_data': 'denied', \n'ad_personalization': 'denied'});\ngtag('set', 'ads_data_redaction', true);";

                                if (isset($current_config['consent_mode_settings']['initialization_code'])) {
                                    // Make sure to strip any slashes that might have been added
                                    $code = stripslashes($current_config['consent_mode_settings']['initialization_code']);
                                } else {
                                    $code = $default_code;
                                }

                                echo esc_textarea($code);
                            ?></textarea>
                            <p class="description">JavaScript code to initialize Google Consent Mode v2. This code will run when Google Tag Manager loads.</p>
                        </td>
                    </tr>
                </table>
                <h3>Plugin Settings</h3>
                <p>Template-level settings not related to Klaro-specific functionality.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_consent_logging">Enable Consent Logging:</label></th>
                        <td>
                            <input type="checkbox" name="plugin_settings[enable_consent_logging]" id="enable_consent_logging"
                                <?php checked(isset($templates[$current_template]['plugin_settings']['enable_consent_logging']) ? $templates[$current_template]['plugin_settings']['enable_consent_logging'] : true); ?>>
                            <p class="description">Log consent choices for this template in the WordPress database.</p>
                        </td>
                    </tr>
                </table>

                <h3>Translations</h3>
                <div class="translations-container">
                    <div id="translations-tabs">
                        <ul class="translations-tabs-nav">
                            <li><a href="#tab-zz">Fallback (zz)</a></li>
                            <li><a href="#tab-add">Add Translation</a></li>
                        </ul>

                        <div id="tab-zz" class="translation-tab">
                            <h4>Fallback Translations (zz)</h4>
                            <p class="description">These settings configure the default language (zz) for your consent banner.</p>

                            <h4>Consent Modal</h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_consentModal_title">Title:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentModal][title]" id="template_config_translations_zz_consentModal_title" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['consentModal']['title']) ? $current_config['translations']['zz']['consentModal']['title'] : 'Privacy Settings'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_consentModal_description">Description:</label></th>
                                    <td>
                                        <textarea name="template_config[translations][zz][consentModal][description]" id="template_config_translations_zz_consentModal_description" rows="4" class="large-text"><?php echo esc_textarea(isset($current_config['translations']['zz']['consentModal']['description']) ? $current_config['translations']['zz']['consentModal']['description'] : 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.'); ?></textarea>
                                    </td>
                                </tr>
                            </table>

                            <h4>Consent Notice</h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_consentNotice_title">Title:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][title]" id="template_config_translations_zz_consentNotice_title" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['consentNotice']['title']) ? $current_config['translations']['zz']['consentNotice']['title'] : 'Cookie Consent'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_consentNotice_description">Description:</label></th>
                                    <td>
                                        <textarea name="template_config[translations][zz][consentNotice][description]" id="template_config_translations_zz_consentNotice_description" rows="4" class="large-text"><?php echo esc_textarea(isset($current_config['translations']['zz']['consentNotice']['description']) ? $current_config['translations']['zz']['consentNotice']['description'] : 'Hi! Could we please enable some additional services for {purposes}? You can always change or withdraw your consent later.'); ?></textarea>
                                        <p class="description">Use {purposes} as a placeholder for the list of purposes.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_consentNotice_changeDescription">Change Description:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][changeDescription]" id="template_config_translations_zz_consentNotice_changeDescription" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['consentNotice']['changeDescription']) ? $current_config['translations']['zz']['consentNotice']['changeDescription'] : 'There were changes since your last visit, please renew your consent.'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_consentNotice_learnMore">Learn More Text:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][consentNotice][learnMore]" id="template_config_translations_zz_consentNotice_learnMore" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['consentNotice']['learnMore']) ? $current_config['translations']['zz']['consentNotice']['learnMore'] : 'Let me choose'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h4>Privacy Policy</h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_privacyPolicyUrl">Privacy Policy URL:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicyUrl]" id="template_config_translations_zz_privacyPolicyUrl" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['privacyPolicyUrl']) ? $current_config['translations']['zz']['privacyPolicyUrl'] : '/privacy'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_privacyPolicy_name">Policy Name:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicy][name]" id="template_config_translations_zz_privacyPolicy_name" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['privacyPolicy']['name']) ? $current_config['translations']['zz']['privacyPolicy']['name'] : 'privacy policy'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_privacyPolicy_text">Policy Text:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][privacyPolicy][text]" id="template_config_translations_zz_privacyPolicy_text" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['privacyPolicy']['text']) ? $current_config['translations']['zz']['privacyPolicy']['text'] : 'To learn more, please read our {privacyPolicy}.'); ?>">
                                        <p class="description">Use {privacyPolicy} as a placeholder for the privacy policy link.</p>
                                    </td>
                                </tr>
                            </table>

                            <h4>Buttons</h4>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_acceptAll">Accept All:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][acceptAll]" id="template_config_translations_zz_acceptAll" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['acceptAll']) ? $current_config['translations']['zz']['acceptAll'] : 'Accept all'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_acceptSelected">Accept Selected:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][acceptSelected]" id="template_config_translations_zz_acceptSelected" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['acceptSelected']) ? $current_config['translations']['zz']['acceptSelected'] : 'Accept selected'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_decline">Decline:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][decline]" id="template_config_translations_zz_decline" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['decline']) ? $current_config['translations']['zz']['decline'] : 'I decline'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_ok">OK:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][ok]" id="template_config_translations_zz_ok" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['ok']) ? $current_config['translations']['zz']['ok'] : 'That\'s ok'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_save">Save:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][save]" id="template_config_translations_zz_save" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['save']) ? $current_config['translations']['zz']['save'] : 'Save'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_close">Close:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][close]" id="template_config_translations_zz_close" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['close']) ? $current_config['translations']['zz']['close'] : 'Close'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_poweredBy">Powered By:</label></th>
                                    <td>
                                        <input type="text" name="template_config[translations][zz][poweredBy]" id="template_config_translations_zz_poweredBy" class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['poweredBy']) ? $current_config['translations']['zz']['poweredBy'] : 'Realized with Klaro!'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h4>Purposes</h4>
                            <p class="description">Configure the text for each purpose category.</p>

                            <?php
                            // Get all defined purposes from the option
                            $purposes_string = get_option('klaro_geo_purposes', 'functional,analytics,advertising');
                            $purposes = array_map('trim', explode(',', $purposes_string));

                            // Default descriptions for standard purposes
                            $default_descriptions = array(
                                'functional' => 'These services are essential for the correct functioning of this website. You cannot disable them here as the service would not work correctly otherwise.',
                                'analytics' => 'These services process personal information to help us understand how visitors interact with the website.',
                                'advertising' => 'These services process personal information to show you personalized or interest-based advertisements.'
                            );

                            // Loop through each purpose and create form fields
                            foreach ($purposes as $purpose) {
                                $purpose_key = sanitize_key($purpose); // Sanitize for use in HTML IDs
                                $purpose_title = ucfirst($purpose); // Capitalize the first letter for default title

                                // Get default description if it exists, otherwise use empty string
                                $default_description = isset($default_descriptions[$purpose_key]) ? $default_descriptions[$purpose_key] : '';

                                // Get saved values if they exist
                                $saved_title = isset($current_config['translations']['zz']['purposes'][$purpose_key]['title'])
                                    ? $current_config['translations']['zz']['purposes'][$purpose_key]['title']
                                    : $purpose_title;

                                $saved_description = isset($current_config['translations']['zz']['purposes'][$purpose_key]['description'])
                                    ? $current_config['translations']['zz']['purposes'][$purpose_key]['description']
                                    : $default_description;

                                ?>
                                <h5><?php echo esc_html($purpose_title); ?></h5>
                                <table class="form-table">
                                    <tr>
                                        <th><label for="template_config_translations_zz_purposes_<?php echo esc_attr($purpose_key); ?>_title">Title:</label></th>
                                        <td>
                                            <input type="text"
                                                name="template_config[translations][zz][purposes][<?php echo esc_attr($purpose_key); ?>][title]"
                                                id="template_config_translations_zz_purposes_<?php echo esc_attr($purpose_key); ?>_title"
                                                class="regular-text"
                                                value="<?php echo esc_attr($saved_title); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="template_config_translations_zz_purposes_<?php echo esc_attr($purpose_key); ?>_description">Description:</label></th>
                                        <td>
                                            <textarea
                                                name="template_config[translations][zz][purposes][<?php echo esc_attr($purpose_key); ?>][description]"
                                                id="template_config_translations_zz_purposes_<?php echo esc_attr($purpose_key); ?>_description"
                                                rows="3"
                                                class="large-text"><?php echo esc_textarea($saved_description); ?></textarea>
                                        </td>
                                    </tr>
                                </table>
                            <?php } ?>

                            <h4>Service Translations</h4>
                            <p class="description">Configure the global service-related translations that apply to all services.</p>

                            <h5>Service Controls</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_service_disableAll_title">Disable All Title:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][disableAll][title]"
                                            id="template_config_translations_zz_service_disableAll_title"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['disableAll']['title']) ? $current_config['translations']['zz']['service']['disableAll']['title'] : 'Enable or disable all services'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_disableAll_description">Disable All Description:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][disableAll][description]"
                                            id="template_config_translations_zz_service_disableAll_description"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['disableAll']['description']) ? $current_config['translations']['zz']['service']['disableAll']['description'] : 'Use this switch to enable or disable all services.'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h5>Opt-Out Services</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_service_optOut_title">Opt-Out Title:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][optOut][title]"
                                            id="template_config_translations_zz_service_optOut_title"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['optOut']['title']) ? $current_config['translations']['zz']['service']['optOut']['title'] : '(opt-out)'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_optOut_description">Opt-Out Description:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][optOut][description]"
                                            id="template_config_translations_zz_service_optOut_description"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['optOut']['description']) ? $current_config['translations']['zz']['service']['optOut']['description'] : 'This service is loaded by default (but you can opt out)'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h5>Required Services</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_service_required_title">Required Title:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][required][title]"
                                            id="template_config_translations_zz_service_required_title"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['required']['title']) ? $current_config['translations']['zz']['service']['required']['title'] : '(always required)'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_required_description">Required Description:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][required][description]"
                                            id="template_config_translations_zz_service_required_description"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['required']['description']) ? $current_config['translations']['zz']['service']['required']['description'] : 'This service is always required'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h5>Purpose Labels</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_service_purpose">Purpose (Singular):</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][purpose]"
                                            id="template_config_translations_zz_service_purpose"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['purpose']) ? $current_config['translations']['zz']['service']['purpose'] : 'purpose'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_purposes">Purposes (Plural):</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][purposes]"
                                            id="template_config_translations_zz_service_purposes"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['purposes']) ? $current_config['translations']['zz']['service']['purposes'] : 'purposes'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h5>Purpose Item Labels</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_purposeItem_service">Service (Singular):</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][purposeItem][service]"
                                            id="template_config_translations_zz_purposeItem_service"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['purposeItem']['service']) ? $current_config['translations']['zz']['purposeItem']['service'] : 'service'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_purposeItem_services">Services (Plural):</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][purposeItem][services]"
                                            id="template_config_translations_zz_purposeItem_services"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['purposeItem']['services']) ? $current_config['translations']['zz']['purposeItem']['services'] : 'services'); ?>">
                                    </td>
                                </tr>
                            </table>

                            <h5>Contextual Consent</h5>
                            <table class="form-table">
                                <tr>
                                    <th><label for="template_config_translations_zz_service_contextualConsent_description">Description:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][contextualConsent][description]"
                                            id="template_config_translations_zz_service_contextualConsent_description"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['contextualConsent']['description']) ? $current_config['translations']['zz']['service']['contextualConsent']['description'] : 'Would you like to consent to {title}?'); ?>">
                                        <p class="description">Use {title} as a placeholder for the service title.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_contextualConsent_acceptOnce">Accept Once:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][contextualConsent][acceptOnce]"
                                            id="template_config_translations_zz_service_contextualConsent_acceptOnce"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['contextualConsent']['acceptOnce']) ? $current_config['translations']['zz']['service']['contextualConsent']['acceptOnce'] : 'Yes'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="template_config_translations_zz_service_contextualConsent_acceptAlways">Accept Always:</label></th>
                                    <td>
                                        <input type="text"
                                            name="template_config[translations][zz][service][contextualConsent][acceptAlways]"
                                            id="template_config_translations_zz_service_contextualConsent_acceptAlways"
                                            class="regular-text"
                                            value="<?php echo esc_attr(isset($current_config['translations']['zz']['service']['contextualConsent']['acceptAlways']) ? $current_config['translations']['zz']['service']['contextualConsent']['acceptAlways'] : 'Always'); ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div id="tab-add" class="translation-tab">
                            <p class="description">Add a new language translation to your consent banner.</p>

                            <table class="form-table">
                                <tr>
                                    <th><label for="new_language_code">Language Code:</label></th>
                                    <td>
                                        <select id="new_language_code" class="regular-text">
                                            <option value="">Select a language...</option>
                                            <option value="en">English (en)</option>
                                            <option value="de">German (de)</option>
                                            <option value="fr">French (fr)</option>
                                            <option value="es">Spanish (es)</option>
                                            <option value="it">Italian (it)</option>
                                            <option value="nl">Dutch (nl)</option>
                                            <option value="pt">Portuguese (pt)</option>
                                            <option value="sv">Swedish (sv)</option>
                                            <option value="no">Norwegian (no)</option>
                                            <option value="da">Danish (da)</option>
                                            <option value="fi">Finnish (fi)</option>
                                            <option value="pl">Polish (pl)</option>
                                            <option value="ru">Russian (ru)</option>
                                            <option value="ja">Japanese (ja)</option>
                                            <option value="zh">Chinese (zh)</option>
                                            <option value="ar">Arabic (ar)</option>
                                        </select>
                                        <p class="description">Select the language code for the new translation.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="copy_from_language">Copy From:</label></th>
                                    <td>
                                        <select id="copy_from_language" class="regular-text">
                                            <option value="zz">Default Language (zz)</option>
                                            <?php
                                            if (isset($current_config['translations']) && is_array($current_config['translations'])) {
                                                foreach ($current_config['translations'] as $lang_code => $translation) {
                                                    if ($lang_code !== 'zz') {
                                                        echo '<option value="' . esc_attr($lang_code) . '">' . esc_html($lang_code) . '</option>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </select>
                                        <p class="description">Select which language to copy translations from.</p>
                                    </td>
                                </tr>
                            </table>

                            <p>
                                <button type="button" id="add_language_translation" class="button button-primary">Add Language Translation</button>
                            </p>
                        </div>
                    </div>

                    <div class="advanced-translations-section">
                        <h4>Advanced Translations (JSON)</h4>
                        <div class="translation-json-buttons">
                            <button type="button" class="button" id="format_json">Format JSON</button>
                            <button type="button" class="button" id="validate_json">Validate JSON</button>
                            <button type="button" class="button" id="update_form_from_json">Update Form from JSON</button>
                            <button type="button" class="button" id="update_json_from_form">Update JSON from Form</button>
                            <button type="button" class="button" id="debug_translations">Debug Translations</button>
                        </div>
                    </div>
                    <textarea id="translations_json_editor" name="template_config[translations_json]" rows="15" class="large-text code"><?php
                        if (isset($current_config['translations'])) {
                            // Use JSON_PRETTY_PRINT, JSON_UNESCAPED_UNICODE, and JSON_UNESCAPED_SLASHES for better formatting
                            $json = json_encode($current_config['translations'],
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                            // Don't add any additional escaping that might break the JSON
                            // Just output the JSON as is
                            echo esc_textarea($json);
                        }
                    ?></textarea>
                    <p class="description">Advanced JSON editor for translations. Changes here will override the basic translations when saved.</p>
                </div>

                <!-- JavaScript functionality is handled by klaro-geo-template-translations.js -->
            </div>

            <input type="submit" name="submit_template" class="button button-primary" value="Save Template">
        </form>
    </div>
    <?php
}

// Helper function to create default templates
function klaro_geo_create_templates() {
    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // The constructor already initializes with default templates if empty
    // Just make sure to save any changes
    $template_settings->save();

    return $template_settings->get();
}

// AJAX handler for template creation
function klaro_geo_create_template() {
    check_ajax_referer('klaro_geo_template_nonce', 'nonce');

    $template_name = sanitize_text_field($_POST['template_name']);
    $inherit_from = sanitize_text_field($_POST['inherit_from']);

    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // Get existing templates
    $templates = $template_settings->get();

    // Check if template name already exists
    foreach ($templates as $template) {
        if (strcasecmp($template['name'], $template_name) === 0) {
            wp_send_json_error(array(
                'message' => 'A template with this name already exists. Please choose a different name.'
            ));
            return;
        }
    }

    // Generate a unique key for the template
    $template_key = sanitize_title($template_name);
    $original_key = $template_key;
    $counter = 1;
    while (isset($templates[$template_key])) {
        $template_key = $original_key . '-' . $counter;
        $counter++;
    }

    // Validate inherited template exists
    if (!isset($templates[$inherit_from])) {
        wp_send_json_error(array(
            'message' => 'Selected template to copy from does not exist.'
        ));
        return;
    }

    // Get the template to inherit from
    $inherited_template = $template_settings->get_template($inherit_from);

    if (!$inherited_template) {
        wp_send_json_error(array(
            'message' => 'Error retrieving template to copy from.'
        ));
        return;
    }

    // Copy settings from inherited template
    $inherited_config = $inherited_template['config'];
    $plugin_settings = isset($inherited_template['plugin_settings']) ?
                          $inherited_template['plugin_settings'] :
                          array('enable_consent_logging' => true);

    // Create new template
    $new_template = array(
        'name' => $template_name,
        'config' => $inherited_config,
        'plugin_settings' => $plugin_settings
    );

    // Save the new template
    $template_settings->set_template($template_key, $new_template);
    $template_settings->save();

    wp_send_json_success(array(
        'template_key' => $template_key,
        'message' => 'Template created successfully.'
    ));
}
add_action('wp_ajax_create_klaro_template', 'klaro_geo_create_template');

/**
 * AJAX handler for saving translations
 *
 * This function handles AJAX requests to save template translations without reloading the page.
 * It processes the JSON data, updates the template in the database, and returns a JSON response.
 */
function klaro_geo_save_translations_ajax() {
    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaro_geo_template_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
        return;
    }

    // Check if we have a template ID
    if (!isset($_POST['template_id']) || empty($_POST['template_id'])) {
        wp_send_json_error(array('message' => 'No template ID provided.'));
        return;
    }

    // Get the template ID
    $template_id = sanitize_text_field($_POST['template_id']);

    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // Check if the template exists
    $template = $template_settings->get_template($template_id);
    if (!$template) {
        wp_send_json_error(array('message' => 'Template not found.'));
        return;
    }

    // Log the AJAX request
    klaro_geo_debug_log('AJAX save translations request for template: ' . $template_id);

    // Process the translations JSON
    if (isset($_POST['translations_json']) && !empty($_POST['translations_json'])) {
        $translations_json = stripslashes($_POST['translations_json']);
        klaro_geo_debug_log('Received translations JSON: ' . substr($translations_json, 0, 500) . (strlen($translations_json) > 500 ? '...' : ''));

        try {
            // Parse the JSON
            $translations = json_decode($translations_json, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($translations)) {
                // Successfully parsed JSON
                klaro_geo_debug_log('Translations JSON parsed successfully');

                // Get the template config
                $config = $template_settings->get_template_config($template_id);
                if (!$config) {
                    $config = array();
                }

                // Update the translations
                $config['translations'] = $translations;

                // Save the updated template config
                $template_settings->set_template_config($template_id, $config);
                $template_settings->save();

                // Return success response with the updated translations
                wp_send_json_success(array(
                    'message' => 'Translations saved successfully.',
                    'translations_json' => json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ));
            } else {
                // JSON parsing error
                $error = json_last_error_msg();
                klaro_geo_debug_log('Error parsing translations JSON: ' . $error);
                wp_send_json_error(array('message' => 'Error parsing translations JSON: ' . $error));
            }
        } catch (Exception $e) {
            klaro_geo_debug_log('Exception parsing translations JSON: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Exception parsing translations JSON: ' . $e->getMessage()));
        }
    } else {
        wp_send_json_error(array('message' => 'No translations JSON provided.'));
    }
}
add_action('wp_ajax_klaro_geo_save_translations', 'klaro_geo_save_translations_ajax');

/**
 * AJAX handler for deleting a template
 */
function klaro_geo_delete_template_ajax() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaro_geo_template_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }

    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to delete templates.'));
        return;
    }

    // Get the template ID
    if (!isset($_POST['template_id']) || empty($_POST['template_id'])) {
        wp_send_json_error(array('message' => 'No template ID provided.'));
        return;
    }

    $template_id = sanitize_text_field($_POST['template_id']);

    // Don't allow deleting the default template
    if ($template_id === 'default') {
        wp_send_json_error(array('message' => 'The default template cannot be deleted.'));
        return;
    }

    // Initialize the template settings class
    $template_settings = new Klaro_Geo_Template_Settings();

    // Check if the template exists
    $template = $template_settings->get_template($template_id);
    if (!$template) {
        wp_send_json_error(array('message' => 'Template not found.'));
        return;
    }

    // Check if the template is in use by any countries
    $country_settings = get_option('klaro_geo_country_settings', array());
    $template_in_use = false;
    $countries_using_template = array();

    if (is_array($country_settings)) {
        foreach ($country_settings as $country_code => $country_data) {
            if (isset($country_data['template']) && $country_data['template'] === $template_id) {
                $template_in_use = true;
                $countries_using_template[] = $country_code;
            }
        }
    }

    // If the template is in use, return an error
    if ($template_in_use) {
        $country_list = implode(', ', $countries_using_template);
        $error_message = 'This template cannot be deleted because it is currently in use by the following countries: ' . $country_list . '. ';
        $error_message .= 'Please assign a different template to these countries in Country Settings before deleting this template.';

        wp_send_json_error(array('message' => $error_message));
        return;
    }

    // Log the deletion
    klaro_geo_debug_log('Deleting template: ' . $template_id);

    // Remove the template
    $template_settings->remove_template($template_id);

    // Save the changes
    $result = $template_settings->save();

    if ($result) {
        wp_send_json_success(array('message' => 'Template deleted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete template.'));
    }
}
add_action('wp_ajax_klaro_geo_delete_template', 'klaro_geo_delete_template_ajax');
