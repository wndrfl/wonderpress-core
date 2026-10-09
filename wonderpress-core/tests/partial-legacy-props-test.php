<?php
/**
 * Abstract_Partial reads property maps under either name.
 *
 * Generated classes used to declare $_properties, $_acf_compatible and
 * $_partial_template. Current ones declare $properties, $acf_compatible and
 * $partial_template. Both have to hydrate, ingest ACF and render.
 *
 * Run: php wonderpress-core/tests/partial-legacy-props-test.php
 */

define( 'ABSPATH', '/tmp' );

$wonderpress_probe_root = sys_get_temp_dir() . '/wonderpress-partial-legacy-' . getmypid() . '/';
define( 'WONDERPRESS_CORE_PATH', $wonderpress_probe_root );

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

require_once dirname( __DIR__ ) . '/inc/block-attributes.php';
require_once dirname( __DIR__ ) . '/src/partials/class-partial-interface.php';
require_once dirname( __DIR__ ) . '/src/partials/class-abstract-partial.php';

use Wonderpress_Core\Partials\Abstract_Partial;

if ( ! is_dir( WONDERPRESS_CORE_PATH . 'partials' ) ) {
	mkdir( WONDERPRESS_CORE_PATH . 'partials', 0777, true );
}
file_put_contents(
	WONDERPRESS_CORE_PATH . 'partials/probe.php',
	'<?php echo $title . "|" . ( $flag ? "1" : "0" );'
);

/**
 * A partial still declaring the underscore-prefixed properties.
 */
class Legacy_Named_Partial extends Abstract_Partial {
	/**
	 * @var Boolean
	 */
	protected $_acf_compatible = true;

	/**
	 * @var String
	 */
	protected $_partial_template = 'partials/probe.php';

	/**
	 * @var Array
	 */
	protected static $_properties = array(
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

/**
 * A partial declaring the current property names.
 */
class Current_Named_Partial extends Abstract_Partial {
	/**
	 * @var Boolean
	 */
	protected $acf_compatible = true;

	/**
	 * @var String
	 */
	protected $partial_template = 'partials/probe.php';

	/**
	 * @var Array
	 */
	protected static $properties = array(
		'acf'   => array(
			'format' => 'array',
		),
		'title' => array(
			'format'  => 'string',
			'default' => 'Current default',
		),
		'flag'  => array(
			'format'  => 'boolean',
			'default' => false,
		),
	);
}

/**
 * Declares both maps. The current name wins.
 */
class Both_Named_Partial extends Abstract_Partial {
	/**
	 * @var Boolean
	 */
	protected $_acf_compatible = false;

	/**
	 * @var Boolean
	 */
	protected $acf_compatible = true;

	/**
	 * @var String
	 */
	protected $partial_template = 'partials/probe.php';

	/**
	 * @var Array
	 */
	protected static $_properties = array(
		'stale' => array(
			'format'  => 'string',
			'default' => 'stale',
		),
	);

	/**
	 * @var Array
	 */
	protected static $properties = array(
		'title' => array(
			'format'  => 'string',
			'default' => 'current',
		),
		'flag'  => array(
			'format'  => 'boolean',
			'default' => false,
		),
	);
}

/**
 * Not ACF-compatible, still on the legacy flag name.
 */
class Legacy_Plain_Partial extends Abstract_Partial {
	/**
	 * @var Boolean
	 */
	protected $_acf_compatible = false;

	/**
	 * @var String
	 */
	protected $_partial_template = 'partials/probe.php';

	/**
	 * @var Array
	 */
	protected static $_properties = array(
		'title' => array(
			'format'  => 'string',
			'default' => 'plain',
		),
		'flag'  => array(
			'format'  => 'boolean',
			'default' => false,
		),
	);
}

/**
 * @param Object $partial Hydrated partial.
 * @param String $title   Expected title.
 * @param String $label   Assertion label.
 */
function wonderpress_assert_probe( $partial, $title, $label ) {
	$html = $partial->render( false );
	assert( $title . '|1' === $html, $label . " rendered {$html}" );
}

$legacy = new Legacy_Named_Partial( array( 'title' => 'Hi' ) );
assert( 'Hi' === $legacy->title, 'legacy supplied value' );
assert( false === $legacy->flag, 'legacy default' );

$from_acf = new Legacy_Named_Partial(
	array(
		'acf' => array(
			'title' => 'From ACF',
			'flag'  => 1,
		),
	)
);
assert( 'From ACF' === $from_acf->title, 'legacy ACF ingestion' );
assert( true === $from_acf->flag, 'legacy ACF boolean coerced from 1' );
wonderpress_assert_probe( $from_acf, 'From ACF', 'legacy' );

$current = new Current_Named_Partial(
	array(
		'acf' => array(
			'title' => 'Current',
			'flag'  => '0',
		),
	)
);
assert( 'Current' === $current->title, 'current ACF ingestion' );
assert( false === $current->flag, 'current ACF boolean coerced from "0"' );
$current->flag = true;
wonderpress_assert_probe( $current, 'Current', 'current' );

$bare = new Current_Named_Partial( array() );
assert( 'Current default' === $bare->title, 'current default when nothing supplied' );

$both = new Both_Named_Partial(
	array(
		'acf' => array(
			'title' => 'Preferred',
			'flag'  => 1,
		),
	)
);
assert( 'Preferred' === $both->title, 'class declaring both maps uses $properties' );
$threw_on_stale = false;
try {
	$both->stale;
} catch ( \Exception $e ) {
	$threw_on_stale = true;
}
assert( $threw_on_stale, 'legacy map is ignored when $properties is declared' );

$plain = new Legacy_Plain_Partial(
	array(
		'acf' => array(
			'title' => 'Should not apply',
		),
	)
);
assert( 'plain' === $plain->title, 'legacy acf_compatible false skips ingestion' );

foreach ( array( $legacy, $current ) as $partial ) {
	$threw = false;
	try {
		$partial->nope = 'x';
	} catch ( \Exception $e ) {
		$threw = true;
	}
	assert( $threw, 'unknown property is rejected' );
}

$missing = new class() extends Abstract_Partial {};
$threw_on_render = false;
try {
	$missing->render( false );
} catch ( \Exception $e ) {
	$threw_on_render = str_contains( $e->getMessage(), 'partial template has not been provided' );
}
assert( $threw_on_render, 'no declared template throws' );

@unlink( WONDERPRESS_CORE_PATH . 'partials/probe.php' );
@rmdir( WONDERPRESS_CORE_PATH . 'partials' );
@rmdir( rtrim( WONDERPRESS_CORE_PATH, '/' ) );

echo "PARTIAL_LEGACY_PROPS_OK\n";
