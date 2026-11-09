/**
 * Docs search: the button is previewed from the server; the dialog's placeholder is set in the sidebar.
 */
( function ( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { __ } = wp.i18n;

	wp.blocks.registerBlockType( 'studio-docs/search', {
		edit: ( { attributes, setAttributes } ) =>
			el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Search', 'studio-docs' ) },
						el( TextControl, {
							label: __( 'Placeholder', 'studio-docs' ),
							value: attributes.placeholder,
							placeholder: __( 'Search documentation…', 'studio-docs' ),
							onChange: ( placeholder ) => setAttributes( { placeholder } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					useBlockProps( { className: 'studio-docs-inline-preview' } ),
					el( wp.serverSideRender, { block: 'studio-docs/search', attributes } )
				)
			),
		save: () => null,
	} );
} )( window.wp );
