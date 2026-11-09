// Finalize the static site in dist/ for Spacefast.
//
// Spacefast renders every uploaded .md file as an HTML page and stops serving the file itself,
// which would take the Markdown copies of the docs pages (and llms.txt's links to them) offline.
// Each .md under /docs becomes .md.txt and a rewrite keeps the .md URL, served as text/markdown.
//
// Usage: node scripts/finalize-site.mjs dist

import { appendFileSync, existsSync, readdirSync, renameSync } from "node:fs";
import { join, relative, resolve } from "node:path";

const dist = resolve(process.argv[2] ?? "dist");
const docs = join(dist, "docs");
const base = "/docs";

if (!existsSync(docs)) {
	console.error(`No docs in ${dist}: did the export include /docs/?`);
	process.exit(1);
}

const walk = (dir) =>
	readdirSync(dir, { withFileTypes: true, recursive: true })
		.filter((entry) => entry.isFile())
		.map((entry) => join(entry.parentPath, entry.name));

const rewrites = [];
const headers = [];
for (const file of walk(docs).filter((f) => f.endsWith(".md"))) {
	const route = `${base}/${relative(docs, file).split("\\").join("/")}`;
	renameSync(file, `${file}.txt`);
	rewrites.push(`${route}  ${route}.txt  200`);
	headers.push(`${route}\n  Content-Type: text/markdown; charset=utf-8`);
}
appendFileSync(join(dist, "_headers"), `\n# Markdown copies of the docs pages\n${headers.join("\n")}\n`);
appendFileSync(join(dist, "_redirects"), `\n# Markdown copies of the docs pages (Spacefast would render a .md file as a page)\n${rewrites.join("\n")}\n`);

console.log(`Finalized dist/docs: ${rewrites.length} Markdown copies behind rewrites.`);
