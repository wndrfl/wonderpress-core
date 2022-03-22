<?php
/**
 * Various functions and configurations to run upon page load.
 *
 * @package Wonderpress Core
 */

if ( ! function_exists( 'wonder_add_slug_to_body_class' ) ) {
	/**
	 * Adds the slug of the current page or post as a class to the <body> tag
	 * Extract the slug and add it to the classes[] array.
	 *
	 * @param mixed[] $classes An array of classes for the body tag.
	 */
	function wonder_add_slug_to_body_class( $classes ) {
		global $post;
		if ( is_home() ) {
			$key = array_search( 'blog', $classes, true );
			if ( $key > -1 ) {
				unset( $classes[ $key ] );
			}
		} elseif ( is_page() ) {
			$classes[] = sanitize_html_class( $post->post_name );
		} elseif ( is_singular() ) {
			$classes[] = sanitize_html_class( $post->post_name );
		}

		return $classes;
	}

	add_filter( 'body_class', 'wonder_add_slug_to_body_class' );
}

if ( ! function_exists( 'wonder_remove_admin_bar' ) ) {
	/**
	 * Will remove the admin bar.
	 */
	function wonder_remove_admin_bar() {
		return false;
	}

	add_filter( 'show_admin_bar', 'wonder_remove_admin_bar' );
}

if ( ! function_exists( 'wonder_remove_recent_comments_style' ) ) {
	/**
	 * Remove recent_comments_style from wp_head
	 */
	function wonder_remove_recent_comments_style() {
		global $wp_widget_factory;
		remove_action(
			'wp_head',
			array(
				$wp_widget_factory->widgets['WP_Widget_Recent_Comments'],
				'recent_comments_style',
			)
		);
	}

	add_action( 'widgets_init', 'wonder_remove_recent_comments_style' );
}
