<?php
/**
 * 1.5.6: Jump to In / Jump to Out at the END of their rows (after the +1s trim button). Run: wp eval-file tests/v156-tests.php
 */
$p = 0; $f = 0; $ok = function ( $n, $c ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo "FAIL $n\n"; } };
$ok( 'version 1.5.6', version_compare( FTT_VERSION, '1.5.6', '>=' ) );
$r = do_shortcode( '[ftt_clip_selector clip_id="V156"]' );
$m = substr( $r, strpos( $r, 'class="fwcc-marks"' ) ); $m = substr( $m, 0, strpos( $m, '</div>' ) );
foreach ( array( 'in' => 'I', 'out' => 'O' ) as $w => $k ) {
	$set = strpos( $m, 'fwcc-set" data-which="' . $w . '"' ); $tc = strpos( $m, 'data-tc="' . $w . '"' );
	$nud = strpos( $m, 'fwcc-nudge" data-which="' . $w . '"' ); $j = strpos( $m, 'fwcc-jump" data-which="' . $w . '"' );
	$ok( "$w: Set, timecode, trims, then Jump", false !== $set && $set < $tc && $tc < $nud && $nud < $j );
	$seg = substr( $m, $nud, $j - $nud );
	$ok( "$w: the button right before Jump is +1s", 1 === preg_match( '/data-d="\+1s"[^>]*>\+1s<\/button><\/span>\s*<button type="button" class="button $/', $seg ) );
	$ok( "$w: Shift+$k shortcut kept", false !== strpos( $m, 'data-which="' . $w . '" aria-keyshortcuts="Shift+' . $k . '"' ) );
}
$ok( 'Jump to In before Set Out', strpos( $m, 'fwcc-jump" data-which="in"' ) < strpos( $m, 'fwcc-set" data-which="out"' ) );
$ok( 'exactly two jump buttons, labelled', 2 === substr_count( $m, 'class="button fwcc-jump"' ) && false !== strpos( $m, '>Jump to In</button>' ) && false !== strpos( $m, '>Jump to Out</button>' ) );
$ok( 'marks still a labelled group', false !== strpos( $r, 'class="fwcc-marks" role="group" aria-label="In and Out marks"' ) );
$ok( 'block output identical to shortcode', do_blocks( '<!-- wp:ftt/clip-selector {"clip_id":"V156"} /-->' ) === $r );
$css = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.css' );
$ok( 'css: 5-column wide grid starting Set, timecode', false !== strpos( $css, 'grid-template-columns:max-content minmax(8em,max-content) max-content max-content max-content' ) );
$ok( 'css: narrow puts Jump in column 3 of the trim row (container + fallback)', 2 === substr_count( $css, '.fwcc-marks .fwcc-jump{grid-column:3;justify-self:start;width:auto}' ) );
$ok( 'css: phone width puts Jump on its own full-width line', 2 === substr_count( $css, '.fwcc-trim-label,.fwcc-nudge,.fwcc-marks .fwcc-jump{grid-column:1 / -1}' ) );
$js = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.js' );
$ok( 'js: Shift+I / Shift+O handlers unchanged', false !== strpos( $js, "clickBtn('.fwcc-jump[data-which=in]')" ) && false !== strpos( $js, "clickBtn('.fwcc-jump[data-which=out]')" ) );
echo "v156 php: $p passed, $f failed\n";
