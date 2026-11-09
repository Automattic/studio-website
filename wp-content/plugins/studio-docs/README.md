# Studio Docs

The documentation section of wordpress.studio, as a WordPress plugin: the `docs` post type, the importer
that fills it from the markdown in [Automattic/studio](https://github.com/Automattic/studio/tree/trunk/docs/user),
the Markdown and JSON endpoints for agents and search, and the blocks of the docs layout. The `studio`
theme adds the `single-docs` template and the `docs-header` and `docs-footer` template parts, built from
core blocks and the blocks below; everything docs-specific lives here.

The pages look like the Blume build the docs had before, in both color schemes: the stylesheets are
written against its measurements (`assets/css/`, `blocks/*/style.css`), the markup is WordPress's own
(core blocks for the content and the layout, the blocks below where core has nothing).

```
studio-docs.php            bootstrap: requires, activation, the vendored importer and parser
inc/post-type.php          the `docs` post type and its meta
inc/class-importer.php     the importer (a subclass of the WordPress.org Markdown importer)
inc/manifest.php           manifest normalisation and lookups
inc/local-sources.php      serves the staged files to the importer through `pre_http_request`
inc/sources.php            source URL parsing and resolution
inc/pipeline.php           the conversion steps the importer runs
inc/transforms/            front matter, GitHub alerts, heading ids, links, images, HTML → blocks,
                           the Markdown served to agents; and the way back: chunks.php (a Markdown
                           body split into chunks), markdown-out.php (blocks → Markdown), reverse.php
                           (the merge of edited blocks into the Markdown, verified), upstream.php
                           (the merge replayed onto the file in Automattic/studio)
inc/drift.php              the hash the importer stores per page, so an edit made in WordPress shows
inc/class-drift.php        drift detection, reconciliation and export (`wp wp-docs drift`)
inc/permalinks.php         `/docs/<path>/` from the manifest path; `/docs/` is the home page
inc/tree.php               the documentation tree (one query, cached per import)
inc/template-tags.php      helpers the blocks and the theme read
inc/agents.php             `/docs/<page>.md`, llms.txt, llms-full.txt, index.json, search-index.json
inc/render.php             rendering helpers: the page a block renders for, icons, highlights, the
                           sidebar tree, the table of contents, previous/next
inc/blocks.php             registers blocks/*; the Post Excerpt block shows only a written description
inc/content.php            render filters: heading anchors, external links in a new tab
inc/assets.php             the stylesheets and shared script on docs pages and in the editor, the
                           color scheme script, favicons, the document title
inc/cli.php                `wp wp-docs import|status|drift|convert`
blocks/<name>/             one folder per block: block.json, index.js (editor), render.php,
                           style.css, view.js (front end)
assets/css/tokens.css      the design tokens, both schemes (`studio-docs-tokens`, a dependency of the rest)
assets/css/docs.css        the page frame (header, columns, footer) and shared controls
assets/css/prose.css       the article: title, lede and the content's core blocks
assets/js/docs.js          shared helpers for the block scripts (clipboard, live region), image zoom
assets/vendor/             medium-zoom 1.1.2 (MIT)
assets/images/             the favicon pair and the touch icon
vendor/wporg-markdown/     the WordPress.org Markdown importer base class, unchanged
vendor/jetpack-markdown/   Jetpack's Markdown parser (16.3.1, GPL), unchanged
tests/                     plain-PHP tests of the pipeline and the reverse sync (no database)
```

## The pipeline

1. `scripts/build-docs-manifest.mjs` (repository root) stages the markdown: `build/docs/manifest.json`,
   `build/docs/content/**/*.md`, `build/docs/images/` and `build/docs/highlights.json` (every fenced
   code block highlighted with Shiki, `github-light` and `github-dark`).
2. `wp wp-docs import --force` reads the manifest (`WP_DOCS_MANIFEST`, default `build/docs/manifest.json`
   in the site folder; `WP_DOCS_PREFER_LOCAL` reads files from the checkout) and, for each page:
   front matter is split, GitHub alerts become placeholders, Jetpack's parser renders the markdown,
   links to other pages become `/docs/<path>/`, images are sideloaded once per content hash into
   `wp-content/uploads/docs/`, headings get GitHub's ids, and the HTML becomes blocks: core paragraph,
   heading, list, image, table, quote, separator and details, plus `studio-docs/code` and
   `studio-docs/callout`. The description becomes the excerpt.
3. The page's Markdown (front matter `title`, `description`, `sidebar.label`; links and images made
   root-relative) is stored for `/docs/<path>.md`.
4. A hash of what was written (title, description and the fingerprints of the blocks) is stored in
   `_wp_docs_content_hash`, so a page edited in WordPress afterwards can be told apart (see below).

The manifest entry fields the blocks read: `title`, `label` (sidebar), `heading` (sub-section heading
when the page has children), `group` and `group_title` (the sidebar group), `order`, `description`,
`source_url` (the GitHub edit link derives from it), `updated` (YYYY-MM-DD of the page's last commit).

## Editing in WordPress: drift

GitHub is the source of truth and every import rewrites every page, so an edit made in the block
editor is live on the next deploy (the static export reads the database) and gone on the next import.
Such an edit is *drift*, and the plugin makes it visible and recoverable instead of silent:

- The importer stores `_wp_docs_content_hash` with every page it writes (`inc/drift.php`): a sha256 of
  the title, the description and the fingerprints of the top-level blocks (block name, the attributes
  that matter, the saved HTML normalised). Saving a page in the editor without a change re-serialises
  every block but leaves the fingerprints alone, so that is not drift; a changed word, a new block or a
  removed one is. `post_modified` later than `_wp_docs_imported` is kept as a hint for pages imported
  before hashes existed (reported as `unknown`).
- `wp wp-docs drift` lists the drifted pages: post, path, what changed (content, title, description),
  who last edited the page (`_edit_last`, else the author) and when. `--format=table|json|count`; exit
  code 1 when there is drift, 0 when every page matches its import. `wp wp-docs status` prints a
  one-line summary.
- `wp wp-docs drift --diff` shows each page's edit as Markdown: the page's blocks are merged back into
  the Markdown it was imported from (`inc/transforms/reverse.php`). The baseline body is split into
  chunks (one paragraph, list, fence, table, alert…), each chunk is converted on its own so its blocks
  are known, baseline and live blocks are matched by fingerprint (longest common subsequence), unchanged
  chunks are copied byte for byte and only the changed blocks are written with `markdown-out.php`
  (the inverse of `blocks.php`: `-` bullets, `1.` steps, fences with the `language` attribute,
  `> [!NOTE]` alerts for `studio-docs/callout`, GFM tables, `<details>` and inline HTML kept). Site
  links (`/docs/<route>/`) go back to relative links between the Markdown files, attachment URLs to
  the repository image paths (`_wp_docs_image_source`). The result is converted again and compared
  with the page's blocks; a block that does not survive the round trip (a callout title, a group) is
  named, and that part of the export needs a look. The baseline is the staged file in
  `build/docs/content/` when it is from the revision the last import recorded, else `_wp_docs_markdown`
  with its absolute links made relative again.
- `wp wp-docs drift --export=<dir>` writes the reconciled files. The staged page is not the upstream
  file (scripts/build-docs-manifest.mjs lifts the first paragraph into the description, strips heading
  anchors, fences command lines, rewrites links), so with a checkout of Automattic/studio at hand
  (`--upstream=<dir>`, default: the one the staging script left in `build/docs/.cache/studio`) the edit is
  replayed onto the upstream file (`inc/transforms/upstream.php`: both bodies chunked and aligned by a
  key that ignores what the staging changes, a changed chunk's upstream counterpart replaced, an added
  block inserted after its predecessor's counterpart) and written at `docs/user/docs/<src>`; a changed
  title updates the `# ` line or the front matter, a changed description the front matter or the lifted
  paragraph. Without a checkout the staged form is written under `content/`. `drift.json` next to the
  files lists the pages, editors, times and warnings; `scripts/reconcile-docs.mjs` (repository root)
  turns it into a branch and a pull request.
- `wp wp-docs drift --reset` discards the edits (after a confirmation; `--yes` skips it): every page is
  re-imported from the staged files.
- `scripts/build-site.sh` runs the drift check before it stages and imports, and stops with these
  options while there is drift (`DOCS_ALLOW_DRIFT=1` builds anyway and the import overwrites the edits).

Saving in the editor straight to GitHub (the proof of concept's sync class and GitHub client) is not
part of this plugin yet; the export and the pull request are the way back for now.

## Blocks

Each block is a folder under `blocks/` registered from its `block.json`. The editor scripts are plain
JavaScript (no build step); each one ships its `index.asset.php` with its dependencies.

Content blocks, in the inserter under Text:

| Block | |
|---|---|
| `code` | Code edited in place (a rich-text `<code>`, like core's Code block) with a language picked in the sidebar. The front end adds the language label, the Shiki highlight from `highlights.json` (plain lines without one) and a copy button. Converts from and to core's Code block. |
| `callout` | A note, tip, important, warning or caution box (the type in the sidebar, an optional title in place) around any blocks. The importer writes one for each GitHub alert. |

Template blocks, in the inserter under Docs, previewed from the server in the editor (the Site Editor
previews the docs home when no page is in context):

| Block | |
|---|---|
| `menu-toggle` | the button that opens the sidebar drawer below 64rem |
| `search` | the search button and dialog: loads `/docs/search-index.json` on first open, scores title, headings, description and content, filters by section, previews the selected result; ⌘K / Ctrl K and `/` open it, ⌘J toggles the preview. The placeholder is a setting. |
| `theme-toggle` | switches `data-theme` on `<html>` (set before the first paint by an inline script) and remembers the choice |
| `sidebar` | a container: the drawer below 64rem (inert page behind it, Escape closes, focus returns), a sticky column above; the current page scrolled into view |
| `navigation` | the page tree: Overview, a heading per group, a sub-heading for a page with children, the current page marked |
| `breadcrumbs` | the page's group, or its parent page when it has one |
| `toc` | "On this page" (H2 and H3) with the heading in view marked; the `collapsible` setting renders a closed disclosure above the article, shown only when the rail is hidden, that the page actions move into |
| `page-actions` | Scroll to top, Copy as Markdown, Open in chat (v0, ChatGPT, Claude, T3 Chat, Scira, Cursor) |
| `pagination` | Previous / Next in sidebar order, named by the sidebar heading |
| `page-meta` | at the end of the article: "Edit this page on GitHub" and the date of the page's last commit (the manifest's `updated`, which the staging script reads from the GitHub commits API); each line is a setting |

The docs templates in the theme place them next to core blocks: Site Logo and Site Title in the header
with a Download button, a constrained `main` group holding Post Title, Post Excerpt (the description)
and Post Content, lists and Social Links in the footer. Two render filters apply to every block on a
docs page: headings wrap their text in a `#` anchor link, and links to other sites open in a new tab
with the external-link icon.

## Styling

`tokens.css` defines the tokens (`--docs-background`, `--docs-foreground`, `--docs-muted`, `--docs-border`,
`--docs-accent`, …) on `:root`, with the dark values under `[data-theme="dark"]`; the content blocks list it
as a dependency so they are styled wherever they are inserted. `docs.css` lays out the page frame; `prose.css` sets the article typography; every block's `style.css` styles
that block. The theme's stylesheet and core's block styles stay loaded; the docs rules override them
where the docs differ. The block editor gets the same stylesheets while a docs page or a docs template
is edited (`block_editor_settings_all`), so the previews look like the pages.

## Tests

```bash
WP_DOCS_TEST_WORDPRESS=/path/to/a/wordpress php tests/run-unit.php
WP_DOCS_TEST_WORDPRESS=/path/to/a/wordpress php tests/run-fixtures.php      # --update rewrites the expected files
WP_DOCS_TEST_WORDPRESS=/path/to/a/wordpress php tests/run-roundtrip.php     # needs build/docs (node scripts/build-docs-manifest.mjs)
```

Any WordPress install works (a Studio site, for example); `tests/fetch-deps.sh` downloads one into
`tests/.deps/` otherwise. The fixtures cover paragraphs and headings, alerts, code, lists, tables, images
and links, details and raw HTML, in blocks and classic mode and as the Markdown served to agents. The
unit tests also cover the reverse sync (chunks, Markdown out, merge, verify, the upstream replay) and
the drift hash. The round-trip test runs over every staged page: every block written back as Markdown
converts to the same blocks, a merge without an edit returns the staged body byte for byte, one edited
paragraph changes exactly one chunk and verifies, and (with the staging's checkout in
`build/docs/.cache/studio`) the replay onto the upstream file is exact (`-v` prints the upstream edits).
