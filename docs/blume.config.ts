import { defineConfig } from "blume";

// The docs follow the Studio app's design tokens (apps/ui/src/lib/theme/tokens.css in
// Automattic/studio): Inter, the system mono stack, neutral surfaces, WordPress.com
// Blueberry as the one accent, and a light scheme that follows the OS. theme.css holds
// the tokens Blume's config can't express.
export default defineConfig({
	title: "WordPress Studio Docs",
	description: "How to use WordPress Studio: local sites, Studio Code, the CLI, syncing and previews.",
	// The same app icon and name as the header of wordpress.studio. public/favicon.png and
	// public/apple-icon.png are the same file; Blume picks them up by name.
	logo: {
		href: "/",
		image: { light: "/logo.png", dark: "/logo.png", alt: "WordPress Studio" },
		text: "WordPress Studio",
	},
	deployment: {
		site: "https://wordpress.studio",
		base: "/docs",
	},
	theme: {
		accent: { light: "#3858e9", dark: "oklch(0.72 0.2 268)" },
		background: { light: "#ffffff", dark: "#161616" },
		mode: "system",
		radius: "md",
		fonts: { display: "inter", body: "inter" },
	},
	content: { root: "content" },
	navigation: {
		cta: { href: "https://wordpress.studio/download/", label: "Download" },
		featured: [{ label: "WordPress Studio", href: "https://wordpress.studio" }],
		sidebar: { display: "flat" },
	},
	footer: {
		copyright: `© ${new Date().getFullYear()} Automattic`,
		links: [
			{ label: "WordPress Studio", href: "https://wordpress.studio" },
			{ label: "Download", href: "https://wordpress.studio/download/" },
		],
		socials: { github: "https://github.com/Automattic/studio" },
	},
	markdown: { externalLinks: true, imageZoom: true },
	// Studio has no HTTP API: keep Blume's JSON docs API, its OpenAPI description and the
	// API catalog out of the build so agents aren't pointed at one. llms.txt and the .md
	// mirrors stay.
	agents: { api: false },
	// Ratings go nowhere until an analytics adapter is configured.
	feedback: false,
});
