<?php
// Minimal GitHub API mock for FTT updater tests. State in ./state: latest.json, assets/<id> files, pause.json, private (token required).
$S = __DIR__ . '/state'; $u = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); $h = getallheaders(); $auth = $h['Authorization'] ?? '';
file_put_contents( "$S/log", date('c') . " $u auth=" . ( $auth ? 'yes' : 'no' ) . ' accept=' . ( $h['Accept'] ?? '' ) . "\n", FILE_APPEND );
$priv = file_exists( "$S/private" );
if ( 0 === strpos( $u, '/dl/' ) ) { // signed-URL host stand-in: must NOT get the token
	if ( $auth ) { http_response_code( 400 ); echo 'auth sent to storage'; exit; }
	$f = "$S/assets/" . basename( $u ); if ( ! is_file( $f ) ) { http_response_code( 404 ); exit; }
	header( 'Content-Type: application/octet-stream' ); readfile( $f ); exit;
}
if ( $priv && 'Bearer ' . trim( file_get_contents( "$S/private" ) ) !== $auth ) { http_response_code( 404 ); echo '{"message":"Not Found"}'; exit; }
if ( preg_match( '#^/repos/[^/]+/[^/]+/releases/latest$#', $u ) ) { if ( ! is_file( "$S/latest.json" ) ) { http_response_code( 404 ); exit; } header( 'Content-Type: application/json' ); readfile( "$S/latest.json" ); exit; }
if ( preg_match( '#^/repos/[^/]+/[^/]+/releases/assets/(\d+)$#', $u, $m ) ) { header( 'Location: http://127.0.0.1:8099/dl/' . $m[1], true, 302 ); exit; }
if ( preg_match( '#^/repos/[^/]+/[^/]+/contents/ftt-pause\.json$#', $u ) ) { if ( ! is_file( "$S/pause.json" ) ) { http_response_code( 404 ); exit; } readfile( "$S/pause.json" ); exit; }
http_response_code( 404 );
