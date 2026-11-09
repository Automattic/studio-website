<?php
/**
 * Plain-PHP test bootstrap: no database, no WordPress runtime.
 *
 * Loads a handful of pure WordPress core files (formatting for wpautop and esc_url, kses for the URL
 * protocol helpers, the HTML API, the block parser) and the vendored Jetpack Markdown library, with
 * stubs for the few runtime functions they call. Then loads the plugin's pure modules.
 *
 * @package WP_Docs
 */

// phpcs:disable

// WordPress core: WP_DOCS_TEST_WORDPRESS (any WordPress install, a Studio site for example), else
// tests/.deps/wordpress (run tests/fetch-deps.sh once). The Markdown parser is vendored with the plugin.
$wordpress = getenv( 'WP_DOCS_TEST_WORDPRESS' ) ?: ( getenv( 'WP_DOCS_TEST_DEPS' ) ?: __DIR__ . '/.deps' ) . '/wordpress';
if ( ! is_dir( "$wordpress/wp-includes" ) ) {
	fwrite( STDERR, "Missing WordPress core for the tests. Run tests/fetch-deps.sh, or set WP_DOCS_TEST_WORDPRESS to a WordPress install.\n" );
	exit( 2 );
}

define( 'ABSPATH', rtrim( $wordpress, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );

$GLOBALS['wp_docs_test_filters'] = array();

function apply_filters( $hook, $value, ...$args ) {
	if ( isset( $GLOBALS['wp_docs_test_filters'][ $hook ] ) ) {
		return ( $GLOBALS['wp_docs_test_filters'][ $hook ] )( $value, ...$args );
	}
	return $value;
}
function do_action( ...$args ) {}
function has_filter( ...$args ) { return false; }
function __( $s ) { return $s; }
function _x( $s ) { return $s; }
function esc_attr__( $s ) { return esc_attr( $s ); }
function esc_html__( $s ) { return esc_html( $s ); }
function _doing_it_wrong( $function, $message ) { fwrite( STDERR, "_doing_it_wrong: $function: $message\n" ); }
function get_option( $name, $default = false ) { return 'blog_charset' === $name ? 'UTF-8' : $default; }
function is_utf8_charset( $charset = null ) { return true; }
function wp_allowed_protocols() {
	return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
}

$inc = ABSPATH . WPINC;
require_once "$inc/compat-utf8.php";
require_once "$inc/utf8.php";
require_once "$inc/formatting.php";
require_once "$inc/kses.php";
require_once "$inc/class-wp-token-map.php";
foreach ( array( 'html5-named-character-references', 'class-wp-html-attribute-token', 'class-wp-html-span', 'class-wp-html-doctype-info', 'class-wp-html-text-replacement', 'class-wp-html-decoder', 'class-wp-html-tag-processor', 'class-wp-html-unsupported-exception', 'class-wp-html-active-formatting-elements', 'class-wp-html-open-elements', 'class-wp-html-token', 'class-wp-html-stack-event', 'class-wp-html-processor-state', 'class-wp-html-processor' ) as $file ) {
	require_once "$inc/html-api/$file.php";
}
require_once "$inc/class-wp-block-parser-block.php";
require_once "$inc/class-wp-block-parser-frame.php";
require_once "$inc/class-wp-block-parser.php";
$plugin = dirname( __DIR__ );
require_once "$plugin/vendor/jetpack-markdown/markdown/extra.php";
require_once "$plugin/vendor/jetpack-markdown/markdown/gfm.php";

require_once "$plugin/inc/sources.php";
require_once "$plugin/inc/manifest.php";
require_once "$plugin/inc/transforms/front-matter.php";
require_once "$plugin/inc/transforms/alerts.php";
require_once "$plugin/inc/transforms/anchors.php";
require_once "$plugin/inc/transforms/agent-markdown.php";
require_once "$plugin/inc/transforms/links.php";
require_once "$plugin/inc/transforms/images.php";
require_once "$plugin/inc/transforms/blocks.php";
require_once "$plugin/inc/transforms/chunks.php";
require_once "$plugin/inc/transforms/markdown-out.php";
require_once "$plugin/inc/transforms/reverse.php";
require_once "$plugin/inc/transforms/upstream.php";
require_once "$plugin/inc/pipeline.php";
require_once "$plugin/inc/drift.php";
