/**
 * Docs table of contents: a list in the rail, or (collapsible) a disclosure above the article for
 * screens without a rail. Previewed from the server for the page being edited.
 */
( function ( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { __ } = wp.i18n;

	wp.blocks.registerBlockType( 'studio-docs/toc', {
		edit: ( { attributes, setAttributes, context } ) =>
			el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Table of contents', 'studio-docs' ) },
						el( ToggleControl, {
							label: __( 'Collapsible', 'studio-docs' ),
							help: __( 'A closed "On this page" disclosure, shown only when the right rail is hidden. The page actions move into it.', 'studio-docs' ),
							checked: !! attributes.collapsible,
							onChange: ( collapsible ) => setAttributes( { collapsible } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( wp.serverSideRender, {
						block: 'studio-docs/toc',
						attributes,
						urlQueryArgs: context.postId ? { post_id: context.postId } : {},
						EmptyResponsePlaceholder: () => el( 'p', { className: 'studio-docs-empty-preview' }, wp.i18n.__( 'On this page: shown when the page has headings.', 'studio-docs' ) ),
					} )
				)
			),
		save: () => null,
	} );
} )( window.wp );
