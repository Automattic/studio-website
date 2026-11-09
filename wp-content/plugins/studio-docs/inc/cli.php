<?php
/**
 * WP-CLI: `wp wp-docs import|status|drift|convert`.
 *
 * @package WP_Docs
 */

namespace WP_Docs;

use WP_CLI;
use WP_CLI\Utils;
use WP_Post;
use WP_Query;

defined( 'WPINC' ) || die();

/**
 * Import and inspect the Studio documentation.
 */
class CLI {

	/**
	 * Import the manifest: manifest stage, then Markdown stage, then removals and redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Ignore stored ETags and re-import every page.
	 *
	 * [--manifest=<url-or-path>]
	 * : Import from this manifest instead of WP_DOCS_MANIFEST, for this run only. When it is a file, its
	 * directory is the content checkout local files are read from (unless WP_DOCS_CONTENT_PATH is set).
	 *
	 * ## EXAMPLES
	 *
	 *     wp wp-docs import
	 *     wp wp-docs import --force
	 *     wp wp-docs import --manifest=/var/www/html/wp-docs-tools/sample-content/manifest.json
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function import( $args, $assoc_args ) {
		$summary = Importer::instance()->run(
			array(
				'force'    => (bool) Utils\get_flag_value( $assoc_args, 'force', false ),
				'manifest' => (string) Utils\get_flag_value( $assoc_args, 'manifest', '' ),
			)
		);
		if ( is_wp_error( $summary ) ) {
			WP_CLI::error( $summary->get_error_message() );
		}

		WP_CLI::success(
			sprintf(
				'%d pages from %s: %d updated, %d unchanged, %d errors, %d restored, %d unpublished, %d warnings.',
				$summary['pages'],
				$summary['manifest'],
				$summary['updated'],
				$summary['unchanged'],
				$summary['errors'],
				$summary['restored'],
				count( $summary['removed'] ),
				$summary['warnings']
			)
		);
	}

	/**
	 * Show page counts per section, pages removed from the manifest, and the last import.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function status( $args, $assoc_args ) {
		$query = new WP_Query(
			array(
				'post_type'      => POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$sections = array();
		$removed  = array();
		foreach ( $query->posts as $post ) {
			$section = (string) get_post_meta( $post->ID, '_wp_docs_section', true );
			$section = '' === $section ? '(home)' : $section;
			if ( ! isset( $sections[ $section ] ) ) {
				$sections[ $section ] = array(
					'section'   => $section,
					'published' => 0,
					'draft'     => 0,
					'removed'   => 0,
					'blocks'    => 0,
					'classic'   => 0,
				);
			}
			if ( 'publish' === $post->post_status ) {
				++$sections[ $section ]['published'];
			} else {
				++$sections[ $section ]['draft'];
			}
			$mode = (string) get_post_meta( $post->ID, '_wp_docs_output_mode', true );
			if ( isset( $sections[ $section ][ $mode ] ) ) {
				++$sections[ $section ][ $mode ];
			}
			$flag = (string) get_post_meta( $post->ID, '_wp_docs_removed', true );
			if ( '' !== $flag ) {
				++$sections[ $section ]['removed'];
				$removed[] = array(
					'post'    => $post->ID,
					'path'    => (string) get_post_meta( $post->ID, '_wp_docs_path', true ),
					'removed' => $flag,
				);
			}
		}
		ksort( $sections );

		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		Utils\format_items( $format, array_values( $sections ), array( 'section', 'published', 'draft', 'removed', 'blocks', 'classic' ) );

		if ( $removed ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Removed from the manifest (unpublished, kept as drafts):' );
			Utils\format_items( $format, $removed, array( 'post', 'path', 'removed' ) );
		}

		$last = get_option( Importer::LAST_IMPORT_OPTION );
		WP_CLI::log( '' );
		if ( is_array( $last ) ) {
			WP_CLI::log(
				sprintf(
					'Last import: %s from %s, %d pages, %d updated, %d errors, %d warnings.',
					$last['time'],
					(string) ( $last['manifest'] ?? '' ),
					$last['pages'],
					$last['updated'],
					$last['errors'],
					$last['warnings']
				)
			);
			if ( ! empty( $last['source']['commit'] ) ) {
				WP_CLI::log(
					sprintf(
						'Docs source: %s@%s (%s), staged %s.',
						$last['source']['repository'],
						$last['source']['ref'],
						substr( $last['source']['commit'], 0, 7 ),
						$last['source']['staged']
					)
				);
			}
		} else {
			WP_CLI::log( 'No import has run yet.' );
		}
		$override = Local_Sources::$manifest_override;
		Drift::use_manifest();
		$drifted                          = Drift::detect();
		Local_Sources::$manifest_override = $override;
		if ( $drifted ) {
			WP_CLI::log( sprintf( 'Drift: %d page%s edited in WordPress since the import (%s); `wp wp-docs drift` lists them, `--export` writes the Markdown back.', count( $drifted ), 1 === count( $drifted ) ? '' : 's', implode( ', ', array_column( $drifted, 'path' ) ) ) );
		} else {
			WP_CLI::log( 'Drift: none; every page matches its import.' );
		}
		$next = wp_next_scheduled( Importer::CRON_HOOK );
		WP_CLI::log( 'Next scheduled import: ' . ( $next ? gmdate( 'c', $next ) : 'none' ) );
		WP_CLI::log( 'Manifest: ' . manifest_location() );
		WP_CLI::log( 'Content checkout: ' . Local_Sources::content_path() . ( Local_Sources::prefer_local() ? ' (preferred over GitHub: WP_DOCS_PREFER_LOCAL)' : ' (used for repository-relative sources only)' ) );
		WP_CLI::log( 'Output mode: ' . output_mode() );
	}

	/**
	 * List the pages edited in WordPress since their import, and write their edits back as Markdown.
	 *
	 * The importer records a hash of every page it writes; a page whose title, description or blocks no
	 * longer match is drift. Such edits go live on the next deploy and are overwritten by the next
	 * import, so scripts/build-site.sh stops when there is drift. Reconcile with `--export` (then
	 * scripts/reconcile-docs.mjs opens a pull request against Automattic/studio), or discard the edits
	 * with `--reset`.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or count.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - count
	 * ---
	 *
	 * [--diff]
	 * : Print, per page, a unified diff of the reconciled Markdown against the baseline (the staged file
	 * the page was imported from).
	 *
	 * [--export=<dir>]
	 * : Write the reconciled Markdown files into this directory at their upstream paths
	 * (docs/user/docs/… in Automattic/studio) when a checkout of the repository is available, else as
	 * the staged files under content/. A drift.json next to them lists the pages. A relative directory
	 * is taken from the WordPress root (WP-CLI's working directory), not the shell's.
	 *
	 * [--upstream=<dir>]
	 * : A checkout of Automattic/studio (its root) to replay the edits onto. Default: the checkout the
	 * staging script left in build/docs/.cache/studio, when its revision is there.
	 *
	 * [--manifest=<url-or-path>]
	 * : The manifest (and staged files next to it) the pages were imported from. Default: the one the
	 * last import recorded.
	 *
	 * [--reset]
	 * : Discard the edits: re-import every page from the staged files (`import --force`). Asks first.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation of --reset.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wp-docs drift
	 *     wp wp-docs drift --diff
	 *     wp wp-docs drift --export=build/docs-reconcile
	 *     wp wp-docs drift --reset
	 *
	 * Exit code 1 when any page has drifted (0 when none has).
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function drift( $args, $assoc_args ) {
		$format   = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$manifest = (string) Utils\get_flag_value( $assoc_args, 'manifest', '' );
		Drift::use_manifest( $manifest );

		if ( Utils\get_flag_value( $assoc_args, 'reset', false ) ) {
			$before = Drift::detect();
			if ( ! $before ) {
				WP_CLI::success( 'No drift: nothing to discard.' );
				return;
			}
			WP_CLI::confirm(
				sprintf(
					'Discard the WordPress edits of %d page%s (%s) and re-import every page from %s?',
					count( $before ),
					1 === count( $before ) ? '' : 's',
					implode( ', ', array_column( $before, 'path' ) ),
					manifest_location()
				),
				$assoc_args
			);
			$summary = Importer::instance()->run(
				array(
					'force'    => true,
					'manifest' => manifest_location(),
				)
			);
			if ( is_wp_error( $summary ) ) {
				WP_CLI::error( $summary->get_error_message() );
			}
			WP_CLI::success( sprintf( '%d pages re-imported from %s; the edits of %d drifted page%s were discarded.', $summary['pages'], $summary['manifest'], count( $before ), 1 === count( $before ) ? '' : 's' ) );
			return;
		}

		$drifted = Drift::detect();
		$diff    = (bool) Utils\get_flag_value( $assoc_args, 'diff', false );
		$export  = (string) Utils\get_flag_value( $assoc_args, 'export', '' );
		if ( '' !== $export && ! path_is_absolute( $export ) ) {
			$export = rtrim( (string) getcwd(), '/' ) . '/' . $export;
		}
		if ( '' !== $export && 'json' !== $format && 'count' !== $format ) {
			$format = 'table';
		}

		if ( 'count' === $format ) {
			WP_CLI::line( (string) count( $drifted ) );
			WP_CLI::halt( $drifted ? 1 : 0 );
		}

		if ( ! $drifted ) {
			if ( 'json' === $format ) {
				WP_CLI::line( '[]' );
			} else {
				WP_CLI::success( 'No drift: every docs page matches its import.' );
			}
			return;
		}

		$upstream = (string) Utils\get_flag_value( $assoc_args, 'upstream', '' );
		if ( '' === $upstream && '' !== $export ) {
			$upstream = Drift::default_upstream_dir();
		}

		$rows  = array();
		$infos = array();
		$files = array();
		foreach ( $drifted as $info ) {
			$post = get_post( $info['post'] );
			$row  = array(
				'post'      => $info['post'],
				'path'      => $info['path'],
				'changed'   => implode( ', ', $info['changed'] ),
				'edited_by' => $info['edited_by'],
				'edited_at' => $info['edited_at'],
				'imported'  => $info['imported'],
			);
			if ( $post instanceof WP_Post && ( $diff || '' !== $export ) ) {
				$result = '' !== $export ? Drift::export( $post, $export, $upstream ) : Drift::reconcile( $post );
				if ( is_wp_error( $result ) ) {
					$row['error']    = $result->get_error_message();
					$info['error']   = $result->get_error_message();
				} else {
					$reconciled      = '' !== $export ? $result['reconcile'] : $result;
					$info['verify']  = $reconciled['verify'];
					$info['warnings'] = '' !== $export ? $result['warnings'] : $reconciled['warnings'];
					$info['baseline'] = $reconciled['baseline']['source'];
					$info['stats']    = array_intersect_key( $reconciled['merge'], array_flip( array( 'verbatim', 'rewritten', 'added', 'removed' ) ) );
					if ( $diff ) {
						$label        = ltrim( $info['path'], '/' );
						$info['diff'] = Drift::unified_diff( $reconciled['baseline']['body'], $reconciled['markdown'], "a/{$label}", "b/{$label}" );
					}
					if ( '' !== $export ) {
						$info['file']  = $result['file'];
						$info['form']  = $result['form'];
						$files[]       = array_intersect_key( $info, array_flip( array( 'post', 'id', 'path', 'title', 'source', 'changed', 'edited_by', 'edited_at', 'imported', 'file', 'form', 'warnings', 'verify', 'baseline', 'stats' ) ) );
					}
				}
			}
			$rows[]  = $row;
			$infos[] = $info;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $infos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			WP_CLI::halt( 1 );
		}

		Utils\format_items( 'table', $rows, array( 'post', 'path', 'changed', 'edited_by', 'edited_at', 'imported' ) );
		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '%d page%s edited in WordPress since the import. The next import overwrites these edits.', count( $drifted ), 1 === count( $drifted ) ? '' : 's' ) );

		foreach ( $infos as $info ) {
			if ( ! $diff && '' === $export ) {
				continue;
			}
			WP_CLI::log( '' );
			WP_CLI::log( "== {$info['path']} (post {$info['post']}, baseline: " . ( $info['baseline'] ?? 'n/a' ) . ')' );
			if ( isset( $info['error'] ) ) {
				WP_CLI::warning( $info['error'] );
				continue;
			}
			if ( ! empty( $info['verify'] ) ) {
				$v = $info['verify'];
				WP_CLI::warning( sprintf( 'Block %d (%s) does not round-trip through Markdown (it converts back as %s); check that part of the export by hand.', $v['index'] + 1, $v['expected'], $v['actual'] ) );
			}
			foreach ( (array) ( $info['warnings'] ?? array() ) as $warning ) {
				WP_CLI::warning( $warning );
			}
			if ( $diff ) {
				WP_CLI::log( '' !== $info['diff'] ? rtrim( $info['diff'] ) : '(the reconciled Markdown equals the baseline: the edit has no visible effect)' );
			}
			if ( '' !== $export ) {
				WP_CLI::log( "Written: {$info['file']} ({$info['form']} form)" );
			}
		}

		if ( '' !== $export ) {
			$index = rtrim( $export, '/' ) . '/drift.json';
			wp_mkdir_p( dirname( $index ) );
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$index,
				wp_json_encode(
					array(
						'site'            => home_url(),
						'exported'        => gmdate( 'c' ),
						'upstream'        => $upstream,
						'upstream_commit' => Drift::checkout_commit( $upstream ),
						'source'          => get_option( LAST_IMPORT_OPTION )['source'] ?? null,
						'pages'           => $files,
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				) . "\n"
			);
			WP_CLI::log( '' );
			WP_CLI::log( "Index: {$index}" );
			WP_CLI::log( 'Next: node scripts/reconcile-docs.mjs --from ' . escapeshellarg( rtrim( $export, '/' ) ) . ' (dry run; add --pr to open the pull request), or `wp wp-docs drift --reset` to discard the edits.' );
		} elseif ( ! $diff ) {
			WP_CLI::log( 'Reconcile: `wp wp-docs drift --diff` shows the edits as Markdown, `--export=<dir>` writes them for scripts/reconcile-docs.mjs. Discard: `wp wp-docs drift --reset`.' );
		}
		WP_CLI::halt( 1 );
	}

	/**
	 * Convert one Markdown file with the import pipeline and print the result (no images, no links).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Markdown file on disk.
	 *
	 * [--mode=<mode>]
	 * : blocks or classic. Defaults to the configured output mode.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function convert( $args, $assoc_args ) {
		if ( ! is_readable( $args[0] ) ) {
			WP_CLI::error( "Cannot read {$args[0]}." );
		}
		load_markdown_parser();
		$report = array();
		$html   = Pipeline\convert_file(
			(string) file_get_contents( $args[0] ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			array( 'mode' => Utils\get_flag_value( $assoc_args, 'mode', output_mode() ) ),
			$report
		);
		WP_CLI::log( $html );
		if ( $report['freeform'] ) {
			WP_CLI::warning( 'Content outside blocks: ' . implode( ' | ', $report['freeform'] ) );
		}
	}
}
