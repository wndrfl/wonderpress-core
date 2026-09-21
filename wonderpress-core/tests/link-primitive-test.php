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

require dirname( __DIR__ ) . '/inc/manifest-property.php';
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

assert( 'noopener noreferrer' === wonder_link_merge_rel( true, '' ), 'new-tab rel defaults' );
assert( 'noopener noreferrer nofollow' === wonder_link_merge_rel( true, 'nofollow' ), 'new-tab keeps noopener when merging nofollow' );
assert( 'nofollow' === wonder_link_merge_rel( false, 'nofollow' ), 'same-tab caller rel unchanged' );

assert( 'mailto:hi@example.com' === wonder_link_url_from_acf( array( 'type' => 'email', 'email' => 'hi@example.com' ) ), 'email href' );
assert( 'tel:+15551212' === wonder_link_url_from_acf( array( 'type' => 'telephone', 'telephone' => '+1 555 1212' ) ), 'tel href' );
assert( 'https://example.com' === wonder_link_url_from_acf( array( 'type' => 'url', 'url' => 'https://example.com' ) ), 'url href' );

if ( ! defined( 'WONDERPRESS_CORE_PATH' ) ) {
	define( 'WONDERPRESS_CORE_PATH', dirname( __DIR__ ) . '/' );
}
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
	function wp_kses( $html, $allowed_html ) {
		return $html;
	}
}
if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
	function wp_kses_allowed_html() {
		return array(
			'a' => array(
				'href'   => true,
				'rel'    => true,
				'target' => true,
				'class'  => true,
			),
		);
	}
}

require_once dirname( __DIR__ ) . '/src/partials/class-partial-interface.php';
require_once dirname( __DIR__ ) . '/src/partials/class-abstract-partial.php';
require_once dirname( __DIR__ ) . '/src/partials/class-link.php';

$link = new Wonderpress_Core\Partials\Link(
	array(
		'url'             => 'https://example.com',
		'content'         => 'Example',
		'open_in_new_tab' => true,
		'attributes'      => array(
			'rel' => 'nofollow',
		),
	)
);
$link_html = $link->render( false );
assert( str_contains( $link_html, 'target="_blank"' ), 'new tab target' );
assert( 1 === preg_match_all( '/\brel="/', $link_html ), 'single rel attribute' );
assert( str_contains( $link_html, 'noopener' ) && str_contains( $link_html, 'noreferrer' ) && str_contains( $link_html, 'nofollow' ), 'merged rel tokens' );

fwrite( STDOUT, "link-primitive-test.php OK\n" );
