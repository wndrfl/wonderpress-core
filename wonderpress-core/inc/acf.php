<?php
/**
 * Register ACF field groups from theme partial manifests.
 *
 * Manifests are loaded in inc/manifests.php. When ACF is present this file
 * maps located, acf_compatible entries to acf_add_local_field_group() so a
 * PHP template can hydrate the same properties a block would get from
 * Gutenberg attributes.
 *
 * ACF is not a Composer dependency. If it is not loaded, registration no-ops.
 *
 * @package Wonderpress Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wonder_template_fields' ) ) {
	/**
	 * Which ACF-compatible partials each page template owns.
	 *
	 * Keyed by template slug as WordPress reports it — `page-landing.php` —
	 * with `default` standing for a page on no particular template. Values are
	 * arrays of partial slugs. Empty by default: a group with no location
	 * would appear on every page, which is how a Hero metabox and a Hero
	 * block end up on the same screen.
	 *
	 * @return array<string, string[]>
	 */
	function wonder_template_fields() {
		return (array) apply_filters( 'wonderpress_template_fields', array() );
	}
}

if ( ! function_exists( 'wonder_acf_locations_for' ) ) {
	/**
	 * Resolve ACF location rules for a partial slug.
	 *
	 * Merges optional `acf.location` from the manifest with every template
	 * in wonder_template_fields() that lists the slug. Each source is an OR
	 * group. Returns an empty array when nothing locates the partial.
	 *
	 * @param string $slug     The partial slug.
	 * @param array  $manifest The parsed manifest.
	 * @return array
	 */
	function wonder_acf_locations_for( $slug, $manifest ) {
		$groups = array();

		if ( ! empty( $manifest['acf']['location'] ) && is_array( $manifest['acf']['location'] ) ) {
			foreach ( $manifest['acf']['location'] as $group ) {
				if ( is_array( $group ) ) {
					$groups[] = $group;
				}
			}
		}

		foreach ( wonder_template_fields() as $template => $slugs ) {
			if ( ! in_array( $slug, (array) $slugs, true ) ) {
				continue;
			}

			$groups[] = array(
				array(
					'param'    => 'page_template',
					'operator' => '==',
					'value'    => $template,
				),
			);
		}

		return $groups;
	}
}

if ( ! function_exists( 'wonder_acf_humanize' ) ) {
	/**
	 * Turn a slug into a label: `call_to_action` -> "Call to action".
	 *
	 * @param string $slug A property or partial slug.
	 * @return string
	 */
	function wonder_acf_humanize( $slug ) {
		$words = strtolower( str_replace( array( '_', '-' ), ' ', (string) $slug ) );
		$words = trim( $words );
		if ( '' === $words ) {
			return '';
		}
		return strtoupper( $words[0] ) . substr( $words, 1 );
	}
}

if ( ! function_exists( 'wonder_acf_is_textarea_name' ) ) {
	/**
	 * Whether a string property wants a textarea rather than a one-line input.
	 *
	 * Matches the heuristic in editor-preview.js so the two forms agree.
	 *
	 * @param string $name Property name.
	 * @return bool
	 */
	function wonder_acf_is_textarea_name( $name ) {
		return (bool) preg_match( '/(body|content|text|quote|description|excerpt|dek)/i', (string) $name );
	}
}

if ( ! function_exists( 'wonder_acf_field_from_property' ) ) {
	/**
	 * Map one manifest property to an ACF field array.
	 *
	 * Returns null for types this slice will not guess (array, object, a
	 * nested repeater). The caller decides whether to complain.
	 *
	 * @param array  $prop       A manifest property.
	 * @param string $key_prefix Stable ACF key prefix (`field_wndr_{slug}`).
	 * @return array|null
	 */
	function wonder_acf_field_from_property( $prop, $key_prefix ) {
		if ( empty( $prop['name'] ) || empty( $prop['type'] ) ) {
			return null;
		}

		$name = $prop['name'];
		$type = $prop['type'];
		$key  = $key_prefix . '_' . $name;

		$field = array(
			'key'          => $key,
			'label'        => wonder_acf_humanize( $name ),
			'name'         => $name,
			'instructions' => isset( $prop['description'] ) ? (string) $prop['description'] : '',
			'required'     => ! empty( $prop['required'] ) ? 1 : 0,
		);

		switch ( $type ) {
			case 'string':
				$field['type'] = wonder_acf_is_textarea_name( $name ) ? 'textarea' : 'text';
				return $field;

			case 'boolean':
				$field['type'] = 'true_false';
				$field['ui']   = 1;
				return $field;

			case 'image':
				$field['type']          = 'image';
				$field['return_format'] = 'array';
				$field['preview_size']  = 'medium';
				$field['library']       = 'all';
				return $field;

			case 'link':
				$field['type']       = 'group';
				$field['layout']     = 'block';
				$field['sub_fields'] = array(
					wonder_acf_field_from_property(
						array(
							'name' => 'content',
							'type' => 'string',
						),
						$key
					),
					wonder_acf_field_from_property(
						array(
							'name' => 'url',
							'type' => 'string',
						),
						$key
					),
					wonder_acf_field_from_property(
						array(
							'name' => 'open_in_new_tab',
							'type' => 'boolean',
						),
						$key
					),
					wonder_acf_field_from_property(
						array(
							'name' => 'title',
							'type' => 'string',
						),
						$key
					),
				);
				return $field;

			case 'repeater':
				if ( empty( $prop['properties'] ) || ! is_array( $prop['properties'] ) ) {
					return null;
				}

				$sub_fields = array();
				foreach ( $prop['properties'] as $sub ) {
					if ( ! empty( $sub['type'] ) && 'repeater' === $sub['type'] ) {
						_doing_it_wrong(
							__FUNCTION__,
							sprintf(
								/* translators: %s: property name */
								esc_html__( 'Repeater sub-field "%s" cannot itself be a repeater.', 'wonderpress' ),
								isset( $sub['name'] ) ? esc_html( $sub['name'] ) : ''
							),
							'2.1.0'
						);
						continue;
					}

					$mapped = wonder_acf_field_from_property( $sub, $key );
					if ( $mapped ) {
						$sub_fields[] = $mapped;
					}
				}

				if ( ! $sub_fields ) {
					return null;
				}

				$field['type']         = 'repeater';
				$field['layout']       = 'row';
				$field['button_label'] = __( 'Add row', 'wonderpress' );
				$field['sub_fields']   = $sub_fields;
				return $field;

			default:
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: property name, 2: property type */
						esc_html__( 'Property "%1$s" has type "%2$s", which cannot be mapped to an ACF field. Use string, boolean, image, link, or repeater.', 'wonderpress' ),
						esc_html( $name ),
						esc_html( $type )
					),
					'2.1.0'
				);
				return null;
		}
	}
}

if ( ! function_exists( 'wonder_acf_group_from_manifest' ) ) {
	/**
	 * Build an acf_add_local_field_group() payload from a manifest.
	 *
	 * Fields are wrapped in a group named the slug so get_field( $slug )
	 * returns the property map existing ingest already understands.
	 *
	 * @param array $manifest A parsed manifest.
	 * @param array $location ACF location rule groups.
	 * @return array|null Null when there is nothing to register.
	 */
	function wonder_acf_group_from_manifest( $manifest, $location ) {
		$slug = $manifest['slug'];
		$key  = 'field_wndr_' . $slug;

		$sub_fields = array();
		foreach ( (array) ( $manifest['properties'] ?? array() ) as $prop ) {
			$mapped = wonder_acf_field_from_property( $prop, $key );
			if ( $mapped ) {
				$sub_fields[] = $mapped;
			}
		}

		if ( ! $sub_fields ) {
			return null;
		}

		$title = ! empty( $manifest['name'] )
			? str_replace( '_', ' ', $manifest['name'] )
			: wonder_acf_humanize( $slug );

		return array(
			'key'      => 'group_wndr_' . $slug,
			'title'    => $title,
			'fields'   => array(
				array(
					'key'        => $key,
					'label'      => $title,
					'name'       => $slug,
					'type'       => 'group',
					'layout'     => 'block',
					'sub_fields' => $sub_fields,
				),
			),
			'location' => $location,
		);
	}
}

if ( ! function_exists( 'wonder_register_acf_groups' ) ) {
	/**
	 * Register a field group for every located ACF-compatible manifest.
	 *
	 * A compatible partial with no location is skipped and (when WP_DEBUG)
	 * reported: a group that is not located would appear everywhere.
	 *
	 * @return void
	 */
	function wonder_register_acf_groups() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		foreach ( wonder_load_theme_manifests() as $slug => $manifest ) {
			if ( empty( $manifest['acf_compatible'] ) ) {
				continue;
			}

			$location = wonder_acf_locations_for( $slug, $manifest );
			if ( ! $location ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: partial slug */
						esc_html__( 'Partial "%s" is ACF compatible but has no location. Add it to wonderpress_template_fields or set acf.location on the manifest; otherwise the group is not registered.', 'wonderpress' ),
						esc_html( $slug )
					),
					'2.1.0'
				);
				continue;
			}

			$group = wonder_acf_group_from_manifest( $manifest, $location );
			if ( $group ) {
				acf_add_local_field_group( $group );
			}
		}
	}

	add_action( 'acf/init', 'wonder_register_acf_groups' );
}

if ( ! function_exists( 'wonder_partial_props' ) ) {
	/**
	 * Constructor args for a PHP-rendered ACF-compatible partial.
	 *
	 * Returns `array( 'acf' => get_field( $slug ) )` so existing ingest
	 * hydrates matching properties. Empty when ACF is absent or the field
	 * has no value.
	 *
	 * @param string $slug The partial slug (and the wrapping ACF group name).
	 * @return array
	 */
	function wonder_partial_props( $slug ) {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$value = get_field( $slug );
		return is_array( $value ) ? array( 'acf' => $value ) : array();
	}
}
