<?php
/**
 * Map theme.json presets onto Static Kit custom properties.
 *
 * Static Kit assigns `$color-blue: var(--color-blue, #7A97AB)` and the same
 * shape for fonts and type. WordPress prints different names
 * (`--wp--preset--*`, `--wp--custom--*`). This file is the only join: a
 * `:root` rule that points each kit name at the WordPress variable for the
 * same slot. Token files stay host-agnostic.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_token_bridge_kebab' ) ) {
	/**
	 * Kebab-case one settings key the way WordPress flattens custom properties.
	 *
	 * Covers the identifiers this bridge emits (camelCase leaves such as
	 * `sizeTablet`, and slugs that are already kebab-case). Slashes and
	 * underscores become hyphens, matching `WP_Theme_JSON::flatten_tree()`.
	 *
	 * @param String $key A settings key or preset slug.
	 * @return String
	 */
	function wonder_token_bridge_kebab( $key ) {
		$key = preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', (string) $key );
		$key = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $key );
		$key = strtolower( $key );
		$key = str_replace( array( '/', '_' ), '-', $key );

		return $key;
	}
}

if ( ! function_exists( 'wonder_token_bridge_wp_segment' ) ) {
	/**
	 * Kebab-case one path segment exactly as WordPress names its variables.
	 *
	 * WordPress also splits letters from digits, so `h2` prints as `h-2` and
	 * `2xl` as `2-xl`. Static Kit names keep `h2`, so this is applied only to
	 * the `--wp--*` side of each declaration.
	 *
	 * @param String $segment One settings key or preset slug.
	 * @return String
	 */
	function wonder_token_bridge_wp_segment( $segment ) {
		if ( function_exists( '_wp_to_kebab_case' ) ) {
			return _wp_to_kebab_case( $segment );
		}

		$segment = wonder_token_bridge_kebab( $segment );
		$segment = preg_replace( '/([a-z])([0-9])/', '$1-$2', $segment );
		$segment = preg_replace( '/([0-9])([a-z])/', '$1-$2', $segment );

		return trim( preg_replace( '/-+/', '-', $segment ), '-' );
	}
}

if ( ! function_exists( 'wonder_token_bridge_wp_path' ) ) {
	/**
	 * WordPress variable path for a flattened Static Kit path.
	 *
	 * `h2--size-tablet` becomes `h-2--size-tablet`.
	 *
	 * @param String $path A path from wonder_token_bridge_flatten() or a preset slug.
	 * @return String
	 */
	function wonder_token_bridge_wp_path( $path ) {
		return implode( '--', array_map( 'wonder_token_bridge_wp_segment', explode( '--', $path ) ) );
	}
}

if ( ! function_exists( 'wonder_token_bridge_flatten' ) ) {
	/**
	 * Flatten a settings tree into WordPress custom-property paths.
	 *
	 * `array( 'h2' => array( 'sizeTablet' => '3rem' ) )` becomes
	 * `array( 'h2--size-tablet' )`. The level separator is `--`, which is what
	 * `WP_Theme_JSON::flatten_tree()` writes between keys.
	 *
	 * @param Array  $tree   Nested settings. Leaves are the values.
	 * @param String $prefix Path already flattened, with a trailing `--` when nested.
	 * @return String[] Leaf paths, without a leading `--wp--custom--`.
	 */
	function wonder_token_bridge_flatten( $tree, $prefix = '' ) {
		$paths = array();

		if ( ! is_array( $tree ) ) {
			return $paths;
		}

		foreach ( $tree as $property => $value ) {
			$segment = str_replace( '/', '-', wonder_token_bridge_kebab( $property ) );
			$path    = $prefix . $segment;

			if ( is_array( $value ) ) {
				$paths = array_merge( $paths, wonder_token_bridge_flatten( $value, $path . '--' ) );
				continue;
			}

			$paths[] = $path;
		}

		return $paths;
	}
}

if ( ! function_exists( 'wonder_token_bridge_preset_slugs' ) ) {
	/**
	 * Slugs from a preset list, or from the theme origin of an origin-keyed list.
	 *
	 * `wp_get_global_settings()` returns palettes and font families keyed by
	 * origin (`theme`, `default`, `custom`). A raw theme.json file stores a
	 * flat list. Core's `default` bucket is never bridged: Static Kit does not
	 * consume those names, and `defaultPalette` does not always stop WordPress
	 * from merging them into the settings array.
	 *
	 * @param Mixed $presets A flat preset list, or an origin-keyed array.
	 * @return String[]
	 */
	function wonder_token_bridge_preset_slugs( $presets ) {
		if ( ! is_array( $presets ) || array() === $presets ) {
			return array();
		}

		$origins = array( 'theme', 'default', 'custom' );
		$keys    = array_keys( $presets );
		$keyed   = array() === array_diff( $keys, $origins );

		if ( $keyed ) {
			$presets = isset( $presets['theme'] ) && is_array( $presets['theme'] ) ? $presets['theme'] : array();
		}

		$slugs = array();

		foreach ( $presets as $preset ) {
			if ( ! is_array( $preset ) || ! isset( $preset['slug'] ) || ! is_string( $preset['slug'] ) ) {
				continue;
			}

			$slug = wonder_token_bridge_kebab( $preset['slug'] );
			if ( '' === $slug ) {
				continue;
			}

			$slugs[] = $slug;
		}

		return $slugs;
	}
}

if ( ! function_exists( 'wonder_token_bridge_css' ) ) {
	/**
	 * Build the `:root` rule that aliases kit names to WordPress variables.
	 *
	 * Reads three roots only. `fontSizes` and `spacingSizes` are the editor
	 * ladders, not Static Kit tokens, and are left alone.
	 *
	 * - `color.palette` slug `blue` → `--color-blue: var(--wp--preset--color--blue)`
	 * - `typography.fontFamilies` slug `sans-serif` → `--font-sans-serif: var(--wp--preset--font-family--sans-serif)`
	 * - `custom.type` leaf `h2.sizeTablet` → `--type-h2-size-tablet: var(--wp--custom--type--h2--size-tablet)`
	 * - `custom.color` leaf `error` → `--color-error: var(--wp--custom--color--error)`
	 *
	 * @param Array $settings `settings` from theme.json, or the array returned by `wp_get_global_settings()`.
	 * @return String CSS, or an empty string when there is nothing to bridge.
	 */
	function wonder_token_bridge_css( $settings ) {
		if ( ! is_array( $settings ) ) {
			return '';
		}

		$declarations = array();

		$palette = array();
		if ( isset( $settings['color']['palette'] ) ) {
			$palette = wonder_token_bridge_preset_slugs( $settings['color']['palette'] );
		}

		foreach ( $palette as $slug ) {
			$declarations[] = '--color-' . $slug . ': var(--wp--preset--color--' . wonder_token_bridge_wp_path( $slug ) . ');';
		}

		$families = array();
		if ( isset( $settings['typography']['fontFamilies'] ) ) {
			$families = wonder_token_bridge_preset_slugs( $settings['typography']['fontFamilies'] );
		}

		foreach ( $families as $slug ) {
			$declarations[] = '--font-' . $slug . ': var(--wp--preset--font-family--' . wonder_token_bridge_wp_path( $slug ) . ');';
		}

		$type = array();
		if ( isset( $settings['custom']['type'] ) && is_array( $settings['custom']['type'] ) ) {
			$type = wonder_token_bridge_flatten( $settings['custom']['type'] );
		}

		foreach ( $type as $path ) {
			$name           = str_replace( '--', '-', $path );
			$declarations[] = '--type-' . $name . ': var(--wp--custom--type--' . wonder_token_bridge_wp_path( $path ) . ');';
		}

		$colors = array();
		if ( isset( $settings['custom']['color'] ) && is_array( $settings['custom']['color'] ) ) {
			$colors = wonder_token_bridge_flatten( $settings['custom']['color'] );
		}

		foreach ( $colors as $path ) {
			$name           = str_replace( '--', '-', $path );
			$declarations[] = '--color-' . $name . ': var(--wp--custom--color--' . wonder_token_bridge_wp_path( $path ) . ');';
		}

		if ( array() === $declarations ) {
			return '';
		}

		return ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}\n";
	}
}

if ( ! function_exists( 'wonder_enqueue_token_bridge' ) ) {
	/**
	 * Attach the token bridge to a stylesheet handle already on the page.
	 *
	 * The Static Kit bundle is the consumer, so the rule rides on that handle
	 * rather than on `global-styles`. Projects that supply their own mapping
	 * can decline the output:
	 *
	 *     add_filter( 'wonderpress_print_token_bridge', '__return_false' );
	 *
	 * @param String $handle Registered stylesheet handle.
	 * @return void
	 */
	function wonder_enqueue_token_bridge( $handle ) {
		/**
		 * Filter whether the Static Kit token bridge is printed.
		 *
		 * @param Boolean $print True to print the `:root` bridge.
		 */
		if ( ! apply_filters( 'wonderpress_print_token_bridge', true ) ) {
			return;
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return;
		}

		$settings = wp_get_global_settings( array(), array( 'origin' => 'base' ) );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$css = wonder_token_bridge_css( $settings );
		if ( '' === $css ) {
			return;
		}

		wp_add_inline_style( $handle, $css );
	}
}
