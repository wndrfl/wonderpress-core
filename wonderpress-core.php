<?php
/**
* Plugin Name: Wonderpress Core
* Description: A toolkit layer for awesome WordPress development and management.
* Version: 1.0.0
* Author: Wonderful
* Author URI: https://wonderful.io
**/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'WONDERPRESS_CORE_VERSION', '1.0.0');

define( 'WONDERPRESS_CORE_DIRECTORY_NAME', 'wonderpress-core' );

/**
 * This auto-loads a class or trait just when you need it.
 *
 * This function expects the class to be stored in the `src` directory
 * and named according to the official WordPress Coding Standards for PHP classes.
 *
 * See: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/#only-one-object-structure-class-interface-trait-should-be-declared-per-file
 **/
spl_autoload_register(
	function ( $class_name ) {

		// Check the namespace
		$class_name_parts = explode('\\', $class_name);
		if ( isset($class_name_parts[0]) && 0 === strcmp('Wonderpress_Core', $class_name_parts[0]) ) {

			// Clean up the class name to reflect that of a normal PSR-4 standard
			$classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . WONDERPRESS_CORE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
			$class_file  = str_replace( 'Wonderpress_Core\\', '', $class_name ) . '.php';
			$class_file  = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
			$class_file = strtolower( $class_file );

			// FOOLED YOU. The WordPress Coding Standard doesn't support true PSR-4
			// naming standards for classes. So we have to adjust the naming a little
			// further in order to find the correct file.
			// Now turn the file name into the WordPress-friendly naming convention
			// of "class-the-class-name.php"
			$class_file_parts = explode( DIRECTORY_SEPARATOR, $class_file );
			$file_name = 'class-' . str_replace( '_', '-', array_pop( $class_file_parts ) );
			array_push( $class_file_parts, $file_name );
			$class_file = implode( DIRECTORY_SEPARATOR, $class_file_parts );

			require_once $classes_dir . $class_file;
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
wonder_require_all( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . WONDERPRESS_CORE_DIRECTORY_NAME . DIRECTORY_SEPARATOR . 'inc' );