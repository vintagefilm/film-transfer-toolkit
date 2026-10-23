<?php
/**
 * 1.5.5: "Require sign-in to add to cart". Run: wp eval-file tests/v155-tests.php (FluentCart on for the cart parts).
 */
$pass = 0; $fail = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$pass, &$fail ) { if ( $c ) { $pass++; } else { $fail++; echo 'FAIL ' . $n . ( $d !== '' ? ' :: ' . substr( is_string( $d ) ? $d : wp_json_encode( $d ), 0, 300 ) : '' ) . "\n"; } };
$fc = class_exists( '\FluentCart\App\Helpers\CartHelper' );
$saved = get_option( 'ftt_clip_settings', null );
$rl = function () { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%ftt_clip_rl_%'" ); };
$call = function ( $route, $body ) use ( $rl ) {
	$rl();
	$r = new WP_REST_Request( 'POST', '/ftt/v1/clip' . $route );
	$r->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$r->set_header( 'Content-Type', 'application/json' );
	$r->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $r );
	return array( $res->get_status(), $res->get_data() );
};

$ok( 'version >= 1.5.5', version_compare( FTT_VERSION, '1.5.5', '>=' ) );
// ---- settings ----
delete_option( 'ftt_clip_settings' );
$d = FTT_Clip_Settings::get();
$ok( 'require_login default off', 0 === $d['require_login'] );
$ok( 'login/register pages default 0', 0 === $d['login_page_id'] && 0 === $d['register_page_id'] );
$ok( 'after_login default page', 'page' === $d['after_login'] );
$old = array( 'default_product_id' => 15496, 'default_fps' => '23.976', 'auto_attach' => 'off', 'heading' => 'X' ); // 1.5.4 shape
$s = FTT_Clip_Settings::sanitize( $old );
$ok( '1.5.4 settings: new keys get defaults', 0 === $s['require_login'] && 'page' === $s['after_login'] && 0 === $s['login_page_id'] );
$ok( '1.5.4 settings keep their values', array_intersect_key( $s, $old ) == $old );
$s = FTT_Clip_Settings::sanitize( array( 'require_login' => '1', 'login_page_id' => '15343', 'register_page_id' => '15344abc', 'after_login' => 'cart' ) );
$ok( 'sanitize on/pages/cart', 1 === $s['require_login'] && 15343 === $s['login_page_id'] && 15344 === $s['register_page_id'] && 'cart' === $s['after_login'], $s );
$s = FTT_Clip_Settings::sanitize( array( 'require_login' => '0', 'login_page_id' => '-5', 'after_login' => 'evil' ) );
$ok( 'sanitize off/bad values', 0 === $s['require_login'] && 5 === $s['login_page_id'] && 'page' === $s['after_login'], $s );
update_option( 'ftt_clip_settings', array( 'require_login' => 1 ) );
$ok( 'login_required_for: empty att = setting (on)', FTT_Clip_Settings::login_required_for( array( 'require_login' => '' ) ) );
$ok( 'login_required_for: no overrides', ! FTT_Clip_Settings::login_required_for( array( 'require_login' => 'no' ) ) );
update_option( 'ftt_clip_settings', array( 'require_login' => 0 ) );
$ok( 'login_required_for: empty att = setting (off)', ! FTT_Clip_Settings::login_required_for( array() ) );
$ok( 'login_required_for: yes overrides', FTT_Clip_Settings::login_required_for( array( 'require_login' => 'yes' ) ) );

// ---- registry ----
$att = null;
foreach ( FTT_Registry::tools() as $t ) { foreach ( (array) ( $t['embeds'] ?? array() ) as $e ) { if ( 'ftt_clip_selector' === $e['shortcode'] ) { foreach ( $e['atts'] as $a ) { if ( 'require_login' === $a['name'] ) { $att = $a; } } } } }
$ok( 'registry has require_login', is_array( $att ) && 'clip:require_login' === $att['setting'] && '' === $att['default'] );
$ok( 'registry current value Yes/No', in_array( FTT_Registry::current_value( $att ), array( 'Yes', 'No' ), true ) );

// ---- auth URLs ----
$lp = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'T155 login' ) );
$rp = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'T155 register' ) );
$back = home_url( '/clip/?ftt_clip_resume=abc' );
update_option( 'ftt_clip_settings', array( 'require_login' => 1 ) );
$ok( 'fallback login = wp_login_url', wp_login_url( $back ) === FTT_Clip_Resume::auth_url( 'login', $back ) );
update_option( 'users_can_register', 1 );
$ok( 'fallback register = wp_registration_url + redirect_to', 0 === strpos( FTT_Clip_Resume::auth_url( 'register', $back ), wp_registration_url() ) && false !== strpos( FTT_Clip_Resume::auth_url( 'register', $back ), 'redirect_to=' ) );
update_option( 'ftt_clip_settings', array( 'require_login' => 1, 'login_page_id' => $lp, 'register_page_id' => $rp ) );
$u = FTT_Clip_Resume::auth_url( 'login', $back );
$ok( 'login page + redirect_to', 0 === strpos( $u, get_permalink( $lp ) ) && urldecode( wp_parse_args( wp_parse_url( $u, PHP_URL_QUERY ) )['redirect_to'] ?? '' ) === $back, $u );
$ok( 'register page used', 0 === strpos( FTT_Clip_Resume::auth_url( 'register', $back ), get_permalink( $rp ) ) );
wp_trash_post( $rp );
$ok( 'trashed register page falls back', 0 === strpos( FTT_Clip_Resume::auth_url( 'register', $back ), wp_registration_url() ) );
$ok( 'off-site return URL replaced by home', home_url( '/' ) === FTT_Clip_Resume::safe_return( 'https://evil.example/x' ) );
$ok( 'return URL loses old token', false === strpos( FTT_Clip_Resume::safe_return( $back ), 'ftt_clip_resume' ) );

if ( ! $fc ) {
	echo "SKIP FluentCart parts (FluentCart inactive)\n";
} else {
	$pid = wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'T155 clip product' ) );
	update_post_meta( $pid, '_fw_clip_cart_enabled', '1' ); update_post_meta( $pid, '_fw_fps', 'any' );
	$plain = wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'T155 plain' ) );
	$c1 = array( 'product_id' => $pid, 'clip_id' => 'T155', 'in' => 1, 'out' => 6, 'fps' => '25', 'seq' => 1, 'name' => 'One' );
	$c2 = array( 'product_id' => $pid, 'clip_id' => 'T155', 'in' => 10, 'out' => 14.5, 'fps' => '25', 'seq' => 2, 'name' => 'Two' );
	$price = FTT_Clip::build( $pid, 'T155', 1, 6, '25', 1, 'One' )['price_cents'];
	update_option( 'ftt_clip_settings', array( 'require_login' => 1 ) );
	wp_set_current_user( 0 );

	// render
	$h = FTT_Clip_Shortcode::render( array( 'product_id' => $pid, 'fps' => '25' ) );
	$ok( 'render: data-require-login on cart selector', false !== strpos( $h, 'data-require-login="1"' ) );
	$h = FTT_Clip_Shortcode::render( array( 'product_id' => $pid, 'fps' => '25', 'require_login' => 'no' ) );
	$ok( 'render: require_login="no" -> opt-out sig, no flag', false === strpos( $h, 'data-require-login' ) && false !== strpos( $h, 'data-login-optout="' . FTT_Clip_Rest::login_optout_sig( $pid ) . '"' ) );
	$h = FTT_Clip_Shortcode::render( array() );
	$ok( 'render: request mode never requires sign-in', false === strpos( $h, 'data-require-login' ) );
	$ok( 'render: block-empty att = omitted', FTT_Clip_Shortcode::render( array( 'product_id' => $pid, 'require_login' => '' ) ) === FTT_Clip_Shortcode::render( array( 'product_id' => $pid ) ) );

	// /add as guest
	list( $st, $j ) = $call( '/add', $c1 );
	$ok( 'guest /add refused 401 fwcc_login_required', 401 === $st && 'fwcc_login_required' === ( $j['code'] ?? '' ), array( $st, $j ) );
	list( $st, $j ) = $call( '/add', $c1 + array( 'login_optout' => 'bogus' ) );
	$ok( 'guest /add with bad opt-out sig refused', 401 === $st );
	list( $st, $j ) = $call( '/add', $c1 + array( 'login_optout' => FTT_Clip_Rest::login_optout_sig( $pid ) ) );
	$ok( 'guest /add with valid opt-out sig allowed', 200 === $st, array( $st, $j ) );

	// /pending
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( $c1 + array( 'client_price_cents' => 1 ) ), 'return' => home_url( '/x/' ) ) );
	$ok( 'pending: tampered client price 409', 409 === $st && 'fwcc_price_mismatch' === ( $j['code'] ?? '' ), array( $st, $j ) );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( array( 'product_id' => $plain ) + $c1 ), 'return' => home_url( '/x/' ) ) );
	$ok( 'pending: non-clip product refused', 400 === $st, array( $st, $j ) );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array(), 'return' => home_url( '/x/' ) ) );
	$ok( 'pending: empty list refused', 400 === $st );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( array( 'out' => 9999 ) + $c1 ), 'return' => home_url( '/x/' ) ) );
	$ok( 'pending: too-long clip refused by server rules', $st >= 400, array( $st, $j ) );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( $c1 + array( 'client_price_cents' => $price ), $c2 ), 'return' => home_url( '/clip-page/?a=1' ) ) );
	$ok( 'pending: 200 + 32-char token', 200 === $st && FTT_Clip_Resume::valid_token( $j['token'] ?? '' ), array( $st, $j ) );
	$tok = $j['token'];
	$ok( 'pending: login_url has redirect_to back + token', false !== strpos( urldecode( $j['login_url'] ), 'clip-page/?a=1&ftt_clip_resume=' . $tok ), $j['login_url'] );
	$ok( 'pending: register_url present', '' !== $j['register_url'] );
	$tr = get_transient( FTT_Clip_Resume::PREFIX . $tok );
	$ok( 'pending: transient holds 2 signed clips', is_array( $tr ) && 2 === count( $tr['clips'] ) && hash_equals( FTT_Clip::sign( $tr['clips'][0]['fw_clip'] ), $tr['clips'][0]['fw_sig'] ) );
	$ok( 'pending: stored price is the server price', $price === $tr['clips'][0]['fw_clip']['price_cents'] );
	$ok( 'pending: expires in about 2 hours', abs( $tr['exp'] - time() - 7200 ) < 30 );
	global $wpdb;
	$to = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", '_transient_timeout_' . FTT_Clip_Resume::PREFIX . $tok ) );
	$ok( 'pending: transient TTL 2 hours', $to === 0 || abs( $to - time() - 7200 ) < 30, $to );

	// claim
	$r = FTT_Clip_Resume::claim( $tok );
	$ok( 'claim as guest refused', is_wp_error( $r ) && 'fwcc_login_required' === $r->get_error_code() );
	$ok( 'guest claim does not burn the token', false !== get_transient( FTT_Clip_Resume::PREFIX . $tok ) );
	$uid = wp_insert_user( array( 'user_login' => 't155u' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 't155' . wp_rand() . '@example.com', 'role' => 'subscriber' ) );
	wp_set_current_user( $uid );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( $c1 ), 'return' => home_url( '/' ) ) );
	$ok( 'pending refused when signed in', 400 === $st );
	// tamper the stored price/frames before claim: signature + recalculation must catch it
	$bad = $tr; $bad['clips'][1]['fw_clip']['out_frame'] += 500; $bad['clips'][0]['fw_clip']['price_cents'] = 1;
	set_transient( FTT_Clip_Resume::PREFIX . $tok, $bad, 7200 );
	// 1.5.8: claim skips clips already in the cart (the guest opt-out add above put clip One there in this process); start from an empty cart.
	$c0 = \FluentCart\App\Helpers\CartHelper::getCart( null, true ); if ( $c0 && ! is_wp_error( $c0 ) ) { $c0->cart_data = array(); $c0->save(); }
	$r = FTT_Clip_Resume::claim( $tok );
	$ok( 'claim: tampered frames rejected, tampered price recalculated', ! is_wp_error( $r ) && 1 === count( $r['added'] ) && 1 === count( $r['failed'] ), $r );
	$cart = \FluentCart\App\Helpers\CartHelper::getCart();
	$line = null; foreach ( (array) ( $cart->cart_data ?? array() ) as $it ) { if ( false !== strpos( (string) ( $it['title'] ?? '' ) . (string) ( $it['post_title'] ?? '' ), '"One"' ) ) { $line = $it; } }
	$ok( 'cart has the clip at the server price', $line && (int) $line['unit_price'] === $price, $cart ? $cart->cart_data : null );
	$ok( 'token deleted after claim', false === get_transient( FTT_Clip_Resume::PREFIX . $tok ) );
	$r = FTT_Clip_Resume::claim( $tok );
	$ok( 'second claim refused (single use)', is_wp_error( $r ) && 'fwcc_token' === $r->get_error_code() );
	$ok( 'notice queued for the user', is_array( get_transient( 'ftt_clip_notice_' . $uid ) ) );
	ob_start(); FTT_Clip_Resume::print_notice(); $n = ob_get_clean();
	$ok( 'notice text + cart link', false !== strpos( $n, 'added to your cart' ) && false !== strpos( $n, 'ftt-clip-resume-cart' ) );
	ob_start(); FTT_Clip_Resume::print_notice(); $ok( 'notice shown once', '' === ob_get_clean() );
	// expiry
	wp_set_current_user( 0 );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( $c2 ), 'return' => home_url( '/' ) ) );
	$t2 = $j['token']; $x = get_transient( FTT_Clip_Resume::PREFIX . $t2 ); $x['exp'] = time() - 1; set_transient( FTT_Clip_Resume::PREFIX . $t2, $x, 7200 );
	wp_set_current_user( $uid );
	$r = FTT_Clip_Resume::claim( $t2 );
	$ok( 'expired token refused', is_wp_error( $r ) && 'fwcc_token' === $r->get_error_code() );
	$ok( 'bad token format refused', is_wp_error( FTT_Clip_Resume::claim( '../../etc' ) ) );
	// REST /resume
	wp_set_current_user( 0 );
	list( $st, $j ) = $call( '/pending', array( 'clips' => array( $c2 ), 'return' => home_url( '/' ) ) );
	$t3 = $j['token'];
	list( $st, $j ) = $call( '/resume', array( 'token' => $t3 ) );
	$ok( 'REST resume as guest 401', 401 === $st );
	wp_set_current_user( $uid );
	list( $st, $j ) = $call( '/resume', array( 'token' => $t3 ) );
	$ok( 'REST resume signed in adds clip', 200 === $st && 1 === count( $j['added'] ) && isset( $j['cart_url'] ), array( $st, $j ) );
	list( $st, $j ) = $call( '/resume', array( 'token' => $t3 ) );
	$ok( 'REST resume twice 404', 404 === $st );
	// signed-in users unaffected
	list( $st, $j ) = $call( '/add', $c1 );
	$ok( 'signed-in /add works with setting on', 200 === $st, array( $st, $j ) );
	$ok( 'cart_url resolves', '' !== FTT_Clip_Resume::cart_url() );
	wp_set_current_user( 0 );
	wp_delete_post( $pid, true ); wp_delete_post( $plain, true );
	require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $uid );
}
wp_delete_post( $lp, true ); wp_delete_post( $rp, true );
null === $saved ? delete_option( 'ftt_clip_settings' ) : update_option( 'ftt_clip_settings', $saved );
echo "v155 php: $pass passed, $fail failed\n";
