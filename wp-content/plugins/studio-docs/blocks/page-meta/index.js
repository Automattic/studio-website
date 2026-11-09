/**
 * Docs page meta: the "Updated" date and the "Edit this page on GitHub" link, each switchable in the
 * sidebar. Previewed from the server for the page being edited.
 */
( function ( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { __ } = wp.i18n;

	wp.blocks.registerBlockType( 'studio-docs/page-meta', {
		edit: ( { attributes, setAttributes, context } ) =>
			el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Page meta', 'studio-docs' ) },
						el( ToggleControl, {
							label: __( 'Show the last update date', 'studio-docs' ),
							help: __( 'The date of the last change to the page in the Studio repository.', 'studio-docs' ),
							checked: attributes.showUpdated,
							onChange: ( showUpdated ) => setAttributes( { showUpdated } ),
							__nextHasNoMarginBottom: true,
						} ),
						el( ToggleControl, {
							label: __( 'Show the "Edit this page on GitHub" link', 'studio-docs' ),
							checked: attributes.showEditLink,
							onChange: ( showEditLink ) => setAttributes( { showEditLink } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( wp.serverSideRender, {
						block: 'studio-docs/page-meta',
						attributes,
						urlQueryArgs: context.postId ? { post_id: context.postId } : {},
						EmptyResponsePlaceholder: () => el( 'p', { className: 'studio-docs-empty-preview' }, __( 'Page meta: the update date and the GitHub edit link of the page.', 'studio-docs' ) ),
					} )
				)
			),
		save: () => null,
	} );
} )( window.wp );
