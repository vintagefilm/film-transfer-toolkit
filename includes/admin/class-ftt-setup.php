<?php
/**
 * Setup wizard: modules, pages, look, prices, email, finish.
 *
 * Runs automatically once, right after the first activation on a site that has never had the toolkit.
 * Updates and re-activations never start it, and nothing changes until a step's Save button is pressed.
 * Pages are only created from the Pages step, and never twice for the same tool.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Setup {
	const OPT      = 'ftt_setup';
	const PAGES    = 'ftt_pages';
	const REDIRECT = 'ftt_setup_redirect';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'admin_post_ftt_setup', array( __CLASS__, 'save' ) );
	}

	public static function steps() {
		return array(
			'modules' => __( 'Tools', 'film-transfer-toolkit' ),
			'pages'   => __( 'Pages', 'film-transfer-toolkit' ),
			'look'    => __( 'Look', 'film-transfer-toolkit' ),
			'prices'  => __( 'Prices', 'film-transfer-toolkit' ),
			'storage' => __( 'Storage', 'film-transfer-toolkit' ),
			'email'   => __( 'Email', 'film-transfer-toolkit' ),
			'finish'  => __( 'Done', 'film-transfer-toolkit' ),
		);
	}

	/** pending (new site, wizard not finished), existing (upgraded install), done. */
	public static function state() {
		$s = get_option( self::OPT, array() );
		return is_array( $s ) && isset( $s['state'] ) ? (string) $s['state'] : 'existing';
	}

	private static function set_state( $state ) {
		update_option( self::OPT, array( 'state' => $state, 'time' => time(), 'version' => FTT_VERSION ), true );
	}

	/** Called from FTT_Plugin::activate(). $fresh is true when no toolkit settings existed before. */
	public static function on_activate( $fresh ) {
		if ( false === get_option( self::OPT, false ) ) {
			add_option( self::OPT, array( 'state' => $fresh ? 'pending' : 'existing', 'time' => time(), 'version' => FTT_VERSION ), '', 'yes' );
		}
		if ( $fresh && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			set_transient( self::REDIRECT, get_current_user_id(), 120 );
		}
	}

	/** Updates replace files without running activation: record existing installs so they never auto-start. */
	public static function maybe_upgrade() {
		if ( false === get_option( self::OPT, false ) ) {
			add_option( self::OPT, array( 'state' => false === get_option( 'ftt_modules', false ) ? 'pending' : 'existing', 'time' => time(), 'version' => FTT_VERSION ), '', 'yes' );
		}
	}

	public static function maybe_redirect() {
		$uid = get_transient( self::REDIRECT );
		if ( false === $uid ) {
			return;
		}
		if ( wp_doing_ajax() || is_network_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only flag set by core.
			delete_transient( self::REDIRECT );
			return;
		}
		if ( (int) $uid !== get_current_user_id() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( self::REDIRECT );
		if ( 'pending' === self::state() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ftt-setup' ) );
			exit;
		}
	}

	public static function url( $step = 'modules', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'ftt-setup', 'step' => $step ), $args ), admin_url( 'admin.php' ) );
	}

	private static function next_step( $step ) {
		$keys = array_keys( self::steps() );
		$i    = array_search( $step, $keys, true );
		return false === $i || ! isset( $keys[ $i + 1 ] ) ? 'finish' : $keys[ $i + 1 ];
	}

	private static function prev_step( $step ) {
		$keys = array_keys( self::steps() );
		$i    = array_search( $step, $keys, true );
		return $i ? $keys[ $i - 1 ] : '';
	}

	/** Embeds (shortcode => definition) for the enabled modules that have a page. */
	public static function page_embeds( $modules = null ) {
		$modules = null === $modules ? FTT_Plugin::enabled() : $modules;
		$out     = array();
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			if ( ! empty( $modules[ $e['module'] ] ) && ! empty( $e['page'] ) ) {
				$out[ $tag ] = $e;
			}
		}
		return $out;
	}

	public static function pages() {
		$p = get_option( self::PAGES, array() );
		return is_array( $p ) ? array_map( 'absint', $p ) : array();
	}

	/* ---------------------------------------------------------------- render */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$steps = self::steps();
		$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'modules'; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		$step  = isset( $steps[ $step ] ) ? $step : 'modules';
		$msg   = get_transient( 'ftt_setup_msg_' . get_current_user_id() );
		delete_transient( 'ftt_setup_msg_' . get_current_user_id() );
		$n = array_search( $step, array_keys( $steps ), true ) + 1;
		?>
		<div class="wrap ftt-admin ftt-wizard">
			<h1><?php esc_html_e( 'FTT setup', 'film-transfer-toolkit' ); ?></h1>
			<?php if ( 'existing' === self::state() && 'modules' === $step ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'The toolkit is already set up on this site. You can walk through these steps to review things; nothing changes until you press a Save button, and existing pages are reused rather than copied.', 'film-transfer-toolkit' ); ?></p></div>
			<?php endif; ?>
			<ol class="ftt-steps" aria-label="<?php esc_attr_e( 'Setup progress', 'film-transfer-toolkit' ); ?>">
				<?php $i = 0; foreach ( $steps as $k => $label ) : $i++; ?>
					<li class="<?php echo esc_attr( $i < $n ? 'is-done' : ( $i === $n ? 'is-current' : '' ) ); ?>"<?php echo $i === $n ? ' aria-current="step"' : ''; ?>><a href="<?php echo esc_url( self::url( $k ) ); ?>"><span class="ftt-steps__n"><?php echo esc_html( (string) $i ); ?></span> <?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ol>
			<div class="ftt-card ftt-wizard__card">
				<?php if ( is_array( $msg ) ) : ?>
					<div class="notice notice-<?php echo esc_attr( 'error' === $msg[0] ? 'error' : 'success' ); ?> inline"><?php foreach ( (array) $msg[1] as $line ) : ?><p><?php echo esc_html( $line ); ?></p><?php endforeach; ?></div>
				<?php endif; ?>
				<?php
				if ( 'finish' === $step ) {
					self::render_finish();
				} else {
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="ftt_setup">
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">
						<?php wp_nonce_field( 'ftt_setup_' . $step ); ?>
						<?php call_user_func( array( __CLASS__, 'render_' . $step ) ); ?>
						<p class="ftt-wizard__nav">
							<?php $prev = self::prev_step( $step ); if ( $prev ) : ?><a class="button" href="<?php echo esc_url( self::url( $prev ) ); ?>"><?php esc_html_e( '« Back', 'film-transfer-toolkit' ); ?></a><?php endif; ?>
							<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save and continue', 'film-transfer-toolkit' ); ?></button>
							<a class="ftt-wizard__skip" href="<?php echo esc_url( self::url( self::next_step( $step ) ) ); ?>"><?php esc_html_e( 'Skip this step (change nothing)', 'film-transfer-toolkit' ); ?></a>
						</p>
					</form>
					<?php
				}
				?>
			</div>
			<p class="ftt-wizard__exit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt' ) ); ?>"><?php esc_html_e( 'Exit setup and go to the dashboard', 'film-transfer-toolkit' ); ?></a></p>
		</div>
		<?php
	}

	private static function render_modules() {
		$on = FTT_Plugin::enabled();
		?>
		<h2><?php esc_html_e( 'Which tools do you want?', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Tick the tools you want to use on your site. You can change this at any time under FTT > Tools.', 'film-transfer-toolkit' ); ?></p>
		<div class="ftt-choices">
		<?php foreach ( FTT_Registry::tools() as $k => $t ) : ?>
			<label class="ftt-choice">
				<input type="checkbox" name="modules[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $on[ $k ] ) ); ?>>
				<span class="dashicons dashicons-<?php echo esc_attr( $t['icon'] ); ?>" aria-hidden="true"></span>
				<strong><?php echo esc_html( $t['title'] ); ?></strong>
				<span class="ftt-choice__desc"><?php echo esc_html( $t['summary'] ); ?></span>
				<?php if ( 'clips' === $k ) : ?><span class="ftt-choice__note"><?php echo esc_html( defined( 'FLUENTCART_VERSION' ) || class_exists( '\FluentCart\App\App' ) ? __( 'FluentCart detected: clips can go to the cart.', 'film-transfer-toolkit' ) : __( 'FluentCart is not active: visitors send clip requests instead of using a cart.', 'film-transfer-toolkit' ) ); ?></span><?php endif; ?>
				<?php if ( 'rfq' === $k ) : ?><span class="ftt-choice__note"><?php esc_html_e( 'Optional: for directory-style sites that list several providers.', 'film-transfer-toolkit' ); ?></span><?php endif; ?>
			</label>
		<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_pages() {
		$embeds = self::page_embeds();
		$saved  = self::pages();
		?>
		<h2><?php esc_html_e( 'Pages for your tools', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Each tool needs a page to appear on. Where a page already shows the tool, it is selected for you so nothing is duplicated. You can also add tools to any page yourself later (see Help).', 'film-transfer-toolkit' ); ?></p>
		<?php if ( ! $embeds ) : ?>
			<p><em><?php esc_html_e( 'None of the tools you chose need a page of their own (the clip selector goes on product pages). Continue to the next step.', 'film-transfer-toolkit' ); ?></em></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat ftt-pages-table">
			<thead><tr><th><?php esc_html_e( 'Tool', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Page', 'film-transfer-toolkit' ); ?></th></tr></thead>
			<tbody>
			<?php
			foreach ( $embeds as $tag => $e ) :
				$found   = FTT_Registry::find_pages( $e );
				$default = $found ? 'reuse:' . $found[0]->ID : 'create';
				if ( ! empty( $saved[ $tag ] ) ) {
					foreach ( $found as $p ) {
						if ( $p->ID === $saved[ $tag ] ) {
							$default = 'reuse:' . $p->ID;
						}
					}
				}
				$taken = get_page_by_path( $e['page']['slug'] );
				$name  = 'pages[' . $tag . ']';
				?>
				<tr>
					<th scope="row"><strong><?php echo esc_html( $e['label'] ); ?></strong><br><code>[<?php echo esc_html( $tag ); ?>]</code></th>
					<td>
						<fieldset>
						<?php foreach ( $found as $p ) : ?>
							<label class="ftt-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( 'reuse:' . $p->ID ); ?>" <?php checked( $default, 'reuse:' . $p->ID ); ?>>
							<?php
							/* translators: 1: page title, 2: page address, 3: status */
							echo esc_html( sprintf( __( 'Use the existing page "%1$s" (%2$s, %3$s)', 'film-transfer-toolkit' ), get_the_title( $p ) ?: __( '(no title)', 'film-transfer-toolkit' ), wp_make_link_relative( get_permalink( $p ) ), get_post_status_object( $p->post_status )->label ?? $p->post_status ) );
							?>
							</label>
						<?php endforeach; ?>
						<?php if ( ! $found ) : ?>
							<label class="ftt-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="create" <?php checked( $default, 'create' ); ?>>
							<?php
							/* translators: 1: page title, 2: address */
							echo esc_html( sprintf( __( 'Create a new page "%1$s" at /%2$s/', 'film-transfer-toolkit' ), $e['page']['title'], $e['page']['slug'] ) );
							?>
							</label>
							<?php if ( $taken ) : ?><p class="description ftt-indent"><?php echo esc_html( sprintf( /* translators: %s: address */ __( 'Another page already uses /%s/ without this tool, so WordPress will give the new page a slightly different address. That page is left alone.', 'film-transfer-toolkit' ), $e['page']['slug'] ) ); ?></p><?php endif; ?>
						<?php endif; ?>
						<label class="ftt-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="skip"> <?php esc_html_e( 'No page for now', 'film-transfer-toolkit' ); ?></label>
						</fieldset>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><label><input type="checkbox" name="draft" value="1"> <?php esc_html_e( 'Save new pages as drafts so I can check them before they go live', 'film-transfer-toolkit' ); ?></label></p>
		<p class="description"><?php esc_html_e( 'New pages are not added to your navigation menus. Add them under Appearance > Menus (or in the Site Editor) when you are ready.', 'film-transfer-toolkit' ); ?></p>
		<?php
	}

	private static function render_look() {
		?>
		<h2><?php esc_html_e( 'Look and defaults', 'film-transfer-toolkit' ); ?></h2>
		<?php
		if ( ! function_exists( 'ftt_calc_get_options' ) ) {
			echo '<p><em>' . esc_html__( 'These settings are for the film calculator, which is switched off. The other tools follow your theme\'s fonts and colours. Continue to the next step.', 'film-transfer-toolkit' ) . '</em></p>';
			return;
		}
		$o = ftt_calc_get_options();
		?>
		<p><?php esc_html_e( 'Choose how the film calculator looks and what it shows first. Every page can override these with block settings or shortcode attributes.', 'film-transfer-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Colour scheme', 'film-transfer-toolkit' ); ?></th><td>
				<label class="ftt-radio"><input type="radio" name="theme" value="site" <?php checked( 'site', $o['theme'] ); ?>> <?php esc_html_e( 'Site theme (same colours and fonts as your site and every FTT tool; recommended)', 'film-transfer-toolkit' ); ?></label>
				<label class="ftt-radio"><input type="radio" name="theme" value="dark" <?php checked( 'dark', $o['theme'] ); ?>> <?php esc_html_e( 'Dark (light text on a dark panel)', 'film-transfer-toolkit' ); ?></label>
				<label class="ftt-radio"><input type="radio" name="theme" value="light" <?php checked( 'light', $o['theme'] ); ?>> <?php esc_html_e( 'Light (dark text on a light panel)', 'film-transfer-toolkit' ); ?></label>
			</td></tr>
			<tr><th><label for="ftt-accent"><?php esc_html_e( 'Accent colour', 'film-transfer-toolkit' ); ?></label></th><td>
				<input type="text" id="ftt-accent" name="accent" value="<?php echo esc_attr( $o['accent'] ); ?>" placeholder="#c0392b" pattern="#([0-9a-fA-F]{3}){1,2}" class="regular-text" style="max-width:9em">
				<p class="description"><?php esc_html_e( 'A hex colour such as #c0392b for buttons and highlights. Leave empty for the built-in colour.', 'film-transfer-toolkit' ); ?></p>
			</td></tr>
			<tr><th><label for="ftt-format"><?php esc_html_e( 'Film format shown first', 'film-transfer-toolkit' ); ?></label></th><td>
				<select id="ftt-format" name="default_format"><?php foreach ( ftt_calc_formats() as $k => $f ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['default_format'], (string) $k ); ?>><?php echo esc_html( $f['label'] ); ?></option><?php endforeach; ?></select>
			</td></tr>
			<tr><th><label for="ftt-fps"><?php esc_html_e( 'Frame rate shown first', 'film-transfer-toolkit' ); ?></label></th><td>
				<select id="ftt-fps" name="default_fps"><?php foreach ( ftt_calc_speeds() as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>" <?php selected( $o['default_fps'], $s ); ?>><?php echo esc_html( $s ); ?> fps</option><?php endforeach; ?></select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Fields to show', 'film-transfer-toolkit' ); ?></th><td>
				<input type="hidden" name="fields_form" value="1">
				<?php foreach ( ftt_calc_field_toggle_labels() as $k => $l ) : ?>
					<label class="ftt-radio" style="display:block"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1" <?php checked( ! empty( $o[ $k ] ) ); ?>> <?php echo esc_html( $l[1] ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Feet and running time are always shown.', 'film-transfer-toolkit' ); ?></p>
			</td></tr>
		</table>
		<?php
	}

	private static function render_prices() {
		$stored = get_option( FTT_Price_Table::OPTION, null );
		$t      = FTT_Price_Table::get();
		$has    = is_array( $stored ) && ! empty( $stored['formats'] );
		?>
		<h2><?php esc_html_e( 'Prices', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'The price table and the estimate builder use these prices. The toolkit starts with clearly labelled EXAMPLE prices; a banner tells visitors they are examples until you enter your own and mark the table Final on the Prices page.', 'film-transfer-toolkit' ); ?></p>
		<p><?php /* translators: 1: label, 2: status */ echo esc_html( sprintf( __( 'Current prices: %1$s (%2$s).', 'film-transfer-toolkit' ), $t['label'], strtoupper( $t['status'] ) ) ); ?></p>
		<fieldset>
			<?php if ( $has ) : ?><label class="ftt-radio"><input type="radio" name="prices" value="keep" checked> <?php esc_html_e( 'Keep the current prices', 'film-transfer-toolkit' ); ?></label><?php endif; ?>
			<label class="ftt-radio"><input type="radio" name="prices" value="example" <?php checked( ! $has ); ?>> <?php esc_html_e( 'Use the EXAMPLE prices (recommended to start; edit them later on the Prices page)', 'film-transfer-toolkit' ); ?></label>
			<label class="ftt-radio"><input type="radio" name="prices" value="import"> <?php esc_html_e( 'Import prices from a JSON file (copied from the JSON box at the bottom of the Prices page on another site, or from "wp ftt export")', 'film-transfer-toolkit' ); ?></label>
			<div class="ftt-indent">
				<p><label for="ftt-price-file"><?php esc_html_e( 'JSON file', 'film-transfer-toolkit' ); ?></label> <input type="file" id="ftt-price-file" name="price_file" accept=".json,application/json"></p>
				<p><label for="ftt-price-json"><?php esc_html_e( 'or paste the JSON here', 'film-transfer-toolkit' ); ?></label><br><textarea id="ftt-price-json" name="price_json" rows="5" class="large-text code"></textarea></p>
			</div>
		</fieldset>
		<p><?php echo esc_html( sprintf( /* translators: %s: list of service names */ __( 'Services customers choose from: %s. You can add, rename, reorder or remove them, give each a description, and add "per custom unit" add-ons (for example per USB drive) on the Prices page.', 'film-transfer-toolkit' ), implode( ', ', wp_list_pluck( $t['services'], 'label' ) ) ) ); ?></p>
		<p class="description"><?php esc_html_e( 'To type prices in one by one, finish setup and open FTT > Settings > Prices.', 'film-transfer-toolkit' ); ?></p>
		<?php
	}

	private static function render_storage() {
		?>
		<h2><?php esc_html_e( 'Storage calculator defaults', 'film-transfer-toolkit' ); ?></h2>
		<?php
		if ( ! function_exists( 'ftt_storage_get_options' ) ) {
			echo '<p><em>' . esc_html__( 'The storage calculator is switched off. Continue to the next step.', 'film-transfer-toolkit' ) . '</em></p>';
			return;
		}
		echo '<p>' . esc_html__( 'Choose the film format and the file formats (deliverables) the storage calculator starts with. You can change these later under FTT > Storage calculator.', 'film-transfer-toolkit' ) . '</p>';
		ftt_storage_render_fields( ftt_storage_get_options(), 'storage' );
	}

	private static function render_email() {
		$g = FTT_Plugin::general();
		?>
		<h2><?php esc_html_e( 'Email', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'When a customer sends an estimate as a quote request, the toolkit can tell you by email. Leave the address empty if you would rather check Quote requests in the admin yourself.', 'film-transfer-toolkit' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th><label for="ftt-notify"><?php esc_html_e( 'Notify address (optional)', 'film-transfer-toolkit' ); ?></label></th><td><input type="email" id="ftt-notify" name="notify_email" class="regular-text" value="<?php echo esc_attr( $g['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Mail mode', 'film-transfer-toolkit' ); ?></th><td>
				<p><strong><?php echo esc_html( 'send' === $g['mail_mode'] ? __( 'Send (emails go out with wp_mail)', 'film-transfer-toolkit' ) : __( 'Log only (nothing is sent)', 'film-transfer-toolkit' ) ); ?></strong></p>
				<p class="description"><?php esc_html_e( 'Setup never changes this. In log-only mode every email the toolkit would send is written to the Mail log (FTT > Settings > Email) instead, so you can test safely. When you are ready, switch Mail mode to Send under Settings.', 'film-transfer-toolkit' ); ?></p>
			</td></tr>
		</table>
		<?php
	}

	private static function render_finish() {
		if ( 'done' !== self::state() ) {
			self::set_state( 'done' );
		}
		$pages = self::pages();
		?>
		<h2><span class="dashicons dashicons-yes-alt ftt-ok" aria-hidden="true"></span> <?php esc_html_e( 'You are all set', 'film-transfer-toolkit' ); ?></h2>
		<?php if ( $pages ) : ?>
			<h3><?php esc_html_e( 'Your tool pages', 'film-transfer-toolkit' ); ?></h3>
			<ul class="ftt-list">
			<?php foreach ( FTT_Registry::embeds() as $tag => $e ) : if ( empty( $pages[ $tag ] ) || ! get_post( $pages[ $tag ] ) ) { continue; } $p = get_post( $pages[ $tag ] ); ?>
				<li><strong><?php echo esc_html( $e['label'] ); ?>:</strong> <?php echo esc_html( get_the_title( $p ) ); ?> <span class="ftt-pill<?php echo 'publish' === $p->post_status ? ' ftt-pill--on' : ''; ?>"><?php echo esc_html( get_post_status_object( $p->post_status )->label ?? $p->post_status ); ?></span>
					&nbsp;<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'film-transfer-toolkit' ); ?></a> | <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ?: '' ); ?>"><?php esc_html_e( 'Edit', 'film-transfer-toolkit' ); ?></a></li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Next steps', 'film-transfer-toolkit' ); ?></h3>
		<ul class="ftt-list">
			<li><a href="<?php echo esc_url( FTT_Admin_UX::settings_url( 'prices' ) ); ?>"><?php esc_html_e( 'Enter your own prices and mark them Final', 'film-transfer-toolkit' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt-help' ) ); ?>"><?php esc_html_e( 'Learn how to add a tool to any page (block editor and classic editor)', 'film-transfer-toolkit' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt-tools' ) ); ?>"><?php esc_html_e( 'Tools: switch tools on or off', 'film-transfer-toolkit' ); ?></a> · <a href="<?php echo esc_url( FTT_Admin_UX::settings_url( 'email' ) ); ?>"><?php esc_html_e( 'Settings > Email: start sending emails when you are ready', 'film-transfer-toolkit' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Add the new pages to your menu (optional)', 'film-transfer-toolkit' ); ?></a></li>
		</ul>
		<p><a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=ftt' ) ); ?>"><?php esc_html_e( 'Go to the dashboard', 'film-transfer-toolkit' ); ?></a></p>
		<p class="description"><?php esc_html_e( 'You can run this setup again at any time from FTT > Setup wizard. It reuses the pages it finds instead of creating copies.', 'film-transfer-toolkit' ); ?></p>
		<?php
	}

	/* ------------------------------------------------------------------ save */

	private static function back( $step, $type = '', $lines = array() ) {
		if ( $type ) {
			set_transient( 'ftt_setup_msg_' . get_current_user_id(), array( $type, $lines ), 120 );
		}
		wp_safe_redirect( self::url( $step ) );
		exit;
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'film-transfer-toolkit' ), 403 );
		}
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( ! isset( self::steps()[ $step ] ) || 'finish' === $step ) {
			wp_die( esc_html__( 'Unknown setup step.', 'film-transfer-toolkit' ), 400 );
		}
		check_admin_referer( 'ftt_setup_' . $step );
		$next = self::next_step( $step );
		switch ( $step ) {
			case 'modules':
				$in = isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) ? wp_unslash( $_POST['modules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- reduced to 0/1 by sanitize_modules().
				update_option( 'ftt_modules', FTT_Plugin::sanitize_modules( $in ) );
				self::back( $next );
				break;
			case 'pages':
				$r = self::save_pages(
					isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['pages'] ) ) : array(),
					! empty( $_POST['draft'] )
				);
				self::back( $next, $r['errors'] ? 'error' : 'success', array_merge( $r['notes'], $r['errors'] ) );
				break;
			case 'look':
				if ( function_exists( 'ftt_calc_sanitize_options' ) ) {
					$o = ftt_calc_get_options();
					foreach ( array( 'theme', 'accent', 'default_format', 'default_fps' ) as $k ) {
						if ( isset( $_POST[ $k ] ) ) {
							$o[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
						}
					}
					if ( ! empty( $_POST['fields_form'] ) ) {
						foreach ( array_keys( ftt_calc_field_toggles() ) as $k ) {
							$o[ $k ] = empty( $_POST[ $k ] ) ? 0 : 1;
						}
					}
					update_option( 'ftt_calc_options', ftt_calc_sanitize_options( $o ) );
				}
				self::back( $next );
				break;
			case 'prices':
				$choice = isset( $_POST['prices'] ) ? sanitize_key( wp_unslash( $_POST['prices'] ) ) : 'keep';
				if ( 'example' === $choice ) {
					update_option( FTT_Price_Table::OPTION, FTT_Price_Table::preset( 'example' ), false );
					self::back( $next, 'success', array( __( 'EXAMPLE prices loaded.', 'film-transfer-toolkit' ) ) );
				} elseif ( 'import' === $choice ) {
					$raw = '';
					if ( ! empty( $_FILES['price_file']['tmp_name'] ) && is_uploaded_file( $_FILES['price_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- path checked with is_uploaded_file().
						if ( (int) $_FILES['price_file']['size'] > 200000 ) {
							self::back( 'prices', 'error', array( __( 'That file is too large for a price table.', 'film-transfer-toolkit' ) ) );
						}
						$raw = (string) file_get_contents( $_FILES['price_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput -- uploaded temp file, read only, never stored.
					} elseif ( isset( $_POST['price_json'] ) ) {
						$raw = (string) wp_unslash( $_POST['price_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by FTT_Price_Table::save().
					}
					if ( strlen( $raw ) > 200000 ) {
						self::back( 'prices', 'error', array( __( 'That JSON is too large.', 'film-transfer-toolkit' ) ) );
					}
					$t = json_decode( $raw, true );
					if ( ! is_array( $t ) ) {
						self::back( 'prices', 'error', array( __( 'Choose a JSON file or paste the JSON. What was given is not valid JSON.', 'film-transfer-toolkit' ) ) );
					}
					$err = FTT_Price_Table::save( $t );
					if ( $err ) {
						self::back( 'prices', 'error', $err );
					}
					self::back( $next, 'success', array( __( 'Prices imported.', 'film-transfer-toolkit' ) ) );
				}
				self::back( $next );
				break;
			case 'storage':
				if ( function_exists( 'ftt_storage_from_form' ) && isset( $_POST['storage'] ) && is_array( $_POST['storage'] ) ) {
					update_option( 'ftt_storage', ftt_storage_from_form( wp_unslash( $_POST['storage'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- whitelisted by ftt_storage_sanitize_options().
				}
				self::back( $next );
				break;
			case 'email':
				$g                 = FTT_Plugin::general();
				$email             = isset( $_POST['notify_email'] ) ? sanitize_email( wp_unslash( $_POST['notify_email'] ) ) : '';
				$raw               = isset( $_POST['notify_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['notify_email'] ) ) ) : '';
				if ( '' !== $raw && '' === $email ) {
					self::back( 'email', 'error', array( __( 'That email address does not look right. Fix it or leave the box empty.', 'film-transfer-toolkit' ) ) );
				}
				$g['notify_email'] = $email; // mail_mode is deliberately left as it is.
				update_option( 'ftt_general', $g );
				self::back( $next );
				break;
		}
		self::back( $next );
	}

	/**
	 * Apply the Pages step. $choices: shortcode => 'reuse:ID' | 'create' | 'skip'.
	 *
	 * @return array{notes:string[],errors:string[]}
	 */
	public static function save_pages( array $choices, $draft = false ) {
		$embeds = self::page_embeds();
		$pages  = self::pages();
		$rfq    = get_option( 'ftt_rfq', array() );
		$rfq    = is_array( $rfq ) ? $rfq : array();
		$rfq_changed = false;
		if ( ! isset( $rfq['zip_required'] ) ) {
			$rfq['zip_required'] = 1; // Keep the module's default when its sanitizer sees a partial array.
		}
		$notes  = array();
		$errors = array();
		$auto   = has_action( 'transition_post_status', '_wp_auto_add_pages_to_menu' );
		if ( false !== $auto ) {
			remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', $auto ); // Never add to menus.
		}
		foreach ( $embeds as $tag => $e ) {
			$choice = (string) ( $choices[ $tag ] ?? 'skip' );
			$id     = 0;
			if ( 0 === strpos( $choice, 'reuse:' ) ) {
				$id = absint( substr( $choice, 6 ) );
				$p  = get_post( $id );
				if ( ! $p || 'page' !== $p->post_type || ! FTT_Registry::content_has( $p->post_content, $e ) ) {
					/* translators: %s: tool */
					$errors[] = sprintf( __( '%s: that page no longer shows this tool, so it was not used.', 'film-transfer-toolkit' ), $e['label'] );
					continue;
				}
				/* translators: 1: tool, 2: page title */
				$notes[] = sprintf( __( '%1$s: using the existing page "%2$s".', 'film-transfer-toolkit' ), $e['label'], get_the_title( $p ) );
			} elseif ( 'create' === $choice ) {
				$found = FTT_Registry::find_pages( $e, 1 );
				if ( $found ) { // Created meanwhile (double submit, another tab): reuse, never duplicate.
					$id = $found[0]->ID;
					/* translators: 1: tool, 2: page title */
					$notes[] = sprintf( __( '%1$s: a page already shows this tool ("%2$s"), so it was reused instead of making a copy.', 'film-transfer-toolkit' ), $e['label'], get_the_title( $found[0] ) );
				} else {
					$id = wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => $draft ? 'draft' : 'publish',
							'post_title'   => $e['page']['title'],
							'post_name'    => $e['page']['slug'],
							'post_content' => FTT_Registry::block_markup( $e ),
							'post_author'  => get_current_user_id(),
						),
						true
					);
					if ( is_wp_error( $id ) ) {
						/* translators: 1: tool, 2: error */
						$errors[] = sprintf( __( '%1$s: the page could not be created (%2$s).', 'film-transfer-toolkit' ), $e['label'], $id->get_error_message() );
						continue;
					}
					/* translators: 1: tool, 2: address */
					$notes[] = sprintf( __( '%1$s: created the page %2$s.', 'film-transfer-toolkit' ), $e['label'], wp_make_link_relative( get_permalink( $id ) ) );
				}
			} else {
				continue;
			}
			$pages[ $tag ] = (int) $id;
			if ( ! empty( $e['page']['option'] ) ) {
				$rfq_changed                 = $rfq_changed || (int) ( $rfq[ $e['page']['option'] ] ?? 0 ) !== (int) $id;
				$rfq[ $e['page']['option'] ] = (int) $id;
			}
		}
		if ( false !== $auto ) {
			add_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', $auto, 3 );
		}
		update_option( self::PAGES, $pages, false );
		if ( $rfq_changed ) {
			update_option( 'ftt_rfq', $rfq );
		}
		return array( 'notes' => $notes, 'errors' => $errors );
	}
}
