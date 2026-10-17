<?php
/**
 * Calculator module (ported from Film Calculator 1.1.1).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/options.php';
require_once __DIR__ . '/shortcode.php';
if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}
