/**
 * Callout block: a type (note, tip, important, warning, caution) chosen in the sidebar, an optional
 * custom title, and any blocks inside. Rendered on the server around the inner blocks.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, useInnerBlocksProps, InspectorControls, RichText } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { __ } = wp.i18n;

	const TYPES = [
		{ value: 'note', label: __( 'Note', 'studio-docs' ) },
		{ value: 'tip', label: __( 'Tip', 'studio-docs' ) },
		{ value: 'important', label: __( 'Important', 'studio-docs' ) },
		{ value: 'warning', label: __( 'Warning', 'studio-docs' ) },
		{ value: 'caution', label: __( 'Caution', 'studio-docs' ) },
	];
	const label = ( type ) => ( TYPES.find( ( t ) => t.value === type ) || TYPES[ 0 ] ).label;

	registerBlockType( 'studio-docs/callout', {
		edit: ( { attributes, setAttributes } ) => {
			const blockProps = useBlockProps( { className: 'is-' + attributes.type, role: 'note' } );
			const innerBlocksProps = useInnerBlocksProps(
				{ className: 'studio-docs-callout__content' },
				{ template: [ [ 'core/paragraph' ] ], templateLock: false }
			);
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Callout', 'studio-docs' ) },
						el( SelectControl, {
							label: __( 'Type', 'studio-docs' ),
							value: attributes.type,
							options: TYPES,
							onChange: ( type ) => setAttributes( { type } ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( RichText, {
						tagName: 'p',
						className: 'studio-docs-callout__title',
						identifier: 'title',
						value: attributes.title,
						placeholder: label( attributes.type ),
						'aria-label': __( 'Callout title', 'studio-docs' ),
						allowedFormats: [],
						withoutInteractiveFormatting: true,
						onChange: ( title ) => setAttributes( { title } ),
					} ),
					el( 'div', innerBlocksProps )
				)
			);
		},
		save: () => el( wp.blockEditor.InnerBlocks.Content ),
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'core/quote', 'core/group' ],
					transform: ( attributes, innerBlocks ) => wp.blocks.createBlock( 'studio-docs/callout', {}, innerBlocks ),
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/group' ],
					transform: ( attributes, innerBlocks ) => wp.blocks.createBlock( 'core/group', {}, innerBlocks ),
				},
			],
		},
	} );
} )( window.wp );
