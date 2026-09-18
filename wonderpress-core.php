<?php
/**
 * Plugin Name: Wonderpress Core
 * Description: A toolkit layer for awesome WordPress development and management.
 * Version: 2.0.0
 * Author: Wonderful
 * Author URI: https://wonderful.io
 *
 * Back-compatibility stub for sites that still carry this package in
 * wp-content/mu-plugins. As of 2.0.0 the supported install is a Composer
 * dependency of the theme, which loads `wonderpress-core/init.php` directly
 * through `autoload.files` and never reads this file.
 *
 * Keep it: deleting it would leave existing mu-plugin installs with a
 * directory WordPress no longer boots, because WordPress only auto-loads
 * top-level PHP files in mu-plugins.
 *
 * @package Wonderpress Core
 **/

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'WONDERPRESS_CORE_DIRECTORY_NAME', 'wonderpress-core' );

// init.php defines WONDERPRESS_CORE_VERSION and returns early when a copy of
// the package has already booted, so requiring it twice is harmless.
require_once plugin_dir_path( __FILE__ ) . WONDERPRESS_CORE_DIRECTORY_NAME . '/init.php';
