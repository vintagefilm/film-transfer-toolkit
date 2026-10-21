<?php
/**
 * Module loader, shared assets, the Settings and Mail log pages, WP-CLI. The menu lives in includes/admin/.
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
			'storage'    => array( 1, 'modules/storage/storage.php' ),
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
			'storage'    => array( 'label' => __( 'Data rate / storage calculator', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_storage_calculator]: file size per reel and in total for ProRes, H.264/H.265, YouTube, DPX/TIFF and uncompressed deliverables, with a suggested drive size.', 'film-transfer-toolkit' ), 'default' => 1, 'file' => 'modules/storage/storage.php' ),
			'clips'      => array( 'label' => __( 'Clip selector (optional)', 'film-transfer-toolkit' ), 'desc' => __( '[ftt_clip_selector]: In/Out clip selection under any video player (video block, uploaded video, YouTube, Vimeo, FluentPlayer, FV Player). Clips go to the FluentCart cart when FluentCart is active, otherwise visitors send a clip request. Stays off while the FW Clip Cart prototype plugin is active.', 'film-transfer-toolkit' ), 'default' => 0, 'file' => 'modules/clip-selector/clip-selector.php' ),
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
		$fresh = false === get_option( 'ftt_modules', false );
		add_option( 'ftt_modules', array_map( function ( $m ) { return (int) $m[0]; }, self::files() ) );
		add_option( 'ftt_general', array( 'mail_mode' => 'log', 'notify_email' => '' ) );
		if ( false === get_option( FTT_Price_Table::OPTION, false ) ) {
			add_option( FTT_Price_Table::OPTION, FTT_Price_Table::preset( 'example' ), '', 'no' );
		}
		if ( false === get_option( 'ftt_calc_options', false ) && is_array( get_option( 'filmcalc_options', null ) ) ) {
			add_option( 'ftt_calc_options', get_option( 'filmcalc_options' ) );
		}
		require_once FTT_DIR . 'includes/admin/class-ftt-setup.php';
		FTT_Setup::on_activate( $fresh );
	}

	/** True when the FW Clip Cart prototype plugin is active next to the clip selector module. */
	public static $fwcc_shared = false;

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
				// 1.4.1: the FW Clip Cart prototype keeps [fw_clip_cart] and its FluentCart hooks; [ftt_clip_selector] and the block still work.
				self::$fwcc_shared = true;
				define( 'FTT_CLIP_SHARED_FWCC', true );
			}
			require_once FTT_DIR . $m[1];
			self::$status[ $k ] = 'on';
		}
		FTT_Blocks::init();
		FTT_Testbench::init();
		add_action( 'init', array( __CLASS__, 'register_off_placeholders' ), 20 );
		if ( is_admin() ) {
			FTT_Setup::maybe_upgrade();
			FTT_Setup::init();
			FTT_Admin::init();
			add_action( 'admin_init', array( __CLASS__, 'settings' ) );
			add_action( 'admin_post_ftt_clear_mail_log', array( __CLASS__, 'clear_mail_log' ) );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ftt', 'FTT_CLI' );
		}
	}

	/**
	 * Module switched off: its shortcodes (and blocks) never show as raw text. Visitors see nothing; editors see a
	 * short note. The clip selector also shows a preview-only copy to editors on the test bench and in previews.
	 */
	public static function register_off_placeholders() {
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			if ( 'on' === ( self::$status[ $e['module'] ] ?? '' ) ) {
				continue;
			}
			foreach ( array_merge( array( $tag ), $e['aliases'] ?? array() ) as $t ) {
				if ( ! shortcode_exists( $t ) ) {
					add_shortcode(
						$t,
						function ( $atts ) use ( $tag, $e ) {
							return FTT_Plugin::off_placeholder( $tag, $e, $atts );
						}
					);
				}
			}
		}
	}

	public static function off_placeholder( $tag, $e, $atts ) {
		if ( 'ftt_clip_selector' === $tag && FTT_Testbench::clip_preview_allowed() ) {
			return self::clip_preview( $atts );
		}
		$id = (int) get_the_ID();
		if ( ! ( $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' ) ) ) {
			return '';
		}
		wp_enqueue_style( 'ftt-base' );
		$tools = FTT_Registry::tools();
		$name  = $tools[ $e['module'] ]['label'] ?? $e['label'];
		return '<div class="ftt-off-note" data-ftt-off="' . esc_attr( $tag ) . '" role="note"><strong>' . esc_html__( 'FTT:', 'film-transfer-toolkit' ) . '</strong> '
			/* translators: %s: tool name */
			. esc_html( sprintf( __( '%s is switched off, so this spot is empty for visitors. Turn it on under FTT > Settings > Modules. Only editors see this note.', 'film-transfer-toolkit' ), $name ) )
			. '</div>';
	}

	public static function clip_preview( $atts ) {
		if ( ! class_exists( 'FTT_Clip_Shortcode' ) ) {
			// Load the module's classes only (no shortcodes, REST routes or FluentCart hooks are registered).
			if ( ! defined( 'FTT_CLIP_DIR' ) ) {
				define( 'FTT_CLIP_DIR', FTT_DIR . 'modules/clip-selector/' );
				define( 'FTT_CLIP_URL', FTT_URL . 'modules/clip-selector/' );
			}
			foreach ( array( 'class-rate-card.php', 'class-cutlist.php', 'class-clip.php', 'class-rest.php', 'class-clip-settings.php', 'class-clip-request.php', 'class-shortcode.php' ) as $f ) {
				require_once FTT_CLIP_DIR . $f;
			}
		}
		FTT_Clip_Shortcode::$demo = true;
		$html = FTT_Clip_Shortcode::render( is_array( $atts ) ? $atts : array() );
		FTT_Clip_Shortcode::$demo = false;
		return $html;
	}

	public static function register_assets() {
		wp_register_script( 'ftt-core', FTT_URL . 'core/ftt-core.js', array(), FTT_VERSION, true );
		wp_register_style( 'ftt-base', FTT_URL . 'assets/css/ftt-base.css', array(), FTT_VERSION );
		wp_register_style( 'ftt-front', FTT_URL . 'assets/css/ftt.css', array( 'ftt-base' ), FTT_VERSION );
	}

	public static function settings() {
		register_setting( 'ftt_modules', 'ftt_modules', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_modules' ), 'show_in_rest' => false ) );
		register_setting( 'ftt_general', 'ftt_general', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_general' ), 'show_in_rest' => false ) );
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

	/** 1.4 Settings page, kept for compatibility: switching tools on/off now lives under FTT > Tools. */
	public static function render_modules() {
		FTT_Admin_UX::render_tools();
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
		wp_safe_redirect( FTT_Admin_UX::settings_url( 'email' ) . '#ftt-mail-log' );
		exit;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * FTT commands.
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

		/**
		 * Create or rebuild the draft test bench page, or check it.
		 *
		 * ## OPTIONS
		 *
		 * [--check]
		 * : Render the bench and check every tool instead of rebuilding it. Exits 1 on any failure.
		 *
		 * [--video=<url>]
		 * : HTML5 sample video URL (default: the first video already in the Media Library; nothing is uploaded).
		 *
		 * [--youtube=<url>]
		 * : YouTube watch URL for the embed sample.
		 *
		 * [--format=<format>]
		 * : table or json (for --check).
		 */
		public function testbench( $args, $assoc ) {
			if ( isset( $assoc['check'] ) ) {
				$res  = FTT_Testbench::check();
				$fail = 0;
				foreach ( $res as $r ) {
					$fail += $r[1] ? 0 : 1;
				}
				if ( 'json' === ( $assoc['format'] ?? '' ) ) {
					WP_CLI::line( wp_json_encode( array( 'id' => FTT_Testbench::page_id(), 'failed' => $fail, 'checks' => $res ) ) );
				} else {
					foreach ( $res as $r ) {
						WP_CLI::line( ( $r[1] ? 'PASS ' : 'FAIL ' ) . $r[0] . ( '' !== $r[2] ? ' (' . $r[2] . ')' : '' ) );
					}
				}
				if ( $fail ) {
					WP_CLI::error( $fail . ' check(s) failed.' );
				}
				WP_CLI::success( count( $res ) . ' checks passed on page ' . FTT_Testbench::page_id() . '.' );
				return;
			}
			$id = FTT_Testbench::build( isset( $assoc['video'] ) ? esc_url_raw( $assoc['video'] ) : null, isset( $assoc['youtube'] ) ? esc_url_raw( $assoc['youtube'] ) : '' );
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( $id->get_error_message() );
			}
			WP_CLI::success( 'Test bench page ' . $id . ' (draft). Preview: ' . add_query_arg( array( 'page_id' => $id, 'preview' => 'true' ), home_url( '/' ) ) );
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
