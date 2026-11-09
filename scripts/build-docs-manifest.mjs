// Stage the Studio docs for the WordPress importer (the studio-docs plugin).
//
// The markdown in docs/user/docs of Automattic/studio is the source of truth, and its screenshots
// come from docs/user/screenshots/images there. This script checks both out, maps each page onto the
// sidebar defined in PAGES, cleans up what the markdown mirror of developer.wordpress.com carried
// over from HTML (heading links, linked images, absolute links to the old docs, command lines that
// lost their code fences), and writes to build/docs/:
//
//   manifest.json     the importer's manifest (pages, titles, labels, groups, order)
//   content/**/*.md   the cleaned markdown, one file per page, linking to each other relatively
//   images/           the screenshots the pages use
//   highlights.json   every fenced code block highlighted with Shiki (github-light / github-dark),
//                     keyed by language and code, so the code block looks the same as it did in Blume
//
// Then `studio wp wp-docs import --force` imports it (scripts/build-site.sh runs both).
//
// Usage: node scripts/build-docs-manifest.mjs
//   STUDIO_DOCS_REF=<branch-or-sha>   pin a revision (default: ui-refresh-user-docs)
//   STUDIO_DOCS_SOURCE=<path>         use a local checkout of Automattic/studio instead of cloning
//   DOCS_BUILD_DIR=<path>             output folder (default: build/docs)

import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { basename, dirname, join, posix } from "node:path";
import { fileURLToPath } from "node:url";
import { codeToHtml } from "shiki";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const out = process.env.DOCS_BUILD_DIR || join(root, "build", "docs");
const contentDir = join(out, "content");
const imagesDir = join(out, "images");
const cacheDir = join(out, ".cache", "studio");

const SOURCE = {
	repo: "https://github.com/Automattic/studio.git",
	// The finished 2.0 docs live on the docs refresh branch (Automattic/studio#5517), which
	// Automattic/studio#5772 merged into; switch the default to "trunk" once it merges.
	ref: process.env.STUDIO_DOCS_REF || "ui-refresh-user-docs",
	path: "docs/user/docs",
	// Screenshots captured by `npm run screenshots` in the Studio repo. A page still refers to the
	// copy that was uploaded to developer.wordpress.com; the repo's file of the same name is used.
	images: "docs/user/screenshots/images",
};

// The old docs lived under this prefix; links to it become links between pages.
const OLD_DOCS = "https://developer.wordpress.com/docs/developer-tools/studio";
const OLD_UPLOADS = "https://developer.wordpress.com/wp-content/uploads/";

// Sidebar groups, in order. Pages name their group; a group has no page of its own.
const GROUPS = {
	"use-studio": "Use Studio",
	"build-with-ai": "Build with AI",
	"developer-tools": "Developer tools",
	help: "Help",
};

// src: path under docs/user/docs. route: the page's path under /docs/ ("" is the home). old: the
// page's path under the old docs, used to rewrite links between pages. label: the sidebar label when
// it differs from the title. The order here is the sidebar order (and the previous/next order).
const PAGES = [
	{ src: "developer-tools/studio/index.md", route: "", old: "", label: "Overview" },

	{ src: "developer-tools/studio/sites.md", route: "sites", old: "sites", label: "Sites", group: "use-studio" },
	{ src: "developer-tools/studio/sync.md", route: "sync", old: "sync", label: "Sync", group: "use-studio" },
	{ src: "developer-tools/studio/preview-sites.md", route: "preview-sites", old: "preview-sites", label: "Preview sites", group: "use-studio" },
	{ src: "developer-tools/studio/import-export.md", route: "import-export", old: "import-export", label: "Import and export", group: "use-studio" },
	{ src: "developer-tools/studio/blueprints/index.md", route: "blueprints", old: "blueprints", label: "Blueprints", group: "use-studio" },
	{ src: "guides/how-to-create-custom-blueprints.md", route: "blueprints/how-to-create-custom-blueprints", old: null, oldUrl: "https://developer.wordpress.com/docs/guides/how-to-create-custom-blueprints", label: "Custom Blueprints", group: "use-studio" },
	{ src: "developer-tools/studio/blueprints/open-in-wordpress-studio-button.md", route: "blueprints/open-in-wordpress-studio-button", old: "blueprints/open-in-wordpress-studio-button", label: "Open in Studio button", group: "use-studio" },

	{ src: "developer-tools/studio/agent-skills-wordpress-studio.md", route: "agent-skills-wordpress-studio", old: "agent-skills-wordpress-studio", label: "Agent skills", group: "build-with-ai" },
	{ src: "developer-tools/studio/mcp-on-studio.md", route: "mcp-on-studio", old: "mcp-on-studio", label: "MCP server", group: "build-with-ai" },
	{ src: "developer-tools/studio/studio-code/index.md", route: "studio-code", old: "studio-code", group: "build-with-ai", heading: "Studio Code" },
	{ src: "developer-tools/studio/studio-code/ai-credits-guidelines.md", route: "studio-code/ai-credits-guidelines", old: "studio-code/ai-credits-guidelines", label: "AI credits", group: "build-with-ai" },
	{ src: "developer-tools/studio/studio-code/image-generation.md", route: "studio-code/image-generation", old: "studio-code/image-generation", group: "build-with-ai" },
	{ src: "developer-tools/studio/studio-code/choosing-an-ai-model-tier-in-studio-code.md", route: "studio-code/choosing-an-ai-model-tier-in-studio-code", old: "studio-code/choosing-an-ai-model-tier-in-studio-code", label: "Model tiers", group: "build-with-ai" },

	{ src: "developer-tools/studio/cli.md", route: "cli", old: "cli", label: "Studio CLI", group: "developer-tools" },
	{ src: "developer-tools/studio/ssl-in-studio.md", route: "ssl-in-studio", old: "ssl-in-studio", label: "SSL and custom domains", group: "developer-tools" },
	{ src: "developer-tools/studio/php-runtimes.md", route: "php-runtimes", old: "php-runtimes", group: "developer-tools" },
	{ src: "developer-tools/studio/debugging/index.md", route: "debugging", old: "debugging", label: "Debugging", group: "developer-tools" },
	{ src: "developer-tools/studio/xdebug.md", route: "xdebug", old: "xdebug", label: "Xdebug", group: "developer-tools" },

	{ src: "developer-tools/studio/frequently-asked-questions.md", route: "frequently-asked-questions", old: "frequently-asked-questions", label: "FAQ", group: "help" },
	{ src: "developer-tools/studio/changelog.md", route: "changelog", old: "changelog", label: "Changelog", group: "help" },
	{ src: "developer-tools/studio/feedback/index.md", route: "feedback", old: "feedback", label: "Feedback", group: "help" },
];

// Old paths that pointed at a page under another slug.
const OLD_ALIASES = { ssl: "ssl-in-studio" };

const isParent = (route) => PAGES.some((p) => p.route.startsWith(route + "/"));
const fileOf = (page) => ("" === page.route ? "content/index.md" : isParent(page.route) ? `content/${page.route}/index.md` : `content/${page.route}.md`);
const parentOf = (route) => (route.includes("/") ? route.slice(0, route.lastIndexOf("/")) : null);

// Mirrors github-slugger, which Blume used for heading ids (the importer computes the same ids).
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
	if (process.env.STUDIO_DOCS_SOURCE) {
		const dir = process.env.STUDIO_DOCS_SOURCE;
		if (!existsSync(join(dir, SOURCE.path))) {
			throw new Error(`${dir} has no ${SOURCE.path}`);
		}
		let sha = "local";
		try {
			sha = execFileSync("git", ["rev-parse", "--short", "HEAD"], { cwd: dir, stdio: ["ignore", "pipe", "ignore"] }).toString().trim();
		} catch {}
		return { dir, sha };
	}
	rmSync(cacheDir, { recursive: true, force: true });
	mkdirSync(cacheDir, { recursive: true });
	const git = (...args) => execFileSync("git", args, { cwd: cacheDir, stdio: ["ignore", "ignore", "inherit"] });
	git("init", "-q");
	git("remote", "add", "origin", SOURCE.repo);
	git("sparse-checkout", "set", SOURCE.path, SOURCE.images);
	git("fetch", "-q", "--depth", "1", "--filter=blob:none", "origin", SOURCE.ref);
	git("checkout", "-q", "FETCH_HEAD");
	return { dir: cacheDir, sha: execFileSync("git", ["rev-parse", "--short", "HEAD"], { cwd: cacheDir }).toString().trim() };
}

// Pass 1: read every page, lift its title, and map the anchors of its old headings
// (`## [Text](#0-text)`) to the ids the importer will generate from the heading text.
function readPage(page, sourceDir) {
	const file = join(sourceDir, SOURCE.path, page.src);
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
	// Without an authored description, a short opening paragraph becomes the lede (printed under
	// the title) and leaves the body; a long one lends its first sentence and stays.
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
	return { ...page, title, description, anchors, lines, file: fileOf(page) };
}

function describe(lines) {
	const para = lines.find((l) => l.trim() && !l.startsWith("#") && !l.startsWith("[![") && !l.startsWith("![") && !l.startsWith("-") && !/^\d+\./.test(l));
	if (!para) return "";
	return para
		.replace(/!\[[^\]]*\]\([^)]*\)/g, "")
		.replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
		.replace(/[*_`]/g, "")
		.replace(/\s+/g, " ")
		.trim();
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
		// A paragraph of command lines (indented up to three spaces, as a line that followed a list
		// sometimes is) becomes a fenced block.
		const para = lines.slice(i, j).map((l) => l.replace(/^ {1,3}(?=\S)/, ""));
		const isCommands = para.some((l) => COMMAND.test(l)) && para.every((l) => COMMAND.test(l) || COMMENT.test(l));
		if (isCommands) {
			out.push("```bash", ...para.map((l) => l.replace(/^\\#/, "#").replace(/\\([\[\]])/g, "$1")), "```");
		} else {
			out.push(...lines.slice(i, j));
		}
		i = j;
	}
	return out;
}

const images = new Map();

function rewrite(text, page, pages) {
	const dir = posix.dirname(page.file);
	const relativeTo = (file) => posix.relative(dir, file);
	// Linked images become plain images.
	text = text.replace(/\[(!\[[^\]]*\]\([^)]+\))\]\([^)]+\)/g, "$1");
	// Empty links (not the empty alt of an image).
	text = text.replace(/(^|[^!])\[\]\([^)]*\)/g, "$1");
	// Screenshots: an upload URL becomes the repo's file of the same name under images/, which the
	// importer sideloads into the media library.
	text = text.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (m, alt, src) => {
		if (!src.startsWith(OLD_UPLOADS)) return m;
		const name = basename(src.replace(/\?.*$/, ""));
		images.set(name, page.route || "index");
		return `![${alt}](${relativeTo("images/" + name)})`;
	});
	// Links to the old docs become relative links to the other page's markdown file, carrying their
	// anchors over; the importer turns those into page URLs.
	text = text.replace(/\]\(([^)\s]+)\)/g, (m, href) => {
		let target = null;
		let anchor = "";
		const [url, hash] = href.split("#");
		for (const p of pages) {
			if (p.oldUrl && (url === p.oldUrl || url === p.oldUrl + "/")) target = p;
		}
		if (!target && url.startsWith(OLD_DOCS)) {
			let rest = url.slice(OLD_DOCS.length).replace(/^\/|\/$/g, "");
			rest = OLD_ALIASES[rest] ?? rest;
			target = pages.find((p) => p.old === rest) ?? null;
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
		const path = target === page ? "" : relativeTo(target.file);
		return `](${path}${anchor})`;
	});
	// A quote mark wrapped in stray emphasis markers (`*“***Bold**.”`), which no parser agrees on.
	text = text.replace(/\*“\*\*\*(.+?)\*\*\.”/g, "“**$1**.”");
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

function frontmatter(page) {
	const esc = (s) => JSON.stringify(s);
	const lines = [`title: ${esc(page.title)}`];
	if (page.description) lines.push(`description: ${esc(page.description)}`);
	if (page.label) lines.push(`label: ${esc(page.label)}`);
	return ["---", ...lines, "---", ""].join("\n");
}

function copyImages(sourceDir) {
	rmSync(imagesDir, { recursive: true, force: true });
	mkdirSync(imagesDir, { recursive: true });
	let missing = 0;
	for (const [name, route] of images) {
		const own = join(sourceDir, SOURCE.images, name);
		if (!existsSync(own)) {
			console.warn(`  ! ${route} uses ${name}, which is not in ${SOURCE.images}`);
			missing++;
			continue;
		}
		copyFileSync(own, join(imagesDir, name));
	}
	return missing;
}

// Every fenced code block of a page body: [{ lang, code }]. `code` has no trailing newline, which is
// also how the importer sees it (the plugin's code block looks the highlight up by the same key).
function fencedBlocks(body) {
	const blocks = [];
	const lines = body.split("\n");
	let fence = null;
	let lang = "";
	let buffer = [];
	for (const line of lines) {
		if (fence) {
			if (new RegExp(`^\\s{0,3}${fence[0] === "`" ? "`" : "~"}{${fence.length},}\\s*$`).test(line)) {
				blocks.push({ lang, code: buffer.join("\n").replace(/\n+$/, "") });
				fence = null;
				buffer = [];
				continue;
			}
			buffer.push(line);
			continue;
		}
		const open = line.match(/^\s{0,3}(`{3,}|~{3,})\s*([^\s`]*)/);
		if (open) {
			fence = open[1];
			lang = open[2].toLowerCase();
		}
	}
	return blocks;
}

export const highlightKey = (lang, code) => createHash("sha1").update(`${lang}\n${code.replace(/\n+$/, "")}`).digest("hex");

async function highlight(blocks) {
	const highlights = {};
	for (const { lang, code } of blocks) {
		const key = highlightKey(lang, code);
		if (highlights[key]) continue;
		let html;
		const shikiLang = lang || "text";
		try {
			html = await codeToHtml(code, { lang: shikiLang, themes: { light: "github-light", dark: "github-dark" }, defaultColor: false });
		} catch {
			html = await codeToHtml(code, { lang: "text", themes: { light: "github-light", dark: "github-dark" }, defaultColor: false });
		}
		const pre = html.match(/^<pre[^>]*style="([^"]*)"[^>]*>([\s\S]*)<\/pre>\s*$/);
		if (!pre) throw new Error(`Unexpected Shiki output for a ${shikiLang} block`);
		const code_html = pre[2].replace(/^<code>/, "").replace(/<\/code>$/, "");
		highlights[key] = { lang, style: pre[1], html: code_html };
	}
	return highlights;
}

const source = checkout();
console.log(`Automattic/studio ${SOURCE.ref} (${source.sha}), ${SOURCE.path}`);

// The date of the last commit that touched a page (the "Updated" line at the end of the page). The
// checkout is shallow, so the dates come from the GitHub commits API (GITHUB_TOKEN / GH_TOKEN raises the
// rate limit); a local source checkout (STUDIO_DOCS_SOURCE) answers from its own history. A page whose
// date cannot be found gets none.
async function lastUpdated(file) {
	if (process.env.STUDIO_DOCS_SOURCE) {
		try {
			return execFileSync("git", ["log", "-1", "--format=%cs", "--", `${SOURCE.path}/${file}`], { cwd: source.dir, stdio: ["ignore", "pipe", "ignore"] }).toString().trim();
		} catch {
			return "";
		}
	}
	const repo = SOURCE.repo.replace(/^https:\/\/github\.com\//, "").replace(/\.git$/, "");
	const url = `https://api.github.com/repos/${repo}/commits?path=${encodeURIComponent(`${SOURCE.path}/${file}`)}&sha=${encodeURIComponent(SOURCE.ref)}&per_page=1`;
	const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
	const headers = { Accept: "application/vnd.github+json", "User-Agent": "studio-website-build" };
	if (token) headers.Authorization = `Bearer ${token}`;
	try {
		const response = await fetch(url, { headers });
		if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
		const [commit] = await response.json();
		return commit?.commit?.committer?.date?.slice(0, 10) ?? "";
	} catch (error) {
		console.warn(`warning: no update date for ${file} (${error.message})`);
		return "";
	}
}

rmSync(contentDir, { recursive: true, force: true });
const pages = PAGES.map((page) => readPage(page, source.dir));
const updatedDates = new Map(await Promise.all(PAGES.map(async (page) => [page.src, await lastUpdated(page.src)])));
const manifest = {};
const allBlocks = [];
let order = 0;
for (const page of pages) {
	const body = rewrite(fenceCommands(page.lines).join("\n"), page, pages).replace(/\n{3,}/g, "\n\n").trim() + "\n";
	const file = join(out, page.file);
	mkdirSync(dirname(file), { recursive: true });
	writeFileSync(file, frontmatter(page) + "\n" + body);
	allBlocks.push(...fencedBlocks(body));

	const key = page.route === "" ? "index" : page.route;
	manifest[key] = {
		id: key,
		title: page.title,
		slug: page.route === "" ? "index" : posix.basename(page.route),
		parent: parentOf(page.route),
		order: order * 10,
		path: page.route === "" ? "" : page.route + "/",
		markdown_source: page.file,
		description: page.description ?? "",
		label: page.label ?? page.title,
		heading: page.heading ?? page.label ?? page.title,
		group: page.group ?? "",
		group_title: page.group ? GROUPS[page.group] : "",
		source_url: `https://github.com/Automattic/studio/blob/${SOURCE.ref}/${SOURCE.path}/${page.src}`,
		updated: updatedDates.get(page.src) || "",
	};
	order++;
}
writeFileSync(join(out, "manifest.json"), JSON.stringify(manifest, null, "\t") + "\n");
console.log(`${pages.length} pages written to ${posix.relative(root, contentDir)}/`);

const missing = copyImages(source.dir);
console.log(`${images.size} screenshots copied to ${posix.relative(root, imagesDir)}/${missing ? `, ${missing} missing` : ""}`);

const highlights = await highlight(allBlocks);
writeFileSync(join(out, "highlights.json"), JSON.stringify(highlights) + "\n");
console.log(`${allBlocks.length} code blocks highlighted (${Object.keys(highlights).length} distinct)`);
if (missing) process.exitCode = 1;
