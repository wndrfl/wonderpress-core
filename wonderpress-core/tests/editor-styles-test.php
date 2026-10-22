<?php
/**
 * Smoke test for the block editor canvas stylesheet.
 *
 * Run from wonderpress-core: php wonderpress-core/tests/editor-styles-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_test_is_admin      = true;
$wonderpress_test_should_load   = false;
$wonderpress_test_filters       = array();
$wonderpress_test_post          = null;
$wonderpress_test_template_slug = '';
$wonderpress_test_enqueued      = array();
$wonderpress_test_bridged       = array();
$wonderpress_test_theme_dir     = sys_get_temp_dir() . '/wonderpress-editor-styles-' . getmypid();

// phpcs:disable Generic.Classes.DuplicateClassName.Found, Squiz.Commenting
class WP_Post {
	public $ID        = 1;
	public $post_type = 'post';
}

function add_action() {}

function apply_filters( $tag, $value ) {
	global $wonderpress_test_filters;

	if ( array_key_exists( $tag, $wonderpress_test_filters ) ) {
		return $wonderpress_test_filters[ $tag ];
	}

	return $value;
}

function get_page_template_slug( $post ) {
	global $wonderpress_test_template_slug;

	return $wonderpress_test_template_slug;
}

function is_admin() {
	global $wonderpress_test_is_admin;

	return $wonderpress_test_is_admin;
}

function wp_should_load_block_editor_scripts_and_styles() {
	global $wonderpress_test_should_load;

	return $wonderpress_test_should_load;
}

function get_post() {
	global $wonderpress_test_post;

	return $wonderpress_test_post;
}

function get_template_directory() {
	global $wonderpress_test_theme_dir;

	return $wonderpress_test_theme_dir;
}

function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/wonderpress';
}

function wp_enqueue_style( $handle, $src, $deps, $ver ) {
	global $wonderpress_test_enqueued;

	$wonderpress_test_enqueued[] = array(
		'handle' => $handle,
		'src'    => $src,
		'ver'    => $ver,
	);
}

function wonder_enqueue_token_bridge( $handle ) {
	global $wonderpress_test_bridged;

	$wonderpress_test_bridged[] = $handle;
}
// phpcs:enable

require_once dirname( __DIR__ ) . '/inc/assets.php';

$page            = new WP_Post();
$page->post_type = 'page';
$post            = new WP_Post();

// Candidate resolution.
assert( array( '/static/dist/css/global.css' ) === wonder_editor_asset_candidates( null ) );
assert( array( '/static/dist/css/single.css', '/static/dist/css/global.css' ) === wonder_editor_asset_candidates( $post ) );
assert( array( '/static/dist/css/page.css', '/static/dist/css/global.css' ) === wonder_editor_asset_candidates( $page ) );

$wonderpress_test_template_slug = 'template-field-types-demo.php';
assert(
	array( '/static/dist/css/field-types-demo.css', '/static/dist/css/page.css', '/static/dist/css/global.css' )
	=== wonder_editor_asset_candidates( $page )
);

// A template in a subdirectory, and one without the `template-` prefix.
$wonderpress_test_template_slug = 'templates/template-landing.php';
assert( '/static/dist/css/landing.css' === wonder_editor_asset_candidates( $page )[0] );
$wonderpress_test_template_slug = 'about.php';
assert( '/static/dist/css/about.css' === wonder_editor_asset_candidates( $page )[0] );

// A template whose body id matches the post-type bundle is not listed twice.
$wonderpress_test_template_slug = 'template-page.php';
assert( array( '/static/dist/css/page.css', '/static/dist/css/global.css' ) === wonder_editor_asset_candidates( $page ) );

// The filter replaces the list.
$wonderpress_test_filters['wonderpress_editor_asset_candidates'] = array( '/assets/editor.css' );
assert( array( '/assets/editor.css' ) === wonder_editor_asset_candidates( $page ) );
unset( $wonderpress_test_filters['wonderpress_editor_asset_candidates'] );
$wonderpress_test_template_slug = '';

// Enqueueing: build a fake theme with a page bundle only.
$css_dir = $wonderpress_test_theme_dir . '/static/dist/css';
mkdir( $css_dir, 0777, true );
file_put_contents( $css_dir . '/page.css', 'body{}' );
$wonderpress_test_post = $page;

// Front end: never.
$wonderpress_test_is_admin = false;
wonder_enqueue_editor_styles();
assert( array() === $wonderpress_test_enqueued );
$wonderpress_test_is_admin = true;

// The admin document's own pass: never — this is what would restyle wp-admin.
$wonderpress_test_should_load = true;
wonder_enqueue_editor_styles();
assert( array() === $wonderpress_test_enqueued );
$wonderpress_test_should_load = false;

// Opted out: never.
$wonderpress_test_filters['wonderpress_editor_styles'] = false;
wonder_enqueue_editor_styles();
assert( array() === $wonderpress_test_enqueued );
unset( $wonderpress_test_filters['wonderpress_editor_styles'] );

// The iframe's pass: the first bundle that exists, with the token bridge on it.
wonder_enqueue_editor_styles();
assert( 1 === count( $wonderpress_test_enqueued ) );
assert( 'wonderpress-editor-canvas' === $wonderpress_test_enqueued[0]['handle'] );
assert( 'https://example.test/wp-content/themes/wonderpress/static/dist/css/page.css' === $wonderpress_test_enqueued[0]['src'] );
assert( (string) filemtime( $css_dir . '/page.css' ) === $wonderpress_test_enqueued[0]['ver'] );
assert( array( 'wonderpress-editor-canvas' ) === $wonderpress_test_bridged );

// A template bundle wins over the post-type bundle once it is built.
file_put_contents( $css_dir . '/field-types-demo.css', 'body{}' );
$wonderpress_test_template_slug = 'template-field-types-demo.php';
wonder_enqueue_editor_styles();
assert( 2 === count( $wonderpress_test_enqueued ) );
assert( false !== strpos( $wonderpress_test_enqueued[1]['src'], '/static/dist/css/field-types-demo.css' ) );
$wonderpress_test_template_slug = '';

// A post with no single or global bundle: nothing to load, nothing enqueued.
$wonderpress_test_post = $post;
wonder_enqueue_editor_styles();
assert( 2 === count( $wonderpress_test_enqueued ) );

unlink( $css_dir . '/page.css' );
unlink( $css_dir . '/field-types-demo.css' );
rmdir( $css_dir );
rmdir( dirname( $css_dir ) );
rmdir( dirname( $css_dir, 2 ) );
rmdir( $wonderpress_test_theme_dir );

echo "editor-styles-test.php OK\n";
