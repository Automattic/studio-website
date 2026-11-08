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

	// While a doc or a docs template is edited, the canvas gets the scope class of the docs stylesheet
	// (assets/css/docs-editor.css) and the content root the article's classes, so the preview looks like
	// the page. Everything else in the editor stays untouched.
	const DOCS_TEMPLATES = [ 'studio//single-docs', 'studio//archive-docs' ];
	const editsDocs = () => {
		const editor = wp.data.select( 'core/editor' );
		if ( ! editor ) {
			return false;
		}
		const type = editor.getCurrentPostType();
		if ( type === 'docs' ) {
			return true;
		}
		if ( type === 'wp_template' ) {
			return DOCS_TEMPLATES.includes( String( editor.getCurrentPostId() ) );
		}
		return false;
	};
	const canvasDocument = () => {
		const frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		return frame && frame.contentDocument && frame.contentDocument.body ? frame.contentDocument : document;
	};
	const applyScope = () => {
		const doc = canvasDocument();
		const on = editsDocs();
		const body = doc.body;
		if ( ! body ) {
			return;
		}
		body.classList.toggle( 'studio-docs-canvas', on );
		const root = doc.querySelector( '.is-root-container' );
		if ( root ) {
			const isPost = wp.data.select( 'core/editor' ).getCurrentPostType() === 'docs';
			for ( const c of [ 'prose', 'mx-auto', 'max-w-content' ] ) {
				root.classList.toggle( c, on && isPost );
			}
		}
	};
	wp.data.subscribe( applyScope );
	setInterval( applyScope, 1000 );
} )( window.wp );
