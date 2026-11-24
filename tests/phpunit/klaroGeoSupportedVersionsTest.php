<?php
/**
 * The oldest WordPress the plugin says it runs on ("Requires at least") is a
 * promise. This test keeps the code inside it: every WordPress function the
 * plugin calls must have existed in that version.
 *
 * The rest of the suite cannot catch this. A function that is too new only
 * fails when the line calling it runs on an old WordPress, and that line may
 * have no test: wp_print_inline_script_tag() (5.7) is called once, and the
 * suite passed on WordPress 5.2.
 *
 * What it cannot see is a newer *argument* to an old function (the script
 * loading strategy of wp_enqueue_script(), 6.3). CI runs PHPUnit and the
 * Playwright suite on the oldest supported pair for that.
 *
 * @package Klaro_Geo
 */

class KlaroGeoSupportedVersionsTest extends WP_UnitTestCase {

	private function plugin_dir() {
		return dirname( dirname( __DIR__ ) );
	}

	private function plugin_files() {
		$dir = $this->plugin_dir();
		return array_merge(
			array( $dir . '/klaro-geo.php', $dir . '/uninstall.php' ),
			glob( $dir . '/includes/*.php' ),
			glob( $dir . '/includes/admin/*.php' )
		);
	}

	/**
	 * The "Requires at least" header of the plugin file.
	 */
	private function required_wordpress() {
		$headers = get_file_data( $this->plugin_dir() . '/klaro-geo.php', array( 'requires' => 'Requires at least' ) );
		return $headers['requires'];
	}

	/**
	 * "5.7" as "5.7.0": version_compare() ranks "5.7.0" above "5.7".
	 */
	private function three_part( $version ) {
		return implode( '.', array_slice( array_merge( explode( '.', $version ), array( '0', '0' ) ), 0, 3 ) );
	}

	/**
	 * Names of the functions called in a PHP file, with the line of one call.
	 * Method calls, static calls and definitions are left out.
	 *
	 * @return array function name => line
	 */
	private function functions_called_in( $file ) {
		$tokens = token_get_all( file_get_contents( $file ) );
		$count  = count( $tokens );
		$called = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] ) {
				continue;
			}

			$next = $i + 1;
			while ( $next < $count && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) {
				++$next;
			}
			$previous = $i - 1;
			while ( $previous >= 0 && is_array( $tokens[ $previous ] ) && T_WHITESPACE === $tokens[ $previous ][0] ) {
				--$previous;
			}
			$before = is_array( $tokens[ $previous ] ) ? $tokens[ $previous ][0] : $tokens[ $previous ];

			if ( $next < $count && '(' === $tokens[ $next ] && ! in_array( $before, array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW ), true ) ) {
				$called[ $tokens[ $i ][1] ] = $tokens[ $i ][2];
			}
		}

		return $called;
	}

	/**
	 * The version each WordPress function was introduced in, read from the
	 * "@since" line of its documentation in the WordPress under test.
	 *
	 * @return array function name => version
	 */
	private function wordpress_function_versions() {
		$versions = array();
		$files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH ) );

		foreach ( $files as $file ) {
			$path = (string) $file;
			if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, '/wp-content/' ) ) {
				continue;
			}
			if ( ! preg_match_all( '#/\*\*((?:(?!\*/).)*?)\*/\s*function\s+&?([a-zA-Z0-9_]+)\s*\(#s', file_get_contents( $path ), $functions, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $functions as $function ) {
				if ( preg_match( '/@since\s+(?:MU \()?([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $function[1], $since ) ) {
					$versions[ $function[2] ] = $since[1];
				}
			}
		}

		return $versions;
	}

	public function test_plugin_file_and_readme_state_the_same_requirements() {
		$plugin = get_file_data(
			$this->plugin_dir() . '/klaro-geo.php',
			array(
				'wordpress' => 'Requires at least',
				'php'       => 'Requires PHP',
			)
		);
		$readme = file_get_contents( $this->plugin_dir() . '/readme.txt' );

		$this->assertNotSame( '', $plugin['wordpress'] );
		$this->assertStringContainsString( "\nRequires at least: " . $plugin['wordpress'] . "\n", $readme );
		$this->assertStringContainsString( "\nRequires PHP: " . $plugin['php'] . "\n", $readme );
	}

	public function test_every_wordpress_function_called_existed_in_the_oldest_supported_version() {
		$required = $this->required_wordpress();
		$versions = $this->wordpress_function_versions();
		$this->assertArrayHasKey( 'wp_enqueue_script', $versions, 'WordPress function versions could not be read' );

		$too_new = array();
		foreach ( $this->plugin_files() as $file ) {
			foreach ( $this->functions_called_in( $file ) as $function => $line ) {
				if ( isset( $versions[ $function ] ) && version_compare( $this->three_part( $versions[ $function ] ), $this->three_part( $required ), '>' ) ) {
					$too_new[] = $function . '() is from WordPress ' . $versions[ $function ] . ' (' . basename( $file ) . ':' . $line . ')';
				}
			}
		}

		$this->assertSame( array(), $too_new, 'The plugin requires WordPress ' . $required . ' but calls newer functions' );
	}
}
