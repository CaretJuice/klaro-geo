<?php
/**
 * Admin AJAX handlers must refuse users who cannot manage options, even when
 * the request carries a valid nonce. A nonce proves where a request came from,
 * not what the user is allowed to do.
 *
 * @package Klaro_Geo
 */

class KlaroGeoAdminAjaxAuthorizationTest extends WP_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Klaro_Geo_Service_Settings::clear_instance_cache();
		Klaro_Geo_Template_Settings::clear_instance_cache();
	}

	public function tear_down() {
		Klaro_Geo_Service_Settings::clear_instance_cache();
		Klaro_Geo_Template_Settings::clear_instance_cache();
		parent::tear_down();
	}

	/**
	 * Run an AJAX action and return the decoded JSON response.
	 */
	private function request( $action, array $post ) {
		$_POST                = $post;
		$this->_last_response = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			return array(
				'success' => false,
				'die'     => $e->getMessage(),
			);
		}
		return json_decode( $this->_last_response, true );
	}

	private function services_json() {
		return wp_json_encode(
			array(
				array(
					'name'     => 'injected-service',
					'purposes' => array( 'advertising' ),
					'required' => true,
				),
			)
		);
	}

	private function service_names() {
		Klaro_Geo_Service_Settings::clear_instance_cache();
		return wp_list_pluck( Klaro_Geo_Service_Settings::get_instance()->get(), 'name' );
	}

	/**
	 * @dataProvider unprivileged_roles
	 */
	public function test_save_services_refuses_users_without_manage_options( $role ) {
		$this->_setRole( $role );
		$before = $this->service_names();

		$response = $this->request(
			'klaro_geo_save_services',
			array(
				'_wpnonce' => wp_create_nonce( 'klaro_geo_nonce' ),
				'services' => $this->services_json(),
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( $before, $this->service_names(), 'Services must be unchanged' );
		$this->assertNotContains( 'injected-service', $this->service_names() );
	}

	/**
	 * @dataProvider unprivileged_roles
	 */
	public function test_delete_service_refuses_users_without_manage_options( $role ) {
		$this->_setRole( $role );
		$before = $this->service_names();
		$this->assertNotEmpty( $before );

		$response = $this->request(
			'klaro_geo_delete_service',
			array(
				'_wpnonce' => wp_create_nonce( 'klaro_geo_nonce' ),
				'index'    => 0,
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( $before, $this->service_names(), 'Services must be unchanged' );
	}

	/**
	 * @dataProvider unprivileged_roles
	 */
	public function test_create_template_refuses_users_without_manage_options( $role ) {
		$this->_setRole( $role );

		$response = $this->request(
			'klaro_geo_create_template',
			array(
				'nonce'         => wp_create_nonce( 'klaro_geo_template_nonce' ),
				'template_name' => 'Injected Template',
				'inherit_from'  => 'default',
			)
		);

		$this->assertFalse( $response['success'] );
		Klaro_Geo_Template_Settings::clear_instance_cache();
		$this->assertArrayNotHasKey( 'injected-template', Klaro_Geo_Template_Settings::get_instance()->get() );
	}

	public function unprivileged_roles() {
		return array(
			'subscriber' => array( 'subscriber' ),
			'editor'     => array( 'editor' ),
		);
	}

	public function test_administrator_without_a_valid_nonce_is_refused() {
		$this->_setRole( 'administrator' );

		$response = $this->request(
			'klaro_geo_save_services',
			array(
				'_wpnonce' => 'not-a-nonce',
				'services' => $this->services_json(),
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertNotContains( 'injected-service', $this->service_names() );
	}

	public function test_administrator_can_save_services() {
		$this->_setRole( 'administrator' );

		$response = $this->request(
			'klaro_geo_save_services',
			array(
				'_wpnonce' => wp_create_nonce( 'klaro_geo_nonce' ),
				'services' => $this->services_json(),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertContains( 'injected-service', $this->service_names() );
	}

	public function test_administrator_can_create_a_template() {
		$this->_setRole( 'administrator' );

		$response = $this->request(
			'klaro_geo_create_template',
			array(
				'nonce'         => wp_create_nonce( 'klaro_geo_template_nonce' ),
				'template_name' => 'Created Template',
				'inherit_from'  => 'default',
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'created-template', $response['data']['template_key'] );
	}

	/**
	 * The frontend must not hand visitors a nonce for the admin handlers.
	 */
	public function test_frontend_settings_do_not_include_the_admin_nonce() {
		unset( $GLOBALS['wp_scripts'] );
		$this->_setRole( 'subscriber' );
		set_current_screen( 'front' );
		$this->go_to( home_url( '/' ) );

		do_action( 'wp_enqueue_scripts' );

		$inline = implode( "\n", (array) wp_scripts()->get_data( 'klaro-geo-consent-button-js', 'before' ) );
		$this->assertStringContainsString( 'window.klaroGeo', $inline );
		$this->assertStringNotContainsString( wp_create_nonce( 'klaro_geo_nonce' ), $inline );
		$this->assertStringNotContainsString( '"nonce"', $inline );
	}
}
