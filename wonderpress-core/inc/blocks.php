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

		// Mark the pass as having happened even when there is nothing to find.
		// Everything downstream needs to tell "no blocks" apart from "not asked
		// yet", because those want opposite behaviour.
		wonder_theme_blocks( null, true );

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		foreach ( glob( $blocks_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR ) as $block_path ) {
			if ( ! file_exists( $block_path . DIRECTORY_SEPARATOR . 'block.json' ) ) {
				continue;
			}

			$block = register_block_type( $block_path );

			// Keep what was actually registered, rather than re-reading the same
			// files later to work it out again. This is also more truthful than a
			// second scan: a block whose metadata WordPress rejected never lands
			// here, so it cannot be offered in the inserter as though it exists.
			if ( $block instanceof WP_Block_Type ) {
				wonder_theme_blocks( $block );
			}
		}
	}

	add_action( 'init', 'wonder_register_theme_blocks' );
}

if ( ! function_exists( 'wonder_theme_blocks' ) ) {
	/**
	 * The theme's own blocks, as registered.
	 *
	 * Doubles as the store and its accessor so the list has exactly one home.
	 * Call with no arguments to read it.
	 *
	 * @param WP_Block_Type|null $add  A block type to record.
	 * @param bool               $mark Mark the registration pass as having run.
	 * @return WP_Block_Type[] Keyed by block name.
	 */
	function wonder_theme_blocks( $add = null, $mark = false ) {
		static $blocks = array();
		static $ran    = false;

		if ( $mark ) {
			$ran = true;
		}

		if ( $add instanceof WP_Block_Type ) {
			$blocks[ $add->name ] = $add;
		}

		return $ran ? $blocks : null;
	}
}

if ( ! function_exists( 'wonder_theme_block_categories' ) ) {
	/**
	 * The category slugs the theme's own blocks ask to live in.
	 *
	 * Taken from what was registered rather than from a second pass over the
	 * same files. The category follows the project's block namespace — `acme/hero`
	 * is filed under `acme` — and the namespace belongs to the project, not to
	 * WonderPress, so this keeps working whatever a project calls itself,
	 * including older projects still using `wonderpress`.
	 *
	 * @return string[] Distinct category slugs.
	 */
	function wonder_theme_block_categories() {
		$blocks = wonder_theme_blocks();

		if ( ! is_array( $blocks ) ) {
			return array();
		}

		$slugs = array();

		foreach ( $blocks as $block ) {
			if ( ! empty( $block->category ) && is_string( $block->category ) ) {
				$slugs[] = $block->category;
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

if ( ! function_exists( 'wonder_allowed_core_blocks' ) ) {
	/**
	 * The core blocks that stay available alongside the theme's own.
	 *
	 * Kept deliberately small. The argument for curating at all is that a client
	 * composes pages out of the project's components, not out of ninety generic
	 * ones — so this is the minimum needed to write, not a second design system.
	 *
	 * `core/list-item` is not optional company for `core/list`: a list is a
	 * container and its items are separate blocks, so allowing the list alone
	 * produces one that cannot be typed into.
	 *
	 * @return string[]
	 */
	function wonder_allowed_core_blocks() {
		$core = array(
			'core/paragraph',
			'core/heading',
			'core/list',
			'core/list-item',
			'core/image',
		);

		return (array) apply_filters( 'wonderpress_allowed_core_blocks', $core );
	}
}

if ( ! function_exists( 'wonder_curate_allowed_blocks' ) ) {
	/**
	 * Narrow the inserter to the project's own blocks plus a small core set.
	 *
	 * OPT-IN, and deliberately so. Switching this on removes most of the block
	 * library, which is the point — but doing it by default would silently strip
	 * blocks from existing client sites the moment they updated the plugin, and
	 * a curated suite is a decision each project makes rather than one the
	 * framework makes for it. Enable with WONDERPRESS_CURATE_BLOCKS, or take the
	 * `wonderpress_curate_blocks` filter for per-context control.
	 *
	 * This governs what can be INSERTED. Blocks already placed in content stay
	 * registered and keep rendering, so curating a live site does not break the
	 * pages it already has.
	 *
	 * @param bool|string[]           $allowed Allowed block types, or true for all.
	 * @param WP_Block_Editor_Context $context The editor being configured.
	 * @return bool|string[]
	 */
	function wonder_curate_allowed_blocks( $allowed, $context = null ) {
		$default = defined( 'WONDERPRESS_CURATE_BLOCKS' ) && WONDERPRESS_CURATE_BLOCKS;

		if ( ! apply_filters( 'wonderpress_curate_blocks', $default, $context ) ) {
			return $allowed;
		}

		$blocks = wonder_theme_blocks();

		// null means registration has not run yet — not that the theme has no
		// blocks. Curating on that would hand back a list with every one of the
		// project's own blocks missing, which is worse than not curating at all.
		if ( ! is_array( $blocks ) ) {
			return $allowed;
		}

		return array_values( array_unique( array_merge( array_keys( $blocks ), wonder_allowed_core_blocks() ) ) );
	}

	add_filter( 'allowed_block_types_all', 'wonder_curate_allowed_blocks', 10, 2 );
}
