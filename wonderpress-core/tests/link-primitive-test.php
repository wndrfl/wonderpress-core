<?php
/**
 * Pass 2: bundled Link manifest + query param helper.
 *
 * Run: php wonderpress-core/tests/link-primitive-test.php
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

require dirname( __DIR__ ) . '/inc/acf.php';
require dirname( __DIR__ ) . '/inc/link-utils.php';

$manifest_path = dirname( __DIR__ ) . '/manifest/partials/link.json';
$manifest      = json_decode( file_get_contents( $manifest_path ), true );
assert( is_array( $manifest ) && 'link' === $manifest['slug'], 'link manifest readable' );

$fields = wonder_acf_fields_from_properties(
	$manifest['properties'],
	'field_wndr_link'
);
assert( count( $fields ) === count( $manifest['properties'] ), 'all link properties map to ACF fields' );

$url_field = null;
foreach ( $fields as $field ) {
	if ( 'query_params' === $field['name'] ) {
		$url_field = $field;
		break;
	}
}
assert( $url_field && ! empty( $url_field['conditional_logic'] ), 'query_params has conditional_logic' );

$appended = wonder_link_append_query_params( 'https://example.com/path', 'a=1&b=2' );
assert( str_contains( $appended, 'a=1' ) && str_contains( $appended, 'b=2' ), 'query params append' );

$appended_q = wonder_link_append_query_params( 'https://example.com/?x=1', '?a=1' );
assert( str_contains( $appended_q, 'a=1' ), 'query params with leading ?' );

fwrite( STDOUT, "link-primitive-test.php OK\n" );
