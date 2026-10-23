<?php
/**
 * 1.5.4: any-frame-rate clip products, delivery helper, calculator heading, Set/Jump layout.
 * Run: wp eval-file tests/v154-tests.php   (creates and deletes its own probe products)
 */
$P = 0; $F = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$P, &$F ) { if ( $c ) { $P++; } else { $F++; echo "FAIL $n" . ( '' !== $d ? ' :: ' . ( is_string( $d ) ? $d : wp_json_encode( $d ) ) : '' ) . "\n"; } };
$err = function ( $x ) { return is_wp_error( $x ) ? $x->get_error_code() : ( is_array( $x ) ? 'array' : gettype( $x ) ); };
wp_set_current_user( 1 );
$mk = function ( $meta ) { return wp_insert_post( array( 'post_type' => 'fluent-products', 'post_status' => 'private', 'post_title' => 'v154 probe', 'meta_input' => $meta + array( '_fw_clip_cart_enabled' => '1' ) ) ); };
$any   = $mk( array( '_fw_fps' => 'any', '_fw_clip_title' => 'Film clip' ) );
$fixed = $mk( array( '_fw_fps' => '60', '_fw_clip_id' => 'VFC-YT-PILOT' ) );
$fixdf = $mk( array( '_fw_fps' => '29.97df' ) );
$nofps = $mk( array() );
$filep = $mk( array( '_fw_fps' => 'any', '_fw_source_file' => "Reel 7 master's.mov" ) );

/* timecode table */
foreach ( array( '23.976', '24', '25', '29.97df', '29.97ndf', '30', '50', '59.94df', '59.94ndf', '60' ) as $r ) { $ok( "rate $r supported", $r === FTT_Timecode::norm( $r ) ); }
$ok( '50 fps: 1 h = 180000 frames', '01:00:00:00' === FTT_Timecode::frames_to_tc( 180000, '50' ) );
$ok( '59.94 NDF: 3600 frames = 00:01:00:00', '00:01:00:00' === FTT_Timecode::frames_to_tc( 3600, '59.94ndf' ) );
$ok( '59.94 NDF round trip', 123457 === FTT_Timecode::tc_to_frames( FTT_Timecode::frames_to_tc( 123457, '59.94ndf' ), '59.94ndf' ) );
$ok( '"59.94" still means DF', '59.94df' === FTT_Timecode::norm( '59.94' ) );

/* any-rate product */
$ok( 'product_fps any', 'any' === FTT_Clip::product_fps( $any ) );
$ok( 'case-insensitive ANY', ( update_post_meta( $any, '_fw_fps', ' ANY ' ) || true ) && 'any' === FTT_Clip::product_fps( $any ) );
update_post_meta( $any, '_fw_fps', 'any' );
foreach ( array( '23.976', '24', '25', '29.97df', '29.97ndf', '30', '50', '59.94df', '59.94ndf', '60' ) as $r ) {
	$c = FTT_Clip::build( $any, 'VID-1', 1, 31.5, $r );
	$ok( "any product accepts $r", is_array( $c ) && $r === $c['fps'], $err( $c ) );
	if ( is_array( $c ) ) {
		$frames = $c['out_frame'] - $c['in_frame'];
		$ok( "$r frame-accurate duration", abs( $c['duration'] - round( FTT_Timecode::frames_to_seconds( $frames, $r ), 3 ) ) < 1e-9 );
		$ok( "$r price from rate card", FTT_Clip_Rate_Card::price_cents( FTT_Timecode::frames_to_seconds( $frames, $r ) ) === $c['price_cents'] );
		$v = FTT_Clip::to_variation( $c );
		$back = FTT_Clip::from_stored( $v['other_info'] );
		$ok( "$r signed round trip", is_array( $back ) && $back['price_cents'] === $c['price_cents'] && $back['fps'] === $r, $err( $back ) );
		$t = $v['other_info']; $t['fw_clip']['fps'] = ( '25' === $r ) ? '24' : '25';
		$ok( "$r tampered fps rejected", is_wp_error( FTT_Clip::from_stored( $t ) ) && 'fwcc_bad_sig' === FTT_Clip::from_stored( $t )->get_error_code(), $err( FTT_Clip::from_stored( $t ) ) );
	}
}
$c25 = FTT_Clip::build( $any, 'VID-1', 0, 10, '25' ); $c24 = FTT_Clip::build( $any, 'VID-1', 0, 10, '24' );
$ok( 'same in/out at two rates gives two different signed lines', FTT_Clip::item_id( $c25 ) !== FTT_Clip::item_id( $c24 ) && FTT_Clip::sign( $c25 ) !== FTT_Clip::sign( $c24 ) );
$t = FTT_Clip::to_variation( $c25 )['other_info']; $t['fw_clip']['out_frame'] = 2;
$ok( 'tampered out frame rejected', 'fwcc_bad_sig' === $err( FTT_Clip::from_stored( $t ) ) );
$ok( 'unsupported rate still refused on any product', 'fwcc_bad_fps' === $err( FTT_Clip::build( $any, 'VID-1', 0, 10, '48' ) ) );
$ok( 'any product: offset 0 without _fw_tc_offset', 0 === FTT_Clip::product_offset_frames( $any, '25' ) );
update_post_meta( $any, '_fw_tc_offset', '01:00:00:00' );
$ok( 'any product: offset uses the clip rate (25)', 90000 === FTT_Clip::product_offset_frames( $any, '25' ) );
$ok( 'any product: offset uses the clip rate (60)', 216000 === FTT_Clip::product_offset_frames( $any, '60' ) );
$o = FTT_Clip::build( $any, 'VID-1', 0, 1, '25' );
$ok( 'any product: master TC at clip rate', is_array( $o ) && '01:00:00:00' === $o['in_tc'] && '01:00:01:00' === $o['out_tc'], is_array( $o ) ? $o['in_tc'] . '/' . $o['out_tc'] : $err( $o ) );
delete_post_meta( $any, '_fw_tc_offset' );

/* fixed-rate products unchanged */
$ok( 'fixed 60: 60 ok', is_array( FTT_Clip::build( $fixed, 'VFC-YT-PILOT', 0, 5, '60' ) ) );
foreach ( array( '59.94df', '30', '25', '23.976' ) as $r ) { $ok( "fixed 60: $r refused", 'fwcc_fps_mismatch' === $err( FTT_Clip::build( $fixed, 'VFC-YT-PILOT', 0, 5, $r ) ) ); }
$ok( 'fixed 60: clip lock still enforced', 'fwcc_clip_mismatch' === $err( FTT_Clip::build( $fixed, 'OTHER', 0, 5, '60' ) ) );
$ok( 'fixed 29.97df: 29.97ndf refused', 'fwcc_fps_mismatch' === $err( FTT_Clip::build( $fixdf, 'X', 0, 5, '29.97ndf' ) ) );
$ok( 'no _fw_fps: still defaults to 29.97 DF and enforces it', '29.97df' === FTT_Clip::product_fps( $nofps ) && 'fwcc_fps_mismatch' === $err( FTT_Clip::build( $nofps, 'X', 0, 5, '25' ) ) );
$ok( 'bad _fw_fps value is not "any"', ( update_post_meta( $nofps, '_fw_fps', 'anything' ) || true ) && '29.97df' === FTT_Clip::product_fps( $nofps ) );

/* shortcode fps resolution */
if ( class_exists( '\FluentCart\App\Helpers\CartHelper' ) ) {
$sc = function ( $a ) { return do_shortcode( '[ftt_clip_selector ' . $a . ']' ); };
$ok( 'shortcode: any product + fps="50" -> data-fps 50', false !== strpos( $sc( 'product_id="' . $any . '" clip_id="VID-1" fps="50"' ), 'data-fps="50"' ) );
$set = FTT_Clip_Settings::get();
$ok( 'shortcode: any product, no fps -> settings default', false !== strpos( $sc( 'product_id="' . $any . '" clip_id="VID-1"' ), 'data-fps="' . $set['default_fps'] . '"' ) );
$ok( 'shortcode: fixed product, no fps -> product rate', false !== strpos( $sc( 'product_id="' . $fixed . '" clip_id="VFC-YT-PILOT"' ), 'data-fps="60"' ) );

} else { echo "SKIP shortcode cart-mode fps checks (FluentCart not active)\n"; }
$sc = function ( $a ) { return do_shortcode( '[ftt_clip_selector ' . $a . ']' ); };
/* delivery helper */
$c = FTT_Clip::build( $any, 'VFC-YT-abc123', 12.5, 20, '25', 2, 'Harbor' );
$v = FTT_Clip::to_variation( $c ); $at = $v['other_info']['item_attributes'] ?? array();
$ok( 'attributes present', array( 'fw_source', 'fw_in', 'fw_out', 'fw_fps', 'fw_ffmpeg' ) === array_keys( $at ), array_keys( $at ) );
$ok( 'source = clip id', 'VFC-YT-abc123' === $at['fw_source']['value'] );
$ok( 'in/out TC', '00:00:12:12' === $at['fw_in']['value'] && '00:00:20:00' === $at['fw_out']['value'], $at['fw_in']['value'] . ' ' . $at['fw_out']['value'] );
$ok( 'fps label', '25 fps' === $at['fw_fps']['value'] );
$cmd = $at['fw_ffmpeg']['value'];
$ok( 'ffmpeg cmd', "ffmpeg -n -i 'VFC-YT-abc123.mov' -ss 12.480000 -t 7.520000 -frames:v 188 -c:v prores_ks -profile:v 3 -c:a pcm_s16le 'VFC-YT-abc123-02_Harbor.mov'" === $cmd, $cmd );
$ok( 'ffmpeg matches cut list frames', false !== strpos( FTT_Clip_Cutlist::ffmpeg( FTT_Clip_Cutlist::rows( array( $c ) ) ), '-frames:v 188' ) );
$ok( 'title carries fps', false !== strpos( $v['title'], '@ 25 fps' ), $v['title'] );
$ok( 'attributes not part of signature (old lines still verify)', is_array( FTT_Clip::from_stored( array( 'fw_clip' => $c, 'fw_sig' => FTT_Clip::sign( $c ) ) ) ) );
$cf = FTT_Clip::build( $filep, 'VID-9', 0, 2, '23.976', 1, "it's" );
$af = FTT_Clip::delivery_attributes( $cf );
$ok( 'source file shown with id', "Reel 7 master's.mov (VID-9)" === $af['fw_source']['value'], $af['fw_source']['value'] );
$ok( 'ffmpeg quotes the file name safely', 0 === strpos( $af['fw_ffmpeg']['value'], "ffmpeg -n -i 'Reel 7 master'\\''s.mov' " ), $af['fw_ffmpeg']['value'] );
$ok( 'ffmpeg output name sanitized', false !== strpos( $af['fw_ffmpeg']['value'], "'VID-9-01_it_s.mov'" ), $af['fw_ffmpeg']['value'] );
$ok( 'ffmpeg at 59.94 NDF uses exact seconds', false !== strpos( FTT_Clip::ffmpeg_command( FTT_Clip::build( $any, 'V', 0, 1.001, '59.94ndf' ) ), '-t 1.001000 -frames:v 60' ), FTT_Clip::ffmpeg_command( FTT_Clip::build( $any, 'V', 0, 1.001, '59.94ndf' ) ) );
$ok( 'no customer delivery hooks added', ! has_action( 'fluent_cart/order_paid' ) || false === strpos( wp_json_encode( $GLOBALS['wp_filter']['fluent_cart/order_paid'] ?? array() ), 'FTT_' ) );

/* calculator heading */
$h = do_shortcode( '[ftt_calculator]' );
$ok( 'calculator default heading FTT Calculator', false !== strpos( $h, 'FTT Calculator' ) && false === strpos( $h, '>Film Calculator<' ) );
$ok( 'calculator title att still overrides', false !== strpos( do_shortcode( '[ftt_calculator title="How long?"]' ), 'How long?' ) );
$ok( 'registry default note', false !== strpos( wp_json_encode( FTT_Registry::tools() ), 'FTT Calculator' ) );

/* layout */
$r = $sc( 'product_id="' . $any . '" clip_id="VID-1"' );
$m = substr( $r, strpos( $r, 'fwcc-marks' ) ); $m = substr( $m, 0, strpos( $m, 'fwcc-namebar' ) );
$ok( 'no separate jump bar', false === strpos( $r, 'fwcc-jumpbar' ) );
$ok( 'Set In, In trims, Jump to In, then Set Out, Out trims, Jump to Out in the marks grid (1.5.6 order)', preg_match( '/fwcc-set" data-which="in".*fwcc-nudge" data-which="in".*fwcc-jump" data-which="in".*fwcc-set" data-which="out".*fwcc-nudge" data-which="out".*fwcc-jump" data-which="out"/s', $m ) );
$ok( 'shortcuts kept', false !== strpos( $m, 'aria-keyshortcuts="Shift+I"' ) && false !== strpos( $m, 'aria-keyshortcuts="Shift+O"' ) );
$ok( 'marks is a labelled group', false !== strpos( $r, 'class="fwcc-marks" role="group" aria-label=' ) );

/* 1.5.4 staff-only rows and admin email block (needs FluentCart) */
if ( class_exists( '\FluentCart\App\Services\Email\Mailer' ) ) {
	$pp = $mk( array( '_fw_fps' => 'any' ) );
	$row = apply_filters( 'fluent_cart/item_display_attr_fw_ffmpeg', array( 'display_title' => 'fw_ffmpeg', 'attr_key' => 'fw_ffmpeg', 'display_value' => 'x' ), array() );
	$ok( 'FluentCart label filter names the row', 'ffmpeg' === $row['display_title'] );
	$row = apply_filters( 'fluent_cart/item_display_attr_fw_source', array( 'display_title' => 'fw_source', 'attr_key' => 'fw_source', 'display_value' => 'x' ), array() );
	$ok( 'label: Source video', 'Source video' === $row['display_title'] );
	$cc = FTT_Clip::build( $pp, 'VFC-YT-abc123', 12.5, 20, '25', 1, 'Harbor' );
	$vv = FTT_Clip::to_variation( $cc );
	$oi = new \FluentCart\App\Models\OrderItem();
	$oi->forceFill( array( 'post_id' => $pp, 'post_title' => $vv['post_title'], 'title' => $vv['title'], 'object_id' => $vv['object_id'], 'other_info' => $vv['other_info'] ) );
	$cust = $oi->variation_display_title;
	$ok( 'customer/cron view: In, Out, fps shown', false !== strpos( $cust, 'In: 00:00:12:12' ) && false !== strpos( $cust, 'Frame rate: 25 fps' ), $cust );
	$ok( 'customer/cron view: no ffmpeg or source', false === strpos( $cust, 'ffmpeg' ) && false === strpos( $cust, 'Source video' ), $cust );
	add_filter( 'ftt/clip/show_delivery_details', '__return_true' );
	$adm = $oi->variation_display_title;
	remove_filter( 'ftt/clip/show_delivery_details', '__return_true' );
	$ok( 'staff view: source and ffmpeg shown', false !== strpos( $adm, 'Source video: VFC-YT-abc123' ) && false !== strpos( $adm, 'ffmpeg: ffmpeg -n -i' ), $adm );
	set_current_screen( 'dashboard' );
	$ok( 'wp-admin as admin: staff view', FTT_Clip_FluentCart_Hooks::is_staff_view() );
	apply_filters( 'fluent_cart/parse_email_block_content', '', '', array() );
	$ok( 'inside email render: not staff view', ! FTT_Clip_FluentCart_Hooks::is_staff_view() );
	$order = (object) array( 'id' => 0, 'order_items' => array( array( 'object_id' => $vv['object_id'], 'other_info' => $vv['other_info'] ) ) );
	$m = \FluentCart\App\Services\Email\Mailer::make()->body( '<html><body><p>Order</p></body></html>' );
	$m = apply_filters( 'fluent_cart/email_notification/mailer', $m, array( 'recipient' => 'admin', 'data' => array( 'order' => $order ) ) );
	$body = Closure::bind( function () { return $this->body; }, $m, get_class( $m ) )();
	$ok( 'admin email: delivery block before </body>', preg_match( '#ftt-clip-delivery.*</body>#s', $body ) && false !== strpos( $body, 'ffmpeg -n -i &#039;VFC-YT-abc123.mov&#039;' ), $body );
	$ok( 'mailer filter ends the email-render flag', FTT_Clip_FluentCart_Hooks::is_staff_view() );
	$m2 = \FluentCart\App\Services\Email\Mailer::make()->body( '<html><body><p>Order</p></body></html>' );
	$m2 = apply_filters( 'fluent_cart/email_notification/mailer', $m2, array( 'recipient' => 'customer', 'data' => array( 'order' => $order ) ) );
	$b2 = Closure::bind( function () { return $this->body; }, $m2, get_class( $m2 ) )();
	$ok( 'customer email: no delivery block', false === strpos( $b2, 'ftt-clip-delivery' ) );
	$m3 = apply_filters( 'fluent_cart/email_notification/mailer', \FluentCart\App\Services\Email\Mailer::make()->body( '<body>x</body>' ), array( 'recipient' => 'admin', 'data' => array( 'order' => (object) array( 'id' => 0, 'order_items' => array( array( 'object_id' => 5, 'other_info' => array() ) ) ) ) ) );
	$ok( 'admin email without clips unchanged', '<body>x</body>' === Closure::bind( function () { return $this->body; }, $m3, get_class( $m3 ) )() );
	wp_delete_post( $pp, true );
} else { echo "SKIP FluentCart email checks (FluentCart not active)\n"; }
$ok( 'version 1.5.4 or later', version_compare( FTT_VERSION, '1.5.4', '>=' ) );

foreach ( array( $any, $fixed, $fixdf, $nofps, $filep ) as $id ) { wp_delete_post( $id, true ); }
echo "v154 php: $P passed, $F failed\n";
