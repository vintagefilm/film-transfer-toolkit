<?php
/**
 * 1.4.0 tests: the rename to "FTT" changes names people read and nothing code or saved content depends on;
 * every shortcode option is in its block's sidebar with the same default, a label and help; the option docs are
 * generated from the registry; FilmTransfer.ai's filmcalc_format_badge filter keeps working.
 * Run inside WordPress with all modules on (it restores what it touches):
 *   wp eval-file wp-content/plugins/film-transfer-toolkit/tests/v140-tests.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pass = 0; $fail = 0;
$ok   = function ( $name, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) { $pass++; } else { $fail++; echo 'FAIL ', $name, '' !== $detail ? ' :: ' . $detail : '', "\n"; }
};
$keep = array();
foreach ( array( 'ftt_calc_options', 'ftt_storage', 'ftt_clip_settings' ) as $o ) {
	$keep[ $o ] = get_option( $o, null );
}
global $shortcode_tags, $menu;
require_once FTT_DIR . 'includes/admin/class-ftt-setup.php';
$dir = FTT_DIR;
// Remove per-render counters/ids so two renders of the same tool can be compared.
$norm = function ( $h ) {
	return preg_replace( array( '/\b(ftt-[a-z]+-|fc)[0-9a-z]{1,12}\b/', '/\b(id|for|name|aria-describedby|aria-controls|aria-labelledby|list)="[^"]*"/' ), array( 'X', '$1="X"' ), (string) $h );
};

/* ---------------- (1) Rename: what people read says FTT ---------------- */
if ( ! function_exists( 'get_plugin_data' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
$pd = get_plugin_data( FTT_FILE, false, false );
$ok( 'plugin header Name is FTT', 'FTT' === $pd['Name'], $pd['Name'] );
$ok( 'version 1.4.x', version_compare( $pd['Version'], '1.4.0', '>=' ) && $pd['Version'] === FTT_VERSION );
$ok( 'text domain unchanged', 'film-transfer-toolkit' === $pd['TextDomain'] );
$ok( 'folder/file (plugin slug) unchanged', 'film-transfer-toolkit/film-transfer-toolkit.php' === plugin_basename( FTT_FILE ), plugin_basename( FTT_FILE ) );
$ok( 'plugin still active under the same slug', is_plugin_active( 'film-transfer-toolkit/film-transfer-toolkit.php' ) );
$cats = FTT_Blocks::category( array() );
$ok( 'block category slug unchanged, title FTT', 'film-transfer-toolkit' === $cats[0]['slug'] && 'FTT' === $cats[0]['title'], wp_json_encode( $cats[0] ) );
wp_set_current_user( 1 );
$menu = array(); $GLOBALS['submenu'] = array(); $GLOBALS['_wp_submenu_nopriv'] = array();
FTT_Admin::menu();
$top = null;
foreach ( (array) $menu as $m ) { if ( 'ftt' === $m[2] ) { $top = $m; } }
$ok( 'admin menu: top item is "FTT" with slug ftt', $top && 'FTT' === $top[0] && 'FTT' === $top[3], wp_json_encode( $top ) );
$subs = wp_list_pluck( $GLOBALS['submenu']['ftt'] ?? array(), 2 );
foreach ( array( 'ftt', 'ftt-settings', 'ftt-help', 'ftt-prices' ) as $slug ) {
	// 1.5.0: an old slug may instead redirect to its Settings tab (FTT_Admin_UX::REDIRECTS).
	$ok( "admin submenu slug $slug unchanged", in_array( $slug, $subs, true ) || '' !== FTT_Admin_UX::redirect_target( $slug ), implode( ',', $subs ) );
}
ob_start(); FTT_Admin::render_dashboard(); $dash = ob_get_clean();
ob_start(); $_GET['tool'] = ''; FTT_Admin::render_help(); $help = ob_get_clean();
$ok( 'dashboard heading says FTT', (bool) preg_match( '/<h1[^>]*>\s*<span class="ftt-header__brand">FTT<\/span>/', $dash ), substr( strip_tags( $dash ), 0, 80 ) ); // 1.5.0 header
$ok( 'help heading says FTT help', (bool) preg_match( '/ftt-header__brand">FTT<\/span>.*Help/s', $help ) );
$ok( 'no "Film Transfer Toolkit" in dashboard/help output', false === stripos( $dash . $help, 'Film Transfer Toolkit' ) );
// No file the plugin ships (code, readme, docs, help images) still shows the old name - except the changelog line that records the rename.
$old = array();
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$rel = substr( $f->getPathname(), strlen( $dir ) );
	if ( preg_match( '#^(tests|e2e)/#', $rel ) || ! preg_match( '/\.(php|js|css|txt|md|json|svg|html)$/', $rel ) ) { continue; }
	foreach ( file( $f->getPathname() ) as $n => $line ) {
		if ( preg_match( '/film\s+transfer\s+toolkit/i', $line ) && ! ( 'readme.txt' === $rel && false !== strpos( $line, 'formerly "Film Transfer Toolkit"' ) ) ) {
			$old[] = $rel . ':' . ( $n + 1 );
		}
	}
}
$ok( 'old name gone from every shipped file (changelog note excepted)', ! $old, implode( ' ', $old ) );
$ok( 'readme title is FTT', 0 === strpos( file_get_contents( $dir . 'readme.txt' ), '=== FTT ===' ) );

/* ---------------- (1) ...and nothing anything depends on changed (snapshot of 1.3.0) ---------------- */
require_once $dir . 'tools/surface.php';
$was = json_decode( file_get_contents( $dir . 'tests/fixtures/surface-1.3.0.json' ), true );
$now = ftt_surface( $dir );
$ok( '1.3.0 snapshot loaded', is_array( $was ) && count( $was ) >= 15 );
foreach ( (array) $was as $kind => $list ) {
	$miss = array_diff( $list, $now[ $kind ] ?? array() );
	// 1.5.0 admin redesign, on purpose: old page slugs redirect; the old dashboard card classes and the old
	// "Calculator look" page callback are gone from the admin (the function itself still exists).
	$allowed150 = array( 'page_slug' => array_keys( FTT_Admin_UX::REDIRECTS ), 'css_class' => array( 'ftt-tool__links', 'ftt-tool__sc' ), 'ftt_string' => array( 'ftt_calc_render_settings_page' ) );
	$miss = array_diff( $miss, $allowed150[ $kind ] ?? array() );
	$ok( "every 1.3.0 $kind still present (" . count( $list ) . ')', ! $miss, implode( ', ', $miss ) );
}
foreach ( array( 'ftt_calculator', 'film_calculator', 'ftt_reel_estimator', 'ftt_price_table', 'ftt_estimate', 'ftt_storage_calculator', 'ftt_clip_selector', 'ftt_rfq_providers', 'ftt_rfq_form', 'ftt_rfq_reply', 'ftt_rfq_compare' ) as $tag ) {
	$ok( "shortcode [$tag] registered", isset( $shortcode_tags[ $tag ] ) );
}
$reg = WP_Block_Type_Registry::get_instance();
foreach ( FTT_Blocks::active_embeds() as $tag => $e ) {
	$ok( "block {$e['block']} registered in category film-transfer-toolkit", $reg->is_registered( $e['block'] ) && 'film-transfer-toolkit' === $reg->get_registered( $e['block'] )->category );
}
// Content saved with 1.3.0 (shortcodes and blocks with attributes) renders the same tools.
$saved = array(
	'[ftt_calculator format="s8" fps="18"]'                                  => array( 'data-ftt-calc', 'value="s8" data-fpf="72"  selected' ),
	'<!-- wp:ftt/calculator {"format":"16","meters":"no"} /-->'               => array( 'data-ftt-calc', 'data-hide="meters"' ),
	'[ftt_storage_calculator format="8" preset="h264"]'                       => array( 'data-ftt-storage', 'data-format="8"', 'data-preset="h264"' ),
	'<!-- wp:ftt/storage-calculator {"headroom":"30"} /-->'                   => array( 'data-ftt-storage', 'data-headroom="30"' ),
	'[ftt_reel_estimator format="16"]'                                        => array( 'data-ftt-reel' ),
	'<!-- wp:ftt/price-table {"fees":"no"} /-->'                              => array( 'ftt-prices' ),
	'[ftt_estimate title="My estimate"]'                                      => array( 'My estimate' ),
);
foreach ( $saved as $content => $needles ) {
	$h = do_shortcode( do_blocks( $content ) );
	$miss = array_filter( $needles, function ( $n ) use ( $h ) { return false === strpos( $h, $n ); } );
	$ok( 'saved 1.3.0 content still renders: ' . $content, ! $miss && false === strpos( $h, '[ftt_' ), implode( ' | ', $miss ) );
}
// Settings saved under the 1.3.0 option keys are read as before.
update_option( 'ftt_calc_options', array_merge( ftt_calc_default_options(), array( 'theme' => 'light', 'show_frames' => 0 ) ) );
$h = ftt_calc_shortcode( array() );
$ok( 'ftt_calc_options (1.3.0 key) still applied', false !== strpos( $h, 'filmcalc--light' ) && false !== strpos( $h, 'data-hide="frames"' ) );
update_option( 'ftt_storage', array( 'default_format' => 's8', 'deliverables' => array( array( 'kind' => 'h264', 'mbps' => '10', 'fps' => 'film', 'audio_kbps' => '2304' ) ) ) );
$h = do_shortcode( '[ftt_storage_calculator]' );
$ok( 'ftt_storage (1.3.0 key) still applied', false !== strpos( $h, 'data-format="s8"' ) && false !== strpos( $h, '&quot;mbps&quot;:&quot;10&quot;' ) );
foreach ( $keep as $o => $v ) { if ( null === $v ) { delete_option( $o ); } else { update_option( $o, $v ); } }

/* ---------------- (2) Every shortcode option is in its block sidebar, same default, label + help ---------------- */
$pairs = array();
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	// The calculator passes 'film_calculator' to shortcode_atts (kept for FilmTransfer.ai filters), so listen on aliases too.
	$cap = function ( $out, $p ) use ( &$pairs, $tag ) { $pairs[ $tag ] = $p; return $out; };
	$tags = array_merge( array( $tag ), $e['aliases'] ?? array() );
	foreach ( $tags as $t ) { add_filter( 'shortcode_atts_' . $t, $cap, 10, 2 ); }
	if ( isset( $shortcode_tags[ $tag ] ) ) { call_user_func( $shortcode_tags[ $tag ], array(), '', $tag ); }
	foreach ( $tags as $t ) { remove_filter( 'shortcode_atts_' . $t, $cap, 10 ); }
}
wp_scripts()->registered['ftt-blocks'] ?? null;
$inline = implode( '', (array) wp_scripts()->get_data( 'ftt-blocks', 'before' ) );
$client = json_decode( preg_replace( '/^.*?window\.FTT_BLOCKS = (.*);\s*$/s', '$1', $inline ), true );
$cl     = array();
foreach ( (array) ( $client['blocks'] ?? array() ) as $b ) { $cl[ $b['shortcode'] ] = $b; }
$ok( 'block editor data (FTT_BLOCKS) found for every active tool', count( $cl ) === count( FTT_Blocks::active_embeds() ), count( $cl ) . ' / ' . count( FTT_Blocks::active_embeds() ) );
foreach ( FTT_Blocks::active_embeds() as $tag => $e ) {
	$reg_names = wp_list_pluck( $e['atts'], 'name' );
	$sc_names  = array_keys( $pairs[ $tag ] ?? array() );
	sort( $reg_names ); sort( $sc_names );
	$ok( "[$tag]: registry lists exactly the shortcode's options", $reg_names === $sc_names, 'registry=' . implode( ',', $reg_names ) . ' shortcode=' . implode( ',', $sc_names ) );
	$bt    = $reg->get_registered( $e['block'] );
	$battr = $bt ? array_keys( $bt->attributes ) : array();
	$missing = array_diff( $sc_names, $battr );
	$ok( "{$e['block']}: block has every shortcode option", $bt && ! $missing, 'missing in block: ' . implode( ',', $missing ) );
	$cnames = wp_list_pluck( $cl[ $tag ]['atts'] ?? array(), 'name' );
	$ok( "{$e['block']}: sidebar shows every shortcode option", ! array_diff( $sc_names, $cnames ), 'missing in sidebar: ' . implode( ',', array_diff( $sc_names, $cnames ) ) );
	$cidx = array();
	foreach ( $cl[ $tag ]['atts'] ?? array() as $ca ) { $cidx[ $ca['name'] ] = $ca; }
	$base = $norm( call_user_func( $shortcode_tags[ $tag ], array(), '', $tag ) );
	foreach ( $e['atts'] as $a ) {
		$n  = $a['name'];
		$bd = $bt->attributes[ $n ]['default'] ?? null;
		$ok( "$tag $n: block default = registry default", (string) $a['default'] === (string) $bd, var_export( $bd, true ) );
		$ok( "$tag $n: sidebar default = registry default", isset( $cidx[ $n ] ) && (string) $a['default'] === $cidx[ $n ]['default'] );
		$sd = $pairs[ $tag ][ $n ] ?? null;
		if ( '' !== (string) $a['default'] ) {
			$ok( "$tag $n: shortcode default = block default ({$a['default']})", (string) $sd === (string) $a['default'], var_export( $sd, true ) );
		} else {
			// Empty default means "use the setting": leaving the option out and the block's empty value must render the same.
			$ok( "$tag $n: empty block value renders like the shortcode default", $base === $norm( call_user_func( $shortcode_tags[ $tag ], array( $n => '' ), '', $tag ) ) );
		}
		$ok( "$tag $n: sidebar label is plain English", isset( $cidx[ $n ] ) && '' !== $cidx[ $n ]['label'] && $cidx[ $n ]['label'] !== $n, $cidx[ $n ]['label'] ?? '' );
		$ok( "$tag $n: sidebar help text", isset( $cidx[ $n ] ) && strlen( $cidx[ $n ]['help'] ) > 15 && '' !== $cidx[ $n ]['defaultText'] && '' !== $cidx[ $n ]['values'] );
		if ( ! empty( $a['options'] ) ) {
			$ok( "$tag $n: sidebar offers every allowed value", isset( $cidx[ $n ] ) && array_keys( $a['options'] ) == wp_list_pluck( $cidx[ $n ]['options'], 'value' ) );
		}
		// Block render with a value = shortcode with the same value.
		$v = ! empty( $a['options'] ) ? (string) array_keys( FTT_Registry::att_values( $a ) )[ count( FTT_Registry::att_values( $a ) ) - 1 ] : ( $a['example'] ?? ( 'number' === $a['type'] ? '7' : 'Test value' ) );
		$ok( "$tag $n=\"$v\": block render = shortcode render", $norm( FTT_Blocks::render( $tag, array( $n => $v ) ) ) === $norm( call_user_func( $shortcode_tags[ $tag ], array( $n => $v ), '', $tag ) ) );
	}
}

/* ---------------- (2) Help: generated option lists ---------------- */
ob_start(); $_GET['tool'] = 'options'; FTT_Admin::render_help(); $all = ob_get_clean(); $_GET['tool'] = '';
$ok( 'Help > All options tab exists and is active', false !== strpos( $all, 'All options for every tool' ) && (bool) preg_match( '/nav-tab nav-tab-active"[^>]*>All options</', $all ) );
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	$ok( "All options: section for [$tag] with block {$e['block']}", false !== strpos( $all, 'id="ftt-opt-' . $tag . '"' ) && false !== strpos( $all, '<code>' . $e['block'] . '</code>' ) );
	$ok( "All options: copy-ready every-option example for [$tag]", ! $e['atts'] || false !== strpos( $all, esc_html( FTT_Registry::full_example( $e ) ) ) );
	$ok( "All options: block markup example for [$tag]", false !== strpos( $all, esc_html( FTT_Registry::block_example( $e ) ) ) );
	foreach ( $e['atts'] as $a ) {
		$row = preg_match( '#<table class="widefat striped ftt-atts" data-ftt-atts="' . preg_quote( $tag, '#' ) . '">.*?</table>#s', $all, $m ) ? $m[0] : '';
		$ok( "All options: [$tag] {$a['name']} row has label, values and default", (bool) preg_match( '#<tr data-att="' . preg_quote( $a['name'], '#' ) . '">.*?' . preg_quote( esc_html( FTT_Registry::att_label( $a ) ), '#' ) . '.*?' . preg_quote( esc_html( FTT_Registry::default_text( $a ) ), '#' ) . '#s', $row ) );
	}
	foreach ( $e['examples'] as $code => $what ) {
		$ok( "All options: hand-written example $code", false !== strpos( $all, esc_html( $code ) ) );
	}
}
// The generated every-option example is safe to paste: it renders exactly like the bare shortcode.
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	if ( $e['atts'] && 'on' === ( FTT_Plugin::$status[ $e['module'] ] ?? '' ) ) {
		$ok( "[$tag] every-option example renders like [$tag]", $norm( do_shortcode( '[' . $tag . ']' ) ) === $norm( do_shortcode( FTT_Registry::full_example( $e ) ) ) );
	}
}
// Every example in the registry uses only real options and allowed values.
foreach ( FTT_Registry::embeds() as $tag => $e ) {
	$names = wp_list_pluck( $e['atts'], 'name' );
	foreach ( array_keys( $e['examples'] ) as $code ) {
		if ( ! preg_match( '/^\[([a-z_]+)(.*)\]$/', $code, $m ) ) { continue; }
		$parsed = shortcode_parse_atts( trim( $m[2] ) );
		$bad    = array();
		foreach ( (array) $parsed as $k => $v ) {
			$a = $e['atts'][ array_search( $k, $names, true ) ] ?? null;
			if ( ! in_array( $k, $names, true ) || ( ! empty( $a['options'] ) && ! isset( $a['options'][ $v ] ) ) ) { $bad[] = "$k=$v"; }
		}
		$ok( "example $code uses real options and values", in_array( $m[1], array_merge( array( $tag ), $e['aliases'] ?? array() ), true ) && ! $bad, implode( ',', $bad ) );
	}
}
// Per-tool help shows the same generated table.
ob_start(); $_GET['tool'] = 'calculator'; FTT_Admin::render_help(); $th = ob_get_clean(); $_GET['tool'] = '';
$ok( 'per-tool help uses the generated options table', false !== strpos( $th, 'data-ftt-atts="ftt_calculator"' ) && false !== strpos( $th, 'Block sidebar label' ) );
$ok( 'current setting shown for empty defaults', false !== strpos( $all, 'currently 16mm (16)' ) || false !== strpos( $all, 'currently ' ) );

/* ---------------- (2) FilmTransfer.ai badge (ft-core filter) still works ---------------- */
$calls = array();
global $wp_filter;
$other = $wp_filter['filmcalc_format_badge'] ?? null; // isolate: other (test) callbacks off while ft-core's runs alone
unset( $wp_filter['filmcalc_format_badge'] );
// ft-core.php's filter (verbatim signature: 2 args, priority 10), with its term lookups replaced by fixed URLs.
$ftcore = function ( $h, $cur ) use ( &$calls ) {
	$calls[] = func_get_args();
	$u = array( '8' => 'https://example.test/8mm.png', 's8' => 'https://example.test/super-8.png', '16' => 'https://example.test/16mm.png', '35-4' => '', '35-3' => '', '35-2' => '' );
	$c = $u[ $cur ] ?? '';
	return $h . '<span class="ft-calc-badge" data-ft-badges="' . esc_attr( wp_json_encode( $u ) ) . '"><img class="ft-fmt-badge" alt="" width="24" height="24"' . ( $c ? ' src="' . esc_url( $c ) . '"' : ' hidden' ) . '><span class="ft-fmt-badge ft-fmt-fallback" aria-hidden="true"' . ( $c ? ' hidden' : '' ) . '></span></span>';
};
add_filter( 'filmcalc_format_badge', $ftcore, 10, 2 );
$h = ftt_calc_shortcode( array( 'format' => 's8' ) );
$ok( 'filmcalc_format_badge called once per calculator with 2 args', 1 === count( $calls ) && 2 === count( $calls[0] ) );
$ok( 'badge filter gets "" and the sanitized format', isset( $calls[0] ) && '' === $calls[0][0] && 's8' === $calls[0][1], wp_json_encode( $calls ) );
$ok( 'badge HTML lands inside the Film format label', (bool) preg_match( '#<label class="filmcalc__field filmcalc__f-format"><span>[^<]*<span class="ft-calc-badge"[^>]*><img class="ft-fmt-badge"[^>]*src="https://example.test/super-8.png"#', $h ) );
$ok( 'badge next to select[data-fc="format"] inside [data-ftt-calc] (what the compat script looks for)', (bool) preg_match( '#data-ftt-calc.*ft-calc-badge.*<select data-fc="format">#s', $h ) );
$calls = array();
ftt_calc_shortcode( array( 'format' => '<b>bad' ) );
$ok( 'badge filter never receives raw input', isset( $calls[0][1] ) && in_array( $calls[0][1], array( '8', 's8', '16', '35-4', '35-3', '35-2' ), true ), wp_json_encode( $calls ) );
$ok( 'calculator script handle ftt-calculator registered (compat inline script target)', wp_script_is( 'ftt-calculator', 'registered' ) && wp_script_is( 'ftt-calculator', 'enqueued' ) );
$ok( 'badge line present in toolkit calculator source', false !== strpos( file_get_contents( $dir . 'modules/calculator/shortcode.php' ), "<?php echo apply_filters( 'filmcalc_format_badge', '', \$atts['format'] ); ?>" ) );
if ( defined( 'FILMCALC_VERSION' ) && isset( $shortcode_tags['film_calculator'] ) ) {
	$calls = array();
	$h = do_shortcode( '[film_calculator format="16"]' );
	$ok( 'standalone [film_calculator] still passes the badge filter', 1 === count( $calls ) && '16' === $calls[0][1] && false !== strpos( $h, 'ft-calc-badge' ) );
}
remove_filter( 'filmcalc_format_badge', $ftcore, 10 );
$h = ftt_calc_shortcode( array() );
$ok( 'no badge markup without the filter (empty by default)', false === strpos( $h, 'ft-calc-badge' ) );
if ( $other ) { $wp_filter['filmcalc_format_badge'] = $other; }

foreach ( $keep as $o => $v ) { if ( null === $v ) { delete_option( $o ); } else { update_option( $o, $v ); } }
echo "v140-tests.php: $pass passed, $fail failed\n";
