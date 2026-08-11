<?php
/**
 * Initialize the Wonderpress Core.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * The absolute path to this directory, used by the partial classes to locate
 * their fallback view templates when a theme does not override them.
 */
define( 'WONDERPRESS_CORE_PATH', plugin_dir_path( __FILE__ ) );

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
