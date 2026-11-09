<?php
/**
 * Round-trip tests for the reverse sync, over every staged page (build/docs, written by
 * scripts/build-docs-manifest.mjs).
 *
 *     php tests/run-roundtrip.php [--content=/path/to/build/docs] [--only=cli] [-v]
 *
 * For each page, with the real manifest for link resolution and a fake media library:
 *
 * 1. Regenerate: import the page, write every block back as Markdown, import that, and compare block
 *    fingerprints. Every block the importer produces must survive the writer.
 * 2. No-op merge: merge the imported blocks (unchanged) into the staged body; the result must be the
 *    staged body, byte for byte.
 * 3. One edit: change the text of the first paragraph; the merged body must differ from the staged
 *    body only in that chunk, and verify() must pass.
 * 4. Upstream (when the staging's checkout is at build/docs/.cache/studio): replaying the no-op
 *    merge onto the upstream file gives the upstream body byte for byte, and replaying the one-edit
 *    merge changes exactly one chunk of it.
 *
 * No WordPress runtime, no database.
 *
 * @package WP_Docs
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

use WP_Docs\Manifest;
use WP_Docs\Pipeline;
use WP_Docs\Sources;
use WP_Docs\Transforms\Chunks;
use WP_Docs\Transforms\Markdown_Out;
use WP_Docs\Transforms\Reverse;
use WP_Docs\Transforms\Upstream;

$content = dirname( __DIR__, 4 ) . '/build/docs';
$only    = '';
$verbose = false;
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--content=' ) ) {
		$content = substr( $arg, 10 );
	} elseif ( 0 === strpos( $arg, '--only=' ) ) {
		$only = substr( $arg, 7 );
	} elseif ( '-v' === $arg ) {
		$verbose = true;
	}
}
if ( ! is_file( "$content/manifest.json" ) ) {
	fwrite( STDERR, "No manifest at $content/manifest.json (run `node scripts/build-docs-manifest.mjs` first).\n" );
	exit( 2 );
}
$upstream_dir = is_dir( "$content/.cache/studio/docs" ) ? "$content/.cache/studio" : '';

$manifest = json_decode( file_get_contents( "$content/manifest.json" ), true );
$norm     = Manifest\normalize( array( 'manifest' => $manifest ) );
$entries  = $norm['entries'];
$map      = Manifest\source_map( $entries, array() );
$site     = '/docs/';
$lookup   = Manifest\lookup_callback( $entries, $map, array(), $site );

// Page URL => staged source path and upstream path, for the reverse link maps.
$url_to_staged   = array();
$url_to_upstream = array();
foreach ( $entries as $key => $doc ) {
	$path = (string) ( $doc['_path'] ?? $key );
	$url  = rtrim( $site, '/' ) . '/' . ( '' === $path ? '' : $path . '/' );
	$url_to_staged[ $url ] = $doc['_source']['path'];
	$github                = ! empty( $doc['source_url'] ) ? Sources\parse( (string) $doc['source_url'] ) : null;
	if ( $github ) {
		$url_to_upstream[ $url ] = $github['path'];
	}
}

// Fake media library: repository image path => uploads URL, and back.
$uploads      = array();
$import_image = static function ( array $target, string $alt ) use ( &$uploads ) {
	$url             = '/wp-content/uploads/docs/' . basename( $target['path'] );
	$uploads[ $url ] = $target['path'];
	return array( 'url' => $url, 'id' => crc32( $target['path'] ) % 100000 + 1 );
};

$relative = static function ( string $from_file, string $to_path ): string {
	$from = explode( '/', dirname( $from_file ) );
	$to   = explode( '/', $to_path );
	while ( $from && $to && $from[0] === $to[0] ) {
		array_shift( $from );
		array_shift( $to );
	}
	return str_repeat( '../', count( array_filter( $from, static fn( $s ) => '.' !== $s ) ) ) . implode( '/', $to );
};

$resolvers = static function ( array $base, array $url_to_source, string $own_path, ?callable $image_path ) use ( &$uploads, $relative ): array {
	return array(
		'link'  => static function ( string $href ) use ( $url_to_source, $own_path, $relative ): ?string {
			$fragment = '';
			if ( false !== ( $pos = strpos( $href, '#' ) ) ) {
				$fragment = substr( $href, $pos );
				$href     = substr( $href, 0, $pos );
			}
			if ( isset( $url_to_source[ $href ] ) ) {
				$target = $url_to_source[ $href ];
				return ( $target === $own_path ? '' : $relative( $own_path, $target ) ) . $fragment;
			}
			return null;
		},
		'image' => static function ( string $src ) use ( &$uploads, $own_path, $relative, $image_path ): ?string {
			if ( ! isset( $uploads[ $src ] ) ) {
				return null;
			}
			$path = $image_path ? $image_path( $uploads[ $src ] ) : $uploads[ $src ];
			return $relative( $own_path, $path );
		},
	);
};

$pages = array();
foreach ( $entries as $key => $doc ) {
	$src = $doc['_source'];
	if ( ! is_file( "$content/{$src['path']}" ) ) {
		continue;
	}
	if ( '' !== $only && $key !== $only ) {
		continue;
	}
	$pages[ $key ] = $doc;
}

$failed   = array();
$checked  = 0;
$upstream = 0;
$stats    = array( 'warnings' => array() );

foreach ( $pages as $key => $doc ) {
	++$checked;
	$base    = $doc['_source'];
	$raw     = file_get_contents( "$content/{$base['path']}" );
	$options = array(
		'mode'         => 'blocks',
		'base'         => $base,
		'lookup'       => $lookup,
		'import_image' => $import_image,
	);
	$convert = static fn( string $md ) => Pipeline\convert_body( $md, $options );

	$report = array();
	$blocks = Markdown_Out\parse( Pipeline\convert_file( $raw, $options, $report ) );
	if ( $report['freeform'] ) {
		$failed[ $key ][] = 'freeform content after conversion (classic page); skipped';
		continue;
	}
	$parts       = Reverse\split_file( $raw );
	$out_options = $resolvers( $base, $url_to_staged, $base['path'], null );

	// 1. Regenerate everything.
	$warnings = array();
	$regen    = Markdown_Out\blocks_to_markdown( $blocks, $out_options, $warnings );
	foreach ( $warnings as $w ) {
		$stats['warnings'][ $w ] = ( $stats['warnings'][ $w ] ?? 0 ) + 1;
	}
	$diff = Reverse\verify( $regen, $blocks, $convert );
	if ( $diff ) {
		$at = 0;
		while ( isset( $diff['live'][ $at ], $diff['result'][ $at ] ) && $diff['live'][ $at ] === $diff['result'][ $at ] ) {
			++$at;
		}
		$from = max( 0, $at - 80 );
		$failed[ $key ][] = "regenerate: block {$diff['index']} expected {$diff['expected']} got {$diff['actual']}" . ( $verbose ? "\n    live:   ..." . substr( $diff['live'], $from, 200 ) . "\n    result: ..." . substr( $diff['result'], $from, 200 ) : '' );
	}

	// 2. No-op merge.
	$merged = Reverse\merge( $parts['body'], $blocks, $convert, $out_options );
	if ( rtrim( $merged['markdown'], "\n" ) !== rtrim( $parts['body'], "\n" ) ) {
		$a    = explode( "\n", rtrim( $parts['body'], "\n" ) );
		$b    = explode( "\n", rtrim( $merged['markdown'], "\n" ) );
		$line = 0;
		while ( isset( $a[ $line ], $b[ $line ] ) && $a[ $line ] === $b[ $line ] ) {
			++$line;
		}
		$failed[ $key ][] = sprintf(
			"no-op merge differs at line %d (verbatim %d, rewritten %d, added %d, removed %d)\n    source: %s\n    merged: %s",
			$line + 1,
			$merged['verbatim'],
			$merged['rewritten'],
			$merged['added'],
			$merged['removed'],
			var_export( $a[ $line ] ?? null, true ),
			var_export( $b[ $line ] ?? null, true )
		);
	}
	$noop_ops = $merged['ops'];

	// 3. One edit: the first paragraph.
	$edited = $blocks;
	foreach ( $edited as $i => $block ) {
		if ( 'core/paragraph' === $block['blockName'] ) {
			$edited[ $i ]['innerHTML']       = preg_replace( '#</p>\s*$#', ' Edited in WordPress.</p>', $block['innerHTML'] );
			$edited[ $i ]['innerContent'][0] = $edited[ $i ]['innerHTML'];
			break;
		}
	}
	$merged = Reverse\merge( $parts['body'], $edited, $convert, $out_options );
	$diff   = Reverse\verify( $merged['markdown'], $edited, $convert );
	if ( $diff ) {
		$failed[ $key ][] = "edit: verify failed at block {$diff['index']} ({$diff['expected']} vs {$diff['actual']})";
	} elseif ( $edited !== $blocks && 1 !== $merged['rewritten'] ) {
		$failed[ $key ][] = "edit: expected 1 rewritten chunk, got {$merged['rewritten']} (added {$merged['added']}, removed {$merged['removed']})";
	}
	$edit_ops = $merged['ops'];

	// 4. Upstream: replay both merges onto the upstream file.
	$github = ! empty( $doc['source_url'] ) ? Sources\parse( (string) $doc['source_url'] ) : null;
	if ( '' === $upstream_dir || ! $github || ! is_file( "$upstream_dir/{$github['path']}" ) ) {
		continue;
	}
	++$upstream;
	$up_parts = Reverse\split_file( file_get_contents( "$upstream_dir/{$github['path']}" ) );
	$up_body  = rtrim( $up_parts['body'], "\n" );

	$warnings = array();
	$replayed = Upstream\apply( $up_body, $parts['body'], $noop_ops, $warnings );
	if ( $replayed !== $up_body ) {
		$a    = explode( "\n", $up_body );
		$b    = explode( "\n", $replayed );
		$line = 0;
		while ( isset( $a[ $line ], $b[ $line ] ) && $a[ $line ] === $b[ $line ] ) {
			++$line;
		}
		$failed[ $key ][] = sprintf( "upstream no-op differs at line %d\n    upstream: %s\n    replayed: %s", $line + 1, var_export( $a[ $line ] ?? null, true ), var_export( $b[ $line ] ?? null, true ) );
	}
	if ( $warnings ) {
		$failed[ $key ][] = 'upstream no-op warned: ' . implode( ' | ', $warnings );
	}

	// The edit, written with upstream link and image resolvers.
	$up_options = $resolvers( $base, $url_to_upstream, $github['path'], static fn( string $staged ) => 'docs/user/screenshots/images/' . basename( $staged ) );
	$up_merged  = Reverse\merge( $parts['body'], $edited, $convert, $up_options );
	$warnings   = array();
	$replayed   = Upstream\apply( $up_body, $parts['body'], $up_merged['ops'], $warnings );
	$before     = array_map( static fn( $c ) => $c['text'], Chunks\chunk( $up_body ) );
	$after      = array_map( static fn( $c ) => $c['text'], Chunks\chunk( $replayed ) );
	$changed    = 0;
	foreach ( $before as $i => $text ) {
		if ( ( $after[ $i ] ?? null ) !== $text ) {
			++$changed;
		}
	}
	if ( $edited !== $blocks && ( count( $before ) !== count( $after ) || 1 !== $changed ) ) {
		$failed[ $key ][] = sprintf( 'upstream edit: expected exactly 1 changed chunk, got %d (chunks %d -> %d)%s', $changed, count( $before ), count( $after ), $warnings ? '; ' . implode( ' | ', $warnings ) : '' );
	} elseif ( $verbose && $edited !== $blocks ) {
		foreach ( $before as $i => $text ) {
			if ( ( $after[ $i ] ?? null ) !== $text ) {
				echo "  {$key} upstream edit:\n    - " . str_replace( "\n", "\n    - ", $text ) . "\n    + " . str_replace( "\n", "\n    + ", (string) $after[ $i ] ) . "\n";
			}
		}
	}
}

echo "Checked {$checked} pages ({$upstream} against the upstream checkout), " . count( $failed ) . " with failures.\n";
foreach ( $failed as $key => $messages ) {
	echo "FAIL {$key}\n  " . implode( "\n  ", $messages ) . "\n";
}
if ( $stats['warnings'] ) {
	arsort( $stats['warnings'] );
	echo "Warnings:\n";
	foreach ( $stats['warnings'] as $w => $n ) {
		echo "  {$n}  {$w}\n";
	}
}
exit( $failed ? 1 : 0 );
