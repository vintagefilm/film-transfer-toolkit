<?php
/**
 * 1.5.11: cart helpers. Standard shopping-cart flow: any visitor adds clips to the cart, then goes to the cart and the
 * checkout, where FluentCart handles sign-in, guest checkout and account creation. The selector itself shows no sign-in
 * or create-account UI (the 1.5.5 "Require sign-in" gate and the 1.5.8 "add, then prompt" notice were retired).
 *
 * What stays: a guest's cart (FluentCart keeps it in a cookie) is recorded on the user when the visitor signs in or
 * registers anywhere on the site, so the clips are still there on the next visit. Only an open, unowned cart that
 * holds FTT clips is touched; its items are left exactly as they are (no merge, nothing can be duplicated).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Clip_Cart {
	public static function init() {
		// Every path that sets the auth cookie (wp-login.php, FluentAuth, FluentCart checkout auto-login).
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'adopt_guest_cart' ), 10, 4 );
	}

	public static function adopt_guest_cart( $cookie, $expire, $expiration, $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! class_exists( '\FluentCart\Api\Cookie\Cookie' ) || ! class_exists( '\FluentCart\App\Models\Cart' ) ) {
			return;
		}
		try {
			$hash = \FluentCart\Api\Cookie\Cookie::getCartHash();
			if ( ! $hash ) {
				return;
			}
			$cart = \FluentCart\App\Models\Cart::query()->where( 'cart_hash', $hash )->where( 'cart_group', 'global' )->where( 'stage', '!=', 'completed' )->first();
			if ( ! $cart || (int) $cart->user_id || ! self::has_clips( $cart ) ) {
				return;
			}
			$cart->user_id = $user_id;
			$cart->save();
			do_action( 'ftt/clip/guest_cart_adopted', $hash, $user_id );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/** Item ids in a FluentCart cart. */
	public static function cart_ids( $cart ) {
		return array_map( 'intval', array_column( (array) ( $cart->cart_data ?? array() ), 'id' ) );
	}

	public static function has_clips( $cart ) {
		foreach ( self::cart_ids( $cart ) as $id ) {
			if ( $id >= FTT_Clip::ID_BASE && $id < FTT_Clip::ID_BASE + FTT_Clip::ID_SPAN ) {
				return true;
			}
		}
		return false;
	}

	/** FluentCart's own Cart page (FluentCart > Settings > Store > Pages), else /cart/. */
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
}
