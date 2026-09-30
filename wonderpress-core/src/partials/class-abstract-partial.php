<?php
/**
 * An abstract class for WonderPress Partials.
 *
 * @package Wonderpress Core
 */

namespace Wonderpress_Core\Partials;

use Wonderpress_Core\Partials\Partial_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract_Partial
 * Wonderpress_Core\Partials\Abstract_Partial
 */
abstract class Abstract_Partial implements Partial_Interface {

	/**
	 * Whether this partial accepts an ACF parameter for easy hydration.
	 *
	 * @var Boolean $acf_compatible
	 */
	protected $acf_compatible = false;

	/**
	 * All attributes for the template will be stored here.
	 *
	 * @var Array $attrs
	 */
	protected $attrs = array();

	/**
	 * A definition of all available properties.
	 *
	 * @var Array $properties
	 */
	protected static $properties = array();

	/**
	 * A relative path to a partial template to use as the view for this partial.
	 *
	 * @var String|Boolean $partial_template
	 */
	protected $partial_template = null;

	/**
	 * Property map declared on this partial.
	 *
	 * Generated classes used to redeclare $_properties. Prefer a map the
	 * called class declares under the current name, and fall back to that
	 * legacy name so older partials keep hydrating.
	 *
	 * @return Array
	 */
	protected static function property_definitions() {
		$class = get_called_class();
		if ( self::class_declares_property( $class, 'properties' ) ) {
			return static::$properties;
		}
		if ( self::class_declares_property( $class, '_properties' ) ) {
			return static::$_properties;
		}
		return static::$properties;
	}

	/**
	 * Read an instance property a subclass may still declare with a leading underscore.
	 *
	 * @param String $name Current property name, without a leading underscore.
	 * @return Mixed
	 */
	protected function declared_value( $name ) {
		$class = get_class( $this );
		if ( self::class_declares_property( $class, $name ) ) {
			return $this->$name;
		}

		$legacy = '_' . $name;
		if ( self::class_declares_property( $class, $legacy ) ) {
			return $this->$legacy;
		}

		return $this->$name;
	}

	/**
	 * Whether $class_name introduces $property, rather than inheriting it.
	 *
	 * @param String $class_name Class name.
	 * @param String $property   Property name.
	 * @return Boolean
	 */
	private static function class_declares_property( $class_name, $property ) {
		static $cache = array();

		$key = $class_name . "\0" . $property;
		if ( array_key_exists( $key, $cache ) ) {
			return $cache[ $key ];
		}

		if ( ! property_exists( $class_name, $property ) ) {
			$cache[ $key ] = false;
			return false;
		}

		$ref           = new \ReflectionProperty( $class_name, $property );
		$cache[ $key ] = ( $class_name === $ref->getDeclaringClass()->getName() );
		return $cache[ $key ];
	}

	/**
	 * A magic method for how to handle var_dump() of this object.
	 */
	public function __debugInfo() {
		return $this->attrs;
	}

	/**
	 * A magic getter method.
	 *
	 * @param String $property The property to attempt to get.
	 * @throws \Exception If $property is not valid.
	 */
	public function __get( $property ) {
		$definitions = static::property_definitions();
		if ( ! isset( $definitions[ $property ] ) ) {
			throw new \Exception( esc_html( '\'' . $property . '\' is not an allowed property.' ) );
		}

		// Only a true null falls through to the default, so explicitly
		// set values of '', 0 and false are respected.
		if ( array_key_exists( $property, $this->attrs ) && ! is_null( $this->attrs[ $property ] ) ) {
			return $this->attrs[ $property ];
		}

		$default = isset( $definitions[ $property ]['default'] ) ? $definitions[ $property ]['default'] : null;
		return $default;
	}

	/**
	 * Whether a magic property is set, so empty() and isset() call __get.
	 *
	 * Without this, empty( $this->open_in_new_tab ) is always true for a
	 * magic property.
	 *
	 * @param String $property The property to check.
	 * @return Boolean
	 */
	public function __isset( $property ) {
		$definitions = static::property_definitions();
		if ( ! isset( $definitions[ $property ] ) ) {
			return false;
		}

		if ( array_key_exists( $property, $this->attrs ) && ! is_null( $this->attrs[ $property ] ) ) {
			return true;
		}

		return isset( $definitions[ $property ]['default'] );
	}

	/**
	 * A magic setter method.
	 *
	 * @param String $property The property to attempt to set.
	 * @param Mixed  $value The value of the property to set.
	 * @throws \Exception If $property is not valid.
	 * @return void
	 */
	public function __set( $property, $value ) {
		$definitions = static::property_definitions();
		if ( ! isset( $definitions[ $property ] ) ) {
			throw new \Exception( esc_html( '\'' . $property . '\' is not an allowed property.' ) );
		}

		// Format validation happens in get_invalid_properties(), which
		// render() consults before output.
		$this->attrs[ $property ] = $value;
	}

	/**
	 * A magic method to handle outputting this object as a string.
	 *
	 * @return String
	 */
	public function __toString() {
		return $this->render( false );
	}

	/**
	 * Constructor
	 *
	 * @param Array $params An array of values to populate the partial snippet.
	 * @return void
	 */
	public function __construct( array $params = array() ) {
		// Only assign properties that were actually supplied, so unsupplied
		// properties fall through to their declared defaults in __get().
		foreach ( static::property_definitions() as $name => $config ) {
			if ( array_key_exists( $name, $params ) ) {
				$this->$name = $params[ $name ];
			}
		}

		$this->attempt_acf_ingestion( $params );
	}

	/**
	 * A method to attempt to use a provided ACF field to hydrate various properties.
	 *
	 * @param Array $params The parameters passed into the class.
	 *
	 * @return Boolean
	 */
	public function attempt_acf_ingestion( array $params = array() ) {
		if ( ! $this->declared_value( 'acf_compatible' ) || ! isset( $params['acf'] ) ) {
			return;
		}

		foreach ( static::property_definitions() as $property_key => $property_config ) {

			if ( 'acf' === $property_key ) {
				continue;
			}

			foreach ( $params['acf'] as $acf_key => $acf_value ) {
				if ( $acf_key === $property_key ) {
					$this->$property_key = $acf_value;
					break;
				}
			}
		}

		$this->coerce_boolean_properties_from_acf();
	}

	/**
	 * ACF and block storage often use 0/1 instead of booleans; normalize for validation.
	 *
	 * @return void
	 */
	protected function coerce_boolean_properties_from_acf() {
		if ( ! function_exists( 'wonder_normalize_boolean_value' ) ) {
			return;
		}

		foreach ( static::property_definitions() as $property_key => $property_config ) {
			if ( 'acf' === $property_key || ! isset( $property_config['format'] ) ) {
				continue;
			}

			$formats = explode( '|', (string) $property_config['format'] );
			if ( ! in_array( 'boolean', $formats, true ) && ! in_array( 'bool', $formats, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $property_key, $this->attrs ) ) {
				continue;
			}

			$coerced = wonder_normalize_boolean_value( $this->attrs[ $property_key ] );
			if ( null !== $coerced ) {
				$this->attrs[ $property_key ] = $coerced;
			}
		}
	}

	/**
	 * Compress an HTML string to remove extra whitespaces.
	 *
	 * @param String $html An html string to compress.
	 * @return String
	 */
	public static function compress_html( $html ) {
		// Collapse to a single space (never ''), so attributes separated
		// only by whitespace do not fuse together.
		$html = preg_replace( '/[\n\t]+/S', ' ', $html );
		return $html;
	}

	/**
	 * Outputs an example code snippet for how to use this partial.
	 *
	 * @return void
	 */
	public static function example() {
		echo '<pre>';
		echo esc_html( htmlspecialchars( static::example_snippet() ) );
		echo '</pre>';
	}

	/**
	 * Outputs an example code snippet for how to use this partial.
	 *
	 * @return void
	 */
	public static function example_snippet() {

		$params_str = '';
		foreach ( static::property_definitions() as $name => $config ) {
			$params_str .= '\'' . $name . '\' => \'' . ( isset( $config['description'] ) ? $config['description'] : '' ) . '\'' . "\n";
		}

		echo '$element = new ' . get_called_class() . '( array( ' . "\n" . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		"\t" . $params_str . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		') );' . "\n" .
		'$element->render();';
	}

	/**
	 * Outputs an explanation of each property available for this partial.
	 *
	 * @return void
	 */
	public static function explain() {
		echo '<pre>';
		var_dump( static::property_definitions() );
		echo '</pre>';
	}

	/**
	 * Gathers any properties that do not match configuration constraints.
	 *
	 * @return Array
	 */
	public function get_invalid_properties() {

		$invalid_properties = array();
		$definitions        = static::property_definitions();

		foreach ( $definitions as $key => $config ) {
			if ( isset( $config['required'] ) && $config['required'] && is_null( $this->$key ) && ! isset( $config['default'] ) ) {
				$invalid_properties[ $key ] = $config;
				continue;
			}

			if ( ! is_null( $this->$key ) && isset( $config['format'] ) ) {
				$format_parts = explode( '|', $config['format'] );
				$is_valid     = false;
				foreach ( $format_parts as $format ) {
					switch ( $format ) {
						case 'array':
							$is_valid = is_array( $this->$key ) || ( is_bool( $this->$key ) && ! $this->$key );
							break;
						case 'bool':
						case 'boolean':
							$is_valid = is_bool( $this->$key );
							break;
						case 'object':
							$is_valid = is_object( $this->$key ) || ( is_bool( $this->$key ) && ! $this->$key );
							break;
						case 'string':
							$is_valid = is_string( $this->$key ) || ( is_bool( $this->$key ) && ! $this->$key );
							break;
						case 'integer':
						case 'int':
							$is_valid = is_int( $this->$key ) || ( is_numeric( $this->$key ) && ! is_float( $this->$key + 0 ) );
							break;
					}

					if ( $is_valid ) {
						break;
					}
				}

				if ( ! $is_valid ) {
					$invalid_properties[ $key ] = $config;
					continue;
				}
			}
		}

		return $invalid_properties;
	}

	/**
	 * Outputs the current contents of this partial's settings.
	 *
	 * @return void
	 */
	public function inspect() {

		$properties = array();

		foreach ( static::property_definitions() as $key => $config ) {
			$properties[ $key ] = ! is_null( $this->$key ) ? $this->$key : null;
		}

		echo '<pre>';
		var_dump( $properties );
		echo '</pre>';
	}

	/**
	 * Determines whether this instatiation is currently valid for output.
	 *
	 * @return Boolean
	 */
	public function is_valid() {
		return ( $this->get_invalid_properties() ) ? false : true;
	}

	/**
	 * A method to manipulate $attrs before attempting to display.
	 *
	 * @return Boolean
	 */
	public function prepare_properties_for_display() {
		return true;
	}

	/**
	 * Build and render the HTML for this partial.
	 *
	 * @param Boolean $echo Whether or not to echo the HTML or simply return it.
	 * @throws \Exception If the render_into_template method doesn't exist.
	 * @return String|Boolean
	 */
	public function render( $echo = true ) {
		if ( ! method_exists( $this, 'render_into_template' ) ) {
			throw new \Exception( 'No template provided for this partial.' );
		}

		$this->prepare_properties_for_display();

		$invalid_properties = $this->get_invalid_properties();
		if ( $invalid_properties ) {
			throw new \Exception( esc_html( 'Partial is invalid, missing or invalid value for property: ' . array_key_first( $invalid_properties ) ) );
		}

		$html = '';
		ob_start();

		$this->render_into_template();

		$html = ob_get_contents();
		ob_end_clean();

		$html = static::compress_html( $html );

		$allowed_tags = static::allowed_html();

		if ( ! $echo ) {
			return wp_kses( $html, $allowed_tags );
		}

		echo wp_kses( $html, $allowed_tags );

		return true;
	}

	/**
	 * Allowed HTML for partial output.
	 *
	 * Extends the post kses set with SVG, picture, and img attributes WordPress
	 * still omits from `wp_kses_allowed_html( 'post' )` (decoding, srcset,
	 * sizes, fetchpriority).
	 *
	 * @return Array
	 */
	public static function allowed_html() {
		$allowed_tags = array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'circle'  => array(
					'cx'           => array(),
					'cy'           => array(),
					'fill'         => array(),
					'r'            => array(),
					'stroke'       => array(),
					'stroke-width' => array(),
				),
				'picture' => array(),
				'source'  => array(
					'media'  => array(),
					'sizes'  => array(),
					'srcset' => array(),
					'type'   => array(),
				),
				'svg'     => array(
					'class'           => array(),
					'aria-hidden'     => array(),
					'aria-labelledby' => array(),
					'role'            => array(),
					'xmlns'           => array(),
					'width'           => array(),
					'height'          => array(),
					'viewbox'         => array(), // <= Must be lower case!
				),
				'line'    => array(
					'x1'     => array(),
					'y1'     => array(),
					'x2'     => array(),
					'y2'     => array(),
					'stroke' => array(),
				),
				'g'       => array( 'fill' => array() ),
				'title'   => array( 'title' => array() ),
				'path'    => array(
					'd'            => array(),
					'fill'         => array(),
					'stroke'       => array(),
					'stroke-width' => array(),
				),
			)
		);

		$img_correctness = array(
			'decoding'      => true,
			'fetchpriority' => true,
			'loading'       => true,
			'sizes'         => true,
			'srcset'        => true,
		);

		$allowed_tags['img'] = array_merge(
			isset( $allowed_tags['img'] ) && is_array( $allowed_tags['img'] ) ? $allowed_tags['img'] : array(),
			$img_correctness
		);

		return $allowed_tags;
	}

	/**
	 * An internal process to merge the property values and HTML bits into a
	 * usable HTML snippet.
	 *
	 * The theme may override the view by shipping a file at the same relative
	 * path as $partial_template; otherwise the plugin's copy is used.
	 *
	 * @throws \Exception If there is no configured partial template.
	 *
	 * @return void
	 */
	public function render_into_template() {
		$template = $this->declared_value( 'partial_template' );
		if ( ! $template ) {
			throw new \Exception( 'A partial template has not been provided.' );
		}

		// Prefer a theme override, then fall back to the plugin's template.
		$_template_path = locate_template( $template );
		if ( ! $_template_path && defined( 'WONDERPRESS_CORE_PATH' ) && file_exists( WONDERPRESS_CORE_PATH . $template ) ) {
			$_template_path = WONDERPRESS_CORE_PATH . $template;
		}

		if ( ! $_template_path ) {
			throw new \Exception( esc_html( 'Partial template could not be located: ' . $template ) );
		}

		// Expose each declared property to the template through its getter,
		// so declared defaults apply to unsupplied properties.
		foreach ( static::property_definitions() as $_property_name => $_property_config ) {
			${ $_property_name } = $this->{ $_property_name };
		}

		include $_template_path;
	}
}
