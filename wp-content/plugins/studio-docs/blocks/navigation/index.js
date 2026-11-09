/**
 * Docs navigation: the page tree, previewed from the server.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/navigation', {
		edit: ( { context } ) =>
			el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, { block: 'studio-docs/navigation', urlQueryArgs: context.postId ? { post_id: context.postId } : {} } )
			),
		save: () => null,
	} );
} )( window.wp );
