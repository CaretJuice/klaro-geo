# Klaro Geo Consent Mode - Google Tag Manager Template

This Google Tag Manager template integrates [Klaro Geo](https://github.com/CaretJuice/klaro-geo) consent management with Google Consent Mode v2.

## Why Use This Template?

When using `gtag('consent', 'update', ...)` commands directly, there's a timing issue: gtag commands are queued and may not be processed before the next dataLayer event fires. This means tags that require consent may not fire even when consent has been granted.

This template uses GTM's native `setDefaultConsentState()` and `updateConsentState()` APIs, which update consent state **immediately** rather than through a queue. This ensures tags fire correctly when consent is granted.

## Installation

### Option 1: Import from File

1. Download `template.tpl` from this directory
2. In Google Tag Manager, go to **Templates** > **Tag Templates**
3. Click **New** to create a new template
4. Click the **three-dot menu** in the top right corner
5. Select **Import**
6. Choose the downloaded `template.tpl` file
7. Click **Save**

### Option 2: Manual Creation

Copy the contents of `template.tpl` and paste into a new custom template in GTM.

## Setup Instructions

You need to create **two tags** using this template:

### Tag 1: Consent Defaults (Required)

This tag sets the default consent state before any other tags fire.

1. Create a new tag using the "Klaro Geo Consent Mode" template
2. Configure:
   - **Command**: `Default (Consent Initialization)`
   - **Klaro Cookie Name**: `klaro` (or your custom cookie name)
   - **Analytics Storage Service**: `google-analytics` (your Klaro service name)
   - **Ad Storage Service**: `google-ads` (your Klaro service name)
   - **Default Consent State**: Set all to `denied` for strict consent mode
   - **Wait for Update**: `500` ms (recommended)
   - **Enable custom service-specific consent types**: Checked
3. **Trigger**: `Consent Initialization - All Pages`
4. Save the tag

### Tag 2: Consent Update (Required)

This tag updates consent state when users interact with the Klaro consent banner.

1. Create a new tag using the "Klaro Geo Consent Mode" template
2. Configure:
   - **Command**: `Update (Klaro Consent Data event)`
   - **Service Mappings**: Same as Tag 1
   - **Enable custom service-specific consent types**: Checked
3. **Trigger**: Create a Custom Event trigger:
   - **Trigger Type**: Custom Event
   - **Event Name**: `Klaro Consent Data`
4. Save the tag

**Note**: The plugin pushes `Klaro Consent Data` with raw consent information. The GTM template processes this, calls `updateConsentState()`, and then pushes `Klaro Consent Update`. Use `Klaro Consent Update` as the trigger for your GA4 and other tags to ensure consent is set before they fire.

## Configuring Your Tags

### Using Standard Consent Signals

Configure your Google tags (GA4, Google Ads, etc.) to require consent:

1. Open your GA4 Configuration tag
2. Go to **Advanced Settings** > **Consent Settings**
3. Check **Require additional consent for tag to fire**
4. Add:
   - `analytics_storage` for GA4 tags
   - `ad_storage` for Google Ads tags

### Using Custom Consent Types

If you enabled custom consent types, you can also use service-specific consent signals:

- `google_analytics_consent` - granted when `google-analytics` service is accepted
- `google_ads_consent` - granted when `google-ads` service is accepted
- `{service_name}_consent` - for any other Klaro service

## First-Page Attribution

When a user lands on your site without consent and then grants consent, tags triggered by `Klaro Consent Update` will fire with the current page's attribution data (referrer, campaign parameters, etc.).

To capture first-page attribution:

1. Create a **Custom Event** trigger for `Klaro Consent Update`
2. Configure your GA4 Configuration tag to fire on this trigger
3. Set consent requirements (`analytics_storage`) on the tag

The `Klaro Consent Update` event includes a `consent_trigger` field that indicates whether this is from `initialConsents` (page load with existing consent) or `saveConsents` (user just granted consent).

## How It Works

### Consent Initialization Flow

1. GTM's "Consent Initialization" trigger fires (before all other triggers)
2. Template sets default consent state to "denied"
3. Template reads Klaro cookie to check for existing consent
4. If consent exists in cookie, immediately updates consent state to "granted"
5. Google tags now know the consent state before they fire

### Consent Update Flow

1. User interacts with Klaro consent banner
2. Klaro Geo plugin pushes `Klaro Consent Data` event to dataLayer (contains raw consent data)
3. GTM Update tag trigger fires on this event
4. Template calls `updateConsentState()` with consent values
5. Template pushes `Klaro Consent Update` event (consent is now guaranteed to be set)
6. Google tags triggered by `Klaro Consent Update` fire with correct consent state

## Service Name Mapping

The template maps Klaro service names to Google consent types:

| Klaro Service | Standard Consent Types | Custom Consent Type |
|---------------|----------------------|---------------------|
| `google-analytics` | `analytics_storage` | `google_analytics_consent` |
| `google-ads` | `ad_storage`, `ad_user_data`, `ad_personalization` | `google_ads_consent` |
| Any service | - | `{service_name}_consent` |

## Troubleshooting

### Tags not firing after consent granted

1. Verify the Update tag trigger is set to `Klaro Consent Data` (not `Klaro Consent Update`)
2. Verify your GA4 tags trigger on `Klaro Consent Update` (fired by the template AFTER consent is set)
3. Check GTM Preview mode to see if both the Update tag and `Klaro Consent Update` event fire
4. Enable debug mode in the template to see console logs
5. Verify the consent state in GTM's Consent tab

### Wrong consent state on page load

1. Ensure the Default tag fires on "Consent Initialization - All Pages"
2. Check that the Klaro cookie name matches your configuration
3. Verify the cookie is accessible (same domain, not HTTP-only)

### Understanding the event flow

1. Plugin pushes `Klaro Consent Data` (raw consent data from Klaro)
2. GTM Update tag fires, calls `updateConsentState()`, then pushes `Klaro Consent Update`
3. Your GA4/Google Ads tags should trigger on `Klaro Consent Update` (consent is guaranteed to be set)

## Advanced Settings

### Ads Data Redaction

When enabled, sets `ads_data_redaction: true` when `ad_storage` is denied. This prevents certain data from being sent to Google when ads consent is not granted.

### URL Passthrough

When enabled, allows ad click information (gclid, dclid, etc.) to be passed through URLs even when ad_storage is denied. Useful for maintaining conversion tracking without full cookie consent.

## Support

For issues with this template, please open an issue on the [Klaro Geo GitHub repository](https://github.com/CaretJuice/klaro-geo/issues).

## License

Apache 2.0 - See LICENSE file in the main repository.
