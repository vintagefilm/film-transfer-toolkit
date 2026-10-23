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
				<tr><th><label for="ftt-clip-heading"><?php esc_html_e( 'Heading', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-heading" class="regular-text" maxlength="120" name="<?php echo esc_attr( $n ); ?>[heading]" value="<?php echo esc_attr( $o['heading'] ); ?>">
					<p class="description"><?php esc_html_e( 'Shown above every clip selector in your site\'s heading font. Default "Select Stock Footage". Leave blank to hide it. One selector can override it (heading option on the shortcode or block).', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-label"><?php esc_html_e( 'Rate card label', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-label" class="large-text" name="ftt_clip_rate_card[label]" value="<?php echo esc_attr( $c['label'] ); ?>"></td></tr>
				<?php foreach ( $fields as $k => $l ) : ?>
					<tr><th><label for="ftt-clip-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></label></th><td><input id="ftt-clip-<?php echo esc_attr( $k ); ?>" type="number" min="0" class="small-text" name="ftt_clip_rate_card[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $c[ $k ] ); ?>"></td></tr>
				<?php endforeach; ?>
				<tr><th><?php esc_html_e( 'Require sign-in to add to cart', 'film-transfer-toolkit' ); ?></th><td>
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[require_login]" value="0">
					<label><input id="ftt-clip-require-login" type="checkbox" name="<?php echo esc_attr( $n ); ?>[require_login]" value="1" <?php checked( $o['require_login'], 1 ); ?>> <?php esc_html_e( 'Visitors must sign in or create an account before clips go into the cart', 'film-transfer-toolkit' ); ?></label>
					<p class="description"><?php esc_html_e( 'FluentCart clip products only. Visitors can still preview and mark clips. Add to cart saves their selection for 2 hours and offers Sign in / Create account; after signing in or registering they come back to the same page and the clips are added to the cart automatically (price recalculated on the server). Signed-in customers are not affected. One selector can override this with require_login="yes" or "no".', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-login-page"><?php esc_html_e( 'Sign-in page', 'film-transfer-toolkit' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => $n . '[login_page_id]', 'id' => 'ftt-clip-login-page', 'selected' => (int) $o['login_page_id'], 'show_option_none' => __( 'WordPress sign-in (wp-login.php)', 'film-transfer-toolkit' ), 'option_none_value' => '0' ) ); ?>
					<p class="description"><?php esc_html_e( 'For example the FluentCart Login page (FluentCart > Settings > Pages). The page is opened with redirect_to back to the selector.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-register-page"><?php esc_html_e( 'Registration page', 'film-transfer-toolkit' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => $n . '[register_page_id]', 'id' => 'ftt-clip-register-page', 'selected' => (int) $o['register_page_id'], 'show_option_none' => __( 'WordPress registration (needs "Anyone can register")', 'film-transfer-toolkit' ), 'option_none_value' => '0' ) ); ?>
					<p class="description"><?php esc_html_e( 'For example the FluentCart Registration page.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'When a signed-out visitor adds clips', 'film-transfer-toolkit' ); ?></th><td>
					<?php foreach ( array( 'block' => __( 'Ask them to sign in first; the clips go into the cart after they sign in or register (default)', 'film-transfer-toolkit' ), 'add_then_prompt' => __( 'Add the clips to the cart now, then ask them to sign in or create an account (the same cart is kept, no duplicates)', 'film-transfer-toolkit' ) ) as $k => $l ) : ?>
						<label class="ftt-radio" style="display:block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[login_mode]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $o['login_mode'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Only used when Require sign-in is on. WP-CLI: wp option patch update ftt_clip_settings login_mode add_then_prompt (or block).', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'After sign-in', 'film-transfer-toolkit' ); ?></th><td>
					<?php foreach ( array( 'page' => __( 'Return to the page with a "clips added" notice and a cart link (default)', 'film-transfer-toolkit' ), 'cart' => __( 'Go straight to the cart', 'film-transfer-toolkit' ) ) as $k => $l ) : ?>
						<label class="ftt-radio" style="display:block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[after_login]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $o['after_login'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?></td></tr>
			</table>
			<details class="ftt-advanced" id="ftt-adv-clips"><summary><?php esc_html_e( 'Advanced: rounding, frame rate, FluentCart product and automatic placement', 'film-transfer-toolkit' ); ?></summary><div class="ftt-advanced__body">
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Rounding of clip length', 'film-transfer-toolkit' ); ?></th><td>
					<?php foreach ( array( 'up' => __( 'Round up to the next whole second (default)', 'film-transfer-toolkit' ), 'down' => __( 'Round down (in the customer\'s favour)', 'film-transfer-toolkit' ), 'nearest' => __( 'Round to the nearest second', 'film-transfer-toolkit' ) ) as $k => $l ) : ?>
						<label class="ftt-radio" style="display:block"><input type="radio" name="ftt_clip_rate_card[rounding]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $c['rounding'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Clips are billed in whole seconds. Example: a 30.03 s clip bills 31 s rounded up, 30 s rounded down or to the nearest. The minimum billed seconds and minimum charge still apply.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-fps"><?php esc_html_e( 'Default timecode frame rate', 'film-transfer-toolkit' ); ?></label></th><td><select id="ftt-clip-fps" name="<?php echo esc_attr( $n ); ?>[default_fps]"><?php foreach ( FTT_Timecode::LABELS as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['default_fps'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
					<p class="description"><?php esc_html_e( 'Used when a selector has no fps attribute and no clip product.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><label for="ftt-clip-product"><?php esc_html_e( 'Default clip product (FluentCart)', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-clip-product" type="number" min="0" class="small-text" name="<?php echo esc_attr( $n ); ?>[default_product_id]" value="<?php echo esc_attr( $o['default_product_id'] ? $o['default_product_id'] : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional. A FluentCart product with clip selling switched on; selectors without product_id add clips under it. Leave empty to use clip requests.', 'film-transfer-toolkit' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Automatic placement', 'film-transfer-toolkit' ); ?></th><td>
					<?php foreach ( array( 'off' => __( 'Off: only where I add the block or shortcode', 'film-transfer-toolkit' ), 'page' => __( 'Under the content of every page that has a video', 'film-transfer-toolkit' ), 'post' => __( 'Under the content of every post that has a video', 'film-transfer-toolkit' ), 'all' => __( 'Under the content of every page, post and custom post with a video', 'film-transfer-toolkit' ) ) as $k => $l ) : ?>
						<label class="ftt-radio" style="display:block"><input type="radio" name="<?php echo esc_attr( $n ); ?>[auto_attach]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $o['auto_attach'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?></td></tr>
			</table>
			</div></details>
			<?php submit_button( __( 'Save changes', 'film-transfer-toolkit' ) ); ?>
		</form>
	</div>
	<?php
}
