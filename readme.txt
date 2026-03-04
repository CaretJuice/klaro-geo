=== Klaro Geo ===
Contributors: caretjuice
Tags: consent, gdpr, ccpa, geolocation, privacy
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 0.3.4
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

= How do I trigger non-Google tags based on consent? =

Add the service's custom consent key (e.g., `facebook_consent`) to the tag's "Require additional consent for tag to fire" setting in GTM. The plugin automatically generates consent keys in the format `{service_name}_consent`.

= Does Global Privacy Control (GPC) override user consent? =

No. GPC changes service defaults to denied but does not prevent users from explicitly accepting services via the consent modal.

= Can I use this plugin without Google Tag Manager? =

The dataLayer events follow GTM conventions but work with other tag managers (Tealium, Adobe Launch, etc.) or without any tag manager. The `Klaro Consent Update` event is pushed directly by the plugin.

== External Services ==

This plugin connects to the following external services:

= Klaro (KIProtect) =

This plugin loads the Klaro consent management JavaScript library and its CSS stylesheet from KIProtect's CDN:

* `https://cdn.kiprotect.com/klaro/v0.7/klaro.js` (or `klaro-no-css.js`)
* `https://cdn.kiprotect.com/klaro/v0.7/klaro.css`

Klaro is the open-source consent management tool that powers the consent banner UI. The script runs entirely in the visitor's browser. No personal data is transmitted to KIProtect — the CDN only serves the static JavaScript and CSS files.

* Klaro GitHub repository: [https://github.com/KIProtect/klaro](https://github.com/KIProtect/klaro)
* Klaro license: BSD-3-Clause
* KIProtect privacy policy: [https://kiprotect.com/privacy](https://kiprotect.com/privacy)

The Klaro JS version is configurable in the plugin settings under **Klaro Geo > Klaro Geo > Klaro JS Version**.

= Google Tag Manager =

When a Google Tag Manager ID is configured, the plugin loads the GTM script from Google's servers:

* `https://www.googletagmanager.com/gtm.js`

This is standard GTM behavior. Data sent to Google depends on your GTM container configuration and the consent state managed by this plugin.

* Google Tag Manager: [https://tagmanager.google.com/](https://tagmanager.google.com/)
* Google privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

== Screenshots ==

1. Template configuration with consent mode settings
2. Country and region assignment
3. Service management with custom consent keys
4. Consent receipt browser

== Changelog ==

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

= 0.3.4 =
Adds Global Privacy Control (GPC) support. GPC detection is enabled by default — review your template settings if you want to customize which services are affected.
