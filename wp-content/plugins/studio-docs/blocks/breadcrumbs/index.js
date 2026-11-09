/**
 * Docs breadcrumbs: previewed from the server for the page being edited.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	wp.blocks.registerBlockType( 'studio-docs/breadcrumbs', {
		edit: ( { context } ) =>
			el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, {
					block: 'studio-docs/breadcrumbs',
					urlQueryArgs: context.postId ? { post_id: context.postId } : {},
					EmptyResponsePlaceholder: () => el( 'p', { className: 'studio-docs-empty-preview' }, wp.i18n.__( 'Breadcrumbs: shown when the page belongs to a group or has a parent.', 'studio-docs' ) ),
				} )
			),
		save: () => null,
	} );
} )( window.wp );
