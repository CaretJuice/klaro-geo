# Klaro Geo

This plugin integrates the [Klaro](https://github.com/KIProtect/klaro) consent management tool with the [Geolocation IP Detection](https://en-ca.wordpress.org/plugins/geoip-detect/) Wordpress plugin to allow you to target different consent banners using Klaro.

It is designed to work with Google Tag Manager where Klaro controls the loading of Google Tag Manager. For optimal reliability, we recommend loading Google Tag Manager via the plugin settings page rather than manually adding it to your site. This ensures the GTM snippet is properly customized to integrate with Klaro.

It allows you to create and manage multiple consent banner templates, assign each template to users by location, and track consent decisions for compliance purposes.

This plugin gives you a lot of control over how consent is managed and tracked across your website. Using this plugin does not guarantee that you will be compliant with applicable laws and regulations. 

**You are responsible for ensuring compliance with all applicable laws and regulations.**

## Features

- **Geolocation-Based Consent Management**: Display different consent banners based on user location (country and region).
- **Template System**: Create and manage multiple consent banner templates.
- **Google Tag Manager Integration**: Built-in support for Google Tag Manager with including a custom community template.
- **Google Consent Mode v2**: Support for Google's basic and advanced consent mode.
- **Simplified GTM Consent Triggers**: Custom Consent Mode consent keys allow for a cleaner triggering of consented services in Google Tag Manager.
- **Fallback Templates**: Use strict fallback templates to prevent accidental violations of local law.
- **Granular Consent Receipts**: Automatically generates consent receipts stored in browser local storage for user reference if enabled. Optionally configure specific templates to archive these receipts in your WordPress database, allowing you to retain records only for jurisdictions that require it while blocking storage for others.
- **Consent Buttons**: Add a floating button or integrate with WordPress menus to allow users to easily access consent settings.
- **Bidirectional Data Layer Integration**: A full-duplex integration that listens for dataLayer events to trigger plugin actions, forwards native Klaro events to the dataLayer, and dispatches its own enhanced consent status events.
- **Admin Debug Tools**: Test different geolocation scenarios directly from the admin bar.



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

**Important**: Configuration order matters. You must define foundational settings (like Purposes) before you can assign them to Services or configure Translations.

Please follow the steps below in order to ensure a smooth setup and avoid having to backtrack.

1. Go to the main settings page **Klaro Geo > Klaro Geo** and ensure the following settings are configured correctly:
  - Google Tag Manager ID is set.
  - Purposes are configured as desired.
2. Go to the services page **Klaro Geo > Services** and configure your services
  - If you plan on using consent mode, be sure that your consent mode services are configured before proceeding to the next step.
  - The service-level **Required** and **Default** fields override the template-level fields of the same name. Any services that should behave differently from the templates need to be configured here. This includes services assigned to required or security purposes.
  - Assign each service to one or more purposes
3. Go to the templates page **Klaro Geo > Templates** and customize the default template and create any other desired templates
  - Ensure that your templates have the **Default State** setting set the desired opt-in or opt-out behavior.
4. Go to the countries page **Klaro Geo > Country Settings** and map a template to the Fallback Template and to your desired countries or regions.
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
- **Consent Management Buttons**:
  - Enable Floating Consent Button: Create a persistent floating button that opens the Klaro service management modal
  - Button Text: Enter the text for the floating consent button
  - Button Theme: Select a style for the button
  - Button Position: Select where the button should appear on the screen
  - WordPress Menu Integration: Instructions on how to add a link that opens the Klaro service management modal into your WordPress menus
  - Shortcode: Instructions on how to use a shortcode to add a link that opens the Klaro service management modal into your WordPress pages
- **Purposes**:
  - Purposes (comma-separated): List of purposes by which you want to group your tags
- **Data Layer Settings**:
  - Suppress Individual Klaro Consent Event dataLayer Pushes: Klaro sends a "consents" event every time an individual consent toggle is changed in the consent banner. These don't take effect until the user saves their choices, at which point the plugin sends a consolidated event. Klaro Geo is configured to forward Klaro events to the dataLayer. Enabling this setting (recommended) suppresses the individual toggle events, which can pollute the dataLayer when users toggle multiple settings. Disable this only if you need to track individual consent toggle interactions within the banner.

- **Debug Settings**:
  - Enable Plugin Debug Logging: When enabled, the plugin will output debug messages to WordPress logs (PHP) and browser console (JavaScript). Useful for troubleshooting but should be disabled in production to reduce log noise. Note: WP_DEBUG must also be enabled for PHP logging to work.
  - Debug Countries/Regions (comma-separated): Enter a list of two-digit country codes or ISO 3166-2 region codes (e.g., US, UK, US-CA, CA-QC). These codes will populate a dropdown in the WordPress Admin Bar, allowing you to simulate browsing from those locations to test your Klaro configuration.
  - Plugin Cleanup: Remove all settings when deactivating the plugin. You will need to reconfigure everything if you reactivate the plugin.

### Templates

Templates define the language, layout, and behavior of the consent banner displayed to users. Templates get assigned to countries and regions so that you can change consent features and language by jurisdiction. 

Most of the template settings are derived directly from Klaro settings but Klaro Geo settings, like Google Consent Mode settings and Consent Logging, that need to be managed by country or region are also set by templates.

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
#### Consent Mode Settings:
  Consent mode is always enabled and generates dynamic consent keys for every service. Configure which services control the standard Google Consent Mode signals:
  - Map analytics_storage to service: Select the service that enables or disables `analytics_storage`.
  - Map ad signals to service: Select the service that enables or disables `ads_storage` and under which `ad_personalization` and `ad_user_data` controls get injected.
  ![ad_personalization and ad_user_data controls injected by Klaro Geo](assets/ad_personalization-ad_user_data-controls.png)
  - ##### Google Consent Mode Defaults
    - ad_storage: Default `ad_storage` consent mode value
    - analytics_storage: Default `analytics_storage` consent mode value
    - ad_user_data: Default `ad_user_data` consent mode value
    - ad_personalization: Default `ad_personalization` consent mode value
  - ##### Additional Google Settings
    - ads_data_redaction: Manage the redaction of ad click identifiers when `ad_storage` is denied
    - url_passthrough: Manage the appending of ad click identifiers as URL parameters when `ad_storage` or `analytics_storage` is denied 
  - ##### Service Consent Defaults
    - View the custom consent key and default state of each service
- **Plugin Settings**:
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

It should be possible to use this plugin to manage other inline scripts without using Google Tag Manager or to implement other tag managers. However, it will require advanced knowledge of Klaro, Google Tag Manager, and Klaro Geo to get it to work correctly.

Klaro Geo adds a `Klaro Consent Update` dataLayer push whenever consent is updated with custom consent keys as well as an `acceptedServices` array containing the names of services that are currently accepted.

![standard and custom consent keys in Google Tag Manager](assets/custom_consent_keys.png)

Use the `Klaro Consent Update` event with "Require additional consent fo tag to fire" set to the custom consent key for the relevant service to set up page view tags to fire when consent is given to a specific service. The `acceptedServices` array is for use in situations where Google Consent Mode is not applicable (like when using a tag manager other than Google Tag Manager).

Navigate to **Klaro Geo > Services** to manage services:

Use the `Add New Service`, `Edit`, and `Delete` buttons to manage services.

Each service gets the following settings that Klaro consumes and uses:

- Name: The name of the service as it will appear in the acceptedServices array in lowercase with spaces replaced with hyphens.
- Required: Display the service but do not allow visitors to turn off the service. Overrides the template-level setting of the same name. 

**Functional service warning**: Setting a purpose to 'functional' or 'security' or some other purpose that does not require consent does not mean the service is automatically loaded. You need to set each functional service's `Required` field here to "Yes" in order to automatically load the service.
- Default: Load this service by default before the consent banner is interacted with. Overrides the template-level setting of the same name.
- Purpose: Select the purpose(s) associated with this service. The list of purposes is managed on the main settings page at **Klaro Geo > Klaro Geo > Purposes (comma-separated)**. It is best to decide on your list of purposes before you start assigning them here.
- Advanced Settings: Manage additional Klaro settings for the service (these have not been tested with the plugin).
- Callback Scripts: Customize the JavaScript functions called during consent initialization, acceptance, and decline events.

#### Service Translations (only shown when editing a specific service)

The default Fallback translation tab sets the Klaro `zz` translation key for each service.

New languages need to be configured in a template on the Templates page before you can set translation settings here.

Manage the following service-specific translation fields here:

- Title: This is the human-readable name of the service. It should be populated automatically in title-case from the service name.
- Description: A brief description of what the service does.

## Consent Receipts

The Consent Receipts page lets you browse the consent receipts logged in Wordpress. 

Consent receipts need to be enabled under **Klaro Geo > Enable Consent Receipts** for you to view data here.

You will only see data from countries / regions with a template that enables consent logging under **Templates > select desired template > Enable Consent Logging**.

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

### GTM Consent Mode Template

Klaro Geo includes a **Google Tag Manager Community Template** for consent mode integration. This template uses GTM's native consent APIs (`setDefaultConsentState()` and `updateConsentState()`) which update consent state immediately, avoiding timing issues with queued `gtag()` commands.

**Why use the GTM template?**
- Ensures consent is set BEFORE tags fire
- Supports both standard signals (`analytics_storage`, `ad_storage`) and custom service-specific consent types (`google_analytics_consent`, etc.)
- Handles first-page attribution when consent is granted after page load

**Setup overview:**
1. Import the template from `gtm-template/template.tpl` into GTM
2. Create a "Default" tag triggered on "Consent Initialization - All Pages"
3. Create an "Update" tag triggered on the `Klaro Consent Data` custom event
4. Configure your GA4/Google Ads tags to require appropriate consent signals

For detailed setup instructions, see the [GTM Template README](gtm-template/README.md).

### How to Set Up GTM

1. Go to **Klaro Geo > Klaro Geo > Google Tag Manager**
2. Enter your Google Tag Manager ID (e.g., GTM-XXXXXX)
3. Save your settings

The plugin will automatically:
- Add the GTM script to your site's header with the proper Klaro attributes
- Add the GTM noscript iframe to your site's body with the proper Klaro attributes
- Configure consent callbacks to manage GTM's consent mode

### Integrating Klaro Geo into Google Tag Manager

Klaro Geo adds three dataLayer pushes: 'Klaro Event', 'Klaro Consent Data' and 'Klaro Consent Update'.

#### Klaro Event

This event is fired when Klaro fires internal events as well as when Klaro Geo fires internal events. They are useful for debugging and adding consent data as Data Layer variables.

These events can include the following parameters:
- `event: "Klaro Event"`: Hard-coded value for all of these events
- `eventSource: "klaro|klaro-geo"`: Indicates whether the event Klaro Geo or was forwarded from Klaro
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

#### Klaro Consent Data

This event is pushed by the plugin when consent state changes. It contains raw consent data for the GTM template to process.

Parameters:
- `event: "Klaro Consent Data"`: Hard-coded event name
- `eventSource: "klaro-geo"`: This should always be klaro-geo
- `consentMode: {...}`: Object containing all consent signals (standard and custom)
- `acceptedServices: ["google-tag-manager", "google-analytics"]`: Array of accepted service names
- `triggerEvent: "initialConsents|saveConsents"`: The Klaro event that triggered this

**Important**: Use this event as the trigger for the GTM Update tag from the community template, NOT for your GA4/Google Ads tags in a standard setup.

#### Klaro Consent Update

This event is pushed by the GTM template AFTER calling `updateConsentState()`. This is the recommended trigger for your GA4 and Google Ads tags because consent is guaranteed to be set when this event fires.

Parameters:
- `event: "Klaro Consent Update"`: Hard-coded event name
- `consent_trigger: "initialConsents|saveConsents"`: The Klaro event that triggered this
- `analytics_storage: "granted|denied"`: Current analytics consent state
- `ad_storage: "granted|denied"`: Current ads consent state
- `ad_user_data: "granted|denied"`: Current ad user data consent state
- `ad_personalization: "granted|denied"`: Current ad personalization consent state
- `consentMode: {...}`: Complete consent mode object
- `acceptedServices: [...]`: Array of accepted service names

**Event Flow**:
1. Plugin pushes `Klaro Consent Data` (raw data)
2. GTM Update tag fires, calls `updateConsentState()`, pushes `Klaro Consent Update`
3. Your tags trigger on `Klaro Consent Update` (consent is set)

It is expected that you will create a Data Layer Variable named `acceptedServices` and then use "Data Layer Variable contains google-analytics" to trigger the Google Analytics tag, for example. Note that the names of Klaro services are lowercased and hyphenated when set in acceptedServices.

#### Dynamic Consent Keys

In addition to the standard Google Consent Mode signals (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`), Klaro Geo automatically generates dynamic consent keys for every service defined in your configuration. This allows you to fire tags in GTM based on granular per-service consent without relying on Trigger Groups.

**Key Format**: `[service_name]_consent`
- Hyphens in service names are converted to underscores
- A `_consent` suffix is appended

**Examples**:
| Service Name | Dynamic Consent Key |
|-------------|---------------------|
| `google-analytics` | `google_analytics_consent` |
| `piwik` | `piwik_consent` |
| `facebook` | `facebook_consent` |
| `contact-form-7` | `contact_form_7_consent` |

**Values**: Each key is set to either `'granted'` or `'denied'` based on whether the user consented to that service.

**GTM Usage**: Access these keys in the `consentMode` object usually via the "Require additional consent for tag to fire" setting under "Additional Consent Checks" in your tags:
```javascript
// Example consentMode object pushed to dataLayer
{
  'ad_storage': 'denied',
  'analytics_storage': 'granted',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'google_analytics_consent': 'granted',
  'piwik_consent': 'granted',
  'facebook_consent': 'denied'
}
```

#### Using with Other Tag Managers

The dataLayer format follows Google Tag Manager conventions but can be read by other tag managers (Tealium, Adobe Launch, etc.). The `Klaro Consent Update` event relies on a Google Tag Manager community template to fire which won't work unless you are using Google Tag Manager beside this other tag manager. In cases where Google Tag Manager is not in use, use the `Klaro Consent Data` event to trigger your tags. The `Klaro Consent Data` > `Klaro Consent Update` event flow is implemented to prevent race conditions in Google Tag Manager's integration in to Google Consent Mode:
- `consentMode`: Object with all consent keys (both Google standard and dynamic service keys)
- `acceptedServices`: Array of service names with granted consent
- `triggerEvent`: Either `'initialConsents'` or `'saveConsents'`

### How It Works

Klaro Geo uses Klaro's native consent management to control the loading of Google Tag Manager.

When a user visits your site:

1. GTM tags are initially blocked (we set an invalid `type="text/plain"`)
2. Consent Mode defaults are added after the klaroConfig object
3. When the user gives consent (or is defaulted in, or reads consent settings from a previous page) to Google Tag Manager, Klaro changes the type to `text/javascript` which triggers Google Tag Manager
4. Klaro Geo fires the initialConsents event which triggers Consent Mode `Klaro Consent Data` events where the community template tag triggers `Consent Update` (for Consent Mode) and `Klaro Consent Update` (for triggering page view tags) events
5. Google Tag Manager reads the consent settings and Data Layer variables and triggers tags as configured
6. Saving consent changes triggers Consent Mode `Consent Update` events and `Klaro Consent Update` events
7. Google Tag Manager reads these updated settings and Data Layer variables and triggers tags as configured


## Geo Detection

Klaro Geo uses the [GeoIP Detection plugin](https://en-ca.wordpress.org/plugins/geoip-detect/) to determine a user's location. This information is used to decide which consent banner template to show.

The GeoIP Detection plugin lets you select different IP databases. In testing, the free HostIP.info Web-API database frequently failed to return results. This would result in the fallback consent template being shown. 

For this reason, we recommend using strict fallback templates. 

In addition, it is recommended that you test one of the paid, commercial services supported by the plugin. Klaro Geo pushes a `Klaro Config Loaded` event to the datalayer that includes the following fields useful for evaluating the performance of the plugin and IP detection:
 
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

Please note that browser privacy safeguards, like Apple's Privacy Relay, make regional detection unreliable. Beyond just the issues with IP database accuracy, these safeguards deliberately change the location of visitors. Where country regulations are relaxed but regional regulations are stricter, you should consider using the stricter templates for the entire country in order to avoid inadvertently violating regional regulations.

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

3. Receipts are also pushed to the dataLayer for integration with analytics tools.

### Enabling/Disabling Consent Receipts

- **Global Setting**: Enable/disable consent receipts globally in Klaro Geo Settings
- **Per-Template Setting**: Enable/disable server-side logging for specific templates

### Viewing Consent Receipts

Administrators can view stored consent receipts under **Klaro Geo > Consent Receipts** in the WordPress admin.

### Passing Consent Receipt Numbers to Analytics Tools

The consent receipt number is passed to the dataLayer via a `consentReceiptNumber` property. This allows you to track consent receipts in your analytics tool of choice to facilitate deletion requests by receipt number.

## Support and Contributions

For support requests, feature suggestions, or to report bugs, please use the GitHub repository's issue tracker.