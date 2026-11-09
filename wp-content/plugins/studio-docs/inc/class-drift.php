<?php
/**
 * Drift: docs pages edited in WordPress after their import, and the way back to the Markdown.
 *
 * The import is one-directional: the Markdown in Automattic/studio is the source of truth, every
 * import rewrites every page, and an edit made in the block editor is live on the next deploy (the
 * static export reads the database) and gone on the next import. This class makes such edits
 * visible and recoverable:
 *
 * - `store_hash()`: the importer records a hash of what it wrote (drift.php) in `_wp_docs_content_hash`.
 * - `inspect()` / `detect()`: a page whose current hash differs has drifted; the report says which of
 *   title, description and content changed, who last edited the page and when.
 * - `reconcile()`: the page's blocks are merged back into the Markdown it was imported from
 *   (inc/transforms/reverse.php), so the diff is the editor's change and nothing else, and the result
 *   is verified by converting it again.
 * - `export()`: the reconciled Markdown is written at the page's upstream path (docs/user/docs/… in
 *   Automattic/studio) when a checkout of the repository is at hand, replaying the change onto the
 *   upstream file (inc/transforms/upstream.php); else as the staged file. scripts/reconcile-docs.mjs
 *   turns the export into a branch and a pull request.
 *
 * The baseline a page is compared with is the staged file in build/docs/content/ when it is from the
 * revision that was imported, else the Markdown stored for agents (`_wp_docs_markdown`) with its
 * absolute links turned back into the relative ones.
 *
 * @package WP_Docs
 */

namespace WP_Docs;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_Docs\Transforms\Agent_Markdown;
use WP_Docs\Transforms\Markdown_Out;
use WP_Docs\Transforms\Reverse;
use WP_Docs\Transforms\Upstream;

defined( 'WPINC' ) || die();

/**
 * Drift detection and reconciliation.
 */
class Drift {

	/**
	 * Meta key of the hash the importer writes.
	 */
	const HASH_META = '_wp_docs_content_hash';

	/**
	 * Where the screenshots live in Automattic/studio (scripts/build-docs-manifest.mjs, SOURCE.images).
	 */
	const UPSTREAM_IMAGES = 'docs/user/screenshots/images';

	/**
	 * The upload URL prefix the upstream pages still use for their screenshots (OLD_UPLOADS there).
	 */
	const OLD_UPLOADS = 'https://developer.wordpress.com/wp-content/uploads/';

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Detection.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Record the hash of a page's current title, description and content (the importer calls this
	 * right after writing a page).
	 *
	 * @param int $post_id Post ID.
	 * @return string The hash, or empty when there is no such post.
	 */
	public static function store_hash( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$hash = Drift\content_hash( (string) $post->post_content, (string) $post->post_title, (string) $post->post_excerpt );
		update_post_meta( $post->ID, self::HASH_META, $hash );
		return $hash;
	}

	/**
	 * Whether a page matches its import right now (a stored hash that equals the current one).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_clean( $post_id ) {
		$post   = get_post( $post_id );
		$stored = $post ? (string) get_post_meta( $post->ID, self::HASH_META, true ) : '';
		if ( ! $post || '' === $stored || ! Drift\is_current( $stored ) ) {
			return false;
		}
		return Drift\content_hash( (string) $post->post_content, (string) $post->post_title, (string) $post->post_excerpt ) === $stored;
	}

	/**
	 * Read the manifest (and the staged files next to it) from where the pages were last imported,
	 * unless a location is given or the configured one is already that.
	 *
	 * @param string $location Manifest URL or path; empty for the last import's.
	 */
	public static function use_manifest( $location = '' ) {
		if ( '' === $location ) {
			$last     = get_option( LAST_IMPORT_OPTION );
			$location = is_array( $last ) ? (string) ( $last['manifest'] ?? '' ) : '';
		}
		if ( '' !== $location && $location !== manifest_location() ) {
			Local_Sources::$manifest_override = $location;
		}
	}

	/**
	 * Every published docs page, in sidebar order.
	 *
	 * @return WP_Post[]
	 */
	public static function pages() {
		$query = new WP_Query(
			array(
				'post_type'      => POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		return $query->posts;
	}

	/**
	 * The state of one page against its import.
	 *
	 * @param WP_Post $post Page.
	 * @return array {
	 *     @type int      $post       Post ID.
	 *     @type string   $id         Page id (`_wp_docs_id`).
	 *     @type string   $path       URL path (`/docs/cli/`).
	 *     @type string   $title      Current title.
	 *     @type string   $source     Upstream file (from the GitHub source URL), or empty.
	 *     @type bool     $drifted    Whether the page differs from its import.
	 *     @type string[] $changed    content, title, description; `unknown` when there is no hash to compare.
	 *     @type string   $hash       ok, missing (imported before hashes existed) or stale (older hash version).
	 *     @type string   $edited_by  Display name and login of the last editor, or `unknown`.
	 *     @type string   $edited_at  ISO 8601 UTC time of the last modification.
	 *     @type string   $imported   ISO 8601 time of the import.
	 *     @type bool     $modified_after_import Whether post_modified is later than the import (a hint, not proof).
	 * }
	 */
	public static function inspect( WP_Post $post ) {
		$stored   = (string) get_post_meta( $post->ID, self::HASH_META, true );
		$imported = (string) get_post_meta( $post->ID, '_wp_docs_imported', true );
		$modified = get_post_modified_time( 'c', true, $post );
		$after    = '' !== $imported && strtotime( $modified ) > strtotime( $imported );
		$github   = Sources\parse( (string) get_post_meta( $post->ID, '_wp_docs_source_url', true ) );

		$info = array(
			'post'                  => (int) $post->ID,
			'id'                    => (string) get_post_meta( $post->ID, '_wp_docs_id', true ),
			'path'                  => '/docs/' . (string) get_post_meta( $post->ID, '_wp_docs_path', true ),
			'title'                 => (string) $post->post_title,
			'source'                => $github ? $github['path'] : '',
			'drifted'               => false,
			'changed'               => array(),
			'hash'                  => 'ok',
			'edited_by'             => self::editor_of( $post ),
			'edited_at'             => $modified,
			'imported'              => $imported,
			'modified_after_import' => $after,
		);

		if ( '' === $stored || ! Drift\is_current( $stored ) ) {
			// Nothing to compare with: the modification time is the only hint.
			$info['hash'] = '' === $stored ? 'missing' : 'stale';
			if ( $after ) {
				$info['drifted'] = true;
				$info['changed'] = array( 'unknown' );
			}
			return $info;
		}

		$current = Drift\content_hash( (string) $post->post_content, (string) $post->post_title, (string) $post->post_excerpt );
		if ( $current === $stored ) {
			return $info;
		}

		$info['drifted'] = true;
		$info['changed'] = Drift\changed_fields(
			$stored,
			(string) $post->post_content,
			(string) $post->post_title,
			(string) $post->post_excerpt,
			self::imported_title( $post ),
			self::imported_description( $post )
		);
		if ( ! $info['changed'] ) {
			$info['changed'] = array( 'content' );
		}
		return $info;
	}

	/**
	 * The pages that differ from their import.
	 *
	 * @return array[] inspect() results.
	 */
	public static function detect() {
		$drifted = array();
		foreach ( self::pages() as $post ) {
			$info = self::inspect( $post );
			if ( $info['drifted'] ) {
				$drifted[] = $info;
			}
		}
		return $drifted;
	}

	/**
	 * Who last edited a page: the editor's `_edit_last`, else the post author.
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	protected static function editor_of( WP_Post $post ) {
		$user_id = (int) get_post_meta( $post->ID, '_edit_last', true );
		if ( ! $user_id ) {
			$user_id = (int) $post->post_author;
		}
		$user = $user_id ? get_userdata( $user_id ) : null;
		return $user ? $user->display_name . ' (' . $user->user_login . ')' : 'unknown';
	}

	/**
	 * The title the import wrote: the manifest's (which wins over the file's H1).
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	protected static function imported_title( WP_Post $post ) {
		$entry = get_post_meta( $post->ID, 'wporg_markdown_manifest_entry', true );
		return is_array( $entry ) && isset( $entry['title'] ) && '' !== (string) $entry['title'] ? (string) $entry['title'] : (string) $post->post_title;
	}

	/**
	 * The description the import wrote (also the excerpt).
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	protected static function imported_description( WP_Post $post ) {
		return (string) get_post_meta( $post->ID, '_wp_docs_description', true );
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Reconciliation.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * Merge a page's blocks back into the Markdown it was imported from.
	 *
	 * @param WP_Post $post Page.
	 * @return array|WP_Error {
	 *     @type array  $info      inspect().
	 *     @type array  $baseline  { body, raw, parts, source: staged|stored, file }.
	 *     @type string $markdown  The merged body (staged form, relative links).
	 *     @type array  $merge     The merge result (stats, ops, warnings).
	 *     @type array  $warnings  Merge and resolver warnings.
	 *     @type array|null $verify Null when the result converts back to the page's blocks, else the first difference.
	 *     @type array  $live      The page's parsed blocks.
	 *     @type array  $options   Pipeline options used.
	 *     @type array  $prepared  The baseline chunks converted (Reverse\prepare()), for a second merge.
	 * }
	 */
	public static function reconcile( WP_Post $post ) {
		if ( 'blocks' !== (string) get_post_meta( $post->ID, '_wp_docs_output_mode', true ) ) {
			return new WP_Error( 'wp_docs_classic', 'This page was imported as classic HTML; its edits cannot be merged back into Markdown.' );
		}
		load_markdown_parser();
		$importer = Importer::instance();
		$options  = $importer->options_for_post( $post->ID );
		if ( empty( $options['base'] ) ) {
			return new WP_Error( 'wp_docs_no_source', 'This page has no Markdown source recorded.' );
		}

		$warnings  = array();
		$resolvers = self::resolvers( $post, 'staged', '', $warnings );
		$baseline  = self::baseline( $post, $resolvers );
		if ( is_wp_error( $baseline ) ) {
			return $baseline;
		}

		$live     = Markdown_Out\parse( (string) $post->post_content );
		$convert  = static fn( string $markdown ) => Pipeline\convert_body( $markdown, $options );
		$prepared = Reverse\prepare( $baseline['body'], $convert );
		$merge    = Reverse\merge( $baseline['body'], $live, $convert, $resolvers, $prepared );
		$verify   = Reverse\verify( $merge['markdown'], $live, $convert );

		return array(
			'info'     => self::inspect( $post ),
			'baseline' => $baseline,
			'markdown' => $merge['markdown'],
			'merge'    => $merge,
			'warnings' => array_values( array_unique( array_merge( $warnings, $merge['warnings'] ) ) ),
			'verify'   => $verify,
			'live'     => $live,
			'options'  => $options,
			'prepared' => $prepared,
		);
	}

	/**
	 * The Markdown body a page was imported from.
	 *
	 * The staged file (build/docs/content/…) when it exists and was staged from the revision the last
	 * import recorded; else the Markdown stored for agents, with its absolute links and image URLs turned
	 * back into the relative paths the staged file had.
	 *
	 * @param WP_Post $post      Page.
	 * @param array   $resolvers Staged-form link and image resolvers (resolvers()).
	 * @return array|WP_Error { body, raw, parts, source, file }
	 */
	public static function baseline( WP_Post $post, array $resolvers ) {
		$source = Sources\parse( (string) get_post_meta( $post->ID, 'wporg_markdown_source', true ) );
		if ( ! $source ) {
			return new WP_Error( 'wp_docs_no_source', 'This page has no Markdown source recorded.' );
		}

		$file = 'local' === $source['kind'] ? Local_Sources::content_path() . '/' . $source['path'] : '';
		if ( '' !== $file && is_readable( $file ) && self::staged_matches_import() ) {
			$raw   = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$parts = Reverse\split_file( $raw );
			return array(
				'body'   => rtrim( $parts['body'], "\n" ),
				'raw'    => $raw,
				'parts'  => $parts,
				'source' => 'staged',
				'file'   => $file,
			);
		}

		$stored = (string) get_post_meta( $post->ID, '_wp_docs_markdown', true );
		if ( '' === $stored ) {
			return new WP_Error( 'wp_docs_no_baseline', 'Neither the staged file nor the stored Markdown of this page is available; run the import again first.' );
		}
		$stored_parts = Reverse\split_file( $stored );
		$body         = Agent_Markdown\rewrite(
			trim( $stored_parts['body'], "\n" ),
			static fn( string $href ): ?string => ( $resolvers['link'] )( $href ),
			static fn( string $src, string $alt ): ?string => ( $resolvers['image'] )( $src )
		);
		// The stored front matter is the agents' (`sidebar.label`); the staged file's is the staging script's.
		$title = self::imported_title( $post );
		$label = (string) get_post_meta( $post->ID, '_wp_docs_label', true );
		$front = Drift\staged_front_matter( $title, self::imported_description( $post ), $label !== $title ? $label : '' );
		$raw   = $front . "\n" . $body . "\n";
		$parts = Reverse\split_file( $raw );
		return array(
			'body'   => rtrim( $parts['body'], "\n" ),
			'raw'    => $raw,
			'parts'  => $parts,
			'source' => 'stored',
			'file'   => '',
		);
	}

	/**
	 * Whether the staged files next to the manifest are from the revision the last import recorded
	 * (true as well when either revision is unknown).
	 *
	 * @return bool
	 */
	protected static function staged_matches_import() {
		$last   = get_option( LAST_IMPORT_OPTION );
		$was    = is_array( $last ) ? (string) ( $last['source']['commit'] ?? '' ) : '';
		$staged = Local_Sources::load_source( manifest_location() );
		$now    = is_array( $staged ) ? (string) ( $staged['commit'] ?? '' ) : '';
		return '' === $was || '' === $now || $was === $now;
	}

	/**
	 * Link and image resolvers for Markdown_Out: site URLs back to repository paths.
	 *
	 * @param WP_Post $post      Page.
	 * @param string  $form      `staged` (paths relative to the staged file, under build/docs) or
	 *                           `upstream` (relative to the page's file in Automattic/studio).
	 * @param string  $upstream  The upstream file's text, to reuse the upload URL an image already has there.
	 * @param array   $warnings  Receives one line per URL that could not be mapped.
	 * @return array { link: callable, image: callable }
	 */
	public static function resolvers( WP_Post $post, $form, $upstream = '', array &$warnings = array() ) {
		$entries = Importer::instance()->entries();
		$by_url  = array();
		foreach ( $entries as $key => $doc ) {
			$path            = (string) ( $doc['_path'] ?? $key );
			$by_url[ $path ] = $doc;
		}

		$own_source = Sources\parse( (string) get_post_meta( $post->ID, 'wporg_markdown_source', true ) );
		$own_github = Sources\parse( (string) get_post_meta( $post->ID, '_wp_docs_source_url', true ) );
		$own        = 'upstream' === $form ? ( $own_github ? $own_github['path'] : '' ) : ( $own_source ? $own_source['path'] : '' );

		$target_of = static function ( array $doc ) use ( $form ): string {
			if ( 'upstream' === $form ) {
				$github = ! empty( $doc['source_url'] ) ? Sources\parse( (string) $doc['source_url'] ) : null;
				return $github ? $github['path'] : '';
			}
			return (string) ( $doc['_source']['path'] ?? '' );
		};

		$link = static fn( string $href ): ?string => Drift\link_back(
			$href,
			$own,
			static fn( string $route ): ?string => isset( $by_url[ $route ] ) ? $target_of( $by_url[ $route ] ) : null
		);

		$image = static function ( string $src ) use ( $own, $form, $upstream, &$warnings ): ?string {
			$repo_path = self::image_repo_path( $src );
			if ( null === $repo_path ) {
				if ( preg_match( '#/wp-content/uploads/#', $src ) ) {
					$warnings[] = "Image {$src} is not in the repository (uploaded in WordPress?); its URL was kept. Add the file to " . self::UPSTREAM_IMAGES . ' by hand.';
				}
				return null;
			}
			if ( 'upstream' === $form ) {
				$name = basename( $repo_path );
				if ( '' !== $upstream && preg_match( '#' . preg_quote( self::OLD_UPLOADS, '#' ) . '[^\s)"\']*/' . preg_quote( $name, '#' ) . '#', $upstream, $m ) ) {
					return $m[0]; // The URL form the upstream page already uses for this screenshot.
				}
				$repo_path = self::UPSTREAM_IMAGES . '/' . $name;
			}
			return Drift\image_back( $src, $own, static fn(): string => $repo_path );
		};

		return array(
			'link'  => $link,
			'image' => $image,
		);
	}

	/**
	 * The repository path (relative to the staged content root, `images/<name>`) of an attachment URL,
	 * from the `_wp_docs_image_source` the importer recorded.
	 *
	 * @param string $src Attachment URL (any host) or root-relative path.
	 * @return string|null Null when the URL is not a docs image the import knows.
	 */
	protected static function image_repo_path( $src ) {
		static $cache = array();
		if ( array_key_exists( $src, $cache ) ) {
			return $cache[ $src ];
		}
		$path    = (string) wp_parse_url( $src, PHP_URL_PATH );
		$uploads = wp_upload_dir();
		$base    = rtrim( (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_PATH ), '/' ) . '/';
		if ( '' === $path || 0 !== strpos( $path, $base ) ) {
			return $cache[ $src ] = null; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
		}
		$file = rawurldecode( substr( $path, strlen( $base ) ) );
		$ids  = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! $ids ) {
			return $cache[ $src ] = null; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
		}
		$source = Sources\parse( (string) get_post_meta( (int) $ids[0], '_wp_docs_image_source', true ) );
		return $cache[ $src ] = $source ? $source['path'] : null; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
	}

	/*
	 * ---------------------------------------------------------------------------------------------
	 * Export.
	 * ---------------------------------------------------------------------------------------------
	 */

	/**
	 * The upstream checkout the staging script leaves next to the manifest, if any.
	 *
	 * @return string Directory, or empty.
	 */
	public static function default_upstream_dir() {
		$dir = Local_Sources::content_path() . '/.cache/studio';
		return is_dir( $dir . '/docs' ) ? $dir : '';
	}

	/**
	 * The commit a checkout is at, when it is a git checkout.
	 *
	 * @param string $dir Directory.
	 * @return string Full SHA, or empty.
	 */
	public static function checkout_commit( $dir ) {
		if ( '' === (string) $dir || ! is_dir( $dir . '/.git' ) ) {
			return '';
		}
		$sha = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $dir ) . ' rev-parse HEAD 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		return preg_match( '/^[0-9a-f]{40}$/', $sha ) ? $sha : '';
	}

	/**
	 * Write a page's reconciled Markdown to a directory.
	 *
	 * With an upstream checkout, the file is written at its upstream path with the editor's change
	 * replayed onto the upstream text. Without one, the staged file with the merged body is written
	 * under `content/`.
	 *
	 * @param WP_Post $post         Page.
	 * @param string  $dir          Output directory.
	 * @param string  $upstream_dir Checkout of Automattic/studio (its root), or empty.
	 * @return array|WP_Error { file, path, form: upstream|staged, warnings, verify, changed, reconcile }
	 */
	public static function export( WP_Post $post, $dir, $upstream_dir = '' ) {
		$reconciled = self::reconcile( $post );
		if ( is_wp_error( $reconciled ) ) {
			return $reconciled;
		}
		$info     = $reconciled['info'];
		$warnings = $reconciled['warnings'];
		$changed  = $info['changed'];

		$github        = Sources\parse( (string) get_post_meta( $post->ID, '_wp_docs_source_url', true ) );
		$upstream_file = ( '' !== $upstream_dir && $github ) ? rtrim( $upstream_dir, '/' ) . '/' . $github['path'] : '';

		if ( '' !== $upstream_file && is_readable( $upstream_file ) ) {
			$upstream_raw = (string) file_get_contents( $upstream_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$up_parts     = Reverse\split_file( $upstream_raw );
			$resolvers    = self::resolvers( $post, 'upstream', $upstream_raw, $warnings );
			$convert      = static fn( string $markdown ) => Pipeline\convert_body( $markdown, $reconciled['options'] );
			$merge        = Reverse\merge( $reconciled['baseline']['body'], $reconciled['live'], $convert, $resolvers, $reconciled['prepared'] );
			$body         = Upstream\apply( rtrim( $up_parts['body'], "\n" ), $reconciled['baseline']['body'], $merge['ops'], $warnings );

			if ( in_array( 'title', $changed, true ) ) {
				$title = trim( (string) $post->post_title );
				if ( Reverse\has_front_matter_field( $up_parts['front_matter'], 'title' ) ) {
					$up_parts['front_matter'] = Reverse\set_front_matter_field( $up_parts['front_matter'], 'title', $title );
				} elseif ( '' !== $up_parts['title'] ) {
					$up_parts['title'] = $title;
				} else {
					$warnings[] = "The title changed to \"{$title}\" but the upstream file has neither a front matter title nor a `# ` line; set it by hand.";
				}
			}
			if ( in_array( 'description', $changed, true ) ) {
				$description = trim( (string) $post->post_excerpt );
				if ( Reverse\has_front_matter_field( $up_parts['front_matter'], 'description' ) ) {
					$up_parts['front_matter'] = Reverse\set_front_matter_field( $up_parts['front_matter'], 'description', $description );
				} else {
					$replaced = Upstream\replace_description( $body, self::imported_description( $post ), $description );
					if ( null === $replaced ) {
						$warnings[] = "The description changed to \"{$description}\" but the upstream page derives its description from its first paragraph, which could not be found; change it by hand.";
					} else {
						$body = $replaced;
					}
				}
			}
			$raw  = Reverse\join_file( $up_parts, $body );
			$path = $github['path'];
			$form = 'upstream';
		} else {
			if ( '' === $upstream_dir ) {
				$warnings[] = 'No checkout of Automattic/studio was found; the staged form of the page was written (scripts/reconcile-docs.mjs checks the repository out and exports again).';
			} elseif ( '' !== $upstream_file ) {
				$warnings[] = "The page's upstream file {$upstream_file} is not in the checkout; the staged form was written.";
			}
			$parts = $reconciled['baseline']['parts'];
			if ( in_array( 'title', $changed, true ) ) {
				$parts['front_matter'] = Reverse\set_front_matter_field( $parts['front_matter'], 'title', trim( (string) $post->post_title ) );
			}
			if ( in_array( 'description', $changed, true ) ) {
				$parts['front_matter'] = Reverse\set_front_matter_field( $parts['front_matter'], 'description', trim( (string) $post->post_excerpt ) );
			}
			$raw    = Reverse\join_file( $parts, $reconciled['markdown'] );
			$source = Sources\parse( (string) get_post_meta( $post->ID, 'wporg_markdown_source', true ) );
			$path   = $source ? $source['path'] : 'content/' . $post->post_name . '.md';
			$form   = 'staged';
		}

		$file = rtrim( $dir, '/' ) . '/' . $path;
		if ( ! wp_mkdir_p( dirname( $file ) ) || false === file_put_contents( $file, $raw ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'wp_docs_write', "Could not write {$file}." );
		}

		return array(
			'file'      => $file,
			'path'      => $path,
			'form'      => $form,
			'warnings'  => array_values( array_unique( $warnings ) ),
			'verify'    => $reconciled['verify'],
			'changed'   => $changed,
			'reconcile' => $reconciled,
		);
	}

	/**
	 * A unified diff of two texts (through the `diff` command).
	 *
	 * @param string $a       Old text.
	 * @param string $b       New text.
	 * @param string $label_a Old label.
	 * @param string $label_b New label.
	 * @return string Empty when the texts are equal.
	 */
	public static function unified_diff( $a, $b, $label_a, $label_b ) {
		if ( $a === $b ) {
			return '';
		}
		$file_a = wp_tempnam( 'wp-docs-a' );
		$file_b = wp_tempnam( 'wp-docs-b' );
		file_put_contents( $file_a, rtrim( $a, "\n" ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file_b, rtrim( $b, "\n" ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$diff = (string) shell_exec( 'diff -u --label ' . escapeshellarg( $label_a ) . ' --label ' . escapeshellarg( $label_b ) . ' ' . escapeshellarg( $file_a ) . ' ' . escapeshellarg( $file_b ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		wp_delete_file( $file_a );
		wp_delete_file( $file_b );
		return $diff;
	}
}
