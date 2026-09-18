<?php
/**
 * Initialize the Wonderpress Core.
 *
 * This file is the package's single entry point. Composer requires it through
 * the `autoload.files` key, and the legacy `wonderpress-core.php` mu-plugin
 * stub requires it directly, so it must be safe to reach by either route.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stand down when another copy of the package has already booted.
 *
 * A site part-way through the move from mu-plugin to theme dependency can have
 * core on disk twice: once in mu-plugins, once in the theme's vendor
 * directory. The `wonder_*` functions all guard themselves with
 * function_exists(), but the define() calls and the autoloader registration
 * below do not, so a second load would emit notices and register a redundant
 * autoloader. First loader wins; mu-plugins loads first, so an un-migrated
 * site keeps the behaviour it had.
 */
if ( defined( 'WONDERPRESS_CORE_PATH' ) ) {
	return;
}

/**
 * The version of this package.
 *
 * Lives here rather than in the mu-plugin stub so that it survives the move
 * into a theme's vendor directory, where that stub is not loaded at all.
 */
define( 'WONDERPRESS_CORE_VERSION', '2.0.0' );

/**
 * The absolute path to this directory, used by the partial classes to locate
 * their fallback view templates when a theme does not override them.
 */
define( 'WONDERPRESS_CORE_PATH', plugin_dir_path( __FILE__ ) );

if ( ! function_exists( 'wonder_core_url' ) ) {
	/**
	 * Resolve a browser-facing URL for a file shipped inside this package.
	 *
	 * WordPress's plugins_url() cannot do this job: it resolves against
	 * WP_PLUGIN_DIR, and
	 * this package may sit in mu-plugins, in a theme's Composer vendor
	 * directory, or anywhere else beneath wp-content. Mapping the file's real
	 * path onto content_url() is the one resolution that holds in every case.
	 *
	 * @param String $relative_path Path relative to this directory, without a leading slash.
	 * @return String The URL, or an empty string when the file is not under wp-content.
	 */
	function wonder_core_url( $relative_path ) {
		$path        = wp_normalize_path( WONDERPRESS_CORE_PATH . ltrim( $relative_path, '/' ) );
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// A package installed outside wp-content has no servable URL, and
		// guessing one would produce a 404 rather than an honest failure.
		if ( 0 !== strpos( $path, $content_dir ) ) {
			return '';
		}

		return content_url( substr( $path, strlen( $content_dir ) ) );
	}
}

/**
 * This auto-loads a class or trait just when you need it.
 *
 * Classes are expected to live in the `src` directory, named according to the
 * WordPress Coding Standards for PHP classes (`class-the-class-name.php`),
 * which differs from PSR-4: the namespace path is lowercased and the file
 * name gains a `class-` prefix with hyphens instead of underscores.
 *
 * See: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/#only-one-object-structure-class-interface-trait-should-be-declared-per-file
 */
spl_autoload_register(
	function ( $class_name ) {

		// Check the namespace
		$class_name_parts = explode( '\\', $class_name );
		if ( isset( $class_name_parts[0] ) && 0 === strcmp( 'Wonderpress_Core', $class_name_parts[0] ) ) {

			// Clean up the class name to reflect that of a normal PSR-4 standard
			$classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
			$class_file  = str_replace( 'Wonderpress_Core\\', '', $class_name ) . '.php';
			$class_file  = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
			$class_file  = strtolower( $class_file );

			// Convert the trailing file name into the WordPress-friendly
			// naming convention of "class-the-class-name.php".
			$class_file_parts = explode( DIRECTORY_SEPARATOR, $class_file );
			$file_name        = 'class-' . str_replace( '_', '-', array_pop( $class_file_parts ) );
			array_push( $class_file_parts, $file_name );
			$class_file = implode( DIRECTORY_SEPARATOR, $class_file_parts );

			// An autoloader must fall through quietly when it cannot resolve
			// a class, so another registered autoloader can take over.
			if ( file_exists( $classes_dir . $class_file ) ) {
				require_once $classes_dir . $class_file;
			}
		}
	}
);

if ( ! function_exists( 'wonder_require_all' ) ) {
	/**
	 * Require all files in a directory.
	 *
	 * @param String $path The path to the directory (with trailing slash).
	 */
	function wonder_require_all( $path ) {
		foreach ( glob( rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*.php' ) as $filename ) {
			require_once $filename;
		}
	}
}

/**
 * Import PHP files from ./inc/ directory
 */
wonder_require_all( __DIR__ . DIRECTORY_SEPARATOR . 'inc' );
