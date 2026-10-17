<?php
// Local php -S router: adds HTTP Range support for /ftt-test-media/ (the built-in server has none, so videos cannot seek).
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( 0 === strpos( $path, '/ftt-test-media/' ) && preg_match( '/^[a-z0-9._-]+\.webm$/i', basename( $path ) ) && is_file( __DIR__ . $path ) ) {
	$f = __DIR__ . $path; $size = filesize( $f ); $start = 0; $end = $size - 1;
	header( 'Content-Type: video/webm' ); header( 'Accept-Ranges: bytes' );
	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m ) ) {
		if ( '' !== $m[1] ) { $start = (int) $m[1]; } if ( '' !== $m[2] ) { $end = min( (int) $m[2], $size - 1 ); }
		http_response_code( 206 ); header( "Content-Range: bytes $start-$end/$size" );
	}
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	$h = fopen( $f, 'rb' ); fseek( $h, $start ); echo fread( $h, $end - $start + 1 ); fclose( $h );
	return true;
}
if ( '/' !== $path && file_exists( __DIR__ . $path ) && ! is_dir( __DIR__ . $path ) ) {
	return false;
}
if ( is_dir( __DIR__ . $path ) && is_file( rtrim( __DIR__ . $path, '/' ) . '/index.php' ) ) {
	return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
