<?php
/**
 * [ftt_price_table service="" addons="yes" fees="yes"] - shows the admin price table.
 * EXAMPLE and DRAFT tables always carry their warning banner.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	add_shortcode( 'ftt_price_table', 'ftt_price_table_shortcode' );
} );

function ftt_price_table_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'service' => '', 'addons' => 'yes', 'fees' => 'yes' ), $atts, 'ftt_price_table' );
	$t    = FTT_Price_Table::get();
	$cur  = $t['currency'];
	$svcs = $t['services'];
	if ( '' !== $atts['service'] ) {
		$want = sanitize_key( $atts['service'] );
		$svcs = array_values( array_filter( $svcs, function ( $s ) use ( $want ) { return $s['id'] === $want; } ) );
	}
	$yes = function ( $v ) { return ! in_array( strtolower( (string) $v ), array( 'no', '0', 'false', 'off', 'hide' ), true ); };
	wp_enqueue_style( 'ftt-front' );
	ob_start();
	echo '<div class="ftt-box ftt-prices">';
	echo FTT_Price_Table::banner( $t ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in banner().
	foreach ( $svcs as $s ) {
		$rows = '';
		foreach ( $t['formats'] as $f ) {
			$foot = null;
			$reel = array();
			foreach ( $t['rates'] as $r ) {
				if ( $r['format'] !== $f['id'] || $r['service'] !== $s['id'] ) { continue; }
				if ( 'foot' === $r['unit'] ) { $foot = $r['price']; } else { $reel[ $r['size'] ] = $r['price']; }
			}
			if ( null === $foot && ! $reel ) { continue; }
			$reel_txt = array();
			foreach ( $t['reels'] as $rl ) {
				if ( $rl['format'] === $f['id'] && isset( $reel[ $rl['size'] ] ) ) {
					$reel_txt[] = $rl['label'] . ' (' . $rl['feet'] . ' ft): ' . FTT_Pricing::format_mills( $reel[ $rl['size'] ], $cur );
				}
			}
			$rows .= '<tr><th scope="row">' . esc_html( $f['label'] ) . '</th><td>' . ( null === $foot ? '&ndash;' : esc_html( FTT_Pricing::format_mills( $foot, $cur ) ) ) . '</td><td>' . ( $reel_txt ? esc_html( implode( '; ', $reel_txt ) ) : '&ndash;' ) . '</td></tr>';
		}
		if ( '' === $rows ) { continue; }
		echo '<table class="ftt-table"><caption>' . esc_html( $s['label'] ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Format', 'film-transfer-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Per foot', 'film-transfer-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Per full reel', 'film-transfer-toolkit' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput -- rows escaped above.
	}
	if ( $yes( $atts['addons'] ) && $t['addons'] ) {
		$units = array( 'foot' => __( 'per foot', 'film-transfer-toolkit' ), 'reel' => __( 'per reel', 'film-transfer-toolkit' ), 'order' => __( 'per order', 'film-transfer-toolkit' ) );
		$names = array_column( $t['formats'], 'label', 'id' );
		echo '<table class="ftt-table"><caption>' . esc_html__( 'Add-ons', 'film-transfer-toolkit' ) . '</caption><tbody>';
		foreach ( $t['addons'] as $a ) {
			$only = $a['formats'] ? ' (' . implode( ', ', array_map( function ( $id ) use ( $names ) { return $names[ $id ] ?? $id; }, $a['formats'] ) ) . ')' : '';
			echo '<tr><th scope="row">' . esc_html( $a['label'] . $only ) . '</th><td>' . esc_html( FTT_Pricing::format_mills( $a['price'], $cur ) . ' ' . $units[ $a['unit'] ] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( $yes( $atts['fees'] ) ) {
		$fe    = $t['fees'];
		$items = array();
		if ( $fe['setup'] ) { $items[] = sprintf( /* translators: %s: price */ __( 'Setup fee %s per order', 'film-transfer-toolkit' ), FTT_Pricing::format_mills( $fe['setup'], $cur ) ); }
		if ( $fe['min_order'] ) { $items[] = sprintf( /* translators: %s: price */ __( 'Order minimum %s', 'film-transfer-toolkit' ), FTT_Pricing::format_mills( $fe['min_order'], $cur ) ); }
		if ( $fe['min_feet_per_reel'] ) { $items[] = sprintf( /* translators: %d: feet */ __( 'Each reel is billed for at least %d ft', 'film-transfer-toolkit' ), $fe['min_feet_per_reel'] ); }
		foreach ( $t['tiers'] as $x ) { $items[] = sprintf( /* translators: 1: percent, 2: feet */ __( '%1$d%% off orders of %2$s ft or more', 'film-transfer-toolkit' ), $x['pct'], number_format_i18n( $x['min_feet'] ) ); }
		if ( $items ) {
			echo '<ul class="ftt-fees">';
			foreach ( $items as $i ) { echo '<li>' . esc_html( $i ) . '</li>'; }
			echo '</ul>';
		}
	}
	echo '</div>';
	return ob_get_clean();
}
