// Sync the Studio docs from the Automattic/studio repository into content/.
//
// The markdown in docs/user/docs of that repository is the source of truth, and its
// screenshots come from docs/user/screenshots/images there. This script checks both out,
// maps each page onto the sidebar structure defined in PAGES, and rewrites what the
// markdown mirror of developer.wordpress.com carried over from HTML: heading links, linked
// images, absolute links to the old docs, and command lines that lost their code fences.
// Nothing is fetched from developer.wordpress.com.
//
// Usage: pnpm run sync         (STUDIO_DOCS_REF=branch-or-sha to pin a revision)

import { execFileSync } from "node:child_process";
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { basename, dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const contentDir = join(root, "content");
const imagesDir = join(root, "public", "images");
const cacheDir = join(root, ".cache", "studio");

const SOURCE = {
	repo: "https://github.com/Automattic/studio.git",
	// The finished 2.0 docs live on Automattic/studio#5772, stacked on the docs refresh
	// (Automattic/studio#5517); switch the default to "trunk" once both merge.
	ref: process.env.STUDIO_DOCS_REF || "add/docs-kept-images",
	path: "docs/user/docs",
	// Screenshots captured by `npm run screenshots` in the Studio repo (npm there, pnpm here). A page still refers
	// to the copy that was uploaded to developer.wordpress.com; the repo's file of the same
	// name is used instead.
	images: "docs/user/screenshots/images",
};

// The old docs lived under this prefix; links to it become local routes.
const OLD_DOCS = "https://developer.wordpress.com/docs/developer-tools/studio";
const OLD_UPLOADS = "https://developer.wordpress.com/wp-content/uploads/";

// src: path under docs/user/docs. dest: path under content/ (group folders in
// parentheses add a sidebar section without a URL segment). old: the page's path
// under the old docs, used to rewrite links between pages.
const PAGES = [
	{ src: "developer-tools/studio/index.md", dest: "index.md", old: "", label: "Overview" },

	{ src: "developer-tools/studio/sites.md", dest: "(use-studio)/sites.md", old: "sites", label: "Sites" },
	{ src: "developer-tools/studio/blueprints/index.md", dest: "(use-studio)/blueprints/index.md", old: "blueprints", label: "Blueprints" },
	{ src: "developer-tools/studio/blueprints/open-in-wordpress-studio-button.md", dest: "(use-studio)/blueprints/open-in-wordpress-studio-button.md", old: "blueprints/open-in-wordpress-studio-button", label: "Open in Studio button" },
	{ src: "guides/how-to-create-custom-blueprints.md", dest: "(use-studio)/blueprints/how-to-create-custom-blueprints.md", old: null, oldUrl: "https://developer.wordpress.com/docs/guides/how-to-create-custom-blueprints", label: "Custom Blueprints" },
	{ src: "developer-tools/studio/sync.md", dest: "(use-studio)/sync.md", old: "sync", label: "Sync" },
	{ src: "developer-tools/studio/preview-sites.md", dest: "(use-studio)/preview-sites.md", old: "preview-sites", label: "Preview sites" },
	{ src: "developer-tools/studio/import-export.md", dest: "(use-studio)/import-export.md", old: "import-export", label: "Import and export" },

	{ src: "developer-tools/studio/studio-code/index.md", dest: "(build-with-ai)/studio-code/index.md", old: "studio-code" },
	{ src: "developer-tools/studio/studio-code/choosing-an-ai-model-tier-in-studio-code.md", dest: "(build-with-ai)/studio-code/choosing-an-ai-model-tier-in-studio-code.md", old: "studio-code/choosing-an-ai-model-tier-in-studio-code", label: "Model tiers" },
	{ src: "developer-tools/studio/studio-code/ai-credits-guidelines.md", dest: "(build-with-ai)/studio-code/ai-credits-guidelines.md", old: "studio-code/ai-credits-guidelines", label: "AI credits" },
	{ src: "developer-tools/studio/studio-code/image-generation.md", dest: "(build-with-ai)/studio-code/image-generation.md", old: "studio-code/image-generation" },
	{ src: "developer-tools/studio/agent-skills-wordpress-studio.md", dest: "(build-with-ai)/agent-skills-wordpress-studio.md", old: "agent-skills-wordpress-studio", label: "Agent skills" },
	{ src: "developer-tools/studio/mcp-on-studio.md", dest: "(build-with-ai)/mcp-on-studio.md", old: "mcp-on-studio", label: "MCP server" },

	{ src: "developer-tools/studio/cli.md", dest: "(developer-tools)/cli.md", old: "cli", label: "Studio CLI" },
	{ src: "developer-tools/studio/ssl-in-studio.md", dest: "(developer-tools)/ssl-in-studio.md", old: "ssl-in-studio", label: "SSL and custom domains" },
	{ src: "developer-tools/studio/php-runtimes.md", dest: "(developer-tools)/php-runtimes.md", old: "php-runtimes" },
	{ src: "developer-tools/studio/debugging/index.md", dest: "(developer-tools)/debugging.md", old: "debugging", label: "Debugging" },
	{ src: "developer-tools/studio/xdebug.md", dest: "(developer-tools)/xdebug.md", old: "xdebug", label: "Xdebug" },

	{ src: "developer-tools/studio/frequently-asked-questions.md", dest: "(help)/frequently-asked-questions.md", old: "frequently-asked-questions", label: "FAQ" },
	{ src: "developer-tools/studio/changelog.md", dest: "(help)/changelog.md", old: "changelog", label: "Changelog" },
	{ src: "developer-tools/studio/feedback/index.md", dest: "(help)/feedback.md", old: "feedback", label: "Feedback" },
];

// Old paths that pointed at a page under another slug.
const OLD_ALIASES = { ssl: "ssl-in-studio" };

const routeOf = (dest) => "/" + dest.replace(/\([^)]*\)\//g, "").replace(/(^|\/)index\.md$/, "").replace(/\.md$/, "");

// Mirrors github-slugger, which Blume uses for heading ids.
const slug = (text) =>
	text
		.toLowerCase()
		.trim()
		.replace(/[^\p{L}\p{N}\s_-]/gu, "")
		.replace(/\s/g, "-");

const COMMAND = /^(studio|curl|irm|npm|npx|wp|brew|cd|git|composer|php|sudo|export|\$)\b/;
const COMMENT = /^\\?#\s/;
const HTML_TAGS = new Set(["br", "hr", "img", "kbd", "sup", "sub", "a", "p", "b", "i", "em", "strong", "code", "pre", "div", "span", "table", "tr", "td", "th", "ul", "ol", "li"]);

function checkout() {
	rmSync(cacheDir, { recursive: true, force: true });
	mkdirSync(dirname(cacheDir), { recursive: true });
	const git = (...args) => execFileSync("git", args, { cwd: cacheDir, stdio: ["ignore", "ignore", "inherit"] });
	mkdirSync(cacheDir);
	git("init", "-q");
	git("remote", "add", "origin", SOURCE.repo);
	git("sparse-checkout", "set", SOURCE.path, SOURCE.images);
	git("fetch", "-q", "--depth", "1", "--filter=blob:none", "origin", SOURCE.ref);
	git("checkout", "-q", "FETCH_HEAD");
	return execFileSync("git", ["rev-parse", "--short", "HEAD"], { cwd: cacheDir }).toString().trim();
}

// Pass 1: read every page, lift its title, and map the anchors of its old headings
// (`## [Text](#0-text)`) to the ids Blume will generate from the heading text.
function readPage(page) {
	const file = join(cacheDir, SOURCE.path, page.src);
	let lines = readFileSync(file, "utf8").split("\n");
	const anchors = {};
	let title = page.title;
	let description = page.description;
	// A source file that already carries frontmatter (title, description) keeps it.
	if (lines[0] === "---") {
		const end = lines.indexOf("---", 1);
		for (const l of lines.slice(1, end)) {
			const m = l.match(/^(title|description):\s*(.+)$/);
			if (!m) continue;
			const value = m[2].startsWith('"') ? JSON.parse(m[2]) : m[2].trim();
			if (m[1] === "title") title = value;
			else description = value;
		}
		lines = lines.slice(end + 1);
	}
	lines = lines.map((line) => {
		const h = line.match(/^(#{1,6}) \[(.+?)\]\(#([^)]*)\)\s*$/);
		if (h) {
			anchors[h[3]] = slug(h[2]);
			return `${h[1]} ${h[2]}`;
		}
		return line;
	});
	if (!title && lines[0]?.startsWith("# ")) {
		title = lines[0].slice(2).trim();
		lines.shift();
	}
	// Without an authored description, a short opening paragraph becomes the lede
	// (Blume prints it under the title) and leaves the body; a long one lends its
	// first sentence and stays.
	if (!description) {
		const i = lines.findIndex((l) => l.trim());
		const first = lines[i] ?? "";
		const plain = describe([first]);
		if (i >= 0 && plain && !first.startsWith("#") && !first.startsWith("!") && !first.startsWith("-") && !/^\d+\./.test(first)) {
			if (first.length <= 220) {
				description = plain;
				lines.splice(i, 1);
			} else {
				description = plain.includes(". ") ? plain.slice(0, plain.indexOf(". ") + 1) : plain;
			}
		}
	}
	return { ...page, title, description, anchors, lines, route: routeOf(page.dest) };
}

function describe(lines) {
	const para = lines.find((l) => l.trim() && !l.startsWith("#") && !l.startsWith("[![") && !l.startsWith("![") && !l.startsWith("-") && !/^\d+\./.test(l));
	if (!para) return "";
	let text = para
		.replace(/!\[[^\]]*\]\([^)]*\)/g, "")
		.replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
		.replace(/[*_`]/g, "")
		.replace(/\s+/g, " ")
		.trim();
	return text;
}

function fenceCommands(lines) {
	const out = [];
	let i = 0;
	let fenced = false;
	while (i < lines.length) {
		if (lines[i].startsWith("```")) {
			fenced = !fenced;
			out.push(lines[i++]);
			continue;
		}
		if (fenced || !lines[i].trim()) {
			out.push(lines[i++]);
			continue;
		}
		// A paragraph: consecutive non-empty lines.
		let j = i;
		while (j < lines.length && lines[j].trim() && !lines[j].startsWith("```")) j++;
		const para = lines.slice(i, j);
		const isCommands = para.some((l) => COMMAND.test(l)) && para.every((l) => COMMAND.test(l) || COMMENT.test(l));
		if (isCommands) {
			out.push("```bash", ...para.map((l) => l.replace(/^\\#/, "#").replace(/\\([\[\]])/g, "$1")), "```");
		} else {
			out.push(...para);
		}
		i = j;
	}
	return out;
}

const images = new Map();

function rewrite(text, page, routes) {
	// Linked images become plain images.
	text = text.replace(/\[(!\[[^\]]*\]\([^)]+\))\]\([^)]+\)/g, "$1");
	// Empty links (not the empty alt of an image).
	text = text.replace(/(^|[^!])\[\]\([^)]*\)/g, "$1");
	// Screenshots: an upload URL becomes the repo's file of the same name under public/images.
	text = text.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (m, alt, src) => {
		if (!src.startsWith(OLD_UPLOADS)) return m;
		const name = basename(src.replace(/\?.*$/, ""));
		images.set(name, page.dest);
		return `![${alt}](/images/${name})`;
	});
	// Links to the old docs become local routes, carrying their anchors over.
	text = text.replace(/\]\(([^)\s]+)\)/g, (m, href) => {
		let target = null;
		let anchor = "";
		const [url, hash] = href.split("#");
		for (const p of routes) {
			if (p.oldUrl && (url === p.oldUrl || url === p.oldUrl + "/")) target = p;
		}
		if (!target && url.startsWith(OLD_DOCS)) {
			let rest = url.slice(OLD_DOCS.length).replace(/^\/|\/$/g, "");
			rest = OLD_ALIASES[rest] ?? rest;
			target = routes.find((p) => p.old === rest) ?? null;
		}
		if (url === "" && hash !== undefined) target = page;
		if (!target) {
			if (url.startsWith("https://developer.wordpress.com/studio")) {
				return `](https://wordpress.studio/${url.includes("/download") ? "download/" : ""})`;
			}
			return m;
		}
		// Carry an anchor over when it names a heading on the target page; drop a stray one.
		if (hash !== undefined) {
			const ids = new Set(Object.values(target.anchors));
			const id = target.anchors[hash] ?? (ids.has(slug(hash)) ? slug(hash) : null);
			if (id) anchor = "#" + id;
		}
		const path = target === page ? "" : target.route;
		return `](${path}${anchor})`;
	});
	// Placeholders like <host> in prose would be read as HTML. Code, fenced or inline, is left alone.
	let fenced = false;
	text = text
		.split("\n")
		.map((line) => {
			if (line.startsWith("```")) fenced = !fenced;
			if (fenced || line.startsWith("```")) return line;
			// Split on inline code spans; even segments are prose.
			return line
				.split(/(`[^`]*`)/)
				.map((part, i) => (i % 2 ? part : part.replace(/(^|[^\\])<([a-z][a-z0-9-]*)>/g, (m, pre, name) => (HTML_TAGS.has(name) ? m : `${pre}\`<${name}>\``))))
				.join("");
		})
		.join("\n");
	return text;
}

function frontmatter(page, description) {
	const esc = (s) => JSON.stringify(s);
	const lines = [`title: ${esc(page.title)}`];
	if (description) lines.push(`description: ${esc(description)}`);
	if (page.label) lines.push("sidebar:", `  label: ${esc(page.label)}`);
	return ["---", ...lines, "---", ""].join("\n");
}

function copyImages() {
	rmSync(imagesDir, { recursive: true, force: true });
	mkdirSync(imagesDir, { recursive: true });
	let missing = 0;
	for (const [name, dest] of images) {
		const own = join(cacheDir, SOURCE.images, name);
		if (!existsSync(own)) {
			console.warn(`  ! ${dest} uses ${name}, which is not in ${SOURCE.images}`);
			missing++;
			continue;
		}
		copyFileSync(own, join(imagesDir, name));
	}
	return missing;
}

const sha = checkout();
console.log(`Automattic/studio ${SOURCE.ref} (${sha}), ${SOURCE.path}`);

const pages = PAGES.map(readPage);
for (const page of pages) {
	const body = rewrite(fenceCommands(page.lines).join("\n"), page, pages).replace(/\n{3,}/g, "\n\n").trim() + "\n";
	const file = join(contentDir, page.dest);
	mkdirSync(dirname(file), { recursive: true });
	writeFileSync(file, frontmatter(page, page.description) + "\n" + body);
}
console.log(`${pages.length} pages written to content/`);
const missing = copyImages();
console.log(`${images.size} screenshots copied to public/images/${missing ? `, ${missing} missing` : ""}`);
if (missing) process.exitCode = 1;
