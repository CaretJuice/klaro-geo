# Klaro Geo - Google Tag Manager Templates

This directory contains two GTM custom templates that integrate [Klaro Geo](https://github.com/CaretJuice/klaro-geo) consent management with Google Consent Mode v2:

- **Consent Mode Tag** (`template.tpl`) — Sets and updates Google Consent Mode state based on Klaro user choices
- **Consent Gate Variable** (`variable-template.tpl`) — Gates variable values behind consent checks, redacting user data when consent is denied

---

## Consent Mode Tag (`template.tpl`)

### Why Use This Template?

When using `gtag('consent', 'update', ...)` commands directly, there's a timing issue: gtag commands are queued and may not be processed before the next dataLayer event fires. This means tags that require consent may not fire even when consent has been granted.

This template uses GTM's native `setDefaultConsentState()` and `updateConsentState()` APIs, which update consent state **immediately** rather than through a queue. This ensures tags fire correctly when consent is granted.

### Installation

#### Option 1: Import from File

1. Download `template.tpl` from this directory
2. In Google Tag Manager, go to **Templates** > **Tag Templates**
3. Click **New** to create a new template
4. Click the **three-dot menu** in the top right corner
5. Select **Import**
6. Choose the downloaded `template.tpl` file
7. Click **Save**

#### Option 2: Manual Creation

Copy the contents of `template.tpl` and paste into a new custom template in GTM.

### Setup

You need to create **two tags** using this template:

#### Tag 1: Consent Defaults (Required)

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

#### Tag 2: Consent Update (Required)

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

### Configuring Your Tags

#### Using Standard Consent Signals

Configure your Google tags (GA4, Google Ads, etc.) to require consent:

1. Open your GA4 Configuration tag
2. Go to **Advanced Settings** > **Consent Settings**
3. Check **Require additional consent for tag to fire**
4. Add:
   - `analytics_storage` for GA4 tags
   - `ad_storage` for Google Ads tags

#### Using Custom Consent Types

If you enabled custom consent types, you can also use service-specific consent signals:

- `google_analytics_consent` - granted when `google-analytics` service is accepted
- `google_ads_consent` - granted when `google-ads` service is accepted
- `{service_name}_consent` - for any other Klaro service

### First-Page Attribution

When a user lands on your site without consent and then grants consent, tags triggered by `Klaro Consent Update` will fire with the current page's attribution data (referrer, campaign parameters, etc.).

To capture first-page attribution:

1. Create a **Custom Event** trigger for `Klaro Consent Update`
2. Configure your GA4 Configuration tag to fire on this trigger
3. Set consent requirements (`analytics_storage`) on the tag

The `Klaro Consent Update` event includes a `consent_trigger` field that indicates whether this is from `initialConsents` (page load with existing consent) or `saveConsents` (user just granted consent).

### How It Works

#### Consent Initialization Flow

1. GTM's "Consent Initialization" trigger fires (before all other triggers)
2. Template sets default consent state to "denied"
3. Template reads Klaro cookie to check for existing consent
4. If consent exists in cookie, immediately updates consent state to "granted"
5. Google tags now know the consent state before they fire

#### Consent Update Flow

1. User interacts with Klaro consent banner
2. Klaro Geo plugin pushes `Klaro Consent Data` event to dataLayer (contains raw consent data)
3. GTM Update tag trigger fires on this event
4. Template calls `updateConsentState()` with consent values
5. Template pushes `Klaro Consent Update` event (consent is now guaranteed to be set)
6. Google tags triggered by `Klaro Consent Update` fire with correct consent state

### Service Name Mapping

The template maps Klaro service names to Google consent types:

| Klaro Service | Standard Consent Types | Custom Consent Type |
|---------------|----------------------|---------------------|
| `google-analytics` | `analytics_storage` | `google_analytics_consent` |
| `google-ads` | `ad_storage`, `ad_user_data`, `ad_personalization` | `google_ads_consent` |
| Any service | - | `{service_name}_consent` |

### Troubleshooting

#### Tags not firing after consent granted

1. Verify the Update tag trigger is set to `Klaro Consent Data` (not `Klaro Consent Update`)
2. Verify your GA4 tags trigger on `Klaro Consent Update` (fired by the template AFTER consent is set)
3. Check GTM Preview mode to see if both the Update tag and `Klaro Consent Update` event fire
4. Enable debug mode in the template to see console logs
5. Verify the consent state in GTM's Consent tab

#### Wrong consent state on page load

1. Ensure the Default tag fires on "Consent Initialization - All Pages"
2. Check that the Klaro cookie name matches your configuration
3. Verify the cookie is accessible (same domain, not HTTP-only)

#### Understanding the event flow

1. Plugin pushes `Klaro Consent Data` (raw consent data from Klaro)
2. GTM Update tag fires, calls `updateConsentState()`, then pushes `Klaro Consent Update`
3. Your GA4/Google Ads tags should trigger on `Klaro Consent Update` (consent is guaranteed to be set)

### Advanced Settings

#### Ads Data Redaction

When enabled, sets `ads_data_redaction: true` when `ad_storage` is denied. This prevents certain data from being sent to Google when ads consent is not granted.

#### URL Passthrough

When enabled, allows ad click information (gclid, dclid, etc.) to be passed through URLs even when ad_storage is denied. Useful for maintaining conversion tracking without full cookie consent.

---

## Consent Gate Variable (`variable-template.tpl`)

### What It Does

The Consent Gate variable acts as a consent-aware wrapper around any GTM variable. It checks whether a specific consent type (e.g., `ad_user_data`) is granted:

- **Consent granted**: Returns the original variable value (pass-through)
- **Consent denied**: Returns a redacted value (`undefined`, empty string, `0`, `null`, or a custom value)

This keeps consent logic out of your tags. Instead of building conditional logic into each tag, reference a consent-gated variable and the redaction happens automatically.

### When to Use It

Use this variable template when you need to:

- **Redact user-provided data** (email, phone, address) when `ad_user_data` consent is denied
- **Zero out transaction values** when `ad_personalization` or `ad_storage` consent is denied
- **Suppress tracking IDs** when `analytics_storage` consent is denied

### Installation

1. Download `variable-template.tpl` from this directory
2. In Google Tag Manager, go to **Templates** > **Variable Templates**
3. Click **New** to create a new template
4. Click the **three-dot menu** in the top right corner
5. Select **Import**
6. Choose the downloaded `variable-template.tpl` file
7. Click **Save**

### Configuration

1. Create a new variable using the "Klaro Geo Consent Gate" template
2. Configure:
   - **Input Value**: Select a GTM variable using the variable picker (e.g., `{{User Email}}`)
   - **Required Consent Type**: Choose which consent must be granted (default: `ad_user_data`)
   - **Value When Denied**: Choose what to return when consent is denied (default: `undefined`)
3. Save the variable
4. Use this variable in your tags instead of the original variable

#### Example: Gating User Email

Suppose you have a `{{User Email}}` variable that captures `user@example.com` from your site. Create a consent-gated version:

| Setting | Value |
|---------|-------|
| Input Value | `{{User Email}}` |
| Required Consent Type | `ad_user_data` |
| Value When Denied | `undefined` |

Name it `{{Consented User Email}}` and use it in your Google Ads Enhanced Conversions tag. When `ad_user_data` is denied, the email parameter simply won't be sent.

### Redacted Value Options

| Option | Return Type | Use Case |
|--------|-------------|----------|
| `undefined` | `undefined` | **Default.** Parameter is not sent at all. Best for optional fields like Enhanced Conversions user data. |
| `""` (empty string) | `string` | String fields that must be present but blank. Some APIs require the key to exist. |
| `0` (zero) | `number` | Numeric fields like transaction value or item price. Reports zero revenue instead of omitting the field. |
| `null` | `null` | Explicitly signals "no value." Useful when downstream systems distinguish between missing and null. |
| Custom... | `string` | Any arbitrary string (e.g., `"REDACTED"`, `"N/A"`). Always returns string type. |

### How It Works with the Consent Mode Tag

The Consent Gate variable reads consent state that was set by the Consent Mode tag:

1. **Consent Mode Tag** (Default command) sets initial consent to "denied"
2. **Consent Mode Tag** (Update command) calls `updateConsentState()` when user interacts with Klaro
3. **Consent Gate Variable** calls `isConsentGranted()` to check current consent state
4. If granted, passes through the original value; if denied, returns the redacted value

The variable is evaluated each time a tag references it, so it always reflects the current consent state.

---

## Support

For issues with these templates, please open an issue on the [Klaro Geo GitHub repository](https://github.com/CaretJuice/klaro-geo/issues).

## License

Apache 2.0 - See LICENSE file in the main repository.
