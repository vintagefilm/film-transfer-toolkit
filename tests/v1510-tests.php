<?php
/**
 * 1.5.10: clip selector rows stay on one line on desktop-width selectors. Run: wp eval-file tests/v1510-tests.php
 * (The rendered-layout check is e2e/t1510layout.js, which needs a browser.)
 */
$p = 0; $f = 0; $ok = function ( $n, $c ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo "FAIL $n\n"; } };
$ok( 'version 1.5.10+', version_compare( FTT_VERSION, '1.5.10', '>=' ) );
$css = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.css' );
$ok( 'css: no 1000px / 680px / 1140px row breakpoints left', false === strpos( $css, 'max-width:1000px' ) && false === strpos( $css, 'max-width:680px' ) && false === strpos( $css, 'max-width:1140px' ) );
$ok( 'css: container breakpoints are max(em,px) (medium 38em/710px, phone 28em/520px)', 1 === substr_count( $css, '@container (max-width:max(38em,710px))' ) && 1 === substr_count( $css, '@container (max-width:max(28em,520px))' ) );
$ok( 'css: fallback media breakpoints 900px and 740px', 1 === substr_count( $css, '@media (max-width:900px)' ) && 1 === substr_count( $css, '@media (max-width:740px)' ) );
$ok( 'css: compact trim and Jump padding beats .ftt-tool button.button', false !== strpos( $css, '.fwcc .fwcc-marks .fwcc-nudge .fwcc-trim-btn{padding-left:.7em;padding-right:.7em}' ) && false !== strpos( $css, '.fwcc .fwcc-marks .fwcc-jump{padding-left:.8em;padding-right:.8em}' ) );
$ok( 'css: wide grid is one row of five columns, 0.75em column gap', false !== strpos( $css, '.fwcc-marks{display:grid;grid-template-columns:max-content minmax(6.5em,max-content) max-content max-content max-content;gap:.5em .75em;' ) );
$ok( 'css: phone tier still stacks Jump full width', false !== strpos( $css, '.fwcc-trim-label,.fwcc-nudge,.fwcc-marks .fwcc-jump{grid-column:1 / -1}' ) );
$ok( 'css: tokens only (no literal colours added)', 0 === preg_match( '/#[0-9a-f]{3,6}\b|rgba?\(/i', $css ) );
$r = do_shortcode( '[ftt_clip_selector clip_id="V1510"]' );
$ok( 'markup unchanged: ten children in the marks group, Jump at row end', 2 === substr_count( $r, 'class="button fwcc-jump"' ) && 2 === substr_count( $r, 'class="button fwcc-set"' ) );
echo "v1510 php: $p passed, $f failed\n";
