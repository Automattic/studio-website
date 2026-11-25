/* Previewed from the server; the generator itself runs on the front end. */
( function ( wp ) {
	const el = wp.element.createElement;

	wp.blocks.registerBlockType( 'studio/blueprint-button', {
		edit: () => el( 'div', wp.blockEditor.useBlockProps(), el( wp.serverSideRender, { block: 'studio/blueprint-button' } ) ),
		save: () => null,
	} );
} )( window.wp );
