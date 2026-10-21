<?php
/**
 * wonder_partial_props() takes one argument: the ACF group field name.
 *
 * That is the composition row id, or the partial slug when the partial is
 * placed by location. The old two-argument form ( $slug, $instance_id ) still
 * works but raises a deprecation. Abstract_Partial ignores a non-array `acf`
 * value instead of warning on it.
 *
 * Run: php wonderpress-core/tests/partial-props-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_probe_root = sys_get_temp_dir() . '/wonderpress-partial-props-' . getmypid() . '/';
define( 'WONDERPRESS_CORE_PATH', $wonderpress_probe_root );

$wonderpress_acf_values  = array();
$wonderpress_deprecated  = array();
$wonderpress_php_notices = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong() {}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'locate_template' ) ) {
	function locate_template() {
		return '';
	}
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $html, $allowed_html ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $html;
	}
}
if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
	function wp_kses_allowed_html() {
		return array();
	}
}
if ( ! function_exists( '_deprecated_argument' ) ) {
	function _deprecated_argument( $function_name, $version, $message = '' ) {
		global $wonderpress_deprecated;
		$wonderpress_deprecated[] = array( $function_name, $version, $message );
	}
}
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $name ) {
		global $wonderpress_acf_values;
		return array_key_exists( $name, $wonderpress_acf_values ) ? $wonderpress_acf_values[ $name ] : false;
	}
}

set_error_handler(
	function ( $errno, $errstr ) {
		global $wonderpress_php_notices;
		$wonderpress_php_notices[] = $errstr;
		return true;
	}
);

require_once dirname( __DIR__ ) . '/inc/manifest-property.php';
require_once dirname( __DIR__ ) . '/inc/acf.php';
require_once dirname( __DIR__ ) . '/inc/block-attributes.php';
require_once dirname( __DIR__ ) . '/src/partials/class-partial-interface.php';
require_once dirname( __DIR__ ) . '/src/partials/class-abstract-partial.php';

use Wonderpress_Core\Partials\Abstract_Partial;

/**
 * An ACF-compatible probe partial.
 */
class Props_Probe_Partial extends Abstract_Partial {
	/**
	 * @var Boolean
	 */
	protected $acf_compatible = true;

	/**
	 * @var Array
	 */
	protected static $properties = array(
		'acf'   => array(
			'format' => 'array',
		),
		'title' => array(
			'format'  => 'string',
			'default' => 'Default title',
		),
		'flag'  => array(
			'format'  => 'boolean',
			'default' => false,
		),
	);
}

$wonderpress_acf_values = array(
	'hero'      => array( 'title' => 'By location' ),
	'hero-main' => array(
		'title' => 'By composition',
		'flag'  => 1,
	),
	'empty-row' => '',
);

// One argument: the composition row id.
$props = wonder_partial_props( 'hero-main' );
assert( array( 'acf' => $wonderpress_acf_values['hero-main'] ) === $props, 'row id resolves the group field' );
assert( array() === $wonderpress_deprecated, 'one argument raises no deprecation' );

$partial = new Props_Probe_Partial( $props );
assert( 'By composition' === $partial->title, 'props hydrate the partial' );
assert( true === $partial->flag, 'ACF 1 coerced to true' );

// One argument: the partial slug, for a location-placed partial.
assert( array( 'acf' => array( 'title' => 'By location' ) ) === wonder_partial_props( 'hero' ), 'slug resolves a location-placed group' );

// No value, or a non-array value, gives no props.
assert( array() === wonder_partial_props( 'empty-row' ), 'empty string value gives no props' );
assert( array() === wonder_partial_props( 'missing' ), 'missing field gives no props' );
assert( array() === wonder_partial_props( '' ), 'empty name gives no props' );
assert( array() === wonder_partial_props( null ), 'null name gives no props' );
assert( array() === $wonderpress_deprecated, 'still no deprecation' );

// Two arguments: the id wins, and the call is reported as deprecated.
$props = wonder_partial_props( 'hero', 'hero-main' );
assert( array( 'acf' => $wonderpress_acf_values['hero-main'] ) === $props, 'two-argument form still resolves the id' );
assert( 1 === count( $wonderpress_deprecated ), 'two-argument form raises one deprecation' );
assert( 'wonder_partial_props' === $wonderpress_deprecated[0][0], 'deprecation names the function' );
assert( '2.3.0' === $wonderpress_deprecated[0][1], 'deprecation names the version' );
assert( false !== strpos( $wonderpress_deprecated[0][2], "wonder_partial_props( 'hero-main' )" ), 'deprecation shows the replacement call' );

// Two arguments with an empty id: the slug is used, nothing is deprecated.
$wonderpress_deprecated = array();
assert( array( 'acf' => array( 'title' => 'By location' ) ) === wonder_partial_props( 'hero', '' ), 'empty second argument falls back to the first' );
assert( array() === $wonderpress_deprecated, 'empty second argument is not deprecated' );

// A non-array `acf` value is ignored by ingestion, without a PHP warning.
$wonderpress_php_notices = array();
foreach ( array( false, null, '', 0, 'text' ) as $bad ) {
	$partial = new Props_Probe_Partial( array( 'acf' => $bad ) );
	assert( 'Default title' === $partial->title, 'non-array acf value leaves defaults' );
}
assert( array() === $wonderpress_php_notices, 'non-array acf value raises no PHP notice: ' . implode( '; ', $wonderpress_php_notices ) );

restore_error_handler();
echo "PARTIAL_PROPS_OK\n";
