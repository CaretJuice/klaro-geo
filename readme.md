# Klaro Geo

This plugin integrates the [Klaro](https://github.com/KIProtect/klaro) consent management tool with the [Geolocation IP Detection](https://en-ca.wordpress.org/plugins/geoip-detect/) Wordpress plugin to allow you to target different consent banners using Klaro.

It is designed to work with Google Tag Manager where Klaro controls the loading of Google Tag Manager. For optimal reliability, we recommend loading Google Tag Manager via the plugin settings page rather than manually adding it to your site. This ensures the GTM snippet is properly customized to integrate with Klaro.

It allows you to create and manage multiple consent banner templates, assign each template to users by location, and track consent decisions for compliance purposes.

This plugin gives you a lot of control over how consent is managed and tracked across your website. Using this plugin does not guarantee that you will be compliant with applicable laws and regulations. 

**You are responsible for ensuring compliance with all applicable laws and regulations.**

## Features

- **Geolocation-Based Consent Management**: Display different consent banners based on user location (country and region).
- **Template System**: Create and manage multiple consent banner templates.
- **Google Tag Manager Integration**: Built-in support for Google Tag Manager with zero-config consent mode.
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
5. Go to Google Tag Manager and configure your tags to use consent-based firing:
  - For Google tags (GA4, Google Ads): fire on normal triggers (e.g., `All Pages`). Consent mode is handled automatically via `gtag()`.
  - For non-Google tags: use GTM's **"Require additional consent for tag to fire"** with custom consent keys (e.g., `facebook_consent`). Fire on `Klaro Consent Update` event.
  - Alternative: Create a Data Layer Variable for `acceptedServices` and use Trigger Groups for more control (see [Tag Trigger Setup](#configuring-tag-triggers-in-gtm)).

### General Settings

Navigate to **Klaro Geo > Klaro Geo** to configure the general plugin settings:
- **Klaro Geo Settings**: 
  - Enabled Consent Receipts: Toggle to save receipt number and Klaro Config to browser local storage and the WordPress database
  - Klaro JS Version: Set the Klaro script version (changing this may not work as expected, this plugin has been tested with 0.7)
  - Klaro Script Variant: Choose between standard Klaro script or the no-CSS variant
- **Google Tag Manager**:
  - Google Tag Manager ID: Enter your Google Tag Manager container ID (e.g., GTM-XXXXXX)
  - Consent Mode Type: Choose between Basic and Advanced consent mode (see [Basic vs Advanced Consent Mode](#basic-vs-advanced-consent-mode) below)
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

Most of the template settings are derived directly from Klaro settings. Template-specific Klaro Geo settings include additional Google settings (ads_data_redaction, url_passthrough) and Consent Logging.

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
  Consent mode is always enabled. The standard Google Consent Mode signals (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`) are controlled by dedicated [consent mode services](#consent-mode-services) configured in the Services section. Consent defaults for each service are determined by the service's required/default state and this template's Default State setting.
  - ##### Additional Google Settings
    - ads_data_redaction: Manage the redaction of ad click identifiers when `ad_storage` is denied
    - url_passthrough: Manage the appending of ad click identifiers as URL parameters when `ad_storage` or `analytics_storage` is denied
  - ##### Service Consent Defaults
    - Read-only view of the custom consent key and default state for each service
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

Use custom consent keys with GTM's **"Require additional consent for tag to fire"** to gate any tag on a specific service's consent (e.g., add `facebook_consent` to a Facebook Pixel tag). Alternatively, use the `Klaro Consent Update` event with a Data Layer Variable trigger condition (e.g., `consentMode.facebook_consent` equals `granted`). The `acceptedServices` array is for use in situations where Google Consent Mode is not applicable (like when using a tag manager other than Google Tag Manager).

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

### Consent Mode (Zero-Config)

Klaro Geo automatically sets up Google Consent Mode v2 without any GTM template or additional configuration:

- **Consent defaults** are set via `gtag('consent', 'default', ...)` in the `<head>` before any tags load
- **Consent updates** are called via `gtag('consent', 'update', ...)` directly when users interact with the consent banner
- **Custom consent types** (e.g., `facebook_consent`) work natively with GTM's "Require additional consent for tag to fire"

This means consent mode works out of the box for both Google and non-Google tags.

### GTM Consent Gate Variable (Optional)

Klaro Geo includes an optional **Consent Gate Variable** template for GTM. This variable acts as a consent-aware wrapper -- it passes through a value when consent is granted and returns a redacted value when denied. Useful for redacting user-provided data (email, phone) when `ad_user_data` is denied, or zeroing out transaction values when `ad_storage` is denied.

For setup instructions, see the [GTM Template README](gtm-template/README.md).

### How to Set Up GTM

1. Go to **Klaro Geo > Klaro Geo > Google Tag Manager**
2. Enter your Google Tag Manager ID (e.g., GTM-XXXXXX)
3. Select your **Consent Mode Type** (Basic or Advanced) - see [Basic vs Advanced Consent Mode](#basic-vs-advanced-consent-mode) below
4. Save your settings

The plugin will automatically:
- Add the GTM script to your site's header (with Klaro gating in Basic mode, or ungated in Advanced mode)
- Add the GTM noscript iframe to your site's body
- Configure consent callbacks to manage GTM's consent mode

### Basic vs Advanced Consent Mode

Klaro Geo supports two consent mode types that control how Google Tag Manager interacts with the consent banner. Both modes use Google Consent Mode v2 and the Klaro Geo GTM community template.

#### Basic Consent Mode (Default)

In Basic mode, **GTM is completely blocked until the user consents** to the `google-tag-manager` service. No data is sent to Google before consent.

**How it works:**
1. The GTM script tag is rendered with `type="text/plain"` and `data-name="google-tag-manager"`, which prevents execution
2. When the user grants consent to the `google-tag-manager` service, Klaro changes the type to `text/javascript`, which triggers GTM to load
3. The GTM community template sets consent defaults and then updates consent state based on the user's choices
4. Tags fire according to their consent requirements

**When to use Basic mode:**
- When you want zero data collection before explicit consent
- For the strictest interpretation of GDPR and similar regulations
- When you don't need Google's behavioral modeling features

**WordPress settings:**
- Go to **Klaro Geo > Klaro Geo > Google Tag Manager**
- Set **Consent Mode Type** to **Basic**

#### Advanced Consent Mode

In Advanced mode, **GTM loads immediately** (without Klaro gating) but all consent signals default to `denied`. Google tags like GA4 can send cookieless pings before consent, enabling behavioral modeling and basic measurement without cookies.

**How it works:**
1. The GTM script tag is rendered as a normal `<script>` tag (no Klaro `type="text/plain"` gating)
2. GTM loads immediately on page load, but the community template sets all consent signals to `denied`
3. GA4 sends cookieless pings (if configured) while consent is denied, enabling behavioral modeling
4. When the user interacts with the Klaro consent banner, consent state is updated
5. After consent is granted, full tracking begins with cookies

**When to use Advanced mode:**
- When you want to take advantage of Google's behavioral modeling (which fills in gaps in conversion data)
- When your legal interpretation allows cookieless data collection before consent
- When you want GA4 to capture anonymous page view data even without consent

**WordPress settings:**
- Go to **Klaro Geo > Klaro Geo > Google Tag Manager**
- Set **Consent Mode Type** to **Advanced**

**Important notes for Advanced mode:**
- The `google-tag-manager` service still appears in the Klaro consent dialog. Users can still toggle it off, which affects the consent state of your tags.
- `Klaro Consent Update` events are pushed on initial page load (they are not skipped like in Basic mode where GTM hasn't loaded yet).
- The GTM community template is optional but still recommended for advanced use cases like first-page attribution.

#### Side-by-Side Comparison

| Behavior | Basic | Advanced |
|----------|-------|----------|
| GTM loads before consent | No | Yes |
| Data sent before consent | None | Cookieless pings only (if configured in GA4) |
| Consent defaults | `denied` (set in `<head>` via `gtag`) | `denied` (set in `<head>` via `gtag`) |
| Behavioral modeling | Not available | Available |
| `Klaro Consent Update` on initial load | Skipped (GTM not loaded yet) | Pushed immediately |
| GTM script tag | `type="text/plain"` with `data-name` | Normal `<script>` tag |
| User can revoke GTM consent | Yes (via consent dialog) | Yes (via consent dialog) |

#### Configuring Tag Triggers in GTM

How you configure tag triggers differs significantly between Google tags and non-Google tags, and between Basic and Advanced consent mode. This is because Google tags have **built-in consent checks** that make them consent-aware -- they adjust their own behavior based on consent state rather than needing to be blocked or unblocked.

##### Google Tags (GA4, Google Ads) -- Advanced Mode

In Advanced mode, Google tags should fire on their **normal triggers** (e.g., `All Pages` for the Google Tag / GA4 Config, specific events for conversion tags). **Do not** add "Require additional consent for tag to fire" -- this would block the tag entirely and prevent cookieless pings.

Google tags have built-in consent checks for `analytics_storage`, `ad_storage`, `ad_user_data`, and `ad_personalization`. When these signals are `denied`, Google tags still fire but automatically send **cookieless pings** instead of full measurement data. When consent state changes (via `gtag('consent', 'update')`), Google tags automatically adjust to full tracking -- they do not need to be re-triggered.

| Phase | What happens |
|-------|-------------|
| Page load (before consent) | Google Tag fires on `All Pages`. Consent is `denied`. GA4 sends cookieless pings for [behavioral modeling](https://support.google.com/analytics/answer/11161109). No cookies are set. |
| User grants consent | Plugin calls `gtag('consent', 'update')` directly. Google tags automatically switch to full tracking with cookies. |
| Returning visitor with cookie | Plugin reads consent state on page load, calls `gtag('consent', 'update')` with `granted`. Google Tag fires on `All Pages` with full tracking from the start. |

##### Google Tags (GA4, Google Ads) -- Basic Mode

In Basic mode, GTM is completely blocked until the user consents to the `google-tag-manager` service. There are no cookieless pings and no behavioral modeling.

Google tags can fire on `All Pages` since by the time GTM loads and `All Pages` fires, consent has already been established. Alternatively, triggering on `Klaro Consent Update` also works.

| Phase | What happens |
|-------|-------------|
| Page load (before consent) | GTM is not loaded. Nothing happens. |
| User grants consent | Klaro unblocks GTM. GTM loads, community template sets consent, `All Pages` fires. Tags fire with full tracking. |
| Returning visitor with cookie | Klaro reads cookie and immediately unblocks GTM. Same as above. |

##### Non-Google Tags (Facebook Pixel, LinkedIn Insight, etc.)

Non-Google tags do not have built-in consent awareness. They cannot send cookieless pings and have no equivalent to behavioral modeling. **The configuration is the same in both Basic and Advanced mode.**

Since the plugin now calls `gtag('consent', 'update')` directly with all consent types (including custom ones like `facebook_consent`), you can use GTM's **"Require additional consent for tag to fire"** for any consent type. This is the simplest approach:

1. Open your non-Google tag (e.g., Facebook Pixel)
2. Go to **Advanced Settings** > **Consent Settings**
3. Check **Require additional consent for tag to fire**
4. Add the consent type: `facebook_consent` (use the service name with hyphens replaced by underscores, plus `_consent`)

**Alternative: DLV approach** (still works, captures more data with Trigger Groups):

1. **Create a Data Layer Variable** for the service's consent key:
   - **Data Layer Variable Name**: `consentMode.facebook_consent` (replace with your service name)
2. **Create a Custom Event trigger** for `Klaro Consent Update`:
   - Add condition: your DLV **equals** `granted`
3. **Assign the trigger** to your non-Google tag

The DLV approach works because the full `consentMode` object (including all custom consent keys) is included in the `Klaro Consent Update` dataLayer event.

| Phase | What happens (both modes) |
|-------|--------------------------|
| Page load (before consent) | Nothing. Tag is either not loaded (Basic) or blocked by trigger condition (Advanced). |
| User grants consent | `Klaro Consent Update` fires. DLV condition is met. Tag fires. |
| Returning visitor with cookie | `Klaro Consent Update` fires on page load with consent already `granted`. Tag fires immediately. |

##### Summary

- **Google tags in Advanced mode**: Fire on normal triggers (`All Pages`, etc.). Do NOT add additional consent requirements. Built-in consent checks handle cookieless pings and automatic adjustment when consent changes.
- **Google tags in Basic mode**: Fire on `All Pages` or `Klaro Consent Update`. Both work since GTM only loads after consent. No cookieless pings.
- **Non-Google tags in both modes**: Use "Require additional consent for tag to fire" with the custom consent key (e.g., `facebook_consent`). Alternatively, fire on `Klaro Consent Update` with a DLV trigger condition (e.g., `consentMode.facebook_consent` equals `granted`).

### Consent Mode Services

Klaro Geo includes four built-in **consent mode services** that map directly to Google Consent Mode v2 signals. These appear as toggleable services in the Klaro consent dialog, giving users granular control over consent signals.

| Service Name | Consent Mode Signal | Purpose | Parent |
|-------------|-------------------|---------|--------|
| `analytics-storage` | `analytics_storage` | analytics | - |
| `ad-storage` | `ad_storage` | advertising | - |
| `ad-user-data` | `ad_user_data` | advertising | `ad-storage` |
| `ad-personalization` | `ad_personalization` | advertising | `ad-storage` |

These services are automatically added to your configuration on plugin activation. They can be managed like any other service under **Klaro Geo > Services**.

**Parent-child dependencies:** `ad-user-data` and `ad-personalization` are children of `ad-storage`. If a user denies `ad-storage`, the child services are automatically denied as well, regardless of their individual toggle state.

**How these differ from regular services:** Consent mode services use their `consent_mode_key` directly (e.g., `analytics_storage`) rather than the `{service_name}_consent` format used by regular services. This means the consent signals they produce are natively recognized by Google tags without any mapping.

### Integrating Klaro Geo into Google Tag Manager

Klaro Geo adds two dataLayer pushes: 'Klaro Event' and 'Klaro Consent Update'.

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

#### Klaro Consent Update

This event is pushed by the plugin after calling `gtag('consent', 'update', ...)`. This is the recommended trigger for your tags because consent is guaranteed to be set when this event fires.

Parameters:
- `event: "Klaro Consent Update"`: Hard-coded event name
- `consent_trigger: "initialConsents|saveConsents"`: The Klaro event that triggered this
- `consentMode: {...}`: Complete consent mode object (includes all standard + custom consent keys)
- `acceptedServices: [...]`: Array of accepted service names

**Event Flow**:
1. Plugin calls `gtag('consent', 'update', ...)` with consent data
2. Plugin pushes `Klaro Consent Update` to dataLayer
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

**GTM Usage**: All consent keys (standard and custom) are set via `gtag('consent', 'update')` and are also included in the `consentMode` object within the `Klaro Consent Update` dataLayer event. This means custom consent types like `facebook_consent` work natively with GTM's **"Require additional consent for tag to fire"** — no sandbox restrictions.

Alternatively, create a **Data Layer Variable** (e.g., `consentMode.facebook_consent`) and use it as a trigger condition on the `Klaro Consent Update` event. See [Non-Google Tags](#non-google-tags-facebook-pixel-linkedin-insight-etc) above for details.

```javascript
// Example consentMode object in the Klaro Consent Update event
{
  'ad_storage': 'denied',
  'analytics_storage': 'granted',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'google_analytics_consent': 'granted',
  'piwik_consent': 'granted',       // custom - use via DLV
  'facebook_consent': 'denied'      // custom - use via DLV
}
```

#### Using with Other Tag Managers

The dataLayer format follows Google Tag Manager conventions but can be read by other tag managers (Tealium, Adobe Launch, etc.). The `Klaro Consent Update` event is pushed directly by the plugin (no GTM template required), so it works with any tag manager or even without one:
- `consentMode`: Object with all consent keys (both Google standard and dynamic service keys)
- `acceptedServices`: Array of service names with granted consent
- `triggerEvent`: Either `'initialConsents'` or `'saveConsents'`

#### Consent Queue (klaroGeo.push)

The plugin provides a consent-aware queue that holds dataLayer events until consent state is confirmed. This solves race conditions where events fire before consent is set, and avoids the limitations of GTM Trigger Groups for repeatable events like `add_to_cart`.

Just push events to klaroGeo like you would normally do to the dataLayer and the plugin will hold them until consent settings are ready.

**Basic Usage:**
```javascript
// Safe to use anywhere, even before klaro-geo.js loads (stub pattern)
window.klaroGeo = window.klaroGeo || {};
window.klaroGeo.push = window.klaroGeo.push || function(e) {
    (window.klaroGeo.queue = window.klaroGeo.queue || []).push(e);
};

// Queue events - held until consent is confirmed
klaroGeo.push({
    'event': 'view_item_list',
    'ecommerce': {
        'item_list_id': 'services_overview',
        'items': [...]
    }
});
```

**How it works:**
1. Events pushed via `klaroGeo.push()` are queued until the consent event fires
2. When the consent event fires, the queue flushes all events to `dataLayer`
3. After consent is confirmed, new events go directly to `dataLayer`
4. GTM's consent mode determines whether tags actually fire based on consent state

**Configuration:**

The queue always waits for the `Klaro Consent Update` event before flushing, regardless of whether GTM is configured.

**Queue limits:**
- Maximum 100 events (oldest dropped when exceeded)
- Queue resets on each page load

**When to use:**
- Page load events that need to wait for consent state to be set (`view_item_list`, `page_view`)
- Any event that might fire before consent is confirmed

**Note:** The queue ensures events reach the dataLayer after consent state is set. GTM's consent mode then determines whether tags fire based on the actual consent granted. This is a timing mechanism, not a consent enforcement mechanism.

### How It Works

Klaro Geo uses Klaro's native consent management to control consent state. The flow differs based on the consent mode type.

#### Basic Mode Flow

1. Consent defaults are set via `gtag('consent', 'default')` in the `<head>`
2. GTM script is blocked by Klaro (`type="text/plain"`)
3. When the user consents to `google-tag-manager` (or has existing consent from a cookie), Klaro unblocks the script
4. GTM loads, picks up the consent defaults and updates from the dataLayer
5. Klaro Geo calls `gtag('consent', 'update')` and pushes `Klaro Consent Update`
6. Tags configured with consent requirements fire based on consent state
7. Subsequent consent changes repeat steps 5-6

#### Advanced Mode Flow

1. Consent defaults are set via `gtag('consent', 'default')` in the `<head>`
2. GTM script loads immediately (no Klaro gating)
3. GA4 can send cookieless pings while consent is denied (behavioral modeling)
4. Klaro Geo calls `gtag('consent', 'update')` and pushes `Klaro Consent Update` on page load
5. When the user interacts with the consent banner, consent is updated via `gtag('consent', 'update')` + `Klaro Consent Update`
6. Tags fire according to consent state at each step


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