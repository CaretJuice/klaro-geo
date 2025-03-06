jQuery(document).ready(function($) {
    // Ensure klaroGeo is available
    if (typeof klaroGeo === 'undefined') {
        console.error('klaroGeo not defined');
        return;
    }

    // Debug log
    console.log('klaroGeo loaded:', klaroGeo);

    // Populate purposes checkboxes
    function populatePurposes() {
        var container = $('#service_purposes_container');
        container.empty();
        
        if (klaroGeo.purposes && klaroGeo.purposes.length > 0) {
            klaroGeo.purposes.forEach(function(purpose) {
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
        var service = klaroGeo.services[index];
        
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
        
        // Set callback scripts
        $('#service_oninit').val(service.onInit || '');
        $('#service_onaccept').val(service.onAccept || '');
        $('#service_ondecline').val(service.onDecline || '');
        
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
                _wpnonce: klaroGeo.nonce
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
        
        // Get callback scripts
        var onInit = $('#service_oninit').val();
        var onAccept = $('#service_onaccept').val();
        var onDecline = $('#service_ondecline').val();
        
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
        
        // Update or add service
        if (index !== '') {
            klaroGeo.services[index] = service;
        } else {
            klaroGeo.services.push(service);
        }
        
        // Save services
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_klaro_services',
                services: JSON.stringify(klaroGeo.services),
                _wpnonce: klaroGeo.nonce
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