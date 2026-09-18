<?php
/**
 * Class TemplateSaveRoundTripTest
 *
 * Renders the templates admin page, harvests every field from the real form,
 * and posts it back the way a browser would. This reproduces save failures that
 * a hand-built POST array misses, because the real payload carries every
 * config key, the translations JSON and the service rows.
 *
 * @package Klaro_Geo
 */

class TemplateSaveRoundTripTest extends WP_UnitTestCase {

	protected $admin_user_id;

	public function setUp(): void {
		parent::setUp();
		delete_option( 'klaro_geo_templates' );

		$this->admin_user_id = $this->factory->user->create(
			array( 'role' => 'administrator' )
		);
		wp_set_current_user( $this->admin_user_id );

		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get_default_templates();
		$template_settings->set( $templates );
		$template_settings->save();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
		delete_option( 'klaro_geo_templates' );
		wp_delete_user( $this->admin_user_id );
		parent::tearDown();
	}

	/**
	 * Render the page and collect the fields of #template-form into a POST array.
	 *
	 * @return array{0: array, 1: string} The POST payload and the rendered page.
	 */
	private function harvest_form() {
		ob_start();
		klaro_geo_templates_page();
		$html = ob_get_clean();

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$form = $doc->getElementById( 'template-form' );
		$this->assertNotNull( $form, 'Templates page should render #template-form' );

		$payload = array();
		$xpath   = new DOMXPath( $doc );

		foreach ( $xpath->query( './/input|.//select|.//textarea', $form ) as $field ) {
			$name = $field->getAttribute( 'name' );
			if ( $name === '' ) {
				continue;
			}

			$tag = $field->tagName;

			if ( $tag === 'input' ) {
				$type = strtolower( $field->getAttribute( 'type' ) );
				if ( $type === 'submit' ) {
					continue;
				}
				// Unchecked boxes are not submitted; the hidden twin already
				// supplied the "false" value earlier in the document order.
				if ( ( $type === 'checkbox' || $type === 'radio' )
					&& ! $field->hasAttribute( 'checked' ) ) {
					continue;
				}
				$value = $field->getAttribute( 'value' );
			} elseif ( $tag === 'textarea' ) {
				$value = $field->textContent;
			} else {
				$value    = '';
				$selected = $xpath->query( './/option[@selected]', $field );
				if ( $selected->length > 0 ) {
					$value = $selected->item( 0 )->getAttribute( 'value' );
				} else {
					$options = $xpath->query( './/option', $field );
					if ( $options->length > 0 ) {
						$value = $options->item( 0 )->getAttribute( 'value' );
					}
				}
			}

			$this->assign_by_name( $payload, $name, $value );
		}

		return array( $payload, $html );
	}

	/**
	 * Expand a PHP form field name ("a[b][c]" / "a[]") into the payload array.
	 *
	 * @param array  $payload Payload being built, by reference.
	 * @param string $name    The field name attribute.
	 * @param string $value   The field value.
	 */
	private function assign_by_name( &$payload, $name, $value ) {
		if ( ! preg_match( '/^([^\[]+)((\[[^\]]*\])*)$/', $name, $m ) ) {
			return;
		}

		$keys = array( $m[1] );
		if ( ! empty( $m[2] ) ) {
			preg_match_all( '/\[([^\]]*)\]/', $m[2], $km );
			foreach ( $km[1] as $k ) {
				$keys[] = $k;
			}
		}

		$cursor = &$payload;
		$last   = array_pop( $keys );
		foreach ( $keys as $key ) {
			if ( $key === '' ) {
				$cursor[] = array();
				$cursor   = &$cursor[ count( $cursor ) - 1 ];
				continue;
			}
			if ( ! isset( $cursor[ $key ] ) || ! is_array( $cursor[ $key ] ) ) {
				$cursor[ $key ] = array();
			}
			$cursor = &$cursor[ $key ];
		}

		if ( $last === '' ) {
			$cursor[] = $value;
		} else {
			$cursor[ $last ] = $value;
		}
		unset( $cursor );
	}

	/**
	 * A template whose plugin_settings carry no gpc_purposes key must still
	 * render completely.
	 *
	 * On production this aborted the render: $gpc_purposes fell back to an
	 * undefined $available_purposes, and the in_array() right after it threw a
	 * TypeError on null, cutting the page off before the submit button.
	 *
	 * @dataProvider missing_gpc_purposes_provider
	 * @param mixed $stored What to leave in plugin_settings['gpc_purposes'].
	 */
	public function test_renders_completely_without_usable_gpc_purposes( $stored ) {
		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get();

		if ( $stored === 'ABSENT' ) {
			unset( $templates['default']['plugin_settings']['gpc_purposes'] );
		} else {
			$templates['default']['plugin_settings']['gpc_purposes'] = $stored;
		}

		$template_settings->set( $templates );
		$template_settings->save();

		ob_start();
		klaro_geo_templates_page();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'name="submit_template"',
			$output,
			'Page must render through to the submit button'
		);
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function missing_gpc_purposes_provider() {
		return array(
			'key absent'  => array( 'ABSENT' ),
			'null value'  => array( null ),
			'empty string' => array( '' ),
			'string value' => array( 'advertising' ),
		);
	}

	/**
	 * Posting the form back unchanged must save cleanly and render the whole
	 * page again, submit button included.
	 */
	public function test_round_trip_save_renders_complete_page() {
		list( $payload ) = $this->harvest_form();

		$nonce = wp_create_nonce( 'klaro_geo_template_nonce' );

		$payload['submit_template']          = 'Save Template';
		$payload['current_template']         = 'default';
		$payload['klaro_geo_template_nonce'] = $nonce;
		$payload['_wpnonce']                 = $nonce;

		$_POST    = $payload;
		$_REQUEST = $payload;

		ob_start();
		klaro_geo_templates_page();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'name="submit_template"',
			$output,
			'Page should render through to the submit button after a save'
		);
	}
}
