<?php
/**
 * Smoke test for block attribute normalization.
 *
 * Run from wonderpress-core: php wonderpress-core/tests/block-attributes-test.php
 */

define( 'ABSPATH', '/tmp' );

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( $id ) {
		return (int) $id === 42;
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) {
		return (int) $id === 42 ? 'https://example.test/image.jpg' : false;
	}
}
if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	function wp_get_attachment_metadata( $id ) {
		return (int) $id === 42 ? array( 'width' => 800, 'height' => 600, 'file' => '2026/04/image.jpg' ) : false;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single ) {
		unset( $id, $key, $single );
		return 'Alt text';
	}
}
if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	function wp_get_upload_dir() {
		return array( 'baseurl' => 'https://example.test/wp-content/uploads' );
	}
}

require dirname( __DIR__ ) . '/inc/block-attributes.php';

$from_id = wonder_normalize_image_value( 42 );
assert( is_array( $from_id ) );
assert( 42 === $from_id['ID'] );
assert( 'https://example.test/image.jpg' === $from_id['url'] );
assert( 'Alt text' === $from_id['alt'] );

assert( null === wonder_normalize_image_value( null ) );
assert( null === wonder_normalize_image_value( array() ) );

$from_link = wonder_normalize_link_value(
	array(
		'content'         => 'Read more',
		'url'             => 'https://example.test',
		'open_in_new_tab' => 1,
		'title'           => 'About us',
	)
);
assert( is_array( $from_link ) );
assert( 'Read more' === $from_link['content'] );
assert( true === $from_link['open_in_new_tab'] );

assert( null === wonder_normalize_link_value( array( 'content' => '', 'url' => '', 'title' => '' ) ) );

assert( 42 === wonder_normalize_post_object_value( 42 ) );
assert( 7 === wonder_normalize_post_object_value( array( 'ID' => 7 ) ) );
assert( null === wonder_normalize_post_object_value( null ) );

$repeater_def = array(
	'properties' => array(
		array(
			'name' => 'title',
			'type' => 'string',
		),
		array(
			'name' => 'photo',
			'type' => 'image',
		),
	),
);

$repeater_norm = wonder_normalize_repeater_value(
	array(
		array(
			'title' => ' Hello ',
			'photo' => 42,
		),
		array(
			'title' => '',
			'photo' => null,
		),
	),
	$repeater_def
);
assert( 2 === count( $repeater_norm ) );
assert( ' Hello ' === $repeater_norm[0]['title'] );
assert( is_array( $repeater_norm[0]['photo'] ) );
assert( 42 === $repeater_norm[0]['photo']['ID'] );
assert( array() === wonder_normalize_repeater_value( 'not-array', $repeater_def ) );

echo "block-attributes-test: OK\n";
