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
update_option( $O, array( 'default_product_id' => 15496, 'default_fps' => '23.976' ) ); FTT_Plugin::ensure_array_options();
$g = get_option( $O );
$ok( 'partial option: saved values kept', 15496 === $g['default_product_id'] && '23.976' === $g['default_fps'], wp_json_encode( $g ) );
$ok( 'partial option: missing keys filled', count( $g ) === count( $D ) && $D['heading'] === $g['heading'] );
$ok( 'effective settings unchanged by filling', FTT_Clip_Settings::sanitize( array( 'default_product_id' => 15496, 'default_fps' => '23.976' ) ) === FTT_Clip_Settings::get() );
$before = get_option( $O ); global $wpdb; $q = $wpdb->num_queries; FTT_Plugin::ensure_array_options();
$ok( 'complete option: nothing written', get_option( $O ) === $before && $wpdb->num_queries === $q, $wpdb->num_queries - $q );
delete_option( 'ftt_general' ); FTT_Plugin::ensure_array_options();
$ok( 'ftt_general created as array', array( 'mail_mode' => 'log', 'notify_email' => '' ) === get_option( 'ftt_general' ) );
$ok( 'hooked on init', false !== has_action( 'init', array( 'FTT_Plugin', 'ensure_array_options' ) ) );
// preferred service + badge
$ex = FTT_Price_Table::preset( 'example' ); $ids = array_column( $ex['services'], 'id' );
$ok( 'fresh install: 4K output + 1080p output built in, with rates', in_array( '4k_out', $ids, true ) && in_array( '1080_out', $ids, true ) && count( array_filter( $ex['rates'], function ( $r ) { return in_array( $r['service'], array( '4k_out', '1080_out' ), true ); } ) ) >= 8 );
$ok( 'example preset unchanged: no preferred/badge keys', ! array_filter( $ex['services'], function ( $s ) { return isset( $s['preferred'] ) || isset( $s['badge'] ); } ) );
$in = $ex; foreach ( $in['services'] as &$sv ) { if ( '4k_out' === $sv['id'] || 'hd' === $sv['id'] ) { $sv['preferred'] = 1; } if ( '4k_out' === $sv['id'] ) { $sv['badge'] = 'Recommended'; } } unset( $sv );
$r = FTT_Price_Table::sanitize( $in ); $pf = array_values( array_filter( $r['table']['services'], function ( $s ) { return ! empty( $s['preferred'] ); } ) );
$ok( 'sanitize: only one preferred kept (first)', 1 === count( $pf ) && 'hd' === $pf[0]['id'] && ! $r['errors'] );
$b = array_column( $r['table']['services'], 'badge', 'id' );
$ok( 'sanitize: badge kept', 'Recommended' === ( $b['4k_out'] ?? '' ) );
$ok( 'sanitize: unset preferred/badge not stored (old tables byte-identical)', FTT_Price_Table::sanitize( $ex )['table'] === $ex );
$keep_t = get_option( 'ftt_price_table', null ); $in2 = $ex; foreach ( $in2['services'] as &$sv ) { if ( '4k_out' === $sv['id'] ) { $sv['preferred'] = 1; $sv['badge'] = 'Recommended'; } } unset( $sv );
FTT_Price_Table::save( $in2 ); $h = do_shortcode( '[ftt_price_table]' );
$ok( 'price table shows badge + preferred class', false !== strpos( $h, 'ftt-table--preferred' ) && false !== strpos( $h, '<span class="ftt-badge">Recommended</span>' ) );
$rm = new ReflectionMethod( "FTT_Estimate", "client_config" ); $rm->setAccessible( true ); $cfg = $rm->invoke( null ); $p4 = array_values( array_filter( $cfg['table']['services'], function ( $s ) { return ! empty( $s['preferred'] ); } ) );
$ok( 'estimate config carries preferred', $p4 && '4k_out' === $p4[0]['id'] );
$js = file_get_contents( FTT_DIR . 'modules/estimate/estimate.js' );
$ok( 'estimate.js preselects preferred', false !== strpos( $js, 'return x.preferred;' ) );
null === $keep_t ? delete_option( 'ftt_price_table' ) : update_option( 'ftt_price_table', $keep_t, false );
// 1.5.11: login_mode and the other sign-in settings are retired (see v1511-tests.php); the guest-cart hook stays
$ok( 'set_logged_in_cookie hook (guest cart kept at sign-in)', false !== has_action( 'set_logged_in_cookie', array( 'FTT_Clip_Cart', 'adopt_guest_cart' ) ) );
foreach ( array( 'ftt_clip_settings', 'ftt_general' ) as $i => $n ) { null === $keep[ $i ] ? delete_option( $n ) : update_option( $n, $keep[ $i ] ); }
echo "v158 php: $p passed, $f failed\n";
