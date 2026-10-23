=== FTT ===
Contributors: vintagefilmtransfer
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.8
License: GPLv2 or later

One plugin with selectable modules for film transfer businesses.

== Getting started ==
1. Activate the plugin. On a site that has never had the toolkit, the setup wizard opens: choose tools, create or reuse their pages, pick the calculator look, load EXAMPLE prices or import your own, and set an optional notify address (email stays log-only until you switch it on).
2. Add any tool to any page: block editor + > "FTT" group, or paste its shortcode in the classic editor.
3. FTT > Help has step-by-step instructions, pictures, every option and troubleshooting for each tool.
Updates and re-activations never start the wizard or create pages. Re-run it any time from FTT > Setup wizard; pages that already show a tool are reused, never duplicated, and new pages are not added to menus.

== Modules (FTT > Settings) ==
Every module is also a block (ftt/calculator, ftt/reel-estimator, ftt/price-table, ftt/estimate, ftt/storage-calculator, ftt/clip-selector, ftt/rfq-providers, ftt/rfq-form, ftt/rfq-reply, ftt/rfq-compare) that renders through its shortcode.
* Film calculator - [ftt_calculator]; also [film_calculator] when the standalone Film Calculator plugin is not active.
* Reel size / diameter estimator - [ftt_reel_estimator format="s8" fps="18"].
* Price table - [ftt_price_table service="hd" addons="yes" fees="yes"]; edit under Price table (per foot, per reel, add-ons, setup fee, minimums, volume tiers, JSON import/export, EXAMPLE and DRAFT presets).
* Multi-reel estimate builder - [ftt_estimate]; customers send a quote request (no payment). The server re-prices every request; requests are private under Quote requests.
* Clip selector (optional) - [ftt_clip_selector ...], ported from FW Clip Cart 0.4.0. Works under any video player (Video block / uploaded video, YouTube, Vimeo, FluentPlayer, FV Player). Uses the FluentCart cart when FluentCart is active and a clip product is set; otherwise visitors send a clip request. Waits while the FW Clip Cart prototype plugin is active.
* Data rate / storage calculator - [ftt_storage_calculator headroom="20" preset="prores-hq-hd"]; size per reel and in total for ProRes 422 Proxy/LT/422/HQ/4444/4444 XQ (Apple target rates), H.264/H.265 MP4 at a chosen bitrate, YouTube recommended upload bitrates, DPX/TIFF image sequences, uncompressed v210/2vuy/v410 and custom Mbps; several deliverables summed; decimal and binary units; suggested drive with headroom. Presets and sources: core/data/datarate-presets.json and docs/datarate-presets.md. On a page with [ftt_estimate] it can reuse the estimate's reel list.
* Quote comparison / RFQ (optional) - [ftt_rfq_providers] [ftt_rfq_form] [ftt_rfq_reply] [ftt_rfq_compare], ported from filmtransfer.ai.

== Email ==
All toolkit email goes through one place. The default mode is "Log only": nothing is sent and every message appears under Mail log.
Define FTT_MAIL_LOG_ONLY as true in wp-config.php to force log-only on a staging copy.

== Portable core ==
core/ftt-core.js (window.FTTCore / CommonJS / AMD) has the length, reel, pricing, timecode and storage / data-rate maths with no WordPress or DOM dependency, for reuse in a mobile app.
includes/core/ is the PHP mirror used for server-side re-pricing. Both run the same vectors in core/test-vectors/ (tests: node tests/core-tests.js, php tests/core-tests.php, node tests/parity.js, node tests/parity-datarate.js).
Data-rate presets live in core/data/datarate-presets.json; after editing run node tools/embed-presets.js to copy them into ftt-core.js (the tests fail if the copies differ).

== WP-CLI ==
wp ftt status | wp ftt prices example|draft | wp ftt export | wp ftt module on|off <module>... | wp ftt maillog [--full] [--clear] | wp ftt clip-cutlist <order_id> | wp ftt-storage '<request json>' [--format=json]

== Changelog ==

= 1.5.8 =
* Clip selector: new setting "When a signed-out visitor adds clips" (ftt_clip_settings login_mode). block (default) = sign in first, as in 1.5.5. add_then_prompt = the clips go into the FluentCart guest cart at once (server-priced) and Sign in / Create account / View cart are offered. At sign-in or registration (wp-login, FluentAuth, anything that sets the login cookie) the guest cart is recorded as the customer's; items are not merged, so nothing is duplicated. A resume token that is claimed now skips clips already in the cart.
* Prices: a service can be marked Preferred (preselected in the estimate builder for every format it has a rate for) and given an optional badge such as "Recommended" (price table and estimate builder). Stored only when set, so existing tables are unchanged.
* Button text on the accent colour now defaults to white (#fff) instead of the theme's palette9, which gave dark text on a red accent on dark palettes. The contrast check switches it to #fff or #111, whichever reads better, when it is under 4.5:1 against the computed accent (class ftt--auto-on-accent). Override hook: --ftt-site-on-accent, e.g. `:root{--ftt-site-on-accent:#111}` (the check then leaves it alone).
* ftt_clip_settings and ftt_general are always stored as arrays with every default key (saved values are kept; only missing options or keys are filled in), so WP-CLI patching works on any site, e.g.:
  `wp option patch update ftt_clip_settings require_login 1`
  `wp option patch update ftt_clip_settings login_page_id 15343`
  `wp option patch update ftt_clip_settings register_page_id 15344`
  `wp option patch update ftt_clip_settings after_login cart`   (page or cart)
  `wp option patch update ftt_clip_settings login_mode add_then_prompt`   (block = default, or add_then_prompt)
  Use `patch update` (the keys always exist now); `wp option get ftt_clip_settings --format=json` shows them all.

= 1.5.7 =
* Readability on dark and mixed themes: every tool now paints its own background and text as a matching pair from the theme (Kadence palette9 / palette4, then theme.json base / contrast), with headings in palette3. Muted text, borders and soft fills are mixed from the tool's text colour rather than the page's, so they stay readable.
* A small contrast check runs on pages with a tool. If the tool's text/background contrast is still under 4.5:1 it adds the class ftt--auto-contrast (palette9 background, palette3 text).
* Override hook: set --ftt-site-bg and --ftt-site-text (and optionally --ftt-site-heading; headings follow --ftt-site-text when it is set) on :root or any parent element to choose the tool colours yourself, e.g. `:root{--ftt-site-bg:#222;--ftt-site-text:#eee}`. Set them as a pair.

= 1.5.6 =
* Clip selector: Jump to In now sits at the end of the In row (after the +1s trim button) and Jump to Out at the end of the Out row. Rows wrap cleanly on narrow screens (Jump goes on the trim row, then onto its own full-width line on phones). Tab order, aria-keyshortcuts and Shift+I / Shift+O are unchanged.

= 1.5.5 =
* Clip selector: new "Require sign-in to add to cart" (FTT > Clip selector, default off). Visitors who are not signed in can still preview and mark clips; Add clips to cart saves the selection on the server for 2 hours (signed, single-use token) and offers Sign in / Create account. After signing in or registering (your chosen pages, e.g. the FluentCart Login and Registration pages, or wp-login.php) they return to the same page, the clips are added to the FluentCart cart with the price recalculated on the server, and a notice links to the cart (or, if set, they go straight to the cart). Signed-in customers are unaffected. Per selector: require_login="yes|no" (shortcode) or the block sidebar.
* REST: POST ftt/v1/clip/pending and /resume (nonce + rate limit). /add refuses guests with 401 fwcc_login_required while the setting is on.
= 1.5.4 =
* Clip selector: a product with frame rate (_fw_fps) "any" accepts the video's own rate (23.976, 24, 25, 29.97 DF/NDF, 30, 50, 59.94 DF/NDF, 60). Price stays frame-accurate and the rate stays in the signed clip data. Products with a fixed rate behave as before.
* Timecode: added 50 and 59.94 NDF.
* Clip selector: Jump to In / Jump to Out now sit in the Set In / Set Out rows and wrap on small screens. Shift+I / Shift+O unchanged.
* Calculator: default heading is now "FTT Calculator".
* Manual delivery helper: clip lines carry Source video, In, Out, Frame rate and a ready-to-paste ffmpeg command, shown in the admin order and order emails. Optional product meta _fw_source_file names the master file. No automatic delivery.
= 1.5.3 =
* Clip selector: "Jump to In" and "Jump to Out" buttons at the top of the clip editor seek the video to the current In or Out point (pausing there). They are real buttons, so Tab + Enter/Space work; shortcuts Shift+I and Shift+O.
* Clip selector: a heading above the selector, default "Select Stock Footage". Edit it under FTT > Settings > Clip selector, or per selector with heading="..." (shortcode) or the block sidebar. A blank site setting hides it everywhere; heading="none" hides it on one selector. It uses the shared ftt-base.css title style and the site's heading font.

= 1.5.2 =
* Add-ons: new "per custom unit" pricing type. The owner names the unit (USB drive, DVD copy, hour...); customers enter a quantity (1-999) and the estimate reads "3 × USB drive @ $15.00". Server re-pricing matches the browser.
* Add-ons and services: optional description, shown as helper text in the estimate builder and the price table (plain text, escaped).
* Services are now editable rows on the Prices page: add, rename (label only; ids stay), reorder and remove (removing a service removes its rates).
* New built-in services "4K output" and "1080p output" in the EXAMPLE and DRAFT presets. Upgrades from 1.5.1 get them appended once, with no rates, so no existing price changes.
* JSON import/export, setup wizard Prices step and help updated.

= 1.5.1 =
* Clip selector: new rate card setting "rounding" (up | down | nearest) for turning a clip's length into whole billed seconds. Default "up" (unchanged behaviour). The on-page estimate and the cart/request price use the same rule. New filter ftt/clip/billable_seconds ($secs, $duration, $card).

= 1.5.0 =
* One look for every tool: a shared base stylesheet (ftt-base.css) with --ftt-* tokens that inherit the site theme (Kadence palette, then theme.json presets). Every tool has the same width, padding, border radius, buttons, fields and heading sizes, on desktop and mobile. Only ftt-base.css may contain literal colours or fonts; a test checks this. To set tool colours yourself use the override hook --ftt-site-bg / --ftt-site-text (see 1.5.7).
* The film calculator has a new colour scheme, "Site theme", which is now the default. Dark and Light are unchanged. A site that never saved a scheme now shows the site-theme look.
* The admin is simpler: Dashboard (one card per tool, with how to add it to a page), Tools (switch on/off and the request lists), Settings (one tab per tool, with rarely needed options under Advanced) and Help. Old admin addresses redirect to the new place. No setting names changed.
* Site check: the calculator check accepts the shortcode or the block.

= 1.4.1 =
* Fix: with the clip selector switched on while the FW Clip Cart prototype plugin is also active, [ftt_clip_selector] showed as raw text and the Clip selector block was missing. The module now runs next to the prototype: the prototype keeps [fw_clip_cart] and the FluentCart cart hooks, and [ftt_clip_selector] and the block work.
* A switched-off tool's shortcode or block never shows as raw text: visitors see nothing, editors see a short note saying it is switched off and where to turn it on. All blocks stay registered so they can be placed and set up before a tool is switched on.
* tests/v141-tests.sh covers both cases, with and without the prototype.

= 1.4.0 =
* Renamed to FTT (formerly "Film Transfer Toolkit") in the plugin list, admin menu, block inserter category, help, readme and docs. Nothing else changed: the plugin folder and file (film-transfer-toolkit/film-transfer-toolkit.php), text domain, every shortcode ([ftt_*] and [film_calculator]), block name (ftt/*), option key, script/style handle, filter (including filmcalc_format_badge), CSS class and data attribute are the same, so existing pages and settings keep working. tests/v140-tests.php checks this against a snapshot of 1.3.0.
* Help > All options: one page listing every shortcode and block with every option, its block sidebar label, allowed values, default (and, where a setting supplies it, the site's current value) and copy-ready examples: the hand-written ones, a shortcode with every option at its default, and block markup. Built from the tool registry, like the per-tool option tables, so it cannot drift.
* Block sidebars: every shortcode option is in its block's Settings panel with the same default, a plain-English label and help text that gives the default, the allowed values and the matching shortcode option. "Use the settings default" choices show what the setting currently is. New "Reset all to defaults" button. A test fails if any shortcode option is missing from its block or the defaults differ.
= 1.3.0 =
* Film calculator: the Length (meters) field, the Frames field, the Frame rate picker, the film speed (ft/min) readout and the total seconds readout can each be switched off for the site (Calculator look > Fields, and the setup wizard's Look step) or per page (meters, frames, fps_picker, speed, seconds attributes; block Settings panel). Defaults show everything. Settings saved by 1.2.0 carry over (the old extras checkbox becomes the speed and seconds toggles). The summary line leaves out hidden fields.
* Clip selector works on any page with a video player: core Video block and other HTML5 video, YouTube and Vimeo embeds (driven through each player's own postMessage interface; no third-party script is added), FluentPlayer and FV Player. player_selector attaches it to one player; player="html5|youtube|vimeo|fluent|fv" limits automatic detection. Optional automatic placement under pages/posts that contain a video (off by default).
* Clip selector without FluentCart: the button reads "Send clip request"; visitors give a name and email and the list is saved privately under Clip requests (server-side prices from the rate card; honeypot, nonce and rate limit) and the owner is notified through the mail log / Send mode. "Copy list" copies the clip names and timecodes. With FluentCart active and a clip product (product_id or the new default clip product setting), clips go to the cart as before.
* Clip selector settings: default timecode frame rate, default clip product, automatic placement.
* Storage calculator starts with 16mm and two deliverables: ProRes 422 HQ UHD 3840x2160 and H.264 16 Mbps, both at the film frame rate with PCM audio. Each site can choose its own default film format and up to four default deliverables (FTT > Storage calculator, and a new Storage step in the setup wizard). New format attribute; preset still starts with one particular format; new preset prores-hq-uhd.
* Test bench: "wp ftt testbench" builds a draft page with every tool (plus the standalone [film_calculator]) and sample videos; "wp ftt testbench --check" renders and checks it. The page always stays a draft (publishing is refused), is noindex, and is kept out of menus. While the Clip selector module is off, editors previewing it see a "Preview only" selector that cannot send anything.
* Uninstall also removes ftt_storage and ftt_clip_settings (clip requests are kept unless FTT_UNINSTALL_DELETE_DATA is set).

= 1.2.0 =
* Setup wizard on first activation (re-runnable): tools, pages (reuse existing, never duplicate, not added to menus, optional drafts), calculator look, EXAMPLE prices or JSON import, optional notify address (mail mode untouched).
* One "FTT" admin menu: Dashboard / Getting started, Setup wizard, Settings, Prices, Help (a page per tool with block and classic editor steps, option tables, copy buttons, examples, bundled illustrations, FAQ), Calculator look, module screens, Mail log.
* Blocks for every tool in a "FTT" inserter category, with sidebar settings and shortcode-to-block conversion.
* WordPress Help tabs on toolkit screens.
* Notice while the standalone Film Calculator plugin is active, explaining that it keeps handling [film_calculator] and the toolkit covers it once deactivated. Nothing is deactivated automatically.
* Existing installs are marked as configured on update, so the wizard never auto-runs on them.

= 1.1.0 =
* New module: data rate / storage calculator (FTTCore.datarate + FTT_Datarate PHP mirror, shared vectors, parity fuzzer).
* Calculator: "Film format" label carries the filmcalc_format_badge filter, as in FilmTransfer.ai's film-calculator.php.
* Estimate builder shares its reel list with the storage calculator on the same page.

= 1.0.0 =
* First release: six modules, shared core, EXAMPLE and DRAFT FOR REVIEW price presets.
