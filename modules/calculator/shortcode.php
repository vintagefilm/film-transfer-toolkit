<?php
/**
 * [ftt_calculator] shortcode (alias [film_calculator]). Ported from Film Calculator 1.1.1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ftt_calc_register_assets', 11 );
function ftt_calc_register_assets() {
	$base = plugins_url( 'modules/calculator/', FTT_FILE );
	wp_register_style( 'ftt-calculator', $base . 'calculator.css', array(), FTT_VERSION );
	wp_register_script( 'ftt-calculator', $base . 'calculator.js', array( 'ftt-core' ), FTT_VERSION, true );
}

/** Parses yes/no style attributes; returns $default for anything unrecognised. */
function ftt_calc_bool_att( $value, $default ) {
	$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
	if ( in_array( $value, array( '1', 'yes', 'true', 'on', 'show' ), true ) ) {
		return true;
	}
	if ( in_array( $value, array( '0', 'no', 'false', 'off', 'hide' ), true ) ) {
		return false;
	}
	return (bool) $default;
}

add_action( 'init', 'ftt_calc_register_shortcodes', 20 );
function ftt_calc_register_shortcodes() {
	add_shortcode( 'ftt_calculator', 'ftt_calc_shortcode' );
	// Keep [film_calculator] working, unless the standalone Film Calculator plugin already provides it.
	if ( ! shortcode_exists( 'film_calculator' ) ) {
		add_shortcode( 'film_calculator', 'ftt_calc_shortcode' );
	}
}
function ftt_calc_shortcode( $atts ) {
	$o    = ftt_calc_get_options();
	$atts = shortcode_atts(
		array(
			'format'       => $o['default_format'],
			'fps'          => $o['default_fps'],
			'theme'        => $o['theme'],
			'accent'       => $o['accent'],
			'heading_font' => $o['heading_font'],
			'extras'       => '',
			'switch'       => '',
			'table'        => '',
			'meters'       => '',
			'frames'       => '',
			'fps_picker'   => '',
			'speed'        => '',
			'seconds'      => '',
			'title'        => '',
		),
		$atts,
		'film_calculator'
	);

	// Whitelist / sanitize every attribute.
	$format       = ftt_calc_sanitize_format( $atts['format'], $o['default_format'] );
	$atts['format'] = $format; // Sanitized value for the filmcalc_format_badge filter below (as in FilmTransfer.ai's film-calculator.php).
	$fps          = ftt_calc_sanitize_fps( $atts['fps'], $o['default_fps'] );
	$theme        = ftt_calc_sanitize_choice( $atts['theme'], array( 'dark', 'light' ), $o['theme'] );
	$accent       = ftt_calc_sanitize_hex( $atts['accent'] );
	$heading_font = ftt_calc_sanitize_choice( $atts['heading_font'], array( 'preset', 'theme', 'plain', 'yeseva' ), $o['heading_font'] );
	// Field toggles: a field's own attribute wins, then the old extras="" (both readouts), then Settings.
	$show_meters  = ftt_calc_bool_att( $atts['meters'], $o['show_meters'] );
	$show_frames  = ftt_calc_bool_att( $atts['frames'], $o['show_frames'] );
	$show_fps     = ftt_calc_bool_att( $atts['fps_picker'], $o['show_fps'] );
	$show_speed   = ftt_calc_bool_att( $atts['speed'], ftt_calc_bool_att( $atts['extras'], $o['show_speed'] ) );
	$show_seconds = ftt_calc_bool_att( $atts['seconds'], ftt_calc_bool_att( $atts['extras'], $o['show_seconds'] ) );
	$hide         = array();
	foreach ( array( 'meters' => $show_meters, 'frames' => $show_frames, 'fps' => $show_fps ) as $k => $on ) {
		if ( ! $on ) {
			$hide[] = $k;
		}
	}
	$show_switch  = ftt_calc_bool_att( $atts['switch'], $o['show_switch'] );
	$show_table   = ftt_calc_bool_att( $atts['table'], $o['show_table'] );
	$title        = '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : __( 'Film Calculator', 'film-transfer-toolkit' );

	if ( 'preset' === $heading_font ) {
		$heading_font = ( 'light' === $theme ) ? 'yeseva' : 'theme';
	}

	$formats  = ftt_calc_formats();
	$speeds   = ftt_calc_speeds();
	$is_std   = in_array( $fps, $speeds, true );
	$classes  = array( 'filmcalc', 'filmcalc--' . $theme, 'filmcalc--hf-' . $heading_font );
	if ( 'plain' === $heading_font ) {
		$classes[] = 'filmcalc--plain-heading'; // 1.0.x class name, kept for compatibility.
	}
	$style = $accent ? '--fc-accent:' . $accent . ';' : '';
	static $instance = 0;
	$instance++;
	$uid = 'ftt-calc-' . $instance;

	// Enqueue only when the shortcode is rendered.
	wp_enqueue_style( 'ftt-calculator' );
	wp_enqueue_script( 'ftt-calculator' );

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?> data-ftt-calc data-mode="len"<?php echo $hide ? ' data-hide="' . esc_attr( implode( ' ', $hide ) ) . '"' : ''; ?>>
		<div class="filmcalc__head">
			<div>
				<h3 class="filmcalc__title"><?php echo esc_html( $title ); ?></h3>
				<p class="filmcalc__sub"><?php esc_html_e( 'Speed, Feet, Frames and Time — edit any field and the others update.', 'film-transfer-toolkit' ); ?></p>
			</div>
			<?php if ( $show_switch ) : ?>
				<button type="button" class="filmcalc__switch" data-fc="switch" aria-pressed="false" aria-describedby="<?php echo esc_attr( $uid . '-mode' ); ?>">
					<span aria-hidden="true">⇄</span> <?php esc_html_e( 'Switch', 'film-transfer-toolkit' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php if ( $show_switch ) : ?>
			<p class="filmcalc__mode" id="<?php echo esc_attr( $uid . '-mode' ); ?>" data-fc="mode" data-len="<?php esc_attr_e( 'Length → Time', 'film-transfer-toolkit' ); ?>" data-time="<?php esc_attr_e( 'Time → Length', 'film-transfer-toolkit' ); ?>"><?php esc_html_e( 'Length → Time', 'film-transfer-toolkit' ); ?></p>
		<?php endif; ?>
		<div class="filmcalc__grid">
			<label class="filmcalc__field filmcalc__f-format"><span><?php esc_html_e( 'Film format', 'film-transfer-toolkit' ); ?><?php echo apply_filters( 'filmcalc_format_badge', '', $atts['format'] ); ?></span>
				<select data-fc="format">
				<?php foreach ( $formats as $key => $f ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" data-fpf="<?php echo esc_attr( $f['fpf'] ); ?>" <?php selected( $format, $key ); ?>><?php echo esc_html( $f['label'] . ' – ' . $f['fpf_label'] . ' fr/ft' ); ?></option>
				<?php endforeach; ?>
				</select></label>
			<label class="filmcalc__field filmcalc__f-speed"<?php echo $show_fps ? '' : ' hidden'; ?>><span><?php esc_html_e( 'Frame rate (fps)', 'film-transfer-toolkit' ); ?></span>
				<select data-fc="speed">
				<?php foreach ( $speeds as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $fps, $s ); ?>><?php echo esc_html( $s ); ?></option>
				<?php endforeach; ?>
					<option value="custom" <?php selected( ! $is_std ); ?>><?php esc_html_e( 'Custom…', 'film-transfer-toolkit' ); ?></option>
				</select></label>
			<label class="filmcalc__field filmcalc__custom"<?php echo ( $is_std || ! $show_fps ) ? ' hidden' : ''; ?>><span><?php esc_html_e( 'Custom fps', 'film-transfer-toolkit' ); ?></span>
				<input type="number" inputmode="decimal" min="0.1" max="1000" step="any" data-fc="custom" value="<?php echo esc_attr( $is_std ? '' : $fps ); ?>"></label>
			<label class="filmcalc__field filmcalc__f-feet"><span><?php esc_html_e( 'Length (feet)', 'film-transfer-toolkit' ); ?></span>
				<input type="number" inputmode="decimal" min="0" step="any" data-fc="feet" value="100"></label>
			<label class="filmcalc__field filmcalc__f-meters"<?php echo $show_meters ? '' : ' hidden'; ?>><span><?php esc_html_e( 'Length (meters)', 'film-transfer-toolkit' ); ?></span>
				<input type="number" inputmode="decimal" min="0" step="any" data-fc="meters"></label>
			<label class="filmcalc__field filmcalc__f-time"><span><?php esc_html_e( 'Running time (h:mm:ss.ss)', 'film-transfer-toolkit' ); ?></span>
				<input type="text" inputmode="decimal" autocomplete="off" data-fc="time" placeholder="11:06.67"></label>
			<label class="filmcalc__field filmcalc__f-frames"<?php echo $show_frames ? '' : ' hidden'; ?>><span><?php esc_html_e( 'Frames', 'film-transfer-toolkit' ); ?></span>
				<input type="number" inputmode="numeric" min="0" step="1" data-fc="frames"></label>
		</div>
		<p class="filmcalc__summary" data-fc="summary" aria-live="polite"></p>
		<?php if ( $show_speed || $show_seconds ) : ?>
			<dl class="filmcalc__stats">
				<?php if ( $show_speed ) : ?><div><dt><?php esc_html_e( 'Film speed', 'film-transfer-toolkit' ); ?></dt><dd data-fc="ftmin">–</dd></div><?php endif; ?>
				<?php if ( $show_seconds ) : ?><div><dt><?php esc_html_e( 'Total seconds', 'film-transfer-toolkit' ); ?></dt><dd data-fc="seconds">–</dd></div><?php endif; ?>
			</dl>
		<?php endif; ?>
		<?php if ( $show_table ) : ?>
			<div class="filmcalc__tablewrap">
				<table class="filmcalc__table">
					<caption><?php esc_html_e( 'Format reference', 'film-transfer-toolkit' ); ?></caption>
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Format', 'film-transfer-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Frames/ft', 'film-transfer-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Common fps', 'film-transfer-toolkit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'ft/min', 'film-transfer-toolkit' ); ?> @ <span data-fc="tablefps"><?php echo esc_html( $fps ); ?></span> fps</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $formats as $key => $f ) : ?>
						<tr data-fc-row="<?php echo esc_attr( $key ); ?>" data-fpf="<?php echo esc_attr( $f['fpf'] ); ?>">
							<th scope="row"><?php echo esc_html( $f['label'] ); ?></th>
							<td><?php echo esc_html( $f['fpf_label'] ); ?></td>
							<td><?php echo esc_html( $f['std'] ); ?></td>
							<td data-fc="rowftmin"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<noscript><p><?php esc_html_e( 'This calculator requires JavaScript.', 'film-transfer-toolkit' ); ?></p></noscript>
	</div>
	<?php
	return ob_get_clean();
}
