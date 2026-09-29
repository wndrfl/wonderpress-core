<?php
/**
 * Smoke test for the Static Kit token bridge.
 *
 * Run from wonderpress-core: php wonderpress-core/tests/token-bridge-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_test_print_bridge = true;
$wonderpress_test_settings     = array();
$wonderpress_test_inline       = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		global $wonderpress_test_print_bridge;

		if ( 'wonderpress_print_token_bridge' === $tag ) {
			return $wonderpress_test_print_bridge;
		}

		return $value;
	}
}

if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings() {
		global $wonderpress_test_settings;

		return $wonderpress_test_settings;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $css ) {
		global $wonderpress_test_inline;

		$wonderpress_test_inline[] = array(
			'handle' => $handle,
			'css'    => $css,
		);
	}
}

require_once dirname( __DIR__ ) . '/inc/token-bridge.php';

$flat = array(
	'color'      => array(
		'palette' => array(
			array(
				'slug'  => 'blue',
				'color' => '#7a97ab',
			),
			array(
				'slug'  => 'dark-blue',
				'color' => '#415364',
			),
		),
	),
	'typography' => array(
		'fontFamilies' => array(
			array(
				'slug' => 'sans-serif',
			),
		),
		'fontSizes'    => array(
			array(
				'slug' => 'small',
				'size' => '0.875rem',
			),
		),
	),
	'custom'     => array(
		'type'  => array(
			'h2' => array(
				'size'       => '1.5rem',
				'sizeTablet' => '3rem',
			),
		),
		'color' => array(
			'error' => '#f03d3e',
		),
	),
);

$css = wonder_token_bridge_css( $flat );

assert( false !== strpos( $css, '--color-blue: var(--wp--preset--color--blue);' ) );
assert( false !== strpos( $css, '--color-dark-blue: var(--wp--preset--color--dark-blue);' ) );
assert( false !== strpos( $css, '--font-sans-serif: var(--wp--preset--font-family--sans-serif);' ) );
assert( false !== strpos( $css, '--type-h2-size: var(--wp--custom--type--h2--size);' ) );
assert( false !== strpos( $css, '--type-h2-size-tablet: var(--wp--custom--type--h2--size-tablet);' ) );
assert( false !== strpos( $css, '--color-error: var(--wp--custom--color--error);' ) );
assert( false === strpos( $css, '--font-size' ) );
assert( false === strpos( $css, 'small' ) );

$origin_keyed = array(
	'color' => array(
		'palette' => array(
			'theme'   => array(
				array(
					'slug' => 'blue',
				),
			),
			'default' => array(
				array(
					'slug' => 'vivid-red',
				),
			),
		),
	),
);

$origin_css = wonder_token_bridge_css( $origin_keyed );
assert( false !== strpos( $origin_css, '--color-blue: var(--wp--preset--color--blue);' ) );
assert( false === strpos( $origin_css, 'vivid-red' ) );

assert( '' === wonder_token_bridge_css( array() ) );

$wonderpress_test_settings = $flat;
wonder_enqueue_token_bridge( 'wonderpress-home' );
assert( 1 === count( $wonderpress_test_inline ) );
assert( 'wonderpress-home' === $wonderpress_test_inline[0]['handle'] );
assert( $css === $wonderpress_test_inline[0]['css'] );

$wonderpress_test_print_bridge = false;
wonder_enqueue_token_bridge( 'wonderpress-home' );
assert( 1 === count( $wonderpress_test_inline ) );

echo "token-bridge-test.php OK\n";
