<?php
/**
 * A Link partial class.
 *
 * @package Wonderpress Core
 */

namespace Wonderpress_Core\Partials;

use Wonderpress_Core\Partials\Abstract_Partial;

defined( 'ABSPATH' ) || exit;

/**
 * Link
 * Wonderpress_Core\Partials\Link
 */
class Link extends Abstract_Partial {

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
	protected $_partial_template = 'partials/link.php';

	/**
	 * A definition of all available properties.
	 *
	 * @var Array $_properties
	 */
	protected static $_properties = array(
		'attributes'      => array(
			'description' => 'An array of arbitrary attributes for the anchor element',
			'format'      => 'array',
			'default'     => array(),
			'required'    => false,
		),
		'classes'         => array(
			'description' => 'The classes for the link element',
			'format'      => 'string|array',
			'default'     => array(),
			'required'    => false,
		),
		'content'         => array(
			'description' => 'The content to display inside the anchor tag',
			'format'      => 'string',
			'required'    => true,
		),
		'open_in_new_tab' => array(
			'description' => 'Whether or not this link should open in a new tab when clicked',
			'format'      => 'boolean',
			'default'     => false,
			'required'    => true,
		),
		'title'           => array(
			'description' => 'A title to used to aid screenreaders in understanding this link',
			'format'      => 'string',
			'required'    => false,
		),
		'type'            => array(
			'description' => 'The type of URL that this link targets',
			'format'      => 'string',
			'required'    => false,
		),
		'url'             => array(
			'description' => 'The anchor tag url attribute',
			'format'      => 'string',
			'required'    => true,
		),
	);

	/**
	 * A method to attempt to use a provided ACF field to hydrate various properties.
	 *
	 * @param Array $params The parameters passed into the class.
	 *
	 * @return Boolean
	 */
	public function attempt_acf_ingestion( array $params = array() ) {

		if ( ! $this->_acf_compatible ) {
			return;
		}

		if ( isset( $params['acf'] ) ) {
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

		if ( ! $this->url && $this->type ) {
			$acf   = isset( $params['acf'] ) && is_array( $params['acf'] ) ? $params['acf'] : array();
			$built = \wonder_link_url_from_acf( $acf );
			if ( $built ) {
				$this->url = $built;
			}
		}

		if ( $this->url && ! empty( $params['acf'] ) && ! empty( $params['acf']['add_query_params'] ) && ! empty( $params['acf']['query_params'] ) ) {
			$this->url = \wonder_link_append_query_params( $this->url, $params['acf']['query_params'] );
		}

		$this->coerce_boolean_properties_from_acf();
	}

	/**
	 * Merge new-tab rel tokens into attributes before render.
	 *
	 * @return Boolean
	 */
	public function prepare_properties_for_display() {
		$attrs = is_array( $this->attributes ) ? $this->attributes : array();
		$rel   = \wonder_link_merge_rel(
			! empty( $this->open_in_new_tab ),
			isset( $attrs['rel'] ) ? (string) $attrs['rel'] : ''
		);

		if ( '' !== $rel ) {
			$attrs['rel'] = $rel;
		} else {
			unset( $attrs['rel'] );
		}

		if ( ! empty( $this->open_in_new_tab ) && empty( $attrs['target'] ) ) {
			$attrs['target'] = '_blank';
		}

		$this->attributes = $attrs;

		return true;
	}
}
