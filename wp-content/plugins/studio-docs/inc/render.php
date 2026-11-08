<?php
/**
 * Rendering helpers shared by the blocks: the current page, the sidebar tree, icons, code highlights.
 *
 * The markup and class names follow the Blume build of the docs element by element (the Tailwind
 * utility classes are the spec; assets/css/docs.css is Blume's compiled stylesheet, pruned to what
 * these pages use), so the WordPress version looks the same in both color schemes.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Render;

use WP_Post;

defined( 'WPINC' ) || die();

/**
 * The docs page the request shows: the single page, or the home on /docs/.
 *
 * @return WP_Post|null
 */
function current_doc() {
	$id = \WP_Docs\Agents::current_page_id();
	if ( $id ) {
		return get_post( $id );
	}
	// Outside a front-end request (the editor's server-side previews), the post being edited.
	$post = get_post();
	return $post instanceof WP_Post && \WP_Docs\POST_TYPE === $post->post_type ? $post : null;
}

/**
 * Tree node of the current page.
 *
 * @return array|null
 */
function current_node() {
	$post = current_doc();
	if ( ! $post ) {
		return null;
	}
	$tree = wp_docs_get_tree();
	return $tree['nodes'][ $post->ID ] ?? null;
}

/**
 * Root-relative URL of a tree node (the site is static: links never carry a host).
 *
 * @param array $node Node.
 * @return string
 */
function node_href( array $node ) {
	return wp_make_link_relative( $node['url'] );
}

/**
 * An icon from the sprite printed in the footer (Blume's `#blume-i-*` symbols).
 *
 * @param string $name  Symbol name.
 * @param int    $size  Width and height.
 * @param string $class Extra classes.
 * @return string
 */
function icon( $name, $size = 16, $class = '' ) {
	return sprintf(
		'<svg aria-hidden="true"%s height="%d" width="%d"><use href="#blume-i-%s"></use></svg>',
		'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
		(int) $size,
		(int) $size,
		esc_attr( $name )
	);
}

/**
 * An inline Lucide-style icon (what Blume's scripts draw for the copy buttons and the search rows).
 *
 * @param string $name  check|copy|file|chevron-down|search.
 * @param int    $size  Size.
 * @param string $class Classes.
 * @return string
 */
function inline_icon( $name, $size = 14, $class = '' ) {
	$paths = array(
		'check'        => '<path d="M20 6 9 17l-5-5"/>',
		'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
		'copy'         => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'file'         => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		'search'       => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
	);
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%2$s>%3$s</svg>',
		(int) $size,
		'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
		$paths[ $name ] ?? ''
	);
}

/**
 * The SVG sprite with every symbol the blocks use.
 *
 * @return string
 */
function sprite() {
	$stroke = 'fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"';
	return '<svg aria-hidden="true" hidden xmlns="http://www.w3.org/2000/svg">'
		. '<symbol id="blume-i-menu" viewBox="0 0 24 24"><path ' . $stroke . ' d="M4 5h16M4 12h16M4 19h16"/></symbol>'
		. '<symbol id="blume-i-chevron-right" viewBox="0 0 24 24"><path ' . $stroke . ' d="m9 18l6-6l-6-6"/></symbol>'
		. '<symbol id="blume-i-sun" viewBox="0 0 24 24"><g ' . $stroke . '><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></g></symbol>'
		. '<symbol id="blume-i-moon" viewBox="0 0 24 24"><path ' . $stroke . ' d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></symbol>'
		. '<symbol id="blume-i-arrow-up-right" viewBox="0 0 24 24"><path ' . $stroke . ' d="M7 7h10v10M7 17L17 7"/></symbol>'
		. '<symbol id="blume-i-chevron-down" viewBox="0 0 24 24"><path ' . $stroke . ' d="m6 9l6 6l6-6"/></symbol>'
		. '<symbol id="blume-i-arrow-left" viewBox="0 0 24 24"><path ' . $stroke . ' d="m12 19l-7-7l7-7m7 7H5"/></symbol>'
		. '<symbol id="blume-i-arrow-right" viewBox="0 0 24 24"><path ' . $stroke . ' d="M5 12h14m-7-7l7 7l-7 7"/></symbol>'
		. '<symbol id="blume-i-arrow-up" viewBox="0 0 24 24"><path ' . $stroke . ' d="m5 12l7-7l7 7m-7 7V5"/></symbol>'
		. '<symbol id="blume-i-copy" viewBox="0 0 24 24"><g ' . $stroke . '><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></g></symbol>'
		. '<symbol id="blume-i-external-link" viewBox="0 0 24 24"><path ' . $stroke . ' d="M15 3h6v6m-11 5L21 3m-3 10v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></symbol>'
		. '<symbol id="blume-i-search" viewBox="0 0 24 24"><g ' . $stroke . '><path d="m21 21l-4.34-4.34"/><circle cx="11" cy="11" r="8"/></g></symbol>'
		. '</svg>';
}

/**
 * URL of a plugin asset.
 *
 * @param string $path Path under assets/.
 * @return string
 */
function asset( $path ) {
	return plugins_url( 'assets/' . ltrim( $path, '/' ), \WP_Docs\PLUGIN_FILE );
}

/**
 * The display name of a code block language (Blume's map).
 *
 * @param string $lang Fence language.
 * @return string
 */
function language_label( $lang ) {
	$labels = array(
		'astro'        => 'Astro',
		'bash'         => 'Bash',
		'c'            => 'C',
		'c#'           => 'C#',
		'c++'          => 'C++',
		'console'      => 'Console',
		'cpp'          => 'C++',
		'cs'           => 'C#',
		'csharp'       => 'C#',
		'css'          => 'CSS',
		'dart'         => 'Dart',
		'diff'         => 'Diff',
		'docker'       => 'Dockerfile',
		'dockerfile'   => 'Dockerfile',
		'elixir'       => 'Elixir',
		'go'           => 'Go',
		'gql'          => 'GraphQL',
		'graphql'      => 'GraphQL',
		'html'         => 'HTML',
		'http'         => 'HTTP',
		'java'         => 'Java',
		'javascript'   => 'JavaScript',
		'js'           => 'JavaScript',
		'json'         => 'JSON',
		'jsonc'        => 'JSONC',
		'jsx'          => 'JSX',
		'kotlin'       => 'Kotlin',
		'kt'           => 'Kotlin',
		'lua'          => 'Lua',
		'markdown'     => 'Markdown',
		'md'           => 'Markdown',
		'mdx'          => 'MDX',
		'php'          => 'PHP',
		'plaintext'    => 'Text',
		'powershell'   => 'PowerShell',
		'ps1'          => 'PowerShell',
		'py'           => 'Python',
		'python'       => 'Python',
		'rb'           => 'Ruby',
		'rs'           => 'Rust',
		'ruby'         => 'Ruby',
		'rust'         => 'Rust',
		'scala'        => 'Scala',
		'scss'         => 'SCSS',
		'sh'           => 'Shell',
		'shell'        => 'Shell',
		'shellsession' => 'Shell Session',
		'sql'          => 'SQL',
		'svelte'       => 'Svelte',
		'swift'        => 'Swift',
		'text'         => 'Text',
		'toml'         => 'TOML',
		'ts'           => 'TypeScript',
		'tsx'          => 'TSX',
		'txt'          => 'Text',
		'typescript'   => 'TypeScript',
		'vue'          => 'Vue',
		'xml'          => 'XML',
		'yaml'         => 'YAML',
		'yml'          => 'YAML',
		'zsh'          => 'Zsh',
	);
	$lang = strtolower( (string) $lang );
	return $labels[ $lang ] ?? $lang;
}

/**
 * The icon drawn next to a code block's language label (Blume's `data-icon`).
 *
 * @param string $lang Fence language.
 * @return string Empty when there is none.
 */
function language_icon( $lang ) {
	switch ( strtolower( (string) $lang ) ) {
		case 'bash':
		case 'sh':
		case 'shell':
		case 'zsh':
		case 'shellscript':
			return 'gnubash';
		case 'json':
		case 'jsonc':
			return 'json';
	}
	return '';
}

/**
 * The pre-rendered Shiki highlight of a code block, from build/docs/highlights.json (written by
 * scripts/build-docs-manifest.mjs next to the manifest), keyed by language and code.
 *
 * @param string $lang Fence language.
 * @param string $code Code (text, not HTML).
 * @return array|null { style, html }
 */
function highlight( $lang, $code ) {
	static $highlights = null;
	if ( null === $highlights ) {
		$highlights = array();
		$file       = dirname( \WP_Docs\manifest_location() ) . '/highlights.json';
		if ( is_file( $file ) ) {
			$decoded    = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$highlights = is_array( $decoded ) ? $decoded : array();
		}
		/**
		 * Filters the code highlights available to the code block, keyed by sha1( "<lang>\n<code>" ).
		 *
		 * @param array $highlights key => { lang, style, html }.
		 */
		$highlights = (array) apply_filters( 'wp_docs_code_highlights', $highlights );
	}
	$key = sha1( strtolower( (string) $lang ) . "\n" . rtrim( (string) $code, "\n" ) );
	return $highlights[ $key ] ?? null;
}

/**
 * The headings a page's table of contents lists: H2 and H3 with their ids.
 *
 * @param WP_Post $post Post.
 * @return array<int,array{level: int, text: string, anchor: string}>
 */
function toc_items( WP_Post $post ) {
	$items = array();
	foreach ( \WP_Docs\Transforms\Anchors\headings( (string) $post->post_content, array( 2, 3 ) ) as $heading ) {
		if ( '' === $heading['anchor'] ) {
			continue;
		}
		$items[] = $heading;
	}
	return $items;
}

/**
 * The list of a table of contents.
 *
 * @param array  $items      toc_items().
 * @param string $list_class Classes of the `<ul>`.
 * @param string $link_class Classes of each link.
 * @return string
 */
function toc_list( array $items, $list_class, $link_class ) {
	$html = '<ul class="' . esc_attr( $list_class ) . '">';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li data-depth="%1$d" style="padding-inline-start:%2$srem"><a class="%3$s" href="#%4$s">%5$s</a></li>',
			(int) $item['level'],
			3 === (int) $item['level'] ? '0.75' : '0',
			esc_attr( $link_class ),
			esc_attr( $item['anchor'] ),
			esc_html( $item['text'] )
		);
	}
	return $html . '</ul>';
}

/**
 * The pages the search dialog lists before a query is typed: the home and the first pages of the sidebar.
 *
 * @return array[] { href, label }
 */
function popular_pages() {
	$tree    = wp_docs_get_tree();
	$popular = array();
	if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
		$popular[] = $tree['nodes'][ $tree['home'] ];
	}
	foreach ( $tree['order'] as $id ) {
		if ( count( $popular ) >= 6 ) {
			break;
		}
		$popular[] = $tree['nodes'][ $id ];
	}
	return array_map(
		static fn( $node ) => array(
			'href'  => node_href( $node ),
			'label' => html_entity_decode( $node['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		),
		$popular
	);
}

/**
 * The sidebar tree as HTML: a flat list with a heading per group, and a sub-heading for a page with
 * children (listed under it, the page itself first).
 *
 * @param int $current Current post ID.
 * @return string
 */
function nav_tree( $current ) {
	$tree = wp_docs_get_tree();
	$html = '<ul class="m-0 list-none space-y-px p-0">';
	if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
		$html .= nav_item( $tree['nodes'][ $tree['home'] ], $current );
	}

	$groups = array();
	foreach ( $tree['sections'] as $id ) {
		$node                        = $tree['nodes'][ $id ];
		$groups[ $node['group'] ]  ??= array(
			'title' => $node['group_title'],
			'items' => array(),
		);
		$groups[ $node['group'] ]['items'][] = $id;
	}
	foreach ( $groups as $group ) {
		$html .= '<li class="mt-6"><p class="blume-nav-heading"><span class="truncate">' . esc_html( $group['title'] ) . '</span></p>';
		$html .= '<div class="space-y-0.5">' . nav_list( $group['items'], $current, $tree ) . '</div></li>';
	}
	return $html . '</ul>';
}

/**
 * A list of pages; a page with children becomes a sub-heading with its own list.
 *
 * @param int[] $ids     Post IDs.
 * @param int   $current Current post ID.
 * @param array $tree    The tree.
 * @return string
 */
function nav_list( array $ids, $current, array $tree ) {
	$html = '<ul class="m-0 list-none space-y-px p-0">';
	foreach ( $ids as $id ) {
		$node = $tree['nodes'][ $id ];
		if ( empty( $tree['children'][ $id ] ) ) {
			$html .= nav_item( $node, $current );
			continue;
		}
		$html .= sprintf(
			'<li class="mt-6"><p class="blume-nav-heading"><a class="blume-nav-heading-link" href="%1$s"><span class="truncate">%2$s</span></a></p><div class="space-y-0.5">%3$s</div></li>',
			esc_url( node_href( $node ) ),
			esc_html( $node['heading'] ),
			nav_list( array_merge( array( $id ), $tree['children'][ $id ] ), $current, array_merge( $tree, array( 'children' => array_diff_key( $tree['children'], array( $id => true ) ) ) ) )
		);
	}
	return $html . '</ul>';
}

/**
 * One sidebar link.
 *
 * @param array $node    Node.
 * @param int   $current Current post ID.
 * @return string
 */
function nav_item( array $node, $current ) {
	return sprintf(
		'<li><a%1$s class="blume-nav-link truncate" href="%2$s">%3$s</a></li>',
		(int) $node['id'] === (int) $current ? ' aria-current="page"' : '',
		esc_url( node_href( $node ) ),
		esc_html( $node['label'] )
	);
}

/**
 * The previous and next pages of the current one, in sidebar order (the home is the first page).
 *
 * @param WP_Post $post Post.
 * @return array{prev: array|null, next: array|null}
 */
function adjacent( WP_Post $post ) {
	$pages  = \WP_Docs\Agents::pages_in_order();
	$result = array(
		'prev' => null,
		'next' => null,
	);
	foreach ( $pages as $i => $node ) {
		if ( (int) $node['id'] === (int) $post->ID ) {
			$result['prev'] = $pages[ $i - 1 ] ?? null;
			$result['next'] = $pages[ $i + 1 ] ?? null;
			break;
		}
	}
	return $result;
}
