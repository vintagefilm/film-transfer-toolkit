<?php
/**
 * 1.5.1: clip rate card "rounding" (up | down | nearest). PHP side + writes cases for the JS parity check.
 *   bash tests/v151-tests.sh /path/to/wp   (runs this with wp eval-file, then tests/clip-rounding-parity.js)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pass = 0; $fail = 0;
$ok   = function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; } else { $fail++; echo 'FAIL ', $name, '' !== $detail ? ' :: ' . $detail : '', "\n"; }
};
$base = FTT_Clip_Rate_Card::defaults();
$ok( 'default rounding is up', 'up' === $base['rounding'] );
$ok( 'empty option -> up', 'up' === FTT_Clip_Rate_Card::sanitize( array() )['rounding'] );
$ok( 'invalid -> up', 'up' === FTT_Clip_Rate_Card::sanitize( array( 'rounding' => 'sideways' ) )['rounding'] );
foreach ( array( 'up', 'down', 'nearest' ) as $m ) {
	$ok( "sanitize keeps $m", $m === FTT_Clip_Rate_Card::sanitize( array( 'rounding' => $m ) )['rounding'] );
}
$use = function ( $card ) { remove_all_filters( 'ftt/clip/rate_card' ); add_filter( 'ftt/clip/rate_card', function () use ( $card ) { return $card; } ); };
$card = function ( $over ) use ( $base ) { return FTT_Clip_Rate_Card::sanitize( array_merge( $base, $over ) ); };

/* 59.94 DF frame snap: a "30 s" clip snaps to 1800 frames = 30.03 s */
$f   = FTT_Timecode::seconds_to_frames( 30.03, '59.94df' );
$dur = FTT_Timecode::frames_to_seconds( $f, '59.94df' );
$ok( '59.94: 30.03 s snaps to 1800 frames (30.03 s)', 1800 === $f && abs( $dur - 30.03 ) < 0.001, "$f $dur" );
foreach ( array( 'up' => 31, 'down' => 30, 'nearest' => 30 ) as $m => $want ) {
	$c = $card( array( 'rounding' => $m, 'per_second_cents' => 200, 'min_seconds' => 5, 'min_charge_cents' => 1000 ) );
	$use( $c );
	$ok( "59.94 snap, $m bills $want s", $want === FTT_Clip_Rate_Card::billable_seconds( $dur ), FTT_Clip_Rate_Card::billable_seconds( $dur ) );
	$prod = wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'v151 probe product', 'meta_input' => array( '_fw_fps' => '59.94df', '_fw_clip_cart_enabled' => '1' ) ) );
	$cl = FTT_Clip::build( $prod, 'R151-0001', 0, 30.03, '59.94df' );
	wp_delete_post( $prod, true );
	$ok( "59.94 snap via FTT_Clip::build, $m -> " . ( $want * 200 ) . ' cents', is_array( $cl ) && ( $want * 200 ) === (int) $cl['price_cents'], is_array( $cl ) ? $cl['price_cents'] : ( is_wp_error( $cl ) ? $cl->get_error_message() : gettype( $cl ) ) );
}
/* min_seconds interplay */
foreach ( array( array( 4.6, 5, array( 'up' => 5, 'down' => 5, 'nearest' => 5 ) ), array( 5.4, 5, array( 'up' => 6, 'down' => 5, 'nearest' => 5 ) ), array( 30.03, 31, array( 'up' => 31, 'down' => 31, 'nearest' => 31 ) ), array( 0.4, 0, array( 'up' => 1, 'down' => 0, 'nearest' => 0 ) ) ) as $t ) {
	list( $d, $min, $want ) = $t;
	foreach ( $want as $m => $w ) {
		$c = $card( array( 'rounding' => $m, 'min_seconds' => $min ) );
		$ok( "min_seconds=$min, $d s, $m -> $w", $w === FTT_Clip_Rate_Card::billable_seconds( $d, $c ), FTT_Clip_Rate_Card::billable_seconds( $d, $c ) );
	}
}
/* min_charge unchanged: 2 s at 200c with min_charge 1000 -> 1000 in every mode */
foreach ( array( 'up', 'down', 'nearest' ) as $m ) {
	$use( $card( array( 'rounding' => $m, 'min_seconds' => 0, 'per_second_cents' => 200, 'min_charge_cents' => 1000 ) ) );
	$ok( "min_charge still applies ($m)", 1000 === FTT_Clip_Rate_Card::price_cents( 2.2 ) );
}
/* millisecond pre-rounding (same as before for up) */
$ok( 'up: 30.0004 -> 30 (ms first)', 30 === FTT_Clip_Rate_Card::billable_seconds( 30.0004, $card( array( 'rounding' => 'up', 'min_seconds' => 0 ) ) ) );
$ok( 'nearest: 29.4996 -> 30 (ms first: 29.500)', 30 === FTT_Clip_Rate_Card::billable_seconds( 29.4996, $card( array( 'rounding' => 'nearest', 'min_seconds' => 0 ) ) ) );
/* filter */
$use( $card( array() ) );
add_filter( 'ftt/clip/billable_seconds', $fx = function ( $s, $d, $c ) { return is_array( $c ) && isset( $c['rounding'] ) ? $s + 100 : -1; }, 10, 3 );
$ok( 'ftt/clip/billable_seconds filter gets ($secs, $duration, $card)', 110 === FTT_Clip_Rate_Card::billable_seconds( 10 ) );
remove_filter( 'ftt/clip/billable_seconds', $fx, 10 );
/* page script gets the mode */
$use( $card( array( 'rounding' => 'down' ) ) );
do_shortcode( '[ftt_clip_selector clip_id="R151"]' );
$data = wp_scripts()->get_data( 'ftt-clip-selector', 'data' ) . ' ' . implode( ' ', array_map( function ( $h ) { return (string) wp_scripts()->get_data( $h, 'data' ); }, array_keys( (array) wp_scripts()->registered ) ) );
$ok( 'FTT_CLIP.card carries rounding to the browser', false !== strpos( $data, '"rounding":"down"' ) );
/* the settings screen has the field */
wp_set_current_user( 1 );
ob_start(); ftt_clip_render_settings(); $h = ob_get_clean();
$ok( 'settings: rounding radios under Advanced', 3 === substr_count( $h, 'name="ftt_clip_rate_card[rounding]"' ) && strpos( $h, 'ftt-adv-clips' ) < strpos( $h, 'ftt_clip_rate_card[rounding]' ) );
$ok( 'help/registry documents rounding', false !== strpos( wp_json_encode( FTT_Registry::tools() ), 'ftt\/clip\/billable_seconds' ) || false !== strpos( wp_json_encode( FTT_Registry::tools() ), 'ftt/clip/billable_seconds' ) );

/* parity cases for JS */
mt_srand( 151 );
$cases = array();
$durs  = array( 0.001, 0.4, 0.5, 0.5004, 0.4995, 1, 4.2, 4.6, 4.9995, 5.0004, 5.4, 5.5, 29.4995, 29.4996, 29.5, 30, 30.0004, 30.0005, 30.03003003, 59.94, 60.5, 299.9995, 300 );
for ( $i = 0; $i < 400; $i++ ) { $durs[] = mt_rand( 1, 300000 ) / 1000 + mt_rand( 0, 9 ) / 10000; }
foreach ( array( '59.94df', '29.97df', '23.976', '25' ) as $fps ) { for ( $i = 0; $i < 60; $i++ ) { $durs[] = FTT_Timecode::frames_to_seconds( mt_rand( 1, 18000 ), $fps ); } }
$cards = array( array( 'min_seconds' => 5, 'per_second_cents' => 200, 'min_charge_cents' => 1000 ), array( 'min_seconds' => 0, 'per_second_cents' => 7, 'min_charge_cents' => 0 ), array( 'min_seconds' => 31, 'per_second_cents' => 150, 'min_charge_cents' => 500 ) );
foreach ( array( 'up', 'down', 'nearest' ) as $m ) {
	foreach ( $cards as $cc ) {
		$c = $card( array_merge( $cc, array( 'rounding' => $m ) ) );
		$use( $c );
		foreach ( $durs as $d ) {
			$cases[] = array( 'card' => $c, 'dur' => $d, 'secs' => FTT_Clip_Rate_Card::billable_seconds( $d ), 'cents' => FTT_Clip_Rate_Card::price_cents( $d ) );
		}
	}
}
remove_all_filters( 'ftt/clip/rate_card' );
file_put_contents( getenv( 'FTT_CASES' ) ? getenv( 'FTT_CASES' ) : '/tmp/ftt151-cases.json', wp_json_encode( $cases ) );
echo 'cases: ', count( $cases ), "\n";
echo "v151 php: $pass passed, $fail failed\n";
