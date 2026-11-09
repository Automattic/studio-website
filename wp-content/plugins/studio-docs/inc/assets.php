<?php
/**
 * What a docs page loads on top of the theme and core: the docs stylesheets (tokens, layout, article
 * typography), the shared script (clipboard helpers, image zoom), the color scheme set before the
 * first paint, the favicons, and the document title in the docs' form. The block editor gets the
 * same stylesheets while a docs page or a docs template is edited.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Assets;

use WP_Docs\Agents;
use WP_Docs\Render;

defined( 'WPINC' ) || die();

add_action( 'init', __NAMESPACE__ . '\\register' );
add_action( 'wp', __NAMESPACE__ . '\\setup' );
add_filter( 'block_editor_settings_all', __NAMESPACE__ . '\\editor_styles', 10, 2 );

/**
 * Register the shared assets; the block view scripts depend on the shared script.
 */
function register() {
	$version = static fn( $file ) => (string) filemtime( \WP_Docs\PLUGIN_DIR . '/assets/' . $file );

	wp_register_style( 'studio-docs-tokens', Render\asset( 'css/tokens.css' ), array(), $version( 'css/tokens.css' ) );
	wp_register_style( 'studio-docs', Render\asset( 'css/docs.css' ), array( 'studio-docs-tokens', 'wp-block-library', 'global-styles' ), $version( 'css/docs.css' ) );
	wp_register_style( 'studio-docs-prose', Render\asset( 'css/prose.css' ), array( 'studio-docs' ), $version( 'css/prose.css' ) );
	wp_register_script( 'studio-docs-medium-zoom', Render\asset( 'vendor/medium-zoom.js' ), array(), $version( 'vendor/medium-zoom.js' ), array( 'strategy' => 'defer' ) );
	wp_register_script( 'studio-docs', Render\asset( 'js/docs.js' ), array( 'studio-docs-medium-zoom' ), $version( 'js/docs.js' ), array( 'strategy' => 'defer' ) );
}

/**
 * Hook the docs page treatment once the query is known.
 */
function setup() {
	if ( ! Agents::is_docs_request() ) {
		return;
	}
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue' );
	add_action( 'wp_head', __NAMESPACE__ . '\\theme_script', 0 );
	add_action( 'wp_head', __NAMESPACE__ . '\\head', 2 );
	add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\\title_parts' );
	add_filter( 'document_title_separator', static fn() => '-' );
	add_filter( 'document_title', static fn( $title ) => str_replace( ' &#8211; ', ' - ', $title ) );
	add_filter( 'get_site_icon_url', '__return_empty_string' );
}

/**
 * The docs stylesheets and script.
 */
function enqueue() {
	wp_enqueue_style( 'studio-docs' );
	wp_enqueue_style( 'studio-docs-prose' );
	wp_enqueue_script( 'studio-docs' );
	wp_dequeue_script( 'studio-script' ); // The theme's script drives the home page's hero; nothing of it is on a docs page.
}

/**
 * The docs stylesheets in the block editor: everywhere the docs templates can be edited (the Site
 * Editor), and in the post editor of a docs page, where the article typography applies to the content
 * root, since the template's article wrapper is not there.
 *
 * @param array                   $settings Editor settings.
 * @param \WP_Block_Editor_Context $context  Context.
 * @return array
 */
function editor_styles( $settings, $context ) {
	$is_doc = ! empty( $context->post ) && \WP_Docs\POST_TYPE === $context->post->post_type;
	$site   = 'core/edit-site' === ( $context->name ?? '' );
	if ( ! $is_doc && ! $site ) {
		return $settings;
	}
	$read  = static fn( $file ) => (string) file_get_contents( \WP_Docs\PLUGIN_DIR . '/assets/css/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$docs  = $read( 'docs.css' );
	$prose = $read( 'prose.css' );

	// `.studio-docs` is the body class of a docs page: in the editor, the canvas root. Only the descendant
	// selectors are mapped; the body rule itself (`body.studio-docs`: Inter, the docs colors) applies to
	// the canvas of a docs page alone, so the Site Editor previews the other templates as they are.
	$body                 = preg_match( '/body\.studio-docs \{[^}]*\}/', $docs, $m ) ? $m[0] : '';
	$frame                = str_replace( $body, '', $docs ); // The body rule stays out of the mapping below.
	$settings['styles'][] = array( 'css' => str_replace( '.studio-docs ', '.editor-styles-wrapper ', $frame ) );
	$settings['styles'][] = array( 'css' => $prose );
	if ( $is_doc ) {
		$settings['styles'][] = array( 'css' => str_replace( 'body.studio-docs', '.editor-styles-wrapper', $body ) );
		$settings['styles'][] = array( 'css' => str_replace( '.studio-docs-article', '.editor-styles-wrapper', $prose ) );
	}
	return $settings;
}

/**
 * Set `data-theme` on `<html>` before the first paint: the saved choice, else the system scheme.
 * Printing uses the light scheme.
 */
function theme_script() {
	echo '<script>(()=>{const r=document.documentElement;let s=null;try{s=localStorage.getItem("studio-docs-theme")||localStorage.getItem("blume-theme")}catch{}const sys=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";r.dataset.theme=s==="dark"||s==="light"?s:sys;let printed=null;addEventListener("beforeprint",()=>{if(r.dataset.theme==="dark"){printed="dark";r.dataset.theme="light"}});addEventListener("afterprint",()=>{if(printed){r.dataset.theme=printed;printed=null}})})();</script>' . "\n";
}

/**
 * Favicons (the light and dark app icon) and the font preload.
 */
function head() {
	$images = Render\asset( 'images/' );
	echo '<link href="' . esc_url( $images . 'favicon.svg' ) . '" rel="icon" type="image/svg+xml">' . "\n";
	echo '<link href="' . esc_url( $images . 'favicon-dark.svg' ) . '" media="(prefers-color-scheme: dark)" rel="icon" type="image/svg+xml">' . "\n";
	echo '<link href="' . esc_url( $images . 'favicon.svg' ) . '" media="(prefers-color-scheme: light)" rel="icon" type="image/svg+xml">' . "\n";
	echo '<link href="' . esc_url( $images . 'apple-icon.png' ) . '" rel="apple-touch-icon">' . "\n";
	echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( 'assets/fonts/inter/inter-400-700-latin.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}

/**
 * The docs body class, which the docs stylesheet is scoped to.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function body_class( $classes ) {
	$classes[] = 'studio-docs';
	return $classes;
}

/**
 * "<Page> - WordPress Studio Docs".
 *
 * @param array $parts Title parts.
 * @return array
 */
function title_parts( $parts ) {
	$doc = Render\current_doc();
	if ( $doc ) {
		$parts['title'] = html_entity_decode( get_the_title( $doc ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	$parts['site'] = 'WordPress Studio Docs';
	unset( $parts['tagline'], $parts['page'] );
	return $parts;
}
