<?php
/**
 * FTT > Clip selector: EXAMPLE rate card settings.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', function () {
	register_setting( 'ftt_clip', 'ftt_clip_rate_card', array( 'type' => 'array', 'sanitize_callback' => array( 'FTT_Clip_Rate_Card', 'sanitize' ), 'show_in_rest' => false ) );
	register_setting( 'ftt_clip', FTT_Clip_Settings::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( 'FTT_Clip_Settings', 'sanitize' ), 'show_in_rest' => false ) );
} );

add_action( 'ftt/admin_menu', function () {
	add_submenu_page( 'ftt', __( 'Clip selector', 'film-transfer-toolkit' ), __( 'Clip selector', 'film-transfer-toolkit' ), 'manage_options', 'ftt-clips', 'ftt_clip_render_settings' );
} );

function ftt_clip_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
	}
	$c = FTT_Clip_Rate_Card::sanitize( get_option( 'ftt_clip_rate_card', array() ) );
	$o = FTT_Clip_Settings::get();
	$n = FTT_Clip_Settings::OPTION;
	$fields = array(
		'per_second_cents' => __( 'Price per second (cents)', 'film-transfer-toolkit' ),
		'min_seconds'      => __( 'Minimum billed seconds per clip', 'film-transfer-toolkit' ),
		'max_seconds'      => __( 'Longest clip (seconds)', 'film-transfer-toolkit' ),
		'min_charge_cents' => __( 'Minimum charge per clip (cents)', 'film-transfer-toolkit' ),
	);
	?>
	<div class="wrap ftt-admin">
		<h1><?php esc_html_e( 'Clip selector', 'film-transfer-toolkit' ); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e( 'Put the Clip selector block or [ftt_clip_selector] under any video player: a Video block or uploaded video, a YouTube or Vimeo embed, FluentPlayer or FV Player. To attach it to one particular player on a page with several, give that video an HTML anchor (block settings > Advanced) and use player_selector="#that-anchor". The server recalculates every clip price.', 'film-transfer-toolkit' ); ?></p>
		<p><strong><?php esc_html_e( 'FluentCart:', 'film-transfer-toolkit' ); ?></strong> <?php echo FTT_Clip_Settings::fluentcart_active() ? esc_html__( 'active. Selectors with a clip product (product_id, or the default below) add clips to the cart; others send clip requests.', 'film-transfer-toolkit' ) : esc_html__( 'not active. Visitors send their clip list as a clip request (saved under Clip requests). Nothing else is needed.', 'film-transfer-toolkit' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'ftt_clip' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="ftt-clip-label"><?php esc_html_e( 'Rate card label', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-label" class="large-text" name="ftt_clip_rate_card[label]" value="<?php echo esc_attr( $c['label'] ); ?>"></td></tr>
				<?php foreach ( $fields as $k => $l ) : ?>
					<tr><th><label for="ftt-clip-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label></th><td><input id="ftt-clip-<?php echo esc_attr( $k ); ?>" type="number" min="0" class="small-text" name="ftt_clip_rate_card[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $c[ $k ] ); ?>"></td></tr>
				<?php endforeach; ?>
			</table>
			<h2><?php esc_html_e( 'Players and orders', 'film-transfer-toolkit' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="ftt-clip-fps"><?php esc_html_e( 'Default timecode frame rate', 'film-transfer-toolkit' ); ?></label></th><td><select id="ftt-clip-fps" name="<?php echo esc_attr( $n ); ?>[default_fps]"><?php foreach ( FTT_Timecode::LABELS as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['default_fps'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
					<p class="description"><?php esc_html_e( 'Used when a selector has no fps attribute and no clip product.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-product"><?php esc_html_e( 'Default clip product (FluentCart)', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-product" type="number" min="0" class="small-text" name="<?php echo esc_attr( $n ); ?>[default_product_id]" value="<?php echo esc_attr( $o['default_product_id'] ? $o['default_product_id'] : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. A FluentCart product with clip selling switched on; selectors without product_id add clips under it. Leave empty to use clip requests.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Automatic placement', 'film-transfer-toolkit' ); ?></th><td>
					<?php foreach ( array( 'off' => __( 'Off: only where I add the block or shortcode', 'film-transfer-toolkit' ), 'page' => __( 'Under the content of every page that has a video', 'film-transfer-toolkit' ), 'post' => __( 'Under the content of every post that has a video', 'film-transfer-toolkit' ), 'all' => __( 'Under the content of every page, post and custom post with a video', 'film-transfer-toolkit' ) ) as $k => $l ) : ?>
						<label class="ftt-radio" style="display:block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[auto_attach]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $o['auto_attach'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
