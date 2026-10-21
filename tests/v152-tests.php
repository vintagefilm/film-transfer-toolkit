<?php
/**
 * 1.5.2: per-custom-unit add-ons, descriptions, editable services (4K output / 1080p output), safe upgrade from 1.5.1.
 * Run: wp eval-file tests/v152-tests.php   (restores ftt_price_table and ftt_price_migrations)
 */
$P = 0; $F = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$P, &$F ) { if ( $c ) { $P++; } else { $F++; echo "FAIL $n" . ( '' !== $d ? ' :: ' . ( is_string( $d ) ? $d : wp_json_encode( $d ) ) : '' ) . "\n"; } };
$keep_t = get_option( FTT_Price_Table::OPTION, null ); $keep_m = get_option( FTT_Price_Table::MIGRATIONS, null );
$fx = dirname( __FILE__ ) . '/fixtures/';
$old = json_decode( file_get_contents( $fx . 'price-table-151-example.json' ), true );
$oldd = json_decode( file_get_contents( $fx . 'price-table-151-draft.json' ), true );
$reqs = array(
	array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 2, 'size' => '7', 'addons' => array( 'sound' ) ) ), 'order_addons' => array( 'usb' ) ),
	array( 'lines' => array( array( 'format' => 's8', 'service' => '4k', 'feet' => 1234.4 ), array( 'format' => '8', 'service' => 'hd', 'reels' => 3 ) ), 'order_addons' => array() ),
	array( 'lines' => array( array( 'format' => '35', 'service' => '4k', 'reels' => 7, 'size' => '10' ) ), 'order_addons' => array( 'usb', 'dvd', 'ownhd' ) ),
);

/* ---- schema / sanitize */
$s1 = FTT_Price_Table::sanitize( $old );
$ok( '1.5.1 example table sanitizes with no errors', ! $s1['errors'], $s1['errors'] );
$ok( '1.5.1 table round-trips byte-identical (no new keys added)', wp_json_encode( $s1['table'] ) === wp_json_encode( FTT_Price_Table::sanitize( $s1['table'] )['table'] ) );
$ok( 'no description/unit_label keys invented on old rows', false === strpos( wp_json_encode( $s1['table'] ), 'description' ) && false === strpos( wp_json_encode( $s1['table'] ), 'unit_label' ) );
$t = $s1['table'];
$t['addons'][] = array( 'id' => 'usbx', 'label' => 'USB drive', 'unit' => 'custom', 'unit_label' => 'USB drive', 'price' => 15000, 'formats' => array(), 'description' => 'A 32 GB drive <script>alert(1)</script>' );
$t['addons'][] = array( 'id' => 'noname', 'label' => 'No unit', 'unit' => 'custom', 'price' => 1000, 'formats' => array() );
$t['services'][0]['description'] = str_repeat( 'x', 400 );
$s2 = FTT_Price_Table::sanitize( $t );
$ok( 'custom add-on without a unit name is refused with a plain message', 1 === count( $s2['errors'] ) && false !== strpos( $s2['errors'][0], 'type the unit name' ), $s2['errors'] );
$u = wp_list_filter( $s2['table']['addons'], array( 'id' => 'usbx' ) ); $u = reset( $u );
$ok( 'custom add-on keeps unit custom + unit_label', $u && 'custom' === $u['unit'] && 'USB drive' === $u['unit_label'], $u );
$ok( 'description stored as plain text (tags stripped)', $u && false === strpos( $u['description'], '<' ) && 0 === strpos( $u['description'], 'A 32 GB drive' ), $u['description'] ?? '' );
$ok( 'service description capped at 300 chars', 300 === mb_strlen( $s2['table']['services'][0]['description'] ), mb_strlen( $s2['table']['services'][0]['description'] ) );
$b = $t; $b['addons'][2]['unit'] = 'bogus'; unset( $b['addons'][ count( $b['addons'] ) - 1 ] );
$ok( 'unknown unit still refused', (bool) FTT_Price_Table::sanitize( $b )['errors'] );

/* ---- custom unit quantity math + display */
$tb = $s2['table'];
$q  = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'usbx', 'qty' => 3 ) ) ) );
$oa = $q['totals']['order_addons'][0] ?? array();
$ok( '3 x USB drive @ $15 = $45.00', $q['ok'] && 4500 === $oa['cents'] && 3 === $oa['qty'] && 15000 === $oa['unit_mills'], $oa );
$ok( 'quote text reads "USB drive: 3 × USB drive @ $15.00"', 'USB drive: 3 × USB drive @ $15.00' === FTT_Pricing::order_addon_text( $oa, 'USD' ), FTT_Pricing::order_addon_text( $oa, 'USD' ) );
$ok( 'plain order add-on text is just the label', 'X' === FTT_Pricing::order_addon_text( array( 'label' => 'X', 'cents' => 5 ) ) );
$tbm = $tb; $tbm['addons'][] = array( 'id' => 'hr', 'label' => 'Editing', 'unit' => 'custom', 'unit_label' => 'hour', 'price' => 12345, 'formats' => array() );
$q = FTT_Pricing::quote( $tbm, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'hr', 'qty' => 3 ) ) ) );
$ok( 'mills x qty rounds once (3 x $12.345 = $37.04)', 3704 === ( $q['totals']['order_addons'][0]['cents'] ?? null ), $q['totals']['order_addons'] );
$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( 'usbx' ) ) );
$ok( 'bare id = quantity 1', 1500 === ( $q['totals']['order_addons'][0]['cents'] ?? null ) );
foreach ( array( 0, -1, 1000, 1.5, '3.0', 'x', true, '' ) as $bad ) {
	$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'usbx', 'qty' => $bad ) ) ) );
	$ok( 'qty ' . wp_json_encode( $bad ) . ' -> bad_quantity', ! $q['ok'] && 'bad_quantity' === ( $q['errors'][0]['code'] ?? '' ), $q['errors'] );
}
foreach ( array( 1, 999, '7', 2.0 ) as $good ) {
	$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'usbx', 'qty' => $good ) ) ) );
	$ok( 'qty ' . wp_json_encode( $good ) . ' accepted', $q['ok'] && (int) $good * 1500 === $q['totals']['order_addons'][0]['cents'], $q['errors'] );
}
$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7', 'addons' => array( 'usbx' ) ) ), 'order_addons' => array() ) );
$ok( 'custom add-on on a line is refused (it is order-level)', ! $q['ok'] && 'order_addon_on_line' === ( $q['errors'][0]['code'] ?? '' ) );
$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'usbx', 'qty' => 2 ), array( 'id' => 'usbx', 'qty' => 5 ) ) ) );
$ok( 'repeated custom add-on counts once (first wins)', 1 === count( $q['totals']['order_addons'] ) && 3000 === $q['totals']['order_addons'][0]['cents'] );
$q = FTT_Pricing::quote( $tb, array( 'lines' => array( array( 'format' => '16', 'service' => 'hd', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array( array( 'id' => 'usbx', 'qty' => 2 ), 'usb' ) ) );
$ok( 'custom and plain order add-ons together', $q['ok'] && 2 === count( $q['totals']['order_addons'] ) );

/* ---- rendering + escaping */
update_option( FTT_Price_Table::OPTION, $tb, false );
$tbx = $tb; $tbx['addons'][] = array( 'id' => 'evil', 'label' => 'Evil', 'unit' => 'custom', 'unit_label' => '"><img src=x onerror=alert(1)>', 'price' => 1000, 'formats' => array(), 'description' => '" onmouseover="x' );
update_option( FTT_Price_Table::OPTION, $tbx, false ); // stored directly: proves output escaping, not just input sanitizing
$h = do_shortcode( '[ftt_price_table]' );
$ok( 'price table: "per USB drive" for the custom unit', false !== strpos( $h, '$15.00 per USB drive' ), substr( $h, 0, 300 ) );
$ok( 'price table: add-on description as helper text', false !== strpos( $h, '<span class="ftt-desc">A 32 GB drive' ) );
$ok( 'price table: hostile unit/description escaped', false === strpos( $h, '<img src=x' ) && false === strpos( $h, '" onmouseover' ) && false !== strpos( $h, '&lt;img' ) );
wp_set_current_user( 1 ); $_GET = array(); ob_start(); FTT_Price_Table::render_admin(); $a = ob_get_clean();
$ok( 'admin: "per custom unit" choice selected for the custom row', (bool) preg_match( '/<option value="custom" selected/', $a ) );
$ok( 'admin: unit name + description inputs', false !== strpos( $a, 'name="addons[' ) && false !== strpos( $a, '[unit_label]" value="USB drive"' ) && false !== strpos( $a, '[description]" value="A 32 GB drive' ) );
$ok( 'admin: hostile values escaped in inputs', false === strpos( $a, '<img src=x' ) && false !== strpos( $a, '&quot;&gt;&lt;img' ) );
$ok( 'admin: Services table with readonly ids, reorder and remove', false !== strpos( $a, 'data-ftt-rows="services"' ) && false !== strpos( $a, 'name="services[0][id]" value="hd" readonly' ) && false !== strpos( $a, 'data-ftt-up' ) && false !== strpos( $a, 'data-ftt-add="services"' ) );
$ok( 'admin: services no longer in the Advanced JSON box', false === strpos( $a, 'name="json_services"' ) );
$ok( 'estimate builder gets descriptions + unit names in its table', false !== strpos( wp_json_encode( FTT_Price_Table::get() ), '"unit_label":"USB drive"' ) );
$js = file_get_contents( FTT_DIR . 'modules/estimate/estimate.js' );
$ok( 'estimate.js renders owner text with textContent only (no innerHTML)', false === strpos( $js, 'innerHTML' ) && false !== strpos( $js, "function desc(text) { return el('span', { 'class': 'ftt-desc' }, text); }" ) );

/* ---- fresh site defaults */
delete_option( FTT_Price_Table::OPTION ); delete_option( FTT_Price_Table::MIGRATIONS );
$f = FTT_Price_Table::get();
$ok( 'fresh: services are HD 1080p scan, 4K scan, 4K output, 1080p output', 'HD 1080p scan|4K scan|4K output|1080p output' === implode( '|', wp_list_pluck( $f['services'], 'label' ) ), wp_list_pluck( $f['services'], 'label' ) );
$ok( 'fresh: 4K output and 1080p output have rates for every format', 8 === count( array_filter( $f['rates'], function ( $r ) { return in_array( $r['service'], array( '4k_out', '1080_out' ), true ); } ) ) );
$q = FTT_Pricing::quote( $f, array( 'lines' => array( array( 'format' => '16', 'service' => '4k_out', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array() ) );
$ok( 'fresh: 4K output quotes', $q['ok'] );
$ok( 'fresh: migration does nothing without a stored table', false === FTT_Price_Table::maybe_migrate() && null === get_option( FTT_Price_Table::OPTION, null ) );
$fd = FTT_Price_Table::preset( 'draft' );
$ok( 'draft preset has the 4 services too', 4 === count( $fd['services'] ) );
foreach ( array( 'example' => $old, 'draft' => $oldd ) as $k => $o ) {
	$po = FTT_Price_Table::sanitize( $o )['table']; $pn = FTT_Price_Table::preset( $k );
	$same = true; foreach ( $reqs as $r ) { if ( wp_json_encode( FTT_Pricing::quote( $po, $r ) ) !== wp_json_encode( FTT_Pricing::quote( $pn, $r ) ) ) { $same = false; } }
	$ok( "$k preset: existing services quote exactly as in 1.5.1", $same );
	$ok( "$k preset: existing rates unchanged", wp_json_encode( $po['rates'] ) === wp_json_encode( array_slice( $pn['rates'], 0, count( $po['rates'] ) ) ) );
}

/* ---- upgrade from 1.5.1 */
foreach ( array( 'example' => $old, 'draft' => $oldd ) as $k => $o ) {
	$st = FTT_Price_Table::sanitize( $o )['table']; $st['status'] = 'final'; $st['fees']['setup'] = 12340; // an owner-edited table
	update_option( FTT_Price_Table::OPTION, $st, false ); delete_option( FTT_Price_Table::MIGRATIONS );
	$before = array(); foreach ( $reqs as $r ) { $before[] = wp_json_encode( FTT_Pricing::quote( $st, $r ) ); }
	$ok( "upgrade $k: migration appends", true === FTT_Price_Table::maybe_migrate() );
	$m = get_option( FTT_Price_Table::OPTION );
	$ok( "upgrade $k: services = old ones + 4K output + 1080p output at the end", wp_json_encode( array_merge( $st['services'], FTT_Price_Table::NEW_SERVICES_152 ) ) === wp_json_encode( $m['services'] ), wp_list_pluck( $m['services'], 'id' ) );
	$x = $m; $y = $st; unset( $x['services'], $y['services'] );
	$ok( "upgrade $k: everything else (rates, add-ons, fees, tiers, status) byte-identical", wp_json_encode( $x ) === wp_json_encode( $y ) );
	$after = array(); foreach ( $reqs as $r ) { $after[] = wp_json_encode( FTT_Pricing::quote( $m, $r ) ); }
	$ok( "upgrade $k: every sample quote unchanged", $before === $after );
	$ok( "upgrade $k: new services are not offered until rated (no invented prices)", ! FTT_Pricing::quote( $m, array( 'lines' => array( array( 'format' => '16', 'service' => '4k_out', 'reels' => 1, 'size' => '7' ) ), 'order_addons' => array() ) )['ok'] );
	$ok( "upgrade $k: migrated table is valid", ! FTT_Price_Table::sanitize( $m )['errors'] );
	$ok( "upgrade $k: second run is a no-op", false === FTT_Price_Table::maybe_migrate() && wp_json_encode( $m ) === wp_json_encode( get_option( FTT_Price_Table::OPTION ) ) );
}
$m['services'] = array_values( array_filter( $m['services'], function ( $s ) { return '4k_out' !== $s['id']; } ) ); FTT_Price_Table::save( $m );
FTT_Price_Table::maybe_migrate();
$ok( 'owner removed 4K output: it stays removed', ! in_array( '4k_out', wp_list_pluck( get_option( FTT_Price_Table::OPTION )['services'], 'id' ), true ) );
$st = FTT_Price_Table::sanitize( $old )['table']; $st['services'][] = array( 'id' => '4k_out', 'label' => 'My own 4K out' );
update_option( FTT_Price_Table::OPTION, $st, false ); delete_option( FTT_Price_Table::MIGRATIONS ); FTT_Price_Table::maybe_migrate();
$sv = get_option( FTT_Price_Table::OPTION )['services'];
$ok( 'an existing id is never duplicated or relabelled', 1 === count( wp_list_filter( $sv, array( 'id' => '4k_out' ) ) ) && 'My own 4K out' === current( wp_list_filter( $sv, array( 'id' => '4k_out' ) ) )['label'] );

/* ---- JSON export/import round-trip carries the new fields */
$ex = wp_json_encode( $tb ); $tbi = json_decode( $ex, true );
$ok( 'export -> import keeps unit_label + descriptions', ! FTT_Price_Table::save( $tbi ) && wp_json_encode( get_option( FTT_Price_Table::OPTION ) ) === wp_json_encode( FTT_Price_Table::sanitize( $tb )['table'] ) );
$reg = wp_json_encode( FTT_Registry::tools() );
$ok( 'help covers custom unit, descriptions and editing services', false !== strpos( $reg, 'per custom unit' ) && false !== strpos( $reg, 'rename, reorder or remove a service' ) && false !== strpos( $reg, '4K output' ) );

/* restore */
if ( null === $keep_t ) { delete_option( FTT_Price_Table::OPTION ); } else { update_option( FTT_Price_Table::OPTION, $keep_t, false ); }
if ( null === $keep_m ) { delete_option( FTT_Price_Table::MIGRATIONS ); } else { update_option( FTT_Price_Table::MIGRATIONS, $keep_m, false ); }
echo "v152 php: $P passed, $F failed\n";
