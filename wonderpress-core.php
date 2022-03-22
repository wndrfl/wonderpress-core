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

require_once plugin_dir_path( __FILE__ ) . WONDERPRESS_CORE_DIRECTORY_NAME . '/init.php';