<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Services Page Content

function klaro_geo_services_page_content() {
	$purposes = explode( ',', get_option( 'klaro_geo_purposes', klaro_geo_get_default_purposes_string() ) ); // Get available purposes from settings

	// Initialize the service settings class
	$service_settings = Klaro_Geo_Service_Settings::get_instance();
	$services         = $service_settings->get();
	klaro_geo_debug_log( 'Services page content - ' . count( $services ) . ' services' );

	// Scripts are now enqueued in klaro-geo-admin-scripts.php

	// Templates are made available via wp_localize_script in klaro-geo-admin-scripts.php
	?>
	<div class="wrap">
		<h1>Klaro Services</h1>
		<?php // 3. Add Service Button ?>
		<button id="add-new-service" class="button button-primary">Add New Service</button>
		<?php // 4. Table for Existing Services ?>
		<table class="wp-list-table widefat fixed striped" id="klaro-services-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Required</th>
					<th>Default</th>
					<th>Purposes</th>
					<th>Advanced</th>
					<th>Cookies</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody id="klaro-services-list">
				<?php
				if ( empty( $services ) ) {

					echo "<tr><td colspan='8'>No services configured yet.</td></tr>"; // Updated colspan for the new advanced column
				} else {
					foreach ( $services as $index => $service ) {
						klaro_geo_debug_log( 'Processing service in admin loop: ' . ( $service['name'] ?? 'unnamed' ) );

						$name     = isset( $service['name'] ) ? $service['name'] : 'N/A';
						$required = isset( $service['required'] ) ? ( $service['required'] ? 'Yes' : 'No' ) : 'N/A';
						$default  = isset( $service['default'] ) ? ( $service['default'] ? 'Yes' : 'No' ) : 'N/A';

						// Handle purposes (ensure it's an array)
						$purposes     = isset( $service['purposes'] ) ? $service['purposes'] : array();
						$purposes_str = ! empty( $purposes ) && is_array( $purposes ) ? implode( ', ', $purposes ) : 'N/A';

						// Handle advanced settings
						$advanced = array();
						if ( isset( $service['optOut'] ) && $service['optOut'] ) {
							$advanced[] = 'Opt-Out';
						}
						if ( isset( $service['onlyOnce'] ) && $service['onlyOnce'] ) {
							$advanced[] = 'Only Once';
						}
						if ( isset( $service['contextualConsentOnly'] ) && $service['contextualConsentOnly'] ) {
							$advanced[] = 'Contextual Only';
						}
						$advanced_str = ! empty( $advanced ) ? implode( ', ', $advanced ) : 'None';

						// Handle cookies
						$cookies     = isset( $service['cookies'] ) ? $service['cookies'] : array();
						$cookies_str = ! empty( $cookies ) && is_array( $cookies ) ?
						json_encode( $cookies ) : 'N/A';

						echo '<tr>';
						echo '<td>' . esc_html( $name ) . '</td>';
						echo '<td>' . esc_html( $required ) . '</td>';
						echo '<td>' . esc_html( $default ) . '</td>';
						echo '<td>' . esc_html( $purposes_str ) . '</td>';
						echo '<td>' . esc_html( $advanced_str ) . '</td>';
						echo '<td>' . esc_html( $cookies_str ) . '</td>';
						echo '<td>';
						echo "<button class='edit-service button button-secondary' data-index='" . esc_attr( $index ) . "'>Edit</button> ";
						echo "<button class='delete-service button button-danger' data-index='" . esc_attr( $index ) . "'>Delete</button>";
						echo '</td>';
						echo '</tr>';
					}
				}
				?>



			</tbody>
		</table>



		<?php // 5. Form for Add/Edit Service (Hidden initially) ?>
		<div id="service-form-container" style="display: none;">
			<h2>Add/Edit Service</h2>
			<form id="service-form"> <input type="hidden" name="action" value="klaro_geo_save_services">
				<input type="hidden" name="service_index" id="service_index">
				<label for="service_name">Name:</label><br>
				<input type="text" id="service_name" name="service_name" required><br>
				<label for="service_required">Required:</label><br>
				<select id="service_required" name="service_required">
					<option value="global" selected>Use Global Setting</option>
					<option value="true">Yes</option>
					<option value="false">No</option>
				</select>
				<p class="description">When set to "Use Global Setting", this service will inherit the required setting from each template, so it can be required in some templates and declinable in others (useful for Google Tag Manager). When set to "Yes", users cannot decline this service. Only use for essential services that are required for your website to function.</p><br>

				<label for="service_default">Default:</label><br>
				<select id="service_default" name="service_default">
					<option value="global" selected>Use Global Setting</option>
					<option value="true">Yes</option>
					<option value="false">No</option>
				</select>
				<p class="description">When set to "Use Global Setting", this service will inherit the default setting from the template. When set to "Yes", this service will be activated by default (opt-out). Use with caution as this may not be compliant with privacy regulations in some regions.</p><br>
				<label for="service_purposes">Purposes:</label><br>
				<div id="service_purposes_container"></div>
				<label for="service_cookies">Cookies:</label><br>
				<div id="service_cookies_container0"></div>

				<h3>Advanced Settings</h3>
				<div class="advanced-settings">
					<label for="service_gpc_sensitive">GPC Sensitivity:</label><br>
					<select id="service_gpc_sensitive" name="service_gpc_sensitive">
						<option value="auto" selected>Auto-detect (from purposes)</option>
						<option value="true">Yes (always GPC-sensitive)</option>
						<option value="false">No (never GPC-sensitive)</option>
					</select>
					<p class="description">Controls whether Global Privacy Control (GPC) affects this service. "Auto-detect" uses the template's GPC-sensitive purposes list (default: advertising).</p>
					<br>

					<label for="service_optout">
						<input type="checkbox" id="service_optout" name="service_optout" value="1">
						Opt-Out
					</label>
					<p class="description">If enabled, this service will be loaded even before the user gives explicit consent. <strong>We strongly advise against this</strong> as it may violate privacy regulations.</p>

					<label for="service_onlyonce">
						<input type="checkbox" id="service_onlyonce" name="service_onlyonce" value="1">
						Only Once
					</label>
					<p class="description">If enabled, the service will only be executed once regardless of how often the user toggles it on and off. This is useful for tracking scripts that would generate new page view events every time they are re-enabled.</p>

					<label for="service_contextual">
						<input type="checkbox" id="service_contextual" name="service_contextual" value="1">
						Contextual Consent Only
					</label>
					<p class="description">If enabled, this service will only be shown in the consent modal when it's actually used on the page (e.g., embedded YouTube videos).</p>
				</div>

				<h3>Service Translations</h3>
				<div class="translations">
					<p class="description">Configure translations for this service. The "zz" language code is used as a fallback for any missing translations.</p>
					<p class="description"><strong>Note:</strong> Languages are automatically pulled from the Templates section. To add new languages, please add them in the Templates page first.</p>

					<div id="service-translations-tabs" class="translations-container">
						<ul class="translations-tabs-nav">
							<li><a href="#service-tab-zz">Fallback</a></li>
							<!-- Additional language tabs will be dynamically added based on templates -->
						</ul>

						<div id="service-tab-zz" class="translation-tab">
							<h4>Fallback Translations (zz)</h4>
							<p class="description">These translations will be used when a specific language translation is not available.</p>

							<table class="form-table">
								<tr>
									<th scope="row">Title</th>
									<td>
										<input type="text" id="service_translations_zz_title" name="service_translations[zz][title]" class="regular-text">
										<p class="description">The name of the service as displayed to users.</p>
									</td>
								</tr>

								<tr>
									<th scope="row">Description</th>
									<td>
										<textarea id="service_translations_zz_description" name="service_translations[zz][description]" rows="4" cols="50" class="large-text"></textarea>
										<p class="description">A description of what this service does and what data it collects.</p>
									</td>
								</tr>

								<tr>
									<th scope="row" colspan="2">
										<p class="description">
											<strong>Note:</strong> The translations for "Opt-Out Message", "Required Message", and "Purposes" are now configured globally in the Templates section.
											This ensures consistent messaging across all services.
										</p>
									</th>
								</tr>
							</table>
						</div>

						<!-- Additional language tabs will be dynamically added here -->
					</div>
				</div>

				<br> <button type="submit" class="button button-primary">Save Service</button>
				<button type="button" class="button button-primary add-cookie-group" data-index="0">Add Cookie Group</button> </form>
		</div>
	</div>
	<?php
}



/**
 * Sanitize the services array posted by the services admin page.
 *
 * The page posts every service back, including fields it does not edit
 * (consent mode metadata, hidden flags), so unknown fields are kept but never
 * stored as submitted: strings are stripped of markup and nested arrays are
 * sanitized recursively.
 *
 * @param array $services Decoded services from the request.
 * @return array Sanitized services. Entries that are not arrays are dropped.
 */
function klaro_geo_sanitize_services( array $services ) {
	$bool_fields = array( 'required', 'default', 'optOut', 'onlyOnce', 'contextualConsentOnly', 'is_consent_mode_service', 'hidden', 'gpc_sensitive' );
	// Service callbacks held admin-entered JavaScript and were removed. Services
	// saved by an earlier version may still carry them; they are dropped here.
	$removed_fields = array( 'onInit', 'onAccept', 'onDecline', 'callback' );

	$sanitized = array();

	foreach ( $services as $service ) {
		if ( ! is_array( $service ) ) {
			continue;
		}

		$clean = array();

		foreach ( $service as $field => $value ) {
			$field = sanitize_text_field( (string) $field );

			if ( in_array( $field, $removed_fields, true ) ) {
				continue;
			}

			if ( in_array( $field, $bool_fields, true ) ) {
				// null means "Use Global Setting" for required / default.
				$clean[ $field ] = ( null === $value ) ? null : filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( 'translations' === $field ) {
				$clean[ $field ] = is_array( $value ) ? klaro_geo_sanitize_translations( $value ) : array();
			} elseif ( 'purposes' === $field ) {
				$clean[ $field ] = is_array( $value ) ? array_values( array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) ) ) : array();
			} elseif ( 'cookies' === $field ) {
				$clean[ $field ] = is_array( $value ) ? klaro_geo_sanitize_deep( $value, false ) : array();
			} elseif ( is_array( $value ) ) {
				$clean[ $field ] = klaro_geo_sanitize_array( $value );
			} elseif ( is_string( $value ) ) {
				$clean[ $field ] = sanitize_text_field( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$clean[ $field ] = $value;
			}
		}

		// Service names are keys: lowercase, hyphen-separated.
		if ( isset( $clean['name'] ) ) {
			$clean['name'] = str_replace( array( ' ', '_' ), '-', strtolower( $clean['name'] ) );
		}

		$sanitized[] = $clean;
	}

	return $sanitized;
}

add_action( 'wp_ajax_klaro_geo_save_services', 'klaro_geo_save_services' );

function klaro_geo_save_services() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce', '_wpnonce' );

	if ( ! isset( $_POST['services'] ) ) {
		wp_send_json_error( 'No services data provided' );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; every field is sanitized in klaro_geo_sanitize_services().
	$services = json_decode( wp_unslash( $_POST['services'] ), true );

	if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $services ) ) {
		wp_send_json_error( 'Invalid services JSON or not an array' );
	}

	$services = klaro_geo_sanitize_services( $services );

	$service_settings = Klaro_Geo_Service_Settings::get_instance();
	$service_settings->set( $services );
	$service_settings->save();

	klaro_geo_debug_log( 'Saved ' . count( $services ) . ' services' );

	wp_send_json_success();
}


add_action( 'wp_ajax_klaro_geo_delete_service', 'klaro_geo_delete_service' );

/**
 * Delete the service at a position in the stored list.
 *
 * Goes through Klaro_Geo_Service_Settings::remove_service(), which refuses to
 * delete consent mode services: consent mode depends on them, and they can
 * only be hidden.
 *
 * @param int $index Position of the service in the stored list.
 * @return true|WP_Error
 */
function klaro_geo_delete_service_at( $index ) {
	$service_settings = Klaro_Geo_Service_Settings::get_instance();
	$services         = $service_settings->get();

	if ( ! isset( $services[ $index ]['name'] ) ) {
		return new WP_Error( 'klaro_geo_service_not_found', 'Service not found.' );
	}

	$result = $service_settings->remove_service( $services[ $index ]['name'] );
	if ( is_array( $result ) ) {
		return new WP_Error( 'klaro_geo_service_protected', $result['message'] );
	}

	$service_settings->save();
	return true;
}

function klaro_geo_delete_service() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce', '_wpnonce' );

	$result = klaro_geo_delete_service_at( isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1 );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ] );
	}

	wp_send_json_success( Klaro_Geo_Service_Settings::get_instance()->get() );
}
?>
