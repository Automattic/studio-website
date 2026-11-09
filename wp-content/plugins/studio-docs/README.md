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
                           the Markdown served to agents
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
inc/cli.php                `wp wp-docs import|status|convert`
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
tests/                     plain-PHP tests of the pipeline (no database)
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

The manifest entry fields the blocks read: `title`, `label` (sidebar), `heading` (sub-section heading
when the page has children), `group` and `group_title` (the sidebar group), `order`, `description`,
`source_url` (the GitHub edit link derives from it), `updated` (YYYY-MM-DD of the page's last commit).

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
```

Any WordPress install works (a Studio site, for example); `tests/fetch-deps.sh` downloads one into
`tests/.deps/` otherwise. The fixtures cover paragraphs and headings, alerts, code, lists, tables, images
and links, details and raw HTML, in blocks and classic mode and as the Markdown served to agents.
