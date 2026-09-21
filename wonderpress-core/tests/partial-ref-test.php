<?php
/**
 * Partial property embeds (type partial → link primitive).
 *
 * Run: php wonderpress-core/tests/partial-ref-test.php
 */

define( 'ABSPATH', '/tmp' );

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
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

require dirname( __DIR__ ) . '/inc/manifests.php';
require dirname( __DIR__ ) . '/inc/manifest-property.php';
require dirname( __DIR__ ) . '/inc/acf.php';

$link_manifest    = json_decode(
	file_get_contents( dirname( __DIR__ ) . '/manifest/partials/link.json' ),
	true
);
$link_field_count = count( $link_manifest['properties'] );

$hero_props = array(
	array(
		'name'     => 'headline',
		'type'     => 'string',
		'required' => true,
	),
	array(
		'name'     => 'cta',
		'type'     => 'partial',
		'partial'  => 'link',
		'label'    => 'Call to action',
		'required' => false,
	),
);

$fields = wonder_acf_fields_from_properties( $hero_props, 'field_wndr_hero' );
assert( 2 === count( $fields ), 'hero has headline + cta fields' );

$cta = $fields[1];
assert( 'group' === $cta['type'] && 'cta' === $cta['name'], 'cta is a group' );
assert( count( $cta['sub_fields'] ) === $link_field_count, 'cta embeds full link manifest fields' );

$type_field = null;
foreach ( $cta['sub_fields'] as $sub ) {
	if ( 'type' === $sub['name'] ) {
		$type_field = $sub;
		break;
	}
}
assert( $type_field && 'select' === $type_field['type'], 'embedded link includes type select' );

fwrite( STDOUT, "partial-ref-test.php OK\n" );
