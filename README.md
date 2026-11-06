# wordpress.studio

The website for WordPress Studio, built with Studio Code, and the source of its homepage video.

- The repository root is the site's folder: the `studio` block theme, the uploads, the SQLite database and `DESIGN.md`. WordPress core is not tracked, because Studio installs it when it creates a site.
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

`docs/` renders the Studio documentation at wordpress.studio/docs with [Blume](https://useblume.dev). The markdown lives in the [Studio repository](https://github.com/Automattic/studio/tree/trunk/docs/user) and is synced in at build time; see [docs/README.md](docs/README.md).

```bash
cd docs
pnpm install
pnpm run sync   # fetch the markdown and screenshots from Automattic/studio
pnpm run dev    # preview at http://localhost:4321/docs/
```

## Publishing

The site is published as static files to a [Spacefast](https://spacefast.com) Space, which serves wordpress.studio. `scripts/build-site.sh` builds the whole site into `dist/`: the static export of the WordPress site, the docs under `/docs`, and the root files below. Then publish `dist/` with the [Spacefast CLI](https://spacefast.com/docs/cli/):

```bash
scripts/build-site.sh
sf publish dist --root-directory . --space spc_95b3647e99974586b104ab2cf1050dd6
```

- `--root-directory .` makes the CLI publish `dist/` as it is; without it, it picks a folder inside the docs.
- `--space` is only needed the first time on a machine; the CLI remembers the Space in `dist/.spacefast/`. You need to be a member of the Spacefast team that owns the Space.
- `_redirects`, `_headers`, `robots.txt` and `llms.txt` at the site root are published with the export. They keep `/install.sh` and `/install.ps1` working and point search engines at the sitemap. `docs/scripts/finalize-site.mjs` adds the docs' rules to them: Spacefast renders a `.md` file as a page, so the Markdown copies of the docs pages are published as `.md.txt` behind a rewrite, and the files Blume writes under `.well-known` (which Spacefast refuses to serve) move up to `/docs/`.
- The theme writes `https://wordpress.studio` into canonicals, social tags and the sitemap, so the copy on the Space's own address is never indexed in its place.

The build needs `studio export-static`, which is not in a Studio release yet ([Automattic/studio#5567](https://github.com/Automattic/studio/pull/5567)). Until it ships, build the CLI from that branch and run the build with it first on your `PATH`:

```bash
git clone --branch add-spacefast-publishing https://github.com/Automattic/studio.git ../studio-spacefast
( cd ../studio-spacefast && npm ci && npm run cli:build )
mkdir -p ../studio-spacefast/bin && printf '#!/usr/bin/env bash\nexec node "$(dirname "$0")/../apps/cli/dist/cli/main.mjs" "$@"\n' > ../studio-spacefast/bin/studio && chmod +x ../studio-spacefast/bin/studio
PATH="$PWD/../studio-spacefast/bin:$PATH" scripts/build-site.sh
```

That build reads the same site list as the Studio app, so the site needs no re-registration.

The WordPress site alone can also be published with the Studio CLI from that branch (`studio spacefast publish`), but that leaves out the docs.

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
