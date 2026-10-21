<?php
/**
 * [ftt_reel_estimator format="8|s8|16|35" fps="18"] - footage and running time from a reel size or a measured diameter.
 * All maths runs in FTTCore (core/ftt-core.js); this file only renders escaped markup.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	add_shortcode( 'ftt_reel_estimator', 'ftt_reel_shortcode' );
} );

add_action( 'init', function () {
	wp_register_script( 'ftt-reels', FTT_URL . 'modules/reel-estimator/reel-estimator.js', array( 'ftt-core' ), FTT_VERSION, true );
}, 11 );

function ftt_reel_formats() {
	return array(
		'8'  => array( 'label' => __( '8mm (Regular 8)', 'film-transfer-toolkit' ), 'fpf' => 80, 'fps' => '16' ),
		's8' => array( 'label' => __( 'Super 8', 'film-transfer-toolkit' ), 'fpf' => 72, 'fps' => '18' ),
		'16' => array( 'label' => __( '16mm', 'film-transfer-toolkit' ), 'fpf' => 40, 'fps' => '24' ),
		'35' => array( 'label' => __( '35mm (4-perf)', 'film-transfer-toolkit' ), 'fpf' => 16, 'fps' => '24' ),
	);
}

function ftt_reel_shortcode( $atts ) {
	$atts    = shortcode_atts( array( 'format' => 's8', 'fps' => '', 'title' => '' ), $atts, 'ftt_reel_estimator' );
	$formats = ftt_reel_formats();
	$format  = isset( $formats[ $atts['format'] ] ) ? $atts['format'] : 's8';
	$fps     = is_numeric( $atts['fps'] ) && $atts['fps'] > 0 && $atts['fps'] <= 120 ? (string) (float) $atts['fps'] : '';
	$title   = '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : __( 'How much film do I have?', 'film-transfer-toolkit' );
	static $n = 0;
	$n++;
	$uid = 'ftt-reel-' . $n;
	wp_enqueue_style( 'ftt-front' );
	wp_enqueue_script( 'ftt-reels' );
	ob_start();
	?>
	<div class="ftt-tool ftt-box ftt-reels" data-ftt-reels<?php echo '' !== $fps ? ' data-fps="' . esc_attr( $fps ) . '"' : ''; ?>>
		<h3 class="ftt-box__title"><?php echo esc_html( $title ); ?></h3>
		<p class="ftt-box__sub"><?php esc_html_e( 'Pick the reel size, or measure the film itself, to estimate footage and running time.', 'film-transfer-toolkit' ); ?></p>
		<div class="ftt-grid">
			<label class="ftt-field"><span><?php esc_html_e( 'Film format', 'film-transfer-toolkit' ); ?></span>
				<select data-r="format">
				<?php foreach ( $formats as $k => $f ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" data-fpf="<?php echo esc_attr( $f['fpf'] ); ?>" data-fps="<?php echo esc_attr( $f['fps'] ); ?>" <?php selected( $format, $k ); ?>><?php echo esc_html( $f['label'] ); ?></option>
				<?php endforeach; ?>
				</select></label>
			<fieldset class="ftt-field ftt-seg"><legend><?php esc_html_e( 'Method', 'film-transfer-toolkit' ); ?></legend>
				<label><input type="radio" name="<?php echo esc_attr( $uid ); ?>-m" value="size" data-r="method" checked> <?php esc_html_e( 'Reel size', 'film-transfer-toolkit' ); ?></label>
				<label><input type="radio" name="<?php echo esc_attr( $uid ); ?>-m" value="measure" data-r="method"> <?php esc_html_e( 'Measure the film', 'film-transfer-toolkit' ); ?></label>
			</fieldset>
		</div>
		<div class="ftt-grid" data-r="size-panel">
			<label class="ftt-field"><span><?php esc_html_e( 'Reel size', 'film-transfer-toolkit' ); ?></span><select data-r="size"></select></label>
			<label class="ftt-field"><span><?php esc_html_e( 'How many reels', 'film-transfer-toolkit' ); ?></span><input type="number" min="1" max="999" step="1" value="1" inputmode="numeric" data-r="count"></label>
		</div>
		<div class="ftt-grid" data-r="measure-panel" hidden>
			<label class="ftt-field"><span><?php esc_html_e( 'Diameter of the wound film', 'film-transfer-toolkit' ); ?></span><input type="number" min="0" step="any" inputmode="decimal" data-r="outer" placeholder="5.5"></label>
			<label class="ftt-field"><span><?php esc_html_e( 'Units', 'film-transfer-toolkit' ); ?></span><select data-r="unit"><option value="in"><?php esc_html_e( 'inches', 'film-transfer-toolkit' ); ?></option><option value="cm"><?php esc_html_e( 'centimeters', 'film-transfer-toolkit' ); ?></option></select></label>
			<label class="ftt-field"><span><?php esc_html_e( 'Hub diameter (optional)', 'film-transfer-toolkit' ); ?></span><input type="number" min="0" step="any" inputmode="decimal" data-r="hub"></label>
			<label class="ftt-field"><span><?php esc_html_e( 'Film base', 'film-transfer-toolkit' ); ?></span><select data-r="base"><option value="acetate"><?php esc_html_e( 'Acetate (most home movies)', 'film-transfer-toolkit' ); ?></option><option value="polyester"><?php esc_html_e( 'Polyester (thinner)', 'film-transfer-toolkit' ); ?></option></select></label>
		</div>
		<p class="ftt-result" data-r="out" aria-live="polite"></p>
		<p class="ftt-note" data-r="note"><?php esc_html_e( 'Measure across the wound film, not the reel flange. Diameter estimates are approximate (about ±10%) because film thickness varies by stock.', 'film-transfer-toolkit' ); ?></p>
		<noscript><p><?php esc_html_e( 'This estimator requires JavaScript.', 'film-transfer-toolkit' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}
