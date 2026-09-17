<?php
/**
 * Class TemplateRequiredDefaultTest
 *
 * Guards the polarity of the template-level "Required" checkbox.
 *
 * Klaro's changeAll() grants every service when the top-level config.required
 * is true, so a template saved with required=true makes the Decline button
 * accept everything. The admin form must therefore never pre-check the box for
 * a template that has not explicitly opted in.
 *
 * @package Klaro_Geo
 */

class TemplateRequiredDefaultTest extends WP_UnitTestCase {

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
		delete_option( 'klaro_geo_templates' );
		wp_delete_user( $this->admin_user_id );
		parent::tearDown();
	}

	/**
	 * Render the templates admin page and return the "Required" input tag.
	 *
	 * @return string The full <input> tag for template_config[required].
	 */
	private function get_required_input() {
		ob_start();
		klaro_geo_templates_page();
		$output = ob_get_clean();

		$matched = preg_match(
			'/<input[^>]*id="template_config_required"[^>]*>/',
			$output,
			$matches
		);

		$this->assertSame( 1, $matched, 'Templates page should render the Required checkbox' );

		return $matches[0];
	}

	/**
	 * The shipped templates do not define a top-level "required" key, so the
	 * checkbox must render unchecked. If it renders checked, simply opening and
	 * saving a template writes required=true and Decline starts granting
	 * consent for every service.
	 */
	public function test_required_checkbox_is_unchecked_when_template_omits_it() {
		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get_default_templates();

		$this->assertArrayNotHasKey(
			'required',
			$templates['default']['config'],
			'Precondition: the default template should not set a top-level required flag'
		);

		$template_settings->set( $templates );
		$template_settings->save();

		$this->assertStringNotContainsString(
			'checked',
			$this->get_required_input(),
			'Required must default to unchecked so Decline actually declines'
		);
	}

	/**
	 * An explicit opt-in still round-trips, so sites that deliberately require
	 * all services keep their setting.
	 */
	public function test_required_checkbox_is_checked_when_template_sets_it() {
		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get_default_templates();

		$templates['default']['config']['required'] = true;

		$template_settings->set( $templates );
		$template_settings->save();

		$this->assertStringContainsString(
			'checked',
			$this->get_required_input(),
			'An explicitly required template should keep its checked state'
		);
	}

	/**
	 * And an explicit false stays unchecked.
	 */
	public function test_required_checkbox_is_unchecked_when_template_disables_it() {
		$template_settings = new Klaro_Geo_Template_Settings();
		$templates         = $template_settings->get_default_templates();

		$templates['default']['config']['required'] = false;

		$template_settings->set( $templates );
		$template_settings->save();

		$this->assertStringNotContainsString(
			'checked',
			$this->get_required_input(),
			'An explicitly non-required template should render unchecked'
		);
	}
}
