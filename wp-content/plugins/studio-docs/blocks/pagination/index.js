/**
 * Docs previous and next: previewed from the server for the page being edited.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/pagination', {
		edit: ( { context } ) =>
			el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, {
					block: 'studio-docs/pagination',
					urlQueryArgs: context.postId ? { post_id: context.postId } : {},
					EmptyResponsePlaceholder: () => el( 'p', { className: 'studio-docs-empty-preview' }, wp.i18n.__( 'Previous and next pages: shown when the page has neighbours in the sidebar order.', 'studio-docs' ) ),
				} )
			),
		save: () => null,
	} );
} )( window.wp );
