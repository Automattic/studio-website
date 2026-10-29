<?php
/**
 * Studio theme functions.
 *
 * @package studio
 */

add_action( 'wp_enqueue_scripts', function () {
	$version = wp_get_theme()->get( 'Version' ) . '.' . filemtime( get_parent_theme_file_path( 'style.css' ) );
	wp_enqueue_style(
		'studio-style',
		get_parent_theme_file_uri( 'style.css' ),
		array(),
		$version
	);
	wp_enqueue_script(
		'studio-script',
		get_parent_theme_file_uri( 'assets/js/studio.js' ),
		array(),
		filemtime( get_parent_theme_file_path( 'assets/js/studio.js' ) ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
} );

add_action( 'after_setup_theme', function () {
	add_editor_style( 'style.css' );
} );

/**
 * Mark the document as scripted before first paint, so CSS can lay out
 * JS-enhanced parts (hero tabs) in their final state and avoid layout shift.
 */
add_action( 'wp_head', function () {
	echo "<script>document.documentElement.classList.add('js');</script>\n";
}, 0 );

/**
 * Preload the Latin Instrument Sans file, used by every heading and paragraph,
 * so text renders in it on first paint instead of after the CSS is parsed.
 * The URL must match the theme.json @font-face src exactly (no query string),
 * or the browser fetches the font twice.
 */
add_action( 'wp_head', function () {
	printf(
		'<link rel="preload" as="font" type="font/woff2" crossorigin href="%s">' . "\n",
		esc_url( get_theme_file_uri( 'assets/fonts/instrument-sans/instrument-sans-400-600-latin.woff2' ) )
	);
}, 1 );

/**
 * The site has no blog yet: empty author, category, tag and date archives are
 * real 404s (not empty 200 pages that leak the admin login), and feed links are
 * left out of the head. Both switch off on their own once posts exist.
 */
add_action( 'template_redirect', function () {
	global $wp_query;
	if ( ( is_author() || is_category() || is_tag() || is_date() ) && ! have_posts() ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_search() ) {
		$q->set( 'posts_per_page', 20 );
	}
} );
add_action( 'wp', function () {
	if ( 0 === (int) wp_count_posts( 'post' )->publish ) {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
} );

/**
 * The site uses no emoji images; skip the detection script and its styles.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );

/**
 * SEO basics: meta description, Open Graph, Twitter card and JSON-LD.
 * Each page's description is its excerpt (Page settings > Excerpt in the editor).
 * Steps aside entirely when a dedicated SEO plugin handles the head.
 */
add_action( 'init', function () {
	add_post_type_support( 'page', 'excerpt' );
} );

function studio_seo_plugin_active() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
		return true;
	}
	return class_exists( 'Jetpack' ) && method_exists( 'Jetpack', 'is_module_active' ) && Jetpack::is_module_active( 'seo-tools' );
}

/*
 * The site is published as static files with relative links, so it works on any host. Search
 * engines and link previews need absolute URLs, so those always use the production address.
 */
const STUDIO_PUBLIC_ORIGIN = 'https://wordpress.studio';

function studio_public_url( $url ) {
	return STUDIO_PUBLIC_ORIGIN . wp_make_link_relative( $url );
}

add_filter( 'wp_sitemaps_index_entry', 'studio_public_sitemap_entry' );
add_filter( 'wp_sitemaps_posts_entry', 'studio_public_sitemap_entry' );
function studio_public_sitemap_entry( $entry ) {
	$entry['loc'] = studio_public_url( $entry['loc'] );
	return $entry;
}

function studio_seo_description() {
	if ( is_singular() && has_excerpt() ) {
		return wp_strip_all_tags( get_the_excerpt() );
	}
	return get_bloginfo( 'description' );
}

add_filter( 'jetpack_enable_open_graph', function ( $enabled ) {
	return studio_seo_plugin_active() ? $enabled : false;
} );

add_action( 'wp_head', function () {
	if ( studio_seo_plugin_active() ) {
		return;
	}
	remove_action( 'wp_head', 'rel_canonical' );

	$title       = wp_get_document_title();
	$description = studio_seo_description();
	$url         = is_singular() ? studio_public_url( get_permalink() ) : ( is_front_page() ? studio_public_url( home_url( '/' ) ) : '' );
	$image       = array( studio_public_url( get_theme_file_uri( 'assets/images/share.png' ) ), 1200, 630 );
	$image_alt   = 'WordPress Studio: the app window with a site list, a Studio Code chat and a live site preview.';
	if ( is_singular() && has_post_thumbnail() ) {
		$thumb = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
		if ( $thumb ) {
			$image     = array( studio_public_url( $thumb[0] ), $thumb[1], $thumb[2] );
			$image_alt = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
		}
	}

	if ( $url ) {
		printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $url ) );
	}

	$tags = array(
		array( 'name', 'description', $description ),
		array( 'property', 'og:type', 'website' ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
		array( 'property', 'og:locale', get_locale() ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $description ),
		array( 'property', 'og:url', $url ),
		array( 'property', 'og:image', $image[0] ),
		array( 'property', 'og:image:width', $image[1] ),
		array( 'property', 'og:image:height', $image[2] ),
		array( 'property', 'og:image:alt', $image_alt ),
		array( 'name', 'twitter:card', 'summary_large_image' ),
		array( 'name', 'twitter:title', $title ),
		array( 'name', 'twitter:description', $description ),
		array( 'name', 'twitter:image', $image[0] ),
	);
	foreach ( $tags as $tag ) {
		if ( '' === (string) $tag[2] ) {
			continue;
		}
		printf( "<meta %s=\"%s\" content=\"%s\" />\n", $tag[0], esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
	}

	if ( is_front_page() ) {
		$publisher = array( '@type' => 'Organization', 'name' => 'Automattic', 'url' => 'https://automattic.com/' );
		$graph     = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'       => 'WebSite',
					'name'        => get_bloginfo( 'name' ),
					'url'         => studio_public_url( home_url( '/' ) ),
					'description' => $description,
					'publisher'   => $publisher,
				),
				array(
					'@type'               => 'SoftwareApplication',
					'name'                => 'WordPress Studio',
					'description'         => $description,
					'url'                 => studio_public_url( home_url( '/' ) ),
					'downloadUrl'         => studio_public_url( home_url( '/download/' ) ),
					'image'               => $image[0],
					'applicationCategory' => 'DeveloperApplication',
					'operatingSystem'     => 'macOS, Windows, Linux',
					'license'             => 'https://www.gnu.org/licenses/old-licenses/gpl-2.0.html',
					'isAccessibleForFree' => true,
					'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ),
					'publisher'           => $publisher,
					'sameAs'              => array( 'https://github.com/Automattic/studio' ),
				),
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}, 1 );

/**
 * Block style variations: repeated treatments stay switchable in the editor.
 */
add_action( 'init', function () {
	$styles = array(
		'core/group'     => array(
			'band'        => 'Band',
			'band-head'   => 'Band heading row',
			'card'        => 'Card',
			'placeholder' => 'Placeholder',
			'prompt'      => 'Prompt panel',
			'mosaic'      => 'Block mosaic band',
		),
		'core/code'      => array(
			'command' => 'Command line',
		),
		'core/paragraph' => array(
			'eyebrow' => 'Eyebrow pill',
			'kicker'  => 'Mono kicker',
		),
		'core/button'    => array(
			'ghost' => 'Ghost',
		),
		'core/buttons'   => array(
			'tabs' => 'Tab switch',
		),
		'core/list'      => array(
			'checks' => 'Check list',
		),
		'core/table'     => array(
			'compare'   => 'Comparison',
			'reference' => 'Command reference',
		),
	);
	foreach ( $styles as $block => $variations ) {
		foreach ( $variations as $name => $label ) {
			register_block_style( $block, array( 'name' => $name, 'label' => $label ) );
		}
	}
} );
