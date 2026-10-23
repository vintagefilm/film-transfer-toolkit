<?php
/** 1.5.8: generic on-accent (+ hook), FTT array options always arrays with defaults. Run: wp eval-file tests/v158-tests.php */
$p = 0; $f = 0; $ok = function ( $n, $c, $d = '' ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo "FAIL $n $d\n"; } };
$ok( 'version 1.5.8', version_compare( FTT_VERSION, '1.5.8', '>=' ) );
$css = file_get_contents( FTT_DIR . 'assets/css/ftt-base.css' );
$ok( 'on-accent default #fff with hook', false !== strpos( $css, '--ftt-on-accent:var(--ftt-site-on-accent,#fff);' ) );
$ok( 'on-accent no longer palette9', ! preg_match( '/--ftt-on-accent:var\(--global-palette9/', $css ) );
$js = file_get_contents( FTT_DIR . 'core/ftt-contrast.js' );
$ok( 'js picks #fff/#111', false !== strpos( $js, "pick = w >= k ? '#fff' : '#111'" ) && false !== strpos( $js, "ftt--auto-on-accent" ) && false !== strpos( $js, "--ftt-site-on-accent" ) );
$ok( 'readme documents hook + patch', false !== strpos( file_get_contents( FTT_DIR . 'readme.txt' ), '--ftt-site-on-accent' ) && false !== strpos( file_get_contents( FTT_DIR . 'readme.txt' ), 'wp option patch update ftt_clip_settings require_login 1' ) );
$keep = array( get_option( 'ftt_clip_settings', null ), get_option( 'ftt_general', null ) );
$O = 'ftt_clip_settings'; $D = FTT_Clip_Settings::defaults();
delete_option( $O ); FTT_Plugin::ensure_array_options(); wp_cache_delete( 'alloptions', 'options' );
$ok( 'missing option created as array with all defaults', get_option( $O ) === $D, wp_json_encode( get_option( $O ) ) );
update_option( $O, '' ); FTT_Plugin::ensure_array_options();
$ok( 'scalar option repaired to defaults', get_option( $O ) === $D );
update_option( $O, array( 'require_login' => 1, 'login_page_id' => 15343, 'register_page_id' => 15344, 'after_login' => 'cart' ) ); FTT_Plugin::ensure_array_options();
$g = get_option( $O );
$ok( 'partial option: saved values kept', 1 === $g['require_login'] && 15343 === $g['login_page_id'] && 15344 === $g['register_page_id'] && 'cart' === $g['after_login'], wp_json_encode( $g ) );
$ok( 'partial option: missing keys filled', count( $g ) === count( $D ) && $D['heading'] === $g['heading'] );
$ok( 'effective settings unchanged by filling', FTT_Clip_Settings::sanitize( array( 'require_login' => 1, 'login_page_id' => 15343, 'register_page_id' => 15344, 'after_login' => 'cart' ) ) === FTT_Clip_Settings::get() );
$before = get_option( $O ); global $wpdb; $q = $wpdb->num_queries; FTT_Plugin::ensure_array_options();
$ok( 'complete option: nothing written', get_option( $O ) === $before && $wpdb->num_queries === $q, $wpdb->num_queries - $q );
delete_option( 'ftt_general' ); FTT_Plugin::ensure_array_options();
$ok( 'ftt_general created as array', array( 'mail_mode' => 'log', 'notify_email' => '' ) === get_option( 'ftt_general' ) );
$ok( 'hooked on init', false !== has_action( 'init', array( 'FTT_Plugin', 'ensure_array_options' ) ) );
foreach ( array( 'ftt_clip_settings', 'ftt_general' ) as $i => $n ) { null === $keep[ $i ] ? delete_option( $n ) : update_option( $n, $keep[ $i ] ); }
echo "v158 php: $p passed, $f failed\n";
