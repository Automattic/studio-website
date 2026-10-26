---
name: WordPress Studio
description: The WordPress platform for AI. Free and open source.
colors:
  primary: "#3858e9"
  primary-strong: "#2741c7"
  background: "#ffffff"
  text: "#0f1222"
  muted: "#6b7080"
  rule: "#e8eaf0"
  surface: "#f6f7fb"
typography:
  display:
    fontFamily: Instrument Sans
    fontSize: clamp(2.75rem, 7vw, 5.75rem)
    fontWeight: 600
    lineHeight: 1
    letterSpacing: -0.04em
  headline:
    fontFamily: Instrument Sans
    fontSize: clamp(2rem, 4vw, 3rem)
    fontWeight: 600
    lineHeight: 1.08
    letterSpacing: -0.03em
  body:
    fontFamily: Instrument Sans
    fontSize: 1.0625rem
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: Instrument Sans
    fontSize: 0.8125rem
    fontWeight: 500
    lineHeight: 1.2
  code:
    fontFamily: JetBrains Mono
    fontSize: 0.9375rem
    fontWeight: 400
    lineHeight: 1.6
rounded:
  sm: 8px
  md: 14px
  pill: 999px
spacing:
  xs: 8px
  sm: 16px
  md: 32px
  lg: 64px
  xl: 120px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.pill}"
    padding: 14px 26px
  code-block:
    backgroundColor: "{colors.text}"
    textColor: "#e8eaf0"
    rounded: "{rounded.md}"
    padding: 18px 22px
imagery:
  filter: none
---

## Overview

Studio as a modern product: white space, a contemporary grotesk in tight lowercase headlines, and a block mosaic (a field of wireframe blocks, as if looking down on the block editor, with a few tiles in solid WordPress blue) fading in behind the hero and once more near the close. WordPress blue is the single confident accent, on the primary button and active states. Cards carry a 1px gray border and no shadow. Code blocks are dark rounded panels in JetBrains Mono with a copy button. Placeholders for app captures sit over the mosaic with a thin border.

## Colors

- `background` #ffffff is the ground of every section. The page is light from top to bottom.
- `text` #0f1222 is the ink for headlines and body. It is also the ground of code blocks.
- `muted` #6b7080 is for secondary copy, captions, and placeholder labels.
- `rule` #e8eaf0 is for hairlines: the header underline, card borders, table rows, and dividers between sections.
- `surface` #f6f7fb is a barely tinted panel for alternating content such as the FAQ, the under-the-hood band, and placeholders.
- `primary-strong` #2741c7 is only the hover/focus state of `primary` (buttons, links).
- `primary` #3858e9 (WordPress blue) is the one accent. Use it on the primary button, the active tab, links on hover, and small key marks. Never use it as a large flat fill.
- No gradients or glows anywhere. The mosaic uses only `rule`-tone strokes, `surface` and `primary` fills.

## Typography

- Instrument Sans for everything that isn't code. Headlines are set at 600 with tight negative tracking. The display size is for the hero only, and headline size is for section titles. Sentence case, and short headlines may run lowercase.
- Body text is 1.0625rem/1.6 in measures of 60–70 characters.
- Labels are 0.8125rem/500. Section eyebrows sit inside a pill with a 1px `rule` border.
- JetBrains Mono for every command, flag, file name, and code block. Inline code is set on `surface` with a small radius.

## Layout

- Content width is 760px (reading column: hero copy, text blocks) and wide width is 1240px (heading rows, card grids, splits), with root padding of at least 24px so nothing touches the viewport edge.
- Spacing rhythm: section padding is `xl` (120px) on desktop and about 72px on mobile. Gaps between groups are `lg`, gaps inside cards are `sm`–`md`.
- Signature concept: **Stacked bands**, adapted to Aurora. A cover hero has the block mosaic behind it, top right, masked to fade toward the text and downward. Below it, each section is its own full-width band. Bands alternate between white and `surface`, separated by a 1px `rule` hairline rather than by color jumps. Each band opens with its title and, directly under it, a one- or two-sentence muted intro (max ~54ch), both left-aligned on the wide grid above the content. Never park the note alone in the top-right corner.
- Card rows use the wide grid with 3–4 columns on desktop and stack on mobile. Text-beside-visual bands put copy on one side and a framed placeholder or code block on the other.
- The header is a thin sticky bar floating over the hero with a 1px rule underneath. It is frosted: 74% white with `backdrop-filter: blur(14px) saturate(180%)`, falling back to 94% white where backdrop-filter is unsupported. The frost sits on the header's `::before` layer (absolute, `z-index: -1`), never on the header itself: a backdrop-filter on the header would make it the containing block for the mobile menu's fixed overlay and squash the overlay to the bar's height. The footer is the last band, short and on `surface`.
- Every page follows the same system. Sub-pages open with a shorter cover band that has a shorter mosaic, then use stacked bands.

## Elevation & Depth

- The ground is flat white. The only ornament is the block mosaic: a theme SVG (`assets/images/mosaic.svg`) of rounded tiles on a 40px module (blank, paragraph lines, heading bar, image glyph, button pill, and a few solid `primary` tiles), placed as a `::before` on `.is-style-mosaic` and masked to fade. It appears behind the hero and, mirrored, behind the closing call to action, and nowhere else.
- Cards have a 1px `rule` border and no shadow.
- The primary button carries the only shadow on the page: `0 8px 24px rgb(56 88 233 / 0.28)`.

## Shapes

- Cards, code blocks and placeholders: `md` (14px). Inline code and small chips: `sm` (8px). Buttons, tabs and eyebrow labels: `pill`.
- Full-bleed bands stay square-cornered.
- A 1px `rule` under the header. Placeholders may be cropped at the bottom edge of their band.

## Components

- **Primary button**: `primary` ground, white text, pill shape, 14px × 26px padding, soft blue shadow. On hover the ground darkens slightly.
- **Secondary button**: white ground, `text` ink, 1px `rule` border, pill shape.
- **Tab switch**: a pill track on `surface` with 1px `rule`. The active tab is white with ink text and a hairline border.
- **Code block / command line**: `text` ground, light text in JetBrains Mono, 14px radius, a `$` prompt in muted tone, and a small "Copy" button at the right.
- **Card**: white, 1px `rule`, 14px radius, 28–32px padding, a small label on top, a headline-weight title, and muted body text.
- **Placeholder**: a `surface` panel with a 1px dashed `rule` border and 14px radius. A centered muted label names the capture that goes there (for example "Demo video: prompt to site").
- **FAQ**: details/summary rows separated by 1px rules.
- **Featured card**: a card with an ink border (1px plus a 1px ring). Use it for one card per grid at most, to point at the recommended option.
- **Agent logo**: an official provider mark, 40px, at the top of an agent card. Add the classes `has-logo logo-claude`, `logo-openai` or `logo-cursor` to the card. Claude uses its terracotta (#d97757); OpenAI and Cursor use the ink color. The SVGs live in the theme's assets/logos.
- **Agent setup card** (Agents, "Pick your agent"): `is-style-card agent-card`.
  - Head row (`agent-card__head has-logo logo-*`): a 28px mark, the H3 name (1.1875rem), and a mono pill on the right naming the method in plain words (Plugin, MCP server, Through Codex, MCP config).
  - Then one muted sentence on what setup involves.
  - Then the real setup command, copied from that agent's guide, as a core Code block (`agent-card__cmd`). It is a compact ink terminal (10px radius, 12.5px mono, 11.5px on mobile so commands don't break mid-word) with a 32px top bar that holds a muted label ("Terminal", or the config file name, `.cursor/mcp.json`) and the Copy button.
  - The terminal is pushed to the card's bottom, so the terminals line up across a row.
  - The "Set up …" card link stretches over the whole card. Hover gives a `rule-strong` border, a soft shadow and the arrow nudge. Keyboard focus outlines the whole card. The terminal sits above the link layer, so its text stays selectable and Copy works.
  - Never invent a command: if a guide's setup changes, update its card too.
- **Hero video** (Home): an app window on a dark stage.
  - Structure: a full-width `.video-stage` band (ink background, the same as the Download CLI band) holds a wide `.video-window` group. The window shares the `.app-window` frame: a 40px title bar with three dots, a 14px radius, a 1px translucent ink border (`rgb(15 18 34 / 0.12)`, which reads as a rule on the white hero and vanishes on the dark stage, so the window never gets a grey halo there), a surface background behind the video and a soft drop shadow. Inside it, a core Video block (`.hero-video`) at 16:9 with `object-fit: contain`.
  - Overlap: the window rises into the hero by a third of its height. `--vw-overlap` is computed from the window width (`--vw-w` × 0.1875 + 13px). The hero adds the same amount to its bottom padding, and the stage keeps the full `--band-pad` below.
  - Controls: the markup keeps autoplay, muted, loop, playsinline and controls. `studio.js` removes the native controls and adds a `.video-controls` group in the title bar's right corner: a 26px round rewind button (counter-clockwise arrow icon; it jumps to the start and plays), then a 26px "Sound on" pill (it toggles to "Sound off"; its dot turns primary when the sound is on). Clicking the video pauses and resumes it. The script replaces native looping with an `ended` handler that seeks to 0 and plays, so the video loops even on servers without range requests. With reduced motion, the video starts paused.
  - File: 1920×1080 H.264 (x264 slower, CRF 31, `+faststart`) with 96 kbps AAC, about 4.6 MB for 50 seconds. Keep any replacement under 5 MB and import it as a new attachment, with `?v=<id>` on the src. The poster uses the 2048px size.
- **Share image** (`assets/images/share.png`, 1200×630): white with the mosaic texture top right. On the left, the logo, the H1 "The WordPress platform for AI." (66px, -0.045em), a muted one-line sub and a mono primary platform line. On the right, the real app screenshot in the `.app-window` frame, running off the right and bottom edges. Source: `tmp/share-card.html`, rendered at 1x with Playwright. functions.php outputs it as og:image and twitter:image unless a page has a featured image. Each page's meta description is its excerpt.
- **Showcase** (Home, "Built with Studio", homepage only; there is no Showcase page): a horizontally rotating track of eight chosen sites, shown as a track of staggered columns of real captures from the media library. The columns are:
  - a pair of 16:10 desktop screens;
  - a phone screen in a 7px ink device frame;
  - a tall full-page capture, cropped to its first screen and kept still.

  Columns start at offsets of 0/64/22/92px, so the row never lines up. The track loops quickly, at 4.5s per column. `studio.js` moves it from `requestAnimationFrame`, not a CSS animation: pausing a compositor animation makes the track visibly snap. The loop width is measured from where the first clone starts, so the seam is exact. The script runs only while the row is on screen. Each card shows the site name and kind. Hovering a card (not the gaps between cards) or focusing inside the row eases the speed to zero in about a quarter second, and lays an ink panel over the image with the exact prompt in mono, prefixed by the model and build time. With reduced motion, the track becomes a static horizontal scroll.
  - **Copy prompt:** clicking anywhere on a card copies its prompt (not while the visitor is selecting text). Each panel also has a real `.sc-copy` button, a mono pill in its top-right corner (the model line keeps 96px clear for it), for keyboard and screen readers, labelled "Copy the prompt for <site>". On copy, the pill turns primary and reads "Copied", and the card gets `is-copied` for 1.8s, which keeps the panel open, so a tap on a phone shows what was copied. The click handler is delegated on the document, so clones copy too. The section intro says "Hover one to read the prompt, click to copy it."
- **Works-with strip** (Home hero, Agents hero): a mono "works with" label, then linked names, each with an 18px official mark (`.brand.logo-*`). Claude is terracotta, Studio Code is the WordPress mark in primary, and the rest are ink.
- **Hero logo** (agent setup pages): a 56px provider mark above the eyebrow (`.hero-logo.logo-*`).
- **Receipt** (Pricing): a monospaced paper slip tilted -1.2deg with a zig-zag bottom edge, dashed line items and a 2px total rule. The total reads "The AI you use". It never shows amounts.
- **App window**: real app captures sit in an `.app-window` group, the same frame as the homepage video (40px title bar with three dots, 14px radius, translucent ink border, soft shadow; 28px bar and smaller dots under 782px). Captures are window content only, with no OS chrome, and the frame supplies the chrome. On the App page, the hero's `.app-shot` window is followed by a `.window-legend`: three numbered labels in a 20/47/33 grid that matches the app's site list, chat and preview columns, stacking on mobile.
- **Browser window** (CLI, `#studio-ui` section): the app-window frame dressed as a browser, for what `studio ui` opens. A `.browser-window` group: 44px toolbar (32px under 782px) with the three dots and a centered mono address pill (`p.browser-url`, real text, "localhost:8081", the command's real default port), then the App page screenshot (attachment 264), since the browser UI is the same interface as the desktop app. The section is a `split is-reversed ui-split` columns block: copy first in the DOM (kicker `studio ui`, H2, intro, `is-style-command` block, checks), window first on screen above 782px (`row-reverse`), copy first on mobile. The CLI reference has a matching "Interface" group (`#interface`) listing `studio ui`, `--port` and `--no-open`.
- **Source chips** (Liberate): mono pill chips naming where sites come from, ending in a primary "→ WordPress".
- **Short version** (Compare): a one-line summary with a 3px primary left rule, under the hero intro.
- **Platform panel** (Download): one white panel with a soft drop shadow, one row per OS. Each row has the official OS mark (30px, ink), the name and its architectures, and small ghost buttons with a ↓ on the right. The visitor's OS row gets a 5% primary tint and a mono "Your system" label. The large primary button next to it names the detected OS. Buttons go straight to the appscdn.wordpress.com `…/latest` installers. Under the hero button, a 12px muted `.dl-legal` line carries the same wording as wordpress.studio: "By continuing with download, you agree to our Terms of Service and have read our Privacy Policy." The links go to wordpress.com/tos and automattic.com/privacy. The footer's bottom row links Terms and Privacy to the same pages. The site has no privacy page of its own.
- **Dark band**: an ink-ground band for one focused moment per page, such as the CLI install on Download. Code blocks inside it get a translucent white ground with a hairline.
- **First steps**: three columns, each topped by a 2px ink rule, with a mono `01 · one minute` label, a title and muted text.
- **Card link**: primary-colored text pinned to the bottom of a card, with a → arrow that nudges right on hover.
- **Price card**: a card with a label, a large price word ("Free"), then a hairline, a small title and muted body. Never show amounts.
- **Route** (Home, "From a prompt to a live site"): five stops on one horizontal hairline rail (`.route` grid, `.route-stop` groups). Each stop has a 13px ring dot on the rail (the last, Ship, is a solid primary dot with a soft halo), then its real artifact in a small mono chip (`.route-artifact`, surface, `sm` radius, equal heights so the five line up): an example prompt, four direction names, a `validate_blocks ok` log, a source chip, and `studio preview create` as an ink command chip. Then the mono number, a large verb (up to 1.875rem, -0.035em) and one muted sentence. No card borders. On scroll-in the rail draws left to right and the dots fill in turn. Under 960px the rail turns vertical on the left and the artifact moves under the text.
- **Theme tree** (Home, "Real WordPress"): a white panel (`.theme-tree`, 1px rule, `md` radius) with a mono path bar (`wp-content/themes/your-site/`), a file list drawn as a tree (`.file-tree`: box-drawing glyphs in `rule-strong`, ink mono file names, muted notes, dashed separators), and a surface footer line led by a 12px primary tile. Real files only. Beside it, the copy column carries pill `.fact-chips` for the short facts.
- **Stack** (Home, "Under the hood, in the open"; this is also the homepage's open-source section, and there is no separate one): the left column holds only the H2 and intro. On the right, Studio is an ink bar: the H3 name links to its repo with a ↗, its mono kicker is `automattic/studio`, three links (Code, Changelog, Issues) sit beside it, and a GPLv2+ licence pill is on the right. Under it is an in / out ledger, one panel with a 1px rule and `md` radius split into two columns. "in the stack" is on white: Blueprints, WP-CLI, WordPress, PHP and SQLite as hairline rows, each name linking to its source with a ↗ and carrying a mono pill with its real licence (GPLv2+, MIT, PHP License, Public domain). "not in the stack" is on surface: Docker, a virtual machine and a database server, struck through with a 2px primary line. The rows have no descriptions. On mobile the two columns stack.
- **Three doors, one engine** (Home closing, over the mirrored mosaic): three door cards (the app, the CLI, your agent), each with its action. Under them sits the engine bar (`.engine-base`): an ink panel with a muted mono kicker "the engine under all three", a white mono line naming what the Studio CLI does, and translucent mono pills for its main commands. A dashed `rule-strong` line drops from the middle of each door onto the bar. On mobile the doors stack, the connectors are hidden and the bar follows. This is the only place the homepage explains the shared engine. There is no separate "one engine" section.
- **Steps**: an ordered list with mono `01`, `02`… numbers in pill outlines, joined by a dashed vertical line. A bold lead line, then muted detail.
- **Chat transcript**: a dark prompt panel with mono `you` and `agent` labels, step lines, and a final line in soft green.
- **Compare table**: a three-column table with muted row labels. The Studio column has a faint primary tint and a primary header. Hairlines only, plus a dated caption.
- **Command reference**: a two-column table of mono command pills and plain descriptions. On mobile each row stacks.
- **Sub-page structure**: an `is-style-mosaic is-sub` hero (eyebrow, H1, sub), then alternating surface and white bands, and an `is-style-mosaic is-closing` call to action at the end.
- **404**: a mosaic hero with a dark prompt line and a blinking primary caret that stops under reduced motion.
- **Search** (`templates/search.html`, not linked from the nav): an `is-sub` mosaic hero with a "Search" eyebrow, the query title as H1 and a pill search field (`.site-search`: 48px input with a `rule-strong` border, primary pill button). Below it, a band with a wide-aligned query (`.search-hits`, children capped at 760px) listing every result on one page (20 per page): hairline-separated rows with an ink title that turns primary on hover and a muted excerpt, no dates. No results gives one sentence with links to the app, the CLI, the agents and the docs. Don't use a `search-results` class: WordPress puts it on `<body>` of search pages.
- **Mobile header**: only the logo, the menu toggle and the Download button. The GitHub icon is hidden under 782px.

## Do's and Don'ts

- Do keep the page white with hairlines, and let the mosaic appear exactly twice.
- Do use JetBrains Mono for anything a user could type.
- Do label placeholders plainly.
- Don't use purple as a flat fill, the gradient on every section, glass or blur on elements, cream, serifs, hard shadows, Inter as the display face, or dark sections (code blocks are the only dark surfaces).
- Don't invent statistics, star counts, testimonials or fake screenshots. Only real, attributed quotes go in "What builders say" on the homepage. On the homepage they sit in the `.voices` band: an ink band (block colors `text` background, `background` text), with the quotes in full in two plain columns (no cards) in large muted-light type (up to 1.5rem). Each quote highlights its one or two key phrases with `<mark>`, a primary background with white text and a 3px radius, like text selected in an editor. Highlight the person's own words only, never add to them. Under each quote, a 40px round photo (empty alt because the name follows) sits beside the white name, which links to their LinkedIn profile so the quote can be checked, and the muted role. The columns stack under 782px. Other pages keep the `review-grid` cards: a head strip with a 30px `review__photo` (or a `review__avatar` initials badge when there is no photo), the quote, and an optional `review__role`; `review-grid--pair` for two quotes, three columns for three or more.

## Imagery

- No generated photography. App captures and demo videos are clearly labeled placeholders until real captures are added.
- Real screenshots, once added, sit over the mosaic or on `surface` with a 1px `rule` border and 14px radius, with no filter.
- Icons, where needed, are thin monoline in ink. Prefer none.

## Motion

- The mosaic is static. The "Built with Studio" strip scrolls sideways in a slow, seamless loop that pauses on hover and focus and becomes a plain horizontal scroller under reduced motion.
- Content fades in over 400ms with a 10px rise when scrolled into view. The homepage route's rail draws itself over 1.2s and its dots fill in turn, once.
 JavaScript adds the hidden starting state, so content is visible without it.
- Buttons darken on hover over 150ms. The copy button briefly reads "Copied".
- Everything is turned off under `prefers-reduced-motion`.

## Accessibility

- Target: WCAG 2.2 AA. axe-core reports no violations on any page at 1040px or 390px. Re-run it after layout changes.
- No layout shift from scripts: an inline head script adds `.js` to `<html>`, so CSS can draw JS-enhanced parts in their final state before `studio.js` (deferred) runs. Example: the hero tabs show only the first panel until the script marks `.doors.is-tabbed`. Without JS, every panel is visible. Homepage CLS stays under 0.01.
- Tabs: roving tabindex with arrow keys; `aria-selected`, `aria-controls` and `aria-labelledby` are set by the script.
- Showcase: each prompt (`.sc-text`) is a labelled tab stop ("Prompt for <site>"). Focus reveals its overlay (`:focus-within`) and pauses the row. Clones are `aria-hidden`, and their focusables get `tabindex=-1`. They are deliberately not `inert`: inert content takes no hover or clicks, and half the sites on screen are clones. Under reduced motion, the scrolling row is a focusable, labelled region.
- Focus: 2px primary outline on every interactive element; `#9fb1ff` on the dark showcase overlays.
- Headings never skip a level. Card titles straight under a hero H1 are H2s sized down with CSS. Visually empty table headers carry `.screen-reader-text`.
- Decorative images (testimonial photos next to a name) have empty alt text. Every other image is described.

## Voice

- Calm, confident, plain. Short sentences. Specific verbs (build, check, ship, import, export) over adjectives.
- Developer-friendly without jargon. Some warmth.
- Never use: revolutionary, supercharge, unleash, game-changing, seamless, magic.
- Never invent numbers, users, or quotes.
