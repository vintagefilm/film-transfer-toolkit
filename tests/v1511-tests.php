<?php
/**
 * 1.5.11: the clip selector has no sign-in / create-account feature. Standard cart flow: any visitor adds clips to the cart;
 * FluentCart handles sign-in, guest checkout and account creation. Run: wp eval-file tests/v1511-tests.php (FluentCart on for the cart parts).
 */
$p = 0; $f = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo 'FAIL ' . $n . ( '' !== $d ? ' :: ' . substr( is_string( $d ) ? $d : wp_json_encode( $d ), 0, 300 ) : '' ) . "\n"; } };
$fc    = class_exists( '\FluentCart\App\Helpers\CartHelper' );
$O     = FTT_Clip_Settings::OPTION;
$saved = get_option( $O, null ); $savedR = get_option( 'ftt_clip_retired_login', null );
$rl    = function () { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%ftt_clip_rl_%'" ); };
$call  = function ( $route, $body ) use ( $rl ) {
	$rl();
	$r = new WP_REST_Request( 'POST', '/ftt/v1/clip' . $route );
	$r->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); $r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $r );
	return array( $res->get_status(), $res->get_data() );
};
$dir = FTT_DIR . 'modules/clip-selector/';
$ok( 'version 1.5.11+', version_compare( FTT_VERSION, '1.5.11', '>=' ) );

// ---- settings: the five sign-in keys are gone ----
$gone = array( 'require_login', 'login_page_id', 'register_page_id', 'after_login', 'login_mode' );
$ok( 'RETIRED lists the five keys', FTT_Clip_Settings::RETIRED === $gone );
delete_option( $O );
$ok( 'defaults have no sign-in keys', ! array_intersect( $gone, array_keys( FTT_Clip_Settings::defaults() ) ) && array( 'default_product_id', 'default_fps', 'auto_attach', 'heading' ) === array_keys( FTT_Clip_Settings::defaults() ) );
$s = FTT_Clip_Settings::sanitize( array( 'require_login' => 1, 'login_page_id' => 5, 'register_page_id' => 6, 'after_login' => 'cart', 'login_mode' => 'add_then_prompt', 'default_fps' => '25' ) );
$ok( 'sanitize drops sign-in keys, keeps others', ! array_intersect( $gone, array_keys( $s ) ) && '25' === $s['default_fps'], $s );
$ok( 'login_required_for removed', ! method_exists( 'FTT_Clip_Settings', 'login_required_for' ) );

// ---- upgrade from 1.5.10: nothing is lost, sign-in keys retired once ----
$live = array( 'default_product_id' => 15496, 'default_fps' => '23.976', 'auto_attach' => 'off', 'require_login' => 1, 'login_page_id' => 15343, 'register_page_id' => 15344, 'after_login' => 'cart', 'heading' => 'Select Stock Footage', 'login_mode' => 'add_then_prompt' ); // VFC shape
delete_option( 'ftt_clip_retired_login' ); update_option( $O, $live );
set_transient( 'ftt_clip_pending_' . str_repeat( 'a', 32 ), array( 'x' => 1 ), 7200 );
$before_eff = FTT_Clip_Settings::get();
FTT_Plugin::ensure_array_options(); wp_cache_delete( 'alloptions', 'options' );
$now = get_option( $O ); $ret = get_option( 'ftt_clip_retired_login' );
$ok( 'upgrade: settings keep every non-sign-in value', array( 'default_product_id' => 15496, 'default_fps' => '23.976', 'auto_attach' => 'off', 'heading' => 'Select Stock Footage' ) === $now, $now );
$ok( 'upgrade: old values kept in ftt_clip_retired_login', is_array( $ret ) && $ret['values'] === array_intersect_key( $live, array_flip( $gone ) ), $ret );
$ok( 'upgrade: effective settings identical', FTT_Clip_Settings::get() === $before_eff );
$ok( 'upgrade: retired option is not autoloaded', 'no' === $GLOBALS['wpdb']->get_var( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name='ftt_clip_retired_login'" ) || 'off' === $GLOBALS['wpdb']->get_var( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name='ftt_clip_retired_login'" ) );
$ok( 'upgrade: pending sign-in selections removed', false === get_transient( 'ftt_clip_pending_' . str_repeat( 'a', 32 ) ) );
$q = $GLOBALS['wpdb']->num_queries; $b2 = get_option( $O ); $r2 = get_option( 'ftt_clip_retired_login' ); FTT_Plugin::ensure_array_options();
$ok( 'second run changes nothing', get_option( $O ) === $b2 && get_option( 'ftt_clip_retired_login' ) === $r2 );
update_option( $O, array( 'require_login' => 0, 'login_mode' => 'block', 'heading' => 'H' ) ); FTT_Plugin::ensure_array_options();
$ok( 'a later retire never overwrites the first stash', get_option( 'ftt_clip_retired_login' ) === $r2 );

// ---- registry / admin / assets: no sign-in UI anywhere ----
$att = array();
foreach ( FTT_Registry::tools() as $t ) { foreach ( (array) ( $t['embeds'] ?? array() ) as $e ) { if ( 'ftt_clip_selector' === $e['shortcode'] ) { $att = array_column( $e['atts'], 'name' ); $ex = array_keys( $e['examples'] ); } } }
$ok( 'registry: no require_login attribute / example', $att && ! in_array( 'require_login', $att, true ) && ! preg_grep( '/require_login/', $ex ), $att );
if ( ! function_exists( 'ftt_clip_render_settings' ) ) { require_once $dir . 'admin.php'; }
$admin_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0]; wp_set_current_user( $admin_id );
ob_start(); ftt_clip_render_settings();
$adm = ob_get_clean(); wp_set_current_user( 0 );
$ok( 'admin screen: no sign-in fields, one explanatory note', false === stripos( $adm, 'login' ) && false === stripos( $adm, 'Require sign-in' ) && false !== strpos( $adm, 'never asks anyone to sign in' ), substr( $adm, 0, 200 ) );
$js = file_get_contents( $dir . 'assets/clip-selector.js' ) . file_get_contents( $dir . 'assets/clip-selector.css' ) . file_get_contents( $dir . 'assets/clip-players.js' );
$ok( 'js/css: no sign-in or create-account UI', ! preg_match( '/signin|Sign in|Create account|fwcc-auth|fwcc-register|login_url|register_url|toLogin|data-require-login|loggedIn|fwcc_login_required/i', $js ), $js && preg_match( '/signin|Sign in|Create account|fwcc-auth|fwcc-register|login_url|register_url|toLogin|data-require-login|loggedIn|fwcc_login_required/i', $js, $m ) ? $m[0] : '' );
$php = ''; foreach ( glob( $dir . '*.php' ) as $fl ) { if ( 'class-clip-settings.php' !== basename( $fl ) ) { $php .= file_get_contents( $fl ); } } // settings keeps the RETIRED key list
$ok( 'php: no pending/resume routes, no login gate, no resume class', ! preg_match( "/'\/pending'|'\/resume'|login_optout|fwcc_login_required|FTT_Clip_Resume|require_login|login_mode|ftt_clip_resume/", preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $php ) ) );

// ---- render: no login UI, with or without the legacy attribute / stored settings ----
update_option( $O, $live ); // legacy stored values must be ignored
$h0 = FTT_Clip_Shortcode::render( array() );
update_option( $O, array_diff_key( $live, array_flip( $gone ) ) );
$h2 = FTT_Clip_Shortcode::render( array() );
update_option( $O, $live );
$h1 = FTT_Clip_Shortcode::render( array( 'require_login' => 'yes' ) );
$ok( 'render: legacy require_login="yes" and legacy settings change nothing', $h0 === $h1 && $h0 === $h2 );
$ok( 'render: no data-require-login / login-optout', false === strpos( $h1, 'data-require-login' ) && false === strpos( $h1, 'data-login-optout' ) && ! preg_match( '/sign.?in|create.?account|log.?in/i', $h1 ) );
$data = wp_scripts()->get_data( 'ftt-clip', 'data' );
$ok( 'localized data: no loggedIn / sign-in strings', is_string( $data ) && false === strpos( $data, 'loggedIn' ) && ! preg_match( '/sign.?in|create account|login/i', $data ), $data );
$ok( 'localized data: added + View cart strings', false !== strpos( (string) $data, 'viewcart' ) && false !== strpos( (string) $data, 'cartUrl' ) );

// ---- routes ----
$routes = rest_get_server()->get_routes();
$ok( 'REST: /pending and /resume are gone', ! isset( $routes['/ftt/v1/clip/pending'] ) && ! isset( $routes['/ftt/v1/clip/resume'] ) && isset( $routes['/ftt/v1/clip/add'] ) && isset( $routes['/ftt/v1/clip/quote'] ) && isset( $routes['/ftt/v1/clip/minicart'] ) );
$ok( 'class FTT_Clip_Resume gone, FTT_Clip_Cart present, guest-cart hook kept', ! class_exists( 'FTT_Clip_Resume' ) && class_exists( 'FTT_Clip_Cart' ) && false !== has_action( 'set_logged_in_cookie', array( 'FTT_Clip_Cart', 'adopt_guest_cart' ) ) );
$ok( 'readme changelog 1.5.11 says sign-in removed', false !== strpos( file_get_contents( FTT_DIR . 'readme.txt' ), "= 1.5.11 =\n* Clip selector: the sign-in / create-account feature inside the selector is removed" ) );

if ( ! $fc ) {
	echo "SKIP FluentCart parts (FluentCart inactive)\n";
} else {
	$pid = wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'T1511 clip product' ) );
	update_post_meta( $pid, '_fw_clip_cart_enabled', '1' ); update_post_meta( $pid, '_fw_fps', 'any' );
	$c1    = array( 'product_id' => $pid, 'clip_id' => 'T1511', 'in' => 1, 'out' => 6, 'fps' => '25', 'seq' => 1, 'name' => 'One' );
	$price = FTT_Clip::build( $pid, 'T1511', 1, 6, '25', 1, 'One' )['price_cents'];
	foreach ( array( 'no settings', 'legacy require_login + add_then_prompt' ) as $i => $mode ) {
		0 === $i ? delete_option( $O ) : update_option( $O, $live );
		wp_set_current_user( 0 );
		$c0 = \FluentCart\App\Helpers\CartHelper::getCart( null, true ); if ( $c0 && ! is_wp_error( $c0 ) ) { $c0->cart_data = array(); $c0->save(); }
		$h = FTT_Clip_Shortcode::render( array( 'product_id' => $pid, 'fps' => '25' ) );
		$ok( "[$mode] guest: selector renders in cart mode, no login UI", false !== strpos( $h, 'data-mode="cart"' ) && false !== strpos( $h, 'fwcc-addcart' ) && ! preg_match( '/sign.?in|create.?account|log.?in/i', $h ) );
		list( $st, $j ) = $call( '/add', $c1 );
		$ok( "[$mode] signed-out /add works", 200 === $st && ! empty( $j['added'] ), array( $st, $j ) );
		$ok( "[$mode] reply has the server price, mini-cart, cart_url, no auth block", $price === (int) ( $j['clip']['price_cents'] ?? -1 ) && isset( $j['mini_cart']['count'] ) && ! empty( $j['cart_url'] ) && ! isset( $j['auth'] ) && 0 === strpos( $j['cart_url'], 'http' ), $j );
		$cart = \FluentCart\App\Helpers\CartHelper::getCart();
		$ok( "[$mode] the clip is in the guest's cart", $cart && 1 === count( (array) $cart->cart_data ) );
		list( $st2, $j2 ) = $call( '/add', $c1 + array( 'client_price_cents' => 1 ) );
		$ok( "[$mode] tampered client price still refused (409)", 409 === $st2 && 'fwcc_price_mismatch' === ( $j2['code'] ?? '' ) );
		list( $st3, $j3 ) = $call( '/add', array( 'product_id' => 0 ) + $c1 );
		$ok( "[$mode] non-clip product still refused", $st3 >= 400 );
		list( $st4 ) = $call( '/pending', array( 'clips' => array( $c1 ), 'return' => home_url( '/' ) ) );
		$ok( "[$mode] /pending is 404", 404 === $st4 );
		list( $st5 ) = $call( '/resume', array( 'token' => str_repeat( 'a', 32 ) ) );
		$ok( "[$mode] /resume is 404", 404 === $st5 );
	}
	// the guest cart follows the visitor into an account
	$uid = wp_insert_user( array( 'user_login' => 't1511u' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 't1511' . wp_rand() . '@example.com', 'role' => 'subscriber' ) );
	$cart = \FluentCart\App\Helpers\CartHelper::getCart( null, false );
	if ( $cart && ! is_wp_error( $cart ) && ! (int) $cart->user_id && $cart->cart_hash ) {
		$_COOKIE['fct_cart_hash'] = $cart->cart_hash;
		FTT_Clip_Cart::adopt_guest_cart( '', 0, 0, $uid );
		$cart2 = \FluentCart\App\Models\Cart::query()->where( 'cart_hash', $cart->cart_hash )->first();
		$ok( 'sign-in: the guest cart with clips is recorded on the user, items untouched', $cart2 && (int) $cart2->user_id === $uid && count( (array) $cart2->cart_data ) === count( (array) $cart->cart_data ) );
	} else {
		$ok( 'sign-in: guest cart available for the adopt check', false, 'no guest cart' );
	}
	$ok( 'cart_url resolves', '' !== FTT_Clip_Cart::cart_url() );
	wp_set_current_user( $uid );
	list( $st, $j ) = $call( '/add', $c1 );
	$ok( 'signed-in /add still works', 200 === $st, array( $st, $j ) );
	wp_set_current_user( 0 );
	wp_delete_post( $pid, true ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid );
}
null === $saved ? delete_option( $O ) : update_option( $O, $saved );
null === $savedR ? delete_option( 'ftt_clip_retired_login' ) : update_option( 'ftt_clip_retired_login', $savedR, false );
echo "v1511 php: $p passed, $f failed\n";
