<?php
/**
 * The docs blocks: template blocks that render the docs layout (header with search and the theme
 * toggle, sidebar, breadcrumbs, table of contents, article, page actions, pagination, footer) and
 * the content blocks the importer writes instead of core ones (code, image, table, callout).
 *
 * All server-rendered, none in the inserter. Each reads the queried docs page.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Blocks;

use WP_Docs\Render;

defined( 'WPINC' ) || die();

add_action( 'init', __NAMESPACE__ . '\register' );

/**
 * Register the blocks.
 */
function register() {
	$template = array(
		'header'       => array(),
		'sidebar'      => array(),
		'layout'       => array(),
		'main'         => array(),
		'rail'         => array(),
		'breadcrumbs'  => array(),
		'toc'          => array(
			'variant' => array(
				'type'    => 'string',
				'default' => 'rail',
			),
		),
		'article'      => array(),
		'page-actions' => array(),
		'pagination'   => array(),
		'footer'       => array(),
	);
	foreach ( $template as $name => $attributes ) {
		register_block_type(
			'studio-docs/' . $name,
			array(
				'api_version'     => 3,
				'title'           => 'Docs: ' . ucwords( str_replace( '-', ' ', $name ) ),
				'category'        => 'theme',
				'attributes'      => $attributes,
				'supports'        => array(
					'html'     => false,
					'inserter' => false,
				),
				'render_callback' => __NAMESPACE__ . '\render_' . str_replace( '-', '_', $name ),
			)
		);
	}

	$content = array(
		'code'    => array(
			'language' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'image'   => array(
			'id'   => array( 'type' => 'integer' ),
			'href' => array( 'type' => 'string' ),
		),
		'table'   => array(),
		'callout' => array(
			'type' => array(
				'type'    => 'string',
				'default' => 'note',
			),
		),
	);
	foreach ( $content as $name => $attributes ) {
		register_block_type(
			'studio-docs/' . $name,
			array(
				'api_version'     => 3,
				'title'           => 'Docs: ' . ucfirst( $name ),
				'category'        => 'text',
				'attributes'      => $attributes,
				'supports'        => array(
					'html'     => false,
					'inserter' => false,
				),
				'render_callback' => __NAMESPACE__ . '\render_' . $name,
			)
		);
	}
}

/*
 * ---------------------------------------------------------------------------------------------
 * Template blocks.
 * ---------------------------------------------------------------------------------------------
 */

/**
 * The skip link, the header (menu button, logo, Download, search, theme toggle) and the search dialog.
 *
 * @return string
 */
function render_header() {
	$logo       = Render\asset( 'images/logo.svg' );
	$logo_dark  = Render\asset( 'images/logo-dark.svg' );
	$icon_class = 'inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground';
	$popular    = wp_json_encode( Render\popular_pages(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	ob_start();
	?>
<a class="absolute start-[-999px] top-0 z-[100] bg-accent px-4 py-2 text-accent-foreground focus:start-0" href="#blume-content">Skip to content</a>
<span hidden id="blume-new-tab-hint">Opens in a new tab</span>
<header class="sticky top-0 z-40 flex h-16 items-center gap-2 bg-background/90 px-4 backdrop-blur sm:gap-3 md:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(max-content,1fr)]" data-blume-header>
	<div class="flex min-w-0 items-center gap-2 sm:gap-3 lg:col-start-1">
		<button aria-controls="blume-nav-drawer" aria-expanded="false" aria-label="Toggle navigation" class="<?php echo esc_attr( $icon_class ); ?> lg:hidden" data-blume-nav-toggle type="button"><?php echo Render\icon( 'menu', 20 ); ?></button>
		<a class="inline-flex min-w-0 items-center gap-2 font-semibold text-base text-foreground" href="/docs/">
			<img alt="WordPress Studio" class="h-5 w-auto shrink-0 dark:hidden" height="1024" src="<?php echo esc_url( $logo ); ?>" width="1024">
			<img alt="WordPress Studio" class="hidden h-5 w-auto shrink-0 dark:block" height="1024" src="<?php echo esc_url( $logo_dark ); ?>" width="1024">
			<span class="truncate">WordPress Studio</span>
		</a>
	</div>
	<div class="flex-1 lg:hidden"></div>
	<div class="flex shrink-0 items-center gap-1 lg:col-start-3 lg:justify-self-end">
		<a class="hidden sm:inline-flex items-center gap-1 rounded-full bg-accent px-3.5 py-1.5 font-medium text-accent-foreground text-sm transition-opacity hover:opacity-90" href="https://wordpress.studio/download/" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank">Download<?php echo Render\icon( 'chevron-right', 14 ); ?></a>
		<blume-search class="contents" data-index="/docs/search-index.json">
			<button aria-keyshortcuts="Meta+K Control+K" aria-label="Search" class="<?php echo esc_attr( $icon_class ); ?>" data-blume-search-open data-blume-tooltip="Search (⁦⌘K⁩)" data-label="Search" type="button"><?php echo Render\icon( 'search', 18 ); ?></button>
			<dialog aria-label="Search docs" class="mx-auto mt-[8vh] mb-auto h-[min(480px,90dvh)] w-[min(62.5rem,94vw)] flex-col overflow-hidden rounded-blume border border-border bg-background/80 p-0 text-foreground shadow-2xl outline-none backdrop-blur-xl backdrop:bg-black/30 open:flex sm:mt-auto" data-blume-search-dialog>
				<div class="flex items-center gap-2.5 border-border border-b px-4 py-3 text-muted-foreground">
					<?php echo Render\icon( 'search', 18 ); ?>
					<input aria-autocomplete="list" aria-controls="blume-search-listbox" aria-expanded="false" aria-label="Search docs" autocomplete="off" class="flex-1 border-0 bg-transparent text-foreground text-sm pointer-coarse:text-base focus:outline-none [&amp;::-webkit-search-cancel-button]:appearance-none" data-blume-search-input placeholder="Search documentation…" role="combobox" type="search">
					<kbd class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[0.7rem]">Esc</kbd>
				</div>
				<div class="grid min-h-0 flex-1 md:grid-cols-[22rem_minmax(0,1fr)]" data-blume-search-grid>
					<div class="flex min-h-0 flex-col md:border-border md:border-e">
						<div class="flex max-h-[5.75rem] flex-wrap gap-1.5 overflow-x-hidden overflow-y-auto overscroll-contain border-border border-b px-3 py-2 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent" data-blume-search-filters hidden></div>
						<div aria-label="Search docs" class="min-h-0 flex-1 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent overflow-y-auto p-2" data-blume-search-results id="blume-search-listbox" role="listbox"></div>
						<p class="m-0 px-4 py-6 text-center text-muted-foreground text-sm" data-blume-search-message hidden></p>
					</div>
					<div class="hidden min-h-0 scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent overflow-y-auto p-5 md:block" data-blume-search-preview></div>
				</div>
				<div class="flex items-center justify-between gap-3 border-border border-t px-3 py-2 text-muted-foreground text-xs">
					<span></span>
					<div class="flex items-center gap-3">
						<span class="flex items-center gap-1"><kbd class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[0.65rem]">↑</kbd><kbd class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[0.65rem]">↓</kbd>navigate</span>
						<span class="flex items-center gap-1"><kbd class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[0.65rem]">↵</kbd>open</span>
						<span class="flex items-center gap-1 max-md:hidden"><kbd class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[0.65rem]" data-blume-search-preview-kbd dir="ltr">⌘J</kbd>preview</span>
					</div>
				</div>
			</dialog>
			<script data-blume-search-popular type="application/json"><?php echo $popular; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON. ?></script>
		</blume-search>
		<button aria-label="Toggle theme" class="<?php echo esc_attr( $icon_class ); ?>" data-blume-theme-toggle data-blume-tooltip="Toggle theme" type="button">
			<span class="inline-flex dark:hidden"><?php echo Render\icon( 'sun', 18 ); ?></span>
			<span class="hidden dark:inline-flex"><?php echo Render\icon( 'moon', 18 ); ?></span>
		</button>
	</div>
</header>
	<?php
	return (string) ob_get_clean();
}

/**
 * The sidebar: a drawer below the `lg` breakpoint, a sticky column above it.
 *
 * @return string
 */
function render_sidebar() {
	$post    = Render\current_doc();
	$current = $post ? $post->ID : 0;

	ob_start();
	?>
<aside aria-label="Primary" data-blume-nav-drawer id="blume-nav-drawer" class="fixed top-[var(--blume-drawer-top,4rem)] start-0 z-[35] h-[calc(100dvh-var(--blume-drawer-top,4rem))] w-64 max-w-[80vw] -translate-x-[105%] overflow-y-auto border-border border-e bg-background px-5 pt-4 pb-6 transition-transform rtl:translate-x-[105%] [:where([data-blume-nav-open])_&amp;]:translate-x-0! lg:sticky lg:top-16 lg:z-auto lg:h-[calc(100dvh-4rem)] lg:w-auto lg:max-w-none lg:translate-x-0! lg:scrollbar-thin lg:scrollbar-thumb-border lg:scrollbar-track-transparent lg:border-e-0 lg:bg-transparent lg:px-4">
	<nav aria-label="Featured" class="mb-4 border-border border-b pb-4">
		<ul class="m-0 list-none p-0">
			<li><a class="block rounded-[0.65rem] px-2.5 py-1.5 text-muted-foreground text-sm transition-colors hover:bg-muted hover:text-foreground" href="https://wordpress.studio" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank"><span class="flex items-center gap-2"><span class="flex-1 truncate">WordPress Studio</span><?php echo Render\icon( 'arrow-up-right', 12, 'shrink-0 text-muted-foreground opacity-60' ); ?></span></a></li>
		</ul>
	</nav>
	<nav data-blume-nav-tree><?php echo Render\nav_tree( $current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
</aside>
	<?php
	return (string) ob_get_clean();
}

/**
 * The three-column grid.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Inner blocks.
 * @return string
 */
function render_layout( $attributes, $content ) {
	return '<div class="mx-auto grid grid-cols-1 items-start lg:grid-cols-[17.5rem_minmax(0,1fr)] xl:grid-cols-[17.5rem_minmax(0,1fr)_17.5rem]" data-blume-doc-grid>' . $content . '</div>';
}

/**
 * The main column.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Inner blocks.
 * @return string
 */
function render_main( $attributes, $content ) {
	return '<main class="px-6 pt-6 pb-10 lg:px-8 xl:px-10" id="blume-content">' . $content . '</main>';
}

/**
 * The right rail ("On this page" and the page actions), `xl` and up.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Inner blocks.
 * @return string
 */
function render_rail( $attributes, $content ) {
	return '<aside aria-label="On this page" class="sticky top-16 hidden h-[calc(100dvh-4rem)] scrollbar-thin scrollbar-thumb-border scrollbar-track-transparent overflow-y-auto [--blume-rail-inset:1rem] px-(--blume-rail-inset) pt-6 pb-10 text-sm xl:block" data-blume-toc>' . $content . '</aside>';
}

/**
 * The breadcrumb above the title: the page's group, or its parent page when it has one.
 *
 * @return string
 */
function render_breadcrumbs() {
	$post   = Render\current_doc();
	$crumbs = $post ? wp_docs_get_breadcrumbs( $post ) : array();
	array_pop( $crumbs ); // The page itself is the H1.
	if ( ! $crumbs ) {
		return '';
	}
	$crumb = end( $crumbs );
	$html  = '' !== $crumb['url']
		? '<a class="hover:text-foreground" href="' . esc_url( wp_make_link_relative( $crumb['url'] ) ) . '">' . esc_html( $crumb['title'] ) . '</a>'
		: '<span>' . esc_html( $crumb['title'] ) . '</span>';
	return '<nav aria-label="Breadcrumb" class="mb-2 text-muted-foreground text-sm mx-auto max-w-content">' . $html . '</nav>';
}

/**
 * "On this page": in the rail (default), or as a disclosure above the article below `xl`, where the
 * page actions move into it.
 *
 * @param array $attributes Attributes.
 * @return string
 */
function render_toc( $attributes ) {
	$post  = Render\current_doc();
	$items = $post ? Render\toc_items( $post ) : array();
	$items = array_values( array_filter( $items, static fn( $i ) => (int) $i['level'] >= 2 ) );

	if ( 'mobile' === ( $attributes['variant'] ?? 'rail' ) ) {
		if ( ! $items ) {
			return '<div class="mx-auto mb-6 max-w-content xl:hidden" data-blume-page-actions-mount></div>';
		}
		return '<details class="group mx-auto mb-6 max-w-content rounded-blume border border-border xl:hidden">'
			. '<summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 font-medium text-foreground text-sm [&amp;::-webkit-details-marker]:hidden"><span>On this page</span>' . Render\icon( 'chevron-down', 16, 'text-muted-foreground transition-transform group-open:rotate-180' ) . '</summary>'
			. '<blume-toc class="block">' . Render\toc_list( $items, 'm-0 list-none border-border border-t p-2', 'block rounded-md px-2 py-1.5 text-muted-foreground text-sm transition-colors hover:text-foreground aria-[current=location]:font-medium aria-[current=location]:text-foreground' ) . '</blume-toc>'
			. '<div data-blume-page-actions-mount></div>'
			. '</details>';
	}

	if ( ! $items ) {
		return '';
	}
	return '<p class="mb-3 font-semibold text-foreground text-sm">On this page</p>'
		. '<blume-toc class="block">' . Render\toc_list( $items, 'm-0 list-none p-0', 'block py-1.5 text-muted-foreground transition-colors hover:text-foreground aria-[current=location]:font-medium aria-[current=location]:text-foreground' ) . '</blume-toc>';
}

/**
 * The article: title, lede (the description) and the content.
 *
 * @return string
 */
function render_article() {
	$doc = Render\current_doc();
	if ( ! $doc ) {
		return '';
	}
	$meta        = wp_docs_get_page_meta( $doc );
	$description = $meta['description'] ?? '';

	$html  = '<article class="prose mx-auto max-w-content" dir="ltr">';
	$html .= '<h1>' . esc_html( html_entity_decode( get_the_title( $doc ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . '</h1>';
	if ( '' !== $description ) {
		$html .= '<p class="text-lg text-muted-foreground">' . esc_html( $description ) . '</p>';
	}

	// Render the content with the docs page as the global post, so the block filters know the page.
	global $post;
	$previous = $post;
	$post     = $doc; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	$html .= apply_filters( 'the_content', get_the_content( null, false, $doc ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $post instanceof \WP_Post ) {
		setup_postdata( $post );
	} else {
		wp_reset_postdata();
	}

	return $html . '</article>';
}

/**
 * Scroll to top, Copy as Markdown and the "Open in chat" menu (moved into the mobile disclosure by
 * the script below `xl`).
 *
 * @return string
 */
function render_page_actions() {
	$post = Render\current_doc();
	if ( ! $post ) {
		return '';
	}
	$md         = wp_make_link_relative( wp_docs_markdown_url( $post ) );
	$row_class  = 'flex w-full items-center gap-2.5 py-1.5 text-start text-muted-foreground text-sm transition-colors hover:text-foreground';
	$item_class = 'flex w-full items-start gap-2.5 rounded-md px-2 py-1.5 text-start text-muted-foreground text-sm transition-colors hover:bg-muted hover:text-foreground';
	$ext        = '<span class="flex h-lh shrink-0 items-center">' . Render\icon( 'external-link', 13 ) . '</span>';
	$items      = array(
		'v0'      => array( 'Open in v0', '<svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 147 70" xmlns="http://www.w3.org/2000/svg"><path d="M56 50.2031V14H70V60.1562C70 65.5928 65.5928 70 60.1562 70C57.5605 70 54.9982 68.9992 53.1562 67.1573L0 14H19.7969L56 50.2031Z"/><path d="M147 56H133V23.9531L100.953 56H133V70H96.6875C85.8144 70 77 61.1856 77 50.3125V14H91V46.1562L123.156 14H91V0H127.312C138.186 0 147 8.81439 147 19.6875V56Z"/></svg>' ),
		'chatgpt' => array( 'Open in ChatGPT', '<svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/></svg>' ),
		'claude'  => array( 'Open in Claude', '<svg class="size-4 shrink-0" fill="currentColor" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path clip-rule="evenodd" fill-rule="evenodd" d="M2.3545 7.9775L4.7145 6.654L4.7545 6.539L4.7145 6.475H4.6L4.205 6.451L2.856 6.4145L1.6865 6.366L0.5535 6.305L0.268 6.2445L0 5.892L0.0275 5.716L0.2675 5.5555L0.6105 5.5855L1.3705 5.637L2.5095 5.716L3.3355 5.7645L4.56 5.892H4.7545L4.782 5.8135L4.715 5.7645L4.6635 5.716L3.4845 4.918L2.2085 4.074L1.5405 3.588L1.1785 3.3425L0.9965 3.1115L0.9175 2.6075L1.2455 2.2465L1.686 2.2765L1.7985 2.307L2.245 2.65L3.199 3.388L4.4445 4.3045L4.627 4.4565L4.6995 4.405L4.709 4.3685L4.627 4.2315L3.9495 3.0085L3.2265 1.7635L2.9045 1.2475L2.8195 0.938C2.78711 0.819128 2.76965 0.696687 2.7675 0.5735L3.1415 0.067L3.348 0L3.846 0.067L4.056 0.249L4.366 0.956L4.867 2.0705L5.6445 3.5855L5.8725 4.0345L5.994 4.4505L6.0395 4.578H6.1185V4.505L6.1825 3.652L6.301 2.6045L6.416 1.257L6.456 0.877L6.644 0.422L7.0175 0.176L7.3095 0.316L7.5495 0.6585L7.516 0.8805L7.373 1.806L7.0935 3.2575L6.9115 4.2285H7.0175L7.139 4.1075L7.6315 3.4545L8.4575 2.4225L8.8225 2.0125L9.2475 1.5605L9.521 1.345H10.0375L10.4175 1.9095L10.2475 2.4925L9.7155 3.166L9.275 3.737L8.643 4.587L8.248 5.267L8.2845 5.322L8.3785 5.312L9.8065 5.009L10.578 4.869L11.4985 4.7115L11.915 4.9055L11.9605 5.103L11.7965 5.5065L10.812 5.7495L9.6575 5.9805L7.938 6.387L7.917 6.402L7.9415 6.4325L8.716 6.5055L9.047 6.5235H9.858L11.368 6.636L11.763 6.897L12 7.216L11.9605 7.4585L11.353 7.7685L10.533 7.574L8.6185 7.119L7.9625 6.9545H7.8715V7.0095L8.418 7.5435L9.421 8.4485L10.6755 9.6135L10.739 9.9025L10.578 10.13L10.408 10.1055L9.3055 9.277L8.88 8.9035L7.917 8.0935H7.853V8.1785L8.075 8.503L9.2475 10.2635L9.3085 10.8035L9.2235 10.98L8.9195 11.0865L8.5855 11.0255L7.8985 10.063L7.191 8.9795L6.6195 8.008L6.5495 8.048L6.2125 11.675L6.0545 11.86L5.69 12L5.3865 11.7695L5.2255 11.396L5.3865 10.658L5.581 9.696L5.7385 8.931L5.8815 7.981L5.9665 7.665L5.9605 7.644L5.8905 7.653L5.1735 8.6365L4.0835 10.109L3.2205 11.0315L3.0135 11.1135L2.655 10.9285L2.6885 10.5975L2.889 10.303L4.083 8.785L4.803 7.844L5.268 7.301L5.265 7.222H5.2375L2.066 9.28L1.501 9.353L1.2575 9.125L1.288 8.752L1.4035 8.6305L2.3575 7.9745L2.3545 7.9775Z"/></svg>' ),
		't3'      => array( 'Open in T3 Chat', '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>' ),
		'scira'   => array( 'Open in Scira', '<svg class="size-4 shrink-0" fill="none" viewBox="0 0 910 934" xmlns="http://www.w3.org/2000/svg"><path d="M647.664 197.775C569.13 189.049 525.5 145.419 516.774 66.8849C508.048 145.419 464.418 189.049 385.884 197.775C464.418 206.501 508.048 250.131 516.774 328.665C525.5 250.131 569.13 206.501 647.664 197.775Z" fill="currentColor" stroke="currentColor" stroke-linejoin="round" stroke-width="8"/><path d="M857.5 508.116C763.259 497.644 710.903 445.288 700.432 351.047C689.961 445.288 637.605 497.644 543.364 508.116C637.605 518.587 689.961 570.943 700.432 665.184C710.903 570.943 763.259 518.587 857.5 508.116Z" stroke="currentColor" stroke-linejoin="round" stroke-width="20"/><path d="M889.949 121.237C831.049 114.692 798.326 81.9698 791.782 23.0692C785.237 81.9698 752.515 114.692 693.614 121.237C752.515 127.781 785.237 160.504 791.782 219.404C798.326 160.504 831.049 127.781 889.949 121.237Z" fill="currentColor" stroke="currentColor" stroke-linejoin="round" stroke-width="8"/><path d="M760.632 764.337C720.719 814.616 669.835 855.1 611.872 882.692C553.91 910.285 490.404 924.255 426.213 923.533C362.022 922.812 298.846 907.419 241.518 878.531C184.19 849.643 134.228 808.026 95.4548 756.863C56.6815 705.7 30.1238 646.346 17.8129 583.343C5.50207 520.339 7.76433 455.354 24.4266 393.359C41.089 331.364 71.7099 274.001 113.947 225.658C156.184 177.315 208.919 139.273 268.117 114.442" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="30"/></svg>' ),
		'cursor'  => array( 'Open in Cursor', '<svg class="size-4 shrink-0" viewBox="0 0 466.73 532.09" xmlns="http://www.w3.org/2000/svg"><path d="M457.43,125.94L244.42,2.96c-6.84-3.95-15.28-3.95-22.12,0L9.3,125.94c-5.75,3.32-9.3,9.46-9.3,16.11v247.99c0,6.65,3.55,12.79,9.3,16.11l213.01,122.98c6.84,3.95,15.28,3.95,22.12,0l213.01-122.98c5.75-3.32,9.3-9.46,9.3-16.11v-247.99c0-6.65-3.55-12.79-9.3-16.11h-.01ZM444.05,151.99l-205.63,356.16c-1.39,2.4-5.06,1.42-5.06-1.36v-233.21c0-4.66-2.49-8.97-6.53-11.31L24.87,145.67c-2.4-1.39-1.42-5.06,1.36-5.06h411.26c5.84,0,9.49,6.33,6.57,11.39h-.01Z" fill="currentColor"/></svg>' ),
	);

	$menu = '';
	foreach ( $items as $key => $item ) {
		if ( 'chatgpt' === $key ) {
			$menu .= '<hr class="my-1 border-border border-t">';
		}
		$menu .= '<a class="' . esc_attr( $item_class ) . '" data-open-in="' . esc_attr( $key ) . '" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank" href="#">'
			. '<span class="flex h-lh shrink-0 items-center">' . $item[1] . '</span>'
			. '<span class="flex-1">' . esc_html( $item[0] ) . '</span>' . $ext . '</a>';
	}

	return '<div class="space-y-0.5 mt-8 border-border border-t pt-4" data-blume-page-actions data-i18n-chat-prompt="Read {url} so I can ask you questions about this page." data-i18n-copied="Copied!" data-i18n-copy-failed="Copy failed" data-md="' . esc_attr( $md ) . '">'
		. '<button class="' . esc_attr( $row_class ) . '" data-blume-scroll-top type="button">' . Render\icon( 'arrow-up', 16 ) . 'Scroll to top</button>'
		. '<button class="' . esc_attr( $row_class ) . '" data-blume-copy-page type="button">' . Render\icon( 'copy', 16 ) . '<span data-blume-copy-label>Copy as Markdown</span></button>'
		. '<details class="relative" data-blume-dropdown>'
		. '<summary class="' . esc_attr( $row_class ) . ' cursor-pointer list-none [&amp;::-webkit-details-marker]:hidden">' . Render\icon( 'external-link', 16 ) . 'Open in chat' . Render\icon( 'chevron-down', 14, 'ms-auto transition-transform [details[open]>summary_&]:rotate-180' ) . '</summary>'
		. '<div class="absolute top-full end-0 z-50 mt-1 w-max [--blume-menu-room:calc(100%+var(--blume-rail-inset,1rem)-2px)] min-w-[min(14rem,var(--blume-menu-room))] max-w-(--blume-menu-room) wrap-anywhere rounded-blume border border-border bg-background p-1 shadow-xl" data-blume-menu>' . $menu . '</div>'
		. '</details>'
		. '</div>';
}

/**
 * Previous and next pages.
 *
 * @return string
 */
function render_pagination() {
	$post = Render\current_doc();
	if ( ! $post ) {
		return '';
	}
	$adjacent = Render\adjacent( $post );
	$html     = '<div data-blume-page-end><nav aria-label="Pagination" class="mx-auto mt-12 flex max-w-content justify-between gap-4 border-border border-t pt-6">';
	if ( $adjacent['prev'] ) {
		$html .= '<a class="flex max-w-[48%] flex-1 items-center gap-2 rounded-blume border border-border px-4 py-3 text-foreground transition-colors hover:border-foreground" href="' . esc_url( Render\node_href( $adjacent['prev'] ) ) . '">'
			. Render\icon( 'arrow-left', 16, 'rtl:-scale-x-100' )
			. '<span class="min-w-0"><span class="block text-muted-foreground text-xs">Previous</span><span class="block font-medium max-md:line-clamp-2 max-md:wrap-break-word md:truncate">' . esc_html( $adjacent['prev']['label'] ) . '</span></span></a>';
	}
	if ( $adjacent['next'] ) {
		$html .= '<a class="ms-auto flex max-w-[48%] flex-1 items-center justify-end gap-2 rounded-blume border border-border px-4 py-3 text-end text-foreground transition-colors hover:border-foreground" href="' . esc_url( Render\node_href( $adjacent['next'] ) ) . '">'
			. '<span class="min-w-0"><span class="block text-muted-foreground text-xs">Next</span><span class="block font-medium max-md:line-clamp-2 max-md:wrap-break-word md:truncate">' . esc_html( $adjacent['next']['label'] ) . '</span></span>'
			. Render\icon( 'arrow-right', 16, 'rtl:-scale-x-100' ) . '</a>';
	}
	return $html . '</nav></div>';
}

/**
 * The footer: links, copyright, GitHub; then the drawer backdrop and the icon sprite.
 *
 * @return string
 */
function render_footer() {
	$link = 'text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground';
	return '<footer class="border-border border-t px-4 md:px-6" data-blume-footer>'
		. '<div class="flex min-h-16 flex-wrap items-center gap-x-6 gap-y-3 py-3 md:grid md:grid-cols-[1fr_auto_1fr]">'
		. '<ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">'
		. '<li><a class="' . $link . '" href="https://wordpress.studio" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank">WordPress Studio</a></li>'
		. '<li><a class="' . $link . '" href="https://wordpress.studio/download/" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank">Download</a></li>'
		. '</ul>'
		. '<p class="text-muted-foreground text-sm md:col-start-2">© ' . esc_html( gmdate( 'Y' ) ) . ' Automattic</p>'
		. '<ul class="-me-[9px] -my-[9px] ms-auto flex flex-wrap items-center gap-1 md:col-start-3">'
		. '<li><a aria-label="GitHub" class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:text-foreground" href="https://github.com/Automattic/studio" title="GitHub" aria-describedby="blume-new-tab-hint" rel="noreferrer" target="_blank">'
		. '<svg aria-hidden="true" fill="currentColor" height="18" viewBox="0 0 24 24" width="18"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"></path></svg>'
		. '</a></li></ul></div></footer>'
		. '<button aria-label="Close navigation" class="fixed inset-0 z-[30] hidden cursor-pointer border-0 bg-black/40 [:where([data-blume-nav-open])_&amp;]:block lg:hidden" data-blume-nav-toggle type="button"></button>'
		. Render\sprite();
}

/*
 * ---------------------------------------------------------------------------------------------
 * Content blocks.
 * ---------------------------------------------------------------------------------------------
 */

/**
 * A code block: Blume's frame with the language label, the copy button and the Shiki highlight
 * pre-rendered at build time (plain lines when the highlight is missing).
 *
 * @param array  $attributes Attributes.
 * @param string $content    Saved HTML (`<pre><code>…</code></pre>`).
 * @return string
 */
function render_code( $attributes, $content ) {
	$lang = strtolower( (string) ( $attributes['language'] ?? '' ) );
	if ( ! preg_match( '#<code[^>]*>(.*)</code>#s', (string) $content, $m ) ) {
		return $content;
	}
	$code = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$code = rtrim( $code, "\n" );

	$highlight = Render\highlight( $lang, $code );
	if ( $highlight ) {
		$style = (string) $highlight['style'];
		$inner = (string) $highlight['html'];
	} else {
		$style = '--shiki-light:#24292e;--shiki-dark:#e1e4e8;--shiki-light-bg:#fff;--shiki-dark-bg:#24292e';
		$lines = array_map( static fn( $line ) => '<span class="line"><span>' . esc_html( $line ) . '</span></span>', explode( "\n", $code ) );
		$inner = implode( "\n", $lines );
	}

	$attrs = ' class="astro-code astro-code-themes github-light github-dark group relative" style="' . esc_attr( $style . '; overflow-x: auto;' ) . '"';
	if ( '' !== $lang ) {
		$attrs .= ' data-language="' . esc_attr( Render\language_label( $lang ) ) . '" data-blume-language="' . esc_attr( $lang ) . '"';
		$icon   = Render\language_icon( $lang );
		if ( '' !== $icon ) {
			$attrs .= ' data-icon="' . esc_attr( $icon ) . '"';
		}
	}

	$button = '<button type="button" class="inline-flex size-[1.875rem] select-none items-center justify-center rounded-md text-muted-foreground bg-background transition-colors [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 hover:bg-muted hover:text-foreground absolute right-3 z-[2] top-2" data-blume-copy aria-label="Copy code">'
		. Render\inline_icon( 'check', 14, 'scale-0 text-green-600 transition-transform dark:text-green-500' )
		. Render\inline_icon( 'copy', 14, 'absolute transition-transform' )
		. '</button>';

	return '<pre' . $attrs . '><code tabindex="0">' . $inner . '</code>' . $button . '</pre>';
}

/**
 * An image: a plain `<img>` in a paragraph, as the markdown rendered it; the script adds the zoom.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Saved HTML.
 * @return string
 */
function render_image( $attributes, $content ) {
	if ( ! preg_match( '#<img\b([^>]*)/?>#i', (string) $content, $m ) ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( '<img' . $m[1] . '>' );
	$tags->next_tag( 'img' );
	$src = (string) $tags->get_attribute( 'src' );
	$alt = $tags->get_attribute( 'alt' );
	$id  = (int) ( $attributes['id'] ?? 0 );
	if ( $id ) {
		$url = wp_get_attachment_url( $id );
		if ( $url ) {
			$src = $url;
		}
	}
	$img = '<img src="' . esc_url( wp_make_link_relative( $src ) ) . '" alt="' . esc_attr( is_string( $alt ) ? $alt : '' ) . '">';
	if ( ! empty( $attributes['href'] ) ) {
		$img = '<a href="' . esc_url( $attributes['href'] ) . '">' . $img . '</a>';
	}
	return '<p>' . $img . '</p>';
}

/**
 * A table in a scrolling frame.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Saved HTML (`<figure><table>…</table></figure>`).
 * @return string
 */
function render_table( $attributes, $content ) {
	if ( ! preg_match( '#<table\b.*</table>#s', (string) $content, $m ) ) {
		return $content;
	}
	return '<div class="blume-table-scroll">' . $m[0] . '</div>';
}

/**
 * A callout (a GitHub alert in the markdown: note, tip, important, warning, caution).
 *
 * @param array  $attributes Attributes.
 * @param string $content    Saved HTML with the inner blocks rendered.
 * @return string
 */
function render_callout( $attributes, $content ) {
	$type   = sanitize_key( (string) ( $attributes['type'] ?? 'note' ) );
	$titles = array(
		'note'      => 'Note',
		'tip'       => 'Tip',
		'important' => 'Important',
		'warning'   => 'Warning',
		'caution'   => 'Caution',
	);
	$inner = preg_replace( '#^\s*<div[^>]*>|</div>\s*$#', '', (string) $content );
	return '<div class="studio-docs-callout is-' . esc_attr( $type ) . '" role="note"><p class="studio-docs-callout__title">' . esc_html( $titles[ $type ] ?? ucfirst( $type ) ) . '</p>' . $inner . '</div>';
}
