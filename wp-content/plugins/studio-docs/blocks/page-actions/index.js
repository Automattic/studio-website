/**
 * Docs page actions: previewed from the server for the page being edited.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/page-actions', {
		edit: ( { context } ) =>
			el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, {
					block: 'studio-docs/page-actions',
					urlQueryArgs: context.postId ? { post_id: context.postId } : {},
					EmptyResponsePlaceholder: () => el( 'p', { className: 'studio-docs-empty-preview' }, wp.i18n.__( 'Page actions: shown for a docs page.', 'studio-docs' ) ),
				} )
			),
		save: () => null,
	} );
} )( window.wp );
