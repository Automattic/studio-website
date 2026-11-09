/**
 * Docs theme toggle: previewed from the server; nothing to set.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/theme-toggle', {
		edit: () => el( 'div', wp.blockEditor.useBlockProps( { className: 'studio-docs-inline-preview' } ), el( wp.serverSideRender, { block: 'studio-docs/theme-toggle' } ) ),
		save: () => null,
	} );
} )( window.wp );
