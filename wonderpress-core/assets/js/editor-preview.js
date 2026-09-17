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

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.serverSideRender || ! wp.blockEditor ) {
		return;
	}

	var el               = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps    = wp.blockEditor.useBlockProps;

	( names || [] ).forEach( function ( name ) {

		// Something else may already own this block — a project shipping its own
		// edit component for one of them is a legitimate thing to do, and it
		// should win rather than be clobbered by the generic preview.
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				return el(
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
