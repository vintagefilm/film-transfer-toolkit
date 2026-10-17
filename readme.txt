=== Film Transfer Toolkit ===
Contributors: vintagefilmtransfer
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

One plugin with selectable modules for film transfer businesses.

== Modules (Film Transfer Toolkit > Modules) ==
* Film calculator - [ftt_calculator]; also [film_calculator] when the standalone Film Calculator plugin is not active.
* Reel size / diameter estimator - [ftt_reel_estimator format="s8" fps="18"].
* Price table - [ftt_price_table service="hd" addons="yes" fees="yes"]; edit under Price table (per foot, per reel, add-ons, setup fee, minimums, volume tiers, JSON import/export, EXAMPLE and DRAFT presets).
* Multi-reel estimate builder - [ftt_estimate]; customers send a quote request (no payment). The server re-prices every request; requests are private under Quote requests.
* Clip selector (optional) - [ftt_clip_selector ...], ported from FW Clip Cart 0.4.0; needs FluentCart; waits while the FW Clip Cart prototype plugin is active.
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
= 1.1.0 =
* New module: data rate / storage calculator (FTTCore.datarate + FTT_Datarate PHP mirror, shared vectors, parity fuzzer).
* Calculator: "Film format" label carries the filmcalc_format_badge filter, as in FilmTransfer.ai's film-calculator.php.
* Estimate builder shares its reel list with the storage calculator on the same page.

= 1.0.0 =
* First release: six modules, shared core, EXAMPLE and DRAFT FOR REVIEW price presets.
