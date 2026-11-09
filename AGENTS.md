# AI Instructions

This is a local WordPress site managed by [WordPress Studio](https://developer.wordpress.com/studio/).
For WordPress Studio instructions, see @STUDIO.md
If this site has a `DESIGN.md`, follow it for any design or content work.

> **Customising this file:** Feel free to edit, extend, or replace the contents below.

## The docs

`/docs` is build output. The source of truth is the markdown in `docs/user/docs` on `trunk` of
[Automattic/studio](https://github.com/Automattic/studio); every build re-imports it into the `docs` post type
(plugin: `wp-content/plugins/studio-docs`, details in the README's "The docs" and "Publishing").

| Command | Does |
|---|---|
| `npm run docs:check` | Is the live site behind upstream? Exit 1 when it is. |
| `npm run docs:drift` | Pages edited in WordPress since their import (exit 1 when any). `-- --diff` shows the edits as markdown. |
| `npm run docs:reconcile` | Writes those edits back onto `trunk` and prints the diff. Dry run; `-- --pr` pushes a branch and opens the pull request. |
| `npm run docs:drift -- --reset` | Discards those edits (re-imports the pages). Asks first. |
| `npm run deploy` | Builds and publishes to production. The build stops while there is drift. |

Rules for an agent driving this:

- Run the checks and the dry run freely; report what they find.
- Ask the human before: keeping or discarding a WordPress edit, `--pr` (opens a pull request on Automattic/studio),
  and `npm run deploy` (publishes). Never bypass the drift guard (`DOCS_ALLOW_DRIFT=1`) without being told to.
- A new upstream page is on the site only once it is added to `PAGES` in `scripts/build-docs-manifest.mjs`; the
  staging reports unmapped files at every build.
- Never commit `wp-content/database/.ht.sqlite`; the docs posts in it are build output.
