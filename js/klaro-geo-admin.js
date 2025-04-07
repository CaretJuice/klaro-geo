/**
 * Consolidated admin functionality for Klaro Geo
 */
jQuery(document).ready(function($) {
    // Initialize tabs if they exist
    if ($.fn.tabs && $('.tabs-container').length) {
        $('.tabs-container').tabs();
    }
    
    // Common form validation
    $('.klaro-geo-form').on('submit', function(e) {
        var requiredFields = $(this).find('[required]');
        var valid = true;
        
        requiredFields.each(function() {
            if (!$(this).val()) {
                valid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
    
    // Toggle advanced sections
    $('.toggle-advanced').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#' + target).slideToggle();
        $(this).toggleClass('open');
        
        if ($(this).hasClass('open')) {
            $(this).text($(this).data('hide-text') || 'Hide Advanced Options');
        } else {
            $(this).text($(this).data('show-text') || 'Show Advanced Options');
        }
    });

    // Ensure klaroGeoAdmin is available
    if (typeof klaroGeoAdmin === 'undefined') {
        console.error('klaroGeoAdmin not defined');
        return;
    }

    // Debug log
    console.log('klaroGeoAdmin loaded:', klaroGeoAdmin);

    // EU country codes
    const euCountries = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    ];

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
        var countryCode = $(this).data('country');
        var checkbox = $('input[name="klaro_geo_visible_countries[]"][value="' + countryCode + '"]');
        checkbox.prop('checked', false);
        $(this).closest('tr').hide();
    });

    // Handle saving country visibility
    $('.save-countries').click(function() {
        var selectedCountries = $('input[name="klaro_geo_visible_countries[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        console.log('Saving visible countries:', selectedCountries);

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
                    console.log('Successfully saved visible countries');
                    location.reload();
                } else {
                    console.error('Failed to save country visibility:', response);
                    alert('Failed to save country visibility. Please check the browser console for details.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
                alert('Error saving country visibility: ' + status + ' - ' + error);
            }
        });
    });

    // ===== REGION MANAGEMENT =====

    // Function to load regions
    function loadRegions(countryCode, language) {
        var modal = $('#region-modal-' + countryCode);

        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_klaro_regions',
                country: countryCode,
                language: language,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    var regionList = modal.find('.region-list');
                    // Set the country code as a data attribute
                    regionList.attr('data-country', countryCode);

                    // Add or update language selector if multiple languages are available
                    var languages = response.data.languages || [];
                    console.log('Languages:', languages);

                    if (languages.length > 1) {
                        var existingSelector = modal.find('.language-selector-container');
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

                    var regions = response.data.regions || {};
                    console.log('Regions:', regions);

                    var html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Region Code</th><th>Name</th><th>Template</th></tr></thead><tbody>';

                    if (Object.keys(regions).length === 0) {
                        html += '<tr><td colspan="3">No regions found for this country.</td></tr>';
                    } else {
                        // Iterate through the object using Object.entries
                        Object.entries(regions).forEach(function([code, name]) {
                            var fullCode = countryCode + '-' + code;
                            var templateValue = response.data.settings && response.data.settings[code] ? response.data.settings[code] : 'inherit';

                            html += '<tr>';
                            html += '<td class="region-code">' + fullCode + '</td>';
                            html += '<td>' + name + '</td>';
                            html += '<td><select name="klaro_geo_region_settings[' + countryCode + '][' + code + '][template]" data-country="' + countryCode + '" data-region="' + code + '">';
                            html += '<option value="inherit">Use country template</option>';
                            // Use klaroGeoAdmin.templates if available, otherwise try window.klaroTemplates
                            var templates = klaroGeoAdmin.templates || window.klaroTemplates || {};
                            console.log('Available templates:', templates);

                            Object.keys(templates).forEach(function(key) {
                                var template = templates[key];
                                var templateName = template.name || key;
                                var selected = templateValue === key ? ' selected' : '';
                                html += '<option value="' + key + '"' + selected + '>' + templateName + '</option>';
                            });
                            html += '</select></td>';
                            html += '</tr>';
                        });
                    }

                    html += '</tbody></table>';
                    regionList.html(html);
                } else {
                    console.error('Failed to load regions:', response);

                    // Show error message in the region list
                    regionList.html('<div class="error">Failed to load regions: ' + (response.data ? response.data.message : 'Unknown error') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);

                // Show error message in the region list
                var regionList = modal.find('.region-list');
                regionList.html('<div class="error">Error loading regions: ' + status + ' - ' + error + '<br>Please check the browser console for more details.</div>');
            }
        });
    }

    // Function to save region settings
    function saveRegions(countryCode) {
        var modal = $('#region-modal-' + countryCode);
        var regionSettings = {};

        // Initialize the regionSettings object with the country
        regionSettings[countryCode] = {};

        // Get the form element
        var form = modal.find('.region-form');
        console.log('Found form:', form.length ? 'yes' : 'no');

        // Collect all region settings
        var selectElements = form.find('.region-template-select');
        console.log('Found ' + selectElements.length + ' select elements in form');

        if (selectElements.length === 0) {
            // Try a different selector if no elements found
            selectElements = form.find('select').not('.language-selector');
            console.log('Alternative selector found ' + selectElements.length + ' select elements in form');

            // If still no elements found, try the modal
            if (selectElements.length === 0) {
                selectElements = modal.find('select').not('.language-selector');
                console.log('Fallback to modal found ' + selectElements.length + ' select elements');
            }
        }

        selectElements.each(function() {
            var $select = $(this);
            var country = $select.data('country');
            var region = $select.data('region');
            var value = $select.val();

            console.log('Processing select - country:', country, 'region:', region, 'value:', value);

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
        console.log('Collected region settings:', regionSettings);

        // Check if we have any regions
        if (Object.keys(regionSettings[countryCode]).length === 0 || 
            !regionSettings[countryCode].regions || 
            Object.keys(regionSettings[countryCode].regions || {}).length === 0) {
            console.warn('No regions found for country:', countryCode);
            // Still proceed with the save to clear any existing settings
        }

        // Show saving message
        var form = modal.find('.region-form');
        form.find('.region-list').append('<p class="saving">Saving region settings...</p>');

        // Debug log to see what's being sent
        console.log('Region settings to save:', regionSettings);

        // Make AJAX request to save region settings
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'save_klaro_region_settings',
                settings: JSON.stringify(regionSettings),
                nonce: klaroGeoAdmin.nonce,
                timestamp: new Date().getTime() // Add timestamp to prevent caching
            },
            success: function(response) {
                if (response.success) {
                    // Log the response for debugging
                    console.log('Save regions response:', response);

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
                    console.error('Error saving regions:', response);

                    // Show error message
                    var form = modal.find('.region-form');
                    form.find('.saving').removeClass('saving').addClass('error').text('Error saving region settings: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                // Show detailed error message
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                var form = modal.find('.region-form');
                form.find('.saving').removeClass('saving').addClass('error')
                    .html('Error saving region settings: ' + status + ' - ' + error + '<br>Please check the browser console for more details.');
            }
        });
    }

    // Handle language selection change
    $(document).on('change', '.language-selector', function() {
        var countryCode = $(this).closest('.klaro-modal').find('.region-list').data('country');
        var selectedLanguage = $(this).val();
        loadRegions(countryCode, selectedLanguage);
    });

    // Handle region management
    $('.manage-regions').click(function() {
        var countryCode = $(this).data('country');
        var modal = $('#region-modal-' + countryCode);

        // Save the current form state before opening the modal
        var formData = $('#klaro-country-settings-form').serialize();

        // Save country settings via AJAX
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_klaro_country_settings',
                settings: formData,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Country settings saved before opening region modal');

                    // Now show the modal and load regions
                    modal.show();
                    loadRegions(countryCode);
                } else {
                    console.error('Failed to save country settings:', response);
                    alert('Failed to save country settings before opening region modal. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
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
        console.log('Close region modal button clicked');

        // Just hide the modal without submitting anything
        $(this).closest('.klaro-modal').hide();

        // Return false to prevent any other handlers from running
        return false;
    });

    // Handle saving regions
    $(document).on('click', '.save-regions', function(e) {
        e.preventDefault(); // Prevent any default action
        var countryCode = $(this).data('country');
        console.log('Save regions button clicked for country:', countryCode);
        saveRegions(countryCode);
    });

    // ===== BULK EDIT FUNCTIONALITY =====

    // Bulk edit button
    $('#bulk_edit_templates').click(function() {
        $('#bulk_edit_modal').show();
    });
    
    // Close modal
    $('#close_bulk_modal').click(function() {
        $('#bulk_edit_modal').hide();
    });

    // Close modal when clicking outside
    $(window).click(function(event) {
        if ($(event.target).is('#bulk_edit_modal')) {
            $('#bulk_edit_modal').hide();
        }
    });
    
    // Select all countries
    $('#select_all_countries').change(function() {
        $('.country-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Select EU countries
    $('#select_eu_countries').change(function() {
        $('.country-checkbox').prop('checked', false);
        euCountries.forEach(code => $(`.country-checkbox[value="${code}"]`).prop('checked', true));
    });

    // Apply bulk template
    $('#apply_bulk_template').click(function() {
        var selectedTemplate = $('#bulk_template').val();
        var selectedCountries = $('.country-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedCountries.length === 0) {
            alert('Please select at least one country.');
            return;
        }
        
        var formData = {
            countries: {}
        };
        
        selectedCountries.forEach(function(code) {
            formData.countries[code] = {
                template: selectedTemplate
            };
        });
        
        // Save changes via AJAX
        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_klaro_settings',
                settings: JSON.stringify(formData),
                nonce: klaroGeoAdmin.nonce
            },
            beforeSend: function() {
                $('#apply_bulk_template').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    $('#bulk_edit_modal').hide();
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>')
                        .insertAfter('.wp-header-end').delay(3000).fadeOut();
                    
                    // Update the UI
                    selectedCountries.forEach(function(code) {
                        $('select[name="klaro_geo_settings[countries][' + code + '][template]"]').val(selectedTemplate);
                    });
                } else {
                    console.error('Save failed:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
            },
            complete: function() {
                $('#apply_bulk_template').prop('disabled', false).text('Apply to Selected');
            }
        });
    });
});