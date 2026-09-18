<?php
/**
 * Render template composition from manifest.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_partial_class_for_manifest' ) ) {
	/**
	 * FQCN for a partial manifest entry.
	 *
	 * @param array $partial_manifest Parsed partial manifest.
	 * @return string|null
	 */
	function wonder_partial_class_for_manifest( $partial_manifest ) {
		if ( empty( $partial_manifest['name'] ) || ! is_string( $partial_manifest['name'] ) ) {
			return null;
		}

		$class = 'Wonderpress\\Partials\\' . $partial_manifest['name'];
		$class = (string) apply_filters( 'wonderpress_partial_class', $class, $partial_manifest );

		return $class;
	}
}

if ( ! function_exists( 'wonder_require_partial_class_file' ) ) {
	/**
	 * Require the partial PHP class file when recorded on the manifest.
	 *
	 * @param array $partial_manifest Partial manifest.
	 * @return void
	 */
	function wonder_require_partial_class_file( $partial_manifest ) {
		if ( empty( $partial_manifest['artifacts']['class'] ) || ! is_string( $partial_manifest['artifacts']['class'] ) ) {
			return;
		}

		$path = get_stylesheet_directory() . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $partial_manifest['artifacts']['class'] );
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}

if ( ! function_exists( 'wonder_render_template_sections' ) ) {
	/**
	 * Render every row in the current (or given) template manifest composition.
	 *
	 * @param string|null $template_slug WordPress template slug; defaults to current page.
	 * @return void
	 */
	function wonder_render_template_sections( $template_slug = null ) {
		if ( null === $template_slug ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$template_slug = wonder_page_template_slug( $post );
			}
		}

		if ( ! $template_slug || 'default' === $template_slug ) {
			return;
		}

		$manifest = wonder_template_manifest( $template_slug );
		if ( ! $manifest || empty( $manifest['composition'] ) || ! is_array( $manifest['composition'] ) ) {
			return;
		}

		foreach ( $manifest['composition'] as $row ) {
			if ( empty( $row['partial'] ) || empty( $row['id'] ) ) {
				continue;
			}

			$partial_manifest = wonder_theme_manifest( $row['partial'] );
			if ( ! $partial_manifest ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: instance id, 2: partial slug */
						esc_html__( 'Composition row "%1$s" references unknown partial "%2$s".', 'wonderpress' ),
						esc_html( $row['id'] ),
						esc_html( $row['partial'] )
					),
					'2.2.0'
				);
				continue;
			}

			wonder_require_partial_class_file( $partial_manifest );

			$class = wonder_partial_class_for_manifest( $partial_manifest );
			if ( ! $class || ! class_exists( $class ) ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: PHP class name */
						esc_html__( 'Partial class "%s" is not available.', 'wonderpress' ),
						esc_html( (string) $class )
					),
					'2.2.0'
				);
				continue;
			}

			$props = wonder_partial_props( $row['partial'], $row['id'] );
			$instance = new $class( $props );
			if ( method_exists( $instance, 'render' ) ) {
				$instance->render();
			}
		}
	}
}
