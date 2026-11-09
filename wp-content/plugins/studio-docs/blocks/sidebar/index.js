/**
 * Docs sidebar: a container for the navigation (and anything else), rendered as the drawer / sticky
 * column on the front end.
 */
( function ( wp ) {
	const { createElement: el } = wp.element;
	const { useBlockProps, useInnerBlocksProps, InnerBlocks } = wp.blockEditor;
	wp.blocks.registerBlockType( 'studio-docs/sidebar', {
		edit: () => {
			const blockProps = useBlockProps();
			const innerBlocksProps = useInnerBlocksProps( blockProps, {
				template: [ [ 'studio-docs/navigation' ] ],
			} );
			return el( 'aside', innerBlocksProps );
		},
		save: () => el( InnerBlocks.Content ),
	} );
} )( window.wp );
