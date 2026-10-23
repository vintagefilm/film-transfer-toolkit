<?php
/**
 * Clip selector module (ported from FW Clip Cart 0.4.0 prototype). Since 1.3.0 it works under any video player and
 * hands clips to FluentCart only when FluentCart is active and a clip product is set; otherwise visitors send a clip request.
 * Loaded when enabled. If the FW Clip Cart prototype is also active (1.4.1+), it keeps [fw_clip_cart] and the FluentCart
 * cart hooks (same meta keys and HMAC key), and this module adds [ftt_clip_selector] and the block.
 * Keeps the prototype's product meta keys (_fw_clip_cart_enabled, _fw_clip_id, _fw_fps, ...) and HMAC key so
 * cart lines made by the prototype still validate after switching.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'FTT_CLIP_DIR', __DIR__ . '/' );
define( 'FTT_CLIP_URL', FTT_URL . 'modules/clip-selector/' );

require_once FTT_CLIP_DIR . 'class-rate-card.php';
require_once FTT_CLIP_DIR . 'class-cutlist.php';
require_once FTT_CLIP_DIR . 'class-clip.php';
require_once FTT_CLIP_DIR . 'class-rest.php';
require_once FTT_CLIP_DIR . 'class-fluentcart-hooks.php';
require_once FTT_CLIP_DIR . 'class-clip-settings.php';
require_once FTT_CLIP_DIR . 'class-clip-request.php';
require_once FTT_CLIP_DIR . 'class-clip-resume.php';
require_once FTT_CLIP_DIR . 'class-shortcode.php';
require_once FTT_CLIP_DIR . 'admin.php';

FTT_Clip_Shortcode::init();
FTT_Clip_Rest::init();
FTT_Clip_Request::init();
FTT_Clip_Settings::init();
FTT_Clip_Resume::init();
if ( class_exists( '\FluentCart\App\Helpers\CartHelper' ) && ! defined( 'FTT_CLIP_SHARED_FWCC' ) ) {
	FTT_Clip_FluentCart_Hooks::init();
}
