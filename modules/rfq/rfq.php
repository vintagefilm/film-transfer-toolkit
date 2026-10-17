<?php
/**
 * Quote comparison (RFQ) module, ported from the filmtransfer.ai ft-rfq 0.1.0 plugin.
 *
 * Flow: customer picks up to N providers -> fills [ftt_rfq_form] -> confirms by email (button, not a bare link)
 * -> each provider gets a private reply link ([ftt_rfq_reply]) -> customer compares quotes on a private page
 * ([ftt_rfq_compare]) and chooses whom to share contact details with.
 * Providers are an ftt_provider post type by default, or any existing post type (filmtransfer.ai uses "listing").
 * All email goes through FTT_Mail (log-only by default). Tokens are stored only as keyed hashes.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_RFQ {
	const CPT_REQ   = 'ftt_rfq';
	const CPT_QUOTE = 'ftt_rfq_quote';
	const CPT_PROV  = 'ftt_provider';
	const OPT       = 'ftt_rfq';

	public static function formats() {
		return array( '8' => '8mm (Regular 8)', 's8' => 'Super 8', '16' => '16mm', '35' => '35mm', '9.5' => '9.5mm', 'other' => 'Other / not sure' );
	}

	public static function choices() {
		return array(
			'res'      => array( 'label' => 'Scan resolution', 'opts' => array( 'No preference', 'HD (1080p)', '2K', '4K', 'Higher than 4K' ) ),
			'audio'    => array( 'label' => 'Audio', 'opts' => array( 'Silent film / not needed', 'Capture sound (magnetic or optical)', 'Not sure' ) ),
			'clean'    => array( 'label' => 'Cleaning / repair', 'opts' => array( 'Standard handling', 'Cleaning', 'Repairs needed (splices, damage)', 'Not sure' ) ),
			'delivery' => array( 'label' => 'Delivery', 'opts' => array( 'Download link', 'USB drive', 'Hard drive', 'DVD/Blu-ray', 'No preference' ) ),
		);
	}

	public static function opts() {
		$o = get_option( self::OPT, array() );
		$o = is_array( $o ) ? $o : array();
		return array(
			'provider_type' => sanitize_key( $o['provider_type'] ?? self::CPT_PROV ) ?: self::CPT_PROV,
			'email_meta'    => sanitize_key( $o['email_meta'] ?? 'rfq_email' ) ?: 'rfq_email',
			'max'           => max( 1, min( 10, (int) ( $o['max'] ?? 5 ) ) ),
			'brand'         => FTT_Util::text( $o['brand'] ?? '', 80 ),
			'zip_required'  => isset( $o['zip_required'] ) ? (int) (bool) $o['zip_required'] : 1,
			'form_page'     => absint( $o['form_page'] ?? 0 ),
			'reply_page'    => absint( $o['reply_page'] ?? 0 ),
			'compare_page'  => absint( $o['compare_page'] ?? 0 ),
			'providers_page'=> absint( $o['providers_page'] ?? 0 ),
			'claim_url'     => esc_url_raw( $o['claim_url'] ?? '' ),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'assets' ), 11 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'ftt/admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'add_meta_boxes_' . self::CPT_PROV, array( __CLASS__, 'provider_box' ) );
		add_action( 'save_post_' . self::CPT_PROV, array( __CLASS__, 'save_provider' ) );
		add_filter( 'ft_card_extra', array( __CLASS__, 'card_extra' ), 10, 2 ); // filmtransfer.ai directory cards.
	}

	private static function brand() {
		$b = self::opts()['brand'];
		return '' !== $b ? $b : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	public static function register() {
		$admin = 'manage_options';
		$caps  = array( 'edit_post' => $admin, 'read_post' => $admin, 'delete_post' => $admin, 'edit_posts' => $admin, 'edit_others_posts' => $admin, 'publish_posts' => $admin, 'read_private_posts' => $admin, 'delete_posts' => $admin, 'delete_others_posts' => $admin, 'delete_published_posts' => $admin, 'edit_published_posts' => $admin, 'create_posts' => $admin );
		register_post_type( self::CPT_REQ, array( 'label' => 'RFQ requests', 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'supports' => array( 'title' ), 'map_meta_cap' => false, 'capabilities' => $caps ) );
		register_post_type( self::CPT_QUOTE, array( 'label' => 'RFQ quotes', 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'supports' => array( 'title' ), 'map_meta_cap' => false, 'capabilities' => $caps ) );
		if ( self::CPT_PROV === self::opts()['provider_type'] ) {
			register_post_type( self::CPT_PROV, array(
				'labels' => array( 'name' => __( 'Quote providers', 'film-transfer-toolkit' ), 'singular_name' => __( 'Quote provider', 'film-transfer-toolkit' ), 'add_new_item' => __( 'Add provider', 'film-transfer-toolkit' ), 'menu_name' => __( 'Quote providers', 'film-transfer-toolkit' ) ),
				'public' => false, 'show_ui' => true, 'show_in_menu' => 'ftt', 'show_in_rest' => false, 'supports' => array( 'title' ), 'map_meta_cap' => false, 'capabilities' => $caps,
			) );
		}
		add_shortcode( 'ftt_rfq_providers', array( __CLASS__, 'sc_providers' ) );
		add_shortcode( 'ftt_rfq_form', array( __CLASS__, 'sc_form' ) );
		add_shortcode( 'ftt_rfq_reply', array( __CLASS__, 'sc_reply' ) );
		add_shortcode( 'ftt_rfq_compare', array( __CLASS__, 'sc_compare' ) );
	}

	public static function assets() {
		wp_register_script( 'ftt-rfq', FTT_URL . 'modules/rfq/rfq.js', array(), FTT_VERSION, true );
		wp_register_style( 'ftt-rfq', FTT_URL . 'modules/rfq/rfq.css', array( 'ftt-front' ), FTT_VERSION );
	}

	private static function page_url( $key, $slug, $args = array() ) {
		$id  = self::opts()[ $key ];
		$url = $id && get_post( $id ) ? get_permalink( $id ) : home_url( '/' . $slug . '/' );
		return add_query_arg( $args, $url );
	}

	public static function robots( $r ) {
		$o   = self::opts();
		$ids = array_filter( array( $o['form_page'], $o['reply_page'], $o['compare_page'] ) );
		if ( ( $ids && is_page( $ids ) ) || is_page( array( 'request-quotes', 'rfq-reply', 'rfq-compare' ) ) ) {
			$r['noindex']  = true;
			$r['nofollow'] = true;
		}
		return $r;
	}

	/* ---------------------------------------------------------- providers */

	public static function provider_email( $pid ) {
		return sanitize_email( (string) get_post_meta( $pid, self::opts()['email_meta'], true ) );
	}

	public static function opted_out( $pid ) {
		return (bool) get_post_meta( $pid, '_ft_rfq_optout', true );
	}

	public static function valid_provider( $pid ) {
		return $pid && get_post_type( $pid ) === self::opts()['provider_type'] && 'publish' === get_post_status( $pid ) && ! self::opted_out( $pid );
	}

	/** Providers on a CPT that is not public are still published posts; link them only when they have a public permalink. */
	private static function provider_link( $pid ) {
		$pt = get_post_type_object( get_post_type( $pid ) );
		if ( $pt && $pt->public ) {
			return '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a>';
		}
		$site = esc_url( (string) get_post_meta( $pid, '_ftt_website', true ) );
		return $site ? '<a href="' . $site . '" rel="nofollow noopener" target="_blank">' . esc_html( get_the_title( $pid ) ) . '</a>' : esc_html( get_the_title( $pid ) );
	}

	public static function card_extra( $h, $id ) {
		if ( get_post_type( $id ) !== self::opts()['provider_type'] ) {
			return $h;
		}
		wp_enqueue_script( 'ftt-rfq' );
		if ( self::opted_out( $id ) ) {
			return $h . '<p class="ft-small ft-muted">' . esc_html__( 'Not accepting quote requests', 'film-transfer-toolkit' ) . '</p>';
		}
		return $h . '<label class="ftt-rfq-pick"><input type="checkbox" class="ftt-rfq-cb" value="' . (int) $id . '"> ' . esc_html__( 'Request a quote', 'film-transfer-toolkit' ) . '</label>';
	}

	public static function sc_providers() {
		$o    = self::opts();
		$list = get_posts( array( 'post_type' => $o['provider_type'], 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		wp_enqueue_style( 'ftt-rfq' );
		wp_enqueue_script( 'ftt-rfq' );
		$out = '<div class="ftt-box ftt-rfq-providers" data-ftt-rfq-picker data-max="' . esc_attr( $o['max'] ) . '" data-go="' . esc_url( self::page_url( 'form_page', 'request-quotes' ) ) . '">';
		$out .= '<p>' . esc_html( sprintf( /* translators: %d: max */ __( 'Tick up to %d providers, then press Request quotes.', 'film-transfer-toolkit' ), $o['max'] ) ) . '</p><ul class="ftt-rfq-list">';
		$n = 0;
		foreach ( $list as $p ) {
			if ( self::opted_out( $p->ID ) ) { continue; }
			$n++;
			$out .= '<li><label class="ftt-check"><input type="checkbox" class="ftt-rfq-cb" value="' . (int) $p->ID . '"> ' . esc_html( get_the_title( $p ) ) . '</label></li>';
		}
		if ( ! $n ) { $out .= '<li>' . esc_html__( 'No providers are accepting quote requests yet.', 'film-transfer-toolkit' ) . '</li>'; }
		$out .= '</ul><div class="ftt-rfq-bar" data-rfq-bar hidden><span><span data-rfq-count>0</span>/' . esc_html( $o['max'] ) . ' ' . esc_html__( 'selected', 'film-transfer-toolkit' ) . '</span> <a class="ftt-btn" data-rfq-go href="#">' . esc_html__( 'Request quotes', 'film-transfer-toolkit' ) . '</a></div></div>';
		return $out;
	}

	/* ------------------------------------------------------------- tokens */

	private static function find_by( $key, $tok, $type ) {
		if ( ! is_string( $tok ) || ! preg_match( '/^[a-f0-9]{48}$/', $tok ) ) {
			return 0;
		}
		$p = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => FTT_Util::token_hash( $tok ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return $p ? (int) $p[0] : 0;
	}

	private static function m( $id, $k ) {
		return get_post_meta( $id, '_ftt_rfq_' . $k, true );
	}

	/* --------------------------------------------------------- customer form */

	private static function get( $k ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- token links.
	}

	public static function sc_form() {
		wp_enqueue_style( 'ftt-rfq' );
		$o = self::opts();
		if ( '' !== self::get( 'ftt_rfq_verify' ) ) {
			return self::verify( self::get( 'ftt_rfq_verify' ) );
		}
		$raw  = isset( $_REQUEST['labs'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['labs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- list of ids, validated below.
		$labs = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ), array( __CLASS__, 'valid_provider' ) ) ) );
		$labs = array_slice( $labs, 0, $o['max'] );
		$msg  = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['ftt_rfq_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- verified in handle_submit().
			$r = self::handle_submit( $labs );
			if ( true === $r ) {
				return '<div class="ftt-box ftt-cta"><strong>' . esc_html__( 'Check your email.', 'film-transfer-toolkit' ) . '</strong> ' . esc_html__( 'We sent a confirmation link. Your request goes to the providers only after you confirm.', 'film-transfer-toolkit' ) . '</div>';
			}
			$msg = '<p class="ftt-msg ftt-msg--bad" role="alert">' . esc_html( $r ) . '</p>';
		}
		$pick = self::page_url( 'providers_page', 'listings' );
		if ( ! $labs ) {
			return '<p>' . esc_html( sprintf( /* translators: %d: max */ __( 'Choose up to %d providers first.', 'film-transfer-toolkit' ), $o['max'] ) ) . ' <a href="' . esc_url( $pick ) . '">' . esc_html__( 'Choose providers', 'film-transfer-toolkit' ) . '</a></p>';
		}
		$v = function ( $k ) { return isset( $_POST[ $k ] ) && is_scalar( $_POST[ $k ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : ''; }; // phpcs:ignore WordPress.Security.NonceVerification -- redisplay only.
		$arr = function ( $k, $f ) { return isset( $_POST[ $k ][ $f ] ) && is_scalar( $_POST[ $k ][ $f ] ) ? esc_attr( absint( $_POST[ $k ][ $f ] ) ?: '' ) : ''; }; // phpcs:ignore WordPress.Security.NonceVerification -- redisplay only.
		$out  = '<form method="post" class="ftt-box ftt-rfq-form">' . $msg . wp_nonce_field( 'ftt_rfq', '_ftt_rfq', true, false ) . '<input type="hidden" name="labs" value="' . esc_attr( implode( ',', $labs ) ) . '">';
		$out .= '<fieldset><legend>' . esc_html( sprintf( /* translators: %d: count */ __( 'Providers you are asking (%d)', 'film-transfer-toolkit' ), count( $labs ) ) ) . '</legend><ul class="ftt-rfq-labs">';
		foreach ( $labs as $l ) { $out .= '<li>' . esc_html( get_the_title( $l ) ) . '</li>'; }
		$out .= '</ul><p class="ftt-note"><a href="' . esc_url( $pick ) . '">' . esc_html__( 'Change providers', 'film-transfer-toolkit' ) . '</a></p></fieldset>';
		$out .= '<fieldset><legend>' . esc_html__( 'Your film', 'film-transfer-toolkit' ) . '</legend><table class="ftt-table ftt-rfq-qty"><thead><tr><th scope="col">' . esc_html__( 'Format', 'film-transfer-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Reels', 'film-transfer-toolkit' ) . '</th><th scope="col">' . esc_html__( 'or total feet', 'film-transfer-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( self::formats() as $k => $lbl ) {
			$out .= '<tr><th scope="row">' . esc_html( $lbl ) . '</th><td><input type="number" min="0" max="999" aria-label="' . esc_attr( $lbl . ' reels' ) . '" name="reels[' . esc_attr( $k ) . ']" value="' . $arr( 'reels', $k ) . '"></td><td><input type="number" min="0" max="99999" aria-label="' . esc_attr( $lbl . ' feet' ) . '" name="feet[' . esc_attr( $k ) . ']" value="' . $arr( 'feet', $k ) . '"></td></tr>';
		}
		$out .= '</tbody></table></fieldset><fieldset><legend>' . esc_html__( 'Services', 'film-transfer-toolkit' ) . '</legend><div class="ftt-grid">';
		foreach ( self::choices() as $k => $c ) {
			$cur  = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- redisplay only.
			$out .= '<label class="ftt-field"><span>' . esc_html( $c['label'] ) . '</span><select name="' . esc_attr( $k ) . '">';
			foreach ( $c['opts'] as $opt ) { $out .= '<option' . selected( $cur, $opt, false ) . '>' . esc_html( $opt ) . '</option>'; }
			$out .= '</select></label>';
		}
		$out .= '<label class="ftt-field"><span>' . esc_html__( 'Needed by', 'film-transfer-toolkit' ) . '</span><input type="date" name="deadline" value="' . $v( 'deadline' ) . '"></label>';
		$out .= '<label class="ftt-field"><span>' . esc_html__( 'Budget (optional)', 'film-transfer-toolkit' ) . '</span><input name="budget" maxlength="40" placeholder="e.g. $300" value="' . $v( 'budget' ) . '"></label>';
		$out .= '<label class="ftt-field"><span>' . esc_html__( 'ZIP code', 'film-transfer-toolkit' ) . ( $o['zip_required'] ? ' *' : '' ) . '</span><input name="zip" inputmode="numeric" ' . ( $o['zip_required'] ? 'required ' : '' ) . 'pattern="\d{5}" maxlength="5" value="' . $v( 'zip' ) . '"></label></div>';
		$out .= '<label class="ftt-field"><span>' . esc_html__( 'Notes for the providers', 'film-transfer-toolkit' ) . '</span><textarea name="notes" rows="4" maxlength="2000">' . esc_textarea( isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '' ) . '</textarea></label></fieldset>'; // phpcs:ignore WordPress.Security.NonceVerification -- redisplay only.
		$out .= '<fieldset><legend>' . esc_html__( 'Your details', 'film-transfer-toolkit' ) . '</legend><div class="ftt-grid"><label class="ftt-field"><span>' . esc_html__( 'Name', 'film-transfer-toolkit' ) . ' *</span><input name="cname" required maxlength="80" autocomplete="name" value="' . $v( 'cname' ) . '"></label><label class="ftt-field"><span>' . esc_html__( 'Email', 'film-transfer-toolkit' ) . ' *</span><input type="email" name="cemail" required maxlength="120" autocomplete="email" value="' . $v( 'cemail' ) . '"></label></div>';
		$out .= '<div class="ftt-hp" aria-hidden="true"><label>' . esc_html__( 'Leave blank', 'film-transfer-toolkit' ) . ' <input name="website_url" tabindex="-1" autocomplete="off"></label></div>';
		$out .= '<p class="ftt-privacy"><strong>' . esc_html__( 'Privacy:', 'film-transfer-toolkit' ) . '</strong> ' . esc_html__( 'providers see your film details and ZIP code only. Your name and email stay hidden until you choose to contact a provider from your private quotes page. We email you a confirmation link first.', 'film-transfer-toolkit' ) . '</p>';
		$out .= '<p><button class="ftt-btn" name="ftt_rfq_submit" value="1">' . esc_html__( 'Send request', 'film-transfer-toolkit' ) . '</button></p></fieldset></form>';
		return $out;
	}

	/** @return true|string */
	private static function handle_submit( $labs ) {
		$o = self::opts();
		if ( ! isset( $_POST['_ftt_rfq'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ftt_rfq'] ) ), 'ftt_rfq' ) ) {
			return __( 'Your session expired. Please submit again.', 'film-transfer-toolkit' );
		}
		if ( ! empty( $_POST['website_url'] ) ) {
			return __( 'Something went wrong. Please try again.', 'film-transfer-toolkit' );
		}
		if ( ! $labs ) {
			return __( 'Choose at least one provider.', 'film-transfer-toolkit' );
		}
		$email = sanitize_email( wp_unslash( $_POST['cemail'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return __( 'Please enter a valid email address.', 'film-transfer-toolkit' );
		}
		$name = FTT_Util::text( wp_unslash( $_POST['cname'] ?? '' ), 80 );
		if ( '' === $name ) {
			return __( 'Please enter your name.', 'film-transfer-toolkit' );
		}
		$zip = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['zip'] ?? '' ) );
		if ( $o['zip_required'] && strlen( $zip ) !== 5 ) {
			return __( 'Please enter a 5-digit ZIP code.', 'film-transfer-toolkit' );
		}
		$zip  = substr( $zip, 0, 5 );
		$film = array();
		foreach ( self::formats() as $k => $l ) {
			$r = isset( $_POST['reels'][ $k ] ) && is_scalar( $_POST['reels'][ $k ] ) ? min( 999, absint( $_POST['reels'][ $k ] ) ) : 0;
			$f = isset( $_POST['feet'][ $k ] ) && is_scalar( $_POST['feet'][ $k ] ) ? min( 99999, absint( $_POST['feet'][ $k ] ) ) : 0;
			if ( $r || $f ) {
				$film[ $k ] = array( 'reels' => $r, 'feet' => $f );
			}
		}
		if ( ! $film ) {
			return __( 'Enter reels or feet for at least one format.', 'film-transfer-toolkit' );
		}
		if ( ! FTT_Util::rate( 'rfq_ip|' . FTT_Util::ip(), 5, HOUR_IN_SECONDS ) || ! FTT_Util::rate( 'rfq_em|' . strtolower( $email ), 5, DAY_IN_SECONDS ) ) {
			return __( 'Too many requests. Please try again later.', 'film-transfer-toolkit' );
		}
		$m = array( 'state' => 'unverified', 'labs' => $labs, 'film' => $film, 'zip' => $zip, 'cname' => $name, 'cemail' => $email, 'ip_hash' => FTT_Util::ip_hash(), 'contacted' => array(),
			'deadline' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_POST['deadline'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) : '',
			'budget' => FTT_Util::text( wp_unslash( $_POST['budget'] ?? '' ), 40 ), 'notes' => FTT_Util::textarea( wp_unslash( $_POST['notes'] ?? '' ), 2000 ) );
		foreach ( self::choices() as $k => $c ) {
			$val     = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
			$m[ $k ] = in_array( $val, $c['opts'], true ) ? $val : $c['opts'][0];
		}
		$id = wp_insert_post( array( 'post_type' => self::CPT_REQ, 'post_status' => 'private', 'post_title' => 'RFQ ' . current_time( 'Y-m-d H:i' ) . ' ' . $zip ) );
		if ( ! $id || is_wp_error( $id ) ) {
			return __( 'Could not save your request. Please try again.', 'film-transfer-toolkit' );
		}
		foreach ( $m as $k => $val ) {
			update_post_meta( $id, '_ftt_rfq_' . $k, $val );
		}
		$tok = FTT_Util::token();
		update_post_meta( $id, '_ftt_rfq_verify', FTT_Util::token_hash( $tok ) );
		update_post_meta( $id, '_ftt_rfq_verify_exp', time() + DAY_IN_SECONDS );
		FTT_Mail::send( $email, __( 'Confirm your film transfer quote request', 'film-transfer-toolkit' ), sprintf( "Hi %s,\n\nPlease confirm your quote request to %d provider(s) on %s:\n%s\n\nThe link expires in 24 hours. If you didn't request this, ignore this email; nothing will be sent.\n\n%s", $name, count( $labs ), self::brand(), self::page_url( 'form_page', 'request-quotes', array( 'ftt_rfq_verify' => $tok ) ), self::brand() ), 'rfq:verify:' . $id );
		return true;
	}

	public static function summary( $id ) {
		$f    = self::formats();
		$film = array();
		foreach ( (array) self::m( $id, 'film' ) as $k => $x ) {
			$film[] = ( $f[ $k ] ?? $k ) . ': ' . trim( ( $x['reels'] ? $x['reels'] . ' reel(s)' : '' ) . ( $x['reels'] && $x['feet'] ? ', ' : '' ) . ( $x['feet'] ? $x['feet'] . ' ft' : '' ) );
		}
		$out = array( 'Film' => implode( '; ', $film ) );
		foreach ( self::choices() as $k => $c ) { $out[ $c['label'] ] = self::m( $id, $k ); }
		return $out + array( 'Needed by' => self::m( $id, 'deadline' ), 'Budget' => self::m( $id, 'budget' ), 'Customer ZIP' => self::m( $id, 'zip' ), 'Notes' => self::m( $id, 'notes' ) );
	}

	private static function summary_text( $id ) {
		$s = '';
		foreach ( self::summary( $id ) as $k => $v ) {
			if ( '' !== (string) $v ) { $s .= "$k: $v\n"; }
		}
		return $s;
	}

	/** GET shows a confirm button; only the POST confirms, so mail scanners that prefetch links do not send requests. */
	private static function verify( $tok ) {
		$id = self::find_by( '_ftt_rfq_verify', $tok, self::CPT_REQ );
		if ( ! $id ) {
			return '<p class="ftt-msg">' . esc_html__( 'This confirmation link is invalid or has already been used.', 'film-transfer-toolkit' ) . '</p>';
		}
		if ( 'unverified' !== self::m( $id, 'state' ) ) {
			return '<p class="ftt-msg">' . esc_html__( 'Already confirmed.', 'film-transfer-toolkit' ) . '</p>';
		}
		if ( (int) self::m( $id, 'verify_exp' ) < time() ) {
			return '<p class="ftt-msg">' . esc_html__( 'This link has expired. Please send your request again.', 'film-transfer-toolkit' ) . '</p>';
		}
		$labs = array_values( array_filter( (array) self::m( $id, 'labs' ), array( __CLASS__, 'valid_provider' ) ) );
		if ( ! isset( $_POST['ftt_rfq_confirm'] ) || ! isset( $_POST['_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_n'] ) ), 'ftt_rfq_confirm' . $id ) ) {
			$h = '<form method="post" class="ftt-box">' . wp_nonce_field( 'ftt_rfq_confirm' . $id, '_n', true, false ) . '<p>' . esc_html( sprintf( /* translators: %d: count */ __( 'Send your quote request to %d provider(s)?', 'film-transfer-toolkit' ), count( $labs ) ) ) . '</p><ul>';
			foreach ( $labs as $l ) { $h .= '<li>' . esc_html( get_the_title( $l ) ) . '</li>'; }
			return $h . '</ul><p><button class="ftt-btn" name="ftt_rfq_confirm" value="1">' . esc_html__( 'Confirm and send', 'film-transfer-toolkit' ) . '</button></p></form>';
		}
		update_post_meta( $id, '_ftt_rfq_state', 'sent' );
		delete_post_meta( $id, '_ftt_rfq_verify' );
		$key = FTT_Util::token();
		update_post_meta( $id, '_ftt_rfq_key', FTT_Util::token_hash( $key ) );
		$sum   = self::summary_text( $id );
		$map   = array();
		$o     = self::opts();
		$admin = FTT_Plugin::general()['notify_email'] ?: get_option( 'admin_email' );
		foreach ( $labs as $lid ) {
			$lt = FTT_Util::token();
			$hh = FTT_Util::token_hash( $lt );
			add_post_meta( $id, '_ftt_rfq_lt', $hh );
			$map[ $hh ] = $lid;
			$reply = self::page_url( 'reply_page', 'rfq-reply', array( 't' => $lt ) );
			$opt   = self::page_url( 'reply_page', 'rfq-reply', array( 't' => $lt, 'optout' => 1 ) );
			$claim = $o['claim_url'] ? "\nIs this your business? Keep your details up to date: " . add_query_arg( 'listing', $lid, $o['claim_url'] ) . "\n" : '';
			$body  = sprintf( "A customer near ZIP %s has asked %s for a film transfer quote via %s.\n\n%s\nReply with your quote here (no account needed):\n%s\n\nThe customer's contact details are shared with you only if they choose to contact you after seeing quotes.\n%s\nDon't want quote requests? Opt out: %s\n\n%s", self::m( $id, 'zip' ), get_the_title( $lid ), self::brand(), $sum, $reply, $claim, $opt, self::brand() );
			$to    = self::provider_email( $lid );
			if ( $to ) {
				FTT_Mail::send( $to, sprintf( 'Quote request for %s (%s)', get_the_title( $lid ), self::brand() ), $body, 'rfq:provider:' . $id . ':' . $lid );
			} else {
				FTT_Mail::send( $admin, sprintf( '[Forward needed] Quote request for %s (no provider email on file)', get_the_title( $lid ) ), $body, 'rfq:forward:' . $id . ':' . $lid );
			}
		}
		update_post_meta( $id, '_ftt_rfq_ltmap', $map );
		FTT_Mail::send( self::m( $id, 'cemail' ), __( 'Your quote request was sent', 'film-transfer-toolkit' ), sprintf( "Thanks. Your request went to %d provider(s). We'll email you as quotes arrive.\n\nYour private quotes page (keep this link private):\n%s\n\n%s", count( $map ), self::page_url( 'compare_page', 'rfq-compare', array( 'k' => $key ) ), self::brand() ), 'rfq:customer:' . $id );
		return '<div class="ftt-box ftt-cta"><strong>' . esc_html__( 'Confirmed.', 'film-transfer-toolkit' ) . '</strong> ' . esc_html( sprintf( /* translators: %d: count */ __( 'Your request was sent to %d provider(s). We emailed you a private link where quotes will appear side by side.', 'film-transfer-toolkit' ), count( $map ) ) ) . '</div>';
	}

	/* ------------------------------------------------------- provider reply */

	public static function sc_reply() {
		wp_enqueue_style( 'ftt-rfq' );
		$t  = self::get( 't' );
		$id = self::find_by( '_ftt_rfq_lt', $t, self::CPT_REQ );
		if ( ! $id || 'sent' !== self::m( $id, 'state' ) ) {
			return '<p class="ftt-msg">' . esc_html__( 'This quote link is invalid or the request has closed.', 'film-transfer-toolkit' ) . '</p>';
		}
		$map = (array) self::m( $id, 'ltmap' );
		$lid = (int) ( $map[ FTT_Util::token_hash( $t ) ] ?? 0 );
		if ( ! $lid ) {
			return '<p class="ftt-msg">' . esc_html__( 'This quote link is invalid or the request has closed.', 'film-transfer-toolkit' ) . '</p>';
		}
		$title = get_the_title( $lid );
		if ( '' !== self::get( 'optout' ) ) {
			if ( isset( $_POST['ftt_rfq_optout'], $_POST['_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_n'] ) ), 'ftt_rfq_opt' . $id . '_' . $lid ) ) {
				update_post_meta( $lid, '_ft_rfq_optout', 1 );
				return '<p class="ftt-msg">' . esc_html( sprintf( /* translators: %s: provider */ __( '%s will no longer receive quote requests. Contact us to undo this.', 'film-transfer-toolkit' ), $title ) ) . '</p>';
			}
			return '<form method="post" class="ftt-box">' . wp_nonce_field( 'ftt_rfq_opt' . $id . '_' . $lid, '_n', true, false ) . '<p>' . esc_html( sprintf( /* translators: %s: provider */ __( 'Stop all quote requests to %s?', 'film-transfer-toolkit' ), $title ) ) . '</p><button class="ftt-btn" name="ftt_rfq_optout" value="1">' . esc_html__( 'Opt out', 'film-transfer-toolkit' ) . '</button></form>';
		}
		$done = get_posts( array( 'post_type' => self::CPT_QUOTE, 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_ftt_q_rfq', 'value' => $id ), array( 'key' => '_ftt_q_lab', 'value' => $lid ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$out  = '<div class="ftt-box"><h3>' . esc_html( sprintf( /* translators: %s: provider */ __( 'Quote request for %s', 'film-transfer-toolkit' ), $title ) ) . '</h3><table class="ftt-table">';
		foreach ( self::summary( $id ) as $k => $v ) {
			if ( '' !== (string) $v ) { $out .= '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>'; }
		}
		$out .= '</table>';
		if ( $done ) {
			return $out . '<p class="ftt-msg">' . esc_html__( 'You already sent a quote for this request.', 'film-transfer-toolkit' ) . '</p></div>';
		}
		if ( isset( $_POST['ftt_q_submit'], $_POST['_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_n'] ) ), 'ftt_q' . $id . '_' . $lid ) ) {
			if ( ! FTT_Util::rate( 'rfq_q|' . $id . '|' . $lid, 3, HOUR_IN_SECONDS ) ) {
				return $out . '<p class="ftt-msg">' . esc_html__( 'Too many attempts.', 'film-transfer-toolkit' ) . '</p></div>';
			}
			$price = FTT_Util::text( wp_unslash( $_POST['price'] ?? '' ), 40 );
			$turn  = FTT_Util::text( wp_unslash( $_POST['turnaround'] ?? '' ), 60 );
			if ( '' === $price || '' === $turn ) {
				$out .= '<p class="ftt-msg ftt-msg--bad" role="alert">' . esc_html__( 'Please enter a price and a turnaround.', 'film-transfer-toolkit' ) . '</p>';
			} else {
				$q = wp_insert_post( array( 'post_type' => self::CPT_QUOTE, 'post_status' => 'private', 'post_title' => 'Quote: ' . $title ) );
				$valid = sanitize_text_field( wp_unslash( $_POST['valid'] ?? '' ) );
				foreach ( array( 'price' => $price, 'turnaround' => $turn, 'notes' => FTT_Util::textarea( wp_unslash( $_POST['notes'] ?? '' ), 2000 ), 'valid' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valid ) ? $valid : '', 'rfq' => $id, 'lab' => $lid ) as $k => $v ) {
					update_post_meta( $q, '_ftt_q_' . $k, $v );
				}
				FTT_Mail::send( self::m( $id, 'cemail' ), sprintf( 'New quote from %s', $title ), sprintf( "%s sent a quote. Open the private quotes page link from your earlier email (\"Your quote request was sent\") to compare.\n\n%s", $title, self::brand() ), 'rfq:quote:' . $id . ':' . $lid );
				return $out . '<div class="ftt-cta"><strong>' . esc_html__( 'Thanks, your quote was sent.', 'film-transfer-toolkit' ) . '</strong> ' . esc_html__( 'The customer will contact you if they want to go ahead.', 'film-transfer-toolkit' ) . '</div></div>';
			}
		}
		$out .= '<form method="post" class="ftt-rfq-form">' . wp_nonce_field( 'ftt_q' . $id . '_' . $lid, '_n', true, false ) . '<div class="ftt-grid">'
			. '<label class="ftt-field"><span>' . esc_html__( 'Total price (USD)', 'film-transfer-toolkit' ) . ' *</span><input name="price" required maxlength="40" placeholder="e.g. $340 or $0.45/ft"></label>'
			. '<label class="ftt-field"><span>' . esc_html__( 'Turnaround', 'film-transfer-toolkit' ) . ' *</span><input name="turnaround" required maxlength="60" placeholder="e.g. 2-3 weeks"></label>'
			. '<label class="ftt-field"><span>' . esc_html__( 'Valid until', 'film-transfer-toolkit' ) . '</span><input type="date" name="valid"></label></div>'
			. '<label class="ftt-field"><span>' . esc_html__( "Notes (what's included, shipping, assumptions)", 'film-transfer-toolkit' ) . '</span><textarea name="notes" rows="4" maxlength="2000"></textarea></label>'
			. '<p><button class="ftt-btn" name="ftt_q_submit" value="1">' . esc_html__( 'Send quote', 'film-transfer-toolkit' ) . '</button></p></form></div>';
		return $out;
	}

	/* ---------------------------------------------------- customer compare */

	public static function sc_compare() {
		wp_enqueue_style( 'ftt-rfq' );
		$id = self::find_by( '_ftt_rfq_key', self::get( 'k' ), self::CPT_REQ );
		if ( ! $id ) {
			return '<p class="ftt-msg">' . esc_html__( 'This private link is invalid.', 'film-transfer-toolkit' ) . '</p>';
		}
		$quotes    = get_posts( array( 'post_type' => self::CPT_QUOTE, 'post_status' => 'private', 'numberposts' => 20, 'meta_key' => '_ftt_q_rfq', 'meta_value' => $id, 'orderby' => 'date', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$contacted = array_map( 'intval', (array) self::m( $id, 'contacted' ) );
		$msg       = '';
		if ( isset( $_POST['ftt_rfq_contact'], $_POST['_n'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_n'] ) ), 'ftt_rfq_c' . $id ) ) {
			$lid = absint( $_POST['ftt_rfq_contact'] );
			$ok  = false;
			foreach ( $quotes as $q ) {
				if ( (int) get_post_meta( $q->ID, '_ftt_q_lab', true ) === $lid ) { $ok = true; }
			}
			if ( $ok && ! in_array( $lid, $contacted, true ) ) {
				$to = self::provider_email( $lid ) ?: ( FTT_Plugin::general()['notify_email'] ?: get_option( 'admin_email' ) );
				FTT_Mail::send( $to, 'Customer wants to proceed: ' . get_the_title( $lid ), sprintf( "The customer you quoted (ZIP %s) would like to contact you.\n\nName: %s\nEmail: %s\n\nPlease reply to them directly.\n\n%s", self::m( $id, 'zip' ), self::m( $id, 'cname' ), self::m( $id, 'cemail' ), self::brand() ), 'rfq:contact:' . $id . ':' . $lid );
				$contacted[] = $lid;
				update_post_meta( $id, '_ftt_rfq_contacted', $contacted );
				$msg = '<p class="ftt-cta">' . esc_html( sprintf( /* translators: %s: provider */ __( 'We sent your name and email to %s. They will contact you directly.', 'film-transfer-toolkit' ), get_the_title( $lid ) ) ) . '</p>';
			}
		}
		$labs = array_map( 'intval', (array) self::m( $id, 'labs' ) );
		$out  = '<div class="ftt-box"><h3>' . esc_html__( 'Your quotes', 'film-transfer-toolkit' ) . '</h3>' . $msg . '<p class="ftt-note">' . esc_html( sprintf( /* translators: 1: providers, 2: quotes, 3: brand */ __( 'Requested from %1$d provider(s). %2$d quote(s) so far. Quotes are the providers\' own figures; %3$s does not verify or guarantee them.', 'film-transfer-toolkit' ), count( $labs ), count( $quotes ), self::brand() ) ) . '</p>';
		if ( ! $quotes ) {
			return $out . '<p>' . esc_html__( "No quotes yet. We'll email you when they arrive.", 'film-transfer-toolkit' ) . '</p></div>';
		}
		$out .= '<div class="ftt-rfq-compare"><table class="ftt-table"><thead><tr><td></td>';
		foreach ( $quotes as $q ) { $out .= '<th scope="col">' . self::provider_link( (int) get_post_meta( $q->ID, '_ftt_q_lab', true ) ) . '</th>'; }
		$out .= '</tr></thead><tbody>';
		foreach ( array( 'price' => __( 'Price', 'film-transfer-toolkit' ), 'turnaround' => __( 'Turnaround', 'film-transfer-toolkit' ), 'valid' => __( 'Valid until', 'film-transfer-toolkit' ), 'notes' => __( 'Notes', 'film-transfer-toolkit' ) ) as $f => $lbl ) {
			$out .= '<tr><th scope="row">' . esc_html( $lbl ) . '</th>';
			foreach ( $quotes as $q ) { $out .= '<td>' . nl2br( esc_html( (string) get_post_meta( $q->ID, '_ftt_q_' . $f, true ) ) ) . '</td>'; }
			$out .= '</tr>';
		}
		$out .= '<tr><th scope="row">' . esc_html__( 'Next step', 'film-transfer-toolkit' ) . '</th>';
		foreach ( $quotes as $q ) {
			$l    = (int) get_post_meta( $q->ID, '_ftt_q_lab', true );
			$out .= '<td>' . ( in_array( $l, $contacted, true ) ? esc_html__( 'Contact details sent', 'film-transfer-toolkit' ) : '<form method="post">' . wp_nonce_field( 'ftt_rfq_c' . $id, '_n', true, false ) . '<button class="ftt-btn" name="ftt_rfq_contact" value="' . esc_attr( $l ) . '">' . esc_html__( 'Share my contact details', 'film-transfer-toolkit' ) . '</button></form>' ) . '</td>';
		}
		$out .= '</tr></tbody></table></div>';
		$pending = array_diff( $labs, array_map( function ( $q ) { return (int) get_post_meta( $q->ID, '_ftt_q_lab', true ); }, $quotes ) );
		if ( $pending ) {
			$out .= '<p class="ftt-note">' . esc_html__( 'Waiting on:', 'film-transfer-toolkit' ) . ' ' . esc_html( implode( ', ', array_map( 'get_the_title', $pending ) ) ) . '</p>';
		}
		return $out . '<p class="ftt-note">' . esc_html__( 'Providers only see your contact details after you press Share my contact details.', 'film-transfer-toolkit' ) . '</p></div>';
	}

	/* --------------------------------------------------------------- admin */

	public static function menu() {
		add_submenu_page( 'ftt', __( 'RFQ requests', 'film-transfer-toolkit' ), __( 'RFQ requests', 'film-transfer-toolkit' ), 'manage_options', 'ftt-rfq', array( __CLASS__, 'render_admin' ) );
	}

	public static function settings() {
		register_setting( 'ftt_rfq', self::OPT, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'show_in_rest' => false ) );
	}

	public static function sanitize( $in ) {
		$in = is_array( $in ) ? $in : array();
		$pt = sanitize_key( $in['provider_type'] ?? self::CPT_PROV );
		if ( self::CPT_PROV !== $pt && ! post_type_exists( $pt ) ) {
			add_settings_error( self::OPT, 'pt', __( 'That provider post type does not exist; using Quote providers.', 'film-transfer-toolkit' ) );
			$pt = self::CPT_PROV;
		}
		return array(
			'provider_type' => $pt, 'email_meta' => sanitize_key( $in['email_meta'] ?? 'rfq_email' ) ?: 'rfq_email', 'max' => max( 1, min( 10, absint( $in['max'] ?? 5 ) ) ),
			'brand' => FTT_Util::text( $in['brand'] ?? '', 80 ), 'zip_required' => empty( $in['zip_required'] ) ? 0 : 1, 'claim_url' => esc_url_raw( $in['claim_url'] ?? '' ),
			'form_page' => absint( $in['form_page'] ?? 0 ), 'reply_page' => absint( $in['reply_page'] ?? 0 ), 'compare_page' => absint( $in['compare_page'] ?? 0 ), 'providers_page' => absint( $in['providers_page'] ?? 0 ),
		);
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$o     = self::opts();
		$pages = array( 'providers_page' => array( '[ftt_rfq_providers]', 'listings' ), 'form_page' => array( '[ftt_rfq_form]', 'request-quotes' ), 'reply_page' => array( '[ftt_rfq_reply]', 'rfq-reply' ), 'compare_page' => array( '[ftt_rfq_compare]', 'rfq-compare' ) );
		?>
		<div class="wrap ftt-admin">
			<h1><?php esc_html_e( 'RFQ requests', 'film-transfer-toolkit' ); ?></h1>
			<?php settings_errors(); ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'State', 'film-transfer-toolkit' ); ?></th><th>ZIP</th><th><?php esc_html_e( 'Customer', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Providers', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Quotes', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Film', 'film-transfer-toolkit' ); ?></th></tr></thead><tbody>
			<?php
			$reqs = get_posts( array( 'post_type' => self::CPT_REQ, 'post_status' => 'private', 'numberposts' => 200 ) );
			if ( ! $reqs ) {
				echo '<tr><td colspan="7">' . esc_html__( 'No requests yet.', 'film-transfer-toolkit' ) . '</td></tr>';
			}
			foreach ( $reqs as $r ) {
				$q = get_posts( array( 'post_type' => self::CPT_QUOTE, 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_ftt_q_rfq', 'meta_value' => $r->ID ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				echo '<tr><td>' . esc_html( $r->post_date ) . '</td><td>' . esc_html( self::m( $r->ID, 'state' ) ) . '</td><td>' . esc_html( self::m( $r->ID, 'zip' ) ) . '</td><td>' . esc_html( self::m( $r->ID, 'cname' ) . ' <' . self::m( $r->ID, 'cemail' ) . '>' ) . '</td><td>' . esc_html( implode( ', ', array_map( 'get_the_title', (array) self::m( $r->ID, 'labs' ) ) ) ) . '</td><td>' . (int) count( $q ) . '</td><td>' . esc_html( self::summary( $r->ID )['Film'] ) . '</td></tr>';
			}
			?>
			</tbody></table>
			<h2><?php esc_html_e( 'Settings', 'film-transfer-toolkit' ); ?></h2>
			<form action="options.php" method="post">
				<?php settings_fields( 'ftt_rfq' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="ftt-rfq-pt"><?php esc_html_e( 'Provider post type', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-rfq-pt" name="ftt_rfq[provider_type]" value="<?php echo esc_attr( $o['provider_type'] ); ?>"> <span class="description"><?php esc_html_e( 'ftt_provider = the toolkit\'s own Quote providers list. On filmtransfer.ai use listing.', 'film-transfer-toolkit' ); ?></span></td></tr>
					<tr><th><label for="ftt-rfq-em"><?php esc_html_e( 'Provider email meta key', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-rfq-em" name="ftt_rfq[email_meta]" value="<?php echo esc_attr( $o['email_meta'] ); ?>"></td></tr>
					<tr><th><label for="ftt-rfq-max"><?php esc_html_e( 'Most providers per request', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-rfq-max" type="number" min="1" max="10" class="small-text" name="ftt_rfq[max]" value="<?php echo esc_attr( $o['max'] ); ?>"></td></tr>
					<tr><th><label for="ftt-rfq-brand"><?php esc_html_e( 'Name used in emails', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-rfq-brand" name="ftt_rfq[brand]" value="<?php echo esc_attr( $o['brand'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'ZIP code', 'film-transfer-toolkit' ); ?></th><td><label><input type="checkbox" name="ftt_rfq[zip_required]" value="1" <?php checked( $o['zip_required'] ); ?>> <?php esc_html_e( 'Require a 5-digit US ZIP code', 'film-transfer-toolkit' ); ?></label></td></tr>
					<tr><th><label for="ftt-rfq-claim"><?php esc_html_e( 'Claim-listing URL (optional)', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-rfq-claim" class="regular-text" name="ftt_rfq[claim_url]" value="<?php echo esc_attr( $o['claim_url'] ); ?>" placeholder="https://filmtransfer.ai/claim-your-listing/"></td></tr>
					<?php foreach ( $pages as $k => $p ) : ?>
						<tr><th><label for="ftt-rfq-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( sprintf( /* translators: %s: shortcode */ __( 'Page with %s', 'film-transfer-toolkit' ), $p[0] ) ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => 'ftt_rfq[' . esc_attr( $k ) . ']', 'id' => 'ftt-rfq-' . esc_attr( $k ), 'selected' => (int) $o[ $k ], 'show_option_none' => sprintf( '(default: /%s/)', $p[1] ), 'option_none_value' => 0, 'post_status' => array( 'publish', 'private', 'draft' ) ) ); ?></td></tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function provider_box() {
		add_meta_box( 'ftt_provider', __( 'Quote requests', 'film-transfer-toolkit' ), array( __CLASS__, 'render_provider_box' ), self::CPT_PROV, 'normal', 'high' );
	}

	public static function render_provider_box( $post ) {
		wp_nonce_field( 'ftt_provider', 'ftt_provider_nonce' );
		$key = self::opts()['email_meta'];
		echo '<p><label>' . esc_html__( 'Quote-request email', 'film-transfer-toolkit' ) . '<br><input type="email" class="regular-text" name="ftt_provider_email" value="' . esc_attr( get_post_meta( $post->ID, $key, true ) ) . '"></label><br><span class="description">' . esc_html__( 'Only enter an address the provider has published or given you. Blank = requests go to the notify address to forward.', 'film-transfer-toolkit' ) . '</span></p>';
		echo '<p><label>' . esc_html__( 'Website', 'film-transfer-toolkit' ) . '<br><input type="url" class="regular-text" name="ftt_provider_site" value="' . esc_attr( get_post_meta( $post->ID, '_ftt_website', true ) ) . '"></label></p>';
		echo '<p><label><input type="checkbox" name="ftt_provider_optout" value="1" ' . checked( (bool) get_post_meta( $post->ID, '_ft_rfq_optout', true ), true, false ) . '> ' . esc_html__( 'Opted out of quote requests', 'film-transfer-toolkit' ) . '</label></p>';
	}

	public static function save_provider( $id ) {
		if ( ! isset( $_POST['ftt_provider_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ftt_provider_nonce'] ) ), 'ftt_provider' ) || ! current_user_can( 'manage_options' ) || wp_is_post_revision( $id ) ) {
			return;
		}
		update_post_meta( $id, self::opts()['email_meta'], sanitize_email( wp_unslash( $_POST['ftt_provider_email'] ?? '' ) ) );
		update_post_meta( $id, '_ftt_website', esc_url_raw( wp_unslash( $_POST['ftt_provider_site'] ?? '' ) ) );
		if ( empty( $_POST['ftt_provider_optout'] ) ) {
			delete_post_meta( $id, '_ft_rfq_optout' );
		} else {
			update_post_meta( $id, '_ft_rfq_optout', 1 );
		}
	}
}
FTT_RFQ::init();
