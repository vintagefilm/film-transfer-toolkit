<?php
/**
 * Plugin Name:       Film Transfer Toolkit
 * Description:       Film calculator, reel size estimator, price table, multi-reel estimate builder with quote requests, and optional clip selector and quote-comparison (RFQ) modules. Enable modules under Film Transfer Toolkit > Modules.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Vintage Film Transfer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       film-transfer-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FTT_VERSION', '1.0.0' );
define( 'FTT_FILE', __FILE__ );
define( 'FTT_DIR', plugin_dir_path( __FILE__ ) );
define( 'FTT_URL', plugin_dir_url( __FILE__ ) );

require_once FTT_DIR . 'includes/core/class-ftt-pricing.php';
require_once FTT_DIR . 'includes/core/class-ftt-timecode.php';
require_once FTT_DIR . 'includes/class-ftt-util.php';
require_once FTT_DIR . 'includes/class-ftt-mail.php';
require_once FTT_DIR . 'includes/class-ftt-price-table.php';
require_once FTT_DIR . 'includes/class-ftt-plugin.php';

register_activation_hook( __FILE__, array( 'FTT_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'FTT_Plugin', 'boot' ), 20 );
