<?php
/**
 * wonder_partial_class_for_slug() resolves theme partials through the manifest.
 *
 * Core primitives still come from the bundled map. Any other slug with a theme
 * manifest resolves to Wonderpress\Partials\<Name> and its class file is
 * required from artifacts.class, with no filter. The filter still runs last.
 * An unknown slug renders nothing and raises _doing_it_wrong().
 *
 * Run: php wonderpress-core/tests/partial-ref-resolve-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_probe_theme = sys_get_temp_dir() . '/wonderpress-partial-ref-resolve-' . getmypid() . '/';
define( 'WONDERPRESS_CORE_PATH', $wonderpress_probe_theme );

$wonderpress_probe_wrong_calls = array();
$wonderpress_probe_filters     = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong( $function_name, $message ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		global $wonderpress_probe_wrong_calls;
		$wonderpress_probe_wrong_calls[] = $message;
	}
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
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback ) {
		global $wonderpress_probe_filters;
		$wonderpress_probe_filters[ $tag ][] = $callback;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		global $wonderpress_probe_filters;
		$args = array_slice( func_get_args(), 1 );
		foreach ( isset( $wonderpress_probe_filters[ $tag ] ) ? $wonderpress_probe_filters[ $tag ] : array() as $callback ) {
			$args[0] = call_user_func_array( $callback, $args );
		}
		return $args[0];
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
if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory() {
		return rtrim( WONDERPRESS_CORE_PATH, '/' );
	}
}

require_once dirname( __DIR__ ) . '/inc/block-attributes.php';
require_once dirname( __DIR__ ) . '/inc/manifests.php';
require_once dirname( __DIR__ ) . '/inc/manifest-property.php';
require_once dirname( __DIR__ ) . '/inc/acf.php';
require_once dirname( __DIR__ ) . '/inc/template-render.php';
require_once dirname( __DIR__ ) . '/inc/partial-ref.php';
require_once dirname( __DIR__ ) . '/src/partials/class-partial-interface.php';
require_once dirname( __DIR__ ) . '/src/partials/class-abstract-partial.php';

// A theme with one CLI-generated partial: manifest, class file, view.
foreach ( array( '.wonderpress/manifest/partials', 'src/partials', 'partials' ) as $dir ) {
	if ( ! is_dir( $wonderpress_probe_theme . $dir ) ) {
		mkdir( $wonderpress_probe_theme . $dir, 0777, true );
	}
}
file_put_contents(
	$wonderpress_probe_theme . '.wonderpress/manifest/partials/document-grid-item.json',
	wp_json_encode_probe(
		array(
			'name'           => 'Document_Grid_Item',
			'slug'           => 'document-grid-item',
			'acf_compatible' => true,
			'properties'     => array(
				array(
					'name'     => 'title',
					'type'     => 'string',
					'required' => true,
				),
			),
			'artifacts'      => array(
				'class' => 'src/partials/class-document-grid-item.php',
				'view'  => 'partials/document-grid-item.php',
			),
		)
	)
);
file_put_contents(
	$wonderpress_probe_theme . '.wonderpress/manifest/partials/document-grid-item.lock.json',
	'{"not":"a manifest"}'
);
file_put_contents(
	$wonderpress_probe_theme . 'src/partials/class-document-grid-item.php',
	'<?php
namespace Wonderpress\Partials;

class Document_Grid_Item extends \Wonderpress_Core\Partials\Abstract_Partial {
	protected $acf_compatible = true;
	protected $partial_template = \'partials/document-grid-item.php\';
	protected static $properties = array(
		\'acf\'   => array( \'format\' => \'array\' ),
		\'title\' => array( \'format\' => \'string\', \'default\' => \'\' ),
	);
}
'
);
file_put_contents(
	$wonderpress_probe_theme . 'partials/document-grid-item.php',
	'<article class="dgi"><?php echo $title; ?></article>'
);

/**
 * json_encode without the WP wrapper (not loaded here).
 *
 * @param array $data Data.
 * @return string
 */
function wp_json_encode_probe( $data ) {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

// 1. Core primitives still resolve from the bundled map, not the manifest index.
assert( \Wonderpress_Core\Partials\Link::class === wonder_partial_class_for_slug( 'link' ), 'link stays a core primitive' );
assert( \Wonderpress_Core\Partials\Image::class === wonder_partial_class_for_slug( 'image' ), 'image stays a core primitive' );

// 2. A theme slug resolves through its manifest and the class file is required.
assert( ! class_exists( 'Wonderpress\Partials\Document_Grid_Item', false ), 'theme class is not loaded before resolution' );
$resolved = wonder_partial_class_for_slug( 'document-grid-item' );
assert( 'Wonderpress\Partials\Document_Grid_Item' === $resolved, 'theme slug resolves to Wonderpress\Partials\<Name>' );
assert( class_exists( 'Wonderpress\Partials\Document_Grid_Item', false ), 'artifacts.class was required during resolution' );

// 3. The embed helper renders it with no filter hooked.
$html = wonder_render_partial_ref( 'document-grid-item', array( 'title' => 'Quarterly report' ) );
assert( false !== strpos( $html, 'Quarterly report' ), 'theme partial renders through wonder_render_partial_ref' );
assert( array() === $wonderpress_probe_wrong_calls, 'a resolvable slug raises no notice' );

// 4. An empty value renders nothing, quietly.
assert( '' === wonder_render_partial_ref( 'document-grid-item', null ), 'null value renders empty' );
assert( '' === wonder_render_partial_ref( 'document-grid-item', '' ), 'string value renders empty' );
assert( array() === $wonderpress_probe_wrong_calls, 'an empty value raises no notice' );

// 5. An unknown slug renders nothing and says so.
assert( null === wonder_partial_class_for_slug( 'no-such-partial' ), 'unknown slug resolves to null' );
assert( '' === wonder_render_partial_ref( 'no-such-partial', array( 'title' => 'x' ) ), 'unknown slug renders empty' );
assert( 1 === count( $wonderpress_probe_wrong_calls ), 'unknown slug raises exactly one notice' );
assert( false !== strpos( $wonderpress_probe_wrong_calls[0], 'no-such-partial' ), 'notice names the slug' );

// 6. The filter still runs last and can override either path.
add_filter(
	'wonderpress_partial_class_for_slug',
	static function ( $class, $slug ) {
		return 'custom-slug' === $slug ? 'Wonderpress\Partials\Document_Grid_Item' : $class;
	}
);
assert( 'Wonderpress\Partials\Document_Grid_Item' === wonder_partial_class_for_slug( 'custom-slug' ), 'filter maps an unknown slug' );
assert( 'Wonderpress\Partials\Document_Grid_Item' === wonder_partial_class_for_slug( 'document-grid-item' ), 'filter passes manifest result through untouched' );

// Clean up.
foreach ( array(
	'.wonderpress/manifest/partials/document-grid-item.json',
	'.wonderpress/manifest/partials/document-grid-item.lock.json',
	'src/partials/class-document-grid-item.php',
	'partials/document-grid-item.php',
) as $file ) {
	unlink( $wonderpress_probe_theme . $file );
}
foreach ( array( '.wonderpress/manifest/partials', '.wonderpress/manifest', '.wonderpress', 'src/partials', 'src', 'partials' ) as $dir ) {
	rmdir( $wonderpress_probe_theme . $dir );
}
rmdir( $wonderpress_probe_theme );

fwrite( STDOUT, "partial-ref-resolve-test.php OK\n" );
