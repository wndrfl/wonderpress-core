<?php
/**
 * The theme manifest scan reads manifests and nothing else.
 *
 * The CLI keeps each partial's sync lock beside its manifest as
 * `<slug>.lock.json`, so the directory this scans holds both.
 *
 * Run: php wonderpress-core/tests/theme-manifest-scan-test.php
 */

define( 'ABSPATH', '/tmp' );

$theme_dir   = sys_get_temp_dir() . '/wonder-manifest-scan-' . getmypid();
$partial_dir = $theme_dir . '/.wonderpress/manifest/partials';
mkdir( $partial_dir, 0777, true );

register_shutdown_function(
	function () use ( $theme_dir ) {
		foreach ( array_reverse( iterator_to_array(
			new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $theme_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			)
		) ) as $path => $info ) {
			$info->isDir() ? rmdir( $path ) : unlink( $path );
		}
		rmdir( $theme_dir );
	}
);

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory() {
		global $wonder_test_theme_dir;
		return $wonder_test_theme_dir;
	}
}
$GLOBALS['wonder_test_theme_dir'] = $theme_dir;

file_put_contents(
	$partial_dir . '/hero.json',
	wonder_test_json( array(
		'name'       => 'Hero',
		'slug'       => 'hero',
		'properties' => array(
			array(
				'name' => 'title',
				'type' => 'string',
			),
		),
	) )
);

// A lock carries hashes and the verbatim content of what sync last wrote. It
// has no `slug`, so it was skipped even before the scan learned to ignore it —
// this test is that it is never opened in the first place.
$lock_path = $partial_dir . '/hero.lock.json';
file_put_contents(
	$lock_path,
	wonder_test_json( array(
		'manifest'          => str_repeat( 'a', 64 ),
		'classFence'        => str_repeat( 'b', 64 ),
		'classFenceContent' => "// <wonderpress:generated>\n\t// ...\n\t// </wonderpress:generated>",
		'cliVersion'        => '2.17.0',
	) )
);

// Unreadable: file_get_contents() on it warns and returns false, so a scan
// that opens the lock is loud about it. Chmod is a no-op for root, so the
// assertions below stand on their own too.
chmod( $lock_path, 0000 );

$warnings = array();
set_error_handler(
	function ( $errno, $errstr ) use ( &$warnings ) {
		$warnings[] = $errstr;
		return true;
	}
);

require dirname( __DIR__ ) . '/inc/manifests.php';

$manifests = wonder_load_theme_manifests();

restore_error_handler();
chmod( $lock_path, 0644 );

assert( array() === $warnings, 'the scan opened no file it could not read: ' . implode( '; ', $warnings ) );
assert( isset( $manifests['hero'] ), 'the hero manifest is loaded' );
assert( 'Hero' === $manifests['hero']['name'], 'the hero manifest is the one that was loaded' );

// The lock must not become an entry under any key: not its own name, and not
// by overwriting the partial it belongs to.
assert( ! isset( $manifests['hero.lock'] ), 'the lock is not a partial named "hero.lock"' );
foreach ( $manifests as $slug => $manifest ) {
	assert( ! isset( $manifest['classFence'] ), "no lock was loaded as the manifest for {$slug}" );
}

fwrite( STDOUT, "theme-manifest-scan-test.php OK\n" );

/**
 * Pretty-print JSON the way the CLI writes it.
 *
 * @param array $data Data to encode.
 * @return string
 */
function wonder_test_json( $data ) {
	return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
}
