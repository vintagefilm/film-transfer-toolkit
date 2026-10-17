<?php
/**
 * All toolkit email goes through FTT_Mail::send().
 * Mode "log" (the default) never sends: each message is stored in the admin Mail log instead.
 * Mode "send" uses wp_mail(). Defining FTT_MAIL_LOG_ONLY as true in wp-config.php forces "log"
 * whatever the setting says (use it on staging copies).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Mail {
	const LOG = 'ftt_mail_log';
	const MAX = 100;

	public static function mode() {
		if ( defined( 'FTT_MAIL_LOG_ONLY' ) && FTT_MAIL_LOG_ONLY ) {
			return 'log';
		}
		$g = FTT_Plugin::general();
		return 'send' === $g['mail_mode'] ? 'send' : 'log';
	}

	/**
	 * @return bool True when sent (send mode) or logged (log mode).
	 */
	public static function send( $to, $subject, $body, $context = '' ) {
		$to      = sanitize_email( $to );
		$subject = wp_strip_all_tags( (string) $subject );
		$body    = (string) $body;
		if ( ! is_email( $to ) ) {
			self::log( $to, $subject, $body, $context, 'skipped: no valid recipient' );
			return false;
		}
		if ( 'send' !== self::mode() ) {
			self::log( $to, $subject, $body, $context, 'logged (not sent)' );
			return true;
		}
		$ok = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		self::log( $to, $subject, $body, $context, $ok ? 'sent' : 'send failed' );
		return (bool) $ok;
	}

	private static function log( $to, $subject, $body, $context, $result ) {
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array( 'time' => time(), 'to' => (string) $to, 'subject' => $subject, 'body' => $body, 'context' => (string) $context, 'result' => $result ) );
		update_option( self::LOG, array_slice( $log, 0, self::MAX ), false );
	}

	public static function entries() {
		$log = get_option( self::LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	public static function clear() {
		update_option( self::LOG, array(), false );
	}
}
