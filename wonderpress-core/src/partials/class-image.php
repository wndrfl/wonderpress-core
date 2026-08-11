<?php
/**
 * An Image partial class.
 *
 * @package Wonderpress Core
 */

namespace Wonderpress_Core\Partials;

use Wonderpress_Core\Partials\Abstract_Partial;

defined( 'ABSPATH' ) || exit;

/**
 * Image
 * Wonderpress_Core\Partials\Image
 */
class Image extends Abstract_Partial {

	/**
	 * Whether this partial accepts an ACF parameter for easy hydration.
	 *
	 * @var Boolean $_acf_compatible
	 */
	protected $_acf_compatible = true;

	/**
	 * The view template for this partial. Themes may override it by shipping
	 * a file at this relative path.
	 *
	 * @var String $_partial_template
	 */
	protected $_partial_template = 'partials/image.php';

	/**
	 * A definition of all available properties.
	 *
	 * @var Array $_properties
	 */
	protected static $_properties = array(
		'acf'        => array(
			'description' => 'The ACF array for this partial',
			'format'      => 'array',
			'required'    => false,
		),
		'attributes' => array(
			'description' => 'An array of arbitrary attributes for the DOM element',
			'format'      => 'array',
			'required'    => false,
		),
		'classes'    => array(
			'description' => 'The classes for the image element',
			'default'     => 'theme-image',
			'format'      => 'string|array',
			'required'    => false,
		),
		'alt'        => array(
			'description' => 'Alternative text for the image',
			'format'      => 'string',
			'required'    => false,
		),
		'height'     => array(
			'description' => 'The height of the image (used for attributes only).',
			'format'      => 'string',
			'required'    => false,
		),
		'size'       => array(
			'description' => 'The default WP Image size',
			'default'     => 'large',
			'format'      => 'string',
			'required'    => false,
		),
		'src'        => array(
			'description' => 'The image src attribute',
			'format'      => 'string',
			'required'    => true,
		),
		'srcset'     => array(
			'description' => 'A srcset for a <picture> element',
			'format'      => 'array',
			'required'    => false,
		),
		'width'      => array(
			'description' => 'The width of the image (used for attributes only).',
			'format'      => 'string',
			'required'    => false,
		),
	);

	/**
	 * A method to manipulate $_attrs before attempting to display.
	 *
	 * @return Boolean
	 */
	public function prepare_properties_for_display() {

		// If ACF is provided, we do some more special assignments
		if ( isset( $this->_attrs['acf'] ) && is_array( $this->_attrs['acf'] ) ) {

			$src = $this->get_acf_size_url( $this->size );
			if ( $src ) {
				$this->src = $src;
			}

			$this->srcset = array(
				'1024' => $this->get_acf_size_url( 'banner', $this->src ),
				'768'  => $this->get_acf_size_url( 'large', $this->src ),
				'120'  => $this->get_acf_size_url( 'medium', $this->src ),
				'0'    => $this->get_acf_size_url( 'small', $this->src ),
			);

			$this->width  = isset( $this->_attrs['acf']['width'] ) ? (string) $this->_attrs['acf']['width'] : null;
			$this->height = isset( $this->_attrs['acf']['height'] ) ? (string) $this->_attrs['acf']['height'] : null;
		}

		// Fall back to the attachment's stored alt text, then to an empty
		// alt (correct for decorative images) — never to the image URL.
		if ( is_null( $this->alt ) || false === $this->alt ) {
			$stored_alt = '';
			if ( isset( $this->_attrs['acf']['ID'] ) ) {
				$stored_alt = get_post_meta( (int) $this->_attrs['acf']['ID'], '_wp_attachment_image_alt', true );
			}
			$this->alt = $stored_alt ? $stored_alt : '';
		}

		return true;
	}

	/**
	 * Resolve a size name to a URL from the ACF sizes array, checking the
	 * theme-prefixed registration first, then the unprefixed legacy name.
	 *
	 * @param String $size The unprefixed size name (e.g. 'banner').
	 * @param String $fallback A URL to fall back to when the size is absent.
	 * @return String|null
	 */
	protected function get_acf_size_url( $size, $fallback = null ) {
		$sizes = isset( $this->_attrs['acf']['sizes'] ) && is_array( $this->_attrs['acf']['sizes'] ) ? $this->_attrs['acf']['sizes'] : array();

		if ( ! empty( $sizes[ 'wonderpress-' . $size ] ) ) {
			return $sizes[ 'wonderpress-' . $size ];
		}

		if ( ! empty( $sizes[ $size ] ) ) {
			return $sizes[ $size ];
		}

		return $fallback;
	}
}
