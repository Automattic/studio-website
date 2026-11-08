<?php
/**
 * Content rendering on docs pages: the core blocks the importer still writes (paragraphs, headings,
 * lists, quotes, separators, preformatted text, details) come out as the plain HTML Blume rendered
 * from the markdown: no `wp-block-*` classes, and headings wrap their text in an anchor link.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Content;



defined( 'WPINC' ) || die();

// Late, after core's own render filters have added their classes (the paragraph gets its class in a
// block-specific filter, which runs after the generic one).
add_filter( 'render_block', __NAMESPACE__ . '\plain_markup', 100, 2 );
add_filter( 'render_block_core/paragraph', __NAMESPACE__ . '\plain_markup', 100, 2 );
add_filter( 'render_block', __NAMESPACE__ . '\external_links', 101, 2 );
add_filter( 'render_block_core/heading', __NAMESPACE__ . '\heading_anchor', 110, 2 );

/**
 * Whether a block is being rendered inside a docs page.
 *
 * @return bool
 */
function in_doc() {
	$post = get_post();
	return $post instanceof \WP_Post && \WP_Docs\POST_TYPE === $post->post_type;
}

/**
 * Strip the `wp-block-*` classes core adds to its wrapper tag (and an empty class attribute).
 *
 * @param string $content Rendered block.
 * @param array  $block   Block.
 * @return string
 */
function plain_markup( $content, $block ) {
	$name = (string) ( $block['blockName'] ?? '' );
	if ( 0 !== strpos( $name, 'core/' ) || ! in_doc() ) {
		return $content;
	}
	return (string) preg_replace_callback(
		'#^(\s*<[a-z0-9]+)([^>]*)>#i',
		static function ( $m ) {
			$attrs = preg_replace_callback(
				'/\sclass="([^"]*)"/',
				static function ( $c ) {
					$classes = array_filter(
						explode( ' ', $c[1] ),
						static fn( $class ) => '' !== $class && 0 !== strpos( $class, 'wp-block-' ) && 0 !== strpos( $class, 'has-' ) && 'wp-element-caption' !== $class
					);
					return $classes ? ' class="' . implode( ' ', $classes ) . '"' : '';
				},
				$m[2]
			);
			return $m[1] . $attrs . '>';
		},
		$content,
		1
	);
}

/**
 * Links to other sites open in a new tab and get the external-link icon (Blume's `markdown.externalLinks`):
 * every absolute http(s) link, including the ones to wordpress.studio itself, as Blume did.
 *
 * @param string $content Rendered block.
 * @param array  $block   Block.
 * @return string
 */
function external_links( $content, $block ) {
	if ( ! in_doc() || false === stripos( $content, '<a ' ) ) {
		return $content;
	}
	$tags = new \WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag( 'a' ) ) {
		$href = $tags->get_attribute( 'href' );
		if ( ! is_string( $href ) || ! preg_match( '#^https?://#i', $href ) || null !== $tags->get_attribute( 'data-blume-external' ) ) {
			continue;
		}
		$tags->set_attribute( 'target', '_blank' );
		$tags->set_attribute( 'rel', 'noreferrer' );
		$tags->set_attribute( 'aria-describedby', 'blume-new-tab-hint' );
		$tags->set_attribute( 'data-blume-external', '' );
	}
	return $tags->get_updated_html();
}

/**
 * Wrap the heading text in a link to the heading, which shows a `#` on hover (Blume's heading anchors).
 *
 * @param string $content Rendered heading.
 * @param array  $block   Block.
 * @return string
 */
function heading_anchor( $content, $block ) {
	if ( ! in_doc() ) {
		return $content;
	}
	return (string) preg_replace_callback(
		'#<h([2-6])([^>]*)\sid="([^"]+)"([^>]*)>(.*?)</h\1>#is',
		static fn( $m ) => '<h' . $m[1] . $m[2] . ' id="' . $m[3] . '"' . $m[4] . '><a class="blume-heading-anchor" href="#' . $m[3] . '">' . $m[5] . '</a></h' . $m[1] . '>',
		$content,
		1
	);
}
