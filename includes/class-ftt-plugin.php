<?php
/**
 * Module loader, shared assets, the Film Transfer Toolkit admin menu, Modules and Mail log pages, WP-CLI.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Plugin {
	/** Module status after boot: 'on', 'off' or a reason string when deferred. */
	public static $status = array();

	/** Module files and defaults (no translation calls: used at plugins_loaded). */
	public static function files() {
		return array(
			'calculator' => array( 1, 'modules/calculator/calculator.php' ),
			'reels'      => array( 1, 'modules/reel-estimator/reel-estimator.php' ),
			'pricing'    => array( 1, 'modules/pricing/pricing.php' ),
			'estimate'   => array( 1, 'modules/estimate/estimate.php' ),
			'clips'      => array( 0, 'modules/clip-selector/clip-selector.php' ),
			'rfq'        => array( 0, 'modules/rfq/rfq.php' ),
		);
	}

	public static function modules() {
		return array(
			'calculator' => array( 'label' => __( 'Film calculator', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_calculator]; also answers [film_calculator] when the standalone Film Calculator plugin is not active.', 'film-transfer-toolkit' ), 'default' => 1, 'file' => 'modules/calculator/calculator.php' ),
			'reels'      => array( 'label' => __( 'Reel size / diameter estimator', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_reel_estimator]: footage and running time from a reel size or a measured diameter.', 'film-transfer-toolkit' ), 'default' => 1, 'file' => 'modules/reel-estimator/reel-estimator.php' ),
			'pricing'    => array( 'label' => __( 'Price table', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_price_table]: shows the admin price table on a page.', 'film-transfer-toolkit' ), 'default' => 1, 'file' => 'modules/pricing/pricing.php' ),
			'estimate'   => array( 'label' => __( 'Multi-reel estimate builder', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_estimate]: customers build an estimate and send a quote request (no payment). Requests are stored privately under Quote requests.', 'film-transfer-toolkit' ), 'default' => 1, 'file' => 'modules/estimate/estimate.php' ),
			'clips'      => array( 'label' => __( 'Clip selector (optional)', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_clip_selector]: In/Out clip selection into the FluentCart cart (ported from FW Clip Cart). Needs FluentCart; stays off while the FW Clip Cart prototype plugin is active.', 'film-transfer-toolkit' ), 'default' => 0, 'file' => 'modules/clip-selector/clip-selector.php' ),
			'rfq'        => array( 'label' => __( 'Quote comparison / RFQ (optional)', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_rfq_providers], [ftt_rfq_form], [ftt_rfq_reply], [ftt_rfq_compare]: customers ask several providers for quotes and compare them (ported from filmtransfer.ai).', 'film-transfer-toolkit' ), 'default' => 0, 'file' => 'modules/rfq/rfq.php' ),
		);
	}

	public static function enabled() {
		$saved = get_option( 'ftt_modules', null );
		$out   = array();
		foreach ( self::files() as $k => $m ) {
			$out[ $k ] = is_array( $saved ) && isset( $saved[ $k ] ) ? (int) (bool) $saved[ $k ] : (int) $m[0];
		}
		return $out;
	}

	public static function general() {
		$g = get_option( 'ftt_general', array() );
		$g = is_array( $g ) ? $g : array();
		return array(
			'mail_mode'    => ( $g['mail_mode'] ?? 'log' ) === 'send' ? 'send' : 'log',
			'notify_email' => sanitize_email( $g['notify_email'] ?? '' ),
		);
	}

	public static function activate() {
		add_option( 'ftt_modules', array_map( function ( $m ) { return (int) $m[0]; }, self::files() ) );
		add_option( 'ftt_general', array( 'mail_mode' => 'log', 'notify_email' => '' ) );
		if ( false === get_option( FTT_Price_Table::OPTION, false ) ) {
			add_option( FTT_Price_Table::OPTION, FTT_Price_Table::preset( 'example' ), '', 'no' );
		}
		if ( false === get_option( 'ftt_calc_options', false ) && is_array( get_option( 'filmcalc_options', null ) ) ) {
			add_option( 'ftt_calc_options', get_option( 'filmcalc_options' ) );
		}
	}

	public static function boot() {
		FTT_Price_Table::init();
		add_action( 'init', array( __CLASS__, 'register_assets' ), 5 );
		$on = self::enabled();
		foreach ( self::files() as $k => $m ) {
			if ( empty( $on[ $k ] ) ) {
				self::$status[ $k ] = 'off';
				continue;
			}
			if ( 'clips' === $k && class_exists( 'FWCC_Clip' ) ) {
				self::$status[ $k ] = 'wait_fwcc';
				continue;
			}
			require_once FTT_DIR . $m[1];
			self::$status[ $k ] = 'on';
		}
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'settings' ) );
			add_action( 'admin_post_ftt_clear_mail_log', array( __CLASS__, 'clear_mail_log' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_css' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( FTT_FILE ), array( __CLASS__, 'action_links' ) );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ftt', 'FTT_CLI' );
		}
	}

	public static function register_assets() {
		wp_register_script( 'ftt-core', FTT_URL . 'core/ftt-core.js', array(), FTT_VERSION, true );
		wp_register_style( 'ftt-front', FTT_URL . 'assets/css/ftt.css', array(), FTT_VERSION );
	}

	public static function admin_css( $hook ) {
		if ( false !== strpos( (string) $hook, 'ftt' ) ) {
			wp_enqueue_style( 'ftt-admin', FTT_URL . 'assets/css/admin.css', array(), FTT_VERSION );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=ftt' ) ) . '">' . esc_html__( 'Modules', 'film-transfer-toolkit' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page( __( 'Film Transfer Toolkit', 'film-transfer-toolkit' ), __( 'Film Transfer Toolkit', 'film-transfer-toolkit' ), 'manage_options', 'ftt', array( __CLASS__, 'render_modules' ), 'dashicons-video-alt2', 82 );
		add_submenu_page( 'ftt', __( 'Modules', 'film-transfer-toolkit' ), __( 'Modules', 'film-transfer-toolkit' ), 'manage_options', 'ftt', array( __CLASS__, 'render_modules' ) );
		$st = self::$status;
		if ( 'on' === ( $st['calculator'] ?? '' ) ) {
			add_submenu_page( 'ftt', __( 'Calculator', 'film-transfer-toolkit' ), __( 'Calculator', 'film-transfer-toolkit' ), 'manage_options', 'ftt-calculator', 'ftt_calc_render_settings_page' );
		}
		if ( 'on' === ( $st['pricing'] ?? '' ) || 'on' === ( $st['estimate'] ?? '' ) ) {
			add_submenu_page( 'ftt', __( 'Price table', 'film-transfer-toolkit' ), __( 'Price table', 'film-transfer-toolkit' ), 'manage_options', 'ftt-prices', array( 'FTT_Price_Table', 'render_admin' ) );
		}
		do_action( 'ftt/admin_menu' );
		add_submenu_page( 'ftt', __( 'Mail log', 'film-transfer-toolkit' ), __( 'Mail log', 'film-transfer-toolkit' ), 'manage_options', 'ftt-mail', array( __CLASS__, 'render_mail_log' ) );
	}

	public static function settings() {
		register_setting( 'ftt_modules', 'ftt_modules', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_modules' ), 'show_in_rest' => false ) );
		register_setting( 'ftt_modules', 'ftt_general', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_general' ), 'show_in_rest' => false ) );
	}

	public static function sanitize_modules( $in ) {
		$out = array();
		foreach ( self::files() as $k => $m ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		return $out;
	}

	public static function sanitize_general( $in ) {
		$in = is_array( $in ) ? $in : array();
		return array(
			'mail_mode'    => ( $in['mail_mode'] ?? '' ) === 'send' ? 'send' : 'log',
			'notify_email' => sanitize_email( $in['notify_email'] ?? '' ),
		);
	}

	public static function render_modules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$on = self::enabled();
		$g  = self::general();
		?>
		<div class="wrap ftt-admin">
			<h1><?php esc_html_e( 'Film Transfer Toolkit', 'film-transfer-toolkit' ); ?> <small>v<?php echo esc_html( FTT_VERSION ); ?></small></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'ftt_modules' ); ?>
				<h2><?php esc_html_e( 'Modules', 'film-transfer-toolkit' ); ?></h2>
				<table class="widefat striped ftt-modules">
					<thead><tr><th><?php esc_html_e( 'On', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Module', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Status', 'film-transfer-toolkit' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( self::modules() as $k => $m ) : $s = self::$status[ $k ] ?? 'off'; ?>
						<tr>
							<td><input type="checkbox" id="ftt-mod-<?php echo esc_attr( $k ); ?>" name="ftt_modules[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $on[ $k ] ) ); ?>></td>
							<td><label for="ftt-mod-<?php echo esc_attr( $k ); ?>"><strong><?php echo esc_html( $m['label'] ); ?></strong></label><br><span class="description"><?php echo esc_html( $m['desc'] ); ?></span></td>
							<td><?php echo 'on' === $s ? '<span class="ftt-pill ftt-pill--on">' . esc_html__( 'Active', 'film-transfer-toolkit' ) . '</span>' : ( 'off' === $s ? '<span class="ftt-pill">' . esc_html__( 'Off', 'film-transfer-toolkit' ) . '</span>' : '<span class="ftt-pill ftt-pill--wait">' . esc_html__( 'Waiting', 'film-transfer-toolkit' ) . '</span> ' . esc_html( 'wait_fwcc' === $s ? __( 'The FW Clip Cart prototype plugin is active and keeps handling [fw_clip_cart]. Deactivate it to switch to this module.', 'film-transfer-toolkit' ) : $s ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'Email', 'film-transfer-toolkit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Mail mode', 'film-transfer-toolkit' ); ?></th><td>
						<label><input type="radio" name="ftt_general[mail_mode]" value="log" <?php checked( 'log', $g['mail_mode'] ); ?>> <?php esc_html_e( 'Log only: nothing is sent; messages appear under Mail log (default, use on staging)', 'film-transfer-toolkit' ); ?></label><br>
						<label><input type="radio" name="ftt_general[mail_mode]" value="send" <?php checked( 'send', $g['mail_mode'] ); ?>> <?php esc_html_e( 'Send with wp_mail()', 'film-transfer-toolkit' ); ?></label>
						<?php if ( defined( 'FTT_MAIL_LOG_ONLY' ) && FTT_MAIL_LOG_ONLY ) : ?><p class="description"><strong><?php esc_html_e( 'FTT_MAIL_LOG_ONLY is set in wp-config.php, so mail is logged only whatever this says.', 'film-transfer-toolkit' ); ?></strong></p><?php endif; ?>
					</td></tr>
					<tr><th><label for="ftt-notify"><?php esc_html_e( 'Notify address for estimate requests', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-notify" type="email" class="regular-text" name="ftt_general[notify_email]" value="<?php echo esc_attr( $g['notify_email'] ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to store requests without any notification email.', 'film-transfer-toolkit' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<h2><?php esc_html_e( 'Shortcodes', 'film-transfer-toolkit' ); ?></h2>
			<p><code>[ftt_calculator]</code> <code>[ftt_reel_estimator]</code> <code>[ftt_price_table]</code> <code>[ftt_estimate]</code> <code>[ftt_clip_selector]</code> <code>[ftt_rfq_providers]</code> <code>[ftt_rfq_form]</code> <code>[ftt_rfq_reply]</code> <code>[ftt_rfq_compare]</code></p>
			<p class="description"><?php esc_html_e( 'The calculation and pricing logic lives in core/ftt-core.js (no WordPress dependency) with a PHP mirror for server-side re-pricing; both run the shared tests in core/test-vectors/.', 'film-transfer-toolkit' ); ?></p>
		</div>
		<?php
	}

	public static function render_mail_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$log = FTT_Mail::entries();
		?>
		<div class="wrap ftt-admin">
			<h1><?php esc_html_e( 'Mail log', 'film-transfer-toolkit' ); ?></h1>
			<p><?php echo esc_html( sprintf( /* translators: %s: mode */ __( 'Current mode: %s. The last 100 toolkit messages are kept here.', 'film-transfer-toolkit' ), 'send' === FTT_Mail::mode() ? __( 'send', 'film-transfer-toolkit' ) : __( 'log only (nothing is sent)', 'film-transfer-toolkit' ) ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ftt_clear_mail_log"><?php wp_nonce_field( 'ftt_clear_mail_log' ); ?><?php submit_button( __( 'Clear log', 'film-transfer-toolkit' ), 'delete', 'submit', false ); ?></form>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Time', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'To', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Subject / body', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Result', 'film-transfer-toolkit' ); ?></th></tr></thead><tbody>
			<?php if ( ! $log ) : ?><tr><td colspan="4"><?php esc_html_e( 'No messages yet.', 'film-transfer-toolkit' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $log as $e ) : ?>
				<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $e['time'] ) ); ?></td><td><?php echo esc_html( $e['to'] ); ?></td>
				<td><details><summary><?php echo esc_html( $e['subject'] ); ?> <span class="description">(<?php echo esc_html( $e['context'] ); ?>)</span></summary><pre class="ftt-mail-body"><?php echo esc_html( $e['body'] ); ?></pre></details></td>
				<td><?php echo esc_html( $e['result'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	public static function clear_mail_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'film-transfer-toolkit' ), 403 );
		}
		check_admin_referer( 'ftt_clear_mail_log' );
		FTT_Mail::clear();
		wp_safe_redirect( admin_url( 'admin.php?page=ftt-mail' ) );
		exit;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Film Transfer Toolkit commands.
	 */
	class FTT_CLI {
		/**
		 * Load a price preset: example or draft.
		 *
		 * ## OPTIONS
		 * <preset>
		 * : example|draft
		 */
		public function prices( $args ) {
			$t = FTT_Price_Table::preset( $args[0] ?? '' );
			if ( ! $t ) {
				WP_CLI::error( 'Unknown preset (use example or draft).' );
			}
			update_option( FTT_Price_Table::OPTION, $t, false );
			WP_CLI::success( 'Loaded ' . $t['status'] . ' prices: ' . $t['label'] );
		}

		/** Print the active price table as JSON. */
		public function export() {
			WP_CLI::line( wp_json_encode( FTT_Price_Table::get(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		}

		/**
		 * Turn modules on or off.
		 *
		 * ## OPTIONS
		 * <on|off>
		 * <module>...
		 */
		public function module( $args ) {
			$state = array_shift( $args );
			$on    = FTT_Plugin::enabled();
			foreach ( $args as $m ) {
				if ( ! isset( $on[ $m ] ) ) {
					WP_CLI::error( "Unknown module $m" );
				}
				$on[ $m ] = 'on' === $state ? 1 : 0;
			}
			update_option( 'ftt_modules', $on );
			WP_CLI::success( wp_json_encode( $on ) );
		}

		/** Show the module status and mail mode. */
		public function status() {
			WP_CLI::line( wp_json_encode( array( 'version' => FTT_VERSION, 'modules' => FTT_Plugin::$status, 'mail_mode' => FTT_Mail::mode(), 'prices' => FTT_Price_Table::get()['label'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		}

		/**
		 * Show the mail log.
		 *
		 * ## OPTIONS
		 * [--clear]
		 * [--full]
		 */
		public function maillog( $args, $assoc ) {
			if ( ! empty( $assoc['clear'] ) ) {
				FTT_Mail::clear();
				WP_CLI::success( 'Cleared.' );
				return;
			}
			foreach ( FTT_Mail::entries() as $e ) {
				WP_CLI::line( gmdate( 'c', $e['time'] ) . ' | ' . $e['to'] . ' | ' . $e['subject'] . ' | ' . $e['result'] );
				if ( ! empty( $assoc['full'] ) ) {
					WP_CLI::line( $e['body'] . "\n----" );
				}
			}
		}
	}
}
