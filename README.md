# wordpress.studio

The website for WordPress Studio, built with Studio Code, and the source of its homepage video.

- The repository root is the site's folder: the `studio` block theme, the `studio-docs` plugin, the uploads, the SQLite database and `DESIGN.md`. WordPress core is not tracked, because Studio installs it when it creates a site.
- `video/` is the Remotion project behind the homepage video.

## Load the site in Studio

You need [WordPress Studio](https://developer.wordpress.com/studio/), either the app or the CLI (`npm install -g wp-studio`).

Studio only adds a folder that is empty or already holds a full WordPress install, so you first create a blank site, then check this repository out into its folder.

### With the Studio app

1. Click **Add site**, then **Blank site**. Name it `WordPress Studio`, open **Advanced settings** and set **Local path** to `~/Studio/studio-website`. Leave **What should we create?** empty and click **Create site**.
2. Stop the site from its menu in the sidebar.
3. In a terminal, check the repository out into the site's folder:

   ```bash
   cd ~/Studio/studio-website
   git init -b main
   git remote add origin https://github.com/Automattic/studio-website.git
   git fetch origin
   git checkout -f -B main --track origin/main
   ```

4. Start the site again in Studio. Its pages, theme and media are now the ones from this repository.

### With the CLI

```bash
studio site create --path ~/Studio/studio-website --name "WordPress Studio" --start=false
cd ~/Studio/studio-website
git init -b main
git remote add origin https://github.com/Automattic/studio-website.git
git fetch origin
git checkout -f -B main --track origin/main
studio site start
```

`git checkout -f` replaces the blank site's database and theme with the ones in this repository. Studio keeps its own files (`wp-config.php`, `wp-content/db.php`, `wp-content/mu-plugins/`, `STUDIO.md`), which are ignored.

To log in to WP Admin, use Studio: **Open in… → WP Admin** in the app, or `studio site status` for the admin password.

## Working on the site

Ask Studio Code (in the app, or `studio code` in the site's folder) for changes, then commit them. `DESIGN.md` describes the design system; follow it for any design or content change.

The content lives in one SQLite file, `wp-content/database/.ht.sqlite`. Git can version it but not merge it: stop the site before you pull or commit, and when two people change content, the last commit wins.

## The docs

The Studio documentation at wordpress.studio/docs is WordPress too: the `docs` post type of the `studio-docs` plugin (`wp-content/plugins/studio-docs`), rendered by the theme's `single-docs` template and its docs header and footer parts, built from core blocks and the plugin's blocks (code, callout, navigation, table of contents, search, …), so the pages and the templates open in the block editor and the Site Editor like the rest of the site. The markdown is not edited here. Its source of truth is `docs/user/docs/` in the [Studio repository](https://github.com/Automattic/studio/tree/trunk/docs/user), so the docs evolve with the code and ship with each release; the site imports it at build time and the posts are build output (the database is not committed).

```bash
npm install                            # once: Shiki, for the code highlighting
node scripts/build-docs-manifest.mjs   # stage the markdown and screenshots into build/docs/
studio wp wp-docs import --force       # import them into the docs post type
```

`scripts/build-docs-manifest.mjs` checks the Studio repository's `docs/user/docs` and `docs/user/screenshots/images` out (from `trunk`; set `STUDIO_DOCS_REF` to pin another revision), places each page in the sidebar defined in its `PAGES` list (a markdown file upstream that is not in the list is reported at every build and stays off the site until it is added; working notes go in `NOT_PAGES`), records the revision it staged in `build/docs/source.json`, cleans up what the markdown carried over from developer.wordpress.com (heading links, linked images, absolute links, command lines without code fences), highlights every code block with Shiki (`github-light` / `github-dark`, the same themes as before) and writes `build/docs/manifest.json` for the importer. `wp wp-docs import` turns the pages into blocks, sideloads the screenshots once per content hash into `wp-content/uploads/docs/` (ignored), and keeps each page's Markdown for `/docs/<page>.md`, `/docs/llms.txt`, `/docs/llms-full.txt`, `/docs/index.json` and the search index at `/docs/search-index.json`. `wp wp-docs status` shows what is imported and from which revision, and whether any page was edited in WordPress since; `wp wp-docs convert <file.md>` prints the blocks for one file. The plugin's README describes the blocks and the pipeline; `php tests/run-fixtures.php` and `php tests/run-unit.php` in the plugin folder test the conversion without a database.

### Edits made in WordPress

The pages open in the block editor like any post, but the import rewrites every page, so an edit made there is live on the next deploy and gone on the one after the next import. The importer records a hash of every page it writes; a page that no longer matches has *drifted*:

```bash
npm run docs:drift                      # the pages edited in WordPress: what changed, by whom, when (exit 1 when any)
npm run docs:drift -- --diff            # each edit as a Markdown diff against the file the page was imported from
npm run docs:reconcile                  # write the edits back onto Automattic/studio trunk and show the diff (dry run)
npm run docs:reconcile -- --pr          # the same, then push a branch and open the pull request with gh
npm run docs:drift -- --reset           # discard the edits (asks first): re-import the pages from the staged files
```

`npm run docs:drift` is `studio wp wp-docs drift`. The reconciliation (`wp wp-docs drift --export`, which `scripts/reconcile-docs.mjs` runs) merges the page's blocks back into the Markdown it was imported from, keeping every unchanged chunk byte for byte, verifies that the result converts to the same blocks, and replays the change onto the upstream file (whose text the staging script transforms), so the diff on the pull request is the editor's change and nothing else. The branch is `docs/site-edits-<date>`; the pull request's body names the pages, who edited them on the site and when. GitHub stays the source of truth: once the pull request merges, the next build imports the pages from `trunk` again. Saving in the editor straight to GitHub is not implemented; the plugin's README has the details of the detection and the merge.

## Publishing

The site is published as static files to a [Spacefast](https://spacefast.com) Space, which serves wordpress.studio. Nothing publishes on its own: a docs change in the Studio repository, or a theme or plugin change here, reaches the site when someone builds and publishes it. Pushing to this repository deploys nothing.

```bash
npm run docs:check      # is the live site behind the docs in Automattic/studio? (exit 1 when it is)
npm run deploy          # build and publish to production (asks before publishing)
npm run deploy:preview  # the same to a preview version, with its own URL, leaving production alone
```

`npm run docs:check` (`scripts/check-docs-freshness.mjs`) reads the revision the live docs were built from out of `/docs/index.json` and lists the upstream commits since then that touched the docs or their screenshots, so a rebuild is never a guess; `--local` checks the last local staging instead, `--site <url>` another site. The build refuses to run while a docs page was edited in WordPress (see "Edits made in WordPress" above): reconcile or discard the edits first, or set `DOCS_ALLOW_DRIFT=1` to let the import overwrite them. `npm run deploy` (`scripts/publish-site.sh`) runs the build below and then `sf publish`; extra arguments go to `sf publish` (`-y` skips the confirmation, `-m` sets the version message), and it ends with the freshness check against the live site.

Under the hood, `scripts/build-site.sh` builds the whole site into `dist/`: it checks for docs edited in WordPress, stages and imports the docs, exports the WordPress site (pages and docs) and adds the root files below. Then `dist/` is published with the [Spacefast CLI](https://spacefast.com/docs/cli/):

```bash
scripts/build-site.sh
sf publish dist --root-directory . --space spc_95b3647e99974586b104ab2cf1050dd6
```

- `--root-directory .` makes the CLI publish `dist/` as it is; without it, it picks a folder inside the docs.
- `--space` is only needed the first time on a machine; the CLI remembers the Space in `dist/.spacefast/`. You need to be a member of the Spacefast team that owns the Space.
- `_redirects`, `_headers`, `robots.txt` and `llms.txt` at the site root are published with the export. They keep `/install.sh` and `/install.ps1` working and point search engines at the sitemap. `scripts/finalize-site.mjs` adds the docs' rules to them: Spacefast renders a `.md` file as a page, so the Markdown copies of the docs pages are published as `.md.txt` behind a rewrite.
- The theme writes `https://wordpress.studio` into canonicals, social tags and the sitemap, so the copy on the Space's own address is never indexed in its place.
- `STUDIO_SITE=<path> scripts/build-site.sh` builds from another Studio site that runs this checkout (a development site whose theme and plugin folders are symlinks into it, with `WP_DOCS_MANIFEST` pointing at this checkout's `build/docs/manifest.json`).

The build needs `studio export-static`, which is not in a Studio release yet ([Automattic/studio#5567](https://github.com/Automattic/studio/pull/5567)). Until it ships, build the CLI from that branch next to this checkout; the build script uses it when the installed CLI lacks the command (or set `STUDIO_EXPORT_CLI` to its `studio` binary):

```bash
git clone --branch add-spacefast-publishing https://github.com/Automattic/studio.git ../studio-spacefast
( cd ../studio-spacefast && npm ci && npm run cli:build )
mkdir -p ../studio-spacefast/bin && printf '#!/usr/bin/env bash\nexec node "$(dirname "$0")/../apps/cli/dist/cli/main.mjs" "$@"\n' > ../studio-spacefast/bin/studio && chmod +x ../studio-spacefast/bin/studio
npm run deploy
```

That build reads the same site list as the Studio app, so the site needs no re-registration.

## The video

`video/` renders the 50-second homepage video at 2560×1440. You need Node 20 or later.

```bash
cd video
npm install
npm run studio   # preview and edit in Remotion Studio
npm run render   # out/studio-video.mp4
npm run poster   # out/studio-video-poster.jpg
```

- `src/scenes/` holds one component per scene; `src/timeline.json` sets when each starts, on a 120 BPM bar grid (one bar = 60 frames).
- `public/` holds real captures from Studio and the sites it built: the prompt being typed in the app (`typing/`), the scroll through Northlight Architects (`scroll/`), the site editor (`img/`) and the showcase sites (`sites/`).
- `public/audio/soundtrack.wav` is the mixed soundtrack. `prep.py` regenerates it, together with `src/sites.json` and `src/timeline.json`, but it needs GarageBand's Apple Loops and the original showcase captures, so you only need it to change the music or the site list.
- The published cut skips the first 6 seconds of the composition (`--frames=180-1679`).

Licensing: Remotion requires a paid company license for organizations of Automattic's size. The soundtrack is a composition made from Apple Loops, which Apple allows in your own productions but not redistributed as loops, so the loop files are not in this repository. The showcase sites and their images were generated by Studio Code.
