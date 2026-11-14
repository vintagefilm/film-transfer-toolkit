<?php
/**
 * 1.5.12: fresh-nonce endpoints for the clip selector (pages served from a page cache carry an expired wp_rest nonce).
 * Run: wp eval-file tests/v1512-tests.php (FluentCart on for the /add parts; skipped otherwise).
 */
$p = 0; $f = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo 'FAIL ' . $n . ( '' !== $d ? ' :: ' . substr( is_string( $d ) ? $d : wp_json_encode( $d ), 0, 300 ) : '' ) . "\n"; } };
$fc  = class_exists( '\FluentCart\App\Helpers\CartHelper' );
$rl  = function () { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%ftt_clip_rl_%'" ); };
$old = function ( $uid, $ticks_back ) { // a wp_rest nonce from $ticks_back half-lives ago (what a stale cached page carries)
	$i = wp_nonce_tick( 'wp_rest' ) - $ticks_back;
	return substr( wp_hash( $i . '|wp_rest|' . $uid . '|' . wp_get_session_token(), 'nonce' ), -12, 10 );
};
$post = function ( $route, $nonce, $body ) use ( $rl ) {
	$rl();
	$r = new WP_REST_Request( 'POST', '/ftt/v1/clip' . $route );
	if ( null !== $nonce ) { $r->set_header( 'X-WP-Nonce', $nonce ); }
	$r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $r ); $d = $res->get_data();
	return array( $res->get_status(), is_array( $d ) ? $d : array(), $res );
};
$get = function ( $route ) { $res = rest_do_request( new WP_REST_Request( 'GET', '/ftt/v1/clip' . $route ) ); return array( $res->get_status(), $res->get_data(), $res->get_headers() ); };
$ajax = function () { // run admin-ajax.php?action=ftt_clip_nonce in-process
	add_filter( 'wp_doing_ajax', '__return_true' );
	$h = function () { return function () { throw new RuntimeException( 'ajax-done' ); }; };
	add_filter( 'wp_die_ajax_handler', $h );
	ob_start();
	try { do_action( 'wp_ajax_' . ( is_user_logged_in() ? '' : 'nopriv_' ) . FTT_Clip_Rest::AJAX_NONCE ); } catch ( RuntimeException $e ) {}
	$out = ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' ); remove_filter( 'wp_die_ajax_handler', $h );
	return json_decode( $out, true );
};

$ok( 'version 1.5.12', '1.5.12' === FTT_VERSION, FTT_VERSION );
$readme = file_get_contents( FTT_DIR . 'readme.txt' );
$ok( 'readme Stable tag 1.5.12 + changelog entry', false !== strpos( $readme, 'Stable tag: 1.5.12' ) && false !== strpos( $readme, '= 1.5.12 =' ) );
$hdr = get_file_data( FTT_FILE, array( 'v' => 'Version' ) );
$ok( 'plugin header 1.5.12', '1.5.12' === $hdr['v'], $hdr );

// ---- endpoints are registered ----
$routes = rest_get_server()->get_routes();
$ok( 'REST GET /ftt/v1/clip/nonce registered', isset( $routes['/ftt/v1/clip/nonce'] ) && isset( $routes['/ftt/v1/clip/nonce'][0]['methods']['GET'] ) );
$ok( 'REST /quote and /add still use check_nonce', $routes['/ftt/v1/clip/quote'][0]['permission_callback'] === array( 'FTT_Clip_Rest', 'check_nonce' ) && $routes['/ftt/v1/clip/add'][0]['permission_callback'] === array( 'FTT_Clip_Rest', 'check_nonce' ) );
$ok( 'admin-ajax ftt_clip_nonce for signed-in users', false !== has_action( 'wp_ajax_ftt_clip_nonce', array( 'FTT_Clip_Rest', 'ajax_nonce' ) ) );
$ok( 'admin-ajax ftt_clip_nonce for visitors', false !== has_action( 'wp_ajax_nopriv_ftt_clip_nonce', array( 'FTT_Clip_Rest', 'ajax_nonce' ) ) );

// ---- REST /nonce: visitor nonce, never cached ----
wp_set_current_user( 0 );
list( $st, $d, $h ) = $get( '/nonce' );
$ok( 'REST /nonce 200', 200 === $st, $st );
$ok( 'REST /nonce returns a valid wp_rest nonce', ! empty( $d['nonce'] ) && wp_verify_nonce( $d['nonce'], 'wp_rest' ) );
$ok( 'REST /nonce: Cache-Control no-store + private', isset( $h['Cache-Control'] ) && false !== strpos( $h['Cache-Control'], 'no-store' ) && false !== strpos( $h['Cache-Control'], 'private' ), $h );
$ok( 'REST /nonce: only nonce + ttl in the reply', array( 'nonce', 'ttl' ) === array_keys( $d ), $d );

// ---- admin-ajax nonce: right user, both states ----
$j = $ajax();
$ok( 'ajax (visitor): nonce valid for the visitor', is_array( $j ) && ! empty( $j['nonce'] ) && wp_verify_nonce( $j['nonce'], 'wp_rest' ), $j );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$uid = (int) ( $admins[0] ?? 1 );
wp_set_current_user( $uid );
$j2 = $ajax();
$ok( 'ajax (signed in): nonce valid for that user', is_array( $j2 ) && ! empty( $j2['nonce'] ) && wp_verify_nonce( $j2['nonce'], 'wp_rest' ), $j2 );
wp_set_current_user( 0 );
$ok( 'ajax (signed in): that nonce is NOT valid for a visitor', ! empty( $j2['nonce'] ) && ! wp_verify_nonce( $j2['nonce'], 'wp_rest' ) );

// ---- page config: nonce URL is printed, baked nonce kept as fallback ----
global $wp_scripts; $wp_scripts = null; wp_scripts();
do_shortcode( '[ftt_clip_selector clip_id="T1512" title="Probe"]' );
$data = (string) wp_scripts()->get_data( 'ftt-clip', 'data' );
$ok( 'FTT_CLIP.nonceUrl points at admin-ajax ftt_clip_nonce', false !== strpos( $data, 'admin-ajax.php?action=ftt_clip_nonce' ), substr( $data, 0, 400 ) );
$ok( 'FTT_CLIP.i18n.expired + reload strings', false !== strpos( $data, '"expired"' ) && false !== strpos( $data, '"reload"' ) );
$js = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.js' );
$ok( 'JS: /quote and /add go through apiPost (no direct use of the baked nonce)', 2 === substr_count( $js, "apiPost('/" ) && false === strpos( $js, "'X-WP-Nonce': C.nonce" ) );

// ---- server checks unchanged: stale / bad / missing nonces are rejected, a fresh one passes ----
$body = array( 'product_id' => 0, 'clip_id' => 'T1512', 'in' => 1, 'out' => 2, 'fps' => '25' );
foreach ( array( 'missing' => null, 'garbage' => 'bad0000000', 'expired (4 ticks old)' => $old( 0, 4 ) ) as $k => $n ) {
	list( $st, $d ) = $post( '/quote', $n, $body );
	$ok( "visitor /quote with $k nonce -> 403 fwcc_nonce", 403 === $st && 'fwcc_nonce' === ( $d['code'] ?? '' ), array( $st, $d ) );
}
list( , $tok ) = $get( '/nonce' );
list( $st, $d ) = $post( '/quote', $tok['nonce'], $body );
$ok( 'visitor /quote with the fresh /nonce nonce passes the nonce check', 403 !== $st && 'fwcc_nonce' !== ( $d['code'] ?? '' ), array( $st, $d ) );
$ok( 'a 1-tick-old nonce is still accepted (WordPress grace period unchanged)', 1 === wp_verify_nonce( $old( 0, 0 ), 'wp_rest' ) && 2 === wp_verify_nonce( $old( 0, 1 ), 'wp_rest' ) );
$ok( 'a 2-tick-old nonce is rejected (expired, as on the cached VFC page)', false === wp_verify_nonce( $old( 0, 2 ), 'wp_rest' ) );

if ( $fc ) {
	$prod = wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'T1512 product' ) );
	update_post_meta( $prod, '_fw_fps', 'any' ); update_post_meta( $prod, '_fw_clip_cart_enabled', 1 );
	$b2 = array( 'product_id' => $prod, 'clip_id' => 'T1512', 'in' => 1, 'out' => 3, 'fps' => '25' );
	list( $st, $d ) = $post( '/add', $old( 0, 4 ), $b2 );
	$ok( 'visitor /add with expired nonce -> 403 (nothing added)', 403 === $st, array( $st, $d ) );
	list( , $tok ) = $get( '/nonce' );
	list( $st, $d ) = $post( '/add', $tok['nonce'], $b2 );
	$ok( 'visitor /add retried with a fresh nonce -> 200 added', 200 === $st && ! empty( $d['added'] ), array( $st, $d ) );
	wp_delete_post( $prod, true );
} else {
	echo "SKIP /add checks (FluentCart not active)\n";
}
wp_set_current_user( 0 );
echo "v1512 php: $p passed, $f failed\n";
