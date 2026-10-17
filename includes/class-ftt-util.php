<?php
/**
 * Small shared helpers: client IP, rate limits, bounded text.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Util {
	/** REMOTE_ADDR only; forwarded headers are client-controlled and are not trusted. */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** Privacy: store a salted hash, not the address. */
	public static function ip_hash() {
		return substr( hash_hmac( 'sha256', self::ip(), wp_salt( 'nonce' ) ), 0, 16 );
	}

	/** Returns false once $bucket has been hit more than $max times within $window seconds. */
	public static function rate( $bucket, $max, $window ) {
		$key = 'ftt_rl_' . md5( $bucket );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $key, $n + 1, $window );
		return true;
	}

	public static function text( $v, $max = 200 ) {
		$v = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	public static function textarea( $v, $max = 2000 ) {
		$v = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	}

	public static function token() {
		return bin2hex( random_bytes( 24 ) );
	}

	public static function token_hash( $t ) {
		return hash_hmac( 'sha256', (string) $t, wp_salt( 'auth' ) . 'ftt' );
	}

	/** Never cache responses that carry nonces or private data. */
	public static function nocache_rest( $data, $status = 200 ) {
		$r = new WP_REST_Response( $data, $status );
		$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $r;
	}
}
