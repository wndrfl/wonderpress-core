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

if ( ! function_exists( 'wonder_template_locks' ) ) {
	/**
	 * How locked each page template's content area is.
	 *
	 * Keyed by template slug as WordPress reports it — `page-landing.php` — with
	 * `default` standing for a page on no particular template. Values:
	 *
	 *   'all'         Bespoke, code-rendered. Nothing moves, nothing is added.
	 *   'contentOnly' Text is editable, layout is frozen. The usual answer for
	 *                 client-editable pages, and the one most agencies skip.
	 *   'insert'      Blocks may be reordered but not added or removed.
	 *   false         Open composition.
	 *
	 * Empty by default: which pages a client may restructure is a decision each
	 * project makes, and silently freezing an existing site's pages on a plugin
	 * update would be the wrong way to find that out.
	 *
	 * @param WP_Post|null $post The post being edited.
	 * @return array<string, string|bool>
	 */
	function wonder_template_locks( $post = null ) {
		$from_manifests = function_exists( 'wonder_template_locks_from_manifests' )
			? wonder_template_locks_from_manifests()
			: array();
		$custom         = (array) apply_filters( 'wonderpress_template_locks', array(), $post );

		return array_merge( $from_manifests, $custom );
	}
}

if ( ! function_exists( 'wonder_page_lock' ) ) {
	/**
	 * Set the editor's lock level from the page template being edited.
	 *
	 * Bespoke pages and client-composed pages have to coexist in one theme, and
	 * which one a page is follows from its template — so the decision is declared
	 * once per template in code rather than rediscovered on every page. This is
	 * deliberately NOT a property of the components on the page: a page has one
	 * lock level, its components would each claim one, and there is no sensible
	 * rule to resolve that. Whether a block's INNER content can be rearranged is
	 * the separate, component-level setting.
	 *
	 * Runs on `block_editor_settings_all`, which WordPress applies at the end of
	 * edit-form-blocks.php — after it has set `templateLock` from the post type's
	 * own `template_lock`. So a template mapping deliberately wins over the
	 * coarser post-type setting, which stays the fallback for post types that
	 * must have one fixed shape.
	 *
	 * @param array                   $settings Block editor settings.
	 * @param WP_Block_Editor_Context $context  The editor being configured.
	 * @return array
	 */
	function wonder_page_lock( $settings, $context = null ) {
		$post = ( $context instanceof WP_Block_Editor_Context && ! empty( $context->post ) ) ? $context->post : null;

		if ( ! $post instanceof WP_Post ) {
			return $settings;
		}

		$slug  = function_exists( 'wonder_page_template_slug' )
			? wonder_page_template_slug( $post )
			: 'default';
		$locks = wonder_template_locks( $post );

		// Absent is not the same as false. A template nobody mapped is left
		// exactly as WordPress configured it; only an explicit entry changes
		// anything, so this cannot quietly unlock a post type that locked itself.
		if ( ! array_key_exists( $slug, $locks ) ) {
			return $settings;
		}

		$lock = $locks[ $slug ];

		if ( ! in_array( $lock, array( 'all', 'insert', 'contentOnly', false ), true ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: template slug, 2: the invalid value */
					esc_html__( 'Template "%1$s" was given an unrecognised lock level (%2$s). Use "all", "insert", "contentOnly" or false.', 'wonderpress' ),
					esc_html( $slug ),
					esc_html( var_export( $lock, true ) ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				),
				'1.0.0'
			);
			return $settings;
		}

		$settings['templateLock'] = $lock;

		return $settings;
	}

	add_filter( 'block_editor_settings_all', 'wonder_page_lock', 10, 2 );
}

if ( ! function_exists( 'wonder_block_editor_schemas' ) ) {
	/**
	 * Manifest property definitions for blocks, keyed by block name.
	 *
	 * The block.json attribute types alone cannot express manifest types (select vs
	 * string, email, textarea hints, when rules). The editor reads this map.
	 *
	 * @return array<string, array{properties: array<int, array<string, mixed>>}>
	 */
	function wonder_block_editor_schemas() {
		if ( ! function_exists( 'wonder_load_theme_manifests' ) ) {
			return array();
		}

		$schemas = array();

		foreach ( wonder_load_theme_manifests() as $manifest ) {
			if ( empty( $manifest['block'] ) || empty( $manifest['properties'] ) || ! is_array( $manifest['properties'] ) ) {
				continue;
			}

			$properties = array();

			foreach ( $manifest['properties'] as $prop ) {
				$entry = wonder_block_editor_schema_entry_from_property( $prop );
				if ( $entry ) {
					$properties[] = $entry;
				}
			}

			if ( $properties ) {
				$schemas[ (string) $manifest['block'] ] = array(
					'properties' => $properties,
				);
			}
		}

		return $schemas;
	}
}

if ( ! function_exists( 'wonder_enqueue_block_editor_preview' ) ) {
	/**
	 * Register the theme's blocks in the editor, and preview them there.
	 *
	 * Registering a block on the server does NOT put it in the editor.
	 * `unstable__bootstrapServerSideBlockDefinitions()` only stores the server's
	 * metadata, and the one thing that reads it back is `registerBlockType()` on
	 * the client. Without this, blocks emitted from block.json alone are absent
	 * from the inserter — not merely un-previewable.
	 *
	 * The script is buildless and reads the `wp.*` globals WordPress already
	 * enqueues, so a project needs no bundler to get an editor experience.
	 */
	function wonder_enqueue_block_editor_preview() {
		$blocks = wonder_theme_blocks();

		if ( empty( $blocks ) ) {
			return;
		}

		if ( ! defined( 'WONDERPRESS_CORE_PATH' ) ) {
			return;
		}

		$relative_path = 'assets/js/editor-preview.js';
		$absolute_path = WONDERPRESS_CORE_PATH . $relative_path;

		$style_relative = 'assets/css/editor-preview.css';
		$style_absolute = WONDERPRESS_CORE_PATH . $style_relative;

		// wonder_core_url() rather than plugins_url(): this package may be
		// installed as an mu-plugin or as a theme's Composer dependency, and
		// plugins_url() resolves against WP_PLUGIN_DIR either way.
		$src = wonder_core_url( $relative_path );

		if ( ! $src || ! file_exists( $absolute_path ) ) {
			return;
		}

		$style_src = wonder_core_url( $style_relative );
		if ( $style_src && file_exists( $style_absolute ) ) {
			wp_enqueue_style(
				'wonderpress-editor-preview',
				$style_src,
				array( 'wp-components' ),
				filemtime( $style_absolute )
			);
		}

		wp_enqueue_script(
			'wonderpress-editor-preview',
			$src,
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-compose',
				'wp-server-side-render',
				'wp-i18n',
				'wp-api-fetch',
				'media-upload',
				'wp-media-utils',
			),
			filemtime( $absolute_path ),
			true
		);

		// The block list is handed over rather than rediscovered in JS: the
		// server already knows exactly what it registered.
		wp_add_inline_script(
			'wonderpress-editor-preview',
			'window.wonderpressEditorBlocks = ' . wp_json_encode( array_keys( $blocks ) ) . ';',
			'before'
		);

		$schemas = wonder_block_editor_schemas();
		if ( $schemas ) {
			wp_add_inline_script(
				'wonderpress-editor-preview',
				'window.wonderpressBlockSchemas = ' . wp_json_encode( $schemas ) . ';',
				'before'
			);
		}
	}

	add_action( 'enqueue_block_editor_assets', 'wonder_enqueue_block_editor_preview' );
}
