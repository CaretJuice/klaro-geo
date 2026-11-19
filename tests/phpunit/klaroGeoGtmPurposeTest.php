<?php
/**
 * Class GtmPurposeTest
 *
 * Google Tag Manager lives in its own 'performance' purpose.
 *
 * Klaro draws a purpose as checked when any of its services is on. With GTM in
 * both 'analytics' and 'advertising', enabling one purpose made the other look
 * enabled, and a visitor's click on it switched it off. Enabling advertising,
 * then analytics, then disabling advertising saved every service, GTM
 * included, as declined.
 *
 * @package Klaro_Geo
 */

class GtmPurposeTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'klaro_geo_services' );
		delete_option( 'klaro_geo_purposes' );
		delete_option( 'klaro_geo_gtm_purpose_migrated' );
		Klaro_Geo_Service_Settings::clear_instance_cache();
	}

	public function tear_down() {
		Klaro_Geo_Service_Settings::clear_instance_cache();
		parent::tear_down();
	}

	/**
	 * Store a GTM service with the given purposes, replacing the default one.
	 *
	 * @param string[] $purposes GTM purposes to store.
	 */
	private function store_gtm_purposes( array $purposes ) {
		$settings        = Klaro_Geo_Service_Settings::get_instance();
		$gtm             = $settings->get_service( 'google-tag-manager' );
		$gtm['purposes'] = $purposes;
		$settings->set_service( 'google-tag-manager', $gtm );
		$settings->save();
		Klaro_Geo_Service_Settings::clear_instance_cache();
	}

	/**
	 * @return string[] The stored GTM service's purposes.
	 */
	private function stored_gtm_purposes() {
		Klaro_Geo_Service_Settings::clear_instance_cache();
		$gtm = Klaro_Geo_Service_Settings::get_instance()->get_service( 'google-tag-manager' );
		return $gtm['purposes'];
	}

	public function test_default_gtm_service_has_only_the_performance_purpose() {
		$defaults = klaro_geo_get_default_services();
		$gtm      = null;
		foreach ( $defaults as $service ) {
			if ( 'google-tag-manager' === $service['name'] ) {
				$gtm = $service;
			}
		}

		$this->assertNotNull( $gtm );
		$this->assertSame( array( 'performance' ), $gtm['purposes'] );
		$this->assertFalse( $gtm['required'], 'GTM must stay consent-gated' );
	}

	/**
	 * The root cause guard: no default service may span purposes while
	 * groupByPurpose is on by default.
	 */
	public function test_no_default_service_belongs_to_more_than_one_purpose() {
		foreach ( klaro_geo_get_default_services() as $service ) {
			$this->assertCount( 1, $service['purposes'], $service['name'] . ' must have exactly one purpose' );
		}
	}

	public function test_default_purposes_include_performance_with_translations() {
		$this->assertContains( 'performance', klaro_geo_get_purposes() );

		$translations = klaro_geo_get_default_purpose_translations();
		foreach ( klaro_geo_get_default_purposes() as $purpose ) {
			$this->assertNotEmpty( $translations[ $purpose ]['title'], "$purpose needs a default title" );
			$this->assertNotEmpty( $translations[ $purpose ]['description'], "$purpose needs a default description" );
		}
	}

	public function test_migration_moves_gtm_from_analytics_and_advertising() {
		$this->store_gtm_purposes( array( 'analytics', 'advertising' ) );

		klaro_geo_migrate_gtm_purpose();

		$this->assertSame( array( 'performance' ), $this->stored_gtm_purposes() );
		$this->assertTrue( (bool) get_option( 'klaro_geo_gtm_purpose_migrated' ) );
	}

	public function test_migration_leaves_customized_gtm_purposes_alone() {
		$this->store_gtm_purposes( array( 'analytics' ) );

		klaro_geo_migrate_gtm_purpose();

		$this->assertSame( array( 'analytics' ), $this->stored_gtm_purposes() );
	}

	public function test_migration_adds_performance_to_a_stored_purpose_list() {
		update_option( 'klaro_geo_purposes', 'functional,analytics,advertising,personalization' );

		klaro_geo_migrate_gtm_purpose();

		$this->assertSame( 'functional,performance,analytics,advertising,personalization', get_option( 'klaro_geo_purposes' ) );
	}

	public function test_migration_runs_once() {
		klaro_geo_migrate_gtm_purpose();
		$this->store_gtm_purposes( array( 'analytics', 'advertising' ) );

		klaro_geo_migrate_gtm_purpose();

		$this->assertSame( array( 'analytics', 'advertising' ), $this->stored_gtm_purposes(), 'A second run must be a no-op' );
	}

	/**
	 * A template saved before 'performance' existed has no translation for
	 * it; Klaro must still get a title and description.
	 */
	public function test_config_fills_missing_purpose_translation_and_keeps_custom_ones() {
		$templates = array(
			'default' => array(
				'name'   => 'Default Template',
				'config' => array(
					'default'      => false,
					'translations' => array(
						'zz' => array(
							'purposes' => array(
								'analytics' => array(
									'title'       => 'My Analytics',
									'description' => 'Custom text',
								),
							),
						),
					),
				),
			),
		);

		$services = Klaro_Geo_Service_Settings::get_instance()->get_default_services();
		$klaro    = klaro_geo_build_template_payload( 'default', $templates, $services )['config'];
		$purposes = $klaro['translations']['zz']['purposes'];

		$this->assertSame( 'Performance', $purposes['performance']['title'] );
		$this->assertSame( 'My Analytics', $purposes['analytics']['title'], 'A template translation must not be overwritten' );
		$this->assertSame( klaro_geo_get_purposes(), $klaro['purposeOrder'] );
	}

	public function test_config_keeps_a_template_purpose_order() {
		$templates = array(
			'default' => array(
				'name'   => 'Default Template',
				'config' => array(
					'default'      => false,
					'purposeOrder' => array( 'analytics', 'advertising' ),
				),
			),
		);

		$services = Klaro_Geo_Service_Settings::get_instance()->get_default_services();
		$klaro    = klaro_geo_build_template_payload( 'default', $templates, $services )['config'];

		$this->assertSame( array( 'analytics', 'advertising' ), $klaro['purposeOrder'] );
	}
}
