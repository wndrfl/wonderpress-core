<?php
/**
 * Keep the repository's theme.json authoritative.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_strip_user_global_styles' ) ) {
	/**
	 * Remove database-backed Global Styles from the theme.json cascade.
	 *
	 * WordPress stores edits made through Global Styles in a wp_global_styles
	 * post. That user origin outranks the theme file, making the rendered design
	 * differ from the repository without producing a diff. WonderPress themes
	 * keep the file authoritative by default.
	 *
	 * Projects that intentionally use the Global Styles UI can opt out:
	 *
	 *     add_filter( 'wonderpress_strip_user_global_styles', '__return_false' );
	 *
	 * @param WP_Theme_JSON_Data $theme_json User-origin theme JSON data.
	 * @return WP_Theme_JSON_Data
	 */
	function wonder_strip_user_global_styles( $theme_json ) {
		/**
		 * Filter whether database-backed Global Styles should be discarded.
		 *
		 * @param Boolean $strip True to keep theme.json authoritative.
		 */
		if ( ! apply_filters( 'wonderpress_strip_user_global_styles', true ) ) {
			return $theme_json;
		}

		if ( ! class_exists( 'WP_Theme_JSON_Data' ) || ! class_exists( 'WP_Theme_JSON' ) ) {
			return $theme_json;
		}

		return new WP_Theme_JSON_Data(
			array( 'version' => WP_Theme_JSON::LATEST_SCHEMA ),
			'custom'
		);
	}

	add_filter( 'wp_theme_json_data_user', 'wonder_strip_user_global_styles', PHP_INT_MAX );
}
