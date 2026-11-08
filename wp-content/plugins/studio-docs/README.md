# Studio Docs

The documentation section of wordpress.studio, as a WordPress plugin: the `docs` post type, the importer
that fills it from the markdown in [Automattic/studio](https://github.com/Automattic/studio/tree/trunk/docs/user),
the Markdown and JSON endpoints for agents and search, and the blocks that render the docs layout. The
`studio` theme only adds the `single-docs` and `archive-docs` templates (and the `docs-header` part) that
place the blocks; everything docs-specific lives here.

The layout, markup and stylesheet follow the Blume build the docs had before, element by element, so the
pages look the same in both color schemes.

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
inc/permalinks.php         `/docs/<path>/` from the manifest path
inc/tree.php               the documentation tree (one query, cached per import)
inc/template-tags.php      helpers the blocks and the theme read
inc/agents.php             `/docs/<page>.md`, llms.txt, llms-full.txt, index.json, search-index.json
inc/render.php             rendering helpers: current page, icons, highlights, sidebar and TOC builders
inc/blocks.php             the blocks (below)
inc/content.php            render filters: plain markup for core blocks, heading anchors
inc/assets.php             what a docs page loads, the color scheme script, favicons, the title
inc/cli.php                `wp wp-docs import|status|convert`
assets/css/docs.css        Blume's compiled stylesheet, pruned to the utilities the pages use
assets/css/docs-editor.css the same, scoped under .studio-docs-canvas for the editor canvas
assets/js/docs.js          theme toggle, drawer, TOC scroll-spy, page actions, copy, zoom, search
assets/js/editor.js        declares the blocks to the editor (server-side previews, attribute content)
assets/vendor/             medium-zoom 1.1.2 (MIT)
assets/images/             the app icon pair (logo and favicon), the touch icon
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
   `wp-content/uploads/docs/`, headings get GitHub's ids, and the HTML becomes blocks.
3. The page's Markdown (front matter `title`, `description`, `sidebar.label`; links and images made
   root-relative) is stored for `/docs/<path>.md`.

The manifest entry fields the blocks read: `title`, `label` (sidebar), `heading` (sub-section heading
when the page has children), `group` and `group_title` (the sidebar group), `order`, `description`,
`source_url`.

## Blocks

All under `studio-docs/`, server-rendered, not in the inserter. `assets/js/editor.js` declares them to
the block editor with server-side previews, so docs posts and the docs templates open cleanly in the
editor and the Site Editor; while one is edited the canvas gets the scoped docs stylesheet. The editor is
for looking: the posts are regenerated from the markdown on every build.

| Block | Renders |
|---|---|
| `header` | skip link, the sticky header: menu button, logo pair (light/dark), Download, the search dialog, the theme toggle |
| `sidebar` | the drawer / sticky column: the featured link, Overview, one heading per group, a sub-heading for a page with children |
| `layout`, `main`, `rail` | the three-column grid, the main column, the right rail (`xl` and up) |
| `breadcrumbs` | the page's group, or its parent page when it has one |
| `toc` | "On this page" (H2 and H3); `variant: mobile` renders the disclosure above the article, where the page actions move below `xl` |
| `article` | title, lede (the description) and the content |
| `page-actions` | Scroll to top, Copy as Markdown, Open in chat (v0, ChatGPT, Claude, T3 Chat, Scira, Cursor) |
| `pagination` | Previous / Next in sidebar order |
| `footer` | links, copyright, GitHub; the drawer backdrop and the icon sprite |
| `code` | the code frame with language label and copy button; the Shiki highlight from `highlights.json`. Attributes `language`, `code` |
| `image` | a plain image in a paragraph; the script adds the zoom. Attributes `src`, `alt`, `id`, `title`, `href`, `caption` |
| `table` | a table in a horizontally scrolling frame. Attribute `html` |
| `callout` | a GitHub alert (`> [!NOTE]` and friends) with inner blocks; none in the current content |

The content blocks keep their content in attributes (self-closing block comments), so they have no
saved HTML to validate or lose. Core paragraphs, headings, lists, quotes, separators and details keep
their `wp-block-*` classes (Blume's stylesheet ignores them); headings wrap their text in a `#` anchor
link, and links to other sites open in a new tab with the external-link icon.

## Tests

```bash
WP_DOCS_TEST_WORDPRESS=/path/to/a/wordpress php tests/run-unit.php
WP_DOCS_TEST_WORDPRESS=/path/to/a/wordpress php tests/run-fixtures.php      # --update rewrites the expected files
```

Any WordPress install works (a Studio site, for example); `tests/fetch-deps.sh` downloads one into
`tests/.deps/` otherwise. The fixtures cover paragraphs and headings, alerts, code, lists, tables, images
and links, details and raw HTML, in blocks and classic mode and as the Markdown served to agents.
