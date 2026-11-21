<?php
/**
 * Apply the source-controlled additions to the two search landing pages.
 *
 * Run inside WordPress with `studio wp eval-file scripts/import-search-pages.php`.
 * The rest of each page remains in WordPress. Refuse to overwrite an edit to
 * one of the managed fields or sections that was made since the last import.
 */

$search_pages = array(
	'compare' => array(
		'old_title'   => 'Studio vs Local (LocalWP)',
		'title'       => 'LocalWP Alternative for WordPress',
		'old_excerpt' => 'Studio and Local (LocalWP) are both free desktop apps for local WordPress development. See where they differ, side by side, and pick the one that fits.',
		'excerpt'     => 'Compare WordPress Studio and LocalWP for local WordPress development, from site setup and databases to AI agents, previews, and moving an existing site.',
		'old_heading' => 'Studio and Local, side by side.',
		'heading'     => 'A LocalWP alternative for local WordPress development.',
		'anchor'      => 'local-wordpress-development',
	),
	'agents'  => array(
		'old_title'   => 'WordPress MCP server and plugins for AI agents',
		'title'       => 'WordPress with Claude Code and AI Agents',
		'old_excerpt' => 'Give Claude Code, Codex, ChatGPT, Cursor or Grok Build real WordPress tools through plugins and an MCP server. Your agent builds, checks and ships your sites.',
		'excerpt'     => 'Use Claude Code, Codex, or another AI agent with local WordPress sites. Connect WordPress Studio to build, edit, preview, and publish with your agent.',
		'old_heading' => 'Bring the agent you already use.',
		'heading'     => 'Use Claude Code and AI agents with WordPress.',
		'anchor'      => 'using-ai-agents-with-wordpress',
	),
);

/** Stop before changing either page if a managed value was edited in WordPress. */
function studio_search_check_value( $slug, $field, $current, $original, $desired, $previous ) {
	$allowed = array( $original, $desired );
	if ( is_string( $previous ) ) {
		$allowed[] = $previous;
	}
	if ( ! in_array( $current, $allowed, true ) ) {
		WP_CLI::error( "/$slug/ has a different $field in WordPress. Reconcile that edit with scripts/import-search-pages.php before building." );
	}
}

/** Update the hero's H1 while retaining its block and HTML attributes. */
function studio_search_update_heading( &$block, $slug, $spec, $previous, &$count ) {
	if ( 'core/heading' === $block['blockName'] && 1 === (int) ( $block['attrs']['level'] ?? 2 ) ) {
		++$count;
		if ( ! preg_match( '/^(\s*<h1\b[^>]*>)(.*?)(<\/h1>\s*)$/s', $block['innerHTML'], $parts ) || ! empty( $block['innerBlocks'] ) ) {
			WP_CLI::error( "/$slug/ has an unsupported hero heading. Reconcile it before building." );
		}
		$current = html_entity_decode( wp_strip_all_tags( $parts[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		studio_search_check_value( $slug, 'H1', $current, $spec['old_heading'], $spec['heading'], $previous['heading'] ?? null );
		$html                  = $parts[1] . esc_html( $spec['heading'] ) . $parts[3];
		$block['innerHTML']    = $html;
		$block['innerContent'] = array( $html );
	}
	foreach ( $block['innerBlocks'] as &$child ) {
		studio_search_update_heading( $child, $slug, $spec, $previous, $count );
	}
	unset( $child );
}

/** Count an anchor even if an editor moved its block inside another block. */
function studio_search_count_anchor( $block, $anchor ) {
	$count = ( $block['attrs']['anchor'] ?? '' ) === $anchor ? 1 : 0;
	foreach ( $block['innerBlocks'] as $child ) {
		$count += studio_search_count_anchor( $child, $anchor );
	}
	return $count;
}

$updates = array();
foreach ( $search_pages as $slug => $spec ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page || 'publish' !== $page->post_status ) {
		WP_CLI::error( "/$slug/ is missing or unpublished." );
	}
	$previous = get_post_meta( $page->ID, '_studio_search_page_import', true );
	$previous = is_array( $previous ) ? $previous : array();
	studio_search_check_value( $slug, 'page title', $page->post_title, $spec['old_title'], $spec['title'], $previous['title'] ?? null );
	studio_search_check_value( $slug, 'excerpt', $page->post_excerpt, $spec['old_excerpt'], $spec['excerpt'], $previous['excerpt'] ?? null );

	$source = file_get_contents( dirname( __DIR__ ) . "/content/search/$slug.html" );
	if ( false === $source ) {
		WP_CLI::error( "Could not read the /$slug/ section source." );
	}
	$source_blocks = array_values( array_filter( parse_blocks( $source ), static function ( $block ) {
		return null !== $block['blockName'] || '' !== trim( $block['innerHTML'] );
	} ) );
	if ( 1 !== count( $source_blocks ) || 'core/group' !== $source_blocks[0]['blockName'] || $spec['anchor'] !== ( $source_blocks[0]['attrs']['anchor'] ?? '' ) ) {
		WP_CLI::error( "The /$slug/ section source must contain one group with the expected anchor." );
	}
	$section       = $source_blocks[0];
	$section_hash  = hash( 'sha256', serialize_block( $section ) );
	$blocks        = parse_blocks( $page->post_content );
	$hero_index    = null;
	$section_index = null;
	$anchor_count  = 0;
	foreach ( $blocks as $index => $block ) {
		$classes = preg_split( '/\s+/', $block['attrs']['className'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		if ( 'core/group' === $block['blockName'] && in_array( 'is-style-mosaic', $classes, true ) && in_array( 'is-sub', $classes, true ) ) {
			if ( null !== $hero_index ) {
				WP_CLI::error( "/$slug/ has more than one hero." );
			}
			$hero_index = $index;
		}
		if ( $spec['anchor'] === ( $block['attrs']['anchor'] ?? '' ) ) {
			$section_index = $index;
		}
		$anchor_count += studio_search_count_anchor( $block, $spec['anchor'] );
	}
	if ( null === $hero_index || $anchor_count > 1 || ( 1 === $anchor_count && null === $section_index ) ) {
		WP_CLI::error( "/$slug/ has an unexpected hero or section structure. Reconcile it before building." );
	}
	$heading_count = 0;
	studio_search_update_heading( $blocks[ $hero_index ], $slug, $spec, $previous, $heading_count );
	if ( 1 !== $heading_count ) {
		WP_CLI::error( "/$slug/ must have exactly one H1 in its hero." );
	}

	if ( null !== $section_index ) {
		$current_hash = hash( 'sha256', serialize_block( $blocks[ $section_index ] ) );
		if ( $current_hash !== $section_hash && $current_hash !== ( $previous['section_hash'] ?? '' ) ) {
			WP_CLI::error( "/$slug/ has an edited search section in WordPress. Reconcile it with content/search/$slug.html before building." );
		}
		$blocks[ $section_index ] = $section;
	} else {
		if ( isset( $previous['section_hash'] ) ) {
			WP_CLI::error( "/$slug/ no longer has its search section in WordPress. Reconcile that edit before building." );
		}
		$gap = array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => "\n\n", 'innerContent' => array( "\n\n" ) );
		array_splice( $blocks, $hero_index + 1, 0, array( $gap, $section ) );
	}
	$updates[] = array(
		'page'     => $page,
		'data'     => array( 'ID' => $page->ID, 'post_title' => $spec['title'], 'post_excerpt' => $spec['excerpt'], 'post_content' => serialize_blocks( $blocks ) ),
		'last'     => array( 'title' => $spec['title'], 'excerpt' => $spec['excerpt'], 'heading' => $spec['heading'], 'section_hash' => $section_hash ),
		'previous' => $previous,
	);
}

// Both pages passed the conflict checks. Keep their excerpts in WordPress so
// the theme's existing meta, Open Graph and Twitter tags all use the same text.
foreach ( $updates as $update ) {
	$page = $update['page'];
	$data = $update['data'];
	if ( $page->post_title !== $data['post_title'] || $page->post_excerpt !== $data['post_excerpt'] || $page->post_content !== $data['post_content'] ) {
		$result = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) || ! $result ) {
			WP_CLI::error( "Could not update /{$page->post_name}/." );
		}
	}
	if ( $update['previous'] !== $update['last'] ) {
		update_post_meta( $page->ID, '_studio_search_page_import', wp_slash( $update['last'] ) );
	}
	WP_CLI::log( "Updated /{$page->post_name}/ search content." );
}
