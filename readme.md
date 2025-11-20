# Klaro Geo

This plugin integrates the [Klaro](https://github.com/KIProtect/klaro) consent management tool with the [GeoIP2 PHP API](https://dev.maxmind.com/geoip/geoip2/geolite2/) to allow you to target different consent banners using Klaro.

It is designed to work with Google Tag Manager with Klaro controlling the loading of Google Tag Manager. It is best to load Google Tag Manager via the plugin settings page rather than adding it manually to your site as integrating Google Tag Manager with Klaro requires customization of the Google Tag Manager Javascript snippet.

It allows you to create and manage multiple consent banner templates, assign them to users by location, and track consent decisions for compliance purposes.

This plugin gives you a lot of control over how consent is managed and tracked across your website. Using this plugin does not guarantee that you will be compliant with applicable laws and regulations. 

**You are responsible for ensuring compliance with all applicable laws and regulations.**

## Features

- **Geolocation-Based Consent Management**: Display different consent banners based on user location (country and region)
- **Template System**: Create and manage multiple consent banner templates
- **Google Tag Manager Integration**: Built-in support for Google Tag Manager with customizable scripts
- **Google Consent Mode v2**: Support for Google's basic and advanced consent mode
- **Strict Fallback Templates**: Use strict fallback templates to prevent accidental violations of local law
- **Consent Receipts**: Track and optionally store user consent choices for compliance purposes
- **Consent Button Options**: Add a floating button or integrate with WordPress menus to allow users to easily access consent settings
- **Admin Debug Tools**: Test different geolocation scenarios directly from the admin bar
- **Data Layer Integration**: Push consent events to the data layer for advanced tracking

## Requirements

- Tested on WordPress 6.3 and 6.4 (earlier versions may work but are untested)
- [GeoIP Detection](https://wordpress.org/plugins/geoip-detect/) plugin (for geolocation features to work)

## Installation

1. Install the GeoIP Detection plugin from the WordPress plugins directory
2. Upload the `klaro-geo` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress admin
4. Configure Klaro Geo settings under the 'Klaro Geo' menu in the WordPress admin

## Configuration

### Quick Start Guide

Some settings are pre-requisites for other settings. For example, you must configure your consent purposes before you can assign them to tags or give them translations.

Follow this quick start guide to minimize the need to revisit settings.

1. Go to the main settings page **Klaro Geo > Klaro Geo** and ensure the following settings are configured correctly:
  - Google Tag Manager ID is set.
  - Purposes are configured as desired.
2. Go to the services page **Klaro Geo > Services** and configure your services
  - If you plan on using consent mode, be sure that your consent mode services are configured before proceeding to the next step.
  - The service-level **Required** and **Default** fields override the template-level fields of the same name. Any services that should behave differently from the templates need to be configured here. This includes services assigned to required or security purposes.
  - Assign each service to one or more purposes
3. Go to the templates page **Klaro Geo > Templates** and customize the default template and create any other desired templates
  - Ensure that your templates have the **Default State** setting set the desired opt-in or opt-out behavior.
4. Go to the countries page **Klaro Geo > Country Settings** and assign your templates to the Fallback Template and to your desired countries or regions.
5. Go to Google Tag Manager and use the Klaro-generated events to fire your tags 
  - Create a data layer variable assigned to the Data Layer Variable Name of `acceptedServices`.
  - Create a custom event trigger that fires on the `Klaro Consent` event when the `acceptedServices` variable contains the name of the service that you want to trigger. This will trigger page view type events for this service.
  - Create trigger groups that combine the custom event trigger with non-page view triggers as needed.
  - Assign these triggers to your tags.

### General Settings

Navigate to **Klaro Geo > Klaro Geo** to configure the general plugin settings:
- **Klaro Geo Settings**: 
  - Enabled Consent Receipts: Toggle to save receipt number and Klaro Config to browser local storage and the WordPress database
  - Klaro JS Version: Set the Klaro script version (changing this may not work as expected, this plugin has been tested with 0.7)
  - Klaro Script Variant: Choose between standard Klaro script or the no-CSS variant
- **Google Tag Manager**:
  - Google Tag Manager ID: Enter your Google Tag Manager container ID (e.g., GTM-XXXXXX)
- **Country Settings**:
  - Enable/disable geo detection
  - Manage regions within countries
- **Consent Buttons**:
  - Enable Floating Consent Button: Create a persistent floating button that opens the Klaro service management modal
  - Button Theme: Select a style for the button
  - Button Position: Select where the button should appear on the screen
  - WordPress Menu Integration: Add a link that opens the Klaro service management modal into your WordPress menus
  - Shortcode: Use shortcode to add a link that opens the Klaro service management modal into your WordPress pages
- **Purposes**:
  - Purposes (comma-separated): List of purposes by which you want to group your tags
- **Debug Settings**:
  - Enable Plugin Debug Logging: When enabled, the plugin will output debug messages to WordPress logs (PHP) and browser console (JavaScript). Useful for troubleshooting but should be disabled in production to reduce log noise. Note: WP_DEBUG must also be enabled for PHP logging to work.
  - Debug Countries/Regions (comma-separated): Enter two-digit country codes or hyphen-separated ISO 3166-2 region codes for debugging, separated by commas (e.g., US,UK,CA,FR,AU,US-CA,CA-QC)
  - Plugin Cleanup: Remove all settings when deactivating the plugin

### Templates

Templates define the language, layout, and behavior of the consent banner displayed to users. Templates get assigned to countries and regions so that you can change consent features and language by jurisdiction. 

You can not delete templates that are currently assigned to countries or regions. To remove a template, you must first unassign it on the Country Settings page.

Navigate to **Klaro Geo > Templates** to manage consent banner templates.

- **Basic Settings**: 
  - Version: Klaro configuration version.
  - Element ID: Klaro HTML element ID for the Klaro container.
- **Styling**:
  - Theme Color: Choose between Klaro's default light and dark themes.
  - Position: Position of the consent notice.
  - Width: Width of the consent notice.
- **Behavior Settings**:
  - Default State: Default state for services if the user doesn't make a choice
  - Required: When enabled, users cannot decline services. Only use for essential services that are required for your website to function. This setting can also be overridden per-service.
  - HTML Texts: Allow HTML in text fields.
  - Embedded Mode: If enabled, Klaro will render without the modal background, allowing you to embed it into a specific element of your website, such as your privacy notice. 
  - No Auto Load: If enabled, Klaro will not automatically load itself when the page is being loaded. You'll need to manually trigger it.
  - Auto Focus: Automatically focus the consent modal when it appears.
  - Group by Purpose: Group services by their purpose in the consent modal.
- **Cookie Settings**:
  - Storage Method: Save consent choices in a cookie or local storage.
  - Cookie Name: Name of the cookie.
  - Cookie Expires (days): Set the cookie expiry duration in days.
  - Cookie Domain: Domain for the cookie.
  - Cookie Path: Path for the cookie.
- **Consent Modal Settings**:
  - Must Consent: If enabled, users must make a choice before using the site.
  - Accept All: Show an "Accept All" button.
  - Hide Declining All: Hide the "Decline All" button.
  - Hide Learn More: Hide the "Learn More" link.
  - Show Notice Title: Show the title in the consent notice.
  - Show Description for Empty Store: Show description text even when no services are defined.
  - Disable Powered By: Hide the "Powered by Klaro" text.
  - Additional CSS Class: Additional CSS class to add to the consent modal.
  - Default Language: Default language code (e.g., 'en', 'de'). Leave empty to use the fallback language and translation settings ('zz').
- **Consent Mode Settings**:
  - Initialize Consent Mode: Initialize all consent signals (denied) when Google Tag Manager loads and enable other consent mode operations.
  - Map analytics_storage to service: Select the service that enables or disables `analytics_storage`.
  - Map ad signals to service: Select the service that enables or disables `ads_storage` and under which `ad_personalization` and `ad_user_data` controls get injected.
  - Consent Mode Initialization Code: JavaScript code to initialize Google Consent Mode v2. This code will run when Google Tag Manager loads.
- **Cookie Settings**:
  - Enable Consent Logging: Log consent choices for this template in the WordPress database.

#### Translations:
Klaro consent translation settings. All translation settings except for service names and descriptions are managed here in the template settings.

This allows you to create language specific to a legal jurisdiction, for example "do not sell my data" notices in California, and assign the template to that location.

  - Translation Settings: Configure translations for various text elements in the consent notice.
  - Advanced Translations (JSON): Edit translations JSON directly. This is useful for copying and pasting translations between templates.


### Country Settings

Set your fallback template, assign templates to countries and override country templates with region templates.

Navigate to **Klaro Geo > Countries** to assign templates to specific locations:

1. Assign a template to the Fallback Template row.
2. From the dropdown menu in the template column of your chosen country's row, select a template.
3. Select `Manage Regions` to override country settings in specified regions as desired.
4. Select `Hide` to remove a country from the Klaro Geo Country Settings table as desired.
5. Select `Show/Hide Countries` to add and remove countries from the Klaro Geo Country Settings table as desired.
6. Select `Save Changes` to apply your changes.

### Services
Manage services that should show in the consent modal.

Klaro automatically manages [third-party inline scripts](https://klaro.org/docs/getting-started) when the following conditions are met:
- The script has a `type` attribute set to `text/plain`
- The script has a `data-type` attribute set to `application/javascript`.
- The script has a `data-name` attribute matching the service name as defined on this page.

Klaro Geo automatically embeds Google Tag Manager this way.

It should be possible to use this plugin to manage other inline scripts without using Google Tag Manager or to use the inline script method described above to manage other tag managers.

Klaro Geo adds a `Klaro Consent` dataLayer push whenever consent is updated with an `acceptedServices` array containing the names of services that are currently accepted. This can be used to trigger tags in Google Tag Manager and is how the plugin is designed to work.

Navigate to **Klaro Geo > Services** to manage services:

Use the `Add New Service`, `Edit`, and `Delete` buttons to manage services.

Each service gets the following settings derived directly from Klaro:

- Name: The name of the service as it will appear in the acceptedServices array in lowercase with spaces replaced with hyphens.
- Required: Display the service but do not allow visitors to turn off the service. Overrides the template-level setting of the same name. Setting a purpose to 'functional' does not mean the service is automatically loaded. You need to set each functional service's `Required` field to "Yes" in order to automatically load the service.
- Default: Load this service by default before the consent banner is interacted with. Overrides the template-level setting of the same name.
- Purpose: Select the purpose(s) associated with this service. The list of purposes is managed on the main settings page at **Klaro Geo > Klaro Geo > Purposes (comma-separated)**.
- Advanced Settings: Manage additional Klaro settings for the service (these have not been tested with the plugin).
- Callback Scripts: Customize the JavaScript functions called during consent initialization, acceptance, and decline events.

#### Service Translations

The default Fallback translation tab sets the Klaro `zz` translation key for each service.

New languages need to be configured in a template on the Templates page before you can set translation settings here.

Manage the following service-specific translation fields here:

- Title: This is the human-readable name of the service. It should be populated automatically in title-case from the service name.
- Description: A brief description of what the service does.


## Adding Consent Buttons

There are multiple ways to add consent management buttons to your site:

### WordPress Menu Integration

You can add a consent management button to any WordPress menu using a custom link:

1. Go to **Appearance > Menus** in your WordPress admin
2. Create a new menu or edit an existing one
3. In the "Custom Links" section, add a link with:
   - **URL**: # (just a hash symbol)
   - **Link Text**: "Manage Cookies" or whatever text you prefer
4. Click "Add to Menu"
5. Expand the newly added menu item
6. In the **"CSS Classes (optional)"** field, add the class `open-klaro-modal`
   - **IMPORTANT**: While the field is labeled "optional" in WordPress, the `open-klaro-modal` class is **required** for the menu item to open the Klaro modal. Without this class, the link will not function.
7. Save the menu

When clicked, this menu item will open the Klaro consent management popup, allowing users to update their consent preferences at any time.

### Shortcode

You can use the `[klaro_consent_button]` shortcode to add a consent button anywhere shortcodes are supported:

```
[klaro_consent_button text="Manage Cookie Settings" class="my-custom-class" style="button"]
```

Parameters:
- **text**: (Optional) The button text. Defaults to the text configured in settings.
- **class**: (Optional) Additional CSS classes to add to the button.
- **style**: (Optional) Set to "link" for a text link instead of a button.

Examples:
```
[klaro_consent_button]
[klaro_consent_button text="Privacy Settings" style="link"]
[klaro_consent_button class="button button-primary" text="Cookie Settings"]
```

### Floating Button

The plugin also provides a floating button option that can be enabled in the settings. This button can be configured to appear at the bottom or top to the left or right of the viewport and is always accessible to visitors.

## Customizing Klaro Appearance

Klaro has built-in theme settings and Klaro Geo has theme settings for the floating consent button.

Klaro Geo allows you to select either the standard Klaro script (`klaro.js`) or a version that does not load its own CSS (`klaro-no-css.js`). Both scripts provide similar functionality, but they differ in their approach to styling.

The translation settings for the plugin are managed in template and service configurations.

Developers can set custom CSS classes and override Klaro's default styles. 

The GeoIP Detection plugin also includes an option to add the detected country and region as a class to the body element. This can be used to customize the appearance of the consent banner based on the visitor's location beyond the text and behavior settings provided natively by Klaro and Klaro Geo.

### Klaro Script Variants 

The plugin supports different Klaro script variants:

1. **Standard (klaro.js)**: The default Klaro script with built-in styles.
2. **No CSS (klaro-no-css.js)**: A version of Klaro without styles, which is useful if you want to use your own CSS or customize the default styles.


## Google Tag Manager Integration

The plugin directly loads Google Tag Manager in a Klaro-compatible way, ensuring proper consent management.

### How to Set Up GTM

1. Go to **Klaro Geo > Klaro Geo > Google Tag Manager**
2. Enter your Google Tag Manager ID (e.g., GTM-XXXXXX)
3. Save your settings

The plugin will automatically:
- Add the GTM script to your site's header with the proper Klaro attributes
- Add the GTM noscript iframe to your site's body with the proper Klaro attributes
- Configure consent callbacks to manage GTM's consent mode

### Integrating Klaro Geo into Google Tag Manager

Klaro Geo adds two dataLayer pushes: 'Klaro Event' and 'Klaro Consent Update'.

#### Klaro Event

This event is fired when Klaro fires internal events as well as when Klaro Geo fires internal events. They are useful for debugging and adding consent data as Data Layer variables.

These events can include the following parameters:
- `event: "Klaro Event"`: Hard-coded value for all of these events
- `eventSource: "klaro|klaro-geo"`: Indicates whether the event came from Klaro or Klaro Geo
- `klaroEventName: "klaroConfigLoaded|initialConsents|consents|saveConsents|applyConsents|generateConsentReceipt"`: The name of the event. This is the `manager.name` value for Klaro events.
- `klaroEventData: {google-tag-manager: true, google-analytics: true}`: An object containing the names and consent settings for all services currently set by Klaro.
- `acceptedServices: ["google-tag-manager", "google-analytics"]` (initialConsents and saveConsents only): An array of service names that are currently accepted.
- `klaroConfig: {version: "1", ...}`: The complete Klaro configuration object.
- `klaroGeoConsentTemplate: "Default"` (klaroConfigLoaded only): The name of the assigned template
- `klaroGeoTemplateSource: "default|fallback|admin-override|geo-match"` (klaroConfigLoaded only): The source rule that assigned this template.
- `klaroGeoDetectedCountry: "US"` (klaroConfigLoaded only): The detected country
- `klaroGeoDetectedRegion: "US-CA"` (klaroConfigLoaded only): The detected region
- `klaroGeoAdminOverride: false` (klaroConfigLoaded only): Whether the admin override was applied to the location detection rules
- `klaroGeoEnableConsentLogging: true|false` (klaroConfigLoaded and generateConsentReceipt): Whether consent receipt logging is enabled for the current template/country
- `klaroGeoConsentReceipt: {...}` (klaroConfigLoaded and generateConsentReceipt): The complete consent receipt object if available. For klaroConfigLoaded, this will be the most recent consent receipt stored in localStorage, if one exists.

#### Klaro Consent Update

This event is the main firing trigger for most Google Tag Manager tags. It is triggered after initialConsents and saveConsents and Google Consent Mode `Consent Update` events. It simplifies the trigger setup in GTM and resolves race conditions between Klaro and Consent Mode.

This event can include the following parameters:
- `event: "Klaro Consent Update"`: Hard-coded value for all of these events
- `eventSource: "klaro-geo"`: This should always be klaro-geo
- `acceptedServices: ["google-tag-manager", "google-analytics"]`: An array of service names that are currently accepted.
- `triggerEvent: "initialConsents|saveConsents"`: The name of the event that triggered this event.

It is expected that you will created a Data Layer Variable named `acceptedServices` and then use the Data Layer Variable contains "google-analytics" to trigger the Google Analytics tag, for example. Note that the names of Klaro services are lowercased and hyphenated when set in acceptedServices.

### How It Works

Klaro Geo uses Klaro's native consent management to control the loading of Google Tag Manager.

When a user visits your site:

1. GTM tags are initially blocked (we set an invalid `type="text/plain"`)
2. Consent Mode defaults are added after the klaroConfig object
3. When the user gives consent (or is defaulted in, or reads consent settings from a previous page) to Google Tag Manager, Klaro changes the type to `text/javascript` which triggers Google Tag Manager
4. Klaro Geo fires the initialConsents event which triggers Consent Mode `Consent Update` events and `Klaro Consent Update` events
5. Google Tag Manager reads the consent settings and Data Layer variables and triggers tags as configured
6. Saving consent changes triggers Consent Mode `Consent Update` events and `Klaro Consent Update` events
7. Google Tag Manager reads these updated settings and Data Layer variables and triggers tags as configured


## Geo Detection

Klaro Geo uses the [GeoIP Detection plugin](https://en-ca.wordpress.org/plugins/geoip-detect/) to determine a user's location. This information is used to decide which consent banner template to show.

The GeoIP Detection plugin lets you select different IP databases. In testing, the free HostIP.info Web-API database frequently failed to return results. This would result in the fallback consent template being shown. 

For this reason, we recommend using strict fallback templates. 

In addition, it is recommended that you test one of paid, commercial services supported by the plugin. Klaro Geo pushes a `Klaro Config Loaded` event to the datalayer that includes the following fields useful for evaluating the performance of the plugin and IP detection:
 
- `klaro_geo_consent_template`: The ID of the detected template
- `klaro_geo_template_source`: The rule used to determine the template
- `klaro_geo_detected_country`: The detected country
- `klaro_geo_detected_region`: The detected region
- `klaro_geo_admin_override`: Whether the admin override was applied to the location detection rules

Geo detection is optional. If disabled, the fallback template will always be shown regardless of user location. You will still be able to assign templates to countries and regions, it just won't do anything until the GeoIP Detection plugin is installed and configured.

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

### Passing Consent Receipt Numbers to Analytics Tools

The consent receipt number is passed to the dataLayer via a `consentReceiptNumber` property. This allows you to track consent receipts in your analytics tool of choice to faciliate deletion requests by receipt number.

## Support and Contributions

For support requests, feature suggestions, or to report bugs, please use the GitHub repository's issue tracker.