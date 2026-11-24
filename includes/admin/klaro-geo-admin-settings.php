<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Main settings page content
function klaro_geo_settings_page_content() {
	?>
	<div class="wrap">
		<h1>Klaro Geo Settings</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'klaro_geo_settings_group' );
			do_settings_sections( 'klaro-geo' );
			?>
			<table class="form-table">

				<tr valign="top">
					<th scope="row"><label for="klaro_geo_enable_consent_receipts">Enable Consent Receipts</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_enable_consent_receipts" id="klaro_geo_enable_consent_receipts" value="1" <?php checked( get_option( 'klaro_geo_enable_consent_receipts', false ) ); ?> />
						<p class="description">When enabled, consent choices will be pushed to the dataLayer for tracking and auditing purposes.</p>
					</td>
				</tr>
			</table>

			<h2>Geo Resolution</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_geo_resolution_mode">Geo Resolution Mode</label></th>
					<td>
						<select name="klaro_geo_geo_resolution_mode" id="klaro_geo_geo_resolution_mode">
							<option value="server" <?php selected( get_option( 'klaro_geo_geo_resolution_mode', 'server' ), 'server' ); ?>>Server-side (default)</option>
							<option value="client" <?php selected( get_option( 'klaro_geo_geo_resolution_mode', 'server' ), 'client' ); ?>>Client-side (cache-safe)</option>
						</select>
						<p class="description"><strong>Server-side:</strong> the consent template is chosen during page rendering. Incompatible with full-page caching (WP Engine, Kinsta, LiteSpeed, Cloudflare APO, …): cached pages serve one visitor's geo and template to everyone.<br>
						<strong>Client-side:</strong> the page ships all candidate templates and the visitor's browser resolves geo via the GeoIP Detection plugin's AJAX endpoint, then selects the template before Klaro initializes. Recommended on any host with full-page caching. Requires the "Enable AJAX endpoint" setting in the GeoIP Detection plugin.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_client_geo_timeout_ms">Client Geo Lookup Timeout (ms)</label></th>
					<td>
						<input type="number" name="klaro_geo_client_geo_timeout_ms" id="klaro_geo_client_geo_timeout_ms" min="500" max="30000" step="100" value="<?php echo esc_attr( get_option( 'klaro_geo_client_geo_timeout_ms', 2500 ) ); ?>" />
						<p class="description">Maximum time to wait for the client-side geo lookup before falling back (timezone inference, then the fallback template). Only used in client-side mode.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_client_geo_timezone_fallback">Timezone Fallback</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_client_geo_timezone_fallback" id="klaro_geo_client_geo_timezone_fallback" value="1" <?php checked( get_option( 'klaro_geo_client_geo_timezone_fallback', true ) ); ?> />
						<p class="description">When the geo lookup fails or times out, infer the country from the browser timezone (no network request; region stays empty). Only used in client-side mode.</p>
					</td>
				</tr>
			</table>

			<h2>Google Tag Manager</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_gtm_id">Google Tag Manager ID</label></th>
					<td>
						<input type="text" name="klaro_geo_gtm_id" id="klaro_geo_gtm_id" value="<?php echo esc_attr( get_option( 'klaro_geo_gtm_id', '' ) ); ?>" placeholder="GTM-XXXXXX" />
						<p class="description">Enter your Google Tag Manager container ID (e.g., GTM-XXXXXX). Leave empty to disable GTM integration.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_consent_mode_type">Consent Mode Type</label></th>
					<td>
						<select name="klaro_geo_consent_mode_type" id="klaro_geo_consent_mode_type">
							<option value="basic" <?php selected( get_option( 'klaro_geo_consent_mode_type', 'basic' ), 'basic' ); ?>>Basic</option>
							<option value="advanced" <?php selected( get_option( 'klaro_geo_consent_mode_type', 'basic' ), 'advanced' ); ?>>Advanced</option>
						</select>
						<p class="description"><strong>Basic:</strong> GTM is blocked by Klaro until the user consents. No data is sent before consent.<br>
						<strong>Advanced:</strong> GTM loads immediately with all consent defaults set to "denied". GA4 sends cookieless pings before consent, enabling behavioral modeling. When the user consents, consent state updates and full tracking begins.</p>
					</td>
				</tr>
			</table>

			<h2>Consent Management Buttons</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_enable_floating_button">Enable Floating Consent Button</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_enable_floating_button" id="klaro_geo_enable_floating_button" value="1" <?php checked( (bool) get_option( 'klaro_geo_enable_floating_button', false ) ); ?> />
						<p class="description">When enabled, a floating button lets visitors reopen their consent settings at any time. It is off by default: most sites will want to style it to match their theme first, with CSS overrides for <code>.klaro-floating-button</code>. The shortcode and menu item do not need this setting.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_floating_button_text">Button Text</label></th>
					<td>
						<input type="text" name="klaro_geo_floating_button_text" id="klaro_geo_floating_button_text" value="<?php echo esc_attr( get_option( 'klaro_geo_floating_button_text', 'Manage Consent' ) ); ?>" class="regular-text" />
						<p class="description">Text to display on the floating consent button.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_floating_button_theme">Button Theme</label></th>
					<td>
						<?php $button_theme = get_option( 'klaro_geo_floating_button_theme', 'light' ); ?>
						<select name="klaro_geo_floating_button_theme" id="klaro_geo_floating_button_theme">
							<option value="light" <?php selected( $button_theme, 'light' ); ?>>Light</option>
							<option value="dark" <?php selected( $button_theme, 'dark' ); ?>>Dark</option>
							<option value="blue" <?php selected( $button_theme, 'blue' ); ?>>Blue</option>
							<option value="green" <?php selected( $button_theme, 'green' ); ?>>Green</option>
						</select>
						<p class="description">Choose the button style theme.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_floating_button_position">Button Position</label></th>
					<td>
						<?php $button_position = get_option( 'klaro_geo_floating_button_position', 'bottom-right' ); ?>
						<select name="klaro_geo_floating_button_position" id="klaro_geo_floating_button_position">
							<option value="bottom-right" <?php selected( $button_position, 'bottom-right' ); ?>>Bottom Right</option>
							<option value="bottom-left" <?php selected( $button_position, 'bottom-left' ); ?>>Bottom Left</option>
							<option value="top-right" <?php selected( $button_position, 'top-right' ); ?>>Top Right</option>
							<option value="top-left" <?php selected( $button_position, 'top-left' ); ?>>Top Left</option>
						</select>
						<p class="description">Choose where the floating button should appear on the page.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">WordPress Menu Integration</th>
					<td>
						<p><strong>How to add a consent button to your WordPress menu:</strong></p>
						<ol>
							<li>Go to <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">Appearance > Menus</a> in your WordPress admin</li>
							<li>Create a new menu or edit an existing one</li>
							<li>In the "Custom Links" section, add a link with:
								<ul>
									<li><strong>URL:</strong> # (just a hash symbol)</li>
									<li><strong>Link Text:</strong> "Manage Cookies" or whatever text you prefer</li>
								</ul>
							</li>
							<li>Click "Add to Menu"</li>
							<li>Expand the newly added menu item</li>
							<li>In the "CSS Classes (optional)" field, add the class <code>open-klaro-modal</code></li>
							<li>Save the menu</li>
						</ol>
						<p class="description">When clicked, this menu item will open the Klaro consent management popup, allowing users to update their consent preferences at any time.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Shortcode</th>
					<td>
						<p><strong>Use the shortcode to add a consent button anywhere:</strong></p>
						<code>[klaro_consent_button text="Manage Cookie Settings" class="my-custom-class"]</code>
						<p class="description">This shortcode creates a button or link that opens the Klaro consent modal when clicked. Parameters:</p>
						<ul>
							<li><strong>text</strong> - (Optional) The button text. Defaults to the Button Text setting above.</li>
							<li><strong>class</strong> - (Optional) Additional CSS classes to add to the button.</li>
							<li><strong>style</strong> - (Optional) Set to "link" for a text link instead of a button.</li>
						</ul>
					</td>
				</tr>
			</table>
			<h2>Purposes</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_purposes">Purposes (comma-separated):</label></th>
					<td>
						<input type="text" name="klaro_geo_purposes" id="klaro_geo_purposes" value="<?php echo esc_attr( get_option( 'klaro_geo_purposes', klaro_geo_get_default_purposes_string() ) ); ?>" class="regular-text" />
						<p class="description">Enter the purposes for which you use cookies, separated by commas.  These will be available for selection when configuring services.</p>
					</td>
				</tr>
			</table>
			<h2>Global Privacy Control (GPC)</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_gpc_enabled">Enable GPC Detection</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_gpc_enabled" id="klaro_geo_gpc_enabled" value="1" <?php checked( get_option( 'klaro_geo_gpc_enabled', true ) ); ?> />
						<p class="description">When enabled, the plugin detects the <code>navigator.globalPrivacyControl</code> browser signal and adjusts consent defaults for GPC-sensitive services (e.g., advertising). Users can still explicitly accept via the Klaro modal. Can also be enabled/disabled per-template.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_gpc_well_known">Serve <code>/.well-known/gpc.json</code></label></th>
					<td>
						<input type="checkbox" name="klaro_geo_gpc_well_known" id="klaro_geo_gpc_well_known" value="1" <?php checked( get_option( 'klaro_geo_gpc_well_known', false ) ); ?> />
						<p class="description">Serve a <code>/.well-known/gpc.json</code> endpoint indicating that this site respects Global Privacy Control signals. Required by some GPC specifications.</p>
					</td>
				</tr>
			</table>

			<h2>Data Layer Settings</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_suppress_consents_events">Suppress Individual Klaro Consent Event dataLayer Pushes</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_suppress_consents_events" id="klaro_geo_suppress_consents_events" value="1" <?php checked( get_option( 'klaro_geo_suppress_consents_events', true ) ); ?> />
						<p class="description">Klaro sends a "consents" event every time an individual consent toggle is changed in the consent banner. These don't take effect until the user saves their choices, at which point the plugin sends a consolidated event. Enabling this setting (recommended) suppresses the individual toggle events, which can pollute the dataLayer when users toggle multiple settings. Disable this only if you need to track individual consent toggle interactions within the banner.</p>
					</td>
				</tr>
			</table>

			<h2>Debug Settings</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_enable_debug_logging">Enable Plugin Debug Logging</label></th>
					<td>
						<input type="checkbox" name="klaro_geo_enable_debug_logging" id="klaro_geo_enable_debug_logging" value="1" <?php checked( get_option( 'klaro_geo_enable_debug_logging', false ) ); ?> />
						<p class="description">When enabled, the plugin will output debug messages to WordPress logs (PHP) and browser console (JavaScript). This is useful for troubleshooting but should be disabled in production to reduce log noise. Note: WP_DEBUG must also be enabled for PHP logging to work.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="klaro_geo_debug_countries">Debug Countries/Regions (comma-separated):</label></th>
					<td>
						<?php
						$debug_countries_str = implode( ',', klaro_geo_get_debug_locations() );
						?>
						<input type="text" name="klaro_geo_debug_countries" id="klaro_geo_debug_countries" value="<?php echo esc_attr( $debug_countries_str ); ?>" class="regular-text" />
						<p class="description">Enter two-digit country codes or hyphen-separated ISO 3166-2 region codes for debugging, separated by commas (e.g., US,UK,CA,FR,AU,US-CA,CA-QC).</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Plugin Cleanup</th>
					<td>
						<label>
							<input type="checkbox"
									name="klaro_geo_cleanup_on_deactivate"
									value="1" <?php checked( get_option( 'klaro_geo_cleanup_on_deactivate', false ) ); ?> />
							Remove all plugin settings when deactivating
						</label>
						<p class="description">Warning: If enabled, all settings will be deleted when the plugin is deactivated.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Consent Receipts on Delete</th>
					<td>
						<label>
							<input type="checkbox"
									name="klaro_geo_delete_receipts_on_uninstall"
									value="1" <?php checked( get_option( 'klaro_geo_delete_receipts_on_uninstall', false ) ); ?> />
							Delete stored consent receipts when the plugin is deleted
						</label>
						<p class="description">Deleting the plugin always removes its settings. Consent receipts are your record of what visitors agreed to, so they are kept unless this is checked. This cannot be undone.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Register settings for the main settings page
function klaro_geo_register_main_settings() {
	// Templates are saved by the Templates page, not by this form. They are
	// registered only so the sanitize callback runs on every update_option(),
	// and in a group of their own: options.php saves every option in the
	// submitted group, and writes null for one that has no field in the form.
	// In klaro_geo_settings_group that wiped all templates whenever the main
	// settings page was saved.
	register_setting(
		'klaro_geo_templates_group',
		'klaro_geo_templates',
		array(
			'sanitize_callback' => function ( $input ) {
				if ( ! is_array( $input ) ) {
					return array();
				}
				// Each template holds nested 'config' and 'plugin_settings'
				// arrays. Mapping sanitize_text_field() over a template flattens
				// those to empty strings, silently wiping every setting the
				// template carries, so recurse instead.
				return klaro_geo_sanitize_array( $input );
			},
		)
	);

	// Purposes
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_purposes',
		array(
			'type'              => 'string',
			'default'           => klaro_geo_get_default_purposes_string(),
			'sanitize_callback' => function ( $input ) {
				if ( empty( $input ) ) {
					return klaro_geo_get_default_purposes_string();
				}
				// Sanitize the comma-separated list
				$purposes = array_map( 'trim', explode( ',', $input ) );
				$purposes = array_map( 'sanitize_text_field', $purposes );
				return implode( ',', $purposes );
			},
		)
	);

	// Geo resolution settings (cache-safe client mode)
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_geo_resolution_mode',
		array(
			'type'              => 'string',
			'default'           => 'server',
			'sanitize_callback' => function ( $input ) {
				return in_array( $input, array( 'server', 'client' ), true ) ? $input : 'server';
			},
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_client_geo_timeout_ms',
		array(
			'type'              => 'integer',
			'default'           => 2500,
			'sanitize_callback' => function ( $input ) {
				$value = absint( $input );
				if ( 0 === $value ) {
					return 2500;
				}
				return min( max( $value, 500 ), 30000 );
			},
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_client_geo_timezone_fallback',
		array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => function ( $input ) {
				return empty( $input ) ? 0 : 1;
			},
		)
	);

	// GTM settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_gtm_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => function ( $input ) {
				// Validate GTM ID format (GTM-XXXXXX)
				if ( empty( $input ) || preg_match( '/^GTM-[A-Z0-9]+$/', $input ) ) {
					return $input;
				}
				return '';
			},
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_consent_mode_type',
		array(
			'type'              => 'string',
			'default'           => 'basic',
			'sanitize_callback' => function ( $input ) {
				return in_array( $input, array( 'basic', 'advanced' ), true ) ? $input : 'basic';
			},
		)
	);

	// Other settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_debug_countries',
		array(
			'type'              => 'array',
			// An empty or unusable list falls back to the defaults when read
			// (klaro_geo_get_debug_locations()).
			'sanitize_callback' => 'klaro_geo_normalize_location_codes',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_enable_consent_receipts',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_cleanup_on_deactivate',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_delete_receipts_on_uninstall',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_enable_debug_logging',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	// GPC settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_gpc_enabled',
		array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_gpc_well_known',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	// DataLayer settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_suppress_consents_events',
		array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	// Button settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_enable_floating_button',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	// Floating button settings
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_floating_button_text',
		array(
			'type'              => 'string',
			'default'           => 'Manage Consent',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_floating_button_theme',
		array(
			'type'              => 'string',
			'default'           => 'light',
			'sanitize_callback' => function ( $input ) {
				// Validate button theme
				$valid_themes = array( 'light', 'dark', 'blue', 'green' );
				return in_array( $input, $valid_themes, true ) ? $input : 'light';
			},
		)
	);
	register_setting(
		'klaro_geo_settings_group',
		'klaro_geo_floating_button_position',
		array(
			'type'              => 'string',
			'default'           => 'bottom-right',
			'sanitize_callback' => function ( $input ) {
				// Validate button position
				$valid_positions = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
				return in_array( $input, $valid_positions, true ) ? $input : 'bottom-right';
			},
		)
	);
}
add_action( 'admin_init', 'klaro_geo_register_main_settings' );
