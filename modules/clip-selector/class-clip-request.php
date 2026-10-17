<?php
/**
 * Clip requests (1.3.0): when FluentCart is not active, or a selector has no clip product, visitors send their
 * clip list to the site owner instead of adding it to a cart. Requests are saved privately (Film Transfer
 * Toolkit > Clip requests) and the owner is told through FTT_Mail (log-only until mail is switched to Send).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Clip_Request {
	const CPT       = 'ftt_clip_request';
	const META      = '_ftt_clip_request';
	const MAX_CLIPS = 50;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_box' ) );
	}

	public static function register() {
		$admin = 'manage_options';
		register_post_type( self::CPT, array(
			'labels'              => array( 'name' => __( 'Clip requests', 'film-transfer-toolkit' ), 'singular_name' => __( 'Clip request', 'film-transfer-toolkit' ), 'menu_name' => __( 'Clip requests', 'film-transfer-toolkit' ), 'edit_item' => __( 'Clip request', 'film-transfer-toolkit' ) ),
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
	}

	public static function routes() {
		register_rest_route( FTT_Clip_Rest::NS, '/token', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'token' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( FTT_Clip_Rest::NS, '/request', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'request' ), 'permission_callback' => '__return_true' ) );
	}

	/** Fresh nonce so cached pages still work. */
	public static function token() {
		return FTT_Util::nocache_rest( array( 'nonce' => wp_create_nonce( 'ftt_clip_request' ) ) );
	}

	private static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( $code, $msg, array( 'status' => $status ) );
	}

	/**
	 * Validate a request body. Returns the clean request (with server-side prices) or WP_Error. No side effects.
	 */
	public static function validate( array $b ) {
		$card = FTT_Clip_Rate_Card::get();
		$name = FTT_Util::text( $b['name'] ?? '', 100 );
		$email = sanitize_email( (string) ( $b['email'] ?? '' ) );
		if ( '' === $name ) {
			return self::err( 'ftt_clip_name', __( 'Please enter your name.', 'film-transfer-toolkit' ) );
		}
		if ( ! is_email( $email ) ) {
			return self::err( 'ftt_clip_email', __( 'Please enter a valid email address.', 'film-transfer-toolkit' ) );
		}
		$fps = FTT_Timecode::norm( (string) ( $b['fps'] ?? '' ) );
		if ( null === $fps ) {
			return self::err( 'fwcc_bad_fps', __( 'Unsupported frame rate.', 'film-transfer-toolkit' ) );
		}
		$clip_id = (string) ( $b['clip_id'] ?? '' );
		if ( ! preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $clip_id ) ) {
			return self::err( 'fwcc_bad_clip', __( 'Invalid clip id.', 'film-transfer-toolkit' ) );
		}
		$raw = isset( $b['clips'] ) && is_array( $b['clips'] ) ? array_values( $b['clips'] ) : array();
		if ( ! $raw ) {
			return self::err( 'ftt_clip_empty', __( 'Clip list is empty.', 'film-transfer-toolkit' ) );
		}
		if ( count( $raw ) > self::MAX_CLIPS ) {
			/* translators: %d: maximum clips */
			return self::err( 'ftt_clip_many', sprintf( __( 'Send at most %d clips in one request.', 'film-transfer-toolkit' ), self::MAX_CLIPS ) );
		}
		$clips = array();
		$total = 0;
		foreach ( $raw as $i => $c ) {
			if ( ! is_array( $c ) || ! isset( $c['in'], $c['out'] ) || ! is_numeric( $c['in'] ) || ! is_numeric( $c['out'] ) ) {
				return self::err( 'fwcc_bad_input', __( 'In and out must be numbers (seconds).', 'film-transfer-toolkit' ) );
			}
			$in_f  = FTT_Timecode::seconds_to_frames( (float) $c['in'], $fps );
			$out_f = FTT_Timecode::seconds_to_frames( (float) $c['out'], $fps );
			if ( $in_f < 0 || $out_f <= $in_f ) {
				return self::err( 'fwcc_bad_range', __( 'Out must be after In.', 'film-transfer-toolkit' ) );
			}
			$dur = FTT_Timecode::frames_to_seconds( $out_f - $in_f, $fps );
			if ( $dur > $card['max_seconds'] ) {
				return self::err( 'fwcc_too_long', __( 'Clip is longer than the maximum allowed.', 'film-transfer-toolkit' ) );
			}
			$seq   = isset( $c['seq'] ) && is_numeric( $c['seq'] ) ? max( 1, min( 999, (int) $c['seq'] ) ) : $i + 1;
			$price = FTT_Clip_Rate_Card::price_cents( $dur );
			$total += $price;
			$clips[] = array(
				'ref'         => $clip_id . '-' . sprintf( '%02d', $seq ),
				'name'        => FTT_Util::text( $c['name'] ?? '', 120 ),
				'in_frame'    => $in_f,
				'out_frame'   => $out_f,
				'in_tc'       => FTT_Timecode::frames_to_tc( $in_f, $fps ),
				'out_tc'      => FTT_Timecode::frames_to_tc( $out_f, $fps ),
				'seconds'     => round( $dur, 3 ),
				'price_cents' => $price,
			);
		}
		$page_id = absint( $b['page_id'] ?? 0 );
		$url     = esc_url_raw( (string) ( $b['page_url'] ?? '' ) );
		if ( $url && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$url = ''; // Only keep links to this site.
		}
		return array(
			'name'        => $name,
			'email'       => $email,
			'note'        => FTT_Util::textarea( $b['note'] ?? '', 2000 ),
			'clip_id'     => $clip_id,
			'title'       => FTT_Util::text( $b['title'] ?? '', 160 ),
			'fps'         => $fps,
			'page_id'     => $page_id,
			'page_url'    => $url ? $url : ( $page_id ? (string) get_permalink( $page_id ) : '' ),
			'clips'       => $clips,
			'total_cents' => $total,
			'rate_card'   => $card['label'],
		);
	}

	public static function request( WP_REST_Request $req ) {
		$b = $req->get_json_params();
		$b = is_array( $b ) ? $b : array();
		if ( ! wp_verify_nonce( (string) ( $b['nonce'] ?? '' ), 'ftt_clip_request' ) ) {
			return self::err( 'ftt_clip_nonce', __( 'This page has expired. Reload it and try again.', 'film-transfer-toolkit' ), 403 );
		}
		if ( '' !== trim( (string) ( $b['website'] ?? '' ) ) ) {
			return FTT_Util::nocache_rest( array( 'ok' => true, 'message' => __( 'Thank you. Your clip request was sent.', 'film-transfer-toolkit' ) ) ); // Honeypot: pretend success.
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'ftt_clip_rq_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 5 ) {
			return self::err( 'fwcc_rate', __( 'Too many requests. Please try again later.', 'film-transfer-toolkit' ), 429 );
		}
		$r = self::validate( $b );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		$id = wp_insert_post( array(
			/* translators: 1: customer name, 2: number of clips, 3: clip id */
			'post_title'  => sprintf( __( 'Clip request from %1$s: %2$d clip(s) of %3$s', 'film-transfer-toolkit' ), $r['name'], count( $r['clips'] ), $r['clip_id'] ),
			'post_type'   => self::CPT,
			'post_status' => 'private',
		), true );
		if ( is_wp_error( $id ) ) {
			return self::err( 'ftt_clip_save', __( 'Could not save the request. Please try again.', 'film-transfer-toolkit' ), 500 );
		}
		update_post_meta( $id, self::META, wp_slash( wp_json_encode( $r ) ) );
		$g  = FTT_Plugin::general();
		if ( '' !== $g['notify_email'] ) {
			FTT_Mail::send( $g['notify_email'], sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), get_the_title( $id ) ), self::text( $r ) . "\n\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ), 'clip-request' );
		}
		do_action( 'ftt/clip/request_saved', $id, $r );
		return FTT_Util::nocache_rest( array( 'ok' => true, 'id' => $id, 'message' => __( 'Thank you. Your clip request was sent. We will reply by email with the final price.', 'film-transfer-toolkit' ) ) );
	}

	public static function money( $c ) {
		return '$' . number_format( $c / 100, 2 );
	}

	/** Plain-text summary used in the notify email. */
	public static function text( array $r ) {
		$l   = array();
		$l[] = sprintf( 'From: %s <%s>', $r['name'], $r['email'] );
		$l[] = sprintf( 'Footage: %s (%s), timecode %s fps', $r['title'], $r['clip_id'], FTT_Timecode::LABELS[ $r['fps'] ] ?? $r['fps'] );
		if ( $r['page_url'] ) {
			$l[] = 'Page: ' . $r['page_url'];
		}
		$l[] = '';
		foreach ( $r['clips'] as $c ) {
			$l[] = sprintf( '%s  %s  IN %s  OUT %s  %.3fs  est. %s', $c['ref'], $c['name'], $c['in_tc'], $c['out_tc'], $c['seconds'], self::money( $c['price_cents'] ) );
		}
		$l[] = '';
		$l[] = 'Estimated total: ' . self::money( $r['total_cents'] ) . ' (' . $r['rate_card'] . ')';
		if ( '' !== $r['note'] ) {
			$l[] = '';
			$l[] = 'Note: ' . $r['note'];
		}
		return implode( "\n", $l );
	}

	public static function meta_box() {
		add_meta_box( 'ftt-clip-request', __( 'Request', 'film-transfer-toolkit' ), array( __CLASS__, 'render_box' ), self::CPT, 'normal', 'high' );
	}

	public static function render_box( $post ) {
		$r = json_decode( (string) get_post_meta( $post->ID, self::META, true ), true );
		if ( ! is_array( $r ) ) {
			echo '<p>' . esc_html__( 'No details stored.', 'film-transfer-toolkit' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html( $r['name'] ) . '</strong> &lt;<a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a>&gt;</p>';
		echo '<p>' . esc_html( $r['title'] . ' (' . $r['clip_id'] . '), ' . ( FTT_Timecode::LABELS[ $r['fps'] ] ?? $r['fps'] ) . ' fps' ) . ( $r['page_url'] ? ' &middot; <a href="' . esc_url( $r['page_url'] ) . '">' . esc_html__( 'page', 'film-transfer-toolkit' ) . '</a>' : '' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Clip', 'film-transfer-toolkit' ) . '</th><th>' . esc_html__( 'Name', 'film-transfer-toolkit' ) . '</th><th>In</th><th>Out</th><th>' . esc_html__( 'Seconds', 'film-transfer-toolkit' ) . '</th><th>' . esc_html__( 'Estimate', 'film-transfer-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( (array) $r['clips'] as $c ) {
			echo '<tr><td>' . esc_html( $c['ref'] ) . '</td><td>' . esc_html( $c['name'] ) . '</td><td><code>' . esc_html( $c['in_tc'] ) . '</code></td><td><code>' . esc_html( $c['out_tc'] ) . '</code></td><td>' . esc_html( number_format( (float) $c['seconds'], 3 ) ) . '</td><td>' . esc_html( self::money( (int) $c['price_cents'] ) ) . '</td></tr>';
		}
		echo '</tbody><tfoot><tr><th colspan="5">' . esc_html__( 'Estimated total', 'film-transfer-toolkit' ) . '</th><th>' . esc_html( self::money( (int) $r['total_cents'] ) ) . '</th></tr></tfoot></table>';
		echo '<p class="description">' . esc_html( $r['rate_card'] ) . '</p>';
		if ( '' !== $r['note'] ) {
			echo '<p><strong>' . esc_html__( 'Note:', 'film-transfer-toolkit' ) . '</strong> ' . esc_html( $r['note'] ) . '</p>';
		}
	}
}
