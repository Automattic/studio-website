<?php
/**
 * Reverse sync, step 4: replay a merge onto the upstream file.
 *
 * The page the site imports is not the file in Automattic/studio: scripts/build-docs-manifest.mjs
 * stages it first (lifts the title and the first paragraph into front matter, strips the anchors
 * from `## [Heading](#anchor)` lines, fences command lines, rewrites developer.wordpress.com links and
 * upload URLs into relative paths, wraps `<placeholders>` in code). A merge (reverse.php) therefore
 * produces the *staged* body with the editor's change in it, and this module carries that change
 * over to the upstream file:
 *
 * 1. both bodies are split into chunks (chunks.php) and aligned by a key that ignores what the
 *    staging changes (link and image targets, HTML tags, fence lines, punctuation, case);
 * 2. the merge's operations are replayed on the upstream chunks: a kept chunk stays as written
 *    upstream, a replaced chunk's upstream counterpart is replaced by the editor's Markdown, a
 *    deleted chunk's counterpart is dropped, an inserted block goes after the counterpart of the
 *    chunk it followed;
 * 3. upstream chunks with no counterpart (the paragraph the staging lifted into the description)
 *    stay where they are.
 *
 * A replaced chunk whose counterpart cannot be found by key (the upstream text changed since the
 * import) is matched to the most similar unmatched chunk between its neighbours, and a warning says
 * so; with no candidate the editor's text is inserted and the warning asks for a look.
 *
 * Pure PHP.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Transforms\Upstream;

use WP_Docs\Transforms\Chunks;

/**
 * The alignment key of a chunk: its text with everything the staging rewrites removed.
 *
 * @param array|string $chunk Chunk from Chunks\chunk(), or its text.
 * @return string
 */
function key( $chunk ): string {
	$kind = is_array( $chunk ) ? (string) $chunk['kind'] : '';
	$text = is_array( $chunk ) ? (string) $chunk['text'] : (string) $chunk;

	$lines = array_filter(
		explode( "\n", $text ),
		static fn( $line ) => ! preg_match( '/^\s{0,3}(`{3,}|~{3,})/', $line )
	);
	$text  = implode( "\n", $lines );
	// Images keep the file name (the staging keeps it too); links keep their text.
	$text = preg_replace_callback(
		'/!\[([^\]]*)\]\(([^)\s]*)[^)]*\)/',
		static fn( $m ) => $m[1] . ' ' . basename( preg_replace( '/[?#].*$/', '', $m[2] ) ),
		$text
	);
	for ( $i = 0; $i < 2; $i++ ) {
		$text = preg_replace( '/\[([^\[\]]*)\]\([^)]*\)/', '$1', $text );
	}
	$text = preg_replace( '/<[^>]+>/', '', $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );

	// A fenced command paragraph upstream is a paragraph; staged it is a fence. Both key the same.
	$kind = in_array( $kind, array( 'fence', 'paragraph', 'html' ), true ) ? 'text' : $kind;
	return $kind . ':' . $text;
}

/**
 * The text part of a key (without the chunk kind).
 *
 * @param string $key Key.
 * @return string
 */
function key_text( string $key ): string {
	return (string) substr( $key, strpos( $key, ':' ) + 1 );
}

/**
 * Align staged chunks to upstream chunks.
 *
 * Exact keys first (longest common subsequence). Then, for the staged chunks that need a counterpart
 * (the ones the editor changed or removed): a run of consecutive unmatched upstream chunks whose
 * keys, joined, equal the staged chunk's key (the staging fences a comment line and the command
 * under it into one block); failing that, the most similar unmatched upstream chunk between the
 * neighbours, when it is similar enough, with a warning.
 *
 * @param array[] $staged   Chunks of the staged body.
 * @param array[] $upstream Chunks of the upstream body.
 * @param array   $needed   Staged chunk indexes that need a counterpart.
 * @param array   $warnings Receives one line per fuzzy match.
 * @return array<int,int[]> Staged index => upstream indexes (one, or a consecutive run).
 */
function align( array $staged, array $upstream, array $needed = array(), array &$warnings = array() ): array {
	$a     = array_map( __NAMESPACE__ . '\key', $staged );
	$b     = array_map( __NAMESPACE__ . '\key', $upstream );
	$pairs = \WP_Docs\Transforms\Reverse\lcs( $a, $b );
	$map   = array();
	$taken = array();
	foreach ( $pairs as $ci => $ui ) {
		$map[ $ci ]   = array( $ui );
		$taken[ $ui ] = $ci;
	}

	$count_up = count( $upstream );
	$count_st = count( $staged );
	foreach ( $needed as $ci ) {
		if ( isset( $map[ $ci ] ) || ! isset( $staged[ $ci ] ) ) {
			continue;
		}
		// The window: after the previous aligned staged chunk, before the next one.
		$lo = -1;
		$hi = $count_up;
		for ( $p = $ci - 1; $p >= 0; $p-- ) {
			if ( isset( $map[ $p ] ) ) {
				$lo = max( $map[ $p ] );
				break;
			}
		}
		for ( $n = $ci + 1; $n < $count_st; $n++ ) {
			if ( isset( $map[ $n ] ) ) {
				$hi = min( $map[ $n ] );
				break;
			}
		}
		$want = key_text( $a[ $ci ] );

		// A run of upstream chunks that together are this chunk.
		$found = null;
		for ( $start = $lo + 1; $start < $hi && null === $found; $start++ ) {
			if ( isset( $taken[ $start ] ) ) {
				continue;
			}
			$joined = '';
			for ( $end = $start; $end < $hi && ! isset( $taken[ $end ] ); $end++ ) {
				$joined .= key_text( $b[ $end ] );
				if ( $joined === $want && '' !== $want ) {
					$found = range( $start, $end );
					break;
				}
				if ( strlen( $joined ) >= strlen( $want ) ) {
					break;
				}
			}
		}
		if ( null !== $found ) {
			$map[ $ci ] = $found;
			foreach ( $found as $ui ) {
				$taken[ $ui ] = $ci;
			}
			continue;
		}

		$best  = null;
		$score = 0;
		for ( $ui = $lo + 1; $ui < $hi; $ui++ ) {
			if ( isset( $taken[ $ui ] ) ) {
				continue;
			}
			$percent = similarity( $want, key_text( $b[ $ui ] ) );
			if ( $percent > $score ) {
				$score = $percent;
				$best  = $ui;
			}
		}
		if ( null !== $best && $score >= 60 ) {
			$map[ $ci ]     = array( $best );
			$taken[ $best ] = $ci;
			$warnings[]     = sprintf( 'The upstream text of a changed chunk differs from the imported one (%d%% similar); it was replaced anyway. Check: %s', (int) $score, excerpt( $upstream[ $best ]['text'] ) );
		}
	}
	ksort( $map );
	return $map;
}

/**
 * How similar two keys are, in percent, bounded: lengths too different to reach the threshold are
 * not compared at all (similar_text() is cubic), and long keys are compared on their first part.
 *
 * @param string $a Key text.
 * @param string $b Key text.
 * @return float
 */
function similarity( string $a, string $b ): float {
	$la = strlen( $a );
	$lb = strlen( $b );
	if ( 0 === $la || 0 === $lb || 200 * min( $la, $lb ) / ( $la + $lb ) < 60 ) {
		return 0.0;
	}
	similar_text( substr( $a, 0, 2000 ), substr( $b, 0, 2000 ), $percent );
	return (float) $percent;
}

/**
 * Replay a merge onto the upstream body.
 *
 * @param string $upstream_body Body of the upstream file (no front matter, no title line).
 * @param string $staged_body   The staged baseline body the merge ran against.
 * @param array  $ops           Operations from Reverse\merge() run with upstream link and image resolvers.
 * @param array  $warnings      Receives warnings.
 * @return string The upstream body with the editor's changes, no trailing newline.
 */
function apply( string $upstream_body, string $staged_body, array $ops, array &$warnings = array() ): string {
	$staged   = Chunks\chunk( $staged_body );
	$upstream = Chunks\chunk( $upstream_body );

	$needed = array();
	foreach ( $ops as $op ) {
		if ( in_array( $op['op'], array( 'replace', 'delete' ), true ) ) {
			$needed = array_merge( $needed, $op['chunks'] );
		}
	}
	$map = align( $staged, $upstream, $needed, $warnings );

	$slots = array();
	foreach ( $upstream as $ui => $chunk ) {
		$slots[ $ui ] = array(
			'text'    => $chunk['text'],
			'gap'     => $chunk['gap'],
			'keep'    => true,
			'inserts' => array(),
		);
	}
	$leading = array(); // Inserts before the first upstream chunk.
	$last_ui = null;    // The upstream slot the most recent op landed on.

	$position = static function ( array $chunks ) use ( $map ): array {
		$uis = array();
		foreach ( $chunks as $ci ) {
			if ( isset( $map[ $ci ] ) ) {
				$uis = array_merge( $uis, $map[ $ci ] );
			}
		}
		sort( $uis );
		return array_values( array_unique( $uis ) );
	};

	foreach ( $ops as $op ) {
		switch ( $op['op'] ) {
			case 'keep':
				$uis = $position( $op['chunks'] );
				if ( $uis ) {
					$last_ui = max( $uis );
				}
				break;

			case 'delete':
				$uis = $position( $op['chunks'] );
				foreach ( $uis as $ui ) {
					$slots[ $ui ]['keep'] = false;
				}
				if ( $uis ) {
					$last_ui = max( $uis );
				} else {
					$warnings[] = 'A chunk removed in WordPress has no counterpart upstream; nothing removed: ' . excerpt( $staged[ $op['chunks'][0] ]['text'] ?? '' );
				}
				break;

			case 'replace':
				$uis = $position( $op['chunks'] );
				if ( ! $uis ) {
					$warnings[] = 'A chunk changed in WordPress has no counterpart upstream; the new text was inserted instead. Check: ' . excerpt( $op['text'] );
					if ( null === $last_ui ) {
						$leading[] = $op['text'];
					} else {
						$slots[ $last_ui ]['inserts'][] = $op['text'];
					}
					break;
				}
				$slots[ $uis[0] ]['text'] = $op['text'];
				foreach ( array_slice( $uis, 1 ) as $ui ) {
					$slots[ $ui ]['keep'] = false;
				}
				$last_ui = max( $uis );
				break;

			case 'insert':
				if ( null === $last_ui ) {
					$leading[] = $op['text'];
				} else {
					$slots[ $last_ui ]['inserts'][] = $op['text'];
				}
				break;
		}
	}

	// Join: the file's own spacing between two upstream chunks kept as written (which may be none),
	// one blank line next to anything new.
	$out      = '';
	$first    = true;
	$previous = false; // Whether the previous piece was an upstream chunk as written.
	$join     = static function ( string $text, bool $verbatim, string $gap = '' ) use ( &$out, &$first, &$previous ) {
		if ( '' === $text ) {
			return;
		}
		if ( $first ) {
			$out = $text;
		} elseif ( $verbatim && $previous ) {
			$out .= "\n" . $gap . $text;
		} else {
			$out .= "\n" . ( '' !== $gap ? $gap : "\n" ) . $text;
		}
		$first    = false;
		$previous = $verbatim;
	};
	foreach ( $leading as $text ) {
		$join( $text, false );
	}
	foreach ( $upstream as $ui => $chunk ) {
		$slot = $slots[ $ui ];
		if ( $slot['keep'] ) {
			$join( $slot['text'], $slot['text'] === $chunk['text'], $slot['gap'] );
		}
		foreach ( $slot['inserts'] as $text ) {
			$join( $text, false );
		}
	}
	return $out;
}

/**
 * Replace the paragraph the staging lifted into the page description.
 *
 * @param string $upstream_body Upstream body.
 * @param string $old           The imported description (the paragraph's plain text).
 * @param string $new           The description written in WordPress.
 * @return string|null The body with the paragraph replaced, or null when no paragraph matches.
 */
function replace_description( string $upstream_body, string $old, string $new ): ?string {
	$chunks = Chunks\chunk( $upstream_body );
	$want   = key_text( key( $old ) );
	foreach ( $chunks as $chunk ) {
		if ( 'paragraph' !== $chunk['kind'] ) {
			continue;
		}
		if ( key_text( key( $chunk ) ) === $want ) {
			$pos = strpos( $upstream_body, $chunk['text'] );
			if ( false === $pos ) {
				return null;
			}
			return substr( $upstream_body, 0, $pos ) . \WP_Docs\Transforms\Markdown_Out\escape_block_start( $new ) . substr( $upstream_body, $pos + strlen( $chunk['text'] ) );
		}
		break; // Only the first paragraph can have been lifted.
	}
	return null;
}

/**
 * A short, single-line excerpt of a chunk for warnings.
 *
 * @param string $text Text.
 * @return string
 */
function excerpt( string $text ): string {
	$text = preg_replace( '/\s+/', ' ', trim( $text ) );
	return strlen( $text ) > 80 ? substr( $text, 0, 77 ) . '…' : $text;
}
