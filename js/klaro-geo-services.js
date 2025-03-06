/**
 * Functions for validating Klaro Geo Services
 */

/**
 * Tests if the service_cookies property of each service is of the correct type
 * @param {Array|null|undefined} services - Array of service objects to test
 * @returns {boolean} - True if all service_cookies are valid, false otherwise
 */
function testServiceCookiesType(services) {
    // Handle null/undefined/empty array cases
    if (!services) return false;
    if (!Array.isArray(services)) return false;
    if (services.length === 0) return true; // Empty array is valid

    // Check each service
    return services.every(service => {
        // If service_cookies is missing, that's invalid
        if (!service || !('service_cookies' in service)) return false;
        
        const cookies = service.service_cookies;
        
        // service_cookies must be an array
        if (!Array.isArray(cookies)) return false;
        
        // Empty array is valid
        if (cookies.length === 0) return true;
        
        // Each item in the array must be a string or RegExp
        return cookies.every(cookie => 
            typeof cookie === 'string' || cookie instanceof RegExp
        );
    });
}

/**
 * Tests if the service_purposes property of each service is of the correct type
 * @param {Array|null|undefined} services - Array of service objects to test
 * @returns {boolean} - True if all service_purposes are valid, false otherwise
 */
function testServicePurposesType(services) {
    // Handle null/undefined/empty array cases
    if (!services) return false;
    if (!Array.isArray(services)) return false;
    if (services.length === 0) return true; // Empty array is valid

    // Check each service
    return services.every(service => {
        // If service_purposes is missing, that's valid (unlike cookies)
        if (!service) return false;
        if (!('service_purposes' in service)) return true;
        
        const purposes = service.service_purposes;
        
        // service_purposes must be an array
        if (!Array.isArray(purposes)) return false;
        
        // Empty array is valid
        if (purposes.length === 0) return true;
        
        // Each item in the array must be a string
        return purposes.every(purpose => typeof purpose === 'string');
    });
}

// Export the functions
module.exports = {
    testServiceCookiesType,
    testServicePurposesType
};