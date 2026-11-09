<?php
/**
 * Unit tests for the pure modules: front matter, alerts, sources and link resolution, manifests,
 * the block scanner, the reverse sync (chunks, Markdown out, merge, upstream replay) and the drift
 * hash. Plain PHP: `php tests/run-unit.php`.
 *
 * @package WP_Docs
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

use WP_Docs\Manifest;
use WP_Docs\Transforms\Agent_Markdown;
use WP_Docs\Transforms\Anchors;
use WP_Docs\Sources;
use WP_Docs\Transforms\Alerts;
use WP_Docs\Transforms\Blocks;
use WP_Docs\Transforms\Front_Matter;
use WP_Docs\Transforms\Links;
use WP_Docs\Transforms\Images;

$GLOBALS['wdt_passed'] = 0;
$GLOBALS['wdt_failed'] = 0;

function check( string $name, $actual, $expected ) {
	if ( $actual === $expected ) {
		++$GLOBALS['wdt_passed'];
		return;
	}
	++$GLOBALS['wdt_failed'];
	echo "FAIL {$name}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

/*
 * (b) Front matter.
 */
$fm = "---\nid: install\ntitle: \"Install: the basics\"\naudience: [user, developer]\nredirect_from:\n  - /documentation/article/install/\n  - https://developer.wordpress.org/old/\napplies_to: []\ngenerated: {by: scripts/gen.py, from: block.json}\ndescription: >\n  Two lines\n  folded.\nnote: \"a value with --- inside it\" # trailing comment\n---\n# Install\n\nBody.\n";
list( $data, $body, $present ) = Front_Matter\split( $fm );
check( 'fm present', $present, true );
check( 'fm title quoted with colon', $data['title'], 'Install: the basics' );
check( 'fm flow list', $data['audience'], array( 'user', 'developer' ) );
check( 'fm block list', $data['redirect_from'], array( '/documentation/article/install/', 'https://developer.wordpress.org/old/' ) );
check( 'fm empty flow list', $data['applies_to'], array() );
check( 'fm flow map', $data['generated'], array( 'by' => 'scripts/gen.py', 'from' => 'block.json' ) );
check( 'fm folded scalar', $data['description'], 'Two lines folded.' );
check( 'fm --- inside value is safe', $data['note'], 'a value with --- inside it' );
check( 'fm body starts at H1', $body, "# Install\n\nBody.\n" );

list( $data, $body ) = Front_Matter\split( "\xEF\xBB\xBF---\ntitle: x\n---\n# X\n" );
check( 'fm BOM tolerated', array( $data, $body ), array( array( 'title' => 'x' ), "# X\n" ) );

list( $data, $body ) = Front_Matter\split( "---\r\ntitle: crlf\r\n---\r\n# T\r\n" );
check( 'fm CRLF', array( $data, $body ), array( array( 'title' => 'crlf' ), "# T\r\n" ) );

list( $data, $body, $present ) = Front_Matter\split( "# No front matter\n\n---\n\ntext\n" );
check( 'fm absent leaves thematic break', array( $present, $body ), array( false, "# No front matter\n\n---\n\ntext\n" ) );

list( , $body, $present ) = Front_Matter\split( "---\n\nJust a rule and no closing fence\n" );
check( 'fm unclosed is not front matter', $present, false );

$n = Front_Matter\normalize( array( 'redirect_from' => '/old/', 'title' => ' T ', 'type' => 'guide', 'status' => 'placeholder', 'updated' => '2026-10-20', 'audience' => 'user' ) );
check( 'fm normalize scalar redirect_from to list', $n['redirect_from'], array( '/old/' ) );
check( 'fm normalize title trimmed', $n['title'], 'T' );
check( 'fm normalize type, status, updated', array( $n['type'], $n['status'], $n['updated'] ), array( 'guide', 'placeholder', '2026-10-20' ) );
check( 'fm normalize drops unknown fields', isset( $n['audience'] ), false );

/*
 * (a) Alerts.
 */
$md  = "> [!NOTE]\n> One.\n\n> [!WARNING]\n> Two.\n";
$out = Alerts\markdown_to_placeholders( $md );
check( 'alerts: consecutive alerts are two placeholders', substr_count( $out, 'data-wp-docs-alert=' ), 2 );
check( 'alerts: types', array( strpos( $out, '"note"' ) !== false, strpos( $out, '"warning"' ) !== false ), array( true, true ) );

$md  = "```\n> [!NOTE]\n> fenced\n```\n";
check( 'alerts: untouched inside fences', Alerts\markdown_to_placeholders( $md ), $md );

$md  = "> [!TIP]\n> First line\nlazy continuation\n\nAfter.\n";
$out = Alerts\markdown_to_placeholders( $md );
check( 'alerts: lazy continuation stays inside', (bool) preg_match( '/First line\nlazy continuation\n\n<\/div>/', $out ), true );
check( 'alerts: following paragraph outside', (bool) preg_match( '/<\/div>\n\n\nAfter\./', $out ), true );

$md = "> [!NOTE] inline text is not an alert marker\n";
check( 'alerts: marker must be alone on its line', Alerts\markdown_to_placeholders( $md ), $md );

$md = "> Plain quote\n";
check( 'alerts: plain quote untouched', Alerts\markdown_to_placeholders( $md ), $md );

$html = '<div data-wp-docs-alert="caution"><div>nested</div>Text</div>';
check(
	'alerts: classic notice format, nested div balanced',
	trim( Alerts\render_classic( $html, 'notice' ) ),
	"<div class=\"wp-block-wporg-notice is-warning-notice\">\n<div class=\"wp-block-wporg-notice__icon\"></div>\n<div class=\"wp-block-wporg-notice__content\">\n\n<div>nested</div>Text\n\n</div></div>"
);
check( 'alerts: classic shortcode format', trim( Alerts\render_classic( '<div data-wp-docs-alert="tip">Hi</div>', 'shortcode' ) ), "[tip]\n\nHi\n\n[/tip]" );
check( 'alerts: classic callout format', trim( Alerts\render_classic( '<div data-wp-docs-alert="important">Hi</div>', 'callout' ) ), "<div class=\"callout callout-alert\">\n\nHi\n\n</div>" );
check( 'alerts: classic github format', trim( Alerts\render_classic( '<div data-wp-docs-alert="note">Hi</div>', 'github' ) ), "<div class=\"markdown-alert markdown-alert-note\">\n<p class=\"markdown-alert-title\">Note</p>\n\nHi\n\n</div>" );

$blocks = Blocks\convert( '<div data-wp-docs-alert="tip"><p>A</p></div>', array( 'callout_block' => 'wporg/callout' ) );
check( 'alerts: wporg/callout block with inner blocks', $blocks, "<!-- wp:wporg/callout {\"type\":\"tip\"} -->\n<div class=\"callout callout-tip\"><!-- wp:paragraph -->\n<p>A</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:wporg/callout -->" );
$blocks = Blocks\convert( '<div data-wp-docs-alert="note"><p>A</p></div>', array( 'callout_block' => 'core/group' ) );
check( 'alerts: core/group fallback', 0 === strpos( $blocks, '<!-- wp:group {"className":"callout callout-info","layout":{"type":"constrained"}} -->' ), true );

/*
 * Sources.
 */
$s = Sources\parse( 'https://github.com/WordPress/gutenberg/blob/trunk/docs/how-to.md#x' );
check( 'sources: blob', array( $s['kind'], $s['owner'], $s['repo'], $s['ref'], $s['path'], $s['fragment'] ), array( 'github', 'WordPress', 'gutenberg', 'trunk', 'docs/how-to.md', 'x' ) );
check( 'sources: raw url', Sources\fetch_url( $s ), 'https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/how-to.md' );
check( 'sources: edit url', Sources\edit_url( $s ), 'https://github.com/WordPress/gutenberg/edit/trunk/docs/how-to.md' );
$r = Sources\parse( 'https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/how-to.md' );
check( 'sources: raw parses to same key', Sources\match_key( $r ), Sources\match_key( $s ) );
$l = Sources\parse( 'content/get-started/README.md' );
check( 'sources: repo-relative path is local', array( $l['kind'], $l['path'] ), array( 'local', 'content/get-started/README.md' ) );
check( 'sources: local fetch url', Sources\fetch_url( $l ), 'https://wp-docs.local/repo/content/get-started/README.md' );
check( 'sources: local url round trip', Sources\parse( Sources\fetch_url( $l ) )['path'], 'content/get-started/README.md' );
check( 'sources: other host is null', Sources\parse( 'https://example.com/a.md' ), null );
$aliases = array( 'ndiego/wordpress-documentation' => array( 'prefix' => '', 'ref' => 'trunk' ), 'WordPress/gutenberg' => array( 'prefix' => '.spokes/gutenberg/', 'ref' => 'trunk' ) );
check( 'sources: local to github via alias', Sources\blob_url( Sources\to_github( $l, $aliases ) ), 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/get-started/README.md' );
check( 'sources: spoke prefix wins', Sources\blob_url( Sources\to_github( Sources\parse( '.spokes/gutenberg/docs/a.md' ), $aliases ) ), 'https://github.com/WordPress/gutenberg/blob/trunk/docs/a.md' );
check( 'sources: aliased github key equals local key', Sources\match_key( $s, $aliases ), 'local:.spokes/gutenberg/docs/how-to.md' );
check( 'sources: normalize climbing above root', Sources\normalize_path( '../x.md' ), null );
$issue = Sources\issue_url( $s, 'https://wordpress.org/docs/x/', 'How to', null );
check( 'sources: issue url carries page and path', strpos( $issue, 'https://github.com/WordPress/gutenberg/issues/new?title=Docs%3A%20How%20to&body=Page%3A%20https%3A%2F%2Fwordpress.org%2Fdocs%2Fx%2F' ) === 0 && strpos( $issue, 'docs%2Fhow-to.md' ) !== false, true );

/*
 * (d) Link resolution.
 */
$base = Sources\parse( 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/blocks/image.md' );
$cases = array(
	'./gallery.md'          => 'content/blocks/gallery.md',
	'gallery.md#caption'    => 'content/blocks/gallery.md',
	'../themes/README.md'   => 'content/themes/README.md',
	'/content/apis/rest.md' => 'content/apis/rest.md',
	'../themes/'            => 'content/themes/',
);
foreach ( $cases as $href => $path ) {
	check( "links: resolve {$href}", Sources\resolve( $base, $href )['path'], $path );
}
check( 'links: root path resolves from the repository root', Sources\resolve( Sources\parse( 'docs/write/a/b.md' ), '/docs/c.md' )['path'], 'docs/c.md' );
check( 'links: anchor-only is not resolved', Sources\resolve( $base, '#x' ), null );
check( 'links: external is not resolved', Sources\resolve( $base, 'https://make.wordpress.org/' ), null );
check( 'links: mailto is not resolved', Sources\resolve( $base, 'mailto:a@b.c' ), null );
check( 'links: fragment kept', Sources\resolve( $base, 'gallery.md#caption' )['fragment'], 'caption' );

$map    = array(
	'content/blocks/gallery.md' => 'https://wordpress.org/docs/blocks/gallery/',
	'content/themes/README.md'  => 'https://wordpress.org/docs/themes/',
);
$lookup = static fn( $t ) => $map[ $t['path'] ] ?? null;
$unres  = array();
$html   = Links\rewrite(
	'<p><a href="gallery.md#caption">g</a> <a href="../themes/">t</a> <a href="nope.md">n</a> <a href="../examples/x.php">f</a> <a href="#local">l</a></p><pre><code>&lt;a href="gallery.md"&gt;</code></pre>',
	$base,
	$lookup,
	$unres
);
check(
	'links: rewrite',
	$html,
	'<p><a href="https://wordpress.org/docs/blocks/gallery/#caption">g</a> <a href="https://wordpress.org/docs/themes/">t</a> <a href="nope.md">n</a> <a href="https://github.com/ndiego/wordpress-documentation/blob/trunk/content/examples/x.php">f</a> <a href="#local">l</a></p><pre><code>&lt;a href="gallery.md"&gt;</code></pre>'
);
check( 'links: unresolved reported', $unres, array( 'nope.md' ) );

/*
 * (c) Images (pure rewrite).
 */
$failed = array();
$html   = Images\rewrite(
	'<p><img src="images/a.webp" alt="A"> <img src="https://s.w.org/x.png" alt="remote"> <img src="data:image/png;base64,xx" alt=""> <img src="../../../../escape.png" alt=""></p>',
	$base,
	static fn( $t, $alt ) => array( 'url' => 'https://example.org/files/' . basename( $t['path'] ), 'id' => 7 ),
	$failed
);
check( 'images: relative rewritten, absolute untouched', $html, '<p><img class="wp-image-7" src="https://example.org/files/a.webp" alt="A"> <img src="https://s.w.org/x.png" alt="remote"> <img src="data:image/png;base64,xx" alt=""> <img src="../../../../escape.png" alt=""></p>' );
check( 'images: unresolvable reported', $failed, array( '../../../../escape.png' ) );

/*
 * Manifests.
 */
$result = Manifest\normalize(
	array(
		'themes' => array(
			'themes'           => array( 'title' => 'Themes', 'slug' => 'themes', 'parent' => null, 'order' => 10, 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/themes/README.md', 'id' => 'themes', 'section' => 'themes' ),
			'block-themes'     => array( 'title' => 'Block themes', 'slug' => 'block-themes', 'parent' => null, 'order' => 20, 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/themes/block-themes.md', 'id' => 'block-themes', 'section' => 'themes' ),
			'theme-json'       => array( 'title' => 'theme.json', 'slug' => 'theme-json', 'parent' => 'block-themes', 'order' => 10, 'markdown_source' => 'content/themes/theme-json.md', 'source_url' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/themes/theme-json.md', 'id' => 'theme-json', 'section' => 'themes' ),
			'orphan'           => array( 'title' => 'Orphan', 'slug' => 'orphan', 'parent' => 'nowhere', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/x.md', 'section' => 'themes' ),
			'Bad_Slug'         => array( 'title' => 'Bad', 'slug' => 'Bad_Slug', 'parent' => null, 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/y.md' ),
		),
	)
);
check( 'manifest: keys become paths, section mapping applied', array_keys( $result['entries'] ), array( 'themes', 'themes/block-themes', 'themes/block-themes/theme-json' ) );
check( 'manifest: parent remapped to path', $result['entries']['themes/block-themes/theme-json']['parent'], 'themes/block-themes' );
check( 'manifest: local source canonicalised', $result['entries']['themes/block-themes/theme-json']['markdown_source'], 'https://wp-docs.local/repo/content/themes/theme-json.md' );
check( 'manifest: source_url kept for provenance', Sources\blob_url( $result['entries']['themes/block-themes/theme-json']['_github'] ), 'https://github.com/ndiego/wordpress-documentation/blob/trunk/content/themes/theme-json.md' );
check( 'manifest: two warnings (orphan, bad slug)', count( $result['warnings'] ), 2 );
$smap = Manifest\source_map( $result['entries'], array( 'ndiego/wordpress-documentation' => array( 'prefix' => '', 'ref' => 'trunk' ) ) );
check( 'manifest: local entry matchable by local path', $smap['local:content/themes/theme-json.md'] ?? null, 'themes/block-themes/theme-json' );
$lookup = Manifest\lookup_callback( $result['entries'], $smap, array( 'ndiego/wordpress-documentation' => array( 'prefix' => '', 'ref' => 'trunk' ) ), 'https://wordpress.org/docs/' );
check( 'manifest: blob link to local entry resolves', $lookup( Sources\parse( 'https://github.com/ndiego/wordpress-documentation/blob/6.9-branch/content/themes/theme-json.md' ) ), 'https://wordpress.org/docs/themes/block-themes/theme-json/' );

$dupe = Manifest\normalize(
	array(
		'a' => array( 'a' => array( 'slug' => 'a', 'markdown_source' => 'content/a/README.md' ), 'a/overview' => array( 'slug' => 'overview', 'parent' => 'a', 'markdown_source' => 'content/a/overview.md' ) ),
		'b' => array( 'b' => array( 'slug' => 'b', 'markdown_source' => 'content/b/README.md' ), 'b/overview' => array( 'slug' => 'overview', 'parent' => 'b', 'markdown_source' => 'content/b/overview.md' ) ),
	)
);
check( 'manifest: duplicate slug across sections warned', count( $dupe['warnings'] ), 1 );
check( 'manifest: id defaults to path', $dupe['entries']['b/overview']['id'], 'b/overview' );

$designed = Manifest\normalize( array( 'm' => array(
	'blocks'       => array( 'slug' => 'blocks', 'path' => 'blocks/', 'markdown_source' => 'content/blocks/README.md', 'id' => 'blocks' ),
	'blocks/image' => array( 'slug' => 'image', 'parent' => 'blocks', 'path' => 'blocks/media/image/', 'markdown_source' => 'content/blocks/image.md', 'id' => 'core-image' ),
	'other/image'  => array( 'slug' => 'image', 'parent' => 'blocks', 'markdown_source' => 'content/blocks/image2.md', 'id' => 'dup' ),
) ) );
check( 'manifest: designed path kept', $designed['entries']['blocks/image']['_path'], 'blocks/media/image' );
check( 'manifest: same parent and slug collision skipped', array( count( $designed['entries'] ), count( $designed['warnings'] ) ), array( 2, 2 ) );
$dl = Manifest\lookup_callback( $designed['entries'], Manifest\source_map( $designed['entries'] ), array(), 'https://wordpress.org/docs/' );
check( 'manifest: link lookup uses the designed path', $dl( Sources\parse( 'content/blocks/image.md' ) ), 'https://wordpress.org/docs/blocks/media/image/' );

$cross = Manifest\normalize( array( 'spoke' => array( 'x' => array( 'slug' => 'x', 'parent' => 'blocks', 'markdown_source' => 'content/x.md' ) ) ), array( 'blocks' ) );
check( 'manifest: parent from an existing post', array_keys( $cross['entries'] ), array( 'blocks/x' ) );


/*
 * Heading anchors (GitHub's slugs).
 */
check( 'anchors: slug', Anchors\slug( 'What’s new? (6.9) & more_stuff' ), 'whats-new-69--more_stuff' );
check( 'anchors: ids added, repeats numbered, existing kept', Anchors\add_ids( '<h2>Add a title</h2><h3 class="x">Add a title</h3><h2 id="own">Own</h2><p>&lt;h2&gt;not a heading&lt;/h2&gt;</p>' ), '<h2 id="add-a-title">Add a title</h2><h3 id="add-a-title-1" class="x">Add a title</h3><h2 id="own">Own</h2><p>&lt;h2&gt;not a heading&lt;/h2&gt;</p>' );
check( 'anchors: headings listed with anchors', Anchors\headings( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"a-b\">A <code>b</code></h2>\n<!-- /wp:heading --><h4 id=\"skip\">Skip</h4><h3 id=\"c\">C &amp; D</h3>" ), array( array( 'level' => 2, 'text' => 'A b', 'anchor' => 'a-b' ), array( 'level' => 3, 'text' => 'C & D', 'anchor' => 'c' ) ) );

/*
 * The manifest with the home (`index`, path "").
 */
$home_manifest = array(
	'index'               => array( 'title' => 'WordPress documentation', 'slug' => 'index', 'parent' => null, 'order' => 0, 'path' => '', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/README.md', 'type' => 'guide', 'section' => '' ),
	'write'               => array( 'title' => 'Write and edit', 'slug' => 'write', 'parent' => null, 'order' => 20, 'path' => 'write/', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/write/README.md', 'section' => 'write', 'type' => 'guide' ),
	'write/create-a-post' => array( 'title' => 'Create a post', 'slug' => 'create-a-post', 'parent' => 'write', 'order' => 10, 'path' => 'write/create-a-post/', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/write/create-a-post.md', 'section' => 'write', 'type' => 'tutorial', 'status' => '', 'description' => 'Write a post.', 'updated' => '2026-10-20', 'redirect_from' => array( '/documentation/article/write-posts-classic-editor/' ) ),
	'get-started'         => array( 'title' => 'Get started', 'slug' => 'get-started', 'parent' => null, 'order' => 10, 'path' => 'get-started/', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/get-started/README.md', 'section' => 'get-started' ),
);
$hm = Manifest\normalize( array( 'manifest' => $home_manifest ) );
check( 'manifest index: no warnings', $hm['warnings'], array() );
check( 'manifest index: home kept with an empty path', array( $hm['entries']['index']['_path'], $hm['entries']['index']['parent'], $hm['entries']['index']['id'] ), array( '', null, 'index' ) );
check( 'manifest index: top level first, then by order', array_keys( $hm['entries'] ), array( 'index', 'get-started', 'write', 'write/create-a-post' ) );
check( 'manifest index: extension fields kept', array( $hm['entries']['write/create-a-post']['type'], $hm['entries']['write/create-a-post']['updated'], $hm['entries']['write/create-a-post']['_path'] ), array( 'tutorial', '2026-10-20', 'write/create-a-post' ) );
$hl = Manifest\lookup_callback( $hm['entries'], Manifest\source_map( $hm['entries'] ), array(), 'https://example.org/docs/' );
check( 'manifest index: link to the home README is /docs/', $hl( Sources\parse( 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/README.md' ) ), 'https://example.org/docs/' );
check( 'manifest index: link to a page', $hl( Sources\parse( 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/write/create-a-post.md' ) ), 'https://example.org/docs/write/create-a-post/' );
$two_homes = Manifest\normalize( array( 'manifest' => array( 'index' => $home_manifest['index'], 'other' => array_merge( $home_manifest['index'], array( 'slug' => 'other', 'markdown_source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/other.md' ) ) ) ) );
check( 'manifest index: a second empty path is skipped', array( array_keys( $two_homes['entries'] ), count( $two_homes['warnings'] ) ), array( array( 'index' ), 1 ) );

/*
 * Markdown for agents.
 */
$page_base = Sources\parse( 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/write/create-a-post.md' );
$agent_md  = Agent_Markdown\build(
	"# Create a post\n\nSee [the editor](editor/README.md#toolbar), [home](../README.md), [missing](nope.md), [example](../examples/a.php), [WordPress](https://wordpress.org/) and [top](#top).\n\n![The inserter](../assets/screenshots/inserter.webp \"Inserter\")\n![Remote](https://s.w.org/x.png)\n\n```md\n[keep](editor/README.md)\n```\n\nInline `[keep](editor/README.md)` code.\n\n<a href=\"../get-started/README.md\">raw</a> <img src=\"images/a.png\" alt=\"A\">\n\n[ref]: ../get-started/README.md\n",
	array( 'title' => 'Create a post', 'description' => 'Write your first post: add blocks, publish.', 'type' => 'tutorial', 'url' => 'https://example.org/docs/write/create-a-post/', 'source' => 'https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/write/create-a-post.md' ),
	Agent_Markdown\link_resolver(
		$page_base,
		static fn( $t ) => array( 'docs/write/editor/README.md' => 'https://example.org/docs/write/editor/', 'docs/README.md' => 'https://example.org/docs/', 'docs/get-started/README.md' => 'https://example.org/docs/get-started/' )[ $t['path'] ] ?? null
	),
	Agent_Markdown\image_resolver( $page_base, static fn( $t, $alt ) => array( 'url' => 'https://example.org/wp-content/uploads/' . basename( $t['path'] ), 'id' => 5 ) )
);
$agent_expected = "---\ntitle: \"Create a post\"\ndescription: \"Write your first post: add blocks, publish.\"\n---\n\n# Create a post\n\nSee [the editor](https://example.org/docs/write/editor/#toolbar), [home](https://example.org/docs/), [missing](nope.md), [example](https://github.com/ndiego/wordpress-documentation/blob/trunk/docs/examples/a.php), [WordPress](https://wordpress.org/) and [top](#top).\n\n![The inserter](https://example.org/wp-content/uploads/inserter.webp \"Inserter\")\n![Remote](https://s.w.org/x.png)\n\n```md\n[keep](editor/README.md)\n```\n\nInline `[keep](editor/README.md)` code.\n\n<a href=\"https://example.org/docs/get-started/\">raw</a> <img src=\"https://example.org/wp-content/uploads/a.png\" alt=\"A\">\n\n[ref]: https://example.org/docs/get-started/\n";
check( 'agent markdown: front matter, absolute links and images, code untouched', $agent_md, $agent_expected );
check( 'agent markdown: code span inside link text, link inside code span', Agent_Markdown\rewrite_line( 'a [`edit_posts`](../x.md#e) and `[keep](y.md)`', static fn( $h ) => 'ABS', static fn() => null ), 'a [`edit_posts`](ABS) and `[keep](y.md)`' );
check( 'agent markdown: sidebar label only when it differs from the title', Agent_Markdown\build( "Body.\n", array( 'title' => 'T', 'description' => '', 'label' => 'Short' ), static fn() => null, static fn() => null ), "---\ntitle: \"T\"\nsidebar:\n  label: \"Short\"\n---\n\nBody.\n" );
check( 'agent markdown: no label line when it equals the title', Agent_Markdown\build( "Body.\n", array( 'title' => 'T', 'label' => 'T' ), static fn() => null, static fn() => null ), "---\ntitle: \"T\"\n---\n\nBody.\n" );

/*
 * (e) Redirects.
 */
$r = Manifest\split_redirects( array( '/documentation/article/use-images/', 'https://developer.wordpress.org/themes/getting-started/', 'https://wordpress.org/support/article/x/?a=1', '' ), 'wordpress.org' );
check( 'redirects: same-host paths', $r['paths'], array( '/documentation/article/use-images', '/support/article/x' ) );
check( 'redirects: other hosts kept apart', $r['external'], array( 'https://developer.wordpress.org/themes/getting-started/' ) );
check( 'redirects: normalise', Manifest\normalize_redirect_path( '/Docs/Old%20Page/?x#y' ), '/docs/old page' );

/*
 * (g) Block scanner.
 */
check( 'blocks: no freeform', Blocks\find_freeform( "<!-- wp:paragraph -->\n<p>a</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:separator /-->" ), array() );
check( 'blocks: freeform found', Blocks\find_freeform( "text\n<!-- wp:paragraph -->\n<p>a</p>\n<!-- /wp:paragraph -->tail" ), array( 'text', 'tail' ) );
check( 'blocks: bare text wrapped', Blocks\convert( "Bare <em>text</em>\n<h2>H</h2>" ), "<!-- wp:paragraph -->\n<p>Bare <em>text</em></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">H</h2>\n<!-- /wp:heading -->" );
check( 'blocks: attribute escaping like core', Blocks\serialize_block( 'core/paragraph', array( 'className' => 'a--b<c>&"' ), '<p>x</p>' ), "<!-- wp:paragraph {\"className\":\"a\\u002d\\u002db\\u003cc\\u003e\\u0026\\u0022\"} -->\n<p>x</p>\n<!-- /wp:paragraph -->" );
check( 'blocks: ordered list start', strpos( Blocks\convert( '<ol start="3"><li>c</li></ol>' ), '<!-- wp:list {"ordered":true,"start":3} -->' . "\n" . '<ol class="wp-block-list" start="3">' ) === 0, true );
check( 'blocks: figure with caption', Blocks\convert( '<figure><img src="a.png" alt="A"><figcaption>Cap</figcaption></figure>' ), "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"a.png\" alt=\"A\"/><figcaption class=\"wp-element-caption\">Cap</figcaption></figure>\n<!-- /wp:image -->" );
check( 'blocks: code block keeps the language, escapes brackets', Blocks\convert( '<pre><code class="language-bash">echo [x] &lt;y&gt;</code></pre>' ), "<!-- wp:studio-docs/code {\"language\":\"bash\"} -->\n<pre class=\"wp-block-studio-docs-code\"><code>echo &#91;x] &lt;y&gt;</code></pre>\n<!-- /wp:studio-docs/code -->" );
check( 'blocks: table is a core table', strpos( Blocks\convert( '<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>' ), "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>A</th></tr></thead>" ) === 0, true );
check( 'blocks: pre without code is preformatted', Blocks\convert( '<pre>x</pre>' ), "<!-- wp:preformatted -->\n<pre class=\"wp-block-preformatted\">x</pre>\n<!-- /wp:preformatted -->" );
check( 'blocks: empty input', Blocks\convert( '' ), '' );

$parser = new WP_Block_Parser();
$parsed = $parser->parse( Blocks\convert( "<blockquote><p>q</p></blockquote>\n<details><summary>S</summary><p>d</p></details>" ) );
check( 'blocks: core parser sees quote and details with inner paragraphs', array( $parsed[0]['blockName'], $parsed[0]['innerBlocks'][0]['blockName'], $parsed[2]['blockName'], $parsed[2]['innerBlocks'][0]['blockName'] ), array( 'core/quote', 'core/paragraph', 'core/details', 'core/paragraph' ) );

/*
 * (c) SVG safety check (fixtures in tests/fixtures/svg/).
 */
foreach ( glob( __DIR__ . '/fixtures/svg/*.svg' ) as $svg_file ) {
	$svg_name = basename( $svg_file, '.svg' );
	check( "svg: {$svg_name}", Images\svg_is_safe( (string) file_get_contents( $svg_file ) ), 0 !== strpos( $svg_name, 'unsafe-' ) );
}
check( 'svg: the content repository diagram passes', Images\svg_is_safe( '<svg xmlns="http://www.w3.org/2000/svg"><g fill="none"><path d="M0 0h1"/></g></svg>' ), true );

/*
 * Reverse sync: chunks, Markdown out, merge, verify.
 */
use WP_Docs\Transforms\Chunks;
use WP_Docs\Transforms\Markdown_Out;
use WP_Docs\Transforms\Reverse;
use WP_Docs\Transforms\Upstream;
use WP_Docs\Drift;

$body   = "Para one.\n\n## Heading\n\n- a\n- b\n\n  - nested\n\n> [!NOTE]\n> Careful.\n\n> quoted\n\n> still quoted\n\n```php\n<?php echo 1;\n```\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n<!-- a comment -->\n\n<details>\n<summary>More</summary>\n\nInside.\n\n</details>\n\n---\n\nLast.";
$chunks = Chunks\chunk( $body );
check( 'chunk kinds', array_column( $chunks, 'kind' ), array( 'paragraph', 'heading', 'list', 'alert', 'quote', 'fence', 'table', 'comment', 'details', 'hr', 'paragraph' ) );
check( 'chunk list text', $chunks[2]['text'], "- a\n- b\n\n  - nested" );
check( 'chunk quote merges', $chunks[4]['text'], "> quoted\n\n> still quoted" );
check( 'chunk gap', $chunks[1]['gap'], "\n" );
check( 'chunk adjacent heading and paragraph', array_column( Chunks\chunk( "# Not a comment\n\\*.log" ), 'gap' ), array( '', '' ) );

$convert = static fn( string $md ) => WP_Docs\Pipeline\convert_body( $md, array( 'mode' => 'blocks' ) );
$blocks  = Markdown_Out\parse( $convert( $body ) );
check( 'blocks: alert is a studio-docs callout, fence a studio-docs code block', array( $blocks[3]['blockName'], $blocks[5]['blockName'], $blocks[5]['attrs']['language'] ), array( 'studio-docs/callout', 'studio-docs/code', 'php' ) );
$noop    = Reverse\merge( $body, $blocks, $convert );
check( 'merge no-op is identity', $noop['markdown'], $body );
check( 'merge no-op stats', array( $noop['verbatim'], $noop['rewritten'], $noop['added'], $noop['removed'] ), array( 10, 0, 0, 0 ) );
check( 'merge no-op ops are all keeps', array_unique( array_column( $noop['ops'], 'op' ) ), array( 'keep' ) );
check( 'merge adjacent chunks keep their spacing', Reverse\merge( "# H\nText", Markdown_Out\parse( $convert( "# H\nText" ) ), $convert )['markdown'], "# H\nText" );

$edited                        = $blocks;
$edited[0]['innerHTML']        = '<p>Para <strong>one</strong>, edited.</p>';
$edited[0]['innerContent'][0]  = $edited[0]['innerHTML'];
$merged                        = Reverse\merge( $body, $edited, $convert );
check( 'merge rewrites one chunk', array( $merged['rewritten'], $merged['verbatim'] ), array( 1, 9 ) );
check( 'merge keeps the rest', substr( $merged['markdown'], strlen( 'Para **one**, edited.' ) ), substr( $body, strlen( 'Para one.' ) ) );
check( 'merge verifies', Reverse\verify( $merged['markdown'], $edited, $convert ), null );
check( 'merge op names the chunk', array( $merged['ops'][0]['op'], $merged['ops'][0]['chunks'] ), array( 'replace', array( 0 ) ) );

$removed = array_slice( $blocks, 1 );
$merged  = Reverse\merge( $body, $removed, $convert );
check( 'merge drops a removed chunk', substr( $merged['markdown'], 0, 10 ), '## Heading' );
check( 'merge delete op', $merged['ops'][0], array( 'op' => 'delete', 'chunks' => array( 0 ) ) );

$added  = array_merge( array_slice( $blocks, 0, 1 ), Markdown_Out\parse( $convert( 'New paragraph.' ) ), array_slice( $blocks, 1 ) );
$merged = Reverse\merge( $body, $added, $convert );
check( 'merge inserts', substr( $merged['markdown'], 0, 27 ), "Para one.\n\nNew paragraph.\n\n" );
check( 'merge insert stats', array( $merged['added'], $merged['verbatim'] ), array( 1, 10 ) );
check( 'merge insert op follows chunk 0', array( $merged['ops'][1]['op'], $merged['ops'][1]['after'] ), array( 'insert', 0 ) );

// A new callout and a new code block from the editor (no wrapper div, default type omitted).
$editor_blocks = Markdown_Out\parse( "<!-- wp:studio-docs/callout -->\n<!-- wp:paragraph -->\n<p>Mind the gap.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:studio-docs/callout -->\n\n<!-- wp:studio-docs/callout {\"type\":\"warning\",\"title\":\"Careful\"} -->\n<!-- wp:paragraph -->\n<p>Hot.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:studio-docs/callout -->\n\n<!-- wp:studio-docs/code {\"language\":\"bash\"} -->\n<pre class=\"wp-block-studio-docs-code\"><code>studio start &#91;--path]</code></pre>\n<!-- /wp:studio-docs/code -->\n\n<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>plain</code></pre>\n<!-- /wp:code -->" );
$warnings      = array();
$out           = Markdown_Out\blocks_to_markdown( $editor_blocks, array(), $warnings );
check( 'markdown out: editor callout without wrapper', 0 === strpos( $out, "> [!NOTE]\n> Mind the gap." ), true );
check( 'markdown out: callout title becomes a bold line, with a warning', array( strpos( $out, "> [!WARNING]\n> **Careful**\n>\n> Hot." ) !== false, count( $warnings ) ), array( true, 1 ) );
check( 'markdown out: code block language from the attribute, brackets unescaped', strpos( $out, "```bash\nstudio start [--path]\n```" ) !== false, true );
check( 'markdown out: core code block is a plain fence', strpos( $out, "```\nplain\n```" ) !== false, true );
check( 'fingerprint: editor callout equals imported callout', Reverse\fingerprint( $editor_blocks[0] ), Reverse\fingerprint( Markdown_Out\parse( $convert( "> [!NOTE]\n> Mind the gap." ) )[0] ) );
check( 'fingerprint: core code equals studio-docs code', Reverse\fingerprint( $editor_blocks[3] ), Reverse\fingerprint( Markdown_Out\parse( $convert( "```\nplain\n```" ) )[0] ) );
check( 'verify: the callout title does not round-trip', Reverse\verify( $out, $editor_blocks, $convert )['index'], 1 );

$out = Markdown_Out\blocks_to_markdown( $blocks );
check( 'markdown out alert', strpos( $out, "> [!NOTE]\n> Careful." ) !== false, true );
check( 'markdown out table', strpos( $out, "| a | b |\n|---|---|\n| 1 | 2 |" ) !== false, true );
check( 'markdown out fence', strpos( $out, "```php\n<?php echo 1;\n```" ) !== false, true );
check( 'markdown out nested list', strpos( $out, "- b\n    - nested" ) !== false, true );
check( 'markdown out details', strpos( $out, "<details>\n<summary>More</summary>\n\nInside.\n\n</details>" ) !== false, true );
check( 'markdown out hard break', Markdown_Out\html_to_markdown( '<p>one<br>two</p>' ), 'one<br>two' );
check( 'markdown out links and images through the resolvers', Markdown_Out\html_to_markdown( '<p><a href="/docs/cli/#usage">CLI</a> <img src="/wp-content/uploads/docs/a.webp" alt="A"></p>', array( 'link' => static fn( $h ) => 0 === strpos( $h, '/docs/cli/' ) ? 'cli.md' . substr( $h, 10 ) : null, 'image' => static fn( $s ) => 'images/' . basename( $s ) ) ), '[CLI](cli.md#usage) ![A](images/a.webp)' );
check( 'escape text', Markdown_Out\escape_text( 'a *b* [c] snake_case _x <tag> & &amp;' ), 'a \\*b\\* \\[c\\] snake_case \\_x &lt;tag> & &amp;amp;' );
check( 'escape url text raw', Markdown_Out\escape_text( 'see https://x.y/*z*' ), 'see https://x.y/*z*' );

$parts = Reverse\split_file( "---\ntitle: T\ndescription: D\n---\n\n# T\n\nBody.\n" );
check( 'split file', array( $parts['title'], $parts['body'] ), array( 'T', "Body.\n" ) );
check( 'join file', Reverse\join_file( $parts, 'Body.' ), "---\ntitle: T\ndescription: D\n---\n\n# T\n\nBody.\n" );
check( 'join file keeps a missing final newline', Reverse\join_file( Reverse\split_file( "# T\n\nBody." ), 'Body.' ), "# T\n\nBody." );
check( 'join file without a title', Reverse\join_file( Reverse\split_file( "---\ntitle: T\n---\n\nBody.\n" ), 'Body.' ), "---\ntitle: T\n---\n\nBody.\n" );
check( 'front matter set', Reverse\set_front_matter_field( $parts['front_matter'], 'title', 'New: title' ), "---\ntitle: \"New: title\"\ndescription: D\n---\n" );
check( 'front matter add', Reverse\set_front_matter_field( "---\ntitle: T\n---\n", 'description', 'D' ), "---\ntitle: T\ndescription: D\n---\n" );

/*
 * Upstream: the staged body against the file in Automattic/studio.
 */
$upstream = "# Page\n\nShort lede that the staging lifts into the description.\n\nSee the [CLI](https://developer.wordpress.com/docs/developer-tools/studio/cli/) for details.\n\n## [Install](#0-install)\n\n\\# Comment line\nstudio start\n\n# Another comment\nstudio stop\n\n![Shot](https://developer.wordpress.com/wp-content/uploads/2025/01/shot.webp)\n\nLast paragraph.\n";
$staged   = "See the [CLI](cli.md) for details.\n\n## Install\n\n```bash\n# Comment line\nstudio start\n# Another comment\nstudio stop\n```\n\n![Shot](images/shot.webp)\n\nLast paragraph.";
$up_parts = Reverse\split_file( $upstream );
check( 'upstream key ignores targets, tags, fences and case', Upstream\key( "## [Install](#0-install)" ), Upstream\key( '## Install' ) );
check( 'upstream key keeps image file names', Upstream\key( '![Shot](https://x/2025/01/shot.webp)' ), Upstream\key( '![Shot](images/shot.webp)' ) );
$aligned = Upstream\align( Chunks\chunk( $staged ), Chunks\chunk( $up_parts['body'] ), array( 2 ) );
check( 'upstream align: exact keys and a fence onto its run of lines', $aligned, array( 0 => array( 1 ), 1 => array( 2 ), 2 => array( 3, 4, 5 ), 3 => array( 6 ), 4 => array( 7 ) ) );
$sblocks = Markdown_Out\parse( $convert( $staged ) );
$noop    = Reverse\merge( $staged, $sblocks, $convert );
check( 'upstream no-op replay is the upstream body', Upstream\apply( rtrim( $up_parts['body'], "\n" ), $staged, $noop['ops'] ), rtrim( $up_parts['body'], "\n" ) );
$sedited                       = $sblocks;
$sedited[0]['innerHTML']       = '<p>See the <a href="/docs/cli/">CLI</a> for all details.</p>';
$sedited[0]['innerContent'][0] = $sedited[0]['innerHTML'];
$up_resolvers = array( 'link' => static fn( $h ) => '/docs/cli/' === $h ? 'cli.md' : null );
$merged       = Reverse\merge( $staged, $sedited, $convert, $up_resolvers );
$warn         = array();
$replayed     = Upstream\apply( rtrim( $up_parts['body'], "\n" ), $staged, $merged['ops'], $warn );
check( 'upstream edit replay changes the one paragraph and keeps the lifted lede', array( strpos( $replayed, "Short lede that the staging lifts into the description.\n\nSee the [CLI](cli.md) for all details.\n\n## [Install](#0-install)" ), $warn ), array( 0, array() ) );
$fence_edit = $sblocks;
$fence_edit[2]['innerHTML']       = str_replace( 'studio stop', 'studio stop --all', $fence_edit[2]['innerHTML'] );
$fence_edit[2]['innerContent'][0] = $fence_edit[2]['innerHTML'];
$merged   = Reverse\merge( $staged, $fence_edit, $convert );
$replayed = Upstream\apply( rtrim( $up_parts['body'], "\n" ), $staged, $merged['ops'], $warn );
check( 'upstream fence edit replaces the run of upstream lines with the fence', array( strpos( $replayed, "## [Install](#0-install)\n\n```bash\n# Comment line\nstudio start\n# Another comment\nstudio stop --all\n```\n\n![Shot]" ) !== false, substr_count( $replayed, 'studio stop' ) ), array( true, 1 ) );
check( 'upstream description replaced', Upstream\replace_description( $up_parts['body'], 'Short lede that the staging lifts into the description.', 'A new lede.' ), "A new lede.\n\nSee the [CLI](https://developer.wordpress.com/docs/developer-tools/studio/cli/) for details.\n\n## [Install](#0-install)\n\n\\# Comment line\nstudio start\n\n# Another comment\nstudio stop\n\n![Shot](https://developer.wordpress.com/wp-content/uploads/2025/01/shot.webp)\n\nLast paragraph.\n" );
check( 'upstream description not found', Upstream\replace_description( $up_parts['body'], 'Something else', 'x' ), null );

/*
 * Drift: the content hash.
 */
$imported = $convert( $body );
$hash     = Drift\content_hash( $imported, 'Title', 'Desc' );
check( 'hash is versioned sha256', (bool) preg_match( '/^v1:[0-9a-f]{64}$/', $hash ), true );
check( 'hash is stable', Drift\content_hash( $imported, 'Title', 'Desc' ), $hash );
check( 'hash ignores a re-serialised page', Drift\content_hash( str_replace( '<!-- wp:heading -->', '<!-- wp:heading {"level":2,"className":""} -->', $imported ), ' Title ', 'Desc' ), $hash );
check( 'hash sees a content change', Drift\content_hash( str_replace( 'Para one.', 'Para two.', $imported ), 'Title', 'Desc' ) === $hash, false );
check( 'hash sees a title change', Drift\content_hash( $imported, 'Other', 'Desc' ) === $hash, false );
check( 'changed fields', Drift\changed_fields( $hash, str_replace( 'Para one.', 'Para two.', $imported ), 'Title', 'New desc', 'Title', 'Desc' ), array( 'content', 'description' ) );
check( 'changed fields: clean', Drift\changed_fields( $hash, $imported, 'Title', 'Desc', 'Title', 'Desc' ), array() );
check( 'hash version check', array( Drift\is_current( $hash ), Drift\is_current( 'v0:abc' ) ), array( true, false ) );

echo "\n{$GLOBALS['wdt_passed']} passed, {$GLOBALS['wdt_failed']} failed\n";
exit( $GLOBALS['wdt_failed'] ? 1 : 0 );
