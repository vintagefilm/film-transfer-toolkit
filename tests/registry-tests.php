<?php
/**
 * Registry / blocks / help consistency tests. Run inside WordPress with all modules on:
 *   wp eval-file wp-content/plugins/film-transfer-toolkit/tests/registry-tests.php
 * Checks that every documented attribute is a real shortcode attribute (and vice versa), that page detection
 * matches exact tags only, and that each block renders the same markup as its shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pass = 0; $fail = 0;
$ok   = function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; } else { $fail++; echo 'FAIL ', $name, $detail ? ' :: ' . $detail : '', "\n"; }
};
global $shortcode_tags;
$norm = function ( $html ) {
	$html = preg_replace( '/ftt-(est|reel|calc|fc)-\d+/', 'ID', $html );
	$html = preg_replace( '/filmcalc-\d+/', 'ID', $html );
	$html = preg_replace( '/name="_wpnonce" value="[^"]*"/', '', $html );
	return preg_replace( '/\s+/', ' ', $html );
};
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	$ok( "$tag: shortcode registered", isset( $shortcode_tags[ $tag ] ) );
	if ( ! isset( $shortcode_tags[ $tag ] ) ) { continue; }
	// Capture the real attribute pairs via the shortcode_atts_{name} filter.
	$pairs = null;
	$cap   = function ( $out, $p ) use ( &$pairs ) { $pairs = $p; return $out; };
	foreach ( array_merge( array( $tag, 'film_calculator' ), $e['aliases'] ?? array() ) as $n ) { add_filter( 'shortcode_atts_' . $n, $cap, 10, 2 ); }
	$sc_html = (string) call_user_func( $shortcode_tags[ $tag ], array(), '', $tag );
	foreach ( array_merge( array( $tag, 'film_calculator' ), $e['aliases'] ?? array() ) as $n ) { remove_filter( 'shortcode_atts_' . $n, $cap, 10 ); }
	$doc = array_column( $e['atts'], 'name' );
	if ( null === $pairs ) {
		$ok( "$tag: documented as having no attributes", array() === $doc, implode( ',', $doc ) );
	} else {
		$real = array_keys( $pairs );
		sort( $real ); $d = $doc; sort( $d );
		$ok( "$tag: documented attributes == shortcode_atts keys", $real === $d, 'real=' . implode( ',', $real ) . ' doc=' . implode( ',', $d ) );
		foreach ( $e['atts'] as $a ) {
			if ( '' !== (string) $a['default'] && isset( $pairs[ $a['name'] ] ) ) {
				$ok( "$tag {$a['name']}: documented default matches", (string) $pairs[ $a['name'] ] === (string) $a['default'], $pairs[ $a['name'] ] . ' vs ' . $a['default'] );
			}
			if ( ! empty( $a['options'] ) && empty( $a['options'][''] ) && '' !== (string) $a['default'] ) {
				$ok( "$tag {$a['name']}: default is one of the options", isset( $a['options'][ $a['default'] ] ) );
			}
		}
	}
	// Block registered and renders like the shortcode.
	$bt = WP_Block_Type_Registry::get_instance()->get_registered( $e['block'] );
	$ok( "$tag: block {$e['block']} registered in the toolkit category", $bt && 'film-transfer-toolkit' === $bt->category );
	if ( $bt && 'ftt_clip_selector' !== $tag ) {
		$ok( "$tag: block renders same markup as shortcode", $norm( render_block( array( 'blockName' => $e['block'], 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ) ) === $norm( $sc_html ) );
	}
	// Examples parse to known attributes.
	foreach ( array_keys( $e['examples'] ) as $ex ) {
		$ok( "$tag: example uses its own tag", 0 === strpos( $ex, '[' . $tag ) );
		$atts = shortcode_parse_atts( trim( substr( $ex, strlen( $tag ) + 1 ), '] ' ) );
		foreach ( (array) $atts as $k => $v ) {
			$ok( "$tag example $ex: '$k' is documented", in_array( $k, $doc, true ) );
			$def = current( array_filter( $e['atts'], function ( $a ) use ( $k ) { return $a['name'] === $k; } ) );
			if ( $def && ! empty( $def['options'] ) ) { $ok( "$tag example $ex: '$v' is a valid $k", isset( $def['options'][ $v ] ) ); }
		}
	}
	$ok( "$tag: help image bundled", is_file( FTT_DIR . 'assets/img/help/' . FTT_Registry::tools()[ $e['module'] ]['image'] ) );
}
// Attribute rendering through the block: storage preset/headroom.
$h = FTT_Blocks::render( 'ftt_storage_calculator', array( 'preset' => 'dpx-2k', 'headroom' => '30', 'bogus' => 'x' ) );
$ok( 'block attrs pass through to the shortcode', false !== strpos( $h, 'data-preset="dpx-2k"' ) && false !== strpos( $h, 'data-headroom="30"' ) );
$h = FTT_Blocks::render( 'ftt_storage_calculator', array( 'preset' => '"><script>', 'headroom' => '999' ) );
$ok( 'hostile block attrs sanitized by the shortcode', false === strpos( $h, '<script>' ) && false === strpos( $h, 'data-preset=' ) && false !== strpos( $h, 'data-headroom="20"' ) && false !== strpos( $h, 'data-dels=' ) );
$ok( 'unknown tag renders nothing', '' === FTT_Blocks::render( 'nope', array() ) );
// content_has exactness.
$st = FTT_Registry::embeds()['ftt_storage_calculator'];
$ok( 'content_has: alias [ftt_storage]', FTT_Registry::content_has( 'x [ftt_storage] y', $st ) );
$ok( 'content_has: with attrs', FTT_Registry::content_has( '[ftt_storage_calculator preset="h264"]', $st ) );
$ok( 'content_has: self-closing block', FTT_Registry::content_has( '<!-- wp:ftt/storage-calculator /-->', $st ) );
$ok( 'content_has: block with attrs', FTT_Registry::content_has( '<!-- wp:ftt/storage-calculator {"headroom":"30"} /-->', $st ) );
$ok( 'content_has: no prefix match', ! FTT_Registry::content_has( '[ftt_storagex] <!-- wp:ftt/storage-calculatorx /-->', $st ) );
$ok( 'content_has: calculator alias [film_calculator]', FTT_Registry::content_has( '[film_calculator]', FTT_Registry::embeds()['ftt_calculator'] ) );
$ok( 'content_has: [film_calculator_x] is not the calculator', ! FTT_Registry::content_has( '[film_calculator_x]', FTT_Registry::embeds()['ftt_calculator'] ) );
// Category filter idempotent.
$c = FTT_Blocks::category( FTT_Blocks::category( array( array( 'slug' => 'text', 'title' => 'Text' ) ) ) );
$ok( 'category added once, first', 2 === count( $c ) && 'film-transfer-toolkit' === $c[0]['slug'] );
// Every tool has help content.
foreach ( FTT_Registry::tools() as $k => $t ) {
	$ok( "$k: summary + FAQ + embeds", '' !== $t['summary'] && count( $t['faq'] ) >= 2 && count( $t['embeds'] ) >= 1 );
}
$ok( 'seven tools documented', 7 === count( FTT_Registry::tools() ) );
echo "registry-tests.php: $pass passed, $fail failed\n";
