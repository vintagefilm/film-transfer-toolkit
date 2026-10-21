<?php
/**
 * 1.5.0 tests: one look (shared tokens, no hard-coded colours or fonts outside ftt-base.css, every tool root is
 * .ftt-tool), the "site" calculator scheme, the 4-item admin with redirects and tabs, unchanged option keys, and
 * the loosened site-check calculator test. Restores what it touches.
 *   wp eval-file wp-content/plugins/film-transfer-toolkit/tests/v150-tests.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pass = 0; $fail = 0;
$ok   = function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; } else { $fail++; echo 'FAIL ', $name, '' !== $detail ? ' :: ' . $detail : '', "\n"; }
};
$root = FTT_DIR;

/* ---- CSS lint: literal colours / fonts only in ftt-base.css (admin.css is the WP-admin skin, exempt). */
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$css = array();
foreach ( $it as $f ) {
	if ( preg_match( '/\.css$/', $f->getFilename() ) && false === strpos( $f->getPathname(), '/vendor/' ) ) {
		$css[] = $f->getPathname();
	}
}
$ok( 'found front-end CSS files', count( $css ) >= 5, count( $css ) );
$named = 'white|black|red|green|blue|gray|grey|silver|orange|yellow|purple|navy|teal|maroon|olive|aqua|fuchsia|lime';
foreach ( $css as $file ) {
	$rel = substr( $file, strlen( $root ) );
	if ( in_array( $rel, array( 'assets/css/ftt-base.css', 'assets/css/admin.css', 'assets/css/blocks-editor.css' ), true ) ) {
		continue;
	}
	$src  = preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $file ) );
	$bad  = array();
	foreach ( preg_split( '/[;{}]/', $src ) as $decl ) {
		$decl = trim( $decl );
		if ( '' === $decl || false === strpos( $decl, ':' ) ) {
			continue;
		}
		list( $prop, $val ) = array_map( 'trim', explode( ':', $decl, 2 ) );
		if ( 0 === strpos( $prop, '--' ) ) { // custom property: its value must itself be a var() / keyword, not a literal.
			if ( preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\(/i', $val ) ) { $bad[] = $decl; }
			continue;
		}
		if ( preg_match( '/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\(|\b(?:' . $named . ')\b/i', $val ) && ! preg_match( '/^(?:content|grid-area|animation)/', $prop ) ) {
			$bad[] = $decl;
		}
		if ( preg_match( '/^font(?:-family)?$/', $prop ) && ! preg_match( '/^(?:inherit|var\()/', $val ) ) {
			$bad[] = $decl;
		}
	}
	$ok( "CSS lint: $rel has no hard-coded colours or fonts", ! $bad, implode( ' | ', array_slice( $bad, 0, 4 ) ) );
}
$base = file_get_contents( $root . 'assets/css/ftt-base.css' );
foreach ( array( '--ftt-text', '--ftt-bg', '--ftt-accent', '--ftt-border', '--ftt-font', '--ftt-radius', '--ftt-radius-sm', '--ftt-pad', '--ftt-size-input', '--ftt-max-width' ) as $t ) {
	$ok( "token $t defined", false !== strpos( $base, $t . ':' ) );
}
$ok( 'tokens inherit Kadence palette', false !== strpos( $base, '--global-palette' ) );
$ok( 'tokens inherit theme.json presets', false !== strpos( $base, '--wp--preset--' ) );
$ok( 'content width follows theme', false !== strpos( $base, '--wp--style--global--content-size' ) );
$ok( 'ftt-base registered', wp_style_is( 'ftt-base', 'registered' ) );
$ok( 'ftt-clip depends on ftt-base', false !== strpos( file_get_contents( $root . 'modules/clip-selector/class-shortcode.php' ), "'assets/clip-selector.css', ['ftt-base']" ) );
foreach ( array( 'ftt-front', 'ftt-calculator' ) as $h ) {
	$d = wp_styles()->query( $h );
	$ok( "$h depends on ftt-base", $d && in_array( 'ftt-base', $d->deps, true ), $d ? implode( ',', $d->deps ) : 'unregistered' );
}

/* ---- every tool root carries .ftt-tool */
$keepc = get_option( 'ftt_calc_options', null );
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	if ( 'on' !== ( FTT_Plugin::$status[ $e['module'] ] ?? '' ) ) {
		continue;
	}
	$h = do_shortcode( "[$tag]" );
	$ok( "[$tag] root has ftt-tool", (bool) preg_match( '/^\s*(?:<style[^>]*>.*?<\/style>\s*)?<[a-z]+[^>]*class="[^"]*\bftt-tool\b/s', $h ) || false !== strpos( $h, 'ftt-tool' ), substr( strip_tags( $h ), 0, 80 ) );
}

/* ---- calculator schemes */
delete_option( 'ftt_calc_options' );
$h = do_shortcode( '[ftt_calculator]' );
$ok( 'calculator default scheme is site', false !== strpos( $h, 'filmcalc--site' ), substr( $h, 0, 160 ) );
foreach ( array( 'dark', 'light', 'site' ) as $th ) {
	$h = do_shortcode( "[ftt_calculator theme=\"$th\"]" );
	$ok( "calculator theme=$th", false !== strpos( $h, "filmcalc--$th" ) );
}
$ok( 'saved dark is kept', 'dark' === ftt_calc_sanitize_options( array( 'theme' => 'dark' ) )['theme'] ?? '' );
$ok( 'unknown scheme falls back', in_array( ftt_calc_sanitize_options( array( 'theme' => 'neon' ) )['theme'] ?? '', array( 'site', 'dark' ), true ) );
if ( null === $keepc ) { delete_option( 'ftt_calc_options' ); } else { update_option( 'ftt_calc_options', $keepc ); }
$ok( 'badge filter line kept', false !== strpos( file_get_contents( $root . 'modules/calculator/shortcode.php' ), "apply_filters( 'filmcalc_format_badge', '', \$atts['format'] )" ) );

/* ---- admin: menu, redirects, tabs, keys */
$map = array( 'ftt-calculator' => 'calculator', 'ftt-prices' => 'prices', 'ftt-storage' => 'storage', 'ftt-clips' => 'clips', 'ftt-rfq' => 'rfq', 'ftt-mail' => 'email' );
foreach ( $map as $old => $tab ) {
	$t = FTT_Admin_UX::redirect_target( $old, array( 'page' => $old, 'ftt_msg' => 'saved' ) );
	$ok( "redirect $old", false !== strpos( $t, 'page=ftt-settings' ) && false !== strpos( $t, "tab=$tab" ) && false !== strpos( $t, 'ftt_msg=saved' ), $t );
}
foreach ( array( 'ftt', 'ftt-tools', 'ftt-settings', 'ftt-help', 'ftt-setup' ) as $p ) {
	$ok( "$p is not redirected", '' === FTT_Admin_UX::redirect_target( $p ) );
}
$ok( 'six settings tabs', array( 'email', 'calculator', 'prices', 'storage', 'clips', 'rfq' ) === array_keys( FTT_Admin_UX::tabs() ), implode( ',', array_keys( FTT_Admin_UX::tabs() ) ) );
wp_set_current_user( 1 );
if ( ! class_exists( 'FTT_Setup' ) ) { require_once FTT_DIR . 'includes/admin/class-ftt-setup.php'; }
remove_action( 'admin_menu', array( 'FTT_Admin_UX', 'redirect_old_pages' ), 0 );
if ( ! has_action( 'admin_init', array( 'FTT_Plugin', 'settings' ) ) ) { FTT_Plugin::settings(); }
if ( ! function_exists( 'ftt_calc_register_settings' ) && file_exists( FTT_DIR . 'modules/calculator/admin.php' ) ) { require_once FTT_DIR . 'modules/calculator/admin.php'; ftt_calc_register_settings(); }
do_action( 'admin_init' );
set_current_screen( 'toplevel_page_ftt' );
global $submenu, $menu;
$submenu = array(); $menu = array(); // phpcs:ignore
FTT_Admin::menu();
FTT_Admin_UX::trim_menu();
$labels = array_map( function ( $i ) { return wp_strip_all_tags( $i[0] ); }, $submenu['ftt'] ?? array() );
$ok( 'menu is Dashboard, Tools, Settings, Help', array( 'Dashboard', 'Tools', 'Settings', 'Help' ) === array_values( $labels ), implode( '|', $labels ) );
foreach ( array_keys( FTT_Admin_UX::tabs() ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start();
	FTT_Admin_UX::render_settings();
	$h = ob_get_clean();
	$ok( "tab $tab: one h1", 1 === substr_count( $h, '<h1' ), substr_count( $h, '<h1' ) );
	$ok( "tab $tab: no nested .wrap", 1 === preg_match_all( '/class="wrap\b/', $h ), preg_match_all( '/class="wrap\b/', $h ) );
	$ok( "tab $tab: header nav", false !== strpos( $h, 'ftt-header' ) );
	if ( in_array( $tab, array( 'email', 'calculator', 'prices', 'clips', 'rfq' ), true ) && 'on' === ( FTT_Plugin::$status[ 'email' === $tab ? 'calculator' : ( 'calculator' === $tab ? 'calculator' : ( 'prices' === $tab ? 'pricing' : $tab ) ) ] ?? 'on' ) ) {
		$ok( "tab $tab: has Advanced", false !== strpos( $h, 'ftt-advanced' ) );
	}
}
unset( $_GET['tab'] );
foreach ( array( 'render_dashboard', 'render_tools' ) as $fn ) {
	ob_start(); FTT_Admin_UX::$fn(); $h = ob_get_clean();
	$ok( "$fn: one h1", 1 === substr_count( $h, '<h1' ) );
}
ob_start(); FTT_Admin_UX::render_dashboard(); $h = ob_get_clean();
$ok( 'dashboard has a card per tool', substr_count( $h, 'class="ftt-toolcard' ) >= 7, substr_count( $h, 'class="ftt-toolcard' ) );
$ok( 'cards show block and shortcode', false !== strpos( $h, 'ftt/calculator' ) && false !== strpos( $h, '[ftt_calculator]' ) );
ob_start(); FTT_Admin_UX::render_tools(); $h = ob_get_clean();
$ok( 'tools form saves ftt_modules only', preg_match( '/option_page.{0,3} value=.ftt_modules./', $h ) && false === strpos( $h, 'ftt_general[' ) );
$ok( 'email tab saves ftt_general only', ( function () { $_GET['tab'] = 'email'; ob_start(); FTT_Admin_UX::render_settings(); $h = ob_get_clean(); unset( $_GET['tab'] ); return preg_match( '/option_page.{0,3} value=.ftt_general./', $h ) && false !== strpos( $h, 'name="ftt_general[mail_mode]"' ); } )() );
global $new_allowed_options, $allowed_options;
$reg = get_registered_settings();
foreach ( array( 'ftt_modules', 'ftt_general', 'ftt_calc_options', 'ftt_storage', 'ftt_clip_settings', 'ftt_rfq' ) as $k ) {
	$ok( "option $k still registered", isset( $reg[ $k ] ), $k );
}
$ok( 'ftt_general in its own group', 'ftt_general' === ( $reg['ftt_general']['group'] ?? '' ), $reg['ftt_general']['group'] ?? '' );
$ok( 'ftt_modules group unchanged', 'ftt_modules' === ( $reg['ftt_modules']['group'] ?? '' ) );

/* ---- loosened site check: block-only calculator */
$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'v150 bench probe', 'post_content' => '<!-- wp:ftt/calculator /-->' ) );
$rows = FTT_Testbench::check( $pid );
$calc = array_values( array_filter( $rows, function ( $r ) { return 0 === strpos( $r[0], 'calculator rendered' ); } ) );
if ( defined( 'FILMCALC_VERSION' ) ) {
	$ok( 'bench calc check skipped while standalone active (as before)', true );
} else {
	$ok( 'bench calc check passes with block only', $calc && $calc[0][1], $calc ? $calc[0][2] : 'missing' );
}
$h = apply_filters( 'the_content', '<!-- wp:ftt/calculator /-->' );
$ok( 'block-only calculator renders data-ftt-calc', false !== strpos( $h, 'data-ftt-calc' ) );
wp_delete_post( $pid, true );

$ok( 'version 1.5.0', '1.5.0' === FTT_VERSION );
echo "v150: $pass passed, $fail failed\n";
