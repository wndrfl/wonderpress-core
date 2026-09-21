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
		'decoding'   => array(
			'description' => 'The img decoding attribute (async, sync, or auto)',
			'default'     => 'async',
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
		'sizes'      => array(
			'description' => 'The sizes attribute for a responsive <img>',
			'format'      => 'string',
			'required'    => false,
		),
		'src'        => array(
			'description' => 'The image src attribute',
			'format'      => 'string',
			'required'    => true,
		),
		'srcset'     => array(
			'description' => 'A native srcset string, or an art-direction map of min-width => URL for <picture>',
			'format'      => 'string|array',
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

		if ( isset( $this->_attrs['acf'] ) && is_array( $this->_attrs['acf'] ) ) {

			// ACF image arrays also have a `sizes` key (width/height map). That
			// is not the HTML sizes attribute.
			if ( is_array( $this->sizes ) ) {
				$this->sizes = null;
			}

			$src = $this->get_acf_size_url( $this->size );
			if ( $src ) {
				$this->src = $src;
			}

			$attachment_id = isset( $this->_attrs['acf']['ID'] ) ? (int) $this->_attrs['acf']['ID'] : 0;

			// Native srcset/sizes unless the caller already passed art-direction
			// (an array) or an explicit srcset string.
			if ( empty( $this->srcset ) && $attachment_id && function_exists( 'wp_get_attachment_image_srcset' ) ) {
				$size_name = $this->attachment_size_name();
				$srcset    = wp_get_attachment_image_srcset( $attachment_id, $size_name );
				if ( ! $srcset && $size_name !== $this->size ) {
					$srcset = wp_get_attachment_image_srcset( $attachment_id, $this->size );
				}
				if ( $srcset ) {
					$this->srcset = $srcset;
				}

				if ( empty( $this->sizes ) && function_exists( 'wp_get_attachment_image_sizes' ) ) {
					$sizes = wp_get_attachment_image_sizes( $attachment_id, $size_name );
					if ( ! $sizes && $size_name !== $this->size ) {
						$sizes = wp_get_attachment_image_sizes( $attachment_id, $this->size );
					}
					if ( $sizes ) {
						$this->sizes = $sizes;
					}
				}
			}

			$width = $this->get_acf_size_dimension( 'width' );
			if ( null !== $width ) {
				$this->width = $width;
			} elseif ( ! $this->is_blank_dimension( $this->width ) ) {
				$this->width = (string) $this->width;
			}

			$height = $this->get_acf_size_dimension( 'height' );
			if ( null !== $height ) {
				$this->height = $height;
			} elseif ( ! $this->is_blank_dimension( $this->height ) ) {
				$this->height = (string) $this->height;
			}
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
	 * Whether a width/height value is missing (do not invent sizes).
	 *
	 * @param Mixed $value The dimension value.
	 * @return Boolean
	 */
	protected function is_blank_dimension( $value ) {
		return is_null( $value ) || false === $value || '' === $value;
	}

	/**
	 * WP size name to request, preferring the theme-prefixed registration.
	 *
	 * @return String
	 */
	protected function attachment_size_name() {
		$prefixed = 'wonderpress-' . $this->size;
		$sizes    = isset( $this->_attrs['acf']['sizes'] ) && is_array( $this->_attrs['acf']['sizes'] ) ? $this->_attrs['acf']['sizes'] : array();

		if ( ! empty( $sizes[ $prefixed ] ) ) {
			return $prefixed;
		}

		return $this->size;
	}

	/**
	 * Width or height for the selected size from an ACF image array.
	 *
	 * @param String $axis 'width' or 'height'.
	 * @return String|null
	 */
	protected function get_acf_size_dimension( $axis ) {
		$sizes      = isset( $this->_attrs['acf']['sizes'] ) && is_array( $this->_attrs['acf']['sizes'] ) ? $this->_attrs['acf']['sizes'] : array();
		$size       = $this->size;
		$prefixed   = 'wonderpress-' . $size . '-' . $axis;
		$unprefixed = $size . '-' . $axis;

		if ( isset( $sizes[ $prefixed ] ) && '' !== $sizes[ $prefixed ] && false !== $sizes[ $prefixed ] ) {
			return (string) $sizes[ $prefixed ];
		}

		if ( isset( $sizes[ $unprefixed ] ) && '' !== $sizes[ $unprefixed ] && false !== $sizes[ $unprefixed ] ) {
			return (string) $sizes[ $unprefixed ];
		}

		if ( isset( $this->_attrs['acf'][ $axis ] ) && '' !== $this->_attrs['acf'][ $axis ] && false !== $this->_attrs['acf'][ $axis ] ) {
			return (string) $this->_attrs['acf'][ $axis ];
		}

		return null;
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

		if ( ! empty( $this->_attrs['acf']['url'] ) ) {
			return $this->_attrs['acf']['url'];
		}

		return $fallback;
	}
}
