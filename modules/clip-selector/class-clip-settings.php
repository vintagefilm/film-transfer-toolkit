<?php
/**
 * Clip selector site settings (1.3.0): default product, default frame rate, automatic placement.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Clip_Settings {
	const OPTION = 'ftt_clip_settings';

	public static function defaults() {
		return array(
			'default_product_id' => 0,          // FluentCart product used when a selector has no product_id.
			'default_fps'        => '29.97df',  // Timecode rate when neither the shortcode nor a product sets one.
			'auto_attach'        => 'off',      // off | page | post | all: add a selector under the content's first video player.
			'heading'            => __( 'Select Stock Footage', 'film-transfer-toolkit' ), // 1.5.3: title above the selector; '' hides it.
		);
	}

	public static function sanitize( $in ) {
		$d  = self::defaults();
		$in = is_array( $in ) ? $in : array();
		$fps = isset( $in['default_fps'] ) ? FTT_Timecode::norm( sanitize_text_field( (string) $in['default_fps'] ) ) : null;
		return array(
			'default_product_id' => isset( $in['default_product_id'] ) ? absint( $in['default_product_id'] ) : 0,
			'default_fps'        => $fps ? $fps : $d['default_fps'],
			'auto_attach'        => isset( $in['auto_attach'] ) && in_array( $in['auto_attach'], array( 'off', 'page', 'post', 'all' ), true ) ? $in['auto_attach'] : 'off',
			// 1.5.3: missing key = default heading (older saved settings); a saved blank hides it.
			'heading'            => array_key_exists( 'heading', $in ) ? self::clean_heading( $in['heading'] ) : $d['heading'],
		);
	}

	/** Plain text, one line, at most 120 characters. */
	public static function clean_heading( $v ) {
		$v = is_scalar( $v ) ? trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $v ) ) ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 120 ) : substr( $v, 0, 120 );
	}

	/**
	 * The heading one selector shows. An empty or missing heading attribute uses the site setting (so a block left at
	 * its default renders like the bare shortcode); heading="none" hides it; anything else is shown (plain text).
	 */
	public static function heading_for( $atts ) {
		$h = is_array( $atts ) && isset( $atts['heading'] ) ? self::clean_heading( $atts['heading'] ) : '';
		if ( '' === $h ) {
			return self::get()['heading'];
		}
		return 'none' === strtolower( $h ) ? '' : $h;
	}

	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	public static function fluentcart_active() {
		return class_exists( '\FluentCart\App\Helpers\CartHelper' );
	}

	/** True when $pid is a FluentCart product that sells clips. */
	public static function is_clip_product( $pid ) {
		$pid = absint( $pid );
		if ( ! $pid || ! self::fluentcart_active() ) {
			return false;
		}
		$p = get_post( $pid );
		return $p && 'fluent-products' === $p->post_type && in_array( $p->post_status, array( 'publish', 'private' ), true ) && '1' === get_post_meta( $pid, '_fw_clip_cart_enabled', true );
	}

	/** Regex for any supported player in rendered HTML (used by automatic placement). */
	public static function player_pattern() {
		return '#<video[\s>]|<media-player[\s>]|class="[^"]*\b(?:flowplayer|freedomplayer)\b|<iframe[^>]+src="[^"]*(?:youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)#i';
	}

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'auto_attach' ), 20 );
	}

	/** Automatic placement: append one selector after the content of singular pages/posts that contain a player. */
	public static function auto_attach( $content ) {
		$s = self::get();
		if ( 'off' === $s['auto_attach'] || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$type = get_post_type();
		if ( ( 'page' === $s['auto_attach'] && 'page' !== $type ) || ( 'post' === $s['auto_attach'] && 'post' !== $type ) ) {
			return $content;
		}
		if ( false !== strpos( $content, 'class="fwcc' ) || ! preg_match( self::player_pattern(), $content ) ) {
			return $content;
		}
		if ( apply_filters( 'ftt/clip/auto_attach', true, get_the_ID() ) === false ) {
			return $content;
		}
		return $content . FTT_Clip_Shortcode::render( array() );
	}
}
