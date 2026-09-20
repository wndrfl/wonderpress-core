<?php
/**
 * Shared manifest property semantics (ACF + block editor).
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_manifest_property_post_types' ) ) {
	/**
	 * Post types a post_object property may reference.
	 *
	 * @param array<string, mixed> $prop Manifest property.
	 * @return string[]
	 */
	function wonder_manifest_property_post_types( array $prop ) {
		$post_type = null;

		if ( ! empty( $prop['post_type'] ) ) {
			$post_type = $prop['post_type'];
		}

		if ( null === $post_type ) {
			return array( 'post' );
		}

		if ( is_string( $post_type ) ) {
			return array( $post_type );
		}

		if ( is_array( $post_type ) ) {
			return array_values(
				array_filter(
					array_map( 'strval', $post_type ),
					static function ( $slug ) {
						return '' !== $slug;
					}
				)
			);
		}

		return array( 'post' );
	}
}

if ( ! function_exists( 'wonder_manifest_property_string_is_textarea' ) ) {
	/**
	 * Whether a string property uses a textarea in ACF and the block inspector.
	 *
	 * @param array<string, mixed> $prop Manifest property.
	 * @return bool
	 */
	function wonder_manifest_property_string_is_textarea( array $prop ) {
		if ( ! empty( $prop['format'] ) && 'textarea' === $prop['format'] ) {
			return true;
		}

		if ( ! empty( $prop['rows'] ) ) {
			return true;
		}

		if ( function_exists( 'wonder_acf_is_textarea_name' ) && ! empty( $prop['name'] ) ) {
			return wonder_acf_is_textarea_name( (string) $prop['name'] );
		}

		return false;
	}
}

if ( ! function_exists( 'wonder_manifest_property_string_rows' ) ) {
	/**
	 * Optional textarea rows for a string property.
	 *
	 * @param array<string, mixed> $prop Manifest property.
	 * @return int|null
	 */
	function wonder_manifest_property_string_rows( array $prop ) {
		if ( empty( $prop['rows'] ) ) {
			return null;
		}

		$rows = (int) $prop['rows'];
		return $rows > 0 ? $rows : null;
	}
}

if ( ! function_exists( 'wonder_block_editor_schema_entry_from_property' ) ) {
	/**
	 * One block editor schema property entry from a manifest property.
	 *
	 * @param array<string, mixed> $prop Manifest property.
	 * @return array<string, mixed>|null
	 */
	function wonder_block_editor_schema_entry_from_property( array $prop ) {
		if ( empty( $prop['name'] ) || empty( $prop['type'] ) ) {
			return null;
		}

		$entry = array(
			'name' => (string) $prop['name'],
			'type' => (string) $prop['type'],
		);

		if ( ! empty( $prop['label'] ) && is_string( $prop['label'] ) ) {
			$entry['label'] = $prop['label'];
		}

		if ( isset( $prop['description'] ) ) {
			$entry['description'] = (string) $prop['description'];
		}

		if ( ! empty( $prop['required'] ) ) {
			$entry['required'] = true;
		}

		if ( ! empty( $prop['choices'] ) && is_array( $prop['choices'] ) ) {
			$entry['choices'] = $prop['choices'];
		}

		if ( ! empty( $prop['when'] ) && is_array( $prop['when'] ) ) {
			$entry['when'] = $prop['when'];
		}

		if ( ! empty( $prop['post_type'] ) ) {
			$entry['post_type'] = $prop['post_type'];
		}

		if ( ! empty( $prop['format'] ) && is_string( $prop['format'] ) ) {
			$entry['format'] = $prop['format'];
		}

		if ( ! empty( $prop['rows'] ) ) {
			$entry['rows'] = (int) $prop['rows'];
		}

		if ( 'repeater' === $prop['type'] && ! empty( $prop['properties'] ) && is_array( $prop['properties'] ) ) {
			$sub_entries = array();
			foreach ( $prop['properties'] as $sub ) {
				$sub_entry = wonder_block_editor_schema_entry_from_property( $sub );
				if ( $sub_entry ) {
					$sub_entries[] = $sub_entry;
				}
			}
			if ( $sub_entries ) {
				$entry['properties'] = $sub_entries;
			}
		}

		return $entry;
	}
}
