jQuery(document).ready(function($) {
    // Handle clicks on country/region links
    $('#wp-admin-bar-klaro-geo-debug-countries a, #wp-admin-bar-klaro-geo-debug-regions a').on('click', function(e) {
        e.preventDefault();
        const selectedLocation = $(this).text();
        if (selectedLocation && selectedLocation !== 'Countries' && selectedLocation !== 'Regions') {
            // Remove any existing klaro_geo_debug_geo parameter and add the new one
            let url = new URL(window.location.href);
            url.searchParams.delete('klaro_geo_debug_geo');
            url.searchParams.append('klaro_geo_debug_geo', selectedLocation);
            window.location.href = url.toString();
        }
    });

    // Prevent default action on main menu and submenu headers
    $('#wp-admin-bar-klaro-geo-debug > a, #wp-admin-bar-klaro-geo-debug-countries > a, #wp-admin-bar-klaro-geo-debug-regions > a').on('click', function(e) {
        e.preventDefault();
    });
});