#!/usr/bin/env bash
# Build the whole of wordpress.studio into dist/: the docs imported into WordPress from the markdown in
# Automattic/studio, the static export of the WordPress site (pages and docs alike), and the root files
# Spacefast reads (_redirects, _headers, robots.txt, llms.txt). Publish the result with `sf publish dist`
# (the Spacefast CLI).
#
# Needs the Studio CLI with `export-static` (Automattic/studio#5567) and Node 22 or later with the
# dependencies installed (`npm install` in the repository root). The site is the repository root; set
# STUDIO_SITE to build from another Studio site that runs this checkout (a dev site with symlinks).
set -euo pipefail
cd "$(dirname "$0")/.."
site="${STUDIO_SITE:-$PWD}"

echo "Staging the docs from Automattic/studio…"
node scripts/build-docs-manifest.mjs

echo "Importing the docs into WordPress…"
studio wp --path "$site" plugin is-active studio-docs 2>/dev/null || studio wp --path "$site" plugin activate studio-docs
studio wp --path "$site" wp-docs import --force

# The export refuses an output folder inside the site, so it goes to a temporary folder
# first and becomes dist/ afterwards.
echo "Exporting the WordPress site…"
export_dir="$(mktemp -d "${TMPDIR:-/tmp}/studio-website-static.XXXXXX")"
trap 'rm -rf "$export_dir"' EXIT
studio export-static "$export_dir" --path "$site" --overwrite
rm -rf dist
mv "$export_dir" dist

for file in _redirects _headers robots.txt llms.txt; do
	cp "$file" dist/
done
# Spacefast renders .md files as pages: see the script.
node scripts/finalize-site.mjs dist

echo "Site built in dist/ ($(find dist -type f | wc -l | tr -d ' ') files)"
