jQuery(document).ready(function($) {
    // Ensure klaroGeoAdmin is available
    if (typeof klaroGeoAdmin === 'undefined') {
        console.error('klaroGeoAdmin not defined');
        return;
    }

    // Debug log
    console.log('klaroGeoAdmin loaded:', klaroGeoAdmin);


    // Handle country management modal
    $('#manage-countries').click(function() {
        $('#country-modal').show();
    });

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

        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_klaro_visible_countries',
                countries: selectedCountries,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    console.error('Failed to save country visibility:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
            }
        });
    });

    // Function to load regions
    function loadRegions(countryCode, language) {
        var modal = $('#region-modal-' + countryCode);

        $.ajax({
            url: klaroGeoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_country_regions',
                country: countryCode,
                language: language,
                nonce: klaroGeoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var regionList = modal.find('.region-list');
                    // Set the country code as a data attribute
                    regionList.attr('data-country', countryCode);

                    // Add or update language selector if multiple languages are available
                    var languages = response.data.languages;
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

                    var regions = response.data.regions;
                    var html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr><th>Region Code</th><th>Name</th><th>Template</th></tr></thead><tbody>';

                    regions.forEach(function(region) {
                        html += '<tr>';
                        html += '<td class="region-code">' + region.code + '</td>';
                        html += '<td>' + region.name + '</td>';
                        html += '<td><select name="klaro_geo_region_settings[' + region.code + '][template]">';
                        html += '<option value="inherit">Use Country Default</option>';
                        if (window.klaroTemplates) {
                            Object.keys(window.klaroTemplates).forEach(function(key) {
                                var template = window.klaroTemplates[key];
                                var selected = region.template === key ? ' selected' : '';
                                html += '<option value="' + key + '"' + selected + '>' + template.name + '</option>';
                            });
                        }
                        html += '</select></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    regionList.html(html);
                } else {
                    console.error('Failed to load regions:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
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
        modal.show();

        // Load regions for this country
        loadRegions(countryCode);
    });

    // Close region modal
    $('.close-region-modal').click(function() {
        var modal = $(this).closest('.klaro-modal');
        var countryCode = modal.find('.region-list').data('country');
        var regionList = modal.find('.region-list');

        // Check if we have a valid country code before proceeding with saving
        if (countryCode) {
            var formData = {
                countries: {}
            };

            // Collect all region settings
            formData.countries[countryCode] = {
                regions: {}
            };

            regionList.find('tr').each(function() {
                var regionCode = $(this).find('.region-code').text();
                var template = $(this).find('select[name^="klaro_geo_region_settings"]').val();
                var consentMode = $(this).find('.region-consent-mode').val();

                if (regionCode) {
                    formData.countries[countryCode].regions[regionCode] = {
                        template: template,
                        consent_mode: consentMode
                    };
                }
            });

            // Save region settings before closing
            $.ajax({
                url: klaroGeoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_klaro_settings',
                    settings: JSON.stringify(formData),
                    nonce: klaroGeoAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        modal.hide();
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>Region settings saved successfully.</p></div>')
                            .insertAfter('.wp-header-end').delay(3000).fadeOut();
                    } else {
                        console.error('Failed to save region settings:', response);
                        // Still hide the modal even if save fails
                        modal.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    // Still hide the modal even if AJAX fails
                    modal.hide();
                },
                // Add a timeout to prevent the modal from staying open indefinitely
                timeout: 5000
            });
        } else {
            // If we don't have a country code, just close the modal
            console.warn('No country code found for region modal, closing without saving');
            modal.hide();
        }
    });

    // EU country codes
    const euCountries = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    ];

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