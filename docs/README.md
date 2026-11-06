# wordpress.studio/docs

The Studio documentation, served at [wordpress.studio/docs](https://wordpress.studio/docs/). It is built with [Blume](https://useblume.dev), the docs framework behind [spacefast.com/docs](https://spacefast.com/docs/), and published as static files next to the rest of the site.

## Where the content lives

The markdown is not edited here. Its source of truth is `docs/user/docs/` in the [Studio repository](https://github.com/Automattic/studio/tree/trunk/docs/user), so the docs evolve with the code and ship with each release.

`pnpm run sync` checks that folder out together with `docs/user/screenshots/images/`, places each page in the sidebar structure defined in `scripts/sync-content.mjs`, copies the screenshots each page uses into `public/images/`, and rewrites what the markdown carried over from developer.wordpress.com: heading links, linked images, absolute links to the old docs, and command lines that lost their code fences. Nothing is fetched from developer.wordpress.com; a page whose screenshot has no file in the Studio repo fails the sync. `content/**/*.md` and `public/images/` are git-ignored for that reason; only the structure (`meta.ts` files), the config and the theme are versioned here.

The sync reads the `ui-refresh-user-docs` branch, the 2.0 docs refresh [Automattic/studio#5517](https://github.com/Automattic/studio/pull/5517), which the screenshot clean-up [Automattic/studio#5772](https://github.com/Automattic/studio/pull/5772) merged into; that is where the finished pages and their screenshots live until it merges, and then the default moves to `trunk`. Set `STUDIO_DOCS_REF` to sync another branch or commit.

## Work on the docs

```bash
cd docs
pnpm install
pnpm run sync
pnpm run dev
```

Use pnpm (or Bun, as the Spacefast docs do): npm 11 loops forever resolving Blume's peer dependencies. `package.json` requires pnpm 10 or later in `engines`.

`pnpm run build` writes the static site to `dist/`, with every route under `/docs`. `pnpm run validate` checks internal links and anchors.

## Design

The theme follows the Studio app rather than the marketing site: Inter, the system mono stack, neutral surfaces, WordPress.com Blueberry as the single accent, and a light scheme that follows the OS. `blume.config.ts` sets what Blume's config can express and `theme.css` the rest, both from `apps/ui/src/lib/theme/tokens.css` in the Studio repository.

## What readers and agents get

Blume gives every page a table of contents, previous and next links, search, a light and dark theme, Copy as Markdown and Open in chat actions, a Markdown copy of each page next to it (`/docs/cli.md` for `/docs/cli/`), and an `llms.txt` index at `/docs/llms.txt`.
