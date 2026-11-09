/**
 * Docs menu toggle: previewed from the server; nothing to set.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/menu-toggle', {
		edit: () => el( 'div', wp.blockEditor.useBlockProps( { className: 'studio-docs-inline-preview' } ), el( wp.serverSideRender, { block: 'studio-docs/menu-toggle' } ) ),
		save: () => null,
	} );
} )( window.wp );
