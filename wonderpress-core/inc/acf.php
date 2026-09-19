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
		$from_manifests = function_exists( 'wonder_template_fields_from_manifests' )
			? wonder_template_fields_from_manifests()
			: array();
		$custom         = (array) apply_filters( 'wonderpress_template_fields', array() );
		$merged         = $from_manifests;

		foreach ( $custom as $template => $slugs ) {
			$merged[ $template ] = array_values(
				array_unique(
					array_merge(
						isset( $merged[ $template ] ) ? (array) $merged[ $template ] : array(),
						(array) $slugs
					)
				)
			);
		}

		return $merged;
	}
}

if ( ! function_exists( 'wonder_acf_locations_for' ) ) {
	/**
	 * Resolve ACF location rules for a partial slug.
	 *
	 * Built from wonder_template_fields() (composition-derived map plus the
	 * wonderpress_template_fields filter). Each template match is an OR group.
	 * Returns an empty array when nothing locates the partial.
	 *
	 * Partial manifests do not carry location rules; template manifests and the
	 * theme filter own placement.
	 *
	 * @param string $slug The partial slug.
	 * @return array
	 */
	function wonder_acf_locations_for( $slug ) {
		$groups = array();

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

if ( ! function_exists( 'wonder_acf_property_field_key' ) ) {
	/**
	 * Stable ACF field key for one manifest property name under a prefix.
	 *
	 * @param string $key_prefix e.g. field_wndr_hero.
	 * @param string $name       Property name.
	 * @return string
	 */
	function wonder_acf_property_field_key( $key_prefix, $name ) {
		return $key_prefix . '_' . $name;
	}
}

if ( ! function_exists( 'wonder_acf_allowed_passthrough_keys' ) ) {
	/**
	 * Manifest `acf` keys that may be merged onto a compiled field.
	 *
	 * @return string[]
	 */
	function wonder_acf_allowed_passthrough_keys() {
		return array(
			'choices',
			'default_value',
			'ui',
			'return_format',
			'preview_size',
			'library',
			'layout',
			'wrapper',
			'allow_null',
			'multiple',
			'placeholder',
			'min',
			'max',
			'step',
		);
	}
}

if ( ! function_exists( 'wonder_acf_apply_acf_passthrough' ) ) {
	/**
	 * Merge whitelisted manifest `acf` overrides onto a field array.
	 *
	 * @param array $field Compiled ACF field.
	 * @param array $prop  Manifest property.
	 * @return array
	 */
	function wonder_acf_apply_acf_passthrough( $field, $prop ) {
		if ( empty( $prop['acf'] ) || ! is_array( $prop['acf'] ) ) {
			return $field;
		}

		$allowed = wonder_acf_allowed_passthrough_keys();
		foreach ( $prop['acf'] as $acf_key => $acf_value ) {
			if ( in_array( $acf_key, $allowed, true ) ) {
				$field[ $acf_key ] = $acf_value;
			}
		}

		return $field;
	}
}

if ( ! function_exists( 'wonder_acf_conditional_logic_from_when' ) ) {
	/**
	 * Compile manifest `when` rules into ACF conditional_logic (field keys).
	 *
	 * @param array $when        Manifest when groups (OR of AND groups).
	 * @param array $name_to_key Map of sibling property name => ACF field key.
	 * @return array|null Null when no valid rules remain.
	 */
	function wonder_acf_conditional_logic_from_when( $when, $name_to_key ) {
		if ( ! is_array( $when ) || ! $when ) {
			return null;
		}

		$logic = array();

		foreach ( $when as $and_group ) {
			if ( ! is_array( $and_group ) || ! $and_group ) {
				continue;
			}

			$compiled_group = array();
			foreach ( $and_group as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['field'] ) || empty( $rule['operator'] ) ) {
					continue;
				}

				$ref_name = (string) $rule['field'];
				if ( empty( $name_to_key[ $ref_name ] ) ) {
					continue;
				}

				$compiled_group[] = array(
					'field'    => $name_to_key[ $ref_name ],
					'operator' => (string) $rule['operator'],
					'value'    => array_key_exists( 'value', $rule ) ? $rule['value'] : '',
				);
			}

			if ( $compiled_group ) {
				$logic[] = $compiled_group;
			}
		}

		return $logic ? $logic : null;
	}
}

if ( ! function_exists( 'wonder_acf_partial_manifest_properties' ) ) {
	/**
	 * Property definitions from a referenced partial manifest (embed primitive).
	 *
	 * @param string $partial_slug Partial slug (e.g. link).
	 * @return array
	 */
	function wonder_acf_partial_manifest_properties( $partial_slug ) {
		$partial_slug = (string) $partial_slug;
		if ( '' === $partial_slug ) {
			return array();
		}

		$from_core = array();
		if ( function_exists( 'wonder_core_partial_manifest_dir' ) ) {
			$path = wonder_core_partial_manifest_dir() . DIRECTORY_SEPARATOR . $partial_slug . '.json';
			if ( is_readable( $path ) ) {
				$raw = file_get_contents( $path );
				if ( false !== $raw ) {
					$data = json_decode( $raw, true );
					if ( is_array( $data ) && ! empty( $data['properties'] ) && is_array( $data['properties'] ) ) {
						$from_core = $data['properties'];
					}
				}
			}
		}

		if ( function_exists( 'wonder_theme_manifest' ) && function_exists( 'get_stylesheet_directory' ) ) {
			$manifest = wonder_theme_manifest( $partial_slug );
			if ( is_array( $manifest ) && ! empty( $manifest['properties'] ) && is_array( $manifest['properties'] ) ) {
				return $manifest['properties'];
			}
		}

		return $from_core;
	}
}

if ( ! function_exists( 'wonder_acf_build_field_from_property' ) ) {
	/**
	 * Map one manifest property to an ACF field array (no conditional_logic).
	 *
	 * @param array  $prop       A manifest property.
	 * @param string $key_prefix Stable ACF key prefix (`field_wndr_{slug}`).
	 * @return array|null
	 */
	function wonder_acf_build_field_from_property( $prop, $key_prefix ) {
		if ( empty( $prop['name'] ) || empty( $prop['type'] ) ) {
			return null;
		}

		$name = $prop['name'];
		$type = $prop['type'];
		$key  = wonder_acf_property_field_key( $key_prefix, $name );

		$label = ! empty( $prop['label'] ) && is_string( $prop['label'] )
			? $prop['label']
			: wonder_acf_humanize( $name );

		$field = array(
			'key'          => $key,
			'label'        => $label,
			'name'         => $name,
			'instructions' => isset( $prop['description'] ) ? (string) $prop['description'] : '',
			'required'     => ! empty( $prop['required'] ) ? 1 : 0,
		);

		switch ( $type ) {
			case 'string':
				$use_textarea = function_exists( 'wonder_manifest_property_string_is_textarea' )
					? wonder_manifest_property_string_is_textarea( $prop )
					: wonder_acf_is_textarea_name( $name );
				$field['type'] = $use_textarea ? 'textarea' : 'text';
				$rows          = function_exists( 'wonder_manifest_property_string_rows' )
					? wonder_manifest_property_string_rows( $prop )
					: null;
				if ( $rows ) {
					$field['rows'] = $rows;
				}
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'boolean':
				$field['type'] = 'true_false';
				$field['ui']   = 1;
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'email':
				$field['type'] = 'email';
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'select':
				$choices = array();
				if ( ! empty( $prop['choices'] ) && is_array( $prop['choices'] ) ) {
					$choices = $prop['choices'];
				}
				if ( ! $choices ) {
					_doing_it_wrong(
						__FUNCTION__,
						sprintf(
							/* translators: %s: property name */
							esc_html__( 'Select property "%s" must declare choices.', 'wonderpress' ),
							esc_html( $name )
						),
						'2.2.0'
					);
					return null;
				}
				$field['type']    = 'select';
				$field['choices'] = $choices;
				if ( isset( $prop['default'] ) ) {
					$field['default_value'] = $prop['default'];
				}
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'post_object':
				$field['type']       = 'post_object';
				$field['post_type']  = wonder_manifest_property_post_types( $prop );
				$field               = wonder_acf_apply_acf_passthrough( $field, $prop );
				if ( empty( $field['return_format'] ) ) {
					$field['return_format'] = 'object';
				}
				return $field;

			case 'image':
				$field['type']          = 'image';
				$field['return_format'] = 'array';
				$field['preview_size']  = 'medium';
				$field['library']       = 'all';
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'partial':
				$ref_slug = ! empty( $prop['partial'] ) ? (string) $prop['partial'] : '';
				$ref_props = wonder_acf_partial_manifest_properties( $ref_slug );
				if ( ! $ref_props ) {
					_doing_it_wrong(
						__FUNCTION__,
						sprintf(
							/* translators: 1: property name, 2: partial slug */
							esc_html__( 'Partial property "%1$s" references unknown or empty partial "%2$s".', 'wonderpress' ),
							esc_html( $name ),
							esc_html( $ref_slug )
						),
						'2.3.0'
					);
					return null;
				}

				$field['type']       = 'group';
				$field['layout']     = 'block';
				$field['sub_fields'] = wonder_acf_fields_from_properties( $ref_props, $key );
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			case 'link':
				$field['type']       = 'group';
				$field['layout']     = 'block';
				$field['sub_fields'] = array(
					wonder_acf_build_field_from_property(
						array(
							'name' => 'content',
							'type' => 'string',
						),
						$key
					),
					wonder_acf_build_field_from_property(
						array(
							'name' => 'url',
							'type' => 'string',
						),
						$key
					),
					wonder_acf_build_field_from_property(
						array(
							'name' => 'open_in_new_tab',
							'type' => 'boolean',
						),
						$key
					),
					wonder_acf_build_field_from_property(
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
					}
				}

				$sub_fields = wonder_acf_fields_from_properties( $prop['properties'], $key );
				if ( ! $sub_fields ) {
					return null;
				}

				$field['type']         = 'repeater';
				$field['layout']       = 'row';
				$field['button_label'] = __( 'Add row', 'wonderpress' );
				$field['sub_fields']   = $sub_fields;
				return wonder_acf_apply_acf_passthrough( $field, $prop );

			default:
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: property name, 2: property type */
						esc_html__( 'Property "%1$s" has type "%2$s", which cannot be mapped to an ACF field.', 'wonderpress' ),
						esc_html( $name ),
						esc_html( $type )
					),
					'2.2.0'
				);
				return null;
		}
	}
}

if ( ! function_exists( 'wonder_acf_fields_from_properties' ) ) {
	/**
	 * Map manifest properties to ACF fields (two-pass: keys, then conditionals).
	 *
	 * @param array  $properties Property definitions sharing one field group.
	 * @param string $key_prefix Stable ACF key prefix.
	 * @return array
	 */
	function wonder_acf_fields_from_properties( $properties, $key_prefix ) {
		if ( ! is_array( $properties ) || ! $properties ) {
			return array();
		}

		$name_to_key = array();
		foreach ( $properties as $prop ) {
			if ( empty( $prop['name'] ) ) {
				continue;
			}
			$name_to_key[ $prop['name'] ] = wonder_acf_property_field_key( $key_prefix, $prop['name'] );
		}

		$fields = array();
		foreach ( $properties as $prop ) {
			$field = wonder_acf_build_field_from_property( $prop, $key_prefix );
			if ( ! $field ) {
				continue;
			}

			if ( ! empty( $prop['when'] ) ) {
				$logic = wonder_acf_conditional_logic_from_when( $prop['when'], $name_to_key );
				if ( $logic ) {
					$field['conditional_logic'] = $logic;
				}
			}

			$fields[] = $field;
		}

		return $fields;
	}
}

if ( ! function_exists( 'wonder_acf_field_from_property' ) ) {
	/**
	 * Map one manifest property to an ACF field array.
	 *
	 * When the property uses `when`, pass the full sibling list via
	 * wonder_acf_fields_from_properties() instead.
	 *
	 * @param array  $prop       A manifest property.
	 * @param string $key_prefix Stable ACF key prefix (`field_wndr_{slug}`).
	 * @return array|null
	 */
	function wonder_acf_field_from_property( $prop, $key_prefix ) {
		$fields = wonder_acf_fields_from_properties( array( $prop ), $key_prefix );
		return $fields ? $fields[0] : null;
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

		$sub_fields = wonder_acf_fields_from_properties( (array) ( $manifest['properties'] ?? array() ), $key );

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

if ( ! function_exists( 'wonder_acf_template_group_key' ) ) {
	/**
	 * Stable ACF group key for a page template slug.
	 *
	 * @param string $template_slug WordPress template filename.
	 * @return string
	 */
	function wonder_acf_template_group_key( $template_slug ) {
		$normalized = preg_replace( '/[^a-z0-9_]+/', '_', strtolower( (string) $template_slug ) );
		return 'group_wndr_tpl_' . trim( $normalized, '_' );
	}
}

if ( ! function_exists( 'wonder_acf_composition_row_is_tab' ) ) {
	/**
	 * Whether a composition row is a tab container (nested instances).
	 *
	 * @param array $row Composition row.
	 * @return bool
	 */
	function wonder_acf_composition_row_is_tab( $row ) {
		return is_array( $row ) && isset( $row['items'] ) && is_array( $row['items'] );
	}
}

if ( ! function_exists( 'wonder_acf_composition_tab_row_count' ) ) {
	/**
	 * How many tab-container rows exist in a composition.
	 *
	 * Used to pick tab placement (left for a lone tab row, top when there are
	 * two or more — ACF does not draw a top tab bar for a single top tab).
	 *
	 * @param array|null $composition Template composition.
	 * @return int
	 */
	function wonder_acf_composition_tab_row_count( $composition ) {
		if ( ! is_array( $composition ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $composition as $row ) {
			if ( wonder_acf_composition_row_is_tab( $row ) ) {
				$count++;
			}
		}

		return $count;
	}
}

if ( ! function_exists( 'wonder_acf_field_key_suffix' ) ) {
	/**
	 * Sanitize an id for ACF field keys (keys must not rely on raw hyphens).
	 *
	 * @param string $id Composition or tab id.
	 * @return string
	 */
	function wonder_acf_field_key_suffix( $id ) {
		$suffix = preg_replace( '/[^a-z0-9_]+/', '_', strtolower( (string) $id ) );
		return trim( $suffix, '_' );
	}
}

if ( ! function_exists( 'wonder_acf_composition_row_is_field_group' ) ) {
	/**
	 * Whether a composition row defines inline fields (no partial).
	 *
	 * @param array $row Composition row.
	 * @return bool
	 */
	function wonder_acf_composition_row_is_field_group( $row ) {
		return is_array( $row )
			&& ! empty( $row['properties'] )
			&& is_array( $row['properties'] );
	}
}

if ( ! function_exists( 'wonder_acf_composition_group_field' ) ) {
	/**
	 * ACF group field for one composition row (partial instance or inline properties).
	 *
	 * @param array $row Composition content row.
	 * @return array|null
	 */
	function wonder_acf_composition_group_field( $row ) {
		if ( empty( $row['id'] ) ) {
			return null;
		}

		$instance_id = $row['id'];
		$key_prefix = 'field_wndr_' . wonder_acf_field_key_suffix( $instance_id );
		$properties = array();

		if ( ! empty( $row['partial'] ) && is_string( $row['partial'] ) ) {
			$partial_manifest = wonder_theme_manifest( $row['partial'] );
			if ( ! $partial_manifest || empty( $partial_manifest['acf_compatible'] ) ) {
				return null;
			}
			$properties = (array) ( $partial_manifest['properties'] ?? array() );
		} elseif ( wonder_acf_composition_row_is_field_group( $row ) ) {
			$properties = $row['properties'];
		} else {
			return null;
		}

		$sub_fields = wonder_acf_fields_from_properties( $properties, $key_prefix );

		if ( ! $sub_fields ) {
			return null;
		}

		$label = ! empty( $row['label'] ) && is_string( $row['label'] )
			? $row['label']
			: wonder_acf_humanize( $instance_id );

		return array(
			'key'        => $key_prefix,
			'label'      => $label,
			'name'       => $instance_id,
			'type'       => 'group',
			'layout'     => 'block',
			'sub_fields' => $sub_fields,
		);
	}
}

if ( ! function_exists( 'wonder_acf_composition_instance_field' ) ) {
	/**
	 * ACF group field for a partial-backed composition row.
	 *
	 * @param array $row Instance row (id + partial).
	 * @return array|null
	 */
	function wonder_acf_composition_instance_field( $row ) {
		if ( empty( $row['partial'] ) ) {
			return null;
		}

		return wonder_acf_composition_group_field( $row );
	}
}

if ( ! function_exists( 'wonder_acf_tab_endpoint_stopper_field' ) ) {
	/**
	 * Close an ACF tab group so following fields sit outside tabs.
	 *
	 * @param string $suffix Unique key suffix.
	 * @return array
	 */
	function wonder_acf_tab_endpoint_stopper_field( $suffix, $placement = 'top' ) {
		return array(
			'key'       => 'field_wndr_tab_end_' . $suffix,
			'label'     => '',
			'name'      => '',
			'type'      => 'tab',
			'placement' => $placement,
			'endpoint'  => 1,
		);
	}
}

if ( ! function_exists( 'wonder_acf_tab_placement_for_template_manifest' ) ) {
	/**
	 * ACF tab placement for composition tab rows.
	 *
	 * @param array $template_manifest Parsed template manifest.
	 * @return string `left` or `top`.
	 */
	function wonder_acf_tab_placement_for_template_manifest( $template_manifest ) {
		$editor = isset( $template_manifest['editor'] ) && is_array( $template_manifest['editor'] )
			? $template_manifest['editor']
			: array();
		$acf    = isset( $editor['acf'] ) && is_array( $editor['acf'] ) ? $editor['acf'] : array();
		$explicit = isset( $acf['tabPlacement'] ) ? $acf['tabPlacement'] : null;

		if ( 'left' === $explicit || 'top' === $explicit ) {
			return $explicit;
		}

		$composition = isset( $template_manifest['composition'] ) && is_array( $template_manifest['composition'] )
			? $template_manifest['composition']
			: array();

		return wonder_acf_composition_tab_row_count( $composition ) >= 2 ? 'top' : 'left';
	}
}

if ( ! function_exists( 'wonder_acf_fields_from_template_composition' ) ) {
	/**
	 * Ordered ACF fields for a template composition (groups + tab UI).
	 *
	 * Root instance rows have no leading tab. Tab rows insert an ACF tab field
	 * then their child instance groups. ACF requires endpoint markers when fields
	 * appear before the first tab and when returning to root-level groups.
	 *
	 * @param array  $composition   Template manifest composition.
	 * @param string $tab_placement ACF tab placement (`left` or `top`).
	 * @return array
	 */
	function wonder_acf_fields_from_template_composition( $composition, $tab_placement = 'left' ) {
		if ( ! is_array( $composition ) ) {
			return array();
		}

		if ( 'top' !== $tab_placement ) {
			$tab_placement = 'left';
		}

		$fields           = array();
		$tab_group_open   = false;
		$has_fields_above = false;
		$stopper_index    = 0;
		$tab_index        = 0;

		foreach ( $composition as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}

			if ( wonder_acf_composition_row_is_tab( $row ) ) {
				$tab_id = $row['id'];
				$label  = ! empty( $row['label'] ) && is_string( $row['label'] )
					? $row['label']
					: wonder_acf_humanize( $tab_id );

				$child_groups = array();
				foreach ( (array) $row['items'] as $child ) {
					$group = wonder_acf_composition_group_field( $child );
					if ( $group ) {
						$child_groups[] = $group;
					}
				}

				if ( ! $child_groups ) {
					continue;
				}

				$key_suffix = wonder_acf_field_key_suffix( $tab_id );

				// endpoint 1 only when opening a tab group after root-level fields.
				// Further tab rows in the same manifest run stay endpoint 0 so ACF
				// keeps one horizontal (or left) tab set instead of stacking groups.
				$start_new_tab_group = $has_fields_above && ! $tab_group_open;

				$fields[] = array(
					'key'       => 'field_wndr_tab_' . $key_suffix,
					'label'     => $label,
					'name'      => '',
					'type'      => 'tab',
					'placement' => $tab_placement,
					'endpoint'  => $start_new_tab_group ? 1 : 0,
					'selected'  => 0 === $tab_index ? 1 : 0,
				);
				$tab_index++;

				foreach ( $child_groups as $group ) {
					$fields[] = $group;
				}

				$tab_group_open   = true;
				$has_fields_above = true;
				continue;
			}

			if ( $tab_group_open ) {
				$fields[]       = wonder_acf_tab_endpoint_stopper_field( (string) $stopper_index, $tab_placement );
				$stopper_index++;
				$tab_group_open = false;
			}

			$group = wonder_acf_composition_group_field( $row );
			if ( $group ) {
				$fields[]         = $group;
				$has_fields_above = true;
			}
		}

		return $fields;
	}
}

if ( ! function_exists( 'wonder_template_composition_field' ) ) {
	/**
	 * Values for an inline composition field group (no partial).
	 *
	 * @param string $instance_id Composition row id (ACF group name).
	 * @return array
	 */
	function wonder_template_composition_field( $instance_id ) {
		if ( ! function_exists( 'get_field' ) || ! is_string( $instance_id ) || '' === $instance_id ) {
			return array();
		}

		$value = get_field( $instance_id );
		return is_array( $value ) ? $value : array();
	}
}

if ( ! function_exists( 'wonder_acf_group_from_template_manifest' ) ) {
	/**
	 * One ACF field group for a template composition (instance ids as fields).
	 *
	 * @param array $template_manifest Parsed template manifest.
	 * @return array|null
	 */
	function wonder_acf_group_from_template_manifest( $template_manifest ) {
		if ( empty( $template_manifest['composition'] ) || ! is_array( $template_manifest['composition'] ) ) {
			return null;
		}

		$template       = $template_manifest['template'];
		$tab_placement  = wonder_acf_tab_placement_for_template_manifest( $template_manifest );
		$fields         = wonder_acf_fields_from_template_composition( $template_manifest['composition'], $tab_placement );

		if ( ! $fields ) {
			return null;
		}

		$title = wonder_acf_humanize(
			preg_replace( '/\.php$/', '', (string) $template )
		);

		return array(
			'key'      => wonder_acf_template_group_key( $template ),
			'title'    => $title,
			'fields'   => $fields,
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => $template,
					),
				),
			),
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

		if ( function_exists( 'wonder_load_template_manifests' ) ) {
			foreach ( wonder_load_template_manifests() as $template_manifest ) {
				$group = wonder_acf_group_from_template_manifest( $template_manifest );
				if ( $group ) {
					acf_add_local_field_group( $group );
				}
			}
		}

		foreach ( wonder_load_theme_manifests() as $slug => $manifest ) {
			if ( empty( $manifest['acf_compatible'] ) ) {
				continue;
			}

			if ( function_exists( 'wonder_partial_in_any_composition' ) && wonder_partial_in_any_composition( $slug ) ) {
				continue;
			}

			$location = wonder_acf_locations_for( $slug );
			if ( ! $location ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: partial slug */
						esc_html__( 'Partial "%s" is ACF compatible but has no location. Add it to a template composition or wonderpress_template_fields; otherwise the group is not registered.', 'wonderpress' ),
						esc_html( $slug )
					),
					'2.2.0'
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
	 * @param string      $slug        The partial slug.
	 * @param string|null $instance_id Template composition instance id (ACF group field name).
	 * @return array
	 */
	function wonder_partial_props( $slug, $instance_id = null ) {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$field_name = ( is_string( $instance_id ) && '' !== $instance_id ) ? $instance_id : $slug;
		$value      = get_field( $field_name );
		return is_array( $value ) ? array( 'acf' => $value ) : array();
	}
}
