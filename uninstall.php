<?php
/**
 * Removes toolkit settings when the plugin is deleted. Stored quote requests, clip requests, RFQ requests and providers are
 * kept unless FTT_UNINSTALL_DELETE_DATA is true in wp-config.php (they may be business records).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( array( 'ftt_modules', 'ftt_general', 'ftt_price_table', 'ftt_calc_options', 'ftt_clip_rate_card', 'ftt_clip_help_html', 'ftt_rfq', 'ftt_mail_log', 'ftt_setup', 'ftt_pages', 'ftt_storage', 'ftt_clip_settings', 'ftt_clip_retired_login' ) as $o ) {
	delete_option( $o );
}
delete_transient( 'ftt_setup_redirect' );
delete_metadata( 'user', 0, 'ftt_dismiss_filmcalc', '', true );
// Pages created by the setup wizard, and the draft test bench page, are ordinary pages and are left in place.
if ( defined( 'FTT_UNINSTALL_DELETE_DATA' ) && FTT_UNINSTALL_DELETE_DATA ) {
	foreach ( array( 'ftt_estimate', 'ftt_rfq', 'ftt_rfq_quote', 'ftt_provider', 'ftt_clip_request' ) as $pt ) {
		foreach ( get_posts( array( 'post_type' => $pt, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_post( $id, true );
		}
	}
}
