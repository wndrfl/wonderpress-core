<?php
/**
 * Normalize block attributes to match ACF wire shapes before partial hydration.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_normalize_image_value' ) ) {
	/**
	 * Expand attachment references to the canonical image array partials expect.
	 *
	 * @param mixed $value Raw block attribute (int ID, partial array, or empty).
	 * @return array|null|null-shaped empty when invalid.
	 */
	function wonder_normalize_image_value( $value ) {
		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			$value = array( 'ID' => (int) $value );
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		$id = 0;
		if ( ! empty( $value['ID'] ) ) {
			$id = (int) $value['ID'];
		} elseif ( ! empty( $value['id'] ) ) {
			$id = (int) $value['id'];
		}

		if ( ! $id ) {
			return null;
		}

		if ( function_exists( 'acf_get_attachment' ) ) {
			$attachment = acf_get_attachment( $id );
			if ( is_array( $attachment ) && ! empty( $attachment['ID'] ) ) {
				return $attachment;
			}
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			return null;
		}

		$url = wp_get_attachment_url( $id );
		if ( ! $url ) {
			return null;
		}

		$meta   = wp_get_attachment_metadata( $id );
		$width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$alt    = get_post_meta( $id, '_wp_attachment_image_alt', true );

		$sizes = array();
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$upload_dir = wp_get_upload_dir();
			$base_url   = trailingslashit( $upload_dir['baseurl'] );
			$dir        = isset( $meta['file'] ) ? dirname( $meta['file'] ) : '';
			$dir        = ( $dir && '.' !== $dir ) ? trailingslashit( $dir ) : '';

			foreach ( $meta['sizes'] as $size_name => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}
				$sizes[ $size_name ] = array(
					'url'    => $base_url . $dir . $size_data['file'],
					'width'  => isset( $size_data['width'] ) ? (int) $size_data['width'] : 0,
					'height' => isset( $size_data['height'] ) ? (int) $size_data['height'] : 0,
				);
			}
		}

		return array(
			'ID'     => $id,
			'id'     => $id,
			'url'    => $url,
			'alt'    => is_string( $alt ) ? $alt : '',
			'width'  => $width,
			'height' => $height,
			'sizes'  => $sizes,
			'type'   => 'image',
		);
	}
}

if ( ! function_exists( 'wonder_normalize_link_value' ) ) {
	/**
	 * Coerce block-stored link data to the simple four-field ACF group shape.
	 *
	 * @param mixed $value Raw block attribute.
	 * @return array|null
	 */
	function wonder_normalize_link_value( $value ) {
		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return null;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		$content = isset( $value['content'] ) ? (string) $value['content'] : '';
		$url     = isset( $value['url'] ) ? (string) $value['url'] : '';
		$title   = isset( $value['title'] ) ? (string) $value['title'] : '';

		if ( '' === $content && '' === $url && '' === $title ) {
			return null;
		}

		return array(
			'content'         => $content,
			'url'             => $url,
			'open_in_new_tab' => ! empty( $value['open_in_new_tab'] ),
			'title'           => $title,
		);
	}
}

if ( ! function_exists( 'wonder_normalize_post_object_value' ) ) {
	/**
	 * Reduce block-stored post references to a post ID (flat partial property).
	 *
	 * @param mixed $value Raw block attribute.
	 * @return int|null
	 */
	function wonder_normalize_post_object_value( $value ) {
		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			$id = (int) $value;
			return $id > 0 ? $id : null;
		}

		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			if ( ! empty( $value['ID'] ) ) {
				$id = (int) $value['ID'];
				return $id > 0 ? $id : null;
			}
			if ( ! empty( $value['id'] ) ) {
				$id = (int) $value['id'];
				return $id > 0 ? $id : null;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'wonder_normalize_repeater_value' ) ) {
	/**
	 * Normalize repeater rows; nested values follow sub-property types.
	 *
	 * @param mixed                $value    Raw block attribute (array of rows).
	 * @param array<string, mixed> $prop_def Repeater manifest property.
	 * @return array<int, array<string, mixed>>
	 */
	function wonder_normalize_repeater_value( $value, array $prop_def ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sub_props = ! empty( $prop_def['properties'] ) && is_array( $prop_def['properties'] )
			? $prop_def['properties']
			: array();

		if ( ! $sub_props ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$normalized_row = array();

			foreach ( $sub_props as $sub ) {
				if ( empty( $sub['name'] ) || empty( $sub['type'] ) ) {
					continue;
				}

				$sub_key = (string) $sub['name'];
				if ( ! array_key_exists( $sub_key, $row ) ) {
					continue;
				}

				$normalized_row[ $sub_key ] = wonder_normalize_property_value(
					(string) $sub['type'],
					$row[ $sub_key ],
					is_array( $sub ) ? $sub : array()
				);
			}

			if ( $normalized_row ) {
				$normalized[] = $normalized_row;
			}
		}

		return $normalized;
	}
}

if ( ! function_exists( 'wonder_normalize_property_value' ) ) {
	/**
	 * Normalize one manifest property value from block storage.
	 *
	 * @param string               $type     Manifest property type.
	 * @param mixed                $value    Raw attribute value.
	 * @param array<string, mixed> $prop_def Manifest property definition.
	 * @return mixed
	 */
	function wonder_normalize_property_value( $type, $value, array $prop_def = array() ) {
		switch ( $type ) {
			case 'image':
				return wonder_normalize_image_value( $value );
			case 'link':
				return wonder_normalize_link_value( $value );
			case 'post_object':
				return wonder_normalize_post_object_value( $value );
			case 'repeater':
				return wonder_normalize_repeater_value( $value, $prop_def );
			default:
				return $value;
		}
	}
}

if ( ! function_exists( 'wonder_manifest_properties_for_block' ) ) {
	/**
	 * Property definitions for a registered block name.
	 *
	 * @param string $block_name e.g. wonderpress/scalar-demo.
	 * @return array<int, array<string, mixed>>
	 */
	function wonder_manifest_properties_for_block( $block_name ) {
		if ( ! function_exists( 'wonder_load_theme_manifests' ) ) {
			return array();
		}

		foreach ( wonder_load_theme_manifests() as $manifest ) {
			if ( empty( $manifest['block'] ) || (string) $manifest['block'] !== (string) $block_name ) {
				continue;
			}
			if ( empty( $manifest['properties'] ) || ! is_array( $manifest['properties'] ) ) {
				return array();
			}
			return $manifest['properties'];
		}

		return array();
	}
}

if ( ! function_exists( 'wonder_normalize_block_attributes' ) ) {
	/**
	 * Normalize block attributes using the partial manifest for that block.
	 *
	 * @param array<string, mixed> $attributes Block attributes from render callback.
	 * @param string               $block_name Registered block name.
	 * @return array<string, mixed>
	 */
	function wonder_normalize_block_attributes( array $attributes, $block_name ) {
		$properties = wonder_manifest_properties_for_block( $block_name );

		if ( ! $properties ) {
			return $attributes;
		}

		foreach ( $properties as $prop ) {
			if ( empty( $prop['name'] ) || empty( $prop['type'] ) ) {
				continue;
			}

			$key = (string) $prop['name'];
			if ( ! array_key_exists( $key, $attributes ) ) {
				continue;
			}

			$attributes[ $key ] = wonder_normalize_property_value(
				(string) $prop['type'],
				$attributes[ $key ],
				is_array( $prop ) ? $prop : array()
			);
		}

		return $attributes;
	}
}
