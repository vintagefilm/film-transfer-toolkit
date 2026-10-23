<?php
/**
 * 1.5.7: dark-site readability (matching bg/text pair, palette3 headings, text-based muted/border/soft, contrast check).
 * Run: wp eval-file tests/v157-tests.php
 */
$p = 0; $f = 0; $ok = function ( $n, $c ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo "FAIL $n\n"; } };
$ok( 'version 1.5.7', version_compare( FTT_VERSION, '1.5.7', '>=' ) );
$css = file_get_contents( FTT_DIR . 'assets/css/ftt-base.css' );
$ok( 'bg pair', false !== strpos( $css, '--ftt-bg:var(--ftt-site-bg,var(--global-palette9,var(--wp--preset--color--base,#fff)));' ) );
$ok( 'text pair', false !== strpos( $css, '--ftt-text:var(--ftt-site-text,var(--global-palette4,var(--wp--preset--color--contrast,#1d1d1d)));' ) );
$ok( 'headings palette3', false !== strpos( $css, '--ftt-heading-color:var(--ftt-site-heading,var(--ftt-site-text,var(--global-palette3,' ) );
$ok( 'container uses the pair', false !== strpos( $css, 'background:var(--ftt-bg);color:var(--ftt-text)' ) );
$ok( 'no currentColor in tokens', ! preg_match( '/--ftt-(muted|border|soft):[^;]*currentColor/', $css ) );
foreach ( array( 'muted' => 70, 'border' => 22, 'soft' => 5 ) as $k => $v ) { $ok( "$k from --ftt-text", false !== strpos( $css, "--ftt-$k:color-mix(in srgb,var(--ftt-text) $v%,transparent)" ) ); }
$ok( 'auto-contrast class', false !== strpos( $css, '.ftt-tool.ftt--auto-contrast{--ftt-bg:var(--global-palette9,#fff);--ftt-text:var(--global-palette3,#1d1d1d)' ) );
$ok( 'override hook documented in css', false !== strpos( $css, 'Override hook: set --ftt-site-bg and --ftt-site-text' ) );
$ok( 'override hook documented in readme', false !== strpos( file_get_contents( FTT_DIR . 'readme.txt' ), '--ftt-site-bg and --ftt-site-text' ) );
$js = file_get_contents( FTT_DIR . 'core/ftt-contrast.js' );
$ok( 'js threshold 4.5 adds class', false !== strpos( $js, "r < 4.5) { el.classList.add('ftt--auto-contrast')" ) );
$ok( 'dark/light calculator themes kept', false !== strpos( $css, '.filmcalc--dark{--ftt-bg:#1d1d1f;--ftt-text:#f5f5f7' ) );
$ok( 'js not queued on pages without a tool', ( function () { FTT_Plugin::print_contrast_check(); return ! wp_script_is( 'ftt-contrast', 'enqueued' ); } )() );
wp_enqueue_style( 'ftt-base' ); FTT_Plugin::print_contrast_check();
$ok( 'js queued when ftt-base is on the page', wp_script_is( 'ftt-contrast', 'enqueued' ) );
echo "v157 php: $p passed, $f failed\n";
