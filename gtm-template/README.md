# Klaro Geo - Google Tag Manager Templates

## Consent Mode Tag (Removed)

The Consent Mode tag template (`template.tpl`) has been removed. Klaro Geo now handles Google Consent Mode entirely via client-side `gtag()` calls:

- **Consent defaults** are set via `gtag('consent', 'default', ...)` in the `<head>` before any tags load
- **Consent updates** are called via `gtag('consent', 'update', ...)` directly when users interact with the consent banner

No GTM template is needed for consent mode to work. See the [main README](../readme.md) for setup instructions.

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

### How It Works

The Consent Gate variable reads consent state set by the plugin's `gtag('consent', 'update')` calls:

1. Plugin sets consent defaults via `gtag('consent', 'default')` in the `<head>`
2. Plugin calls `gtag('consent', 'update')` when the user interacts with Klaro
3. Consent Gate Variable calls `isConsentGranted()` to check current consent state
4. If granted, passes through the original value; if denied, returns the redacted value

The variable is evaluated each time a tag references it, so it always reflects the current consent state.

---

## Support

For issues with these templates, please open an issue on the [Klaro Geo GitHub repository](https://github.com/CaretJuice/klaro-geo/issues).

## License

Apache 2.0 - See LICENSE file in the main repository.
