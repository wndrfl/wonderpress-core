<?php
/**
 * Front-end asset delivery.
 *
 * Encodes the Static Kit convention: each top-level template declares a body
 * id (see wonder_body_id()), and its compiled bundle lives at
 * static/dist/{css,js}/{body_id}.{css,js}. A template with no bundle of its own
 * falls back to a shared global.{css,js}.
 *
 * This lives in the package rather than the theme because it is the contract
 * between the theme and Static Kit, not a design decision — it shipped
 * identically in every project, so a change to the convention meant editing
 * every site by hand. Projects that need a different layout filter
 * `wonderpress_asset_candidates` rather than editing this file.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_asset_path' ) ) {
	/**
	 * Resolve the current template's asset bundle path, relative to the theme.
	 *
	 * Falls back to the shared "global" bundle when the template-specific
	 * bundle has not been built.
	 *
	 * @param String $type Either 'css' or 'js'.
	 * @return String|null The relative path, or null when no bundle exists.
	 */
	function wonder_asset_path( $type ) {
		$candidates = array(
			sprintf( '/static/dist/%1$s/%2$s.%1$s', $type, wonder_body_id() ),
			sprintf( '/static/dist/%1$s/global.%1$s', $type ),
		);

		/**
		 * Filter the bundle paths tried, in order, for a given asset type.
		 *
		 * The opt-out for anything that changes the build layout — a different
		 * output directory, hashed filenames, a bundler that does not emit
		 * per-template bundles at all. Paths are relative to the template
		 * directory and are tried in order; the first that exists wins.
		 *
		 * @param String[] $candidates Relative paths, in priority order.
		 * @param String   $type       Either 'css' or 'js'.
		 * @param String   $body_id    The current template's body id.
		 */
		$candidates = apply_filters( 'wonderpress_asset_candidates', $candidates, $type, wonder_body_id() );

		foreach ( $candidates as $candidate ) {
			if ( file_exists( get_template_directory() . $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'wonder_enqueue_styles' ) ) {
	/**
	 * Enqueue the template's stylesheet, inline or as a linked asset.
	 *
	 * @return void
	 */
	function wonder_enqueue_styles() {

		// style.css is the WordPress theme identity file (headers only). It is
		// not enqueued. Front-end CSS is the Static Kit bundle below.
		$path = wonder_asset_path( 'css' );
		if ( ! $path ) {
			return;
		}

		$absolute = get_template_directory() . $path;

		if ( wonder_prefer_inline_css() ) {
			// Register an empty handle to attach the inline CSS to. The file is
			// read, never include()d, so it is not executed as PHP.
			wp_register_style( 'wonderpress-inline', false, array(), (string) filemtime( $absolute ) );
			wp_enqueue_style( 'wonderpress-inline' );
			wp_add_inline_style( 'wonderpress-inline', (string) file_get_contents( $absolute ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file, not remote data.
			wonder_enqueue_token_bridge( 'wonderpress-inline' );
			return;
		}

		$handle = 'wonderpress-' . wonder_body_id();

		wp_enqueue_style(
			$handle,
			get_template_directory_uri() . $path,
			array(),
			(string) filemtime( $absolute )
		);
		wonder_enqueue_token_bridge( $handle );
	}

	add_action( 'wp_enqueue_scripts', 'wonder_enqueue_styles' );
}

if ( ! function_exists( 'wonder_enqueue_scripts' ) ) {
	/**
	 * Enqueue the template's script bundle, inline or as a linked asset.
	 *
	 * @return void
	 */
	function wonder_enqueue_scripts() {
		$path = wonder_asset_path( 'js' );

		if ( $path && ! wonder_prefer_inline_js() ) {
			$absolute = get_template_directory() . $path;
			$handle   = 'wonderpress-' . wonder_body_id();

			wp_enqueue_script(
				$handle,
				get_template_directory_uri() . $path,
				array(),
				(string) filemtime( $absolute ),
				true
			);

			wp_localize_script(
				$handle,
				'wonderpressGlobals',
				array(
					'ajax_nonce' => wp_create_nonce( 'ajax-nonce' ),
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		// Support threaded comment replies without a page reload.
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wonder_enqueue_scripts' );
}

if ( ! function_exists( 'wonder_inline_js' ) ) {
	/**
	 * Print the template's script bundle inline in the footer, if configured.
	 *
	 * @return void
	 */
	function wonder_inline_js() {
		if ( ! wonder_prefer_inline_js() ) {
			return;
		}

		$path = wonder_asset_path( 'js' );
		if ( ! $path ) {
			return;
		}

		// The file is read, never include()d, so it is not executed as PHP.
		$js = (string) file_get_contents( get_template_directory() . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file, not remote data.

		printf( '<script>%s</script>', $js ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw built asset; escaping would corrupt it.
	}

	add_action( 'wp_footer', 'wonder_inline_js' );
}

if ( ! function_exists( 'wonder_maybe_dequeue_block_css' ) ) {
	/**
	 * Optionally dequeue the core block-library CSS.
	 *
	 * Off by default so content authored with core blocks renders styled. A
	 * project that owns 100% of its CSS through Static Kit can opt in via the
	 * filter, or by defining WONDERPRESS_DEQUEUE_BLOCK_CSS as true.
	 *
	 * @return void
	 */
	function wonder_maybe_dequeue_block_css() {
		$default = defined( 'WONDERPRESS_DEQUEUE_BLOCK_CSS' ) && WONDERPRESS_DEQUEUE_BLOCK_CSS;

		if ( ! apply_filters( 'wonderpress_dequeue_block_css', $default ) ) {
			return;
		}

		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
	}

	add_action( 'wp_enqueue_scripts', 'wonder_maybe_dequeue_block_css', 100 );
}

if ( ! function_exists( 'wonder_editor_asset_candidates' ) ) {
	/**
	 * Resolve the stylesheet bundles to try for the block editor canvas.
	 *
	 * The front end knows its body id because the template file sets it before
	 * the header runs. The editor never runs a template, so the id is derived
	 * from what is being edited instead: a page template `template-foo.php`
	 * maps to the `foo` bundle (the convention every Wonderpress template
	 * follows), then the post type maps to `page` or `single`, and the shared
	 * `global` bundle is last, as on the front end.
	 *
	 * @param WP_Post|null $post The post being edited, if any.
	 * @return String[] Theme-relative CSS paths, in priority order.
	 */
	function wonder_editor_asset_candidates( $post = null ) {
		$body_ids = array();

		if ( $post instanceof WP_Post ) {
			$template = get_page_template_slug( $post );

			if ( is_string( $template ) && '' !== $template ) {
				$body_id = preg_replace( '/\.php$/i', '', basename( $template ) );
				$body_id = preg_replace( '/^template-/i', '', $body_id );

				if ( '' !== $body_id ) {
					$body_ids[] = $body_id;
				}
			}

			$body_ids[] = ( 'page' === $post->post_type ) ? 'page' : 'single';
		}

		$body_ids[] = 'global';

		$candidates = array();
		foreach ( array_unique( $body_ids ) as $body_id ) {
			$candidates[] = sprintf( '/static/dist/css/%s.css', $body_id );
		}

		/**
		 * Filter the bundle paths tried, in order, for the block editor canvas.
		 *
		 * The editor-side counterpart of `wonderpress_asset_candidates`, for
		 * projects whose build layout differs. Paths are relative to the
		 * template directory and are tried in order; the first that exists wins.
		 *
		 * @param String[]     $candidates Relative CSS paths, in priority order.
		 * @param WP_Post|null $post       The post being edited, if any.
		 */
		return apply_filters( 'wonderpress_editor_asset_candidates', $candidates, $post );
	}
}

if ( ! function_exists( 'wonder_enqueue_editor_styles' ) ) {
	/**
	 * Load the Static Kit bundle inside the block editor canvas.
	 *
	 * Blocks preview through ServerSideRender, so the canvas already shows the
	 * markup render.php produces on the front end. Without the bundle it shows
	 * that markup unstyled, and the editor is WYSIWYG in name only.
	 *
	 * The canvas is an iframe (every block here is apiVersion 3), and only
	 * styles enqueued on `enqueue_block_assets` reach it. The hook most people
	 * reach for, `enqueue_block_editor_assets`, targets the admin document
	 * around the iframe — where editor-preview.css belongs and this bundle
	 * does not.
	 *
	 * WordPress fires `enqueue_block_assets` twice on an editor screen: once
	 * for the admin document, and once more while collecting the iframe's
	 * assets, with `should_load_block_editor_scripts_and_styles` forced to
	 * false (see _wp_get_iframed_editor_assets()). Only the second pass is
	 * wanted — on the first, the bundle's reset and `body` rules would restyle
	 * the admin chrome — so that forced flag is the signal.
	 *
	 * Always linked, never inlined: the iframe's assets are collected as
	 * markup, and wonder_prefer_inline_css() is a front-end delivery choice.
	 *
	 * Opt out:
	 *
	 *     add_filter( 'wonderpress_editor_styles', '__return_false' );
	 *
	 * @return void
	 */
	function wonder_enqueue_editor_styles() {
		if ( ! is_admin() || wp_should_load_block_editor_scripts_and_styles() ) {
			return;
		}

		/**
		 * Filter whether the Static Kit bundle is loaded in the block editor canvas.
		 *
		 * @param Boolean $load True to load the bundle.
		 */
		if ( ! apply_filters( 'wonderpress_editor_styles', true ) ) {
			return;
		}

		$path = null;
		foreach ( wonder_editor_asset_candidates( get_post() ) as $candidate ) {
			if ( file_exists( get_template_directory() . $candidate ) ) {
				$path = $candidate;
				break;
			}
		}

		if ( ! $path ) {
			return;
		}

		$absolute = get_template_directory() . $path;
		$handle   = 'wonderpress-editor-canvas';

		wp_enqueue_style(
			$handle,
			get_template_directory_uri() . $path,
			array(),
			(string) filemtime( $absolute )
		);
		wonder_enqueue_token_bridge( $handle );
	}

	add_action( 'enqueue_block_assets', 'wonder_enqueue_editor_styles' );
}
