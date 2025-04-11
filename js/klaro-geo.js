(function() {
    console.log('DEBUG: klaro-geo.js loaded (early capture attempt)');
  
    function setupKlaroDataLayerWatcher() {
      // Try to access Klaro immediately
      if (typeof window.klaro !== 'undefined' && typeof window.klaro.getManager === 'function') {
        try {
          const manager = window.klaro.getManager();
          if (manager) {
            console.log('DEBUG: Klaro manager available early, setting up watcher');
            setupWatcher(manager);
            return; // Success, don't retry
          } else {
            console.warn('DEBUG: Klaro manager is undefined, even though klaro is defined.');
          }
        } catch (error) {
          console.error('DEBUG: Error getting manager early:', error);
        }
      }
  
      // Fallback: Retry with setTimeout
      console.log('DEBUG: Klaro not fully ready, retrying setupKlaroDataLayerWatcher');
      setTimeout(setupKlaroDataLayerWatcher, 50); // Very short delay
    }
  
    function setupWatcher(manager) {
        try {
          manager.watch({
            update: function(obj, name, data) {
              if (typeof window.dataLayer !== 'undefined') {
                const pushData = {
                  'event': 'Klaro Event',
                  'klaroEventName': name,
                  'klaroEventData': data,
                  'klaroEventContext': {
                    config: obj.config,
                    services: obj.services,
                    translations: obj.translations,
                    serviceNames: [] // Initialize the array
                  }
                };
      
                // Populate serviceNames array
                if (obj.services && Array.isArray(obj.services)) {
                  obj.services.forEach(service => {
                    if (service && service.name) {
                      pushData.klaroEventContext.serviceNames.push(service.name);
                    }
                  });
                }
      
                console.log('DEBUG: Pushing Klaro event to dataLayer:', pushData);
                window.dataLayer.push(pushData);
              } else {
                console.warn('DEBUG: dataLayer is not defined, cannot push Klaro event');
              }
            }
          });
          console.log('DEBUG: Generic Klaro dataLayer watcher setup complete');
        } catch (error) {
          console.error('DEBUG: Error setting up Klaro dataLayer watcher:', error);
        }
      }
  
    // Initialize the watcher
    setupKlaroDataLayerWatcher();
  })();