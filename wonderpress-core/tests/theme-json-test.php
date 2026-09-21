<?php
/**
 * Smoke test for the user Global Styles guard.
 *
 * Run from wonderpress-core: php wonderpress-core/tests/theme-json-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_test_strip_user_styles = true;
$wonderpress_test_registered_filter = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		global $wonderpress_test_strip_user_styles;

		if ( 'wonderpress_strip_user_global_styles' === $tag ) {
			return $wonderpress_test_strip_user_styles;
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10 ) {
		global $wonderpress_test_registered_filter;
		$wonderpress_test_registered_filter = compact( 'tag', 'callback', 'priority' );
	}
}

class WP_Theme_JSON {
	public const LATEST_SCHEMA = 3;
}

class WP_Theme_JSON_Data {
	private $data;
	private $origin;

	public function __construct( $data, $origin ) {
		$this->data   = $data;
		$this->origin = $origin;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_origin() {
		return $this->origin;
	}
}

require_once dirname( __DIR__ ) . '/inc/theme-json.php';

assert( 'wp_theme_json_data_user' === $wonderpress_test_registered_filter['tag'] );
assert( 'wonder_strip_user_global_styles' === $wonderpress_test_registered_filter['callback'] );
assert( PHP_INT_MAX === $wonderpress_test_registered_filter['priority'] );

$user_data = new WP_Theme_JSON_Data(
	array(
		'version' => 3,
		'styles'  => array( 'color' => array( 'text' => '#f00' ) ),
	),
	'custom'
);

$stripped = wonder_strip_user_global_styles( $user_data );
assert( array( 'version' => 3 ) === $stripped->get_data() );
assert( 'custom' === $stripped->get_origin() );

$wonderpress_test_strip_user_styles = false;
assert( $user_data === wonder_strip_user_global_styles( $user_data ) );

echo "theme-json-test.php OK\n";
