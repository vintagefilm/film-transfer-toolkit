<?php
/** 1.5.9: GitHub release updater. Needs the mock at 127.0.0.1:8099 (tools: /workspace/ftt159/mock). Run: wp eval-file tests/v159-tests.php <dummy-1.5.10.zip> */
$p = 0; $f = 0; $ok = function ( $n, $c, $d = '' ) use ( &$p, &$f ) { if ( $c ) { $p++; } else { $f++; echo "FAIL $n $d\n"; } };
$M = getenv( 'FTT_MOCK' ) ?: '/workspace/ftt159/mock'; $Z = $args[0] ?? getenv( 'FTT_DUMMY_ZIP' );
$rel = function ( $a ) use ( $M ) { shell_exec( 'python3 ' . escapeshellarg( "$M/release.py" ) . ' ' . $a ); delete_site_transient( FTT_Updater::CACHE ); };
$st = function ( $f ) use ( $M ) { return "$M/state/$f"; };
@unlink( $st( 'pause.json' ) ); @unlink( $st( 'private' ) ); @unlink( $st( 'log' ) );
add_filter( 'ftt_updater_api_base', function () { return 'http://127.0.0.1:8099'; } );
$B = FTT_Updater::basename();
$ok( 'version 1.5.9+', version_compare( FTT_VERSION, '1.5.9', '>=' ) );
$ok( 'core filters hooked', has_filter( 'pre_set_site_transient_update_plugins', array( 'FTT_Updater', 'inject' ) ) && has_filter( 'plugins_api', array( 'FTT_Updater', 'plugins_api' ) ) && has_filter( 'upgrader_pre_download', array( 'FTT_Updater', 'pre_download' ) ) && has_filter( 'upgrader_source_selection', array( 'FTT_Updater', 'source_selection' ) ) );
$ok( 'default repo', 'vintagefilm/film-transfer-toolkit' === FTT_Updater::repo() );
$h = str_repeat( 'ab', 32 );
$ok( 'sha from "sha256: x"', $h === FTT_Updater::find_sha( "notes\nsha256: $h\n", 'film-transfer-toolkit-1.5.10.zip' ) );
$ok( 'sha from sums line', $h === FTT_Updater::find_sha( "$h  film-transfer-toolkit-1.5.10.zip\n", 'film-transfer-toolkit-1.5.10.zip' ) );
$ok( 'no sha in text', '' === FTT_Updater::find_sha( 'nothing here', 'x.zip' ) );
$t = function () { $o = new stdClass(); $o->response = array(); $o->no_update = array(); return FTT_Updater::inject( $o ); };
// newer release, sha in body
$rel( "v1.5.10 $Z --sha body" );
$o = $t(); $ok( 'newer release offered', isset( $o->response[ $B ] ) && '1.5.10' === $o->response[ $B ]->new_version, wp_json_encode( FTT_Updater::status() ) );
$ok( 'package marker', isset( $o->response[ $B ] ) && false !== strpos( $o->response[ $B ]->package, FTT_Updater::MARK . '101' ) );
$i = FTT_Updater::plugins_api( false, 'plugin_information', (object) array( 'slug' => 'film-transfer-toolkit' ) );
$ok( 'plugins_api details', is_object( $i ) && '1.5.10' === $i->version && false !== strpos( $i->sections['changelog'], 'Test release' ) );
$ok( 'plugins_api ignores other slugs', false === FTT_Updater::plugins_api( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) );
$file = FTT_Updater::pre_download( false, $o->response[ $B ]->package );
$ok( 'download ok, sha256 matches, token not sent to storage', is_string( $file ) && hash_file( 'sha256', $file ) === hash_file( 'sha256', $Z ), is_wp_error( $file ) ? $file->get_error_message() : '' );
if ( is_string( $file ) ) { @unlink( $file ); }
$ok( 'other packages untouched', false === FTT_Updater::pre_download( false, 'https://downloads.wordpress.org/plugin/akismet.zip' ) );
// sha in SHA256SUMS.txt asset
$rel( "v1.5.10 $Z --sha asset" ); $o = $t(); $ok( 'sha from SHA256SUMS.txt asset', isset( $o->response[ $B ] ) && hash_file( 'sha256', $Z ) === FTT_Updater::status()['sha256'] );
// bad sha: offered, but the download is refused
$rel( "v1.5.10 $Z --sha bad" ); $o = $t(); $r = FTT_Updater::pre_download( false, $o->response[ $B ]->package ?? FTT_Updater::MARK . '101' );
$ok( 'sha mismatch refused', is_wp_error( $r ) && 'ftt_sha256' === $r->get_error_code() );
$rel( "v1.5.10 $Z --sha none" ); $o = $t(); $ok( 'no sha: not offered', ! isset( $o->response[ $B ] ) && 'no sha256' === FTT_Updater::status()['state'] );
$rel( "v1.5.10-hold $Z" ); $o = $t(); $ok( 'tag hold: paused', ! isset( $o->response[ $B ] ) && 'hold' === FTT_Updater::status()['state'] );
$rel( "v1.5.10 $Z --name HOLD-test" ); $o = $t(); $ok( 'title hold: paused', ! isset( $o->response[ $B ] ) );
$rel( "v1.5.10 $Z --prerelease" ); $o = $t(); $ok( 'prerelease ignored', ! isset( $o->response[ $B ] ) );
$rel( "v1.5.10 $Z" ); file_put_contents( $st( 'pause.json' ), '{"pause": true}' ); delete_site_transient( FTT_Updater::CACHE ); $o = $t();
$ok( 'ftt-pause.json pauses', ! isset( $o->response[ $B ] ) && 0 === strpos( FTT_Updater::status()['state'], 'paused' ) );
$r = FTT_Updater::pre_download( false, 'x' . FTT_Updater::MARK . '101' ); $ok( 'paused: download refused too', is_wp_error( $r ) );
file_put_contents( $st( 'pause.json' ), '{"pause": false}' ); delete_site_transient( FTT_Updater::CACHE ); $o = $t(); $ok( 'pause false: offered', isset( $o->response[ $B ] ) );
unlink( $st( 'pause.json' ) );
$rel( "v1.5.9 $Z" ); $o = $t(); $ok( 'same version: no_update entry (auto-update toggle)', ! isset( $o->response[ $B ] ) && isset( $o->no_update[ $B ] ) );
$rel( "v1.5.10 $Z" );
// private repo
$TOK = 'tok_' . wp_generate_password( 24, false ); file_put_contents( $st( 'private' ), $TOK ); delete_option( FTT_Updater::TOKEN_OPT ); delete_site_transient( FTT_Updater::CACHE );
$o = $t(); $ok( 'private, no token: nothing offered, clear state', ! isset( $o->response[ $B ] ) && false !== strpos( FTT_Updater::status()['state'], 'needs FTT_GITHUB_TOKEN' ) );
FTT_Updater::set_token( $TOK ); $o = $t(); $ok( 'private + option token: offered', isset( $o->response[ $B ] ) );
$ok( 'token option not autoloaded', 'no' === $GLOBALS['wpdb']->get_var( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name='ftt_github_token'" ) || 'off' === $GLOBALS['wpdb']->get_var( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name='ftt_github_token'" ) );
$file = FTT_Updater::pre_download( false, $o->response[ $B ]->package ?? '' ); $ok( 'private download ok (token to API only)', is_string( $file ) ); if ( is_string( $file ) ) { @unlink( $file ); }
$log = file_get_contents( $st( 'log' ) ); $ok( 'storage never got Authorization', ! preg_match( '#/dl/\S+ auth=yes#', $log ) );
wp_set_current_user( 1 ); ob_start(); FTT_Updater_Admin::tab(); $html = ob_get_clean();
$all = $html . wp_json_encode( FTT_Updater::status() ) . wp_json_encode( FTT_Updater::plugins_api( false, 'plugin_information', (object) array( 'slug' => 'film-transfer-toolkit' ) ) ) . wp_json_encode( $t() ) . wp_json_encode( get_site_transient( FTT_Updater::CACHE ) );
$ok( 'token never output (admin, status, details, transients)', false === strpos( $all, $TOK ) && false !== strpos( $html, 'type="password"' ) && false !== strpos( $html, 'value=""' ) );
$ok( 'front end has no token', false === strpos( do_shortcode( '[ftt_price_table]' ), $TOK ) );
FTT_Updater::set_token( '' ); unlink( $st( 'private' ) ); delete_site_transient( FTT_Updater::CACHE );
$ok( 'public: no token needed', isset( $t()->response[ $B ] ) && 'none' === FTT_Updater::token_source() );
delete_site_transient( FTT_Updater::CACHE ); @unlink( $st( 'latest.json' ) );

// ---- 1.5.9 bug fixes: header mini-cart after a REST add; server-priced estimate ----
if ( class_exists( 'FTT_Clip_Rest' ) ) {
	$m = FTT_Clip_Rest::mini_cart_summary();
	$ok( 'minicart summary has count/qty/subtotal', is_array( $m ) && isset( $m['count'], $m['qty'], $m['subtotal'], $m['subtotal_cents'] ) );
	add_filter( 'fluent_cart/currency_sign', $cs = function () { return '&#36;'; } );
	$ok( 'minicart subtotal is plain text (no HTML entities)', false === strpos( FTT_Clip_Rest::mini_cart_summary()['subtotal'], '&#' ) );
	do_action( 'rest_api_init' ); $rs = rest_get_server()->get_routes();
	$ok( 'minicart route registered', isset( $rs['/ftt/v1/clip/minicart'] ) );
	$ok( 'no-cache hook for cart holders', false !== has_action( 'send_headers', [ 'FTT_Clip_Rest', 'nocache_for_cart_holders' ] ) );
	$js = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.js' );
	$ok( 'JS refreshes the FluentCart mini-cart', false !== strpos( $js, 'fluentCartNotifyCartDrawerItemChanged' ) && false !== strpos( $js, "/minicart'" ) );
	$ok( 'JS no longer hard-codes $0.00 estimate', false === strpos( $js, "'\$0.00'" ) );
	$ok( 'JS uses server /quote price in the list', false !== strpos( $js, "C.rest + '/quote'" ) && false !== strpos( $js, 'row.price' ) );
}
echo "$p passed, $f failed\n";
