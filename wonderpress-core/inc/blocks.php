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

if ( ! function_exists( 'wonder_theme_block_categories' ) ) {
	/**
	 * Collect the category slugs the theme's own blocks ask to live in.
	 *
	 * Read from the emitted block.json files rather than assumed, because the
	 * category follows the project's block namespace — `acme/hero` is filed
	 * under `acme` — and the namespace belongs to the project, not to
	 * WonderPress. Reading it back means this keeps working whatever a project
	 * calls itself, including the older projects that use `wonderpress`.
	 *
	 * @return string[] Distinct category slugs, in directory order.
	 */
	function wonder_theme_block_categories() {
		$blocks_dir = get_stylesheet_directory() . DIRECTORY_SEPARATOR . 'blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return array();
		}

		$slugs = array();

		foreach ( glob( $blocks_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR ) as $block_path ) {
			$metadata_file = $block_path . DIRECTORY_SEPARATOR . 'block.json';

			if ( ! file_exists( $metadata_file ) ) {
				continue;
			}

			$metadata = json_decode( file_get_contents( $metadata_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local theme file, not a remote request.

			if ( is_array( $metadata ) && ! empty( $metadata['category'] ) && is_string( $metadata['category'] ) ) {
				$slugs[] = $metadata['category'];
			}
		}

		return array_values( array_unique( $slugs ) );
	}
}

if ( ! function_exists( 'wonder_register_block_category' ) ) {
	/**
	 * Register a block category for each one the theme's blocks declare, so
	 * they resolve in the inserter instead of falling back to "Uncategorized".
	 *
	 * The category the project shares with its theme is titled from the theme's
	 * own name, so the inserter reads "Acme Co" rather than a slug.
	 *
	 * @param mixed[] $categories The registered block categories.
	 * @return mixed[]
	 */
	function wonder_register_block_category( $categories ) {
		$existing = array_filter( wp_list_pluck( $categories, 'slug' ) );
		$theme    = wp_get_theme();

		foreach ( wonder_theme_block_categories() as $slug ) {
			if ( in_array( $slug, $existing, true ) ) {
				continue;
			}

			$title = ( get_stylesheet() === $slug && $theme->get( 'Name' ) )
				? $theme->get( 'Name' )
				: ucwords( str_replace( '-', ' ', $slug ) );

			$categories[] = array(
				'slug'  => $slug,
				'title' => $title,
			);

			$existing[] = $slug;
		}

		return $categories;
	}

	add_filter( 'block_categories_all', 'wonder_register_block_category' );
}
