=== Film Transfer Toolkit ===
Contributors: vintagefilmtransfer
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

One plugin with selectable modules for film transfer businesses.

== Modules (Film Transfer Toolkit > Modules) ==
* Film calculator - [ftt_calculator]; also [film_calculator] when the standalone Film Calculator plugin is not active.
* Reel size / diameter estimator - [ftt_reel_estimator format="s8" fps="18"].
* Price table - [ftt_price_table service="hd" addons="yes" fees="yes"]; edit under Price table (per foot, per reel, add-ons, setup fee, minimums, volume tiers, JSON import/export, EXAMPLE and DRAFT presets).
* Multi-reel estimate builder - [ftt_estimate]; customers send a quote request (no payment). The server re-prices every request; requests are private under Quote requests.
* Clip selector (optional) - [ftt_clip_selector ...], ported from FW Clip Cart 0.4.0; needs FluentCart; waits while the FW Clip Cart prototype plugin is active.
* Quote comparison / RFQ (optional) - [ftt_rfq_providers] [ftt_rfq_form] [ftt_rfq_reply] [ftt_rfq_compare], ported from filmtransfer.ai.

== Email ==
All toolkit email goes through one place. The default mode is "Log only": nothing is sent and every message appears under Mail log.
Define FTT_MAIL_LOG_ONLY as true in wp-config.php to force log-only on a staging copy.

== Portable core ==
core/ftt-core.js (window.FTTCore / CommonJS / AMD) has the length, reel, pricing and timecode maths with no WordPress or DOM dependency, for reuse in a mobile app.
includes/core/ is the PHP mirror used for server-side re-pricing. Both run the same vectors in core/test-vectors/ (tests: node tests/core-tests.js, php tests/core-tests.php, node tests/parity.js).

== WP-CLI ==
wp ftt status | wp ftt prices example|draft | wp ftt export | wp ftt module on|off <module>... | wp ftt maillog [--full] [--clear] | wp ftt clip-cutlist <order_id>

== Changelog ==
= 1.0.0 =
* First release: six modules, shared core, EXAMPLE and DRAFT FOR REVIEW price presets.
