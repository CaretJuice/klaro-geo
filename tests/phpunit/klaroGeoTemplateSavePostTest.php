<?php
/**
 * Class TemplateSavePostTest
 *
 * Exercises the templates admin page through its POST/save path, which renders
 * the form again in-place rather than redirecting.
 *
 * @package Klaro_Geo
 */

class TemplateSavePostTest extends WP_UnitTestCase {

	protected $admin_user_id;

	public function setUp(): void {
		parent::setUp();
		delete_option( 'klaro_geo_templates' );

		$this->admin_user_id = $this->factory->user->create(
			array( 'role' => 'administrator' )
		);
		wp_set_current_user( $this->admin_user_id );
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
	 * Submitting the form with Required unchecked must save required=false and
	 * still render the complete page, including the submit button.
	 */
	public function test_saving_required_false_renders_complete_page() {
		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get_default_templates();
		$template_settings->set( $templates );
		$template_settings->save();

		$nonce = wp_create_nonce( 'klaro_geo_template_nonce' );

		$_POST = array(
			'submit_template'          => 'Save Template',
			'current_template'         => 'default',
			'klaro_geo_template_nonce' => $nonce,
			'_wpnonce'                 => $nonce,
			// Mirrors the browser: hidden "false" field, checkbox not present.
			'template_config'          => array(
				'required' => 'false',
				'default'  => 'false',
			),
		);
		$_REQUEST = $_POST;

		ob_start();
		klaro_geo_templates_page();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'submit_template',
			$output,
			'Page should render through to the submit button after a save'
		);

		$saved = ( new Klaro_Geo_Template_Settings() )->get();
		$this->assertFalse(
			(bool) $saved['default']['config']['required'],
			'Required should be saved as false'
		);
	}
}
