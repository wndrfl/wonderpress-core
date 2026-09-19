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

echo "block-attributes-test: OK\n";
