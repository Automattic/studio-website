/**
 * Code block: the code is edited in place (a rich-text `<code>`, as core's code block), the language
 * is picked in the sidebar. The front end renders the block on the server, with the Shiki highlight
 * computed at build time, the language label and the copy button; the editor shows plain code.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
	const { PanelBody, ComboboxControl } = wp.components;
	const { __ } = wp.i18n;

	const LANGUAGES = [
		[ '', __( 'Plain text', 'studio-docs' ) ],
		[ 'bash', 'Bash' ],
		[ 'shell', 'Shell' ],
		[ 'console', 'Console' ],
		[ 'css', 'CSS' ],
		[ 'diff', 'Diff' ],
		[ 'html', 'HTML' ],
		[ 'http', 'HTTP' ],
		[ 'ini', 'INI' ],
		[ 'javascript', 'JavaScript' ],
		[ 'json', 'JSON' ],
		[ 'jsonc', 'JSONC' ],
		[ 'jsx', 'JSX' ],
		[ 'markdown', 'Markdown' ],
		[ 'php', 'PHP' ],
		[ 'powershell', 'PowerShell' ],
		[ 'python', 'Python' ],
		[ 'scss', 'SCSS' ],
		[ 'sql', 'SQL' ],
		[ 'toml', 'TOML' ],
		[ 'tsx', 'TSX' ],
		[ 'typescript', 'TypeScript' ],
		[ 'xml', 'XML' ],
		[ 'yaml', 'YAML' ],
	].map( ( [ value, label ] ) => ( { value, label } ) );

	// The escaping core's code block applies on save: opening square brackets and the `//` of a line
	// that is only a URL, so shortcodes and embeds never run on the code.
	const escape = ( content ) =>
		content
			.replace( /\[/g, '&#91;' )
			.replace( /^(\s*https?:)\/\/([^\s<>"]+\s*)$/m, '$1&#47;&#47;$2' );

	registerBlockType( 'studio-docs/code', {
		edit: ( { attributes, setAttributes, onRemove, insertBlocksAfter, mergeBlocks } ) => {
			const language = attributes.language || '';
			const label = ( LANGUAGES.find( ( l ) => l.value === language ) || {} ).label || language;
			const blockProps = useBlockProps( { 'data-language': label || __( 'Text', 'studio-docs' ) } );
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Code', 'studio-docs' ) },
						el( ComboboxControl, {
							label: __( 'Language', 'studio-docs' ),
							value: language,
							options: LANGUAGES,
							allowReset: false,
							onChange: ( value ) => setAttributes( { language: value || '' } ),
							help: __( 'Shown above the code and used for syntax highlighting.', 'studio-docs' ),
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'pre',
					blockProps,
					el( RichText, {
						tagName: 'code',
						identifier: 'content',
						value: attributes.content,
						onChange: ( content ) => setAttributes( { content } ),
						onRemove,
						onMerge: mergeBlocks,
						placeholder: __( 'Write code…', 'studio-docs' ),
						'aria-label': __( 'Code', 'studio-docs' ),
						preserveWhiteSpace: true,
						__unstablePastePlainText: true,
						__unstableOnSplitAtDoubleLineEnd: () => insertBlocksAfter( wp.blocks.createBlock( wp.blocks.getDefaultBlockName() ) ),
					} )
				)
			);
		},
		save: ( { attributes } ) =>
			el(
				'pre',
				useBlockProps.save(),
				el( RichText.Content, { tagName: 'code', value: escape( attributes.content.toString() ) } )
			),
		merge: ( attributes, attributesToMerge ) => ( { content: attributes.content + '\n\n' + attributesToMerge.content } ),
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'core/code', 'core/preformatted' ],
					transform: ( { content, anchor } ) => wp.blocks.createBlock( 'studio-docs/code', { content, anchor } ),
				},
				{
					type: 'enter',
					regExp: /^```$/,
					transform: () => wp.blocks.createBlock( 'studio-docs/code' ),
				},
				{
					type: 'raw',
					isMatch: ( node ) => node.nodeName === 'PRE' && node.children.length === 1 && node.firstChild.nodeName === 'CODE',
					schema: { pre: { children: { code: { children: { '#text': {} } } } } },
					transform: ( node ) => {
						const match = /(?:^|\s)language-([\w-]+)/.exec( node.firstChild.getAttribute( 'class' ) || '' );
						return wp.blocks.createBlock( 'studio-docs/code', {
							content: node.firstChild.textContent.replace( /\n$/, '' ),
							language: match ? match[ 1 ].toLowerCase() : '',
						} );
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/code' ],
					transform: ( { content, anchor } ) => wp.blocks.createBlock( 'core/code', { content, anchor } ),
				},
			],
		},
	} );
} )( window.wp );
