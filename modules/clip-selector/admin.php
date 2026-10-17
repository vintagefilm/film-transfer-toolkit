<?php
/**
 * Film Transfer Toolkit > Clip selector: EXAMPLE rate card settings.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', function () {
	register_setting( 'ftt_clip', 'ftt_clip_rate_card', array( 'type' => 'array', 'sanitize_callback' => array( 'FTT_Clip_Rate_Card', 'sanitize' ), 'show_in_rest' => false ) );
} );

add_action( 'ftt/admin_menu', function () {
	add_submenu_page( 'ftt', __( 'Clip selector', 'film-transfer-toolkit' ), __( 'Clip selector', 'film-transfer-toolkit' ), 'manage_options', 'ftt-clips', 'ftt_clip_render_settings' );
} );

function ftt_clip_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
	}
	$c = FTT_Clip_Rate_Card::sanitize( get_option( 'ftt_clip_rate_card', array() ) );
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
		<p><?php esc_html_e( 'Shortcode: [ftt_clip_selector product_id="123" clip_id="FIRE-0001" fps="29.97df" player="auto|fluent|fv"] (also [fw_clip_cart] when the prototype plugin is not active). Requires FluentCart. The server recalculates every clip price.', 'film-transfer-toolkit' ); ?></p>
		<p><strong><?php esc_html_e( 'FluentCart:', 'film-transfer-toolkit' ); ?></strong> <?php echo class_exists( '\FluentCart\App\Helpers\CartHelper' ) ? esc_html__( 'active', 'film-transfer-toolkit' ) : esc_html__( 'not active (Add to cart will fail until it is installed)', 'film-transfer-toolkit' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'ftt_clip' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="ftt-clip-label"><?php esc_html_e( 'Rate card label', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-label" class="large-text" name="ftt_clip_rate_card[label]" value="<?php echo esc_attr( $c['label'] ); ?>"></td></tr>
				<?php foreach ( $fields as $k => $l ) : ?>
					<tr><th><label for="ftt-clip-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label></th><td><input id="ftt-clip-<?php echo esc_attr( $k ); ?>" type="number" min="0" class="small-text" name="ftt_clip_rate_card[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $c[ $k ] ); ?>"></td></tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
