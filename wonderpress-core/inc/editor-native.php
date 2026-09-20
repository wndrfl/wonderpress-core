<?php
/**
 * Hide or show native page editor UI from template manifests.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_template_native_editor_settings' ) ) {
	/**
	 * Resolved `editor.native` map for a post, or null.
	 *
	 * @param WP_Post|null $post Post.
	 * @return array|null
	 */
	function wonder_template_native_editor_settings( $post = null ) {
		$manifest = wonder_template_manifest_for_post( $post );
		if ( ! $manifest || empty( $manifest['editor']['native'] ) || ! is_array( $manifest['editor']['native'] ) ) {
			return null;
		}
		return $manifest['editor']['native'];
	}
}

if ( ! function_exists( 'wonder_post_matches_template_manifest' ) ) {
	/**
	 * Whether a post uses a template that has a manifest.
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	function wonder_post_matches_template_manifest( $post = null ) {
		return null !== wonder_template_manifest_for_post( $post );
	}
}

if ( ! function_exists( 'wonder_native_editor_flag' ) ) {
	/**
	 * Whether a native editor flag is enabled on the manifest.
	 *
	 * @param array|null $native Resolved editor.native map.
	 * @param string     $key    Flag name.
	 * @return bool|null Null when the flag is not set.
	 */
	function wonder_native_editor_flag( $native, $key ) {
		if ( null === $native || ! array_key_exists( $key, $native ) ) {
			return null;
		}

		return (bool) $native[ $key ];
	}
}

if ( ! function_exists( 'wonder_admin_get_editing_post' ) ) {
	/**
	 * Post being edited on an admin write screen, when known.
	 *
	 * @return WP_Post|null
	 */
	function wonder_admin_get_editing_post() {
		if ( ! is_admin() ) {
			return null;
		}

		$post_id = 0;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post'];
		} elseif ( isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_ID']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		return ( $post instanceof WP_Post ) ? $post : null;
	}
}

if ( ! function_exists( 'wonder_apply_template_native_post_supports' ) ) {
	/**
	 * Drop post-type supports the manifest hides (featured image, excerpt, …).
	 *
	 * @return void
	 */
	function wonder_apply_template_native_post_supports() {
		$post = wonder_admin_get_editing_post();
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native ) {
			return;
		}

		if ( false === wonder_native_editor_flag( $native, 'featuredImage' ) ) {
			remove_post_type_support( 'page', 'thumbnail' );
		}

		if ( false === wonder_native_editor_flag( $native, 'excerpt' ) ) {
			remove_post_type_support( 'page', 'excerpt' );
		}

		if ( false === wonder_native_editor_flag( $native, 'discussion' ) ) {
			remove_post_type_support( 'page', 'comments' );
			remove_post_type_support( 'page', 'trackbacks' );
		}
	}

	add_action( 'admin_init', 'wonder_apply_template_native_post_supports', 5 );
}

if ( ! function_exists( 'wonder_manifest_disables_block_editor' ) ) {
	/**
	 * Whether the template manifest turns off the block editor for this post.
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	function wonder_manifest_disables_block_editor( $post = null ) {
		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native || ! array_key_exists( 'blockEditor', $native ) ) {
			return false;
		}

		return ! $native['blockEditor'];
	}
}

if ( ! function_exists( 'wonder_use_block_editor_for_post_from_manifest' ) ) {
	/**
	 * Disable the block editor when the template manifest says so.
	 *
	 * @param bool    $use  Whether to use the block editor.
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	function wonder_use_block_editor_for_post_from_manifest( $use, $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $use;
		}

		if ( wonder_manifest_disables_block_editor( $post ) ) {
			return false;
		}

		return $use;
	}

	add_filter( 'use_block_editor_for_post', 'wonder_use_block_editor_for_post_from_manifest', 100, 2 );
}

if ( ! function_exists( 'wonder_remove_native_meta_boxes_from_manifest' ) ) {
	/**
	 * Remove classic meta boxes the manifest hides.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post.
	 * @return void
	 */
	function wonder_remove_native_meta_boxes_from_manifest( $post_type, $post ) {
		if ( 'page' !== $post_type || ! $post instanceof WP_Post ) {
			return;
		}

		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native ) {
			return;
		}

		if ( false === wonder_native_editor_flag( $native, 'featuredImage' ) ) {
			remove_meta_box( 'postimagediv', 'page', 'side' );
		}

		if ( false === wonder_native_editor_flag( $native, 'discussion' ) ) {
			remove_meta_box( 'commentstatusdiv', 'page', 'normal' );
			remove_meta_box( 'commentsdiv', 'page', 'normal' );
		}

		if ( false === wonder_native_editor_flag( $native, 'excerpt' ) ) {
			remove_meta_box( 'postexcerpt', 'page', 'normal' );
		}
	}

	add_action( 'add_meta_boxes', 'wonder_remove_native_meta_boxes_from_manifest', 999, 2 );
}

if ( ! function_exists( 'wonder_admin_native_editor_styles' ) ) {
	/**
	 * CSS fallback when a meta box still registers after support was removed.
	 *
	 * @return void
	 */
	function wonder_admin_native_editor_styles() {
		$post = wonder_admin_get_editing_post();
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native ) {
			return;
		}

		$rules = array();
		if ( false === wonder_native_editor_flag( $native, 'featuredImage' ) ) {
			$rules[] = '#postimagediv,.editor-post-featured-image{display:none!important;}';
		}
		if ( false === wonder_native_editor_flag( $native, 'excerpt' ) ) {
			$rules[] = '#postexcerpt,.editor-post-excerpt{display:none!important;}';
		}
		if ( false === wonder_native_editor_flag( $native, 'title' ) ) {
			$rules[] = '#titlediv,.editor-post-title{display:none!important;}';
		}

		if ( $rules ) {
			echo '<style>' . esc_html( implode( '', $rules ) ) . '</style>';
		}
	}

	add_action( 'admin_head', 'wonder_admin_native_editor_styles' );
}
