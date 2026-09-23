<?php
/**
 * Render embedded partial references (manifest type partial).
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_partial_class_for_slug' ) ) {
	/**
	 * Core partial class for a slug, when one ships in wonderpress-core.
	 *
	 * @param string $slug Partial slug.
	 * @return string|null Fully-qualified class name.
	 */
	function wonder_partial_class_for_slug( $slug ) {
		$map = array(
			'link'  => \Wonderpress_Core\Partials\Link::class,
			'image' => \Wonderpress_Core\Partials\Image::class,
		);

		$class = isset( $map[ $slug ] ) ? $map[ $slug ] : null;

		/**
		 * Filter the class used to render a referenced partial slug.
		 *
		 * @param string|null $class Class name or null.
		 * @param string      $slug  Partial slug from the manifest.
		 */
		return apply_filters( 'wonderpress_partial_class_for_slug', $class, $slug );
	}
}

if ( ! function_exists( 'wonder_render_partial_ref' ) ) {
	/**
	 * Hydrate and render a partial from an embedded ACF group value.
	 *
	 * @param string     $partial_slug Referenced partial slug (e.g. link).
	 * @param array|null $acf_data     Field group value from get_field / parent array key.
	 * @return string Rendered HTML, or empty when the class is missing.
	 */
	function wonder_render_partial_ref( $partial_slug, $acf_data ) {
		$class = wonder_partial_class_for_slug( $partial_slug );
		if ( ! $class || ! class_exists( $class ) || ! is_array( $acf_data ) ) {
			return '';
		}

		if ( function_exists( 'wonder_normalize_partial_value' ) ) {
			$acf_data = wonder_normalize_partial_value(
				$acf_data,
				array(
					'type'    => 'partial',
					'partial' => $partial_slug,
				)
			);
		}

		$partial = new $class(
			array(
				'acf' => $acf_data,
			)
		);

		return (string) $partial->render( false );
	}
}
