<?php
/**
 * Load the theme's partial manifests from `.wonderpress/manifest/`.
 *
 * The CLI writes one JSON file per component; this is the shared runtime
 * index. Consumers (ACF field registration today, others later) call
 * wonder_load_theme_manifests() rather than re-scanning the directory.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_theme_manifests' ) ) {
	/**
	 * The theme's partial manifests, as parsed.
	 *
	 * Doubles as the store and its accessor so the list has exactly one home.
	 * Call with no arguments to read it. A missing or unreadable file is
	 * skipped rather than fatal — one bad JSON must not take down a consumer.
	 *
	 * @param array|null $add  A parsed manifest to record.
	 * @param bool       $mark Mark the scan as having run.
	 * @return array[]|null Keyed by slug. null until the scan has run.
	 */
	function wonder_theme_manifests( $add = null, $mark = false ) {
		static $manifests = array();
		static $ran       = false;

		if ( $mark ) {
			$ran = true;
		}

		if ( is_array( $add ) && ! empty( $add['slug'] ) && is_string( $add['slug'] ) ) {
			$manifests[ $add['slug'] ] = $add;
		}

		return $ran ? $manifests : null;
	}
}

if ( ! function_exists( 'wonder_load_theme_manifests' ) ) {
	/**
	 * Scan `.wonderpress/manifest/*.json` in the active theme.
	 *
	 * Same theme root as blocks/: get_stylesheet_directory(). Quiet if the
	 * directory does not exist.
	 *
	 * @return array[] Keyed by slug.
	 */
	function wonder_load_theme_manifests() {
		if ( is_array( wonder_theme_manifests() ) ) {
			return wonder_theme_manifests();
		}

		wonder_theme_manifests( null, true );

		$manifest_dir = get_stylesheet_directory() . DIRECTORY_SEPARATOR . '.wonderpress' . DIRECTORY_SEPARATOR . 'manifest';

		if ( ! is_dir( $manifest_dir ) ) {
			return wonder_theme_manifests();
		}

		foreach ( glob( $manifest_dir . DIRECTORY_SEPARATOR . '*.json' ) as $path ) {
			$raw = file_get_contents( $path );
			if ( false === $raw ) {
				continue;
			}

			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || empty( $data['slug'] ) || ! is_string( $data['slug'] ) ) {
				continue;
			}

			wonder_theme_manifests( $data );
		}

		return wonder_theme_manifests();
	}
}

if ( ! function_exists( 'wonder_theme_manifest' ) ) {
	/**
	 * One manifest by slug, or null when absent.
	 *
	 * @param string $slug Partial slug.
	 * @return array|null
	 */
	function wonder_theme_manifest( $slug ) {
		$all = wonder_load_theme_manifests();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}
}
