<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Country settings page content
function klaro_geo_country_settings_page_content() {
	$geo_settings = Klaro_Geo_Country_Settings::get_instance()->get();
	$visible_countries = Klaro_Geo_Country_Settings::get_instance()->get_visible_countries();

	// Load country codes from CSV file
	$csv_file  = dirname( dirname( plugin_dir_path( __FILE__ ) ) ) . '/countries.csv';
	$countries = array();
	$csv_contents = file_get_contents( $csv_file );
	if ( false !== $csv_contents ) {
		$lines = explode( "\n", $csv_contents );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			$data = str_getcsv( $line );
			if ( isset( $data[0], $data[1] ) ) {
				$countries[ $data[0] ] = $data[1];
			}
		}
	}

	// Sort countries alphabetically by name
	asort( $countries );

	// Sort visible countries alphabetically
	$sorted_visible_countries = array();
	foreach ( $countries as $code => $name ) {
		if ( in_array( $code, $visible_countries ) ) {
			$sorted_visible_countries[] = $code;
		}
	}
	$visible_countries = $sorted_visible_countries;

	$templates = Klaro_Geo_Template_Settings::get_instance()->get();

	// Check if 'default' template exists
	$has_default_template = isset( $templates['default'] );

	// Check for invalid template references in country settings
	$invalid_template_refs   = array();
	$available_template_keys = array_keys( $templates );

	if ( $geo_settings ) {
		// Check default_template
		if ( ! empty( $geo_settings['default_template'] ) &&
			! in_array( $geo_settings['default_template'], $available_template_keys ) ) {
			$invalid_template_refs['Fallback Template'] = $geo_settings['default_template'];
		}

		// Check each country's template
		foreach ( $geo_settings as $code => $config ) {
			if ( $code === 'default_template' ) {
				continue;
			}
			if ( is_array( $config ) && isset( $config['template'] ) &&
				$config['template'] !== 'inherit' &&
				! in_array( $config['template'], $available_template_keys ) ) {
				$country_name = isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
				$invalid_template_refs[ $country_name . ' (' . $code . ')' ] = $config['template'];
			}
		}
	}

	?>
	<div class="wrap">
		<h1>Klaro Geo Country Settings</h1>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by the redirect after a save; no data is modified. ?>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>Country settings saved.</p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $invalid_template_refs ) ) : ?>
		<div class="notice notice-error">
			<p><strong>Warning:</strong> The following settings reference templates that don't exist:</p>
			<ul style="margin-left: 20px; list-style: disc;">
				<?php foreach ( $invalid_template_refs as $location => $template_key ) : ?>
				<li><?php echo esc_html( $location ); ?>: "<strong><?php echo esc_html( $template_key ); ?></strong>"</li>
				<?php endforeach; ?>
			</ul>
			<p>Available templates: <strong><?php echo esc_html( implode( ', ', $available_template_keys ) ); ?></strong></p>
			<p>Please update these settings to use valid template keys, or <a href="<?php echo esc_url( admin_url( 'admin.php?page=klaro_geo_templates_page' ) ); ?>">create the missing templates</a>.</p>
		</div>
		<?php endif; ?>

		<?php if ( empty( $templates ) ) : ?>
		<div class="notice notice-warning">
			<p><strong>No templates found.</strong> Please <a href="<?php echo esc_url( admin_url( 'admin.php?page=klaro_geo_templates_page' ) ); ?>">save your templates</a> first before configuring country settings.</p>
		</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="klaro-country-settings-form">
			<input type="hidden" name="action" value="klaro_geo_save_country_settings_form">
			<?php wp_nonce_field( 'klaro_geo_country_settings_form' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Country</th>
						<th>Template</th>
						<th>Regions with Overrides</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<!-- Default settings for other countries -->
				<tr class="default-settings">
					<td><strong>Fallback Template</strong></td>
					<td>
						<select name="klaro_geo_country_settings[default_template]">
							<?php foreach ( $templates as $key => $template ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"
									<?php selected( isset( $geo_settings['default_template'] ) ? $geo_settings['default_template'] : 'default', $key ); ?>>
									<?php echo esc_html( $template['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>&nbsp;</td>
					<td>
						<button type="button" id="manage-countries" class="button">Show/Hide Countries</button>
					</td>
				</tr>

				<!-- Visible countries -->
				<?php
				foreach ( $visible_countries as $code ) :
					if ( ! isset( $countries[ $code ] ) ) {
						continue;
					}
					$name = $countries[ $code ];
					if ( isset( $geo_settings[ $code ] ) && is_array( $geo_settings[ $code ] ) ) {
						$country_config = $geo_settings[ $code ];
					} else {
						// Default settings if nothing is found
						$country_config = array(
							'template' => 'inherit',
							'regions'  => array(),
						);
						// Flag this as a default config so we don't save it unnecessarily
						$country_config['_is_default'] = true;
					}
					?>
						<tr class="country-row">
							<td><?php echo esc_html( $code ); ?> (<?php echo esc_html( $name ); ?>)</td>
							<td>
								<select name="klaro_geo_country_settings[<?php echo esc_attr( $code ); ?>][template]">
									<option value="inherit">Use fallback template</option>
									<?php foreach ( $templates as $key => $template ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"
											<?php selected( $country_config['template'], $key ); ?>>
											<?php echo esc_html( $template['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>

								<?php
								// Add hidden fields for region settings
								$country_regions = isset( $country_config['regions'] ) ? $country_config['regions'] : array();
								foreach ( $country_regions as $region_code => $template ) {
									echo '<input type="hidden" name="klaro_geo_country_settings[' . esc_attr( $code ) . '][regions][' . esc_attr( $region_code ) . ']" value="' . esc_attr( $template ) . '">';
								}
								?>
							</td>
							<td>
								<?php
								// Display regions with overrides
								$regions_with_overrides = array();

								foreach ( $country_regions as $region_code => $template ) {
									if ( ! empty( $template ) && $template !== 'default' ) {
										$regions_with_overrides[] = $region_code;
									}
								}

								if ( ! empty( $regions_with_overrides ) ) {
									echo '<span class="regions-list">' . esc_html( implode( ', ', $regions_with_overrides ) ) . '</span>';
								} else {
									echo '<span class="no-regions">None</span>';
								}
								?>
							</td>
							<td>
								<button type="button" class="button manage-regions" data-country="<?php echo esc_attr( $code ); ?>">
									Manage Regions
								</button>
								<button type="button" class="button hide-country" data-country="<?php echo esc_attr( $code ); ?>">
									Hide
								</button>
							</td>
						</tr>

						<!-- Region Management Modal -->
						<div id="region-modal-<?php echo esc_attr( $code ); ?>" class="klaro-modal" style="display: none;">
							<div class="klaro-modal-content">
								<h2>Manage Regions for <?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)</h2>
								<form id="region-form-<?php echo esc_attr( $code ); ?>" class="region-form" onsubmit="return false;" action="javascript:void(0);">
									<div class="region-list" data-country="<?php echo esc_attr( $code ); ?>">
										Loading regions...
									</div>
								</form>
								<div class="region-modal-actions">
									<button type="button" class="button button-primary save-regions" data-country="<?php echo esc_attr( $code ); ?>" id="save-regions-<?php echo esc_attr( $code ); ?>">Save Regions</button>
									<button type="button" class="button close-region-modal" id="close-region-modal-<?php echo esc_attr( $code ); ?>">Close</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</tbody>
			</table>
			<!-- Country Management Modal -->
			<div id="country-modal" class="klaro-modal" style="display: none;">
				<div class="klaro-modal-content">
					<h2>Manage Countries</h2>
					<div class="country-list">
						<?php foreach ( $countries as $code => $name ) : ?>
							<div class="country-item">
								<label>
									<input type="checkbox"
											name="klaro_geo_visible_countries[]"
											value="<?php echo esc_attr( $code ); ?>"
											<?php checked( in_array( $code, $visible_countries ) ); ?>>
									<?php echo esc_html( $code . ' - ' . $name ); ?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="modal-actions">
						<button type="button" class="button button-primary save-countries">Save</button>
						<button type="button" class="button close-country-modal">Cancel</button>
					</div>
				</div>
			</div>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Save the country settings form.
 *
 * The form's Save button and the autosave on the same page both submit the
 * same fields, so both come through here: update_from_form() validates the
 * template names and drops countries that only repeat the fallback template.
 *
 * @param mixed $submitted The submitted klaro_geo_country_settings value, unslashed.
 * @return bool Whether the stored option changed.
 */
function klaro_geo_save_country_settings_form_data( $submitted ) {
	$country_settings = Klaro_Geo_Country_Settings::get_instance();
	$country_settings->update_from_form( klaro_geo_sanitize_array( (array) $submitted ) );

	return $country_settings->save();
}

// Handler for the form's Save button (admin-post.php)
function klaro_geo_save_country_settings_form() {
	check_admin_referer( 'klaro_geo_country_settings_form' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to perform this action.', 403 );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in klaro_geo_save_country_settings_form_data()
	klaro_geo_save_country_settings_form_data( isset( $_POST['klaro_geo_country_settings'] ) ? wp_unslash( $_POST['klaro_geo_country_settings'] ) : array() );

	// The form also holds the Manage Countries checkboxes. None checked posts no field.
	$visible_countries = isset( $_POST['klaro_geo_visible_countries'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['klaro_geo_visible_countries'] ) ) : array();
	Klaro_Geo_Country_Settings::get_instance()->save_visible_countries( $visible_countries );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'klaro-geo-country',
				'updated' => 'true',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_klaro_geo_save_country_settings_form', 'klaro_geo_save_country_settings_form' );

// AJAX handler for the autosave of the same form
function klaro_geo_save_country_settings() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce' );

	$raw_settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in klaro_geo_save_country_settings_form_data()
	parse_str( $raw_settings, $settings );

	klaro_geo_save_country_settings_form_data( isset( $settings['klaro_geo_country_settings'] ) ? $settings['klaro_geo_country_settings'] : array() );

	wp_send_json_success();
}
add_action( 'wp_ajax_klaro_geo_save_country_settings', 'klaro_geo_save_country_settings' );

// AJAX handler for saving region settings
function klaro_geo_save_region_settings_ajax() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce' );

	klaro_geo_debug_log( 'Region settings AJAX request received' );

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string cannot be pre-sanitized; sanitized via klaro_geo_sanitize_array after json_decode below.
	$settings_json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';

	if ( empty( $settings_json ) ) {
		wp_send_json_error( [ 'message' => 'No settings provided' ] );
	}

	// Decode and sanitize the JSON string
	$settings = json_decode( $settings_json, true );

	// Check if JSON decoding was successful
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		wp_send_json_error( [ 'message' => 'Invalid JSON: ' . json_last_error_msg() ] );
	}

	$settings = klaro_geo_sanitize_array( $settings );
	klaro_geo_debug_log( 'Region settings received: ' . count( $settings ) . ' entries' );

	// Use the new country settings class
	$country_settings = Klaro_Geo_Country_Settings::get_instance();

	// Update regions from AJAX data
	$country_settings->update_regions_from_ajax( $settings );

	// Save the updated settings
	$result = $country_settings->save();

	// Debug log the result
	klaro_geo_debug_log( 'Update option result: ' . ( $result ? 'true' : 'false' ) );

	wp_send_json_success(
		[
			'message'  => 'Region settings saved successfully',
			'settings' => $settings,
		]
	);
}
add_action( 'wp_ajax_klaro_geo_save_region_settings', 'klaro_geo_save_region_settings_ajax' );

// AJAX handler for getting regions
function klaro_geo_get_regions() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce' );

	$country  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
	$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';

	if ( empty( $country ) ) {
		wp_send_json_error( [ 'message' => 'Country code is required' ] );
	}

	// Debug log
	klaro_geo_debug_log( 'Getting regions for country: ' . $country . ', language: ' . $language );

	// Load regions from CSV file
	$regions_file = KLARO_GEO_PATH . 'subdivisions.csv';
	klaro_geo_debug_log( 'Attempting to load regions from: ' . $regions_file );

	$regions   = [];
	$languages = [];

	$csv_contents = file_exists( $regions_file ) ? file_get_contents( $regions_file ) : false;
	if ( false !== $csv_contents ) {
		$lines = explode( "\n", $csv_contents );

		// Skip the header row
		$header = ! empty( $lines ) ? str_getcsv( array_shift( $lines ) ) : array();
		klaro_geo_debug_log( 'CSV Header: ' . wp_json_encode( $header ) );

		$row_count   = 0;
		$match_count = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			$data = str_getcsv( $line );
			++$row_count;

			// Debug first few rows to understand the structure
			if ( $row_count <= 5 ) {
				klaro_geo_debug_log( 'CSV Row ' . $row_count . ': ' . wp_json_encode( $data ) );
			}

			// The CSV format is: country_code, subdivision_code, subdivision_name, language_code, ...
			// Check if this is a region for the requested country
			if ( isset( $data[0] ) && $data[0] === $country && isset( $data[1] ) ) {
				++$match_count;
				klaro_geo_debug_log( 'Found match for country ' . $country . ': ' . wp_json_encode( $data ) );

				// Extract the region code from the subdivision_code (e.g., "BE-WBR" -> "WBR")
				$region_code = str_replace( $country . '-', '', $data[1] );

				// If we already have this region code, append the new name
				if ( isset( $regions[ $region_code ] ) ) {
					// Only add if it's a different name
					if ( $regions[ $region_code ] !== $data[2] ) {
						$regions[ $region_code ] .= ' / ' . $data[2];
					}
				} else {
					$regions[ $region_code ] = $data[2];
				}

				// Add language if it's specified in the CSV
				if ( isset( $data[3] ) && ! empty( $data[3] ) && ! in_array( $data[3], $languages, true ) ) {
					$languages[] = $data[3];
				}
			}
		}

		klaro_geo_debug_log( 'Processed ' . $row_count . ' rows, found ' . $match_count . ' matches for country ' . $country );
	}

	// If no regions found, return an empty array
	if ( empty( $regions ) ) {
		klaro_geo_debug_log( 'No regions found for country: ' . $country );
		wp_send_json_success(
			[
				'regions'          => [],
				'languages'        => [],
				'current_language' => $language,
				'settings'         => [],
			]
		);
	}

	// Existing region settings for this country
	$country_config  = Klaro_Geo_Country_Settings::get_instance()->get_country( $country );
	$region_settings = isset( $country_config['regions'] ) ? $country_config['regions'] : array();

	// Convert regions to the format expected by the JavaScript
	$formatted_regions = [];
	foreach ( $regions as $code => $name ) {
		// For the JavaScript, we need to provide the regions in a key-value format
		$formatted_regions[ $code ] = $name;
	}

	// Debug log
	klaro_geo_debug_log( 'Returning regions: ' . wp_json_encode( $formatted_regions ) );
	klaro_geo_debug_log( 'Returning region settings: ' . wp_json_encode( $region_settings ) );
	klaro_geo_debug_log( 'Returning languages: ' . wp_json_encode( $languages ) );

	wp_send_json_success(
		[
			'regions'          => $formatted_regions,
			'languages'        => $languages,
			'current_language' => $language,
			'settings'         => $region_settings,
		]
	);
}
add_action( 'wp_ajax_klaro_geo_get_regions', 'klaro_geo_get_regions' );

// AJAX handler for saving visible countries
function klaro_geo_save_visible_countries() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_nonce' );

	$countries = isset( $_POST['countries'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['countries'] ) ) : [];

	// Debug log
	klaro_geo_debug_log( 'Saving visible countries: ' . wp_json_encode( $countries ) );

	// Use the new country settings class
	$country_settings = Klaro_Geo_Country_Settings::get_instance();

	// Set and save visible countries
	$country_settings->set_visible_countries( $countries );
	$result = $country_settings->save_visible_countries();

	klaro_geo_debug_log( 'Saved visible countries: ' . ( $result ? 'success' : 'failed' ) );

	wp_send_json_success();
}
add_action( 'wp_ajax_klaro_geo_save_visible_countries', 'klaro_geo_save_visible_countries' );
