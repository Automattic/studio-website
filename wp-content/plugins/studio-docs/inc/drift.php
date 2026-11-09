<?php
/**
 * Drift: the content hash the importer stores, so a page edited in WordPress after its import can be
 * told apart from the page the import wrote.
 *
 * The hash covers what the import controls and the editor can change: the title, the excerpt (the
 * description) and the content. The content is hashed through the block fingerprints of reverse.php,
 * not the raw markup, so saving a page in the editor without a change (which re-serialises every
 * block) is not drift, while a changed word, a new block or a removed one is.
 *
 * Pure PHP; the WordPress side (reading posts, listing drift, exporting) is in class-drift.php.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Drift;

use WP_Docs\Transforms\Reverse;

/**
 * Bump when the hash's inputs or normalisation change: every stored hash then reads as unknown
 * (not as drift) until the next import.
 */
const HASH_VERSION = 1;

/**
 * The hash of a page's imported (or current) state.
 *
 * @param string $content Block markup (post_content).
 * @param string $title   Title.
 * @param string $excerpt Excerpt (the description).
 * @return string `v<version>:<sha256>`.
 */
function content_hash( string $content, string $title, string $excerpt ): string {
	$payload = array(
		'title'   => normalize_text( $title ),
		'excerpt' => normalize_text( $excerpt ),
		'content' => Reverse\fingerprints( $content ),
	);
	return 'v' . HASH_VERSION . ':' . hash( 'sha256', json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/**
 * Whether a stored hash was written by this version of the hash.
 *
 * @param string $hash Stored hash.
 * @return bool
 */
function is_current( string $hash ): bool {
	return 0 === strpos( $hash, 'v' . HASH_VERSION . ':' );
}

/**
 * Text normalised for the hash: whitespace collapsed, entities decoded, trimmed.
 *
 * @param string $text Text.
 * @return string
 */
function normalize_text( string $text ): string {
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Which of the three fields differ between the imported state and the current one.
 *
 * The stored hash is one value, so the fields are told apart by recomputing: the content is unchanged
 * when the current content hashed with the imported title and excerpt gives the stored hash.
 *
 * @param string $stored           Stored hash.
 * @param string $content          Current content.
 * @param string $title            Current title.
 * @param string $excerpt          Current excerpt.
 * @param string $imported_title   Title the import wrote.
 * @param string $imported_excerpt Excerpt the import wrote.
 * @return string[] Any of content, title, description; empty when nothing changed.
 */
function changed_fields( string $stored, string $content, string $title, string $excerpt, string $imported_title, string $imported_excerpt ): array {
	$changed = array();
	if ( content_hash( $content, $imported_title, $imported_excerpt ) !== $stored ) {
		$changed[] = 'content';
	}
	if ( normalize_text( $title ) !== normalize_text( $imported_title ) ) {
		$changed[] = 'title';
	}
	if ( normalize_text( $excerpt ) !== normalize_text( $imported_excerpt ) ) {
		$changed[] = 'description';
	}
	return $changed;
}
