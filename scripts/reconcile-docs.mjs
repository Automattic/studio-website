// Carry docs edits made in WordPress back to Automattic/studio.
//
// The docs pages are imported from the markdown in Automattic/studio and every import rewrites them,
// so an edit made in the block editor would be lost. `studio wp wp-docs drift --export` writes such
// edits back as markdown at their upstream paths (the studio-docs plugin merges the page's blocks into
// the file it was imported from, so the diff is the edit and nothing else). This script takes that
// export, checks Automattic/studio `trunk` out shallowly (the same sparse checkout the staging script
// makes), applies the files on a branch and prints the diff. Nothing leaves this machine unless --pr
// is given: then the branch is pushed and a pull request against `trunk` is opened with `gh`, its
// body naming the pages, who edited them on the site and when.
//
// Usage: node scripts/reconcile-docs.mjs [options]
//   --from <dir>       use an existing export (default: run `studio wp wp-docs drift --export` now)
//   --pr               push the branch and open the pull request (default: dry run, nothing pushed)
//   --branch <name>    branch name (default: docs/site-edits-<yyyy-mm-dd>)
//   --site <path>      the Studio site to export from (default: this checkout)
//   --keep-checkout    keep the checkout in build/docs/.cache/reconcile after --pr (a dry run always keeps it)
//   STUDIO_DOCS_REF=<branch>  base the branch on another ref than trunk (testing)
//
// Needs git, the Studio CLI (for the export) and, with --pr, the GitHub CLI logged in with push
// access to Automattic/studio.

import { execFileSync, spawnSync } from "node:child_process";
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const SOURCE = {
	repo: "https://github.com/Automattic/studio.git",
	slug: "Automattic/studio",
	ref: process.env.STUDIO_DOCS_REF || "trunk",
	paths: ["docs/user/docs", "docs/user/screenshots/images"],
};

const args = process.argv.slice(2);
const option = (name) => {
	const i = args.indexOf(name);
	return i >= 0 ? args[i + 1] : undefined;
};
const flag = (name) => args.includes(name);
if (flag("--help") || flag("-h")) {
	console.log(readFileSync(fileURLToPath(import.meta.url), "utf8").split("\n").filter((l) => l.startsWith("//")).map((l) => l.slice(3)).join("\n"));
	process.exit(0);
}
const site = option("--site") ? resolve(option("--site")) : root;
const openPr = flag("--pr");
const today = new Date().toISOString().slice(0, 10);
const branch = option("--branch") || `docs/site-edits-${today}`;
const checkoutDir = join(root, "build", "docs", ".cache", "reconcile");
let exportDir = option("--from") ? resolve(option("--from")) : join(root, "build", "docs-reconcile");

const run = (cmd, cmdArgs, opts = {}) => execFileSync(cmd, cmdArgs, { stdio: ["ignore", "pipe", "inherit"], encoding: "utf8", ...opts }).trim();
const git = (...gitArgs) => run("git", gitArgs, { cwd: checkoutDir });

// 1. A fresh shallow checkout of the docs, like scripts/build-docs-manifest.mjs makes.
console.log(`Checking out ${SOURCE.slug} ${SOURCE.ref} (${SOURCE.paths.join(", ")})…`);
rmSync(checkoutDir, { recursive: true, force: true });
mkdirSync(checkoutDir, { recursive: true });
git("init", "-q");
git("remote", "add", "origin", SOURCE.repo);
git("sparse-checkout", "set", ...SOURCE.paths);
git("fetch", "-q", "--depth", "1", "--filter=blob:none", "origin", SOURCE.ref);
git("checkout", "-q", "FETCH_HEAD");
const base = git("rev-parse", "HEAD");
console.log(`  ${SOURCE.ref} is at ${base.slice(0, 7)}`);

// 2. The export: the reconciled markdown at upstream paths, replayed onto this checkout.
if (!option("--from")) {
	console.log("Exporting the pages edited in WordPress…");
	rmSync(exportDir, { recursive: true, force: true });
	const result = spawnSync("studio", ["wp", "--path", site, "wp-docs", "drift", `--export=${exportDir}`, `--upstream=${checkoutDir}`], { encoding: "utf8" });
	process.stdout.write(result.stdout || "");
	if (result.error) {
		console.error(`Could not run the Studio CLI: ${result.error.message}`);
		process.exit(2);
	}
	if (result.status === 0) {
		console.log("No drift: nothing to reconcile.");
		cleanup();
		process.exit(0);
	}
	if (!existsSync(join(exportDir, "drift.json"))) {
		console.error(result.stderr || "");
		console.error("The export did not produce drift.json; see the output above.");
		process.exit(2);
	}
}
const index = JSON.parse(readFileSync(join(exportDir, "drift.json"), "utf8"));
if (option("--from") && index.upstream_commit && index.upstream_commit !== base) {
	console.error(`The export in ${exportDir} was replayed onto ${SOURCE.slug} at ${index.upstream_commit.slice(0, 7)}, but ${SOURCE.ref} is now at ${base.slice(0, 7)}: applying it would undo upstream changes.`);
	console.error("Run without --from so the pages are exported against the current checkout.");
	process.exit(2);
}
const pages = index.pages || [];
if (!pages.length) {
	console.log("The export lists no pages: nothing to reconcile.");
	cleanup();
	process.exit(0);
}
const staged = pages.filter((p) => p.form !== "upstream");
if (staged.length) {
	console.error(`These pages were exported in their staged form, not at their upstream path, so they cannot be applied: ${staged.map((p) => p.path).join(", ")}.`);
	console.error("Run the export against a checkout (this script does that when --from is omitted).");
	process.exit(2);
}

// 3. Apply the files on a branch and show the diff.
git("checkout", "-q", "-b", branch);
for (const page of pages) {
	const target = join(checkoutDir, page.source);
	mkdirSync(dirname(target), { recursive: true });
	copyFileSync(page.file, target);
	git("add", "--", page.source);
}
const stat = git("diff", "--cached", "--stat");
if (!stat) {
	console.log("The exported pages are identical to trunk: nothing to reconcile (the edits may already be upstream).");
	cleanup();
	process.exit(0);
}
console.log(`\nChanges against ${SOURCE.ref} (${base.slice(0, 7)}):\n`);
console.log(git("diff", "--cached"));
console.log(stat);

const title = pages.length === 1 ? `Docs: ${pages[0].title} (edited on wordpress.studio)` : `Docs: ${pages.length} pages edited on wordpress.studio`;
const body = prBody(pages, index, base);
git("commit", "-q", "-m", `${title}\n\n${body}`);
console.log(`\nCommitted on ${branch} in ${checkoutDir} (a fresh checkout; the next run replaces it).`);

const notes = pages.flatMap((p) => [...(p.warnings || []), ...(p.verify ? [`block ${p.verify.index + 1} (${p.verify.expected}) does not round-trip`] : [])].map((w) => `${p.path}: ${w}`));
if (notes.length) {
	console.log("\nLook at these before merging:");
	for (const note of notes) console.log(`  ! ${note}`);
}

if (!openPr) {
	console.log(`\nDry run: nothing pushed. Re-run with --pr to push ${branch} and open a pull request against ${SOURCE.slug} ${SOURCE.ref}.`);
	process.exit(0);
}

// 4. Push and open the pull request (gh supplies the credentials for the push too).
console.log(`\nPushing ${branch}…`);
git("-c", "credential.helper=", "-c", "credential.helper=!gh auth git-credential", "push", "-q", "-u", "origin", branch);
const bodyFile = join(checkoutDir, ".pr-body.md");
writeFileSync(bodyFile, body);
const url = run("gh", ["pr", "create", "--repo", SOURCE.slug, "--base", SOURCE.ref, "--head", branch, "--title", title, "--body-file", bodyFile]);
console.log(`Pull request: ${url}`);
cleanup();

function prBody(pages, index, base) {
	const lines = [
		`These pages were edited in the WordPress editor on ${index.site || "wordpress.studio"} after being imported from ${SOURCE.slug}@${(index.source && index.source.commit ? index.source.commit.slice(0, 7) : "trunk")}. \`studio wp wp-docs drift --export\` merged the edits back into the markdown (unchanged chunks are byte for byte the imported ones), and \`scripts/reconcile-docs.mjs\` in Automattic/studio-website applied them on ${SOURCE.ref} at ${base.slice(0, 7)}.`,
		"",
		"| Page | File | Changed | Edited by | Edited at |",
		"|---|---|---|---|---|",
	];
	for (const p of pages) {
		lines.push(`| ${p.path} | \`${p.source}\` | ${(p.changed || []).join(", ")} | ${p.edited_by} | ${p.edited_at} |`);
	}
	const notes = pages.flatMap((p) => [...(p.warnings || []), ...(p.verify ? [`block ${p.verify.index + 1} (${p.verify.expected}) does not round-trip through markdown; check that part`] : [])].map((w) => `- ${p.path}: ${w}`));
	if (notes.length) {
		lines.push("", "Please look at these:", "", ...notes);
	}
	lines.push("", "Once this merges, the next build of wordpress.studio imports the pages from trunk again and the site and the repository agree.");
	return lines.join("\n");
}

function cleanup() {
	if (!flag("--keep-checkout")) rmSync(checkoutDir, { recursive: true, force: true });
}
// (A dry run keeps the checkout so the branch can be looked at; cleanup() runs after a push or when
// there was nothing to do.)
