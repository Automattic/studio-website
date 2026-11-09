<?php
/**
 * Content rendering on docs pages: headings wrap their text in an anchor link (a `#` appears on
 * hover), and links to other sites open in a new tab with an external-link icon.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Content;

defined( 'WPINC' ) || die();

add_filter( 'render_block', __NAMESPACE__ . '\\external_links', 101, 2 );
add_filter( 'render_block_core/heading', __NAMESPACE__ . '\\heading_anchor', 110, 2 );
add_action( 'wp_footer', __NAMESPACE__ . '\\new_tab_hint' );

/**
 * Whether a block is being rendered for a docs page.
 *
 * @return bool
 */
function in_doc() {
	$post = get_post();
	return $post instanceof \WP_Post && \WP_Docs\POST_TYPE === $post->post_type;
}

/**
 * Links to other sites open in a new tab and get the external-link icon: every absolute http(s) link,
 * in the content and in the template parts around it.
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
		if ( ! is_string( $href ) || ! preg_match( '#^https?://#i', $href ) || null !== $tags->get_attribute( 'data-docs-external' ) ) {
			continue;
		}
		$tags->set_attribute( 'target', '_blank' );
		$tags->set_attribute( 'rel', 'noreferrer' );
		$tags->set_attribute( 'aria-describedby', 'studio-docs-new-tab-hint' );
		$tags->set_attribute( 'data-docs-external', '' );
	}
	return $tags->get_updated_html();
}

/**
 * The hidden text the external links are described by.
 */
function new_tab_hint() {
	if ( ! \WP_Docs\Agents::is_docs_request() ) {
		return;
	}
	echo '<span hidden id="studio-docs-new-tab-hint">' . esc_html__( 'Opens in a new tab', 'studio-docs' ) . '</span>' . "\n";
}

/**
 * Wrap the heading text in a link to the heading.
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
		static function ( $m ) {
			if ( false !== stripos( $m[5], '<a ' ) ) {
				return $m[0]; // A heading that is (or holds) a link keeps it; anchors do not nest.
			}
			return '<h' . $m[1] . $m[2] . ' id="' . $m[3] . '"' . $m[4] . '><a class="studio-docs-heading-anchor" href="#' . $m[3] . '">' . $m[5] . '</a></h' . $m[1] . '>';
		},
		$content,
		1
	);
}
