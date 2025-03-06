<?php
/**
 * Custom test case that ignores deprecation notices.
 */
class IgnoreDeprecatedTestCase extends WP_UnitTestCase {
    /**
     * Set up before class is instantiated.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        // Disable deprecation notices
        add_filter('deprecated_function_trigger_error', '__return_false');
    }

    /**
     * A simple test to prevent PHPUnit warning about no tests in this class.
     * This class is meant to be extended by other test classes.
     */
    public function test_ignore_deprecated_case_exists() {
        $this->assertTrue(true, 'IgnoreDeprecatedTestCase exists');
    }

    /**
     * Clean up after class is done.
     */
    public static function tearDownAfterClass(): void {
        // Re-enable deprecation notices
        remove_filter('deprecated_function_trigger_error', '__return_false');

        parent::tearDownAfterClass();
    }

    /**
     * Set up before each test.
     */
    public function set_up() {
        parent::set_up();

        // Clear the caught_deprecated array before each test
        $this->caught_deprecated = array();
    }

    /**
     * Override the assertDeprecated method to do nothing.
     */
    public function assertDeprecated() {
        // Do nothing
    }

    /**
     * Override the assertNotDeprecated method to do nothing.
     */
    public function assertNotDeprecated() {
        // Do nothing
    }

    /**
     * Override the expectDeprecated method to do nothing.
     */
    public function expectDeprecated() {
        // Do nothing
    }

    /**
     * Override the expected_deprecated_to_exist method to always return true.
     */
    protected function expected_deprecated_to_exist() {
        return true;
    }
}