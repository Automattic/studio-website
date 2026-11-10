<?php
/**
 * The markup of the gallery and of a Blueprint page.
 *
 * @package Studio_Blueprints
 */

namespace Studio_Blueprints\Render;

use const Studio_Blueprints\USE_CASES;
use function Studio_Blueprints\{ catalog, contents, url, image_url, app_link, web_link, preview_url, playground_url, bundle_url };

defined( 'WPINC' ) || die();

/**
 * An inline icon (Lucide outlines, like the docs).
 *
 * @param string $name Icon.
 * @param int    $size Size in px.
 * @return string
 */
function icon( $name, $size = 16 ) {
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'copy'     => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'check'    => '<path d="M20 6 9 17l-5-5"/>',
		'play'     => '<polygon points="6 3 20 12 6 21 6 3"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'monitor'  => '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
		'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
		'layout'   => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
		'chevron'  => '<path d="m9 18 6-6-6-6"/>',
	);
	return '<svg class="sbp-icon" xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * The names of a Blueprint's use cases.
 *
 * @param array $blueprint Blueprint.
 * @return string[]
 */
function use_case_labels( $blueprint ) {
	return array_values( array_filter( array_map( static fn( $key ) => USE_CASES[ $key ] ?? '', $blueprint['useCases'] ) ) );
}

/**
 * A screenshot, or a quiet placeholder when the Blueprint has none.
 *
 * @param array  $blueprint Blueprint.
 * @param string $loading   `lazy` or `eager`.
 * @return string
 */
function thumbnail( $blueprint, $loading = 'lazy' ) {
	$src = image_url( $blueprint );
	if ( ! $src ) {
		return '<span class="sbp-thumb sbp-thumb--empty">' . icon( 'layout', 28 ) . '</span>';
	}
	return '<span class="sbp-thumb"><img src="' . esc_url( $src ) . '" alt="" loading="' . esc_attr( $loading ) . '" decoding="async"></span>';
}

/**
 * A gallery card.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function card( $blueprint ) {
	$labels = use_case_labels( $blueprint );
	$search = strtolower( $blueprint['title'] . ' ' . $blueprint['excerpt'] . ' ' . implode( ' ', $labels ) . ' ' . implode( ' ', contents( $blueprint )['plugins'] ) . ' ' . contents( $blueprint )['theme'] );
	$html   = '<li class="sbp-card" data-use="' . esc_attr( implode( ' ', $blueprint['useCases'] ) ) . '" data-search="' . esc_attr( $search ) . '">';
	$html  .= '<a class="sbp-card__link" href="' . esc_url( url( $blueprint['slug'] ) ) . '">' . thumbnail( $blueprint );
	$html  .= '<span class="sbp-card__body"><span class="sbp-card__title">' . esc_html( $blueprint['title'] ) . '</span>';
	if ( $labels ) {
		$html .= '<span class="sbp-card__tag">' . esc_html( $labels[0] ) . '</span>';
	}
	$html .= '</span>';
	if ( $blueprint['excerpt'] ) {
		$html .= '<span class="sbp-card__excerpt">' . esc_html( $blueprint['excerpt'] ) . '</span>';
	}
	return $html . '</a></li>';
}

/**
 * The gallery: title, use-case filter, search, and the grid.
 *
 * @return string
 */
function gallery() {
	$blueprints = catalog();
	usort( $blueprints, static fn( $a, $b ) => empty( $a['image'] ) <=> empty( $b['image'] ) ); // Those without a screenshot go last.
	$used       = array_unique( array_merge( ...array_map( static fn( $b ) => $b['useCases'], $blueprints ), ...array( array() ) ) );

	$html  = '<main class="sbp-main sbp-gallery">';
	$html .= '<header class="sbp-hero"><h1>Blueprints</h1><p>Ready-made sites for WordPress Studio. Preview one, then open it in the app, on the web or from your terminal.</p></header>';

	$html .= '<div class="sbp-toolbar"><div class="sbp-filters" role="group" aria-label="Filter by use case">';
	$html .= '<button type="button" class="sbp-chip" data-sbp-filter="" aria-pressed="true">All</button>';
	foreach ( USE_CASES as $key => $label ) {
		if ( in_array( $key, $used, true ) ) {
			$html .= '<button type="button" class="sbp-chip" data-sbp-filter="' . esc_attr( $key ) . '" aria-pressed="false">' . esc_html( $label ) . '</button>';
		}
	}
	$html .= '</div><label class="sbp-search">' . icon( 'search' ) . '<span class="screen-reader-text">Search Blueprints</span><input type="search" placeholder="Search Blueprints" data-sbp-search autocomplete="off"></label></div>';

	$html .= '<ul class="sbp-grid" data-sbp-grid>' . implode( '', array_map( __NAMESPACE__ . '\\card', $blueprints ) ) . '</ul>';
	$html .= '<p class="sbp-empty" data-sbp-empty hidden>No Blueprints match. <button type="button" class="sbp-link" data-sbp-reset>Clear the filters</button></p>';

	$html .= '<aside class="sbp-cta"><div><h2>Have a Blueprint of your own?</h2><p>Any Playground Blueprint runs in WordPress Studio. Learn how to write one, or add an Open in WordPress Studio button to your project.</p></div>';
	$html .= '<p class="sbp-cta__links"><a class="sbp-button sbp-button--outline" href="/docs/how-to-create-custom-blueprints/">Create a Blueprint</a><a class="sbp-button sbp-button--ghost" href="/docs/blueprints/open-in-wordpress-studio-button/">Open in WordPress Studio button ' . icon( 'arrow', 14 ) . '</a></p></aside>';

	return $html . '</main>';
}

/**
 * A Blueprint page: the preview on the left, how to launch it and what it installs on the right.
 *
 * @param array $blueprint Blueprint.
 * @return string
 */
function single( $blueprint ) {
	$contents = contents( $blueprint );
	$labels   = use_case_labels( $blueprint );
	$image    = image_url( $blueprint );
	$preview  = preview_url( $blueprint );
	$command  = 'studio blueprint use ' . $blueprint['slug'];
	$json     = wp_json_encode( $blueprint['blueprint'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( strlen( $json ) > 6000 ) {
		$json = substr( $json, 0, 6000 ) . "\n…";
	}

	$html  = '<main class="sbp-main sbp-single">';
	$html .= '<nav class="sbp-crumbs" aria-label="Breadcrumb"><a href="' . esc_url( url() ) . '">Blueprints</a>' . icon( 'chevron', 14 ) . '<span aria-current="page">' . esc_html( $blueprint['title'] ) . '</span></nav>';

	$html .= '<div class="sbp-single__layout"><div class="sbp-single__main">';
	$html .= '<header class="sbp-single__header"><h1>' . esc_html( $blueprint['title'] ) . '</h1>';
	if ( $blueprint['excerpt'] ) {
		$html .= '<p>' . esc_html( $blueprint['excerpt'] ) . '</p>';
	}
	$html .= '</header>';

	// The preview: the screenshot, with a live Playground one click away.
	$html .= '<section class="sbp-preview" data-sbp-preview aria-label="Preview">';
	$html .= '<div class="sbp-preview__bar"><span class="sbp-preview__dots" aria-hidden="true"><i></i><i></i><i></i></span>';
	$html .= '<div class="sbp-segments" role="group" aria-label="Preview mode"><button type="button" aria-pressed="true" data-sbp-mode="image">Screenshot</button>';
	if ( $preview ) {
		$html .= '<button type="button" aria-pressed="false" data-sbp-mode="live">Live preview</button>';
	}
	$html .= '</div>';
	$html .= $preview ? '<a class="sbp-preview__open" href="' . esc_url( playground_url( $blueprint ) ) . '" target="_blank" rel="noopener" title="Open the preview in a new tab" aria-label="Open the preview in a new tab">' . icon( 'external' ) . '</a>' : '<span></span>';
	$html .= '</div><div class="sbp-preview__stage">';
	$html .= $image
		? '<img class="sbp-preview__image" src="' . esc_url( $image ) . '" alt="' . esc_attr( $blueprint['title'] . ' screenshot' ) . '" data-sbp-image>'
		: '<div class="sbp-preview__image sbp-preview__image--empty" data-sbp-image>' . icon( 'layout', 40 ) . '<p>No screenshot yet. Start the live preview to see this Blueprint.</p></div>';
	if ( $preview ) {
		$html .= '<div class="sbp-preview__live" data-sbp-live data-src="' . esc_url( $preview ) . '" hidden><p class="sbp-preview__loading">Starting WordPress in your browser…</p></div>';
		$html .= '<button type="button" class="sbp-preview__start" data-sbp-mode="live">' . icon( 'play', 14 ) . 'Live preview</button>';
	}
	$html .= '</div></section></div>';

	// The rail.
	$html .= '<aside class="sbp-rail">';
	$html .= '<section class="sbp-launch"><h2 class="screen-reader-text">Use this Blueprint</h2>';
	$html .= '<a class="sbp-button sbp-button--accent sbp-button--block" href="' . esc_url( app_link( $blueprint ), array( 'wp-studio' ) ) . '">' . icon( 'monitor' ) . 'Open in WordPress Studio</a>';
	$html .= '<a class="sbp-button sbp-button--outline sbp-button--block" href="' . esc_url( web_link( $blueprint ) ) . '" target="_blank" rel="noopener">' . icon( 'globe' ) . 'Open in WordPress Studio on the web</a>';
	$html .= '<div class="sbp-command"><code>' . esc_html( $command ) . '</code><button type="button" class="sbp-command__copy" data-sbp-copy="' . esc_attr( $command ) . '" aria-label="Copy the command" title="Copy">' . icon( 'copy' ) . icon( 'check' ) . '</button></div>';
	$html .= '<p class="sbp-launch__note">New to WordPress Studio? <a href="/download/">Download the app</a>, it is free.</p></section>';

	$html .= '<section class="sbp-facts"><h2>Details</h2><dl>';
	$facts = array(
		'Use case'  => implode( ', ', $labels ),
		'Theme'     => $contents['theme'],
		'Content'   => $contents['content'] ? 'Demo pages and posts' : '',
		'WordPress' => $contents['wp'],
		'PHP'       => $contents['php'],
	);
	foreach ( array_filter( $facts ) as $term => $value ) {
		$html .= '<div><dt>' . esc_html( $term ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}
	$html .= '</dl>';
	if ( $contents['plugins'] ) {
		$html .= '<h3>Plugins <span>' . count( $contents['plugins'] ) . '</span></h3><ul class="sbp-plugins">';
		foreach ( $contents['plugins'] as $plugin ) {
			$html .= '<li>' . esc_html( $plugin ) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '</section>';

	$html .= '<section class="sbp-files"><h2>Files</h2><ul>';
	$html .= '<li><a href="' . esc_url( url( $blueprint['slug'], 'blueprint.json' ) ) . '">' . icon( 'download', 14 ) . 'blueprint.json</a></li>';
	if ( bundle_url( $blueprint ) ) {
		$html .= '<li><a href="' . esc_url( bundle_url( $blueprint ) ) . '">' . icon( 'download', 14 ) . 'Bundle (.zip)</a></li>';
	}
	if ( $blueprint['demoUrl'] ) {
		$html .= '<li><a href="' . esc_url( $blueprint['demoUrl'] ) . '" target="_blank" rel="noopener">' . icon( 'external', 14 ) . 'Hosted demo</a></li>';
	}
	$html .= '</ul></section></aside></div>';

	$html .= '<details class="sbp-source"><summary>' . icon( 'chevron', 14 ) . 'View the Blueprint</summary><pre><code>' . esc_html( $json ) . '</code></pre></details>';

	// More Blueprints: the same use case first.
	$others = array_values( array_filter( catalog(), static fn( $other ) => $other['slug'] !== $blueprint['slug'] ) );
	$rank = static fn( $other ) => array( count( array_intersect( $other['useCases'], $blueprint['useCases'] ) ), empty( $other['image'] ) ? 0 : 1 );
	usort( $others, static fn( $a, $b ) => $rank( $b ) <=> $rank( $a ) );
	$html .= '<section class="sbp-more"><div class="sbp-more__head"><h2>More Blueprints</h2><a href="' . esc_url( url() ) . '">View all ' . icon( 'arrow', 14 ) . '</a></div><ul class="sbp-grid">' . implode( '', array_map( __NAMESPACE__ . '\\card', array_slice( $others, 0, 3 ) ) ) . '</ul></section>';

	return $html . '</main>';
}
