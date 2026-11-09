#!/usr/bin/env bash
# Download the pinned WordPress core used by the plain-PHP tests into tests/.deps/wordpress. Only
# wpautop, the kses/esc_url helpers, the HTML API and the block parser are loaded. Any WordPress
# install works instead: set WP_DOCS_TEST_WORDPRESS to its path (a Studio site, for example).
set -euo pipefail
cd "$(dirname "$0")"
WP_VERSION=7.1.4
mkdir -p .deps
if [ ! -d .deps/wordpress ]; then
	curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" | tar xz -C .deps
fi
echo "WordPress ${WP_VERSION} in tests/.deps/"
