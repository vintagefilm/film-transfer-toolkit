<?php
/**
 * Multi-reel estimate builder: [ftt_estimate title=""].
 * The browser previews prices with FTTCore; the quote request endpoint re-prices everything with
 * FTT_Pricing (the PHP mirror) and stores the server figures in a private ftt_estimate post.
 * No payment is taken. Email goes through FTT_Mail (log-only by default).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Estimate {
	const CPT = 'ftt_estimate';
	const NS  = 'ftt/v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'init', array( __CLASS__, 'assets' ), 11 );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_status' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function register() {
		$admin = 'manage_options';
		register_post_type( self::CPT, array(
			'labels'              => array( 'name' => __( 'Quote requests', 'film-transfer-toolkit' ), 'singular_name' => __( 'Quote request', 'film-transfer-toolkit' ), 'menu_name' => __( 'Quote requests', 'film-transfer-toolkit' ), 'edit_item' => __( 'Quote request', 'film-transfer-toolkit' ) ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'ftt',
			'show_in_rest'        => false,
			'supports'            => array( 'title' ),
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post' => $admin, 'read_post' => $admin, 'delete_post' => $admin, 'edit_posts' => $admin, 'edit_others_posts' => $admin,
				'delete_posts' => $admin, 'delete_others_posts' => $admin, 'delete_private_posts' => $admin, 'edit_private_posts' => $admin,
				'read_private_posts' => $admin, 'publish_posts' => 'do_not_allow', 'create_posts' => 'do_not_allow', 'edit_published_posts' => $admin, 'delete_published_posts' => $admin,
			),
		) );
		add_shortcode( 'ftt_estimate', array( __CLASS__, 'shortcode' ) );
	}

	public static function assets() {
		wp_register_script( 'ftt-estimate', FTT_URL . 'modules/estimate/estimate.js', array( 'ftt-core' ), FTT_VERSION, true );
	}

	/** Public part of the price table plus config for the builder. */
	private static function client_config() {
		return array(
			'table' => FTT_Price_Table::get(),
			'rest'  => esc_url_raw( rest_url( self::NS . '/estimate' ) ),
		);
	}

	public static function shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'title' => '' ), $atts, 'ftt_estimate' );
		$title = '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : __( 'Build your film transfer estimate', 'film-transfer-toolkit' );
		$t     = FTT_Price_Table::get();
		wp_enqueue_style( 'ftt-front' );
		wp_enqueue_script( 'ftt-estimate' );
		wp_add_inline_script( 'ftt-estimate', 'window.FTT_EST = ' . wp_json_encode( self::client_config() ) . ';', 'before' );
		static $n = 0;
		$n++;
		$u = 'ftt-est-' . $n;
		ob_start();
		?>
		<div class="ftt-tool ftt-box ftt-est" data-ftt-estimate>
			<h3 class="ftt-box__title"><?php echo esc_html( $title ); ?></h3>
			<?php echo FTT_Price_Table::banner( $t ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in banner(). ?>
			<p class="ftt-box__sub"><?php esc_html_e( 'Add a line for each group of reels. If you know the footage, type it in; otherwise pick the reel size.', 'film-transfer-toolkit' ); ?></p>
			<div class="ftt-est__lines" data-e="lines"></div>
			<p><button type="button" class="ftt-btn ftt-btn--ghost" data-e="add"><?php esc_html_e( '+ Add another group of reels', 'film-transfer-toolkit' ); ?></button></p>
			<fieldset class="ftt-est__order" data-e="order-wrap"><legend><?php esc_html_e( 'For the whole order', 'film-transfer-toolkit' ); ?></legend><div data-e="order"></div></fieldset>
			<div class="ftt-est__summary" data-e="summary" aria-live="polite"></div>
			<form class="ftt-est__form" data-e="form" novalidate>
				<h4><?php esc_html_e( 'Request a quote', 'film-transfer-toolkit' ); ?></h4>
				<p class="ftt-note"><?php esc_html_e( 'No payment now. We will confirm the price after we see your film.', 'film-transfer-toolkit' ); ?></p>
				<div class="ftt-grid">
					<label class="ftt-field"><span><?php esc_html_e( 'Name', 'film-transfer-toolkit' ); ?> *</span><input type="text" name="name" maxlength="100" autocomplete="name" required></label>
					<label class="ftt-field"><span><?php esc_html_e( 'Email', 'film-transfer-toolkit' ); ?> *</span><input type="email" name="email" maxlength="190" autocomplete="email" required></label>
					<label class="ftt-field"><span><?php esc_html_e( 'Phone (optional)', 'film-transfer-toolkit' ); ?></span><input type="tel" name="phone" maxlength="30" autocomplete="tel"></label>
				</div>
				<label class="ftt-field"><span><?php esc_html_e( 'Anything we should know? (optional)', 'film-transfer-toolkit' ); ?></span><textarea name="notes" rows="3" maxlength="2000"></textarea></label>
				<div class="ftt-hp" aria-hidden="true"><label><?php esc_html_e( 'Leave this empty', 'film-transfer-toolkit' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<label class="ftt-check"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I understand this is an estimate and the final price is confirmed after inspection. Use my details only to reply about this request.', 'film-transfer-toolkit' ); ?></label>
				<p><button type="submit" class="ftt-btn" data-e="send"><?php esc_html_e( 'Send quote request', 'film-transfer-toolkit' ); ?></button></p>
				<p class="ftt-msg" data-e="msg" role="status" aria-live="polite"></p>
			</form>
			<noscript><p><?php esc_html_e( 'The estimate builder requires JavaScript.', 'film-transfer-toolkit' ); ?></p></noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -------------------------------------------------------------- REST */

	public static function routes() {
		register_rest_route( self::NS, '/estimate/token', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'token' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/estimate/preview', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'preview' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/estimate/request', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'request' ), 'permission_callback' => '__return_true' ) );
	}

	/** Fresh nonce so cached pages still work. */
	public static function token() {
		return FTT_Util::nocache_rest( array( 'nonce' => wp_create_nonce( 'ftt_estimate' ) ) );
	}

	/** Only the fields the engine understands, bounded. */
	public static function clean_request( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$lines = array();
		foreach ( array_slice( (array) ( $raw['lines'] ?? array() ), 0, FTT_Pricing::LIMITS['lines'] + 1 ) as $l ) {
			if ( ! is_array( $l ) ) { $lines[] = array(); continue; }
			$x = array( 'format' => FTT_Util::text( $l['format'] ?? '', 32 ), 'service' => FTT_Util::text( $l['service'] ?? '', 32 ) );
			foreach ( array( 'reels', 'feet' ) as $k ) {
				if ( isset( $l[ $k ] ) && '' !== $l[ $k ] && null !== $l[ $k ] ) { $x[ $k ] = is_numeric( $l[ $k ] ) ? 0 + $l[ $k ] : -1; }
			}
			if ( isset( $l['size'] ) && '' !== (string) $l['size'] ) { $x['size'] = FTT_Util::text( $l['size'], 32 ); }
			$x['addons'] = array_map( function ( $a ) { return FTT_Util::text( $a, 32 ); }, array_slice( (array) ( $l['addons'] ?? array() ), 0, FTT_Pricing::LIMITS['addons'] ) );
			$lines[]     = $x;
		}
		$order = array_map( function ( $a ) {
			if ( is_array( $a ) ) { // { id, qty } for a per-custom-unit add-on
				$q = $a['qty'] ?? 1;
				return array( 'id' => FTT_Util::text( $a['id'] ?? '', 32 ), 'qty' => is_int( $q ) || ( is_string( $q ) && preg_match( '/^[0-9]{1,4}$/', $q ) ) ? (int) $q : ( is_numeric( $q ) ? 0 + $q : -1 ) );
			}
			return FTT_Util::text( $a, 32 );
		}, array_slice( (array) ( $raw['order_addons'] ?? array() ), 0, FTT_Pricing::LIMITS['addons'] ) );
		return array( 'lines' => $lines, 'order_addons' => $order );
	}

	private static function body( WP_REST_Request $req ) {
		if ( strlen( (string) $req->get_body() ) > 32768 ) {
			return new WP_Error( 'ftt_too_big', __( 'That request is too large.', 'film-transfer-toolkit' ), array( 'status' => 413 ) );
		}
		$p = $req->get_json_params();
		return is_array( $p ) ? $p : array();
	}

	public static function preview( WP_REST_Request $req ) {
		if ( ! FTT_Util::rate( 'est_prev|' . FTT_Util::ip(), 120, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'ftt_rate', __( 'Too many requests. Please wait a minute.', 'film-transfer-toolkit' ), array( 'status' => 429 ) );
		}
		$p = self::body( $req );
		if ( is_wp_error( $p ) ) { return $p; }
		return FTT_Util::nocache_rest( FTT_Pricing::quote( FTT_Price_Table::get(), self::clean_request( $p['request'] ?? array() ) ) );
	}

	public static function request( WP_REST_Request $req ) {
		$p = self::body( $req );
		if ( is_wp_error( $p ) ) { return $p; }
		$fail = function ( $code, $msg, $status = 400, $extra = array() ) { return new WP_Error( $code, $msg, array_merge( array( 'status' => $status ), $extra ) ); };
		if ( ! wp_verify_nonce( (string) $req->get_header( 'x_ftt_nonce' ), 'ftt_estimate' ) ) {
			return $fail( 'ftt_nonce', __( 'Your session expired. Please try again.', 'film-transfer-toolkit' ), 403 );
		}
		if ( '' !== trim( (string) ( $p['website'] ?? '' ) ) ) {
			return $fail( 'ftt_hp', __( 'Something went wrong. Please try again.', 'film-transfer-toolkit' ) );
		}
		$c     = (array) ( $p['contact'] ?? array() );
		$name  = FTT_Util::text( $c['name'] ?? '', 100 );
		$email = sanitize_email( (string) ( $c['email'] ?? '' ) );
		$phone = FTT_Util::text( $c['phone'] ?? '', 30 );
		$notes = FTT_Util::textarea( $c['notes'] ?? '', 2000 );
		if ( '' === $name ) { return $fail( 'ftt_name', __( 'Please enter your name.', 'film-transfer-toolkit' ) ); }
		if ( ! is_email( $email ) ) { return $fail( 'ftt_email', __( 'Please enter a valid email address.', 'film-transfer-toolkit' ) ); }
		if ( empty( $p['consent'] ) ) { return $fail( 'ftt_consent', __( 'Please tick the box to confirm you understand this is an estimate.', 'film-transfer-toolkit' ) ); }
		if ( ! FTT_Util::rate( 'est_ip|' . FTT_Util::ip(), 5, HOUR_IN_SECONDS ) || ! FTT_Util::rate( 'est_em|' . strtolower( $email ), 3, DAY_IN_SECONDS ) ) {
			return $fail( 'ftt_rate', __( 'Too many requests. Please try again later or contact us directly.', 'film-transfer-toolkit' ), 429 );
		}
		$table = FTT_Price_Table::get();
		$rq    = self::clean_request( $p['request'] ?? array() );
		$q     = FTT_Pricing::quote( $table, $rq );
		if ( ! $q['ok'] ) {
			return $fail( 'ftt_invalid', __( 'Please fix the highlighted lines and try again.', 'film-transfer-toolkit' ), 400, array( 'errors' => $q['errors'] ) );
		}
		$client   = isset( $p['client_total_cents'] ) && is_numeric( $p['client_total_cents'] ) ? (int) $p['client_total_cents'] : null;
		$repriced = null !== $client && $client !== $q['totals']['total_cents'];
		$id       = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'private', 'post_title' => sprintf( 'Estimate request – %s – %s', $name, FTT_Pricing::format_money( $q['totals']['total_cents'], $q['currency'] ) ) ), true );
		if ( is_wp_error( $id ) ) {
			return $fail( 'ftt_store', __( 'Could not save your request. Please try again.', 'film-transfer-toolkit' ), 500 );
		}
		$meta = array( 'request' => $rq, 'quote' => $q, 'contact' => compact( 'name', 'email', 'phone', 'notes' ), 'client_total_cents' => $client, 'repriced' => $repriced, 'state' => 'new', 'ip_hash' => FTT_Util::ip_hash(), 'prices' => array( 'status' => $table['status'], 'label' => $table['label'], 'as_of' => $table['as_of'] ) );
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_ftt_' . $k, $v );
		}
		$ref = 'E-' . $id;
		$g   = FTT_Plugin::general();
		$to  = $g['notify_email'];
		FTT_Mail::send( $to, sprintf( '[%s] New estimate request %s (%s)', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $ref, FTT_Pricing::format_money( $q['totals']['total_cents'], $q['currency'] ) ), self::text_summary( $id ) . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ), 'estimate:' . $ref );
		return FTT_Util::nocache_rest( array( 'ok' => true, 'reference' => $ref, 'total_cents' => $q['totals']['total_cents'], 'total' => FTT_Pricing::format_money( $q['totals']['total_cents'], $q['currency'] ), 'repriced' => $repriced, 'quote' => $q ), 201 );
	}

	public static function text_summary( $id ) {
		$q   = get_post_meta( $id, '_ftt_quote', true );
		$c   = get_post_meta( $id, '_ftt_contact', true );
		$pr  = get_post_meta( $id, '_ftt_prices', true );
		$cur = $q['currency'] ?? 'USD';
		$out = "Reference: E-$id\nName: {$c['name']}\nEmail: {$c['email']}\nPhone: {$c['phone']}\n\n";
		foreach ( $q['lines'] as $l ) {
			$out .= sprintf( "- %s, %s: %d reel(s)%s, %s ft billed (%s) = %s\n", $l['format_label'], $l['service_label'], $l['reels'], $l['size'] ? ' x ' . $l['size'] . ' in' : '', $l['billable_feet'], $l['method'] === 'reel' ? 'per reel' : 'per foot', FTT_Pricing::format_money( $l['line_cents'], $cur ) );
			foreach ( $l['addons'] as $a ) { $out .= sprintf( "    + %s %s\n", $a['label'], FTT_Pricing::format_money( $a['cents'], $cur ) ); }
		}
		$t    = $q['totals'];
		$out .= sprintf( "\nSubtotal %s", FTT_Pricing::format_money( $t['lines_cents'], $cur ) );
		if ( $t['discount_cents'] ) { $out .= sprintf( "\nVolume discount %d%% -%s", $t['discount_pct'], FTT_Pricing::format_money( $t['discount_cents'], $cur ) ); }
		foreach ( $t['order_addons'] as $a ) { $out .= sprintf( "\n%s %s", FTT_Pricing::order_addon_text( $a, $cur ), FTT_Pricing::format_money( $a['cents'], $cur ) ); }
		if ( $t['setup_cents'] ) { $out .= sprintf( "\nSetup %s", FTT_Pricing::format_money( $t['setup_cents'], $cur ) ); }
		if ( $t['minimum_adjust_cents'] ) { $out .= sprintf( "\nOrder minimum adjustment %s", FTT_Pricing::format_money( $t['minimum_adjust_cents'], $cur ) ); }
		$out .= sprintf( "\nESTIMATE TOTAL %s (%s, prices as of %s)\n\nNotes:\n%s\n", FTT_Pricing::format_money( $t['total_cents'], $cur ), $pr['label'] ?? '', $pr['as_of'] ?? '', $c['notes'] );
		return $out;
	}

	/* ------------------------------------------------------------ admin UI */

	public static function meta_box() {
		remove_meta_box( 'submitdiv', self::CPT, 'side' );
		add_meta_box( 'ftt_est_details', __( 'Request details', 'film-transfer-toolkit' ), array( __CLASS__, 'render_box' ), self::CPT, 'normal', 'high' );
		add_meta_box( 'ftt_est_state', __( 'Status', 'film-transfer-toolkit' ), array( __CLASS__, 'render_state' ), self::CPT, 'side', 'high' );
	}

	public static function render_box( $post ) {
		$rep = get_post_meta( $post->ID, '_ftt_repriced', true );
		echo '<pre class="ftt-mail-body">' . esc_html( self::text_summary( $post->ID ) ) . '</pre>';
		if ( $rep ) {
			echo '<p><strong>' . esc_html__( 'The browser total differed from the server total; the server total above is the one stored.', 'film-transfer-toolkit' ) . '</strong> ' . esc_html( sprintf( 'Browser sent %s.', FTT_Pricing::format_money( (int) get_post_meta( $post->ID, '_ftt_client_total_cents', true ) ) ) ) . '</p>';
		}
	}

	public static function states() {
		return array( 'new' => __( 'New', 'film-transfer-toolkit' ), 'contacted' => __( 'Contacted', 'film-transfer-toolkit' ), 'quoted' => __( 'Quoted', 'film-transfer-toolkit' ), 'won' => __( 'Order received', 'film-transfer-toolkit' ), 'closed' => __( 'Closed', 'film-transfer-toolkit' ) );
	}

	public static function render_state( $post ) {
		wp_nonce_field( 'ftt_est_state', 'ftt_est_state_nonce' );
		$cur = get_post_meta( $post->ID, '_ftt_state', true );
		echo '<select name="ftt_state">';
		foreach ( self::states() as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>'; }
		echo '</select> ';
		submit_button( __( 'Update', 'film-transfer-toolkit' ), 'primary', 'save', false );
	}

	public static function save_status( $id, $post ) {
		if ( ! isset( $_POST['ftt_est_state_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ftt_est_state_nonce'] ) ), 'ftt_est_state' ) || ! current_user_can( 'manage_options' ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$s = isset( $_POST['ftt_state'] ) ? sanitize_key( wp_unslash( $_POST['ftt_state'] ) ) : '';
		if ( isset( self::states()[ $s ] ) ) {
			update_post_meta( $id, '_ftt_state', $s );
		}
		if ( 'private' !== $post->post_status ) {
			remove_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_status' ), 10 );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		}
	}

	public static function columns( $cols ) {
		return array( 'cb' => $cols['cb'] ?? '', 'title' => __( 'Request', 'film-transfer-toolkit' ), 'ftt_email' => __( 'Email', 'film-transfer-toolkit' ), 'ftt_state' => __( 'Status', 'film-transfer-toolkit' ), 'date' => $cols['date'] ?? __( 'Date', 'film-transfer-toolkit' ) );
	}

	public static function column( $col, $id ) {
		if ( 'ftt_email' === $col ) { $c = get_post_meta( $id, '_ftt_contact', true ); echo esc_html( $c['email'] ?? '' ); }
		if ( 'ftt_state' === $col ) { $s = get_post_meta( $id, '_ftt_state', true ); echo esc_html( self::states()[ $s ] ?? $s ); }
	}
}
FTT_Estimate::init();
