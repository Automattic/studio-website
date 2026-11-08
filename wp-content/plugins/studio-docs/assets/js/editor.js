/**
 * The docs blocks in the block editor. The blocks are rendered on the server; here each one is declared
 * so the editor recognises it, previews it through the server-side renderer, and saves it as its block
 * comment (the content blocks keep their content in attributes, the callout in its inner blocks).
 *
 * The docs are regenerated from the markdown in Automattic/studio on every build, so the editor is for
 * looking, not for writing: none of the blocks is in the inserter.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const ServerSideRender = wp.serverSideRender;
	const { Notice } = wp.components;

	const serverRendered = ( name, title, icon, extra = {} ) =>
		registerBlockType( 'studio-docs/' + name, {
			apiVersion: 3,
			title,
			icon,
			category: extra.category || 'theme',
			supports: { html: false, inserter: false },
			attributes: extra.attributes || {},
			edit: ( { attributes } ) =>
				el( 'div', useBlockProps(), el( ServerSideRender, { block: 'studio-docs/' + name, attributes, httpMethod: 'POST' } ) ),
			save: () => null,
		} );

	// Template blocks (the layout of a docs page).
	serverRendered( 'header', 'Docs: Header', 'menu' );
	serverRendered( 'sidebar', 'Docs: Sidebar', 'editor-ul' );
	serverRendered( 'breadcrumbs', 'Docs: Breadcrumbs', 'ellipsis' );
	serverRendered( 'toc', 'Docs: On this page', 'list-view', {
		attributes: { variant: { type: 'string', default: 'rail' } },
	} );
	serverRendered( 'article', 'Docs: Article', 'media-document' );
	serverRendered( 'page-actions', 'Docs: Page actions', 'admin-links' );
	serverRendered( 'pagination', 'Docs: Previous and next', 'leftright' );
	serverRendered( 'footer', 'Docs: Footer', 'minus' );

	// Containers: inner blocks in the editor, rendered around them on the server.
	const container = ( name, title, icon ) =>
		registerBlockType( 'studio-docs/' + name, {
			apiVersion: 3,
			title,
			icon,
			category: 'theme',
			supports: { html: false, inserter: false },
			edit: () => el( 'div', useBlockProps(), el( InnerBlocks ) ),
			save: () => el( InnerBlocks.Content ),
		} );
	container( 'layout', 'Docs: Layout', 'columns' );
	container( 'main', 'Docs: Main column', 'align-center' );
	container( 'rail', 'Docs: Right rail', 'align-pull-right' );

	// Content blocks the importer writes.
	serverRendered( 'code', 'Docs: Code', 'editor-code', {
		category: 'text',
		attributes: { language: { type: 'string', default: '' }, code: { type: 'string', default: '' } },
	} );
	serverRendered( 'image', 'Docs: Image', 'format-image', {
		category: 'text',
		attributes: {
			id: { type: 'integer' },
			src: { type: 'string', default: '' },
			alt: { type: 'string', default: '' },
			title: { type: 'string' },
			href: { type: 'string' },
			caption: { type: 'string' },
		},
	} );
	serverRendered( 'table', 'Docs: Table', 'editor-table', {
		category: 'text',
		attributes: { html: { type: 'string', default: '' } },
	} );
	registerBlockType( 'studio-docs/callout', {
		apiVersion: 3,
		title: 'Docs: Callout',
		icon: 'info',
		category: 'text',
		supports: { html: false, inserter: false },
		attributes: { type: { type: 'string', default: 'note' } },
		edit: ( { attributes } ) =>
			el(
				'div',
				useBlockProps( { className: 'studio-docs-callout is-' + attributes.type } ),
				el( Notice, { status: 'info', isDismissible: false }, attributes.type.charAt( 0 ).toUpperCase() + attributes.type.slice( 1 ) ),
				el( InnerBlocks )
			),
		save: () => el( InnerBlocks.Content ),
	} );
} )( window.wp );
