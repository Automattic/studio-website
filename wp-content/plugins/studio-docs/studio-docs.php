<?php
/**
 * Plugin Name: Studio Docs
 * Description: The documentation section of wordpress.studio: imports the Studio docs from the markdown in Automattic/studio into the `docs` post type (a subclass of the WordPress.org Markdown importer), serves every page as Markdown with llms.txt, a JSON index and a search index, and renders the docs layout with its blocks.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Automattic
 * License: GPLv2 or later
 * Text Domain: studio-docs
 * Update URI: false
 *
 * @package WP_Docs
 */

namespace WP_Docs;

defined( 'WPINC' ) || die();

const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;
const VERSION     = '1.0.0';

// Pure modules (no WordPress dependency; tested with plain PHP in tests/).
require_once __DIR__ . '/inc/sources.php';
require_once __DIR__ . '/inc/manifest.php';
require_once __DIR__ . '/inc/transforms/front-matter.php';
require_once __DIR__ . '/inc/transforms/alerts.php';
require_once __DIR__ . '/inc/transforms/anchors.php';
require_once __DIR__ . '/inc/transforms/links.php';
require_once __DIR__ . '/inc/transforms/images.php';
require_once __DIR__ . '/inc/transforms/blocks.php';
require_once __DIR__ . '/inc/transforms/agent-markdown.php';
require_once __DIR__ . '/inc/transforms/chunks.php';
require_once __DIR__ . '/inc/transforms/markdown-out.php';
require_once __DIR__ . '/inc/transforms/reverse.php';
require_once __DIR__ . '/inc/transforms/upstream.php';
require_once __DIR__ . '/inc/pipeline.php';
require_once __DIR__ . '/inc/drift.php';

// WordPress integration.
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/post-type.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/local-sources.php';
require_once __DIR__ . '/inc/permalinks.php';
require_once __DIR__ . '/inc/agents.php';
require_once __DIR__ . '/inc/render.php';
require_once __DIR__ . '/inc/blocks.php';
require_once __DIR__ . '/inc/content.php';
require_once __DIR__ . '/inc/assets.php';

Local_Sources::init();
Permalinks::init();
Agents::init();

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Register the post type and the endpoint rules, then flush, so /docs/ and the page URLs resolve at once.
 */
function activate() {
	register_post_type();
	Agents::add_rewrite_rules();
	flush_rewrite_rules();
}

/**
 * Stop the recurring import.
 */
function deactivate() {
	wp_clear_scheduled_hook( 'wp_docs_import' );
	delete_option( 'wp_docs_rules_version' );
	flush_rewrite_rules();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_importer' );

/**
 * Load the vendored WordPress.org importer, the importer subclass and the WP-CLI command.
 */
function load_importer() {
	if ( ! class_exists( '\WordPressdotorg\Markdown\Importer' ) ) {
		require_once __DIR__ . '/vendor/wporg-markdown/class-importer.php';
	}
	require_once __DIR__ . '/inc/class-importer.php';
	require_once __DIR__ . '/inc/class-drift.php';

	Importer::instance()->init();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/inc/cli.php';
		\WP_CLI::add_command( 'wp-docs', CLI::class );
	}
}

/**
 * Load the vendored Jetpack Markdown parser (Jetpack 16.3.1, GPL) when the importer needs it.
 */
function load_markdown_parser() {
	if ( class_exists( 'WPCom_GHF_Markdown_Parser' ) ) {
		return;
	}
	if ( ! class_exists( 'MarkdownExtra_Parser' ) ) {
		require_once __DIR__ . '/vendor/jetpack-markdown/markdown/extra.php';
	}
	require_once __DIR__ . '/vendor/jetpack-markdown/markdown/gfm.php';
}
