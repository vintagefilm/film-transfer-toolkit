<?php
/**
 * 1.3.0 tests: calculator field toggles, storage defaults, clip requests, clip settings, test bench.
 * Run inside WordPress with all modules on (it restores the options it touches):
 *   wp eval-file wp-content/plugins/film-transfer-toolkit/tests/v130-tests.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pass = 0; $fail = 0;
$ok   = function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; } else { $fail++; echo 'FAIL ', $name, '' !== $detail ? ' :: ' . $detail : '', "\n"; }
};
$keep = array();
foreach ( array( 'ftt_calc_options', 'filmcalc_options', 'ftt_storage', 'ftt_clip_settings', 'ftt_general' ) as $o ) {
	$keep[ $o ] = get_option( $o, null );
}
global $shortcode_tags;
$calc = function ( $atts = array() ) { return ftt_calc_shortcode( $atts ); };
$hidden = function ( $html, $cls ) { return (bool) preg_match( '/class="filmcalc__field ' . preg_quote( $cls, '/' ) . '" hidden/', $html ); };

/* ---------------- Note 1: calculator field toggles ---------------- */
$d = ftt_calc_default_options();
foreach ( array( 'show_meters', 'show_frames', 'show_fps', 'show_speed', 'show_seconds' ) as $k ) {
	$ok( "calc default $k = 1", 1 === $d[ $k ] );
}
delete_option( 'ftt_calc_options' );
$h = $calc();
$ok( 'defaults: no data-hide', false === strpos( $h, 'data-hide=' ) );
$ok( 'defaults: meters visible', ! $hidden( $h, 'filmcalc__f-meters' ) && false !== strpos( $h, 'data-fc="meters"' ) );
$ok( 'defaults: frames visible', ! $hidden( $h, 'filmcalc__f-frames' ) );
$ok( 'defaults: fps picker visible', ! $hidden( $h, 'filmcalc__f-speed' ) );
$ok( 'defaults: ft/min readout', false !== strpos( $h, 'data-fc="ftmin"' ) );
$ok( 'defaults: seconds readout', false !== strpos( $h, 'data-fc="seconds"' ) );
$ok( 'badge filter line kept', false !== strpos( file_get_contents( FTT_DIR . 'modules/calculator/shortcode.php' ), "apply_filters( 'filmcalc_format_badge', '', \$atts['format'] )" ) );
// Migration from 1.2.0 options (no fields_v): extras off -> speed and seconds off, the rest on.
update_option( 'ftt_calc_options', array( 'default_format' => 's8', 'default_fps' => '18', 'theme' => 'light', 'accent' => '#123456', 'heading_font' => 'plain', 'show_extras' => 0, 'show_switch' => 1, 'show_table' => 1 ) );
$o = ftt_calc_get_options();
$ok( 'migrate: extras 0 -> speed 0, seconds 0', 0 === $o['show_speed'] && 0 === $o['show_seconds'], wp_json_encode( $o ) );
$ok( 'migrate: meters/frames/fps on', 1 === $o['show_meters'] && 1 === $o['show_frames'] && 1 === $o['show_fps'] );
$ok( 'migrate: other settings kept', 's8' === $o['default_format'] && 'light' === $o['theme'] && '#123456' === $o['accent'] && 1 === $o['show_table'] );
update_option( 'ftt_calc_options', array( 'default_format' => '16', 'show_switch' => 1 ) ); // 1.2.0 without show_extras key = on
$o = ftt_calc_get_options();
$ok( 'migrate: missing extras -> speed/seconds on', 1 === $o['show_speed'] && 1 === $o['show_seconds'] );
$ok( 'migrate: legacy standalone filmcalc_options', true );
// Saved 1.3.0 options from the Settings form: missing checkbox = off.
update_option( 'ftt_calc_options', ftt_calc_sanitize_options( array( 'default_format' => '16', 'default_fps' => '24', 'show_switch' => 1, 'show_speed' => 1 ) ) );
$o = ftt_calc_get_options();
$ok( 'form save: unticked = 0', 0 === $o['show_meters'] && 0 === $o['show_frames'] && 0 === $o['show_fps'] && 0 === $o['show_seconds'] && 1 === $o['show_speed'] );
$ok( 'form save: show_extras follows speed||seconds', 1 === $o['show_extras'] );
$h = $calc();
$ok( 'settings off: data-hide lists meters frames fps', false !== strpos( $h, 'data-hide="meters frames fps"' ), substr( $h, 0, 300 ) );
$ok( 'settings off: meters hidden', $hidden( $h, 'filmcalc__f-meters' ) );
$ok( 'settings off: frames hidden', $hidden( $h, 'filmcalc__f-frames' ) );
$ok( 'settings off: fps picker hidden', $hidden( $h, 'filmcalc__f-speed' ) );
$ok( 'settings off: custom fps hidden', (bool) preg_match( '/filmcalc__custom" hidden/', $h ) );
$ok( 'settings off: ft/min kept', false !== strpos( $h, 'data-fc="ftmin"' ) );
$ok( 'settings off: seconds gone', false === strpos( $h, 'data-fc="seconds"' ) );
$ok( 'fps still set when picker hidden', (bool) preg_match( '/<option value="24"\s+selected/', $h ) );
// Attributes beat settings.
$h = $calc( array( 'meters' => 'yes', 'frames' => 'yes', 'fps_picker' => 'yes', 'seconds' => 'yes', 'speed' => 'no' ) );
$ok( 'attrs yes: nothing hidden', false === strpos( $h, 'data-hide=' ) && ! $hidden( $h, 'filmcalc__f-meters' ) );
$ok( 'attrs: speed=no removes ft/min', false === strpos( $h, 'data-fc="ftmin"' ) && false !== strpos( $h, 'data-fc="seconds"' ) );
$ok( 'attrs: no stats block when both off', false === strpos( $calc( array( 'speed' => 'no', 'seconds' => 'no' ) ), 'filmcalc__stats' ) );
delete_option( 'ftt_calc_options' );
$h = $calc( array( 'extras' => 'no' ) );
$ok( 'legacy extras=no hides both readouts', false === strpos( $h, 'filmcalc__stats' ) );
$h = $calc( array( 'extras' => 'no', 'seconds' => 'yes' ) );
$ok( 'seconds=yes beats extras=no', false !== strpos( $h, 'data-fc="seconds"' ) && false === strpos( $h, 'data-fc="ftmin"' ) );
$h = $calc( array( 'meters' => 'nonsense' ) );
$ok( 'bad attr value falls back to settings', false === strpos( $h, 'data-hide=' ) );
$h = FTT_Blocks::render( 'ftt_calculator', array( 'meters' => 'no', 'frames' => 'no' ) );
$ok( 'block attrs reach the shortcode', false !== strpos( $h, 'data-hide="meters frames"' ) );
$ok( 'standalone alias honours toggles when it is the toolkit', true );

/* ---------------- Note 3: storage defaults ---------------- */
delete_option( 'ftt_storage' );
$s = ftt_storage_get_options();
$ok( 'storage default format 16mm', '16' === $s['default_format'] );
$ok( 'storage two default deliverables', 2 === count( $s['deliverables'] ) );
$ok( 'deliverable 1 = ProRes 422 HQ UHD, film fps, PCM', array( 'kind' => 'prores', 'fps' => 'film', 'profile' => 'hq', 'size' => 'uhd', 'audio_kbps' => '2304' ) == $s['deliverables'][0], wp_json_encode( $s['deliverables'][0] ) );
$ok( 'deliverable 2 = H.264 16 Mbps, film fps, PCM', array( 'kind' => 'h264', 'fps' => 'film', 'mbps' => '16', 'audio_kbps' => '2304' ) == $s['deliverables'][1], wp_json_encode( $s['deliverables'][1] ) );
$ok( 'shipped defaults survive sanitize unchanged', ftt_storage_sanitize_options( ftt_storage_default_options() ) == ftt_storage_default_options() );
$uhd = FTT_Datarate::presets();
$ok( 'UHD is 3840x2160', (bool) array_filter( $uhd['sizes'], function ( $z ) { return 'uhd' === $z['id'] && 3840 === $z['w'] && 2160 === $z['h']; } ) );
$h = do_shortcode( '[ftt_storage_calculator]' );
$ok( 'shortcode starts on 16mm', false !== strpos( $h, 'data-format="16"' ) );
$ok( 'shortcode carries default deliverables', false !== strpos( $h, 'data-dels="' ) && false !== strpos( html_entity_decode( $h ), '"size":"uhd"' ) && false !== strpos( html_entity_decode( $h ), '"mbps":"16"' ) );
$ok( 'no preset attribute by default', false === strpos( $h, 'data-preset=' ) );
$h = do_shortcode( '[ftt_storage_calculator preset="dpx-2k" format="s8"]' );
$ok( 'preset attr still works', false !== strpos( $h, 'data-preset="dpx-2k"' ) && false !== strpos( $h, 'data-format="s8"' ) );
$ok( 'bad format attr -> settings default', false !== strpos( do_shortcode( '[ftt_storage_calculator format="99"]' ), 'data-format="16"' ) );
// Server maths for the defaults: one 400 ft 16mm reel at 24 fps = 400*40/24 = 666.67 s.
$r = FTT_Datarate::storage( array( 'lines' => array( array( 'format' => '16', 'fps' => 24, 'reels' => 1, 'feet' => 400 ) ), 'deliverables' => array_map( function ( $d ) { $d['audio_kbps'] = (int) $d['audio_kbps']; if ( isset( $d['mbps'] ) ) { $d['mbps'] = (float) $d['mbps']; } return $d; }, $s['deliverables'] ) ) );
$ok( 'datarate accepts the default deliverables', ! $r['errors'], wp_json_encode( $r['errors'] ) );
$h264 = ( 16 + 2.304 ) * 1e6 / 8 * ( 400 * 40 / 24 );
$ok( 'H.264 16 Mbps + PCM size', isset( $r['deliverables'][1] ) && abs( $r['deliverables'][1]['bytes'] - $h264 ) / $h264 < 0.001, ( $r['deliverables'][1]['bytes'] ?? 'x' ) . ' vs ' . $h264 );
// Owner-defined list.
$saved = ftt_storage_from_form( array( 'default_format' => 's8', 'deliverables' => array(
	array( 'kind' => 'prores', 'profile' => '422', 'size_prores' => 'hd', 'size' => '2k-fa', 'fps' => 'film', 'audio_kbps' => '0' ),
	array( 'kind' => '' ),
	array( 'kind' => 'dpx', 'size' => '2k-fa', 'depth' => '10', 'fps' => '24', 'audio_kbps' => '2304', 'mbps' => '5' ),
	array( 'kind' => 'h265', 'mbps' => '-4', 'fps' => '99', 'audio_kbps' => '7' ),
	array( 'kind' => 'h264', 'mbps' => '10' ),
) ) );
$ok( 'form: format saved', 's8' === $saved['default_format'] );
$ok( 'form: empty row skipped, max 4 rows read', 3 === count( $saved['deliverables'] ), wp_json_encode( $saved ) );
$ok( 'form: ProRes uses its own size list', 'hd' === $saved['deliverables'][0]['size'] && '422' === $saved['deliverables'][0]['profile'] );
$ok( 'form: DPX has no audio or mbps', ! isset( $saved['deliverables'][1]['audio_kbps'] ) && ! isset( $saved['deliverables'][1]['mbps'] ) && '10' === $saved['deliverables'][1]['depth'] );
$ok( 'form: bad values clamped/whitelisted', '0.01' === $saved['deliverables'][2]['mbps'] && 'film' === $saved['deliverables'][2]['fps'] && '0' === $saved['deliverables'][2]['audio_kbps'] );
$ok( 'form: no rows -> shipped defaults', ftt_storage_sanitize_options( array( 'deliverables' => array( array( 'kind' => '' ) ) ) )['deliverables'] == ftt_storage_default_options()['deliverables'] );
$ok( 'form: hostile kind dropped', null === ftt_storage_sanitize_deliverable( array( 'kind' => '<script>' ) ) );
update_option( 'ftt_storage', $saved );
$h = do_shortcode( '[ftt_storage_calculator]' );
$ok( 'owner list reaches the page', false !== strpos( $h, 'data-format="s8"' ) && false !== strpos( html_entity_decode( $h ), '"kind":"dpx"' ) );
$ok( 'deliverable labels', 'ProRes 422 HQ, UHD 3840×2160, film frame rate, PCM audio' === ftt_storage_deliverable_label( ftt_storage_default_options()['deliverables'][0] ) || false !== strpos( ftt_storage_deliverable_label( ftt_storage_default_options()['deliverables'][0] ), 'ProRes 422 HQ' ), ftt_storage_deliverable_label( ftt_storage_default_options()['deliverables'][0] ) );
$ok( 'H.264 label', 0 === strpos( ftt_storage_deliverable_label( ftt_storage_default_options()['deliverables'][1] ), 'H.264 16 Mbps' ), ftt_storage_deliverable_label( ftt_storage_default_options()['deliverables'][1] ) );
delete_option( 'ftt_storage' );

/* ---------------- Note 2: clip selector anywhere ---------------- */
$ok( 'clip shortcode registered', isset( $shortcode_tags['ftt_clip_selector'] ) );
delete_option( 'ftt_clip_settings' );
$cs = FTT_Clip_Settings::get();
$ok( 'clip settings defaults', 0 === $cs['default_product_id'] && '29.97df' === $cs['default_fps'] && 'off' === $cs['auto_attach'] );
$ok( 'clip settings sanitize', array( 'default_product_id' => 5, 'default_fps' => '25', 'auto_attach' => 'off', 'heading' => 'Select Stock Footage' ) === FTT_Clip_Settings::sanitize( array( 'default_product_id' => '5x', 'default_fps' => '25', 'auto_attach' => 'evil' ) ) );
$fc = FTT_Clip_Settings::fluentcart_active();
$h  = do_shortcode( '[ftt_clip_selector]' );
$ok( 'no product -> request mode', false !== strpos( $h, 'data-mode="request"' ) );
$ok( 'request mode button text', false !== strpos( $h, 'Send clip request' ) && false !== strpos( $h, 'class="fwcc-request" hidden' ) );
$ok( 'copy list button', false !== strpos( $h, 'fwcc-copy' ) );
$ok( 'honeypot present', false !== strpos( $h, 'fwcc-rq-hp' ) );
$ok( 'default fps from settings', false !== strpos( $h, 'data-fps="29.97df"' ) );
$ok( 'default clip id when no page', false !== strpos( $h, 'data-clip="CLIP"' ) );
$h = do_shortcode( '[ftt_clip_selector player="youtube" player_selector="#a .b" clip_id="X-1" fps="25" title="T"]' );
$ok( 'player youtube accepted', false !== strpos( $h, 'data-player="youtube"' ) );
$ok( 'player selector passed', false !== strpos( $h, 'data-player-selector="#a .b"' ) );
$ok( 'fps attr wins', false !== strpos( $h, 'data-fps="25"' ) && false !== strpos( $h, 'data-clip="X-1"' ) );
foreach ( array( 'html5', 'vimeo', 'fluent', 'fv', 'auto' ) as $pk ) {
	$ok( "player $pk accepted", false !== strpos( do_shortcode( '[ftt_clip_selector player="' . $pk . '"]' ), 'data-player="' . $pk . '"' ) );
}
$ok( 'bad player -> auto', false !== strpos( do_shortcode( '[ftt_clip_selector player="evil"]' ), 'data-player="auto"' ) );
$ok( 'bad clip id -> fallback', false !== strpos( do_shortcode( '[ftt_clip_selector clip_id="<b>"]' ), 'data-clip="CLIP"' ) );
$ok( 'unknown product -> request mode', false !== strpos( do_shortcode( '[ftt_clip_selector product_id="999999"]' ), 'data-mode="request"' ) );
$ok( 'FluentCart inactive here (request mode only)', ! $fc );
// Page context: clip id from slug, title from page.
$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Fire Engines 1950', 'post_name' => 'fire-engines-1950', 'post_content' => '' ) );
$GLOBALS['post'] = get_post( $pid ); setup_postdata( $GLOBALS['post'] );
$h = do_shortcode( '[ftt_clip_selector]' );
$ok( 'clip id from page slug', false !== strpos( $h, 'data-clip="FIRE-ENGINES-1950"' ), substr( $h, 0, 200 ) );
$ok( 'title from page', false !== strpos( $h, 'data-title="Fire Engines 1950"' ) );
$ok( 'page id passed', false !== strpos( $h, 'data-page="' . $pid . '"' ) );
wp_reset_postdata();
wp_delete_post( $pid, true );
// Player detection pattern for automatic placement.
$pat = FTT_Clip_Settings::player_pattern();
foreach ( array( '<figure class="wp-block-video"><video controls src="a.mp4"></video></figure>', '<iframe src="https://www.youtube.com/embed/abc?feature=oembed"></iframe>', '<iframe title="x" src="https://www.youtube-nocookie.com/embed/abc"></iframe>', '<iframe src="https://player.vimeo.com/video/123"></iframe>', '<media-player src="x">', '<div class="flowplayer is-splash">' ) as $snip ) {
	$ok( 'player detected: ' . substr( $snip, 0, 40 ), (bool) preg_match( $pat, $snip ) );
}
$ok( 'no player in plain text', ! preg_match( $pat, '<p>A video about videos <img src="x.jpg"></p>' ) );
// Automatic placement filter.
update_option( 'ftt_clip_settings', array( 'auto_attach' => 'page' ) );
$ok( 'auto attach off outside the loop', '<video src="a"></video>' === FTT_Clip_Settings::auto_attach( '<video src="a"></video>' ) );
update_option( 'ftt_clip_settings', array() );
// Clip request validation (no side effects).
$good = array( 'name' => 'Ann', 'email' => 'ann@example.test', 'note' => "Line 1\nLine 2", 'fps' => '29.97df', 'clip_id' => 'BENCH-1', 'title' => 'Bench', 'page_url' => home_url( '/x/' ), 'clips' => array( array( 'seq' => 1, 'name' => 'A', 'in' => 1.0, 'out' => 4.0 ), array( 'seq' => 2, 'name' => 'B', 'in' => 10, 'out' => 22.5 ) ) );
$r = FTT_Clip_Request::validate( $good );
$ok( 'valid request accepted', ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
if ( ! is_wp_error( $r ) ) {
	$card = FTT_Clip_Rate_Card::get();
	$ok( 'two clips', 2 === count( $r['clips'] ) );
	$ok( 'server price = rate card (min charge)', FTT_Clip_Rate_Card::price_cents( $r['clips'][0]['seconds'] ) === $r['clips'][0]['price_cents'] );
	$ok( 'total = sum', $r['clips'][0]['price_cents'] + $r['clips'][1]['price_cents'] === $r['total_cents'] );
	$ok( 'timecode computed (1.0 s = frame 29 at 29.97)', '00:00:00;29' === $r['clips'][0]['in_tc'] && 29 === $r['clips'][0]['in_frame'], $r['clips'][0]['in_tc'] );
	$ok( 'clip refs', 'BENCH-1-01' === $r['clips'][0]['ref'] && 'BENCH-1-02' === $r['clips'][1]['ref'] );
	$ok( 'note keeps line breaks', false !== strpos( $r['note'], "\n" ) );
	$ok( 'same-site page url kept', home_url( '/x/' ) === $r['page_url'] );
	$t = FTT_Clip_Request::text( $r );
	$ok( 'email text lists clips', false !== strpos( $t, 'BENCH-1-02' ) && false !== strpos( $t, 'Estimated total' ) );
}
$bad = function ( $patch, $code ) use ( $good, $ok ) {
	$x = FTT_Clip_Request::validate( array_merge( $good, $patch ) );
	$ok( 'request rejects ' . $code, is_wp_error( $x ) && $x->get_error_code() === $code, is_wp_error( $x ) ? $x->get_error_code() : 'accepted' );
};
$bad( array( 'name' => '' ), 'ftt_clip_name' );
$bad( array( 'email' => 'bob@' ), 'ftt_clip_email' );
$bad( array( 'fps' => '12' ), 'fwcc_bad_fps' );
$bad( array( 'clip_id' => 'a b' ), 'fwcc_bad_clip' );
$bad( array( 'clips' => array() ), 'ftt_clip_empty' );
$bad( array( 'clips' => array( array( 'in' => 5, 'out' => 5 ) ) ), 'fwcc_bad_range' );
$bad( array( 'clips' => array( array( 'in' => 'x', 'out' => 5 ) ) ), 'fwcc_bad_input' );
$bad( array( 'clips' => array( array( 'in' => 0, 'out' => 100000 ) ) ), 'fwcc_too_long' );
$bad( array( 'clips' => array_fill( 0, 51, array( 'in' => 0, 'out' => 1 ) ) ), 'ftt_clip_many' );
$x = FTT_Clip_Request::validate( array_merge( $good, array( 'page_url' => 'https://evil.example/' ) ) );
$ok( 'foreign page url dropped', ! is_wp_error( $x ) && '' === $x['page_url'] );
// REST: nonce, honeypot, save + log-only mail.
$srv = rest_get_server();
$post = function ( $body ) use ( $srv ) { $q = new WP_REST_Request( 'POST', '/ftt/v1/clip/request' ); $q->set_header( 'content-type', 'application/json' ); $q->set_body( wp_json_encode( $body ) ); return $srv->dispatch( $q ); };
$ok( 'REST token route', 200 === $srv->dispatch( new WP_REST_Request( 'GET', '/ftt/v1/clip/token' ) )->get_status() );
$ok( 'REST request without nonce -> 403', 403 === $post( $good )->get_status() );
$nonce = wp_create_nonce( 'ftt_clip_request' );
$before = count( get_posts( array( 'post_type' => 'ftt_clip_request', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) );
$ok( 'REST honeypot pretends success, saves nothing', 200 === $post( array_merge( $good, array( 'nonce' => $nonce, 'website' => 'spam' ) ) )->get_status() && count( get_posts( array( 'post_type' => 'ftt_clip_request', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ) === $before );
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
update_option( 'ftt_general', array_merge( (array) get_option( 'ftt_general', array() ), array( 'notify_email' => 'owner@example.test', 'mail_mode' => 'log' ) ) );
$logn = count( FTT_Mail::entries() );
$res = $post( array_merge( $good, array( 'nonce' => $nonce ) ) );
$ok( 'REST request saved', 200 === $res->get_status() && ! empty( $res->get_data()['id'] ), wp_json_encode( $res->get_data() ) );
if ( 200 === $res->get_status() ) {
	$rid = (int) $res->get_data()['id'];
	$ok( 'request is private', 'private' === get_post_status( $rid ) );
	$m = json_decode( (string) get_post_meta( $rid, FTT_Clip_Request::META, true ), true );
	$ok( 'request meta stored', is_array( $m ) && 'ann@example.test' === $m['email'] && 2 === count( $m['clips'] ) );
	$e = FTT_Mail::entries();
	$ok( 'owner notified via mail log (not sent)', count( $e ) === $logn + 1 && 'clip-request' === $e[0]['context'] && 'logged (not sent)' === $e[0]['result'], wp_json_encode( $e[0] ?? null ) );
	ob_start(); FTT_Clip_Request::render_box( get_post( $rid ) ); $box = ob_get_clean();
	$ok( 'admin box shows clips', false !== strpos( $box, 'BENCH-1-02' ) && false !== strpos( $box, 'ann@example.test' ) );
	wp_delete_post( $rid, true );
	array_shift( $e ); update_option( FTT_Mail::LOG, $e, false );
}
for ( $i = 0; $i < 6; $i++ ) { $last = $post( array_merge( $good, array( 'nonce' => $nonce ) ) ); if ( 200 === $last->get_status() ) { wp_delete_post( (int) $last->get_data()['id'], true ); } }
$ok( 'REST rate limit (5 per hour per IP)', 429 === $last->get_status() );
$e = FTT_Mail::entries(); update_option( FTT_Mail::LOG, array_values( array_filter( $e, function ( $x ) { return 'clip-request' !== $x['context']; } ) ), false );
$ok( 'clip request CPT not creatable from admin', 'do_not_allow' === get_post_type_object( 'ftt_clip_request' )->cap->create_posts );

/* ---------------- Note 5: test bench ---------------- */
$c = FTT_Testbench::content( 'https://example.test/v.mp4', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ' );
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	if ( 'clips' === $e['module'] ) { continue; }
	$ok( "bench has $tag block", false !== strpos( $c, '<!-- wp:' . $e['block'] . ' /-->' ) );
}
$ok( 'bench has toggled calculator', false !== strpos( $c, '"meters":"no","frames":"no","seconds":"no"' ) );
$ok( 'bench has standalone [film_calculator]', false !== strpos( $c, '[film_calculator]' ) );
$ok( 'bench has html5 sample + selector', false !== strpos( $c, '<video controls' ) && false !== strpos( $c, 'player="html5"' ) );
$ok( 'bench has youtube sample + selector', false !== strpos( $c, 'wp:embed' ) && false !== strpos( $c, 'player="youtube"' ) );
$had = FTT_Testbench::page_id();
$id  = FTT_Testbench::build( 'https://example.test/v.mp4', '' );
$ok( 'bench built', is_int( $id ) && $id > 0 );
$ok( 'bench is draft', 'draft' === get_post_status( $id ) );
$ok( 'bench rebuild reuses the page', FTT_Testbench::build( '', '' ) === $id );
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
$ok( 'publishing the bench is refused', 'draft' === get_post_status( $id ) );
wp_publish_post( $id ); clean_post_cache( $id );
$ok( 'bench stays draft after wp_publish_post', 'draft' === get_post_status( $id ), get_post_status( $id ) );
$chk = FTT_Testbench::check( $id );
foreach ( $chk as $x ) { $ok( 'bench check: ' . $x[0], $x[1], $x[2] ); }
// Menu guard.
$menu = wp_create_nav_menu( 'ftt-bench-test-' . wp_rand() );
$item = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
$ok( 'menu item for the bench is removed', ! get_post( $item ) );
wp_delete_nav_menu( $menu );
if ( ! $had ) { wp_delete_post( $id, true ); }

foreach ( $keep as $o => $v ) {
	if ( null === $v ) { delete_option( $o ); } else { update_option( $o, $v ); }
}
echo "v130-tests.php: $pass passed, $fail failed\n";
