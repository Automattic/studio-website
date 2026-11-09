<?php
/**
 * The docs blocks, one folder each under blocks/ with its block.json, editor script, styles, front-end
 * script and render file:
 *
 * - content blocks the importer writes and authors can insert: code, callout (the rest of a page is
 *   core blocks: paragraph, heading, list, image, table, quote, …);
 * - template blocks for the docs layout, placed in the theme's docs templates next to core blocks:
 *   menu-toggle, search, theme-toggle (header), sidebar + navigation, breadcrumbs, toc, page-actions,
 *   pagination.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Blocks;

defined( 'WPINC' ) || die();

add_action( 'init', __NAMESPACE__ . '\\register' );
add_filter( 'block_categories_all', __NAMESPACE__ . '\\category' );
add_filter( 'render_block_core/post-excerpt', __NAMESPACE__ . '\\excerpt_only_when_written', 10, 2 );

/**
 * Register every block from its block.json.
 */
function register() {
	foreach ( glob( \WP_Docs\PLUGIN_DIR . '/blocks/*/block.json' ) as $file ) {
		register_block_type( dirname( $file ) );
	}
}

/**
 * A "Docs" category for the template blocks, after the core ones.
 *
 * @param array[] $categories Categories.
 * @return array[]
 */
function category( $categories ) {
	$categories[] = array(
		'slug'  => 'studio-docs',
		'title' => __( 'Docs', 'studio-docs' ),
	);
	return $categories;
}

/**
 * The Post Excerpt block shows a docs page's description (its written excerpt) as the lede; a page
 * without one gets nothing, not core's generated excerpt of the first words.
 *
 * @param string $content Rendered block.
 * @param array  $block   Block.
 * @return string
 */
function excerpt_only_when_written( $content, $block ) {
	$post = get_post();
	if ( $post instanceof \WP_Post && \WP_Docs\POST_TYPE === $post->post_type && ! has_excerpt( $post ) ) {
		return '';
	}
	return $content;
}
