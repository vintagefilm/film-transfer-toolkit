<?php
/**
 * 1.5.5: "Require sign-in to add to cart".
 *
 * A visitor who is not signed in can use the selector as usual. Add to cart then saves the pending clips server-side
 * (built and HMAC-signed here, never priced by the browser) in a transient keyed by a random token (2 hours, single
 * use) and sends the visitor to the sign-in or registration page with redirect_to = this page + ftt_clip_resume=<token>.
 * The token also rides in a short-lived cookie and in localStorage, so the clips still arrive when a sign-in or
 * registration form ignores redirect_to. On the first signed-in page view the token is claimed (deleted first), each
 * clip is re-derived with FTT_Clip::from_stored() (price recalculated) and added to the FluentCart cart.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Clip_Resume {
	const PARAM  = 'ftt_clip_resume';
	const COOKIE = 'ftt_clip_resume';
	const PREFIX = 'ftt_clip_pending_';
	const TTL    = 7200; // 2 hours.
	const MAX    = 20;   // clips per pending selection.

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_resume' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'print_notice' ) );
		add_filter( 'registration_redirect', array( __CLASS__, 'registration_redirect' ) );
	}

	public static function valid_token( $t ) {
		return is_string( $t ) && preg_match( '/^[A-Za-z0-9]{32}$/', $t );
	}

	/** Sign-in / registration URL with redirect_to; configured page first, WordPress default otherwise. */
	public static function auth_url( $which, $return ) {
		$s   = FTT_Clip_Settings::get();
		$pid = 'register' === $which ? $s['register_page_id'] : $s['login_page_id'];
		$url = '';
		if ( $pid && 'publish' === get_post_status( $pid ) ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $return ), get_permalink( $pid ) );
		} elseif ( 'register' === $which ) {
			$url = get_option( 'users_can_register' ) ? add_query_arg( 'redirect_to', rawurlencode( $return ), wp_registration_url() ) : '';
		} else {
			$url = wp_login_url( $return );
		}
		return apply_filters( 'ftt/clip/auth_url', $url, $which, $return );
	}

	/** Only same-site URLs may be used as the return page. */
	public static function safe_return( $url ) {
		$url = wp_validate_redirect( esc_url_raw( (string) $url ), '' );
		if ( '' === $url ) {
			$url = home_url( '/' );
		}
		return remove_query_arg( array( self::PARAM, 'ftt_clip_added' ), $url );
	}

	/**
	 * Build + sign the clips and store them. $clips: list of [product_id, clip_id, in, out, fps, seq, name].
	 * Returns ['token', 'return', 'login_url', 'register_url', 'expires'] or WP_Error.
	 */
	public static function create( array $clips, $return ) {
		if ( ! $clips ) {
			return new WP_Error( 'fwcc_empty', 'Clip list is empty.', array( 'status' => 400 ) );
		}
		if ( count( $clips ) > self::MAX ) {
			return new WP_Error( 'fwcc_too_many', 'Too many clips in one request.', array( 'status' => 400 ) );
		}
		$stored = array();
		foreach ( $clips as $c ) {
			$c = is_array( $c ) ? $c : array();
			$pid = absint( $c['product_id'] ?? 0 );
			if ( ! FTT_Clip_Settings::is_clip_product( $pid ) ) {
				return new WP_Error( 'fwcc_not_clip_product', 'That product does not sell clips.', array( 'status' => 400 ) );
			}
			$clip = FTT_Clip::build( $pid, $c['clip_id'] ?? '', $c['in'] ?? null, $c['out'] ?? null, $c['fps'] ?? '', $c['seq'] ?? 1, $c['name'] ?? '' );
			if ( is_wp_error( $clip ) ) {
				return $clip;
			}
			$stored[] = array( 'fw_clip' => $clip, 'fw_sig' => FTT_Clip::sign( $clip ) );
		}
		$token  = wp_generate_password( 32, false, false );
		$return = self::safe_return( $return );
		$exp    = time() + self::TTL;
		set_transient( self::PREFIX . $token, array( 'clips' => $stored, 'return' => $return, 'exp' => $exp ), self::TTL );
		$back = add_query_arg( self::PARAM, $token, $return );
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, $token, array( 'expires' => $exp, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
		return array(
			'token'        => $token,
			'return'       => $back,
			'login_url'    => self::auth_url( 'login', $back ),
			'register_url' => self::auth_url( 'register', $back ),
			'expires'      => $exp,
		);
	}

	/**
	 * Claim a token for the signed-in user: single use (deleted before anything else), expiry checked, every clip
	 * re-derived from its signed data (server price) and added to the cart. Returns ['added','failed','return'] or WP_Error.
	 */
	public static function claim( $token ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'fwcc_login_required', 'Sign in first.', array( 'status' => 401 ) );
		}
		if ( ! self::valid_token( $token ) ) {
			return new WP_Error( 'fwcc_token', 'Unknown or expired clip selection.', array( 'status' => 404 ) );
		}
		$key  = self::PREFIX . $token;
		$data = get_transient( $key );
		delete_transient( $key ); // single use, even if something below fails.
		self::clear_cookie();
		if ( ! is_array( $data ) || empty( $data['clips'] ) || (int) ( $data['exp'] ?? 0 ) < time() ) {
			return new WP_Error( 'fwcc_token', 'Unknown or expired clip selection.', array( 'status' => 404 ) );
		}
		if ( ! class_exists( '\FluentCart\App\Helpers\CartHelper' ) ) {
			return new WP_Error( 'fwcc_no_cart', 'FluentCart is not active.', array( 'status' => 500 ) );
		}
		$added = array();
		$fail  = array();
		foreach ( (array) $data['clips'] as $st ) {
			$clip = FTT_Clip::from_stored( $st );
			if ( is_wp_error( $clip ) || ! FTT_Clip_Settings::is_clip_product( $clip['product_id'] ) ) {
				$fail[] = is_wp_error( $clip ) ? $clip->get_error_message() : 'Product no longer sells clips.';
				continue;
			}
			$cart = \FluentCart\App\Helpers\CartHelper::getCart( null, true );
			if ( ! $cart || is_wp_error( $cart ) ) {
				$fail[] = 'Could not open the cart.';
				continue;
			}
			$r = $cart->addByCustom( FTT_Clip::to_variation( $clip ), array( 'quantity' => 1 ) );
			if ( is_wp_error( $r ) ) {
				$fail[] = $r->get_error_message();
			} else {
				$added[] = FTT_Clip::item_id( $clip );
			}
		}
		$res = array( 'added' => $added, 'failed' => $fail, 'return' => $data['return'] ?? home_url( '/' ) );
		set_transient( 'ftt_clip_notice_' . get_current_user_id(), array( 'added' => count( $added ), 'failed' => $fail ), 10 * MINUTE_IN_SECONDS );
		return $res;
	}

	public static function clear_cookie() {
		unset( $_COOKIE[ self::COOKIE ] );
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', array( 'expires' => time() - 3600, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		}
	}

	public static function cart_url() {
		$u = '';
		if ( class_exists( '\FluentCart\Api\StoreSettings' ) ) {
			try {
				$u = ( new \FluentCart\Api\StoreSettings() )->getCartPage();
			} catch ( \Throwable $e ) {
				$u = '';
			}
		}
		return apply_filters( 'ftt/clip/cart_url', $u ? $u : home_url( '/cart/' ) );
	}

	/** First signed-in front-end page view with a token (URL or cookie): claim it, then redirect. */
	public static function maybe_resume() {
		// Plain page views only: never inside a sign-in/sign-up POST (that request has not finished logging in yet).
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method || ! is_user_logged_in() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$t = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- the random single-use token is the capability.
		if ( ! self::valid_token( $t ) ) {
			$t = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		}
		if ( ! self::valid_token( $t ) ) {
			return;
		}
		$r = self::claim( $t );
		$here = remove_query_arg( self::PARAM );
		if ( is_wp_error( $r ) ) {
			if ( isset( $_GET[ self::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				set_transient( 'ftt_clip_notice_' . get_current_user_id(), array( 'added' => 0, 'failed' => array( $r->get_error_message() ) ), 10 * MINUTE_IN_SECONDS );
				wp_safe_redirect( $here );
				exit;
			}
			return;
		}
		$to = ( 'cart' === FTT_Clip_Settings::get()['after_login'] && $r['added'] ) ? self::cart_url() : $r['return'];
		wp_safe_redirect( $to ? $to : $here );
		exit;
	}

	/** Notice after resuming: how many clips went into the cart, with a link to it. */
	public static function print_notice() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$k = 'ftt_clip_notice_' . get_current_user_id();
		$n = get_transient( $k );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( $k );
		$bad = ! empty( $n['failed'] );
		echo '<div class="ftt-clip-resume-notice' . ( $bad ? ' bad' : '' ) . '" role="status" style="position:fixed;left:50%;bottom:20px;transform:translateX(-50%);z-index:99999;max-width:92vw;padding:12px 16px;border-radius:6px;background:' . ( $bad ? '#7a1f1f' : '#1d4d2b' ) . ';color:#fff;font:15px/1.4 system-ui,sans-serif;box-shadow:0 4px 18px rgba(0,0,0,.3)">';
		if ( $n['added'] ) {
			/* translators: %d: number of clips */
			echo esc_html( sprintf( _n( 'You are signed in. %d clip was added to your cart.', 'You are signed in. %d clips were added to your cart.', (int) $n['added'], 'film-transfer-toolkit' ), (int) $n['added'] ) ) . ' ';
			echo '<a class="ftt-clip-resume-cart" style="color:#fff;text-decoration:underline;font-weight:600" href="' . esc_url( self::cart_url() ) . '">' . esc_html__( 'View cart', 'film-transfer-toolkit' ) . '</a>';
		}
		if ( $bad ) {
			echo ' ' . esc_html__( 'Not added:', 'film-transfer-toolkit' ) . ' ' . esc_html( implode( '; ', array_map( 'strval', $n['failed'] ) ) );
		}
		echo ' <button type="button" aria-label="' . esc_attr__( 'Dismiss', 'film-transfer-toolkit' ) . '" onclick="this.parentNode.remove()" style="margin-left:10px;background:none;border:0;color:#fff;font-size:18px;cursor:pointer">&times;</button></div>';
	}

	/** wp-login.php registration: keep the redirect_to (with the resume token) the visitor came with. */
	public static function registration_redirect( $to ) {
		$r = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $r && false !== strpos( $r, self::PARAM . '=' ) ) {
			return wp_validate_redirect( $r, $to );
		}
		return $to;
	}
}
