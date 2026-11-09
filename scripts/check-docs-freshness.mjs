// Is wordpress.studio/docs up to date with the docs in Automattic/studio?
//
// The site publishes the revision its docs were imported from in /docs/index.json ("source":
// repository, ref, commit, paths; scripts/build-docs-manifest.mjs stages it, the importer records it).
// This script compares that commit with the tip of the same ref on GitHub and lists the upstream
// commits since then that touched the docs or their screenshots. Nothing runs locally: it works
// from any machine with network access.
//
// Usage: node scripts/check-docs-freshness.mjs [--site https://wordpress.studio] [--local]
//   --site <url>   the site to check (default: https://wordpress.studio)
//   --local        check build/docs/source.json (the last local staging) instead of the site
//   GITHUB_TOKEN / GH_TOKEN raises the GitHub API rate limit.
//
// Exit code: 0 up to date, 1 behind (a rebuild and publish is due), 2 could not tell.

import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const args = process.argv.slice(2);
const flag = (name) => {
	const i = args.indexOf(name);
	return i === -1 ? undefined : args[i + 1];
};
const site = (flag("--site") ?? "https://wordpress.studio").replace(/\/$/, "");
const local = args.includes("--local");

const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
const headers = { Accept: "application/vnd.github+json", "User-Agent": "studio-website-docs-check" };
if (token) headers.Authorization = `Bearer ${token}`;

async function getJson(url, options = {}) {
	const response = await fetch(url, options);
	if (!response.ok) {
		const error = new Error(`${url}: HTTP ${response.status} ${response.statusText}`);
		error.status = response.status;
		if (response.status === 403 && response.headers.get("x-ratelimit-remaining") === "0") {
			error.message = `GitHub API rate limit reached; set GITHUB_TOKEN (for example GITHUB_TOKEN=$(gh auth token)) and try again`;
		}
		throw error;
	}
	return response.json();
}

function fail(message) {
	console.error(message);
	process.exit(2);
}

// 1. What the site (or the last local staging) was built from.
let source;
let origin;
try {
	if (local) {
		origin = "build/docs/source.json";
		source = JSON.parse(readFileSync(join(root, "build", "docs", "source.json"), "utf8"));
	} else {
		origin = `${site}/docs/index.json`;
		source = (await getJson(origin, { headers: { Accept: "application/json" } })).source;
	}
} catch (error) {
	fail(`Could not read ${origin}: ${error.message}`);
}
if (!source?.commit || !source.repository || !source.ref) {
	fail(`${origin} records no docs source. It was built before the source was recorded: rebuild and publish once, then check again.`);
}
const paths = source.paths?.length ? source.paths : ["docs/user/docs", "docs/user/screenshots/images"];
console.log(`${local ? "Staged" : "Published"} docs: ${source.repository}@${source.ref} at ${source.commit.slice(0, 7)}${source.staged ? ` (staged ${source.staged.slice(0, 10)})` : ""}`);

// 2. What the ref is at now, and what changed in the docs paths since.
const api = `https://api.github.com/repos/${source.repository}`;
let head;
try {
	head = (await getJson(`${api}/commits/${encodeURIComponent(source.ref)}`, { headers })).sha;
} catch (error) {
	const hint = error.status === 404 || error.status === 422 ? ` Was the branch merged or deleted? Then STUDIO_DOCS_REF in the build needs a new default.` : "";
	fail(`Could not read the tip of ${source.ref}: ${error.message}.${hint}`);
}
if (head.startsWith(source.commit) || source.commit.startsWith(head)) {
	console.log(`Up to date: ${source.ref} is still at ${head.slice(0, 7)}.`);
	process.exit(0);
}

let compare;
try {
	compare = await getJson(`${api}/compare/${source.commit}...${head}?per_page=250`, { headers });
} catch (error) {
	fail(`Could not compare ${source.commit.slice(0, 7)} with ${head.slice(0, 7)}: ${error.message}`);
}
if (compare.status === "diverged" || compare.status === "behind") {
	console.log(`Note: the published commit is not an ancestor of ${source.ref} (${compare.status}); the build may have used another branch or a rewritten one.`);
}
const changed = (compare.files ?? []).filter((file) => paths.some((path) => file.filename.startsWith(path + "/")));
if (changed.length === 0) {
	console.log(`Up to date: ${source.ref} moved to ${head.slice(0, 7)} (${compare.ahead_by} commits), but nothing under ${paths.join(" or ")} changed.`);
	process.exit(0);
}

// The commits behind those files, newest first, for the message.
const commits = (compare.commits ?? []).slice().reverse();
console.log(`\nBehind: ${changed.length} docs file${changed.length === 1 ? "" : "s"} changed in ${source.ref} since ${source.commit.slice(0, 7)} (now ${head.slice(0, 7)}):`);
for (const file of changed) {
	console.log(`  ${file.status.padEnd(8)} ${file.filename}`);
}
if (commits.length) {
	console.log(`\nCommits since then (${compare.ahead_by} in total, newest first):`);
	for (const commit of commits.slice(0, 15)) {
		console.log(`  ${commit.sha.slice(0, 7)} ${commit.commit.committer.date.slice(0, 10)} ${commit.commit.message.split("\n")[0]}`);
	}
	if (commits.length > 15) console.log(`  … and ${commits.length - 15} more`);
}
console.log(`\nRebuild and publish: npm run deploy`);
process.exit(1);
