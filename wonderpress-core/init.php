<?php
/**
 * Initialize the Wonderpress Core.
 *
 * This file is the package's single entry point: Composer requires it through
 * the `autoload.files` key.
 *
 * There is no plugin bootstrap file beside it any more. One existed so the
 * package could be dropped into wp-content/mu-plugins, which is how core was
 * installed before 2.0.0 — but a site installed that way already has its own
 * copy of that file from the version it installed, and never receives this
 * one. It protected nothing and implied an install path that is no longer
 * supported.
 *
 * @package Wonderpress Core
 */

/**
 * Bail when this file is reached outside WordPress.
 *
 * `return`, not `exit`. Composer lists this file under `autoload.files`, so it
 * runs on every `require vendor/autoload.php` — including inside this package's
 * own repository, where there is no WordPress and no ABSPATH. `exit` there
 * terminated the host process silently: phpcs, and any other Composer-
 * autoloaded tool, produced no output and returned 0, which reads exactly like
 * a clean run.
 *
 * `return` stops this file without touching the caller, which is the whole of
 * what the guard is for. A direct web request still executes nothing.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * The version of this copy of the package.
 *
 * A local first, because the guard below has to compare it against whatever
 * copy may already have loaded before deciding whether to say anything.
 */
$wonderpress_core_this_version = '2.2.0';

/**
 * Stand down when another copy of the package has already booted.
 *
 * A site part-way through the move from mu-plugin to theme dependency can have
 * core on disk twice: once in mu-plugins, once in the theme's vendor
 * directory. The `wonder_*` functions all guard themselves with
 * function_exists(), but the define() calls and the autoloader registration
 * below do not, so a second load would emit notices and register a redundant
 * autoloader.
 *
 * First loader wins, and it has to: by the time this runs the other copy has
 * already declared its functions, and PHP cannot redeclare them. WordPress
 * loads mu-plugins long before any theme, so on a site that still has one it
 * is always the mu-plugin copy that wins — even when it is the older of the
 * two, and even when the theme now depends on something only the newer one
 * provides. That combination produces a site with no compiled CSS or JS and
 * none of the baseline theme supports, breaking quietly and in a place nobody
 * would think to look. So when the copy that won is older than this one, say
 * so where an administrator will see it.
 */
if ( defined( 'WONDERPRESS_CORE_PATH' ) ) {

	$wonderpress_core_loaded_version = defined( 'WONDERPRESS_CORE_VERSION' ) ? WONDERPRESS_CORE_VERSION : '0.0.0';

	if ( version_compare( $wonderpress_core_loaded_version, $wonderpress_core_this_version, '<' ) ) {

		define( 'WONDERPRESS_CORE_SUPERSEDED_BY', $wonderpress_core_this_version );

		if ( ! function_exists( 'wonder_core_stale_copy_notice' ) ) {
			/**
			 * Warn that an older copy of the package loaded first and won.
			 *
			 * @return void
			 */
			function wonder_core_stale_copy_notice() {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: version that loaded, 2: version that stood down, 3: path of the copy that loaded */
							__( 'An older Wonderpress Core (%1$s) loaded first and takes precedence over the copy this theme depends on (%2$s), so compiled assets, theme supports and blocks may be missing. Delete the old copy at %3$s — it is no longer the supported install location.', 'wonderpress' ),
							defined( 'WONDERPRESS_CORE_VERSION' ) ? WONDERPRESS_CORE_VERSION : 'unknown',
							WONDERPRESS_CORE_SUPERSEDED_BY,
							WONDERPRESS_CORE_PATH
						)
					)
				);
			}

			add_action( 'admin_notices', 'wonder_core_stale_copy_notice' );
		}
	}

	return;
}

/**
 * The version of this package.
 *
 * Lives here rather than in a plugin bootstrap file so that it is defined on
 * every install path, including the Composer one.
 */
define( 'WONDERPRESS_CORE_VERSION', $wonderpress_core_this_version );

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
		$relative_path = ltrim( wp_normalize_path( $relative_path ), '/' );

		// Traversal is never legitimate for a file shipped inside this package,
		// and a `..` segment would walk straight past the wp-content check
		// below, which compares strings rather than resolved paths.
		//
		// Resolved paths are not an option here: realpath() follows symlinks,
		// and a Composer `path` repository — how an unreleased core is tested —
		// symlinks the package to a checkout that is usually outside wp-content
		// entirely. Rejecting `..` lexically keeps both cases honest.
		if ( in_array( '..', explode( '/', $relative_path ), true ) ) {
			return '';
		}

		$path        = wp_normalize_path( WONDERPRESS_CORE_PATH ) . $relative_path;
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// A package installed outside wp-content has no servable URL, and
		// guessing one would produce a 404 rather than an honest failure.
		// PHP resolves symlinks in __FILE__, so a symlinked `path` checkout
		// lands here too: its assets genuinely are not reachable over HTTP.
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
 * Import PHP files from ./inc/ directory.
 *
 * Template manifests load before ACF so composition helpers exist when field
 * groups are built (glob order would load acf.php first).
 */
$wonderpress_core_inc = __DIR__ . DIRECTORY_SEPARATOR . 'inc';
require_once $wonderpress_core_inc . DIRECTORY_SEPARATOR . 'template-manifests.php';
require_once $wonderpress_core_inc . DIRECTORY_SEPARATOR . 'manifest-property.php';
foreach ( glob( $wonderpress_core_inc . DIRECTORY_SEPARATOR . '*.php' ) as $wonderpress_core_inc_file ) {
	$wonderpress_core_inc_basename = basename( $wonderpress_core_inc_file );
	if ( 'template-manifests.php' === $wonderpress_core_inc_basename || 'manifest-property.php' === $wonderpress_core_inc_basename ) {
		continue;
	}
	require_once $wonderpress_core_inc_file;
}
