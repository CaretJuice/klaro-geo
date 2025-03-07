/**
 * Klaro Menu Button JavaScript
 * Handles adding the Klaro consent button to WordPress menus
 */
jQuery(document).ready(function($) {
    // Ensure klaroGeoAdmin is available or create a default object
    if (typeof klaroGeoAdmin === 'undefined') {
        console.log('klaroGeoAdmin not defined, using defaults');
        window.klaroGeoAdmin = {
            ajaxurl: (typeof ajaxurl !== 'undefined') ? ajaxurl : '',
            nonce: '',
            buttonText: 'Manage Consent Settings'
        };
    }
    
    // Handle the "Add Consent Button" click
    $('.klaro-consent-button-wrap').on('click', '.button-secondary', function(e) {
        e.preventDefault();
        
        // Show spinner
        $(this).siblings('.spinner').addClass('is-active');
        
        // Get the button text from the data attribute, klaroGeoAdmin, or use default
        var buttonText = $(this).data('button-text') || klaroGeoAdmin.buttonText || 'Manage Consent Settings';
        
        // Try to add the menu item
        addMenuItemSafely(buttonText);
        
        // Hide spinner after a short delay
        setTimeout(function() {
            $('.klaro-consent-button-wrap .spinner').removeClass('is-active');
        }, 500);
    });
    
    /**
     * Safely add a menu item, with fallbacks
     */
    function addMenuItemSafely(buttonText) {
        // First try the WordPress API method
        if (typeof wpNavMenu !== 'undefined') {
            try {
                // Check if addLinkToMenu is a function
                if (typeof wpNavMenu.addLinkToMenu === 'function') {
                    wpNavMenu.addLinkToMenu(buttonText, '#klaro-consent', 'custom', $('#menu').val());
                    return;
                }
            } catch (e) {
                console.error('Error using wpNavMenu.addLinkToMenu:', e);
            }
        }
        
        // If we get here, try the manual method
        try {
            addMenuItemManually(buttonText);
        } catch (e) {
            console.error('Error using manual method:', e);
            alert('Could not add menu item. Please try again or add it manually.');
        }
    }
    
    /**
     * Manually add a menu item by creating and submitting a form
     */
    function addMenuItemManually(buttonText) {
        var menuId = $('#menu').val();
        
        // Create a form and submit it to add the menu item
        var form = $('<form>').attr({
            'method': 'post',
            'action': ''
        });
        
        // Add necessary fields
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu-item[-1][menu-item-type]',
            'value': 'custom'
        }));
        
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu-item[-1][menu-item-title]',
            'value': buttonText
        }));
        
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu-item[-1][menu-item-url]',
            'value': '#klaro-consent'
        }));
        
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu-item[-1][menu-item-status]',
            'value': 'publish'
        }));
        
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'action',
            'value': 'add-menu-item'
        }));
        
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu',
            'value': menuId
        }));
        
        // Add nonce field
        form.append($('<input>').attr({
            'type': 'hidden',
            'name': 'menu-settings-column-nonce',
            'value': $('#menu-settings-column-nonce').val()
        }));
        
        // Append form to body, submit it, and remove it
        $('body').append(form);
        form.submit();
    }
});