#!/usr/bin/env bash
# Build wordpress.studio and publish it to Spacefast, in one go: scripts/build-site.sh, then
# `sf publish dist`. Needs everything the build needs (see the README) and membership of the
# Spacefast team that owns the Space (`sf login`).
#
# Usage: scripts/publish-site.sh [preview|production] [sf publish flags…]
#   The target defaults to production. The CLI asks for confirmation before it publishes; pass -y to
#   skip that. Extra flags go to `sf publish` (for example -m "Docs: new blueprint page").
set -euo pipefail
cd "$(dirname "$0")/.."

SPACE="spc_95b3647e99974586b104ab2cf1050dd6"
target="production"
if [[ "${1:-}" == "preview" || "${1:-}" == "production" ]]; then
	target="$1"
	shift
fi

scripts/build-site.sh

echo "Publishing dist/ to Spacefast ($target)…"
sf publish dist --root-directory . --space "$SPACE" --target "$target" --wait "$@"

if [[ "$target" == "production" ]]; then
	echo
	node scripts/check-docs-freshness.mjs || true
fi
