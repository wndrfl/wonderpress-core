<?php
/**
 * Load page-template manifests from `.wonderpress/manifest/page-templates/`.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_template_manifest_schema_version' ) ) {
	/**
	 * Supported template manifest schema version.
	 *
	 * @return int
	 */
	function wonder_template_manifest_schema_version() {
		return 1;
	}
}

if ( ! function_exists( 'wonder_theme_template_manifests' ) ) {
	/**
	 * Parsed template manifests keyed by WordPress template slug.
	 *
	 * @param array|null $add  Manifest to store.
	 * @param bool       $mark Mark scan complete.
	 * @return array|null
	 */
	function wonder_theme_template_manifests( $add = null, $mark = false ) {
		static $manifests = array();
		static $ran       = false;

		if ( $mark ) {
			$ran = true;
		}

		if ( is_array( $add ) && ! empty( $add['template'] ) && is_string( $add['template'] ) ) {
			$manifests[ $add['template'] ] = $add;
		}

		return $ran ? $manifests : null;
	}
}

if ( ! function_exists( 'wonder_parse_template_manifest_data' ) ) {
	/**
	 * Validate parsed JSON; return manifest array or null.
	 *
	 * @param array  $data Parsed JSON.
	 * @param string $path Source path for diagnostics.
	 * @return array|null
	 */
	function wonder_parse_template_manifest_data( $data, $path = '' ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$supported = wonder_template_manifest_schema_version();
		if ( empty( $data['schemaVersion'] ) || (int) $data['schemaVersion'] !== $supported ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $path ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: file path, 2: supported schema version */
						esc_html__( 'Template manifest "%1$s" has an unsupported or missing schemaVersion (supported: %2$d).', 'wonderpress' ),
						esc_html( $path ),
						(int) $supported
					),
					'2.2.0'
				);
			}
			return null;
		}

		if ( empty( $data['template'] ) || ! is_string( $data['template'] ) ) {
			return null;
		}

		return $data;
	}
}

if ( ! function_exists( 'wonder_load_template_manifests' ) ) {
	/**
	 * Scan `.wonderpress/manifest/page-templates/*.json` in the active theme.
	 *
	 * @return array[] Keyed by template slug.
	 */
	function wonder_load_template_manifests() {
		if ( is_array( wonder_theme_template_manifests() ) ) {
			return wonder_theme_template_manifests();
		}

		wonder_theme_template_manifests( null, true );

		$dir = get_stylesheet_directory() . DIRECTORY_SEPARATOR . '.wonderpress' . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'page-templates';

		if ( ! is_dir( $dir ) ) {
			return wonder_theme_template_manifests();
		}

		foreach ( glob( $dir . DIRECTORY_SEPARATOR . '*.json' ) as $path ) {
			$raw = file_get_contents( $path );
			if ( false === $raw ) {
				continue;
			}

			$data = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					_doing_it_wrong(
						__FUNCTION__,
						sprintf(
							/* translators: 1: file path, 2: JSON error message */
							esc_html__( 'Template manifest "%1$s" is not valid JSON (%2$s). Core ignores the file; partial field groups may fall back to wonderpress_template_fields.', 'wonderpress' ),
							esc_html( $path ),
							esc_html( json_last_error_msg() )
						),
						'2.2.0'
					);
				}
				continue;
			}

			$parsed = wonder_parse_template_manifest_data( $data, $path );
			if ( $parsed ) {
				wonder_theme_template_manifests( $parsed );
			}
		}

		return wonder_theme_template_manifests();
	}
}

if ( ! function_exists( 'wonder_normalize_page_template_slug' ) ) {
	/**
	 * Normalize a template slug for comparisons.
	 *
	 * WordPress stores `_wp_page_template` as `default`, empty, or a theme-relative
	 * path such as `template-landing.php`. We compare on basename.
	 *
	 * @param string $slug Raw slug or path.
	 * @return string Basename or `default`.
	 */
	function wonder_normalize_page_template_slug( $slug ) {
		if ( ! is_string( $slug ) || '' === $slug || 'default' === $slug ) {
			return 'default';
		}

		$slug = str_replace( '\\', '/', $slug );
		return basename( $slug );
	}
}

if ( ! function_exists( 'wonder_template_manifest' ) ) {
	/**
	 * One template manifest by WordPress template slug.
	 *
	 * @param string $template_slug e.g. `template-landing.php`.
	 * @return array|null
	 */
	function wonder_template_manifest( $template_slug ) {
		$needle = wonder_normalize_page_template_slug( $template_slug );
		if ( 'default' === $needle ) {
			return null;
		}

		foreach ( wonder_load_template_manifests() as $manifest ) {
			if ( empty( $manifest['template'] ) ) {
				continue;
			}
			if ( wonder_normalize_page_template_slug( $manifest['template'] ) === $needle ) {
				return $manifest;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'wonder_page_template_slug' ) ) {
	/**
	 * Normalized page template slug for a post.
	 *
	 * @param WP_Post|null $post Post.
	 * @return string Template basename or `default`.
	 */
	function wonder_page_template_slug( $post = null ) {
		if ( ! $post instanceof WP_Post ) {
			return 'default';
		}

		$slug = get_page_template_slug( $post );
		if ( ( ! is_string( $slug ) || '' === $slug ) && $post->ID ) {
			$slug = get_post_meta( $post->ID, '_wp_page_template', true );
		}

		return wonder_normalize_page_template_slug( is_string( $slug ) ? $slug : '' );
	}
}

if ( ! function_exists( 'wonder_template_manifest_for_post' ) ) {
	/**
	 * Template manifest for a page being edited or viewed.
	 *
	 * @param WP_Post|null $post Post.
	 * @return array|null
	 */
	function wonder_template_manifest_for_post( $post = null ) {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$slug = wonder_page_template_slug( $post );
		if ( 'default' === $slug ) {
			return null;
		}

		return wonder_template_manifest( $slug );
	}
}

if ( ! function_exists( 'wonder_template_locks_from_manifests' ) ) {
	/**
	 * Lock levels declared on template manifests.
	 *
	 * @return array<string, string|bool>
	 */
	function wonder_template_locks_from_manifests() {
		$locks = array();

		foreach ( wonder_load_template_manifests() as $manifest ) {
			if ( empty( $manifest['editor'] ) || ! is_array( $manifest['editor'] ) ) {
				continue;
			}
			if ( ! array_key_exists( 'lock', $manifest['editor'] ) ) {
				continue;
			}

			$key = wonder_normalize_page_template_slug( $manifest['template'] );
			if ( 'default' !== $key ) {
				$locks[ $key ] = $manifest['editor']['lock'];
			}
		}

		return $locks;
	}
}

if ( ! function_exists( 'wonder_template_fields_from_manifests' ) ) {
	/**
	 * Partial slugs referenced in template composition, keyed by template.
	 *
	 * @return array<string, string[]>
	 */
	function wonder_template_fields_from_manifests() {
		$map = array();

		foreach ( wonder_load_template_manifests() as $manifest ) {
			if ( empty( $manifest['composition'] ) || ! is_array( $manifest['composition'] ) ) {
				continue;
			}

			$template = $manifest['template'];
			foreach ( $manifest['composition'] as $row ) {
				if ( empty( $row['partial'] ) || ! is_string( $row['partial'] ) ) {
					continue;
				}
				if ( ! isset( $map[ $template ] ) ) {
					$map[ $template ] = array();
				}
				if ( ! in_array( $row['partial'], $map[ $template ], true ) ) {
					$map[ $template ][] = $row['partial'];
				}
			}
		}

		return $map;
	}
}

if ( ! function_exists( 'wonder_partial_in_any_composition' ) ) {
	/**
	 * Whether a partial slug appears in any template composition row.
	 *
	 * @param string $partial_slug Partial slug.
	 * @return bool
	 */
	function wonder_partial_in_any_composition( $partial_slug ) {
		foreach ( wonder_load_template_manifests() as $manifest ) {
			if ( empty( $manifest['composition'] ) || ! is_array( $manifest['composition'] ) ) {
				continue;
			}
			foreach ( $manifest['composition'] as $row ) {
				if ( isset( $row['partial'] ) && $partial_slug === $row['partial'] ) {
					return true;
				}
			}
		}
		return false;
	}
}
