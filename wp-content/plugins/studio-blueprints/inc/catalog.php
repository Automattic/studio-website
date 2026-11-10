<?php
/**
 * The catalog: catalog/catalog.json, and what the pages derive from each Blueprint.
 *
 * @package Studio_Blueprints
 */

namespace Studio_Blueprints;

defined( 'WPINC' ) || die();

const USE_CASES = array(
	'business'    => 'Business',
	'blog'        => 'Blog',
	'portfolio'   => 'Portfolio',
	'store'       => 'Store',
	'events'      => 'Events',
	'development' => 'Development',
);

/**
 * Every Blueprint, in catalog order.
 *
 * @return array[]
 */
function catalog() {
	static $catalog = null;
	if ( null === $catalog ) {
		$file    = PLUGIN_DIR . '/catalog/catalog.json';
		$data    = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$catalog = is_array( $data['blueprints'] ?? null ) ? $data['blueprints'] : array();
	}
	return $catalog;
}

/**
 * One Blueprint by slug.
 *
 * @param string $slug Slug.
 * @return array|null
 */
function find( $slug ) {
	foreach ( catalog() as $blueprint ) {
		if ( $blueprint['slug'] === $slug ) {
			return $blueprint;
		}
	}
	return null;
}

/**
 * URL of the gallery, a Blueprint page, or a file under it.
 *
 * @param string $slug Blueprint slug, or '' for the gallery.
 * @param string $file File under it.
 * @return string
 */
function url( $slug = '', $file = '' ) {
	return home_url( '/' . BASE . '/' . ( $slug ? $slug . '/' : '' ) . $file );
}

/**
 * URL of a Blueprint's screenshot, or '' when it has none.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function image_url( $blueprint ) {
	return empty( $blueprint['image'] ) ? '' : plugins_url( 'catalog/images/' . $blueprint['image'], PLUGIN_DIR . '/studio-blueprints.php' );
}

/**
 * A Blueprint in the shape of the WordPress Studio app's catalog (packages/common/lib/studio-blueprints-api.ts).
 *
 * @param array $blueprint Blueprint.
 * @return array
 */
function api_item( $blueprint ) {
	return array(
		'slug'           => $blueprint['slug'],
		'title'          => $blueprint['title'],
		'excerpt'        => $blueprint['excerpt'],
		'image'          => image_url( $blueprint ),
		'playground_url' => playground_url( $blueprint ),
		'blueprint'      => $blueprint['blueprint'],
		'bundle_url'     => bundle_url( $blueprint ),
		'use_cases'      => $blueprint['useCases'],
		'demo_url'       => $blueprint['demoUrl'],
		'url'            => url( $blueprint['slug'] ),
	);
}

/**
 * What a Blueprint installs, read from its steps: [ 'theme' => string, 'plugins' => string[], 'wp' => string,
 * 'php' => string, 'content' => bool ].
 *
 * @param array $blueprint Blueprint.
 * @return array
 */
function contents( $blueprint ) {
	$json    = $blueprint['blueprint'];
	$name    = static function ( $resource ) {
		if ( ! is_array( $resource ) ) {
			return '';
		}
		$slug = $resource['slug'] ?? basename( (string) wp_parse_url( $resource['url'] ?? ( $resource['path'] ?? '' ), PHP_URL_PATH ) );
		$slug = preg_replace( '/\.(zip|git)$/', '', (string) $slug );
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	};
	$theme   = '';
	$plugins = array_map( static fn( $slug ) => $name( array( 'slug' => $slug ) ), is_array( $json['plugins'] ?? null ) ? array_filter( $json['plugins'], 'is_string' ) : array() );
	$content = false;
	foreach ( is_array( $json['steps'] ?? null ) ? $json['steps'] : array() as $step ) {
		if ( ! is_array( $step ) ) {
			continue;
		}
		switch ( $step['step'] ?? '' ) {
			case 'installTheme':
				$theme = $name( $step['themeData'] ?? ( $step['themeZipFile'] ?? null ) );
				break;
			case 'installPlugin':
				$plugins[] = $name( $step['pluginData'] ?? ( $step['pluginZipFile'] ?? null ) );
				break;
			case 'importWxr':
				$content = true;
				break;
		}
	}
	$plugins = array_values( array_unique( array_filter( $plugins ) ) );
	sort( $plugins );
	$versions = is_array( $json['preferredVersions'] ?? null ) ? $json['preferredVersions'] : array();
	return array(
		'theme'   => $theme,
		'plugins' => $plugins,
		'wp'      => 'latest' === ( $versions['wp'] ?? 'latest' ) ? 'Latest' : (string) $versions['wp'],
		'php'     => (string) ( $versions['php'] ?? '' ),
		'content' => $content,
	);
}

/**
 * The link that opens WordPress Studio's Add site dialog with this Blueprint.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function app_link( $blueprint ) {
	return 'wp-studio://add-site?blueprint_url=' . rawurlencode( url( $blueprint['slug'], 'blueprint.json' ) );
}

/**
 * The same in WordPress Studio on the web. The app does not read this parameter yet.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function web_link( $blueprint ) {
	return 'https://app.wordpress.studio/?blueprint=' . rawurlencode( $blueprint['slug'] );
}

/**
 * Path of the bundle the build made for a Blueprint with bundled resources, or '' when it has none.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function bundle_file( $blueprint ) {
	$file = empty( $blueprint['bundle'] ) ? '' : PLUGIN_DIR . '/catalog/bundles/' . basename( $blueprint['bundle'] );
	return $file && is_readable( $file ) ? $file : '';
}

/**
 * URL of a Blueprint's bundle: the one built here, else one hosted elsewhere, else null.
 *
 * @param array $blueprint Blueprint.
 * @return string|null
 */
function bundle_url( $blueprint ) {
	if ( bundle_file( $blueprint ) ) {
		return url( $blueprint['slug'], 'bundle.zip' );
	}
	return $blueprint['bundleUrl'] ?: null;
}

/**
 * The Blueprint in WordPress Playground, or '' when Playground cannot reach it.
 *
 * - A Blueprint without bundled resources travels in the URL fragment, so nothing is fetched.
 * - A bundle is fetched by Playground from the visitor's browser. That works from the published site
 *   (HTTPS, any origin may read the file) and from another public host, but not from a local site:
 *   playground.wordpress.net cannot read http://localhost.
 *
 * @param array $blueprint Blueprint.
 * @param array $query     Extra query arguments for Playground.
 * @return string
 */
function playground_url( $blueprint, $query = array() ) {
	$bundle = bundle_url( $blueprint );
	if ( ! $bundle ) {
		$json = wp_json_encode( $blueprint['blueprint'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return 'https://playground.wordpress.net/' . ( $query ? '?' . http_build_query( $query ) : '' ) . '#' . rawurlencode( $json );
	}
	if ( 'https' !== wp_parse_url( $bundle, PHP_URL_SCHEME ) ) {
		return '';
	}
	return 'https://playground.wordpress.net/?' . http_build_query( array_merge( array( 'blueprint-url' => $bundle ), $query ) );
}

/**
 * The Playground URL of the live preview, without Playground's own chrome.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function preview_url( $blueprint ) {
	return playground_url( $blueprint, array( 'mode' => 'seamless' ) );
}
