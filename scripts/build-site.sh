#!/usr/bin/env bash
# Build the whole of wordpress.studio into dist/: the static export of the WordPress site,
# the docs under /docs, and the root files Spacefast reads (_redirects, _headers, robots.txt,
# llms.txt). Publish the result with `sf publish dist` (the Spacefast CLI), or with
# `studio spacefast publish` once it can include a folder next to the export.
#
# Needs the Studio CLI with `export-static` (Automattic/studio#5567), Node 22.19+ and pnpm.
set -euo pipefail
cd "$(dirname "$0")/.."

# The export refuses an output folder inside the site, so it goes to a temporary folder
# first and becomes dist/ afterwards.
echo "Exporting the WordPress site…"
export_dir="$(mktemp -d "${TMPDIR:-/tmp}/studio-website-static.XXXXXX")"
trap 'rm -rf "$export_dir"' EXIT
studio export-static "$export_dir" --overwrite
rm -rf dist
mv "$export_dir" dist

echo "Building the docs…"
( cd docs && pnpm run --silent sync && pnpm run --silent build )
rm -rf dist/docs
cp -R docs/dist dist/docs

for file in _redirects _headers robots.txt llms.txt; do
	cp "$file" dist/
done
# Spacefast renders .md files as pages and refuses dot directories: see the script.
node docs/scripts/finalize-site.mjs dist

echo "Site built in dist/ ($(find dist -type f | wc -l | tr -d ' ') files)"
