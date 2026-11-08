<?php
/**
 * What a docs page loads: Blume's stylesheet (pruned) and the docs script instead of the theme's
 * assets and core's block styles, the color scheme set before the first paint, the Inter font, the
 * favicon pair, and the document title in Blume's form.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Assets;

use WP_Docs\Agents;
use WP_Docs\Render;

defined( 'WPINC' ) || die();

add_action( 'wp', __NAMESPACE__ . '\setup' );

/**
 * Hook the docs page treatment once the query is known.
 */
function setup() {
	if ( ! Agents::is_docs_request() ) {
		return;
	}
	add_filter( 'should_load_separate_core_block_assets', '__return_false' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue', 100 );
	add_action( 'wp_head', __NAMESPACE__ . '\theme_script', 0 );
	add_action( 'wp_head', __NAMESPACE__ . '\head', 2 );
	add_filter( 'body_class', __NAMESPACE__ . '\body_class' );
	add_filter( 'language_attributes', __NAMESPACE__ . '\language_attributes' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\title_parts' );
	add_filter( 'document_title_separator', static fn() => '-' );
	add_filter( 'document_title', static fn( $title ) => str_replace( ' &#8211; ', ' - ', $title ) );
	add_filter( 'get_site_icon_url', '__return_empty_string' );

	// Head tags the static docs pages have no use for.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_resource_hints', 2 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
	remove_action( 'wp_footer', 'the_block_template_skip_link' );
	remove_action( 'wp_head', 'wp_print_font_faces', 50 );
	remove_action( 'wp_head', 'wp_print_font_faces_from_style_variations', 50 );
}

/**
 * Swap the theme's assets and core's block styles for the docs stylesheet and script.
 */
function enqueue() {
	foreach ( array( 'studio-style', 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'core-block-supports', 'classic-theme-styles', 'wp-emoji-styles' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
	wp_dequeue_script( 'studio-script' );

	$version = static fn( $file ) => (string) filemtime( \WP_Docs\PLUGIN_DIR . '/assets/' . $file );

	wp_enqueue_style( 'studio-docs', Render\asset( 'css/docs.css' ), array(), $version( 'css/docs.css' ) );
	wp_add_inline_style( 'studio-docs', font_faces() );

	wp_enqueue_script( 'studio-docs-medium-zoom', Render\asset( 'vendor/medium-zoom.js' ), array(), $version( 'vendor/medium-zoom.js' ), array( 'strategy' => 'defer' ) );
	wp_enqueue_script( 'studio-docs', Render\asset( 'js/docs.js' ), array( 'studio-docs-medium-zoom' ), $version( 'js/docs.js' ), array( 'strategy' => 'defer' ) );
}

/**
 * The Inter faces (the files ship with the theme; the pruned Blume stylesheet reads --blume-ff-inter).
 *
 * @return string
 */
function font_faces() {
	$dir   = get_theme_file_uri( 'assets/fonts/inter/' );
	$range = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
	return '@font-face{font-family:Inter;src:url("' . esc_url( $dir . 'inter-400-700-latin.woff2' ) . '") format("woff2");font-display:swap;unicode-range:' . $range . ';font-weight:400 700;font-style:normal}'
		. '@font-face{font-family:Inter;src:url("' . esc_url( $dir . 'inter-400-700-latin-italic.woff2' ) . '") format("woff2");font-display:swap;unicode-range:' . $range . ';font-weight:400 700;font-style:italic}'
		. '@font-face{font-family:"Inter fallback: Arial";src:local("Arial");font-display:swap;font-weight:400 700;font-style:normal;size-adjust:107.7766%;ascent-override:89.885%;descent-override:22.3806%;line-gap-override:0%}'
		. '@font-face{font-family:"Inter fallback: Arial";src:local("Arial");font-display:swap;font-weight:400 700;font-style:italic;size-adjust:107.7766%;ascent-override:89.885%;descent-override:22.3806%;line-gap-override:0%}'
		. ':root{--blume-ff-inter:Inter,"Inter fallback: Arial",ui-sans-serif,system-ui,sans-serif}';
}

/**
 * Set `data-theme` on `<html>` before the first paint: the saved choice, else the system scheme.
 */
function theme_script() {
	echo '<script>(()=>{const r=document.documentElement;let s=null;try{s=localStorage.getItem("blume-theme")}catch{}const sys=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";r.dataset.theme=s==="dark"||s==="light"?s:sys;let printed=null;addEventListener("beforeprint",()=>{if(r.dataset.theme==="dark"){printed="dark";r.dataset.theme="light"}});addEventListener("afterprint",()=>{if(printed){r.dataset.theme=printed;printed=null}})})();</script>' . "\n";
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
 * Blume's body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function body_class( $classes ) {
	return array_merge( $classes, array( 'bg-background', 'font-sans', 'text-foreground', 'antialiased' ) );
}

/**
 * `dir="ltr"` on `<html>`.
 *
 * @param string $output Attributes.
 * @return string
 */
function language_attributes( $output ) {
	return false === strpos( $output, 'dir=' ) ? 'dir="ltr" ' . $output : $output;
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
