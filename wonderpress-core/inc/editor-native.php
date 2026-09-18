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

if ( ! function_exists( 'wonder_use_block_editor_for_post_from_manifest' ) ) {
	/**
	 * Disable the block editor when the template manifest says so.
	 *
	 * @param bool    $use      Whether to use the block editor.
	 * @param WP_Post $post     Post.
	 * @return bool
	 */
	function wonder_use_block_editor_for_post_from_manifest( $use, $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $use;
		}

		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native || ! array_key_exists( 'blockEditor', $native ) ) {
			return $use;
		}

		if ( false === $native['blockEditor'] ) {
			return false;
		}

		return $use;
	}

	add_filter( 'use_block_editor_for_post', 'wonder_use_block_editor_for_post_from_manifest', 10, 2 );
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

		if ( array_key_exists( 'featuredImage', $native ) && false === $native['featuredImage'] ) {
			remove_meta_box( 'postimagediv', 'page', 'side' );
		}

		if ( array_key_exists( 'discussion', $native ) && false === $native['discussion'] ) {
			remove_meta_box( 'commentstatusdiv', 'page', 'normal' );
			remove_meta_box( 'commentsdiv', 'page', 'normal' );
		}

		if ( array_key_exists( 'excerpt', $native ) && false === $native['excerpt'] ) {
			remove_meta_box( 'postexcerpt', 'page', 'normal' );
		}
	}

	add_action( 'add_meta_boxes', 'wonder_remove_native_meta_boxes_from_manifest', 100, 2 );
}

if ( ! function_exists( 'wonder_hide_title_from_manifest' ) ) {
	/**
	 * Hide the title field on the classic edit screen when requested.
	 *
	 * @return void
	 */
	function wonder_hide_title_from_manifest() {
		global $post;
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		$native = wonder_template_native_editor_settings( $post );
		if ( null === $native || ! array_key_exists( 'title', $native ) || false !== $native['title'] ) {
			return;
		}

		echo '<style>#titlediv{display:none;}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static admin CSS.
	}

	add_action( 'admin_head', 'wonder_hide_title_from_manifest' );
}
