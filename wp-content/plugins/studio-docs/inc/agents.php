<?php
/**
 * Endpoints for agents and for the search palette.
 *
 * - `/docs/<path>.md` (and `/docs/index.md` for the home), `?output_format=md` on a page URL, and
 *   `Accept: text/markdown` on a page URL: the page's `_wp_docs_markdown` as `text/markdown`.
 * - `<link rel="alternate" type="text/markdown">` in the head of every docs page.
 * - `/docs/llms.txt`, `/docs/llms-full.txt`, `/docs/index.json` and `/docs/search-index.json`.
 * - `GET /wp-json/wp-docs/v1/search-index`, the same records as the JSON file.
 *
 * Every generated document is cached until the next import (see cached()). The static export saves each
 * endpoint as a file, so the published site serves them without PHP; the head of every docs page links
 * to them so the export discovers them.
 *
 * @package WP_Docs
 */

namespace WP_Docs;

use WP;
use WP_REST_Response;

defined( 'WPINC' ) || die();

/**
 * Agent endpoints.
 */
class Agents {

	const QUERY_VAR_MD       = 'wp_docs_md';
	const QUERY_VAR_ENDPOINT = 'wp_docs_endpoint';

	/**
	 * Bump when a rule changes, so the rules are flushed once.
	 */
	const RULES_VERSION = '3';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'head_links' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'no_canonical_redirect' ), 10, 2 );
	}

	/**
	 * Rewrite rules, ahead of the post type's own.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^docs/llms\.txt$', 'index.php?' . self::QUERY_VAR_ENDPOINT . '=llms', 'top' );
		add_rewrite_rule( '^docs/llms-full\.txt$', 'index.php?' . self::QUERY_VAR_ENDPOINT . '=llms-full', 'top' );
		add_rewrite_rule( '^docs/index\.json$', 'index.php?' . self::QUERY_VAR_ENDPOINT . '=index-json', 'top' );
		add_rewrite_rule( '^docs/search-index\.json$', 'index.php?' . self::QUERY_VAR_ENDPOINT . '=search-index', 'top' );
		add_rewrite_rule( '^docs/(.+?)\.md$', 'index.php?' . self::QUERY_VAR_MD . '=$matches[1]', 'top' );

		// Flush once when the rules are new (activation also flushes).
		if ( get_option( 'wp_docs_rules_version' ) !== self::RULES_VERSION && get_option( 'permalink_structure' ) ) {
			update_option( 'wp_docs_rules_version', self::RULES_VERSION );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register the query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR_MD;
		$vars[] = self::QUERY_VAR_ENDPOINT;
		return $vars;
	}

	/**
	 * Serve the endpoints as soon as the request is parsed (no main query needed).
	 *
	 * @param WP $wp Request.
	 */
	public static function parse_request( $wp ) {
		if ( ! empty( $wp->query_vars[ self::QUERY_VAR_ENDPOINT ] ) ) {
			switch ( $wp->query_vars[ self::QUERY_VAR_ENDPOINT ] ) {
				case 'llms':
					self::send( 'text/plain; charset=utf-8', self::llms_txt() );
					break;
				case 'llms-full':
					self::send( 'text/plain; charset=utf-8', self::llms_full_txt() );
					break;
				case 'index-json':
					self::send( 'application/json; charset=utf-8', (string) wp_json_encode( self::index_json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					break;
				case 'search-index':
					self::send( 'application/json; charset=utf-8', (string) wp_json_encode( self::search_index(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					break;
			}
			return;
		}

		if ( isset( $wp->query_vars[ self::QUERY_VAR_MD ] ) ) {
			$path = trim( (string) $wp->query_vars[ self::QUERY_VAR_MD ], '/' );
			$id   = Permalinks::post_for_path( 'index' === $path ? '' : $path );
			if ( $id ) {
				self::send_markdown( $id );
			}
			// Unknown page: let WordPress answer with its 404.
			unset( $wp->query_vars[ self::QUERY_VAR_MD ] );
			$wp->query_vars['error'] = '404';
		}
	}

	/**
	 * `?output_format=md` and `Accept: text/markdown` on a page URL; `Vary: Accept` on every docs page.
	 */
	public static function template_redirect() {
		$id = self::current_page_id();
		if ( ! $id ) {
			return;
		}
		$format = isset( $_GET['output_format'] ) ? sanitize_key( wp_unslash( $_GET['output_format'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $format, array( 'md', 'markdown' ), true ) || self::accepts_markdown() ) {
			self::send_markdown( $id );
		}
		header( 'Vary: Accept', false );
	}

	/**
	 * The docs page the current request shows: the singular page, or the home on /docs/.
	 *
	 * @return int
	 */
	public static function current_page_id() {
		if ( is_singular( POST_TYPE ) ) {
			$id = (int) get_queried_object_id();
			return 'publish' === get_post_status( $id ) && '' === (string) get_post_field( 'post_password', $id ) ? $id : 0;
		}
		if ( is_post_type_archive( POST_TYPE ) ) {
			return Permalinks::post_for_path( '' );
		}
		return 0;
	}

	/**
	 * Whether the current request is a docs page (a single page or the archive, which shows the home).
	 *
	 * @return bool
	 */
	public static function is_docs_request() {
		return is_singular( POST_TYPE ) || is_post_type_archive( POST_TYPE );
	}

	/**
	 * Whether the request asks for Markdown in its Accept header (and prefers it to HTML).
	 *
	 * @return bool
	 */
	public static function accepts_markdown() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( false === strpos( $accept, 'text/markdown' ) ) {
			return false;
		}
		$q = static function ( $type ) use ( $accept ) {
			foreach ( explode( ',', $accept ) as $part ) {
				$bits = array_map( 'trim', explode( ';', $part ) );
				if ( $bits[0] === $type ) {
					foreach ( array_slice( $bits, 1 ) as $param ) {
						if ( 0 === strpos( $param, 'q=' ) ) {
							return (float) substr( $param, 2 );
						}
					}
					return 1.0;
				}
			}
			return -1.0;
		};
		return $q( 'text/markdown' ) >= $q( 'text/html' ) && $q( 'text/markdown' ) > 0;
	}

	/**
	 * Links in the head of a docs page: the Markdown copy, llms.txt, the JSON index and the search index.
	 * Root-relative, like every link on the static site; the export follows them and saves each file.
	 */
	public static function head_links() {
		$id = self::current_page_id();
		if ( ! $id ) {
			return;
		}
		printf( '<link rel="alternate" type="text/markdown" href="%s">' . "\n", esc_url( wp_make_link_relative( wp_docs_markdown_url( $id ) ) ) );
		echo '<link rel="describedby" type="text/plain" href="/docs/llms.txt">' . "\n";
		echo '<link rel="describedby" type="text/plain" href="/docs/llms-full.txt">' . "\n";
		echo '<link rel="alternate" type="application/json" href="/docs/index.json">' . "\n";
		echo '<link rel="preload" as="fetch" type="application/json" crossorigin href="/docs/search-index.json">' . "\n";
	}

	/**
	 * Keep WordPress from "correcting" the endpoint URLs.
	 *
	 * @param string|false $redirect  Redirect URL.
	 * @param string       $requested Requested URL.
	 * @return string|false
	 */
	public static function no_canonical_redirect( $redirect, $requested ) {
		$path = (string) wp_parse_url( $requested, PHP_URL_PATH );
		if ( preg_match( '#/docs/(llms(-full)?\.txt|index\.json|search-index\.json|.+\.md)$#', $path ) ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * Send the Markdown of a page and stop.
	 *
	 * @param int $id Post ID.
	 */
	public static function send_markdown( $id ) {
		$markdown = (string) get_post_meta( $id, '_wp_docs_markdown', true );
		if ( '' === $markdown ) {
			$markdown = '# ' . get_post_field( 'post_title', $id, 'raw' ) . "\n\n" . wp_strip_all_tags( (string) get_post_field( 'post_content', $id, 'raw' ) ) . "\n";
		}
		header( 'Link: <' . esc_url_raw( public_url( (string) get_permalink( $id ) ) ) . '>; rel="canonical"' );
		self::send( 'text/markdown; charset=utf-8', $markdown );
	}

	/**
	 * Send a response body and stop.
	 *
	 * @param string $type Content type.
	 * @param string $body Body.
	 */
	protected static function send( $type, $body ) {
		status_header( 200 );
		header( 'Content-Type: ' . $type );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=300' );
		header( 'Vary: Accept', false );
		header( 'Access-Control-Allow-Origin: *' );
		if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text, Markdown or JSON.
		}
		exit;
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Documents.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Pages in sidebar order with the home first.
	 *
	 * @return array[] Tree nodes.
	 */
	public static function pages_in_order() {
		$tree  = wp_docs_get_tree();
		$pages = array();
		if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
			$pages[] = $tree['nodes'][ $tree['home'] ];
		}
		foreach ( $tree['order'] as $id ) {
			$pages[] = $tree['nodes'][ $id ];
		}
		return $pages;
	}

	/**
	 * Plain text for a Markdown link label or description.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * The site name and summary used by llms.txt.
	 *
	 * @return array{name: string, summary: string}
	 */
	public static function site() {
		return array(
			/**
			 * Filters the docs title in llms.txt.
			 *
			 * @param string $name Default 'WordPress Studio Docs'.
			 */
			'name'    => (string) apply_filters( 'wp_docs_llms_name', 'WordPress Studio Docs' ),
			/**
			 * Filters the summary paragraph of llms.txt.
			 *
			 * @param string $summary Summary.
			 */
			'summary' => (string) apply_filters( 'wp_docs_llms_summary', 'How to use WordPress Studio: local sites, Studio Code, the CLI, syncing and previews.' ),
		);
	}

	/**
	 * `/docs/llms.txt`: the home, then one section per sidebar group with a sub-section per page that
	 * has children, every page linked at its public URL with its description.
	 *
	 * @return string
	 */
	public static function llms_txt() {
		return cached(
			'llms',
			static function () {
				$tree  = wp_docs_get_tree();
				$site  = self::site();
				$lines = array( '# ' . $site['name'], '', '> ' . $site['summary'], '' );

				$line = static function ( array $node ) {
					$label       = str_replace( array( '[', ']' ), array( '\[', '\]' ), self::plain( $node['title'] ) );
					$description = self::plain( $node['description'] );
					return '- [' . $label . '](' . public_url( $node['url'] ) . ')' . ( '' !== $description ? ': ' . $description : '' );
				};

				if ( $tree['home'] && isset( $tree['nodes'][ $tree['home'] ] ) ) {
					$lines[] = '## Docs';
					$lines[] = '';
					$lines[] = $line( $tree['nodes'][ $tree['home'] ] );
				}

				$group = null;
				foreach ( $tree['order'] as $id ) {
					$node = $tree['nodes'][ $id ];
					if ( 0 === $node['depth'] && $node['group'] !== $group ) {
						$group   = $node['group'];
						$lines[] = '';
						$lines[] = '## ' . self::plain( $node['group_title'] );
						$lines[] = '';
					}
					if ( ! empty( $tree['children'][ $id ] ) ) {
						$lines[] = '';
						$lines[] = str_repeat( '#', $node['depth'] + 3 ) . ' ' . self::plain( $node['heading'] );
						$lines[] = '';
					}
					$lines[] = $line( $node );
				}

				$lines[] = '';
				$lines[] = '## Agent resources';
				$lines[] = '';
				$lines[] = '- [llms-full.txt](' . public_url( '/docs/llms-full.txt' ) . '): The full Markdown of every page in one file.';
				$lines[] = '- [Page Markdown](' . public_url( '/docs/index.md' ) . '): Append `.md` to any page URL to fetch that page as raw Markdown.';
				$lines[] = '- [Page index](' . public_url( '/docs/index.json' ) . '): Every page with its title, description and URLs, as JSON.';

				return implode( "\n", $lines ) . "\n";
			}
		);
	}

	/**
	 * `/docs/llms-full.txt`: every page's Markdown in sidebar order, each under its title with its source URL.
	 *
	 * @return string
	 */
	public static function llms_full_txt() {
		return cached(
			'llms_full',
			static function () {
				$site  = self::site();
				$parts = array( '# ' . $site['name'] . "\n\n> " . $site['summary'] );
				foreach ( self::pages_in_order() as $node ) {
					$markdown = (string) get_post_meta( $node['id'], '_wp_docs_markdown', true );
					list( , $body ) = Transforms\Front_Matter\split( $markdown );
					$body     = trim( $body );
					$parts[]  = '# ' . self::plain( $node['title'] ) . "\nSource: " . public_url( $node['url'] ) . "\n\n" . $body;
				}
				return implode( "\n\n", $parts ) . "\n";
			}
		);
	}

	/**
	 * `/docs/index.json`.
	 *
	 * @return array
	 */
	public static function index_json() {
		return cached(
			'index_json',
			static function () {
				$tree  = wp_docs_get_tree();
				$pages = array();
				foreach ( self::pages_in_order() as $node ) {
					$meta    = wp_docs_get_page_meta( $node['id'] );
					$parent  = $node['parent'] && isset( $tree['nodes'][ $node['parent'] ] ) ? $tree['nodes'][ $node['parent'] ]['doc_id'] : null;
					$pages[] = array(
						'id'          => $node['doc_id'],
						'title'       => self::plain( $node['title'] ),
						'label'       => self::plain( $node['label'] ),
						'path'        => $node['path'],
						'url'         => public_url( $node['url'] ),
						'md_url'      => public_url( $node['md_url'] ),
						'group'       => $node['group'],
						'group_title' => $node['group_title'],
						'description' => $node['description'],
						'parent'      => $parent,
						'order'       => $node['order'],
						'source_url'  => $meta['source_url'] ?? '',
						'imported'    => $meta['imported'] ?? '',
					);
				}
				$stamp = import_stamp();
				$last  = get_option( LAST_IMPORT_OPTION );
				return array(
					'generated' => '' !== $stamp ? $stamp : gmdate( 'c' ),
					'source'    => is_array( $last ) && ! empty( $last['source'] ) ? $last['source'] : null,
					'pages'     => $pages,
				);
			}
		);
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Search index.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Register `GET /wp-json/wp-docs/v1/search-index`.
	 */
	public static function register_routes() {
		register_rest_route(
			'wp-docs/v1',
			'/search-index',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'serve_search_index' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serve the search index, cacheable for five minutes.
	 *
	 * @return WP_REST_Response
	 */
	public static function serve_search_index() {
		$response = new WP_REST_Response( self::search_index() );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * The text of parsed blocks: their HTML, plus the code, table and image attributes of the docs blocks.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return string
	 */
	public static function blocks_text( array $blocks ) {
		$text = '';
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( 'core/image' === $name ) {
				// The alt text, which the tag stripping would drop.
				$tags = new \WP_HTML_Tag_Processor( (string) $block['innerHTML'] );
				if ( $tags->next_tag( 'img' ) ) {
					$text .= ' ' . (string) $tags->get_attribute( 'alt' ) . ' ';
				}
			} else {
				$text .= ' ' . preg_replace( '#</(p|li|h[1-6]|td|th|tr|pre|code|blockquote|div)>#i', ' ', (string) $block['innerHTML'] ) . ' ';
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$text .= self::blocks_text( $block['innerBlocks'] );
			}
		}
		return $text;
	}

	/**
	 * The search index: one record per page, the home first, in sidebar order, in the shape the Blume
	 * search used: title, route (under /docs), url, section, breadcrumb (group title, then the headings
	 * of the ancestors), description, headings (text and anchor of H2 and H3), content (the plain text).
	 *
	 * @return array[]
	 */
	public static function search_index() {
		return cached(
			'search_index',
			static function () {
				$tree  = wp_docs_get_tree();
				$index = array();
				foreach ( self::pages_in_order() as $node ) {
					$id     = $node['id'];
					$crumbs = array();
					$parent = $node['parent'];
					while ( $parent && isset( $tree['nodes'][ $parent ] ) ) {
						array_unshift( $crumbs, self::plain( $tree['nodes'][ $parent ]['heading'] ) );
						$parent = $tree['nodes'][ $parent ]['parent'];
					}
					if ( '' !== $node['group_title'] ) {
						array_unshift( $crumbs, self::plain( $node['group_title'] ) );
					}
					$content  = (string) get_post_field( 'post_content', $id, 'raw' );
					$headings = array();
					foreach ( Transforms\Anchors\headings( $content ) as $heading ) {
						$headings[] = array(
							'text'   => $heading['text'],
							'anchor' => $heading['anchor'],
						);
					}
					$text = self::plain( self::blocks_text( parse_blocks( $content ) ) );

					$index[] = array(
						'title'       => self::plain( $node['title'] ),
						'route'       => '/' . trim( $node['path'], '/' ),
						'url'         => wp_make_link_relative( $node['url'] ),
						'section'     => $crumbs ? end( $crumbs ) : '',
						'breadcrumb'  => $crumbs,
						'description' => self::plain( $node['description'] ),
						'headings'    => $headings,
						'content'     => $text,
					);
				}
				return $index;
			}
		);
	}
}
