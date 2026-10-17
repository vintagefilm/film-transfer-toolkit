<?php
// php tests/core-tests.php  - runs core/test-vectors against the PHP mirror.
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../includes/core/class-ftt-pricing.php';
require __DIR__ . '/../includes/core/class-ftt-timecode.php';
require __DIR__ . '/../includes/core/class-ftt-datarate.php';
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; } else { $fail++; echo "FAIL $m\n"; } }
function sub( $exp, $got, $where ) {
	foreach ( $exp as $k => $v ) {
		if ( is_array( $v ) && $v && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) { sub( $v, $got[ $k ] ?? null, "$where.$k" ); }
		elseif ( is_array( $v ) && $v && is_array( $v[0] ) ) { foreach ( $v as $i => $e ) { sub( $e, $got[ $k ][ $i ] ?? null, "$where.{$k}[$i]" ); } }
		else { ok( json_encode( $got[ $k ] ?? null ) === json_encode( $v ), "$where.$k expected " . json_encode( $v ) . ' got ' . json_encode( $got[ $k ] ?? null ) ); }
	}
}
function sub_num( $exp, $got, $where ) {
	foreach ( $exp as $k => $v ) {
		$g = is_array( $got ) && array_key_exists( $k, $got ) ? $got[ $k ] : null;
		if ( is_array( $v ) && $v && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) { sub_num( $v, $g, "$where.$k" ); }
		elseif ( is_array( $v ) && $v && is_array( $v[0] ) ) { foreach ( $v as $i => $e ) { sub_num( $e, $g[ $i ] ?? null, "$where.{$k}[$i]" ); } }
		elseif ( is_array( $v ) && ! $v && ! is_array( $g ) ) { ok( false, "$where.$k expected [] got " . json_encode( $g ) ); }
		elseif ( is_array( $v ) && ! $v ) { ok( true, '' ); }
		else { $same = ( is_numeric( $v ) && is_numeric( $g ) && ! is_bool( $v ) && ! is_bool( $g ) ) ? 0 + $v == 0 + $g : $v === $g; ok( $same, "$where.$k expected " . json_encode( $v ) . ' got ' . json_encode( $g ) ); }
	}
}
$V = __DIR__ . '/../core/test-vectors/';
$P = json_decode( file_get_contents( $V . 'pricing.json' ), true );
$table = json_decode( file_get_contents( __DIR__ . '/../core/data/' . $P['table'] ), true );
foreach ( $P['cases'] as $c ) {
	$q = FTT_Pricing::quote( $table, $c['request'] ); $e = $c['expect'];
	if ( isset( $e['error_codes'] ) ) { ok( array_column( $q['errors'], 'code' ) === $e['error_codes'], $c['name'] . ' error codes ' . json_encode( array_column( $q['errors'], 'code' ) ) ); unset( $e['error_codes'] ); }
	sub( $e, $q, $c['name'] );
}
$d = json_decode( file_get_contents( __DIR__ . '/../core/data/price-table-vfc-draft.json' ), true );
ok( 12800 === FTT_Pricing::quote( $d, array( 'lines' => array( array( 'format' => 's8', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ) ) )['totals']['total_cents'], 'draft 7in Super 8 HD = $128.00' );
$T = json_decode( file_get_contents( $V . 'timecode.json' ), true );
foreach ( $T['known'] as $k ) { ok( FTT_Timecode::tc_to_frames( $k['tc'], $k['rate'] ) === $k['frames'], 'tc->f ' . $k['tc'] ); ok( FTT_Timecode::frames_to_tc( $k['frames'], $k['rate'] ) === $k['tc'], 'f->tc ' . $k['frames'] ); }
foreach ( $T['invalid'] as $k ) { ok( null === FTT_Timecode::tc_to_frames( $k['tc'], $k['rate'] ), 'invalid ' . $k['tc'] ); }
foreach ( $T['roundtrip_rates'] as $r ) { $bad = 0; for ( $f = 0; $f <= $T['roundtrip_max_frames']; $f += 7 ) { if ( FTT_Timecode::tc_to_frames( FTT_Timecode::frames_to_tc( $f, $r ), $r ) !== $f ) { $bad++; } } ok( 0 === $bad, "roundtrip $r" ); }
ok( '$1,234.56' === FTT_Pricing::format_money( 123456 ) && '$0.325' === FTT_Pricing::format_mills( 325 ) && '$0.32' === FTT_Pricing::format_mills( 320 ), 'money formatting' );
$D = json_decode( file_get_contents( $V . 'datarate.json' ), true );
foreach ( $D['cases'] as $c ) {
	$q = json_decode( json_encode( FTT_Datarate::storage( $c['request'] ) ), true ); $e = $c['expect'];
	if ( isset( $e['error_codes'] ) ) { ok( array_column( $q['errors'], 'code' ) === $e['error_codes'], $c['name'] . ' error codes ' . json_encode( array_column( $q['errors'], 'code' ) ) ); unset( $e['error_codes'] ); }
	sub_num( $e, $q, $c['name'] );
}
foreach ( $D['fmt_bytes'] as $f ) { ok( FTT_Datarate::fmt_bytes( $f['bytes'], $f['binary'] ) === $f['text'], 'fmt_bytes ' . $f['bytes'] . ' -> ' . FTT_Datarate::fmt_bytes( $f['bytes'], $f['binary'] ) ); }
foreach ( $D['fmt_drive'] as $f ) { ok( FTT_Datarate::fmt_drive( $f['drive'] ) === $f['text'], 'fmt_drive ' . $f['text'] ); }
echo "core-tests.php: $pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
