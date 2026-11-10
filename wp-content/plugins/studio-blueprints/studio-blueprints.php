<?php
/**
 * Plugin Name: WordPress Studio Blueprints
 * Description: DEMO. The Blueprints section of wordpress.studio: a gallery at /blueprints/, a page per Blueprint with a preview and the ways to launch it in WordPress Studio, and the catalog as JSON in the shape the Studio app reads. Renders in the docs design, so it needs the Studio Docs plugin.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Automattic
 * License: GPLv2 or later
 * Text Domain: studio-blueprints
 * Update URI: false
 *
 * The catalog is catalog/ (written by scripts/build-blueprints-catalog.mjs from the `blueprints/` folder of
 * Automattic/studio). Nothing is stored in
 * the database: the pages are virtual, resolved from the request path.
 *
 *   /blueprints/                        the gallery
 *   /blueprints/<slug>/                 one Blueprint
 *   /blueprints/index.json              the catalog, as WordPress Studio's fetchStudioBlueprints() expects it
 *   /blueprints/<slug>/blueprint.json   the Blueprint itself
 *   /blueprints/<slug>/bundle.zip       the Blueprint with its bundled resources, when it has any
 *
 * @package Studio_Blueprints
 */

namespace Studio_Blueprints;

defined( 'WPINC' ) || die();

const PLUGIN_DIR = __DIR__;
const BASE       = 'blueprints';

require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__ . '/inc/render.php';

add_filter( 'do_parse_request', __NAMESPACE__ . '\\parse_request', 10, 2 );
add_action( 'wp', __NAMESPACE__ . '\\setup' );
add_action( 'init', __NAMESPACE__ . '\\register_block' );
add_filter( 'template_include', __NAMESPACE__ . '\\template', 99 );

/**
 * What the request path asks for: null (not ours), or [ 'view' => gallery|single|index-json|blueprint-json,
 * 'blueprint' => array|null ].
 *
 * @return array|null
 */
function route() {
	static $route = false;
	if ( false !== $route ) {
		return $route;
	}
	$route = null;
	$path  = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$home  = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path, $home ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}
	$parts = explode( '/', $path );
	if ( BASE !== $parts[0] || count( $parts ) > 3 ) {
		return $route;
	}
	if ( 1 === count( $parts ) ) {
		$route = array( 'view' => 'gallery', 'blueprint' => null );
	} elseif ( 2 === count( $parts ) && 'index.json' === $parts[1] ) {
		$route = array( 'view' => 'index-json', 'blueprint' => null );
	} else {
		$blueprint = find( $parts[1] );
		if ( $blueprint && 2 === count( $parts ) ) {
			$route = array( 'view' => 'single', 'blueprint' => $blueprint );
		} elseif ( $blueprint && 'blueprint.json' === $parts[2] ) {
			$route = array( 'view' => 'blueprint-json', 'blueprint' => $blueprint );
		} elseif ( $blueprint && 'bundle.zip' === $parts[2] && bundle_file( $blueprint ) ) {
			$route = array( 'view' => 'bundle', 'blueprint' => $blueprint );
		}
	}
	return $route;
}

/**
 * Serve the JSON files, and keep WordPress from looking the path up as a post.
 *
 * @param bool $parse Whether to parse the request.
 * @param \WP  $wp    The environment.
 * @return bool
 */
function parse_request( $parse, $wp ) {
	$route = route();
	if ( ! $route ) {
		return $parse;
	}
	if ( 'index-json' === $route['view'] ) {
		send_json( array( 'blueprints' => array_map( __NAMESPACE__ . '\\api_item', catalog() ) ) );
	}
	if ( 'blueprint-json' === $route['view'] ) {
		send_json( $route['blueprint']['blueprint'] );
	}
	if ( 'bundle' === $route['view'] ) {
		$file = bundle_file( $route['blueprint'] );
		status_header( 200 );
		header( 'Content-Type: application/zip' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'Content-Disposition: attachment; filename="' . $route['blueprint']['slug'] . '.zip"' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: public, max-age=300' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
	$wp->query_vars = array();
	remove_action( 'template_redirect', 'redirect_canonical' );
	return false;
}

/**
 * Send JSON any origin can read (Playground and WordPress Studio on the web fetch these from the browser).
 *
 * @param mixed $data Data.
 */
function send_json( $data ) {
	status_header( 200 );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Cache-Control: public, max-age=300' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/**
 * A Blueprints page borrows the docs frame: its stylesheets, color scheme script, favicons and body class.
 */
function setup() {
	$route = route();
	if ( ! $route ) {
		return;
	}
	global $wp_query;
	$wp_query->is_home = false;
	$wp_query->is_404  = false;
	status_header( 200 );

	if ( function_exists( '\\WP_Docs\\Assets\\enqueue' ) ) {
		add_action( 'wp_enqueue_scripts', '\\WP_Docs\\Assets\\enqueue' );
		add_action( 'wp_head', '\\WP_Docs\\Assets\\theme_script', 0 );
		add_action( 'wp_head', '\\WP_Docs\\Assets\\head', 2 );
		add_filter( 'body_class', '\\WP_Docs\\Assets\\body_class' );
		add_filter( 'get_site_icon_url', '__return_empty_string' );
	}
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue', 11 );
	add_action( 'wp_head', __NAMESPACE__ . '\\head', 3 );
	add_filter( 'body_class', static fn( $classes ) => array_merge( array_diff( $classes, array( 'home', 'blog' ) ), array( 'studio-blueprints' ) ) );
	add_filter( 'document_title_separator', static fn() => '-' );
	add_filter( 'document_title', static fn( $title ) => str_replace( ' &#8211; ', ' - ', $title ) );
	add_filter(
		'document_title_parts',
		static function () use ( $route ) {
			return 'single' === $route['view']
				? array( 'title' => $route['blueprint']['title'] . ' Blueprint', 'site' => 'WordPress Studio' )
				: array( 'title' => 'Blueprints', 'site' => 'WordPress Studio' );
		}
	);
}

/**
 * The Blueprints stylesheet and script, on top of the docs ones.
 */
function enqueue() {
	$version = static fn( $file ) => (string) filemtime( PLUGIN_DIR . '/assets/' . $file );
	wp_enqueue_style( 'studio-blueprints', plugins_url( 'assets/blueprints.css', __FILE__ ), array( 'studio-docs' ), $version( 'blueprints.css' ) );
	wp_enqueue_script( 'studio-blueprints', plugins_url( 'assets/blueprints.js', __FILE__ ), array(), $version( 'blueprints.js' ), array( 'strategy' => 'defer' ) );
}

/**
 * Description and canonical.
 */
function head() {
	$route       = route();
	$description = 'single' === $route['view'] ? $route['blueprint']['excerpt'] : 'Ready-made WordPress sites for WordPress Studio. Preview a Blueprint, then open it in the app, on the web or from the command line.';
	$url         = 'single' === $route['view'] ? url( $route['blueprint']['slug'] ) : url();
	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
}

/**
 * The theme's `blueprints` template, rendered through the block template canvas.
 *
 * @param string $template Template path.
 * @return string
 */
function template( $template ) {
	if ( ! route() ) {
		return $template;
	}
	$file = get_theme_file_path( 'templates/blueprints.html' );
	if ( ! is_readable( $file ) ) {
		return $template;
	}
	global $_wp_current_template_content, $_wp_current_template_id;
	$_wp_current_template_content = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$_wp_current_template_id      = get_stylesheet() . '//blueprints';
	return ABSPATH . WPINC . '/template-canvas.php';
}

/**
 * One block for the template: the gallery or the Blueprint page, depending on the request.
 */
function register_block() {
	register_block_type(
		'studio-blueprints/view',
		array(
			'title'           => 'Blueprints',
			'category'        => 'widgets',
			'render_callback' => static function () {
				$route = route();
				if ( ! $route ) {
					return '';
				}
				return 'single' === $route['view'] ? Render\single( $route['blueprint'] ) : Render\gallery();
			},
		)
	);
}
