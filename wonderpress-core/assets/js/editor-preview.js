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
	var ServerSideRender  = wp.serverSideRender;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var TextareaControl   = wp.components.TextareaControl;
	var ToggleControl     = wp.components.ToggleControl;

	/**
	 * Turn a slug into something a person reads: `call_to_action` -> "Call to action".
	 */
	function humanize( slug ) {
		var words = String( slug ).replace( /[_-]+/g, ' ' ).trim();
		return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
	}

	/**
	 * A sidebar field per attribute, derived from the block's own registration.
	 *
	 * Generated rather than authored. The attributes came from the partial's
	 * declared properties, so a property added to a partial gets a control for
	 * free and there is no per-block JavaScript to keep in step with the PHP.
	 *
	 * Array and object attributes get no control: there is no honest way to edit
	 * a structure in a sidebar text box, and a bad one would write malformed
	 * values into a page. Those stay for a project's own edit component.
	 */
	/**
	 * Attributes WordPress adds to every block, which are not the partial's.
	 *
	 * The editor already offers these — `className` is the Advanced panel's
	 * "Additional CSS class(es)" — so generating our own would put a second,
	 * competing field next to WordPress's own. Filtering by an explicit list
	 * rather than by reading block.json a second time: the cost of being wrong
	 * is one spurious control if WordPress adds a reserved name, which is
	 * cosmetic, where a per-request re-read of every block's metadata would not
	 * be.
	 */
	var RESERVED = [ 'lock', 'metadata', 'className', 'style', 'align', 'anchor' ];

	function controlsFor( props ) {
		var blockType  = wp.blocks.getBlockType( props.name );
		var attributes = ( blockType && blockType.attributes ) || {};

		return Object.keys( attributes ).reduce( function ( fields, key ) {

			if ( RESERVED.indexOf( key ) !== -1 || 0 === key.indexOf( '__' ) ) {
				return fields;
			}

			var type  = attributes[ key ].type;
			var label = humanize( key );
			var value = props.attributes[ key ];

			function set( next ) {
				var update = {};
				update[ key ] = next;
				props.setAttributes( update );
			}

			if ( 'boolean' === type ) {
				fields.push( el( ToggleControl, {
					key: key,
					label: label,
					checked: !! value,
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
			} else if ( 'string' === type ) {
				// Longer prose wants room to breathe; a quote in a one-line input
				// is miserable to edit.
				var Control = /(body|content|text|quote|description|excerpt)/i.test( key )
					? TextareaControl
					: TextControl;

				fields.push( el( Control, {
					key: key,
					label: label,
					value: value || '',
					onChange: set,
					__nextHasNoMarginBottom: true,
				} ) );
			}

			return fields;
		}, [] );
	}

	( names || [] ).forEach( function ( name ) {

		// Something else may already own this block — a project shipping its own
		// edit component for one of them is a legitimate thing to do, and it
		// should win rather than be clobbered by the generic preview.
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var fields = controlsFor( props );

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
					el(
					'div',
					useBlockProps(),
					el( ServerSideRender, {
						block: name,
						attributes: props.attributes,
						// The server render of an empty component is often empty,
						// which reads as a broken block rather than an unfilled one.
						EmptyResponsePlaceholder: function () {
							return el(
								'p',
								{ style: { opacity: 0.6, fontStyle: 'italic', margin: 0 } },
								name + ' — nothing to preview yet'
							);
						},
					} )
					)
				);
			},

			// Dynamic: the markup is produced by render.php on every request, so
			// nothing is serialised into post content.
			save: function () {
				return null;
			},
		} );
	} );
}( window.wp, window.wonderpressEditorBlocks ) );
