<?php
/**
 * Image primitive: decoding, dims, native srcset vs picture, kses attrs.
 *
 * Run: php wonderpress-core/tests/image-primitive-test.php
 */

define( 'ABSPATH', '/tmp' );
define( 'WONDERPRESS_CORE_PATH', dirname( __DIR__ ) . '/' );

$wonderpress_test_attachment_srcset = 'https://example.com/large.jpg 1024w, https://example.com/medium.jpg 300w';
$wonderpress_test_attachment_sizes  = '(max-width: 1024px) 100vw, 1024px';
$wonderpress_test_attachment_alt    = 'Stored alt';

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template() {
		return '';
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $html, $allowed_html ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $html;
	}
}
if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
	function wp_kses_allowed_html() {
		return array(
			'img' => array(
				'alt'     => true,
				'src'     => true,
				'width'   => true,
				'height'  => true,
				'loading' => true,
			),
			'a'   => array(
				'href'   => true,
				'rel'    => true,
				'target' => true,
			),
		);
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta() {
		global $wonderpress_test_attachment_alt;
		return $wonderpress_test_attachment_alt;
	}
}
if ( ! function_exists( 'wp_get_attachment_image_srcset' ) ) {
	function wp_get_attachment_image_srcset() {
		global $wonderpress_test_attachment_srcset;
		return $wonderpress_test_attachment_srcset;
	}
}
if ( ! function_exists( 'wp_get_attachment_image_sizes' ) ) {
	function wp_get_attachment_image_sizes() {
		global $wonderpress_test_attachment_sizes;
		return $wonderpress_test_attachment_sizes;
	}
}

require_once dirname( __DIR__ ) . '/src/partials/class-partial-interface.php';
require_once dirname( __DIR__ ) . '/src/partials/class-abstract-partial.php';
require_once dirname( __DIR__ ) . '/src/partials/class-image.php';

use Wonderpress_Core\Partials\Abstract_Partial;
use Wonderpress_Core\Partials\Image;

$allowed = Abstract_Partial::allowed_html();
assert( ! empty( $allowed['img']['decoding'] ), 'kses allows decoding' );
assert( ! empty( $allowed['img']['srcset'] ), 'kses allows srcset' );
assert( ! empty( $allowed['img']['sizes'] ), 'kses allows sizes' );
assert( ! empty( $allowed['img']['fetchpriority'] ), 'kses allows fetchpriority' );

$plain = new Image(
	array(
		'src'    => 'https://example.com/photo.jpg',
		'width'  => '800',
		'height' => '600',
		'alt'    => '',
	)
);
$html = $plain->render( false );
assert( str_contains( $html, 'loading="lazy"' ), 'default loading lazy' );
assert( str_contains( $html, 'decoding="async"' ), 'default decoding async' );
assert( str_contains( $html, 'width="800"' ) && str_contains( $html, 'height="600"' ), 'emits supplied dimensions' );
assert( ! str_contains( $html, '<picture>' ), 'no picture without art-direction srcset' );
assert( str_contains( $html, 'alt=""' ), 'empty alt for decorative' );

$lcp = new Image(
	array(
		'src'        => 'https://example.com/hero.jpg',
		'width'      => '1600',
		'height'     => '900',
		'alt'        => 'Hero',
		'attributes' => array(
			'loading'       => 'eager',
			'decoding'      => 'sync',
			'fetchpriority' => 'high',
		),
	)
);
$lcp_html = $lcp->render( false );
assert( str_contains( $lcp_html, 'loading="eager"' ), 'loading override' );
assert( ! str_contains( $lcp_html, 'loading="lazy"' ), 'lazy suppressed when overridden' );
assert( str_contains( $lcp_html, 'decoding="sync"' ), 'decoding override' );
assert( str_contains( $lcp_html, 'fetchpriority="high"' ), 'fetchpriority via attributes' );

$picture = new Image(
	array(
		'src'    => 'https://example.com/small.jpg',
		'srcset' => array(
			'1024' => 'https://example.com/wide.jpg',
			'0'    => 'https://example.com/small.jpg',
		),
		'alt'    => 'Art',
	)
);
$picture_html = $picture->render( false );
assert( str_contains( $picture_html, '<picture>' ), 'array srcset is art direction' );
assert( str_contains( $picture_html, 'media="(min-width:1024px)"' ), 'picture source media' );

$native = new Image(
	array(
		'src'    => 'https://example.com/photo.jpg',
		'srcset' => 'https://example.com/photo.jpg 800w, https://example.com/photo-300.jpg 300w',
		'sizes'  => '100vw',
		'alt'    => 'Native',
	)
);
$native_html = $native->render( false );
assert( ! str_contains( $native_html, '<picture>' ), 'string srcset is not picture' );
assert( str_contains( $native_html, 'srcset="' ), 'native srcset attribute' );
assert( str_contains( $native_html, 'sizes="100vw"' ), 'sizes attribute' );

$acf = new Image(
	array(
		'acf' => array(
			'ID'     => 12,
			'url'    => 'https://example.com/full.jpg',
			'width'  => 2400,
			'height' => 1600,
			'sizes'  => array(
				'large'        => 'https://example.com/large.jpg',
				'large-width'  => 1024,
				'large-height' => 683,
			),
		),
	)
);
$acf_html = $acf->render( false );
assert( str_contains( $acf_html, 'https://example.com/large.jpg' ), 'ACF uses size URL' );
assert( ! str_contains( $acf_html, '<picture>' ), 'ACF default is not picture' );
assert( str_contains( $acf_html, 'srcset="' ), 'ACF native srcset' );
assert( str_contains( $acf_html, 'width="1024"' ) && str_contains( $acf_html, 'height="683"' ), 'ACF size dimensions' );
assert( str_contains( $acf_html, 'alt="Stored alt"' ), 'ACF falls back to stored alt' );

fwrite( STDOUT, "image-primitive-test.php OK\n" );
