<?php
/**
 * A Link partial class.
 *
 * @package Wonderpress Core
 */

namespace Wonderpress_Core\Partials;

use Wonderpress_Core\Partials\Abstract_Partial;

/**
 * Link
 * Wonderpress_Core\Partials\Image
 */
class Link extends Abstract_Partial {

	/**
	 * Whether this partial accepts an ACF parameter for easy hydration.
	 *
	 * @var Boolean $_acf_compatible
	 */
	protected $_acf_compatible = true;

	/**
	 * A definition of all available properties.
	 *
	 * @var Array $_properties
	 */
	protected static $_properties = array(
		'attributes' => array(
			'description' => 'An array of arbitrary attributes for the anchor element',
			'format' => 'array',
			'default' => array(),
			'required' => false,
		),
		'classes' => array(
			'description' => 'The classes for the link element',
			'format' => 'string|array',
			'default' => array(),
			'required' => false,
		),
		'content' => array(
			'description' => 'The content to display inside the anchor tag',
			'format' => 'string',
			'required' => true,
		),
		'open_in_new_tab' => array(
			'description' => 'Whether or not this link should open in a new tab when clicked',
			'format' => 'boolean',
			'default' => false,
			'required' => true,
		),
		'title' => array(
			'description' => 'A title to used to aid screenreaders in understanding this link',
			'format' => 'string',
			'required' => false,
		),
		'type' => array(
			'description' => 'The type of URL that this link targets',
			'format' => 'string',
			'required' => false,
		),
		'url' => array(
			'description' => 'The anchor tag url attribute',
			'format' => 'string',
			'required' => true,
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

				if ( 'acf' == $property_key ) {
					continue;
				}

				foreach ( $params['acf'] as $acf_key => $acf_value ) {
					if ( $acf_key == $property_key ) {
						$this->$property_key = $acf_value;
						break;
					}
				}
			}
		}

		if ( ! $this->url && $this->type ) {
			switch ( $this->type ) {
				case 'email':
					if ( ! isset( $params['acf']['email'] ) && ! empty( $params['acf']['email'] ) ) {
						break;
					}
					$this->url = 'mailto:' . $params['acf']['email'];
					break;
				case 'file':
					if ( ! isset( $params['acf']['file'] ) ) {
						break;
					}
					$this->url = get_permalink( $params['acf']['file'] );
					break;
				case 'internal':
					if ( ! isset( $params['acf']['internal_target_obj'] ) ) {
						break;
					}
					$this->url = get_permalink( $params['acf']['internal_target_obj'] );
					break;
				case 'telephone':
					if ( ! isset( $params['acf']['telephone'] ) && ! empty( $params['acf']['telephone'] ) ) {
						break;
					}
					$this->url = 'tel:+' . $params['acf']['telephone'];
					break;
			}
		}
	}

	/**
	 * A method to manipulate $_attrs before attempting to display.
	 *
	 * @return Boolean
	 */
	public function prepare_properties_for_display() {
		if ( empty( $this->_attrs['title'] ) ) {
			$this->_attrs['title'] = $this->_attrs['content'];
		}

		return true;
	}

	/**
	 * An internal process to merge the property values and HTML bits into a
	 * usable HTML snippet.
	 *
	 * @throws \Exception If there is no configured partial template.
	 *
	 * @return void
	 */
	public function render_into_template() {

		foreach ( $this->_attrs as $k => $v ) {
			$$k = $v;
		}

		include( dirname( __FILE__ ) . '/../../partials/link.php' );
	}
}
