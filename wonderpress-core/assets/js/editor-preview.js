/**
 * Give the theme's blocks an editor presence.
 *
 * WordPress registering a block on the server is not enough to put it in the
 * editor. `unstable__bootstrapServerSideBlockDefinitions()` only *stores* the
 * server's metadata; the sole thing that reads it back is `registerBlockType()`,
 * which merges it into a client-side registration. So a block registered purely
 * from block.json has no client registration, and is therefore absent from the
 * inserter entirely — not merely un-previewable.
 *
 * This registers each of them on the client, supplying only what the server
 * cannot: an `edit`. Title, category and attributes still come from block.json
 * via the bootstrapped definition, so there is no second place to keep in sync.
 *
 * Manifest property semantics (select, email, when, textarea) come from
 * `window.wonderpressBlockSchemas`, built from partial manifests on the server.
 *
 * `edit` renders through ServerSideRender, which asks WordPress to render the
 * block over REST and shows the result. The PHP partial therefore stays the one
 * source of markup — an `edit` that rebuilt the markup in JS would give a
 * smoother preview and two definitions of what a component looks like, which is
 * the trade this toolkit exists to refuse.
 *
 * Deliberately buildless: it runs against the `wp.*` globals WordPress already
 * enqueues, so a project needs no bundler, and this file is the thing that
 * actually ships rather than the input to something that does.
 */
( function ( wp, names ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.serverSideRender || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var ServerSideRender  = wp.serverSideRender;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var MediaUpload       = wp.blockEditor.MediaUpload;
	var MediaUploadCheck  = wp.blockEditor.MediaUploadCheck;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var TextareaControl   = wp.components.TextareaControl;
	var ToggleControl     = wp.components.ToggleControl;
	var SelectControl     = wp.components.SelectControl;
	var Button            = wp.components.Button;
	var BaseControl       = wp.components.BaseControl;
	var Placeholder       = wp.components.Placeholder;

	var blockSchemas = window.wonderpressBlockSchemas || {};

	/**
	 * Turn a slug into something a person reads: `call_to_action` -> "Call to action".
	 */
	function humanize( slug ) {
		var words = String( slug ).replace( /[_-]+/g, ' ' ).trim();
		return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
	}

	function schemaForBlock( blockName ) {
		return blockSchemas[ blockName ] || null;
	}

	function propertyDef( blockName, attrName ) {
		var schema = schemaForBlock( blockName );
		if ( ! schema || ! schema.properties ) {
			return null;
		}
		for ( var i = 0; i < schema.properties.length; i++ ) {
			if ( schema.properties[ i ].name === attrName ) {
				return schema.properties[ i ];
			}
		}
		return null;
	}

	function ruleMatches( rule, attributes ) {
		if ( ! rule || ! rule.field || ! rule.operator ) {
			return true;
		}
		var actual = attributes[ rule.field ];
		var expect = rule.value;

		switch ( rule.operator ) {
			case '==':
				return String( actual ) === String( expect );
			case '!=':
				return String( actual ) !== String( expect );
			case 'contains':
				return String( actual || '' ).indexOf( String( expect ) ) !== -1;
			case '!contains':
				return String( actual || '' ).indexOf( String( expect ) ) === -1;
			case '>':
				return Number( actual ) > Number( expect );
			case '<':
				return Number( actual ) < Number( expect );
			case '>=':
				return Number( actual ) >= Number( expect );
			case '<=':
				return Number( actual ) <= Number( expect );
			case 'pattern':
				try {
					return new RegExp( String( expect ) ).test( String( actual || '' ) );
				} catch ( e ) {
					return false;
				}
			case '!pattern':
				try {
					return ! new RegExp( String( expect ) ).test( String( actual || '' ) );
				} catch ( e ) {
					return true;
				}
			default:
				return true;
		}
	}

	/**
	 * Manifest `when`: OR of AND groups. Hidden fields keep their saved values (ACF parity).
	 */
	function whenVisible( propDef, attributes ) {
		if ( ! propDef || ! propDef.when || ! propDef.when.length ) {
			return true;
		}
		return propDef.when.some( function ( andGroup ) {
			if ( ! andGroup || ! andGroup.length ) {
				return false;
			}
			return andGroup.every( function ( rule ) {
				return ruleMatches( rule, attributes );
			} );
		} );
	}

	function imageAttachmentId( value ) {
		if ( ! value || typeof value !== 'object' ) {
			return 0;
		}
		if ( value.ID ) {
			return parseInt( value.ID, 10 ) || 0;
		}
		if ( value.id ) {
			return parseInt( value.id, 10 ) || 0;
		}
		return 0;
	}

	function imagePreviewUrl( value ) {
		if ( ! value || typeof value !== 'object' ) {
			return '';
		}
		if ( value.url ) {
			return String( value.url );
		}
		if ( value.sizes && value.sizes.thumbnail && value.sizes.thumbnail.url ) {
			return String( value.sizes.thumbnail.url );
		}
		return '';
	}

	/**
	 * Map a wp.media attachment to the canonical image array (ACF parity).
	 */
	function imageValueFromMedia( media ) {
		if ( ! media || ! media.id ) {
			return null;
		}

		var sizes = {};
		if ( media.sizes && typeof media.sizes === 'object' ) {
			Object.keys( media.sizes ).forEach( function ( sizeName ) {
				var size = media.sizes[ sizeName ];
				if ( ! size || ! size.url ) {
					return;
				}
				sizes[ sizeName ] = {
					url: size.url,
					width: size.width || 0,
					height: size.height || 0,
				};
			} );
		}

		return {
			ID: media.id,
			id: media.id,
			url: media.url || '',
			alt: media.alt || '',
			width: media.width || 0,
			height: media.height || 0,
			sizes: sizes,
			title: media.title || '',
			mime_type: media.mime || '',
			type: 'image',
		};
	}

	function isTextareaField( propDef, key ) {
		if ( propDef && propDef.acf ) {
			if ( propDef.acf.format === 'textarea' || propDef.acf.rows ) {
				return true;
			}
		}
		return /(body|content|text|quote|description|excerpt|dek)/i.test( key );
	}

	var RESERVED = [ 'lock', 'metadata', 'className', 'style', 'align', 'anchor' ];

	function declaredAttributes( blockType ) {
		return Object.keys( ( blockType && blockType.attributes ) || {} ).filter( function ( key ) {
			return RESERVED.indexOf( key ) === -1 && 0 !== key.indexOf( '__' );
		} );
	}

	function isEmptyAttributeValue( value ) {
		if ( value === undefined || value === null || value === '' || value === false ) {
			return true;
		}
		if ( typeof value === 'object' && ! Array.isArray( value ) ) {
			return ! imageAttachmentId( value );
		}
		return false;
	}

	function isUntouched( props, blockType ) {
		return declaredAttributes( blockType ).every( function ( key ) {
			return isEmptyAttributeValue( props.attributes[ key ] );
		} );
	}

	function controlKeysFor( blockName, blockType ) {
		var schema = schemaForBlock( blockName );
		if ( schema && schema.properties && schema.properties.length ) {
			return schema.properties.map( function ( p ) {
				return p.name;
			} );
		}
		return declaredAttributes( blockType );
	}

	function controlsFor( props ) {
		var blockType  = wp.blocks.getBlockType( props.name );
		var attributes = ( blockType && blockType.attributes ) || {};
		var keys       = controlKeysFor( props.name, blockType );

		return keys.reduce( function ( fields, key ) {
			var propDef      = propertyDef( props.name, key );
			var manifestType = propDef && propDef.type ? propDef.type : null;
			var attrType     = attributes[ key ] ? attributes[ key].type : 'string';
			var label        = ( propDef && propDef.label ) ? propDef.label : humanize( key );
			var help         = ( propDef && propDef.description ) ? propDef.description : undefined;
			var value        = props.attributes[ key ];

			if ( ! whenVisible( propDef, props.attributes ) ) {
				return fields;
			}

			function set( next ) {
				var update = {};
				update[ key ] = next;
				props.setAttributes( update );
			}

			if ( manifestType === 'boolean' || ( ! manifestType && attrType === 'boolean' ) ) {
				fields.push( el( ToggleControl, {
					key: key,
					label: label,
					help: help,
					checked: !! value,
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
				return fields;
			}

			if ( manifestType === 'select' ) {
				var options = [ { label: '\u2014', value: '' } ];
				if ( propDef.choices && typeof propDef.choices === 'object' ) {
					Object.keys( propDef.choices ).forEach( function ( choiceKey ) {
						options.push( {
							label: String( propDef.choices[ choiceKey ] ),
							value: choiceKey,
						} );
					} );
				} else if ( attributes[ key ] && attributes[ key ].enum ) {
					attributes[ key ].enum.forEach( function ( choiceKey ) {
						options.push( {
							label: humanize( choiceKey ),
							value: choiceKey,
						} );
					} );
				}
				fields.push( el( SelectControl, {
					key: key,
					label: label,
					help: help,
					value: value || '',
					options: options,
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
				return fields;
			}

			if ( manifestType === 'email' ) {
				fields.push( el( TextControl, {
					key: key,
					label: label,
					help: help,
					type: 'email',
					value: value || '',
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
				return fields;
			}

			if ( manifestType === 'string' || ( ! manifestType && attrType === 'string' ) ) {
				var Control = isTextareaField( propDef, key ) ? TextareaControl : TextControl;
				fields.push( el( Control, {
					key: key,
					label: label,
					help: help,
					value: value || '',
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
				return fields;
			}

			if ( manifestType === 'image' ) {
				var attachmentId = imageAttachmentId( value );
				var previewUrl   = imagePreviewUrl( value );

				fields.push(
					el(
						BaseControl,
						{
							key: key,
							className: 'wonderpress-editor-image-control',
							label: label,
							help: help,
							__nextHasNoMarginBottom: true,
						},
						el(
							MediaUploadCheck,
							null,
							el( MediaUpload, {
								onSelect: function ( media ) {
									set( imageValueFromMedia( media ) );
								},
								allowedTypes: [ 'image' ],
								value: attachmentId || undefined,
								render: function ( renderProps ) {
									return el(
										Fragment,
										null,
										previewUrl
											? el( 'img', {
												src: previewUrl,
												alt: ( value && value.alt ) ? value.alt : '',
												style: { display: 'block', maxWidth: '100%', height: 'auto', marginBottom: '8px' },
											} )
											: null,
										el(
											'div',
											{
												className: 'wonderpress-editor-image-control__actions',
												style: { display: 'flex', gap: '8px', flexWrap: 'wrap' },
											},
											el(
												Button,
												{
													variant: attachmentId ? 'secondary' : 'primary',
													onClick: renderProps.open,
												},
												attachmentId ? 'Replace image' : 'Select image'
											),
											attachmentId
												? el(
													Button,
													{
														variant: 'link',
														isDestructive: true,
														onClick: function () {
															set( null );
														},
													},
													'Remove'
												)
												: null
										)
									);
								},
							} )
						)
					)
				);
				return fields;
			}

			// Tier B types (object/array attributes) — custom edit component required.
			return fields;
		}, [] );
	}

	( names || [] ).forEach( function ( name ) {

		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var blockType = wp.blocks.getBlockType( props.name );
				var fields    = controlsFor( props );
				var title     = ( blockType && blockType.title ) || props.name;

				var body = isUntouched( props, blockType )
					? el(
						Placeholder,
						{
							icon: blockType && blockType.icon && blockType.icon.src,
							label: title,
							instructions: fields.length
								? 'Fill this in from the block settings panel on the right.'
								: 'This block takes no settings — it will render as soon as the page is saved.',
						}
					)
					: el( ServerSideRender, {
						block: name,
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								'p',
								{ style: { opacity: 0.6, fontStyle: 'italic', margin: 0 } },
								title + ' — nothing to preview yet'
							);
						},
					} );

				return el(
					wp.element.Fragment,
					null,
					fields.length
						? el(
							InspectorControls,
							null,
							el( PanelBody, { title: 'Content', initialOpen: true }, fields )
						)
						: null,
					el( 'div', useBlockProps(), body )
				);
			},

			save: function () {
				return null;
			},
		} );
	} );
}( window.wp, window.wonderpressEditorBlocks ) );
