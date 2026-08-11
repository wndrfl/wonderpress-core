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
	 * @var Boolean $_acf_compatible
	 */
	protected $_acf_compatible = false;

	/**
	 * All attributes for the template will be stored here.
	 *
	 * @var Array $_attrs
	 */
	protected $_attrs = array();

	/**
	 * A definition of all available properties.
	 *
	 * @var Array $_properties
	 */
	protected static $_properties = array();

	/**
	 * A relative path to a partial template to use as the view for this partial.
	 *
	 * @var String|Boolean $_partial_template
	 */
	protected $_partial_template = null;

	/**
	 * A magic method for how to handle var_dump() of this object.
	 */
	public function __debugInfo() {
		return $this->_attrs;
	}

	/**
	 * A magic getter method.
	 *
	 * @param String $property The property to attempt to get.
	 * @throws \Exception If $property is not valid.
	 */
	public function __get( $property ) {
		if ( ! property_exists( get_called_class(), '_properties' ) || ! isset( static::$_properties[ $property ] ) ) {
			throw new \Exception( esc_html( '\'' . $property . '\' is not an allowed property.' ) );
		}

		// Only a true null falls through to the default, so explicitly
		// set values of '', 0 and false are respected.
		if ( array_key_exists( $property, $this->_attrs ) && ! is_null( $this->_attrs[ $property ] ) ) {
			return $this->_attrs[ $property ];
		}

		$default = isset( static::$_properties[ $property ]['default'] ) ? static::$_properties[ $property ]['default'] : null;
		return $default;
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
		if ( ! property_exists( get_called_class(), '_properties' ) || ! isset( static::$_properties[ $property ] ) ) {
			throw new \Exception( esc_html( '\'' . $property . '\' is not an allowed property.' ) );
		}

		// Format validation happens in get_invalid_properties(), which
		// render() consults before output.
		$this->_attrs[ $property ] = $value;
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
		foreach ( static::$_properties as $name => $config ) {
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
		if ( ! $this->_acf_compatible || ! isset( $params['acf'] ) ) {
			return;
		}

		foreach ( static::$_properties as $property_key => $property_config ) {

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
		foreach ( static::$_properties as $name => $config ) {
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
		var_dump( static::$_properties );
		echo '</pre>';
	}

	/**
	 * Gathers any properties that do not match configuration constraints.
	 *
	 * @return Array
	 */
	public function get_invalid_properties() {

		$invalid_properties = array();

		if ( ! isset( static::$_properties ) ) {
			return $invalid_properties;
		}

		foreach ( static::$_properties as $key => $config ) {
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

		foreach ( static::$_properties as $key => $config ) {
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
	 * A method to manipulate $_attrs before attempting to display.
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
					'srcset' => array(),
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

		if ( ! $echo ) {
			return wp_kses( $html, $allowed_tags );
		}

		echo wp_kses( $html, $allowed_tags );

		return true;
	}

	/**
	 * An internal process to merge the property values and HTML bits into a
	 * usable HTML snippet.
	 *
	 * The theme may override the view by shipping a file at the same relative
	 * path as $_partial_template; otherwise the plugin's copy is used.
	 *
	 * @throws \Exception If there is no configured partial template.
	 *
	 * @return void
	 */
	public function render_into_template() {
		if ( ! property_exists( $this, '_partial_template' ) || ! $this->_partial_template ) {
			throw new \Exception( 'A partial template has not been provided.' );
		}

		// Prefer a theme override, then fall back to the plugin's template.
		$_template_path = locate_template( $this->_partial_template );
		if ( ! $_template_path && defined( 'WONDERPRESS_CORE_PATH' ) && file_exists( WONDERPRESS_CORE_PATH . $this->_partial_template ) ) {
			$_template_path = WONDERPRESS_CORE_PATH . $this->_partial_template;
		}

		if ( ! $_template_path ) {
			throw new \Exception( esc_html( 'Partial template could not be located: ' . $this->_partial_template ) );
		}

		// Expose each declared property to the template through its getter,
		// so declared defaults apply to unsupplied properties.
		foreach ( static::$_properties as $_property_name => $_property_config ) {
			${ $_property_name } = $this->{ $_property_name };
		}

		include $_template_path;
	}
}
