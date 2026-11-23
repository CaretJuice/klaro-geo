/**
 * Consolidated admin functionality for Klaro Geo
 */

/**
 * Helpers shared by the admin page scripts, which all load after this one.
 */
window.klaroGeoAdminUi = {
    /**
     * English name of a language code, 'Custom' when it is not a known one.
     */
    languageName: function(langCode) {
        const names = {
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
        return names[langCode] || 'Custom';
    },

    /**
     * Show a message in the top right corner, replacing any message showing.
     * 'success' and 'error' messages remove themselves; a 'saving' message
     * stays until the caller removes the returned element or shows another.
     *
     * @param {string} type 'saving' | 'success' | 'error'
     * @param {string} message Shown as text.
     * @returns {jQuery} The message element.
     */
    showIndicator: function(type, message) {
        const $ = jQuery;
        $('.klaro-autosave-indicator').remove();

        const indicator = $('<div>').addClass('klaro-autosave-indicator klaro-autosave-' + type).text(message);
        $('body').append(indicator);

        if (type !== 'saving') {
            setTimeout(function() {
                indicator.fadeOut(300, function() {
                    $(this).remove();
                });
            }, type === 'error' ? 3000 : 2000);
        }

        return indicator;
    }
};

jQuery(document).ready(function($) {
    // Ensure klaroGeoAdmin is available
    if (typeof klaroGeoAdmin === 'undefined') {
        klaroGeoError('klaroGeoAdmin not defined');
        return;
    }

    // Debug log
    klaroGeoLog('klaroGeoAdmin loaded:', klaroGeoAdmin);

    // ===== COUNTRY MANAGEMENT =====

    // Handle country management modal
    $('#manage-countries').click(function() {
        $('#country-modal').show();
    });

    // Handle closing the country modal
    $('.close-country-modal').click(function() {
        $('#country-modal').hide();
    });

    // Handle hiding countries
    $('.hide-country').click(function() {
        let countryCode = $(this).data('country');
        let checkbox = $('input[name="klaro_geo_visible_countries[]"][value="' + countryCode + '"]');
        checkbox.prop('checked', false);
        $(this).closest('tr').hide();
    });

    // Handle saving country visibility
    $('.save-countries').click(function() {
        let selectedCountries = $('input[name="klaro_geo_visible_countries[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        klaroGeoLog('Saving visible countries:', selectedCountries);

        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'klaro_geo_save_visible_countries',
                countries: selectedCountries,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    klaroGeoLog('Successfully saved visible countries');
                    location.reload();
                } else {
                    klaroGeoError('Failed to save country visibility:', response);
                    alert('Failed to save country visibility. Please check the browser console for details.');
                }
            },
            error: function(xhr, status, error) {
                klaroGeoError('AJAX error:', status, error);
                klaroGeoError('Response text:', xhr.responseText);
                alert('Error saving country visibility: ' + status + ' - ' + error);
            }
        });
    });

    // ===== REGION MANAGEMENT =====

    // Function to load regions
    function loadRegions(countryCode, language) {
        let modal = $('#region-modal-' + countryCode);

        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'klaro_geo_get_regions',
                country: countryCode,
                language: language,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                klaroGeoLog('AJAX response:', response);
                if (response.success) {
                    var regionList = modal.find('.region-list');
                    // Set the country code as a data attribute
                    regionList.attr('data-country', countryCode);

                    // Add or update language selector if multiple languages are available
                    let languages = response.data.languages || [];
                    klaroGeoLog('Languages:', languages);

                    if (languages.length > 1) {
                        let existingSelector = modal.find('.language-selector-container');
                        if (existingSelector.length === 0) {
                            var langSelect = $('<select class="language-selector"></select>');
                            languages.forEach(function(lang) {
                                langSelect.append($('<option></option>')
                                    .attr('value', lang)
                                    .text(lang.toUpperCase())
                                    .prop('selected', lang === response.data.current_language)
                                );
                            });
                            regionList.before('<div class="language-selector-container"><label>Language: </label></div>');
                            regionList.prev().append(langSelect);
                        } else {
                            var langSelect = existingSelector.find('select');
                            langSelect.val(response.data.current_language);
                        }
                    }

                    let regions = response.data.regions || {};
                    klaroGeoLog('Regions:', regions);

                    // Escape every value interpolated into the markup below
                    const esc = function(value) {
                        return String(value).replace(/[&<>"']/g, function(c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                        });
                    };

                    let html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Region Code</th><th>Name</th><th>Template</th></tr></thead><tbody>';

                    if (Object.keys(regions).length === 0) {
                        html += '<tr><td colspan="3">No regions found for this country.</td></tr>';
                    } else {
                        // Iterate through the object using Object.entries
                        Object.entries(regions).forEach(function([code, name]) {
                            let fullCode = countryCode + '-' + code;
                            let templateValue = response.data.settings && response.data.settings[code] ? response.data.settings[code] : 'inherit';

                            html += '<tr>';
                            html += '<td class="region-code">' + esc(fullCode) + '</td>';
                            html += '<td>' + esc(name) + '</td>';
                            html += '<td><select name="klaro_geo_region_settings[' + esc(countryCode) + '][' + esc(code) + '][template]" data-country="' + esc(countryCode) + '" data-region="' + esc(code) + '">';
                            html += '<option value="inherit">Use country template</option>';
                            let templates = window.klaroTemplates;
                            klaroGeoLog('Available templates:', templates);

                            Object.keys(templates).forEach(function(key) {
                                let template = templates[key];
                                let templateName = template.name || key;
                                let selected = templateValue === key ? ' selected' : '';
                                html += '<option value="' + esc(key) + '"' + selected + '>' + esc(templateName) + '</option>';
                            });
                            html += '</select></td>';
                            html += '</tr>';
                        });
                    }

                    html += '</tbody></table>';
                    regionList.html(html);
                } else {
                    klaroGeoError('Failed to load regions:', response);

                    // Show error message in the region list
                    regionList.empty().append($('<div class="error"></div>').text('Failed to load regions: ' + (response.data ? response.data.message : 'Unknown error')));
                }
            },
            error: function(xhr, status, error) {
                klaroGeoError('AJAX error:', status, error);
                klaroGeoError('Response text:', xhr.responseText);

                // Show error message in the region list
                let regionList = modal.find('.region-list');
                regionList.empty().append($('<div class="error"></div>').text('Error loading regions: ' + status + ' - ' + error + '. Please check the browser console for more details.'));
            }
        });
    }

    // Function to save region settings
    function saveRegions(countryCode) {
        let modal = $('#region-modal-' + countryCode);
        let regionSettings = {};

        // Initialize the regionSettings object with the country
        regionSettings[countryCode] = {};

        // Get the form element
        var form = modal.find('.region-form');
        klaroGeoLog('Found form:', form.length ? 'yes' : 'no');

        // Collect all region settings
        let selectElements = form.find('.region-template-select');
        klaroGeoLog('Found ' + selectElements.length + ' select elements in form');

        if (selectElements.length === 0) {
            // Try a different selector if no elements found
            selectElements = form.find('select').not('.language-selector');
            klaroGeoLog('Alternative selector found ' + selectElements.length + ' select elements in form');

            // If still no elements found, try the modal
            if (selectElements.length === 0) {
                selectElements = modal.find('select').not('.language-selector');
                klaroGeoLog('Fallback to modal found ' + selectElements.length + ' select elements');
            }
        }

        selectElements.each(function() {
            let $select = $(this);
            let country = $select.data('country');
            let region = $select.data('region');
            let value = $select.val();

            klaroGeoLog('Processing select - country:', country, 'region:', region, 'value:', value);

            if (country && region) {
                // Initialize regions object if it doesn't exist
                if (!regionSettings[country].regions) {
                    regionSettings[country].regions = {};
                }

                // If using the country template (inherit), we'll still send it
                // The server will handle removing it from the database
                regionSettings[country].regions[region] = value;
            }
        });

        // Log the collected settings
        klaroGeoLog('Collected region settings:', regionSettings);

        // Check if we have any regions
        if (Object.keys(regionSettings[countryCode]).length === 0 ||
            !regionSettings[countryCode].regions ||
            Object.keys(regionSettings[countryCode].regions || {}).length === 0) {
            klaroGeoWarn('No regions found for country:', countryCode);
            // Still proceed with the save to clear any existing settings
        }

        // Show saving message
        var form = modal.find('.region-form');
        form.find('.region-list').append('<p class="saving">Saving region settings...</p>');

        // Debug log to see what's being sent
        klaroGeoLog('Region settings to save:', regionSettings);

        // Make AJAX request to save region settings
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'klaro_geo_save_region_settings',
                settings: JSON.stringify(regionSettings),
                nonce: klaroGeoAdmin.nonce,
                timestamp: new Date().getTime() // Add timestamp to prevent caching
            },
            success: function(response) {
                if (response.success) {
                    // Log the response for debugging
                    klaroGeoLog('Save regions response:', response);

                    // Show success message
                    var form = modal.find('.region-form');
                    form.find('.saving').removeClass('saving').addClass('success').text('Region settings saved successfully!');

                    // Hide success message after 2 seconds and reload the page
                    setTimeout(function() {
                        // Reload the page to show updated settings
                        window.location.reload();
                    }, 2000);
                } else {
                    // Log the error for debugging
                    klaroGeoError('Error saving regions:', response);

                    // Show error message
                    var form = modal.find('.region-form');
                    form.find('.saving').removeClass('saving').addClass('error').text('Error saving region settings: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                // Show detailed error message
                klaroGeoError('AJAX error:', status, error);
                klaroGeoError('Response:', xhr.responseText);
                let form = modal.find('.region-form');
                form.find('.saving').removeClass('saving').addClass('error')
                    .text('Error saving region settings: ' + status + ' - ' + error + '. Please check the browser console for more details.');
            }
        });
    }

    // Handle language selection change
    $(document).on('change', '.language-selector', function() {
        let countryCode = $(this).closest('.klaro-modal').find('.region-list').data('country');
        let selectedLanguage = $(this).val();
        loadRegions(countryCode, selectedLanguage);
    });

    // Handle region management
    $('.manage-regions').click(function() {
        let countryCode = $(this).data('country');
        let modal = $('#region-modal-' + countryCode);

        // Save the current form state before opening the modal
        let formData = $('#klaro-country-settings-form').serialize();

        // Save country settings via AJAX
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'klaro_geo_save_country_settings',
                settings: formData,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    klaroGeoLog('Country settings saved before opening region modal');

                    // Now show the modal and load regions
                    modal.show();
                    loadRegions(countryCode);
                } else {
                    klaroGeoError('Failed to save country settings:', response);
                    alert('Failed to save country settings before opening region modal. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                klaroGeoError('AJAX error:', status, error);
                klaroGeoError('Response text:', xhr.responseText);
                alert('Error saving country settings: ' + status + ' - ' + error);

                // Still show the modal and load regions even if saving failed
                modal.show();
                loadRegions(countryCode);
            }
        });
    });

    // Close region modal
    $(document).on('click', '.close-region-modal', function(e) {
        e.preventDefault(); // Prevent any default action
        e.stopPropagation(); // Stop event propagation
        klaroGeoLog('Close region modal button clicked');

        // Just hide the modal without submitting anything
        $(this).closest('.klaro-modal').hide();

        // Return false to prevent any other handlers from running
        return false;
    });

    // Handle saving regions
    $(document).on('click', '.save-regions', function(e) {
        e.preventDefault(); // Prevent any default action
        let countryCode = $(this).data('country');
        klaroGeoLog('Save regions button clicked for country:', countryCode);
        saveRegions(countryCode);
    });

    // ===== AUTOSAVE FUNCTIONALITY =====

    // Autosave timer reference
    let autosaveTimer = null;
    let autosaveDelay = 1000; // 1 second debounce

    const showAutosaveIndicator = klaroGeoAdminUi.showIndicator;

    // Function to autosave country settings
    function autosaveCountrySettings(changedElement) {
        // Show saving indicator
        let savingIndicator = showAutosaveIndicator('saving', 'Saving...');

        // Add saving class to the changed element
        if (changedElement) {
            $(changedElement).addClass('klaro-field-saving');
        }

        // Serialize the form data
        let formData = $('#klaro-country-settings-form').serialize();

        // Make AJAX request
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'klaro_geo_save_country_settings',
                settings: formData,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                savingIndicator.remove();

                if (response.success) {
                    showAutosaveIndicator('success', 'Saved');

                    // Add saved animation to the changed element
                    if (changedElement) {
                        $(changedElement).removeClass('klaro-field-saving').addClass('klaro-field-saved');
                        setTimeout(function() {
                            $(changedElement).removeClass('klaro-field-saved');
                        }, 1000);
                    }

                    klaroGeoLog('Country settings autosaved successfully');
                } else {
                    showAutosaveIndicator('error', 'Save failed: ' + (response.data || 'Unknown error'));
                    if (changedElement) {
                        $(changedElement).removeClass('klaro-field-saving');
                    }
                    klaroGeoError('Autosave failed:', response);
                }
            },
            error: function(xhr, status, error) {
                savingIndicator.remove();
                showAutosaveIndicator('error', 'Save error: ' + error);
                if (changedElement) {
                    $(changedElement).removeClass('klaro-field-saving');
                }
                klaroGeoError('Autosave AJAX error:', status, error);
            }
        });
    }

    // Autosave on country/fallback template dropdown change
    $(document).on('change', '#klaro-country-settings-form select', function() {
        let changedElement = this;

        // Clear any pending autosave
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }

        // Debounce the autosave
        autosaveTimer = setTimeout(function() {
            autosaveCountrySettings(changedElement);
        }, autosaveDelay);
    });

    // Also handle the form submit to prevent double-saving
    $('#klaro-country-settings-form').on('submit', function() {
        // Clear any pending autosave since we're doing a full form submit
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
    });
});
