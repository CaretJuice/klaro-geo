=== Klaro Geo ===
Contributors: caretjuice
Tags: consent, gdpr, ccpa, geolocation, privacy
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 0.3.8
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Geo-targeted consent management powered by Klaro. Show different consent banners by country and region with Google Tag Manager support.

== Description ==

Klaro Geo integrates the [Klaro](https://github.com/KIProtect/klaro) consent management library with WordPress and the [Geolocation IP Detection](https://wordpress.org/plugins/geoip-detect/) plugin to deliver location-aware consent banners.

Create multiple consent banner templates, assign each one to specific countries or regions, and track consent decisions for compliance. The plugin is designed to work with Google Tag Manager, where Klaro controls the loading of GTM.

**You are responsible for ensuring compliance with all applicable laws and regulations.**

= Features =

* **Geolocation-Based Consent**: Different consent banners based on visitor country and region
* **Template System**: Create and manage multiple consent banner templates
* **Google Tag Manager Integration**: Built-in GTM support with zero-config consent mode
* **Google Consent Mode v2**: Basic and advanced consent mode support
* **Custom Consent Keys**: Simplified GTM tag triggering via per-service consent keys (e.g., `facebook_consent`)
* **Fallback Templates**: Strict fallback templates prevent accidental violations of local law
* **Consent Receipts**: Store consent records in browser localStorage and optionally in the WordPress database, configurable per-template
* **Consent Buttons**: Floating button, WordPress menu integration, and shortcode support
* **Global Privacy Control (GPC)**: Detects the browser GPC signal and adjusts consent defaults. Configurable per-template and per-service
* **DataLayer Integration**: Full-duplex dataLayer integration — listens for events, forwards Klaro events, and dispatches enhanced consent status events
* **Consent Queue**: `klaroGeo.push()` holds dataLayer events until consent state is confirmed, solving race conditions
* **Admin Debug Tools**: Test different geolocation scenarios from the admin bar

= How It Works =

1. The plugin generates a Klaro configuration based on the visitor's detected location
2. The appropriate consent banner template is selected (geo-match, fallback, or admin override)
3. Klaro displays the consent banner and manages user choices
4. Consent state is communicated to Google Tag Manager via Consent Mode v2 and dataLayer events
5. Tags fire according to their consent requirements

= Requirements =

* [Geolocation IP Detection](https://wordpress.org/plugins/geoip-detect/) plugin (for geolocation features)
* Geolocation is optional — without it, the fallback template is always shown

== Installation ==

1. Install and configure the [Geolocation IP Detection](https://wordpress.org/plugins/geoip-detect/) plugin
2. Upload the `klaro-geo` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu
4. Configure settings under the Klaro Geo menu:
   a. Set your Google Tag Manager ID and purposes under **Klaro Geo > Klaro Geo**
   b. Configure services under **Klaro Geo > Services**
   c. Customize templates under **Klaro Geo > Templates**
   d. Assign templates to countries/regions under **Klaro Geo > Country Settings**

== Frequently Asked Questions ==

= Does this plugin guarantee GDPR/CCPA compliance? =

No. This plugin provides tools for consent management, but you are responsible for ensuring your implementation complies with applicable laws and regulations.

= Do I need the Geolocation IP Detection plugin? =

Geolocation is optional. Without it, the fallback template is shown to all visitors. Install Geolocation IP Detection if you want to show different consent banners based on visitor location.

= Which consent mode type should I use — Basic or Advanced? =

**Basic mode** blocks Google Tag Manager entirely until consent is given. No data is sent before consent. Use this for the strictest interpretation of privacy regulations.

**Advanced mode** loads GTM immediately but with all consent signals set to denied. Google tags can send cookieless pings for behavioral modeling before consent. Use this if your legal interpretation allows cookieless data collection.

= Why do my GTM tags never fire for visitors who decline Google Tag Manager? =

In Basic mode, the Google Tag Manager service gates GTM itself, so a visitor who has not consented to it never loads GTM and no GTM trigger or tag can run for them, including the `Klaro Consent Update` trigger. To have GTM run for every visitor while still gating individual tags by consent, enable **Required** on the templates where that fits (the Google Tag Manager service inherits it by default, so other templates can keep GTM consent-gated), or switch to Advanced mode. Both load GTM before consent, so confirm that fits your legal interpretation.

= How do I trigger non-Google tags based on consent? =

Add the service's custom consent key (e.g., `facebook_consent`) to the tag's "Require additional consent for tag to fire" setting in GTM. The plugin automatically generates consent keys in the format `{service_name}_consent`.

= Does Global Privacy Control (GPC) override user consent? =

No. GPC changes service defaults to denied but does not prevent users from explicitly accepting services via the consent modal.

= Can I use this plugin without Google Tag Manager? =

The dataLayer events follow GTM conventions but work with other tag managers (Tealium, Adobe Launch, etc.) or without any tag manager. The `Klaro Consent Update` event is pushed directly by the plugin.

== Bundled Libraries ==

The Klaro consent management library (version 0.7) that powers the consent banner is bundled with the plugin as `klaro.js` and `klaro.css`. It is served from your own site and runs entirely in the visitor's browser. The plugin does not load it from a CDN and makes no request to KIProtect.

`klaro.js` is the unmodified minified build published by KIProtect. Its source code is available at:

* Klaro GitHub repository: [https://github.com/KIProtect/klaro](https://github.com/KIProtect/klaro)
* Klaro license: BSD-3-Clause

== External Services ==

This plugin connects to the following external services:

= Google Tag Manager =

When a Google Tag Manager ID is configured, the plugin loads the GTM script from Google's servers:

* `https://www.googletagmanager.com/gtm.js`

This is standard GTM behavior. Data sent to Google depends on your GTM container configuration and the consent state managed by this plugin.

* Google Tag Manager: [https://tagmanager.google.com/](https://tagmanager.google.com/)
* Google privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

== Changelog ==

= 0.3.8 =

Read this before updating: several defaults and dataLayer details change.

Requirements

* Changed: the plugin now supports WordPress 5.7 and later (it required 6.6). PHP 7.2 remains the minimum. Both are tested on every change

Things to check after updating

* Changed: Klaro is bundled with the plugin and no longer loaded from a CDN. The Klaro JS Version and Script Variant settings are removed
* Changed: the plugin's scripts are deferred, and the frontend no longer loads jQuery
* Changed: the floating consent button is off unless it is enabled in the settings (it was on when the setting had never been saved). The shortcode's default button text is "Manage Consent"
* Changed: deleting the plugin removes all its settings. Consent receipts are removed only if the new "delete receipts on uninstall" setting is checked
* Removed: service callback fields and the `-full` zip. The readme has a `watch()` snippet to use instead
* Changed: `ad_user_data` and `ad_personalization` are denied whenever `ad_storage` is, in the consent defaults and in updates (they could be sent as granted before)
* Changed: under Global Privacy Control a consent mode service is denied under its consent mode key only; there is no extra `ad_storage_consent` style key
* Removed: the `Klaro Geo Consent Receipt` dataLayer event. Use `Klaro Event` with `klaroEventName` `generateConsentReceipt`
* Changed: `generateConsentReceipt` also fires for a Global Privacy Control opt-out made without touching the banner, with the new `klaroGeoConsentReceiptType` (`choice` or `gpc`). A tag that must fire only for saved choices adds the condition `klaroGeoConsentReceiptType` equals `choice`
* Changed: `klaroGeoEnableConsentLogging` in `klaroConfigLoaded` is false whenever consent receipts are disabled globally (it reported the template's own setting)
* Changed: `lastUpdate` in `/.well-known/gpc.json` is the date the setting was last switched, not today's date

Consent receipts

* Changed: receipts no longer need a nonce, so they are recorded on cached pages
* Added: receipts record the Global Privacy Control signal, and a GPC opt-out with no banner choice gets its own receipt
* Security: IPv6 addresses written in a shortened form were stored whole with consent receipts instead of being cut to their /48, and each one had its own receipt rate limit; every form is now anonymized, and the rate limit counts the network
* Security: a consent receipt's time is now the server's, and the Klaro config is no longer taken from the browser. A receipt naming a template that does not exist is refused
* Changed: the Klaro config a visitor was shown (the consent text and services) is stored once per version instead of with every receipt, and each receipt refers to its version by hash. The page carries the hash, so a receipt from a cached page refers to the config that page was rendered with. A receipt reporting a config this site never served is stored and marked as a mismatch on the Consent Receipts page. Receipts stored by earlier versions keep their own copy
* Added: the `klaro_geo_client_ip` filter, for sites behind a reverse proxy or CDN whose server does not restore the visitor's address. Without it every visitor shares the proxy's address and one receipt rate limit

Fixes and security

* Fixed: client-side geo resolution now works in a browser
* Fixed: a template stored with a broken config is repaired when templates load, and logged; the repaired value is stored the next time a template is saved
* Fixed: on the Country Settings page, a removed region override no longer comes back when Save Regions is pressed quickly, and the Save button validates template names
* Fixed: invalid translations JSON is rejected with a notice instead of being rewritten
* Changed: consent mode services can no longer be deleted from the Services page, only hidden
* Fixed: activating the plugin on a new site did not store the default templates; they were only handed out on each read
* Security: the admin bar's debug location is only applied with the nonce the debug menu adds; a bare `?klaro_geo_debug_geo=` in a link no longer changes the template an administrator is shown
* Security: the `style` attribute of the `[klaro_embedded]` shortcode goes through WordPress's CSS filter

= 0.3.7 =
* Fixed: the consent notice's "That's ok" button declined every service when a template's config was missing acceptAll (including templates wiped by the 0.3.5 settings bug); missing admin defaults are now filled in
* Fixed: the initial Klaro Consent Update was never pushed for first-time visitors, so the consent queue never flushed and single-trigger tags never fired until they chose
* Fixed: Google Tag Manager belonged to both the Analytics and Advertising purposes, so enabling one purpose made the other look enabled and a click on it switched it off; GTM now has its own Site Infrastructure purpose (existing installs are migrated)
* Fixed: a template's Required setting made Decline accept every service; it now applies per service to services set to "Use Global Setting", so Google Tag Manager can be required in some templates and consent-gated in others
* Changed: the default Google Tag Manager service inherits Required from each template

= 0.3.6 =
* Fixed: saving the main settings page wiped every template's config and plugin settings (nested arrays were flattened to empty strings by the sanitize callback)
* Fixed: the template Required checkbox defaulted to checked, which made Klaro grant every service when a visitor clicked Decline
* Fixed: the templates admin page aborted mid-render after saving, hiding the Save button
* Fixed: the consent key preview table on the templates page never rendered any rows
* Added: a warning naming any template whose stored config is not an array, instead of silently dropping its settings
* Changed: country settings sanitization no longer flattens boolean values

= 0.3.5 =
* Improved input sanitization and output escaping throughout
* Converted all inline scripts to use WordPress enqueue API
* Added ISO 3166 validation for debug country/region codes
* Removed service callback fields from WordPress.org build (available in GitHub full version)
* Renamed AJAX actions to use klaro_geo_ prefix
* Changed disablePoweredBy default to true (Guideline 10 compliance)
* Deleted backup file and fixed Docker test environment

= 0.3.4 =
* Added Global Privacy Control (GPC) detection and integration
* Configurable GPC sensitivity per-template and per-service
* Added `/.well-known/gpc.json` endpoint support
* GPC fields added to consent receipts and dataLayer events

= 0.3.3 =
* Removed user agent from consent receipts to reduce privacy footprint
* Removed redundant Klaro Consent Update events

= 0.3.2 =
* Clarified Google Tag Manager integration and consent mode settings in documentation

= 0.3.1 =
* Implemented advanced consent mode and streamlined GTM integration
* Added support for basic and advanced consent mode types

= 0.3.0 =
* Streamlined dataLayer events for production readiness
* Transformed consent mode into first-class Klaro services
* Added centralized event factory for dataLayer events

= 0.2.0 =
* Enhanced Google Consent Mode integration
* Added consent queue (`klaroGeo.push`) for race condition handling
* Added floating consent button and shortcode support
* Singleton caching for settings classes

= 0.1.0 =
* Initial release
* Geolocation-based consent management
* Template system with country and region assignment
* Google Tag Manager integration with Consent Mode v2
* Consent receipts with localStorage and database storage
* Admin debug tools

== Upgrade Notice ==

= 0.3.8 =
Klaro is now bundled and scripts are deferred; the floating button is off unless enabled; some dataLayer details and consent defaults change. Read the changelog, then check your banner and tags after updating. The consent receipts tables are upgraded automatically.

= 0.3.6 =
Fixes a bug where saving the settings page erased all template configuration. If your templates have lost their settings, re-save each template after updating.

= 0.3.5 =
Security and compliance improvements for WordPress plugin directory submission.

= 0.3.4 =
Adds Global Privacy Control (GPC) support. GPC detection is enabled by default — review your template settings if you want to customize which services are affected.
