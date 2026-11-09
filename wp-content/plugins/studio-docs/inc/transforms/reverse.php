<?php
/**
 * Reverse sync, step 3: merge the blocks saved in the editor into the Markdown source.
 *
 * Three inputs: the Markdown body the page was imported from (the baseline), the blocks the editor
 * saved (live), and a converter that turns a piece of Markdown into blocks the same way the import
 * does. The merge:
 *
 * 1. splits the baseline into chunks (chunks.php) and converts each chunk on its own, so every
 *    baseline block knows which chunk of source text produced it;
 * 2. fingerprints baseline and live blocks by meaning (name, attributes that matter, the saved HTML
 *    with whitespace and entities normalised) and takes the longest common subsequence;
 * 3. walks the baseline chunks in order: a chunk whose blocks all survived, in order and together,
 *    is copied byte for byte; blocks the editor added, changed or reordered are written with
 *    markdown-out.php; a chunk whose blocks are all gone is dropped.
 *
 * The result is a Markdown body whose diff against the source is the editor's change and nothing
 * else, plus the list of operations that produced it (`ops`: keep, replace, insert, delete, each
 * naming the baseline chunks it touched), which upstream.php replays onto the upstream file. The
 * caller verifies the body by converting it and comparing fingerprints with the live blocks
 * (verify()), and refuses to reconcile when they differ.
 *
 * Pure PHP.
 *
 * @package WP_Docs
 */

namespace WP_Docs\Transforms\Reverse;

use WP_Docs\Transforms\Chunks;
use WP_Docs\Transforms\Markdown_Out;

/**
 * Split the baseline into chunks and convert each on its own: the expensive half of a merge, shared
 * when the same baseline is merged twice (once per link form).
 *
 * @param string   $baseline Baseline Markdown body (no front matter, no title H1).
 * @param callable $convert  fn( string $markdown ): string. Block markup for a piece of Markdown.
 * @return array { chunks: array[] (each with start and end into base), base: array[] ({ chunk, fp }) }
 */
function prepare( string $baseline, callable $convert ): array {
	$chunks = Chunks\chunk( $baseline );
	$base   = array(); // Flat list of baseline blocks, each with its chunk index.
	foreach ( $chunks as $ci => $chunk ) {
		$blocks = 'comment' === $chunk['kind'] ? array() : Markdown_Out\parse( $convert( $chunk['text'] ) );
		$chunks[ $ci ]['start'] = count( $base );
		foreach ( $blocks as $block ) {
			$base[] = array(
				'chunk' => $ci,
				'fp'    => fingerprint( $block ),
			);
		}
		$chunks[ $ci ]['end'] = count( $base );
	}
	return array(
		'chunks' => $chunks,
		'base'   => $base,
	);
}

/**
 * Merge the live blocks into the baseline Markdown body.
 *
 * @param string     $baseline Baseline Markdown body (no front matter, no title H1).
 * @param array[]    $live     Live blocks (parsed; Markdown_Out\parse()).
 * @param callable   $convert  fn( string $markdown ): string. Block markup for a piece of Markdown.
 * @param array      $options  Markdown_Out options (link, image).
 * @param array|null $prepared prepare() of the same baseline and converter, to skip converting again.
 * @return array {
 *     @type string $markdown   The merged body, no trailing newline.
 *     @type int    $verbatim   Chunks copied unchanged.
 *     @type int    $rewritten  Chunks rewritten from blocks.
 *     @type int    $added      Blocks added outside any chunk.
 *     @type int    $removed    Chunks dropped.
 *     @type array  $warnings   From Markdown_Out.
 *     @type array  $ops        In order: { op: keep, chunks: [i], text, gap } | { op: replace, chunks: [i…], text }
 *                              | { op: insert, after: i|null, text } | { op: delete, chunks: [i…] }.
 * }
 */
function merge( string $baseline, array $live, callable $convert, array $options = array(), ?array $prepared = null ): array {
	$prepared = $prepared ?? prepare( $baseline, $convert );
	$chunks   = $prepared['chunks'];
	$base     = $prepared['base'];

	$live_fp = array_map( __NAMESPACE__ . '\fingerprint', $live );
	$pairs   = lcs( array_column( $base, 'fp' ), $live_fp ); // base index => live index.

	$ops      = array();
	$warnings = array();
	$stats    = array(
		'verbatim'  => 0,
		'rewritten' => 0,
		'added'     => 0,
		'removed'   => 0,
	);
	$l        = 0;
	$total    = count( $live );
	$last_ci  = null; // The last baseline chunk an op referred to (where an insert goes).

	$live_markdown = static function ( int $from, int $to ) use ( &$warnings, $live, $options ): string {
		$out = array();
		for ( $k = $from; $k <= $to; $k++ ) {
			$md = Markdown_Out\block_to_markdown( $live[ $k ], $options, $warnings );
			if ( '' !== $md ) {
				$out[] = $md;
			}
		}
		return implode( "\n\n", $out );
	};

	$chunk_count = count( $chunks );
	for ( $ci = 0; $ci < $chunk_count; $ci++ ) {
		$chunk = $chunks[ $ci ];
		$s     = $chunk['start'];
		$e     = $chunk['end'];

		if ( $e === $s ) {
			// A comment, or Markdown that produces no block: keep it where it was.
			$ops[]   = array(
				'op'     => 'keep',
				'chunks' => array( $ci ),
				'text'   => $chunk['text'],
				'gap'    => $chunk['gap'],
			);
			$last_ci = $ci;
			continue;
		}

		$first_live = null;
		$last_live  = null;
		for ( $b = $s; $b < $e; $b++ ) {
			if ( isset( $pairs[ $b ] ) ) {
				$first_live = null === $first_live ? $pairs[ $b ] : min( $first_live, $pairs[ $b ] );
				$last_live  = null === $last_live ? $pairs[ $b ] : max( $last_live, $pairs[ $b ] );
			}
		}

		if ( null === $first_live ) {
			// None of the chunk's blocks survived. The run of such chunks from here, together with the
			// live blocks up to the next surviving block, is one replacement: a removal when there are
			// no new live blocks, a rewrite otherwise.
			$run_end = $ci;
			while ( $run_end + 1 < $chunk_count && $chunks[ $run_end + 1 ]['end'] > $chunks[ $run_end + 1 ]['start'] && ! chunk_has_match( $chunks[ $run_end + 1 ], $pairs ) ) {
				++$run_end;
			}
			$anchor = $total;
			for ( $b = $chunks[ $run_end ]['end']; $b < count( $base ); $b++ ) {
				if ( isset( $pairs[ $b ] ) ) {
					$anchor = $pairs[ $b ];
					break;
				}
			}
			$run = range( $ci, $run_end );
			if ( $anchor > $l ) {
				$stats['rewritten'] += count( $run );
				$ops[]               = array(
					'op'     => 'replace',
					'chunks' => $run,
					'text'   => $live_markdown( $l, $anchor - 1 ),
				);
				$l                   = $anchor;
			} else {
				$stats['removed'] += count( $run );
				$ops[]             = array(
					'op'     => 'delete',
					'chunks' => $run,
				);
			}
			$last_ci = $run_end;
			$ci      = $run_end;
			continue;
		}

		// Live blocks before this chunk's first surviving block are new.
		if ( $first_live > $l ) {
			$stats['added'] += $first_live - $l;
			$ops[]           = array(
				'op'    => 'insert',
				'after' => $last_ci,
				'text'  => $live_markdown( $l, $first_live - 1 ),
			);
			$l               = $first_live;
		}

		$intact = true;
		for ( $b = $s; $intact && $b < $e; $b++ ) {
			if ( ! isset( $pairs[ $b ] ) || $pairs[ $b ] !== $l + ( $b - $s ) ) {
				$intact = false;
			}
		}

		if ( $intact ) {
			$ops[]   = array(
				'op'     => 'keep',
				'chunks' => array( $ci ),
				'text'   => $chunk['text'],
				'gap'    => $chunk['gap'],
			);
			$l      += $e - $s;
			$last_ci = $ci;
			++$stats['verbatim'];
			continue;
		}

		// Part of the chunk changed: rewrite the live span it covers.
		++$stats['rewritten'];
		$ops[]   = array(
			'op'     => 'replace',
			'chunks' => array( $ci ),
			'text'   => $live_markdown( $l, $last_live ),
		);
		$l       = $last_live + 1;
		$last_ci = $ci;
	}

	if ( $l < $total ) {
		$stats['added'] += $total - $l;
		$ops[]           = array(
			'op'    => 'insert',
			'after' => $last_ci,
			'text'  => $live_markdown( $l, $total - 1 ),
		);
	}

	return array_merge(
		$stats,
		array(
			'markdown' => join_ops( $ops ),
			'warnings' => $warnings,
			'ops'      => $ops,
		)
	);
}

/**
 * The merged body from the operations: the file's own blank lines between two kept chunks, one
 * blank line everywhere else.
 *
 * @param array $ops Operations from merge().
 * @return string
 */
function join_ops( array $ops ): string {
	$markdown = '';
	$first    = true;
	$previous = false; // Whether the previous piece was a kept chunk.
	foreach ( $ops as $op ) {
		if ( 'delete' === $op['op'] || '' === $op['text'] ) {
			continue;
		}
		$kept = 'keep' === $op['op'];
		if ( $first ) {
			$markdown = $op['text'];
			$first    = false;
		} elseif ( $kept && $previous ) {
			// Two kept chunks: the file's own spacing, which may be none (a heading and its paragraph).
			$markdown .= "\n" . $op['gap'] . $op['text'];
		} else {
			$markdown .= "\n\n" . $op['text'];
		}
		$previous = $kept;
	}
	return $markdown;
}

/**
 * Whether any block of a chunk survived in the live blocks.
 *
 * @param array $chunk Chunk with start and end.
 * @param array $pairs LCS pairs.
 * @return bool
 */
function chunk_has_match( array $chunk, array $pairs ): bool {
	for ( $b = $chunk['start']; $b < $chunk['end']; $b++ ) {
		if ( isset( $pairs[ $b ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Compare the blocks a Markdown body converts to with the live blocks.
 *
 * @param string   $markdown Merged Markdown body.
 * @param array[]  $live     Live blocks.
 * @param callable $convert  The converter used by merge().
 * @return array|null Null when they match; else { index, expected, actual, live, result } for the first
 *                    difference (expected and actual are block names, or "(none)").
 */
function verify( string $markdown, array $live, callable $convert ): ?array {
	$result  = Markdown_Out\parse( $convert( $markdown ) );
	$live_fp = array_map( __NAMESPACE__ . '\fingerprint', $live );
	$res_fp  = array_map( __NAMESPACE__ . '\fingerprint', $result );
	$count   = max( count( $live_fp ), count( $res_fp ) );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ( $live_fp[ $i ] ?? null ) !== ( $res_fp[ $i ] ?? null ) ) {
			return array(
				'index'    => $i,
				'expected' => isset( $live[ $i ] ) ? (string) $live[ $i ]['blockName'] : '(none)',
				'actual'   => isset( $result[ $i ] ) ? (string) $result[ $i ]['blockName'] : '(none)',
				'live'     => $live_fp[ $i ] ?? '',
				'result'   => $res_fp[ $i ] ?? '',
			);
		}
	}
	return null;
}

/**
 * Fingerprints of the top-level blocks of a page, in order: the content as far as it matters.
 *
 * @param string $markup Block markup.
 * @return string[]
 */
function fingerprints( string $markup ): array {
	return array_map( __NAMESPACE__ . '\fingerprint', Markdown_Out\parse( $markup ) );
}

/**
 * A block's fingerprint: what has to be equal for two blocks to mean the same thing.
 *
 * Attributes the editor may add or drop without a visible change are ignored, defaults are
 * normalised (a callout is a note, a code block has no language, a heading is level 2, core's Code
 * block is this plugin's), and the saved HTML is compared with entities decoded, whitespace
 * collapsed (and dropped next to tags) and self-closing slashes dropped. A callout is compared by its inner blocks only, because
 * the importer writes a wrapper `<div>` around them and the editor saves none.
 *
 * @param array $block Parsed block.
 * @return string
 */
function fingerprint( array $block ): string {
	$name  = (string) $block['blockName'];
	$attrs = (array) $block['attrs'];
	foreach ( array( 'className', 'lock', 'metadata', 'sizeSlug', 'width', 'height', 'aspectRatio', 'scale', 'linkDestination', 'anchor', 'align', 'style', 'fontSize', 'backgroundColor', 'textColor', 'id' ) as $drop ) {
		unset( $attrs[ $drop ] );
	}
	if ( isset( $attrs['ordered'] ) && ! $attrs['ordered'] ) {
		unset( $attrs['ordered'] );
	}
	if ( isset( $attrs['level'] ) && 2 === (int) $attrs['level'] ) {
		unset( $attrs['level'] );
	}
	if ( 'core/code' === $name ) {
		$name = Markdown_Out\CODE_BLOCK;
	}
	if ( Markdown_Out\CODE_BLOCK === $name ) {
		if ( '' === strtolower( trim( (string) ( $attrs['language'] ?? '' ) ) ) ) {
			unset( $attrs['language'] );
		} else {
			$attrs['language'] = strtolower( trim( (string) $attrs['language'] ) );
		}
		$html = normalize_html( Markdown_Out\flatten( $block ) );
		$html = preg_replace( '/^<pre[^>]*>/', '<pre>', $html );
	} elseif ( Markdown_Out\CALLOUT_BLOCK === $name ) {
		$attrs['type'] = strtolower( (string) ( $attrs['type'] ?? 'note' ) );
		if ( '' === trim( (string) ( $attrs['title'] ?? '' ) ) ) {
			unset( $attrs['title'] );
		}
		$html = normalize_html( Markdown_Out\flatten_inner( $block ) );
	} else {
		$html = normalize_html( Markdown_Out\flatten( $block ) );
	}
	ksort( $attrs );

	return $name . '|' . json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '|' . $html;
}

/**
 * HTML normalised for comparison.
 *
 * @param string $html HTML.
 * @return string
 */
function normalize_html( string $html ): string {
	$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$html = preg_replace( '/\s*\/>/', '>', $html );
	$html = preg_replace( '/<(h[1-6])\b([^>]*)\sid="[^"]*"/', '<$1$2', $html );
	$html = preg_replace( '/>\s+</', '><', $html );
	// A loose list item (`<li><p>…</p></li>`) means the same as a tight one; the parser picks one or the
	// other from the blank lines around the item, which differ between a chunk and the whole page.
	$html = preg_replace( '#<li><p>#', '<li>', $html );
	$html = preg_replace( '#</p>(</li>|<ul|<ol|<pre)#', '$1', $html );
	$html = preg_replace( '/\s+(<\/[a-z])/i', '$1', $html );
	$html = preg_replace( '/(<[a-z][^>]*>)\s+/i', '$1', $html );
	$html = preg_replace( '/\s+/', ' ', $html );
	$html = preg_replace( '/<(\w+)[^>]*\bclass=""[^>]*>/', '<$1>', $html );
	return trim( $html );
}

/**
 * Longest common subsequence of two fingerprint lists.
 *
 * @param string[] $a Baseline fingerprints.
 * @param string[] $b Live fingerprints.
 * @return array<int,int> a index => b index, in increasing order on both sides.
 */
function lcs( array $a, array $b ): array {
	$n = count( $a );
	$m = count( $b );
	if ( ! $n || ! $m ) {
		return array();
	}

	$table = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
	for ( $i = $n - 1; $i >= 0; $i-- ) {
		for ( $j = $m - 1; $j >= 0; $j-- ) {
			$table[ $i ][ $j ] = $a[ $i ] === $b[ $j ]
				? $table[ $i + 1 ][ $j + 1 ] + 1
				: max( $table[ $i + 1 ][ $j ], $table[ $i ][ $j + 1 ] );
		}
	}

	$pairs = array();
	$i     = 0;
	$j     = 0;
	while ( $i < $n && $j < $m ) {
		if ( $a[ $i ] === $b[ $j ] ) {
			$pairs[ $i ] = $j;
			++$i;
			++$j;
		} elseif ( $table[ $i + 1 ][ $j ] >= $table[ $i ][ $j + 1 ] ) {
			++$i;
		} else {
			++$j;
		}
	}
	return $pairs;
}

/*
 * ---------------------------------------------------------------------------------------------
 * The whole file: front matter, title, body.
 * ---------------------------------------------------------------------------------------------
 */

/**
 * Split a raw Markdown file into its three parts without reformatting any of them.
 *
 * @param string $raw Raw file.
 * @return array { front_matter: string (the `---` block with its trailing newline, or ""), title: string,
 *                 title_line: string (the `# ` line as written, or ""), body: string, eol: string,
 *                 eof_newline: bool (whether the file ended with a newline) }
 */
function split_file( string $raw ): array {
	$eol = false !== strpos( $raw, "\r\n" ) ? "\r\n" : "\n";
	$raw = str_replace( "\r\n", "\n", ltrim( $raw, "\xEF\xBB\xBF" ) );

	$front = '';
	if ( preg_match( '/\A---[ \t]*\n(.*?\n)?---[ \t]*\n/s', $raw, $m ) ) {
		$front = $m[0];
		$raw   = substr( $raw, strlen( $front ) );
	}

	$title      = '';
	$title_line = '';
	$body       = $raw;
	if ( preg_match( '/\A\s*(# (.+))\n?/', $raw, $m ) ) {
		$title_line = $m[1];
		$title      = trim( $m[2] );
		$body       = substr( $raw, strlen( $m[0] ) );
	}

	return array(
		'front_matter' => $front,
		'title'        => $title,
		'title_line'   => $title_line,
		'body'         => ltrim( $body, "\n" ),
		'eol'          => $eol,
		'eof_newline'  => '' === $raw || "\n" === substr( $raw, -1 ),
	);
}

/**
 * Put a file back together.
 *
 * @param array  $parts From split_file(), possibly with `front_matter` and `title` changed.
 * @param string $body  The merged body.
 * @return string Raw file, ending with a newline when the original did.
 */
function join_file( array $parts, string $body ): string {
	$raw = $parts['front_matter'];
	if ( '' !== $parts['title'] ) {
		$raw .= ( '' !== $raw ? "\n" : '' ) . '# ' . $parts['title'] . "\n\n";
	} elseif ( '' !== $raw ) {
		$raw .= "\n";
	}
	$raw .= rtrim( $body, "\n" ) . ( ( $parts['eof_newline'] ?? true ) ? "\n" : '' );
	return "\n" === $parts['eol'] ? $raw : str_replace( "\n", $parts['eol'], $raw );
}

/**
 * Replace a scalar field in a front matter block, keeping every other line as written. Adds the
 * field when it is missing.
 *
 * @param string $front_matter The `---` block.
 * @param string $key          Field.
 * @param string $value        New value (plain text).
 * @return string
 */
function set_front_matter_field( string $front_matter, string $key, string $value ): string {
	$scalar = yaml_scalar( $value );
	$line   = $key . ': ' . $scalar;
	$count  = 0;
	$out    = preg_replace( '/^' . preg_quote( $key, '/' ) . ':[^\n]*(\n(?:[ \t]+[^\n]*)?)*$/m', $line, $front_matter, 1, $count );
	if ( $count ) {
		return $out;
	}
	if ( '' === $front_matter ) {
		return "---\n{$line}\n---\n";
	}
	return preg_replace( '/\n---[ \t]*\n\z/', "\n{$line}\n---\n", $front_matter, 1 );
}

/**
 * Whether a front matter block has a field.
 *
 * @param string $front_matter The `---` block.
 * @param string $key          Field.
 * @return bool
 */
function has_front_matter_field( string $front_matter, string $key ): bool {
	return (bool) preg_match( '/^' . preg_quote( $key, '/' ) . ':/m', $front_matter );
}

/**
 * A YAML scalar: plain when it is safe, double-quoted otherwise.
 *
 * @param string $value Value.
 * @return string
 */
function yaml_scalar( string $value ): string {
	if ( '' === $value || preg_match( '/^[\s\-?:,\[\]{}#&*!|>\'"%@`]|[:#]\s|\s$|^(true|false|null|yes|no|on|off|~)$|^[\d.+-]+$/i', $value ) ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
	}
	return $value;
}
