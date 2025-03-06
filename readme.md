# Klaro Geo

This plugin integrates the [Klaro](https://github.com/KIProtect/klaro) consent management tool with the [GeoIP2 PHP API](https://dev.maxmind.com/geoip/geoip2/geolite2/) to allow you to target different consent banners using Klaro.

It is designed to work with Google Tag Manager with Klaro controlling the loading of Google Tag Manager. 

It allows you to create and manage multiple consent banner templates, customize them based on user location, and track consent decisions for compliance purposes.

This plugin gives you a lot of control over how consent is managed and tracked across your website. Using this plugin does not guarantee that you will be compliant with applicable laws and regulations. 

**You are responsible for ensuring compliance with all applicable laws and regulations. Always consult legal experts before implementing any consent management solution.**

## Features

- **Geolocation-Based Consent Management**: Display different consent banners based on user location (country and region)
- **Template System**: Create and manage multiple consent banner templates
- **Google Tag Manager Integration**: Built-in support for Google Tag Manager with customizable scripts
- **Consent Receipts**: Track and store user consent choices for compliance purposes
- **Consent Button Options**: Add a floating button or integrate with WordPress menus to allow users to easily access consent settings
- **Admin Debug Tools**: Test different geolocation scenarios directly from the admin bar
- **Data Layer Integration**: Push consent events to the data layer for advanced tracking

## Requirements

- Tested on Wordpress 6.3 and 6.4 (earlier versions may work but are untested)
- [GeoIP Detection](https://wordpress.org/plugins/geoip-detect/) plugin (for geolocation features to work)

## Installation

1. Upload the `klaro-geo` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure the GeoIP Detection plugin is installed and properly configured
4. Configure Klaro Geo settings under the 'Klaro Geo' menu in the WordPress admin

## Configuration

### General Settings

Navigate to **Klaro Geo > Settings** to configure the general plugin settings:

- **Consent Button Options**:
  - Enable/disable the floating consent management button
  - Customize the button text
  - Choose from light, dark, or success (green) button themes
  - Add a consent button to any WordPress menu
- **Klaro Script Options**:
  - Set the Klaro script version (e.g., "0.7")
  - Choose between standard Klaro script or the no-CSS variant
- **Enable Consent Receipts**: Toggle the storage of user consent choices
- **Debug Countries**: Configure which countries/regions appear in the debug menu

### Templates

Navigate to **Klaro Geo > Templates** to manage consent banner templates:

1. **Default Template**: The fallback template used when no location-specific template is found
2. **Create New Templates**: Create custom templates for specific regions or use cases
3. **Template Inheritance**: Templates can inherit settings from other templates to reduce duplication

Each template includes settings for:
- Banner appearance (position, theme, width)
- Text content and translations
- Consent options (Accept All, Decline All, etc.)
- Cookie settings (expiration, domain)

#### Translation Settings

Each template includes translation settings that can be configured in two ways:

1. **Basic Text Settings**: Simple form fields for common text elements like modal title, description, and button labels.
2. **Advanced Translations (JSON)**: A JSON editor for configuring complex translations with multiple languages and custom text elements.

### Country/Region Settings

Navigate to **Klaro Geo > Countries** to assign templates to specific locations:

1. Select a country from the dropdown
2. Optionally select a region within that country
3. Choose which template to display for users from that location
4. Save your settings

### Services Configuration

Navigate to **Klaro Geo > Services** to configure which services require consent:

1. Add services that require user consent (e.g., Google Analytics, Facebook Pixel)
2. Configure settings for each service (required, default state, purposes)
3. Add custom JavaScript for initialization, acceptance, and decline events

Klaro lets you group consent by purpose and Klaro-Geo supports this configuration in the plugin template settings.

If you wanted to only allow consent by purpose without individual service options, you could configure your purposes as services, so you might create and `analytics` service, an `advertising` service, and a `functional` service, and then trigger tags in Google Tag Manager off of those purposes. However, grouping by purpose is the only way that you can support consent by purpose while still using templates to allow other consent settings in different regions.

## WordPress Menu Integration

You can add a consent management button to any WordPress menu:

1. Go to **Appearance > Menus** in your WordPress admin
2. Create a new menu or edit an existing one
3. Look for the "Klaro Consent Button" box in the left sidebar (you may need to click "Screen Options" at the top and enable it)
4. Click "Add Consent Button" to add it to your menu
5. Save the menu

The button will use the text configured in the Klaro Geo settings.

## Customizing Klaro Appearance

Klaro Geo allows you to select either the standard Klaro script (`klaro.js`) or a version that does not load its own CSS (`klaro-no-css.js`). Both scripts provide similar functionality, but they differ in their approach to styling.

The translation settings for in template configuration.

### Klaro Script Variants 

The plugin supports different Klaro script variants:

1. **Standard (klaro.js)**: The default Klaro script with built-in styles.
2. **No CSS (klaro-no-css.js)**: A version of Klaro without styles, which is useful if you want to use your own CSS or customize the default styles.

When using the "No CSS" variant, the plugin will automatically load the Klaro CSS file separately, which you can then override with your own styles.

## Google Tag Manager

The plugin is designed to manage the loading of Google Tag Manager via Klaro.

To this end, the plugin defaults with some custom `onInit` and `onAccept` scripts that manage the loading of Google Tag Manager. You can override these by adding your own `onInit` and/or `onAccept` functions in the Google Tag Manager service settings.

The default scripts will likely need to be customized to suit your needs.

### Default onAccept script

This enabled Google Tag Manager to load only if either analytics or advertising consents are accepted. It then pushes consent events to the dataLayer for further processing.

```
if (opts.consents.analytics || opts.consents.advertising) { 
    for(let k of Object.keys(opts.consents)){
        if (opts.consents[k]){
            let eventName = 'klaro-'+k+'-accepted'
            dataLayer.push({'event': eventName})
        }
    }
}
```

This particular setup prevents anonymous pings from being sent by gtag.js until either analytics or advertising consent is given and GTM is loaded.

If you wish to preserve anonymous pings, you might choose to make Google Tag Manager a functional service and use the following onAccept script:

```
for(let k of Object.keys(opts.consents)){
    if (opts.consents[k]){
        let eventName = 'klaro-'+k+'-accepted'
        dataLayer.push({'event': eventName})
    }
}
```


### Default onInit script

This initializes Google Tag Manager advanced consent mode and sets initial consent states.
```
window.dataLayer = window.dataLayer || []; window.gtag = function(){dataLayer.push(arguments)} gtag('consent', 'default', {'ad_storage': 'denied', 'analytics_storage': 'denied', 'ad_user_data': 'denied', 'ad_personalization': 'denied'}) gtag('set', 'ads_data_redaction', true)
```

## Geo Detection

Klaro Geo uses the GeoIP Detection plugin to determine a user's location. This information is used to decide which consent banner template to show.

The GeoIP Detection plugin lets you select different IP databases. In testing, the free HostIP.info Web-API database frequently failed to return results. This would result in the fallback consent template being shown. 

For this reason, we recommend using strict fallback templates. In addition, it is recommended to use the MaxMind GeoLite2 City database, which provides more accurate results than the free HostIP.info Web-API database.

Geo detection is optional. If disabled, the fallback template will always be shown regardless of user location.

### Geo Regions

Klaro Geo supports assigning templates to specific regions within countries.

Geo detection works off of [ISO 3166-2](https://en.wikipedia.org/wiki/ISO_3166-2) codes. For example, the code "US-NY" represents the state of New York in the United States. The subdivisions.csv file included with the plugin contains a mapping of ISO 3166-2 codes to human-readable names. The plugin uses this file to generate region options for the plugin.

This plugin includes Ipregistry ISO 3166 data available from https://ipregistry.co.

To assign a template to a region, follow these steps:

1. You will first need to create a template for that region. 
2. Then go to **Klaro Geo > Country Settings**, and select **Manage Regions**. A modal will open with a list of regions.
3. Assign the desired template to the desired region or regions.

Please note that that browser privacy safeguards, like Apple's Privacy Relay, make regional detection unreliable. Beyond just the issues with IP database accuracy, these safeguards deliberately change the location of visitors. Where country regulations are relaxed but regional regulations are stricter, you should consider using the stricter templates for the entire country in order to avoid inadvertently violating regional regulations.

## Consent Receipts

Klaro Geo can store detailed records of user consent choices, which is useful for compliance with privacy regulations like GDPR and CCPA.

### How Consent Receipts Work

1. When a user makes consent choices, a receipt is generated containing:
   - Unique receipt ID
   - Timestamp
   - User's consent choices for each service
   - Template used
   - User's detected location
   - Other relevant metadata

2. Receipts are stored in two locations:
   - **Client-side**: In the user's browser localStorage (limited to last 10 receipts)
   - **Server-side**: In the WordPress database (if server-side logging is enabled)

3. Receipts are also pushed to the dataLayer for integration with analytics tools

### Enabling/Disabling Consent Receipts

- **Global Setting**: Enable/disable consent receipts globally in Klaro Geo Settings
- **Per-Template Setting**: Enable/disable server-side logging for specific templates

### Viewing Consent Receipts

Administrators can view stored consent receipts under **Klaro Geo > Consent Receipts** in the WordPress admin.

## Developer Features

### Debug Mode

When logged in as an administrator, you can test different geolocation scenarios:

1. A dropdown menu appears in the admin bar
2. Select any country or region to simulate a user from that location
3. The consent banner will display according to the template assigned to that location

### JavaScript Events

Klaro Geo triggers several events that developers can listen for:

```javascript
// Listen for consent changes
document.addEventListener('klaro:consent-change', function(e) {
    console.log('Consent changed:', e.detail.manager.consents);
});

// Listen for Klaro initialization
document.addEventListener('klaro:ready', function(e) {
    console.log('Klaro initialized');
});
```

### DataLayer Integration

Consent events are automatically pushed to the dataLayer:

```javascript
dataLayer.push({
    'event': 'klaro_geo_consent_receipt',
    'klaro_geo_consent_receipt': {
        // Receipt data
    }
});
```

## Troubleshooting

### Common Issues

1. **Banner not displaying**: Ensure the plugin is properly activated and that there are no JavaScript errors in the console.

2. **Geolocation not working**: Verify that the GeoIP Detection plugin is properly configured and that the GeoIP database is up to date.

3. **Template not applying**: Check that you have assigned the correct template to the user's country/region in the Countries settings.

4. **Consent receipts not storing**: Ensure that consent receipts are enabled in the settings and that the database table was created successfully.

### Debug Logging

For advanced troubleshooting, enable WordPress debug logging:

1. Add the following to your wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

2. Check the debug.log file for any errors related to Klaro Geo (entries will be prefixed with [Klaro Geo]).

## Support and Contributions

For support requests, feature suggestions, or to report bugs, please use the GitHub repository's issue tracker.