<?php
/**
 * [ftt_storage_calculator] - data rate / storage calculator for film transfer deliverables.
 * Optional attributes: title="", headroom="20" (percent, 0-100), preset="prores-hq-hd|h264|youtube-4k|dpx-2k|tiff-4k".
 * All maths runs in FTTCore.datarate (core/ftt-core.js); the PHP mirror is FTT_Datarate (WP-CLI: wp ftt storage).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FTT_DIR . 'includes/core/class-ftt-datarate.php';

add_action( 'init', function () {
	add_shortcode( 'ftt_storage_calculator', 'ftt_storage_shortcode' );
	add_shortcode( 'ftt_storage', 'ftt_storage_shortcode' );
} );

add_action( 'init', function () {
	wp_register_script( 'ftt-storage', FTT_URL . 'modules/storage/storage.js', array( 'ftt-core' ), FTT_VERSION, true );
}, 11 );

function ftt_storage_presets() {
	return array( 'prores-hq-hd', 'prores-422-uhd', 'h264', 'youtube-4k', 'dpx-2k', 'tiff-4k' );
}

function ftt_storage_shortcode( $atts ) {
	$atts     = shortcode_atts( array( 'title' => '', 'headroom' => '20', 'preset' => 'prores-hq-hd' ), $atts, 'ftt_storage_calculator' );
	$title    = '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : __( 'How much storage will my transfer need?', 'film-transfer-toolkit' );
	$headroom = is_numeric( $atts['headroom'] ) && $atts['headroom'] >= 0 && $atts['headroom'] <= 100 ? (string) (int) $atts['headroom'] : '20';
	$preset   = in_array( $atts['preset'], ftt_storage_presets(), true ) ? $atts['preset'] : 'prores-hq-hd';
	wp_enqueue_style( 'ftt-front' );
	wp_enqueue_script( 'ftt-storage' );
	ob_start();
	?>
	<div class="ftt-box ftt-store" data-ftt-storage data-headroom="<?php echo esc_attr( $headroom ); ?>" data-preset="<?php echo esc_attr( $preset ); ?>">
		<h3 class="ftt-box__title"><?php echo esc_html( $title ); ?></h3>
		<p class="ftt-box__sub"><?php esc_html_e( 'List your reels, choose one or more file formats, and see the size per reel, the total, and a drive size with room to spare.', 'film-transfer-toolkit' ); ?></p>
		<h4 class="ftt-store__h"><?php esc_html_e( '1. Your film', 'film-transfer-toolkit' ); ?></h4>
		<div data-s="lines"></div>
		<p class="ftt-store__actions"><button type="button" class="ftt-btn ftt-btn--ghost" data-s="add-line"><?php esc_html_e( '+ Add reels', 'film-transfer-toolkit' ); ?></button> <button type="button" class="ftt-btn ftt-btn--link" data-s="from-estimate" hidden><?php esc_html_e( 'Use the reels from the estimate on this page', 'film-transfer-toolkit' ); ?></button></p>
		<h4 class="ftt-store__h"><?php esc_html_e( '2. Files you want', 'film-transfer-toolkit' ); ?></h4>
		<div data-s="dels"></div>
		<p class="ftt-store__actions"><button type="button" class="ftt-btn ftt-btn--ghost" data-s="add-del"><?php esc_html_e( '+ Add another deliverable', 'film-transfer-toolkit' ); ?></button></p>
		<div class="ftt-grid">
			<label class="ftt-field"><span><?php esc_html_e( 'Drive headroom (%)', 'film-transfer-toolkit' ); ?></span><input type="number" min="0" max="100" step="1" inputmode="numeric" value="<?php echo esc_attr( $headroom ); ?>" data-s="headroom"></label>
		</div>
		<h4 class="ftt-store__h"><?php esc_html_e( '3. Storage', 'film-transfer-toolkit' ); ?></h4>
		<div class="ftt-store__out" data-s="out" aria-live="polite"></div>
		<details class="ftt-store__src"><summary><?php esc_html_e( 'Sources and assumptions', 'film-transfer-toolkit' ); ?></summary><div data-s="sources"></div></details>
		<noscript><p class="ftt-msg ftt-msg--bad"><?php esc_html_e( 'This calculator needs JavaScript.', 'film-transfer-toolkit' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Storage estimate with the PHP mirror. Example:
	 *   wp ftt-storage '{"lines":[{"format":"s8","fps":18,"reels":3,"size":"7"}],"deliverables":[{"kind":"prores","profile":"hq","size":"hd"}]}'
	 * Add --format=json for the full result.
	 */
	WP_CLI::add_command( 'ftt-storage', function ( $args, $assoc ) {
		$req = json_decode( (string) ( $args[0] ?? '' ), true );
		if ( ! is_array( $req ) ) {
			WP_CLI::error( 'Pass the request as one JSON argument.' );
		}
		$r = FTT_Datarate::storage( $req );
		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			WP_CLI::line( wp_json_encode( $r ) );
			return;
		}
		foreach ( $r['errors'] as $e ) {
			WP_CLI::warning( $e['code'] . ': ' . $e['message'] . ' (line ' . $e['line'] . ', deliverable ' . $e['deliverable'] . ')' );
		}
		foreach ( $r['deliverables'] as $d ) {
			WP_CLI::line( $d['label'] . ': ' . FTT_Datarate::fmt_bytes( $d['bytes'] ) . ' / ' . FTT_Datarate::fmt_bytes( $d['bytes'], true ) );
		}
		WP_CLI::line( 'Total: ' . FTT_Datarate::fmt_bytes( $r['totals']['size']['bytes'] ) . ' / ' . FTT_Datarate::fmt_bytes( $r['totals']['size']['bytes'], true ) . '; suggested drive (' . $r['totals']['headroom_pct'] . '% headroom): ' . FTT_Datarate::fmt_drive( $r['totals']['drive'] ) );
	} );
}
