<?php
/**
 * Rendering helpers shared by the blocks: the docs page a block renders for, icons, the code
 * highlights, the table of contents, the sidebar tree, the previous and next pages.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Render;

use WP_Block;
use WP_Post;

defined( 'WPINC' ) || die();

/**
 * The docs page a block renders for: the block's `postId` context (templates pass the queried page,
 * the editor the one being edited), else the queried page, else the docs home (the Site Editor's
 * template preview, where there is no page).
 *
 * @param WP_Block|null $block Block, for its context.
 * @return WP_Post|null
 */
function current_doc( $block = null ) {
	$id = 0;
	if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
		$id = (int) $block->context['postId'];
	}
	if ( ! $id ) {
		$id = \WP_Docs\Agents::current_page_id();
	}
	if ( ! $id ) {
		$post = get_post();
		if ( $post instanceof WP_Post && \WP_Docs\POST_TYPE === $post->post_type ) {
			$id = $post->ID;
		}
	}
	if ( ! $id ) {
		$id = \WP_Docs\Permalinks::post_for_path( '' );
	}
	$post = $id ? get_post( $id ) : null;
	return $post instanceof WP_Post && \WP_Docs\POST_TYPE === $post->post_type ? $post : null;
}

/**
 * Root-relative URL of a tree node (the site is exported as static files: links never carry a host).
 *
 * @param array $node Node.
 * @return string
 */
function node_href( array $node ) {
	return wp_make_link_relative( $node['url'] );
}

/**
 * An inline icon (Lucide outlines).
 *
 * @param string $name  Icon name.
 * @param int    $size  Width and height in pixels.
 * @param string $class Extra classes.
 * @return string
 */
function icon( $name, $size = 16, $class = '' ) {
	$paths = array(
		'arrow-left'     => '<path d="m12 19-7-7 7-7M19 12H5"/>',
		'arrow-right'    => '<path d="M5 12h14m-7-7 7 7-7 7"/>',
		'arrow-up'       => '<path d="m5 12 7-7 7 7m-7 7V5"/>',
		'arrow-up-right' => '<path d="M7 7h10v10M7 17 17 7"/>',
		'check'          => '<path d="M20 6 9 17l-5-5"/>',
		'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
		'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
		'copy'           => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'external-link'  => '<path d="M15 3h6v6m-11 5L21 3m-3 10v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'file'           => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		'menu'           => '<path d="M4 5h16M4 12h16M4 19h16"/>',
		'moon'           => '<path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/>',
		'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
	);
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"%2$s>%3$s</svg>',
		(int) $size,
		'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
		$paths[ $name ] ?? ''
	);
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
 * The display name of a code block language.
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
		'ini'          => 'INI',
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
 * The icon drawn next to a code block's language label (the block's stylesheet knows these two).
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
		if ( '' !== $heading['anchor'] ) {
			$items[] = $heading;
		}
	}
	return $items;
}

/**
 * The list of a table of contents.
 *
 * @param array $items toc_items().
 * @return string
 */
function toc_list( array $items ) {
	$html = '<ul class="studio-docs-toc__list">';
	foreach ( $items as $item ) {
		$html .= sprintf(
			'<li class="is-level-%1$d"><a href="#%2$s">%3$s</a></li>',
			(int) $item['level'],
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
	$html = '<ul class="studio-docs-nav">';
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
		$html .= '<li class="studio-docs-nav__group"><p class="studio-docs-nav__heading"><span>' . esc_html( $group['title'] ) . '</span></p>';
		$html .= nav_list( $group['items'], $current, $tree ) . '</li>';
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
	$html = '<ul>';
	foreach ( $ids as $id ) {
		$node = $tree['nodes'][ $id ];
		if ( empty( $tree['children'][ $id ] ) ) {
			$html .= nav_item( $node, $current );
			continue;
		}
		$html .= sprintf(
			'<li class="studio-docs-nav__group"><p class="studio-docs-nav__heading"><a class="studio-docs-nav__heading-link" href="%1$s"><span>%2$s</span></a></p>%3$s</li>',
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
		'<li><a class="studio-docs-nav__link"%1$s href="%2$s">%3$s</a></li>',
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
