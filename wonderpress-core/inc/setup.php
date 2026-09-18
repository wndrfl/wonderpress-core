<?php
/**
 * Theme supports that are correctness rather than taste.
 *
 * Modern markup, feed links, a managed <title>, wide alignments. Every project
 * shipped these identically, so they lived in each theme as boilerplate nobody
 * read and nobody changed. They are defaults here instead.
 *
 * What is NOT here, deliberately: navigation menu locations, image sizes and
 * the text domain. Those are per-project decisions and belong in the theme.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_theme_supports' ) ) {
	/**
	 * Register the baseline theme supports.
	 *
	 * Runs on after_setup_theme, so a theme or child theme hooking the same
	 * action can adjust anything this sets.
	 *
	 * @return void
	 */
	function wonder_theme_supports() {

		$supports = array(
			// Let WordPress manage the document <title>.
			'title-tag'            => true,
			'post-thumbnails'      => true,
			'automatic-feed-links' => true,
			// Scale embedded media to the container width.
			'responsive-embeds'    => true,
			// Allow wide and full alignments in the block editor.
			'align-wide'           => true,
			// Let sites upload a logo instead of editing the header template.
			'custom-logo'          => true,
			// Output modern HTML5 markup for core-generated fragments.
			'html5'                => array(
				'comment-list',
				'comment-form',
				'search-form',
				'gallery',
				'caption',
				'style',
				'script',
			),
		);

		/**
		 * Filter the baseline theme supports.
		 *
		 * The opt-out. Set any feature to false to skip it, pass an array to
		 * replace its arguments, or add a feature of your own. This is how a
		 * project declines something the package considers a sensible default
		 * without editing the package.
		 *
		 * @param Array $supports Feature name => true, false, or arguments array.
		 */
		$supports = apply_filters( 'wonderpress_theme_supports', $supports );

		foreach ( $supports as $feature => $args ) {
			if ( false === $args ) {
				continue;
			}

			if ( true === $args ) {
				add_theme_support( $feature );
				continue;
			}

			add_theme_support( $feature, $args );
		}
	}

	add_action( 'after_setup_theme', 'wonder_theme_supports' );
}

if ( ! function_exists( 'wonder_disable_emojis' ) ) {
	/**
	 * Remove the emoji detection script and styles WordPress prints by default.
	 *
	 * @return void
	 */
	function wonder_disable_emojis() {

		/**
		 * Filter whether to strip the emoji detection assets.
		 *
		 * @param Boolean $disable True to remove them. Default true.
		 */
		if ( ! apply_filters( 'wonderpress_disable_emojis', true ) ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
	}

	add_action( 'init', 'wonder_disable_emojis' );
}
