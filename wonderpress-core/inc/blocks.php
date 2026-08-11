<?php
/**
 * Register the theme's opt-in Gutenberg blocks.
 *
 * The WonderPress CLI (`partial create --block`) emits, per opted-in partial,
 * a `blocks/<slug>/` directory containing a `block.json` and a `render.php`
 * that delegates the block's server render back to the partial. A partial is a
 * rendering primitive and is NOT a block by default; this only registers the
 * ones a developer explicitly exposed to the editor. Blocks that no partial
 * opted into simply do not exist, so there is nothing to register.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_register_theme_blocks' ) ) {
	/**
	 * Register every block found under the active theme's `blocks/` directory.
	 *
	 * Each subdirectory holding a `block.json` is registered via
	 * register_block_type(), which reads the metadata (including the `render`
	 * binding) directly from the directory.
	 */
	function wonder_register_theme_blocks() {
		$blocks_dir = get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		foreach ( glob( $blocks_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR ) as $block_path ) {
			if ( file_exists( $block_path . DIRECTORY_SEPARATOR . 'block.json' ) ) {
				register_block_type( $block_path );
			}
		}
	}

	add_action( 'init', 'wonder_register_theme_blocks' );
}

if ( ! function_exists( 'wonder_register_block_category' ) ) {
	/**
	 * Add the `wonderpress` block category so CLI-emitted blocks
	 * (`"category": "wonderpress"`) resolve in the inserter.
	 *
	 * @param mixed[] $categories The registered block categories.
	 * @return mixed[]
	 */
	function wonder_register_block_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && 'wonderpress' === $category['slug'] ) {
				return $categories;
			}
		}

		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'wonderpress',
					'title' => __( 'WonderPress', 'wonderpress' ),
				),
			)
		);
	}

	add_filter( 'block_categories_all', 'wonder_register_block_category' );
}
