<?php
/**
 * php tools/surface.php <plugin dir>  - prints (JSON) every public identifier other code or saved content can depend on:
 * shortcodes, block names, option keys, hooks, script/style handles, REST routes, post types, admin page slugs,
 * WP-CLI commands, PHP functions/classes/constants, data-* attributes and CSS classes. tests/v140-tests.php compares the
 * current tree against tests/fixtures/surface-1.3.0.json, so a rename can never break existing pages or add-ons.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'ABSPATH' ) ) { exit; }
function ftt_surface( $dir ) {
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	$out = array();
	$add = function ( $k, $v ) use ( &$out ) { $out[ $k ][ $v ] = true; };
	foreach ( $it as $f ) {
		$rel = substr( $f->getPathname(), strlen( rtrim( $dir, '/' ) ) + 1 );
		if ( preg_match( '#^(tests|tools|e2e|docs)/#', $rel ) || ! preg_match( '/\.(php|js|css)$/', $rel ) ) { continue; }
		$s = file_get_contents( $f->getPathname() );
		$ext = pathinfo( $rel, PATHINFO_EXTENSION );
		if ( 'php' === $ext ) {
			$pat = array(
				'shortcode'  => "/add_shortcode\(\s*'([^']+)'/",
				'option'     => "/(?:get_option|update_option|add_option|delete_option|register_setting\(\s*'[^']+',)\s*\(?\s*'((?:ftt|filmcalc)[a-z0-9_]*)'/",
				'hook'       => "/(?:apply_filters|do_action)\(\s*'([^']+)'/",
				'handle'     => "/wp_(?:register|enqueue)_(?:script|style)\(\s*'([^']+)'/",
				'rest'       => "/register_rest_route\(\s*'([^']+)',\s*'([^']+)'/",
				'post_type'  => "/register_post_type\(\s*'([^']+)'/",
				'function'   => "/^function\s+([a-z0-9_]+)\s*\(/mi",
				'class'      => "/^(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m",
				'constant'   => "/define\(\s*'([A-Z0-9_]+)'/",
				'const'      => "/^\s*const\s+([A-Z0-9_]+)\s*=/m",
				'page_slug'  => "/add_(?:sub)?menu_page\((?:[^;]*?),\s*'(ftt[a-z0-9_-]*)'/s",
				'cli'        => "/WP_CLI::add_command\(\s*'([^']+)'/",
				'meta'       => "/'(_ftt_[a-z0-9_]+|_fw_[a-z0-9_]+)'/",
			);
			foreach ( $pat as $k => $re ) {
				if ( preg_match_all( $re, $s, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $x ) { $add( $k, 'rest' === $k ? $x[1] . $x[2] : $x[1] ); }
				}
			}
			// Any ftt_* / filmcalc* string literal: option keys (also those held in class constants), transients, nonces, meta keys.
			if ( preg_match_all( "/'((?:ftt_|filmcalc)[a-z0-9_]+)'/", $s, $m ) ) { foreach ( $m[1] as $v ) { $add( 'ftt_string', $v ); } }
			if ( preg_match_all( "/'block'\s*=>\s*'(ftt\/[a-z0-9-]+)'/", $s, $m ) ) { foreach ( $m[1] as $v ) { $add( 'block', $v ); } }
			if ( preg_match_all( "/'slug'\s*=>\s*'([a-z0-9-]+)'/", $s, $m ) && false !== strpos( $s, 'block_categories_all' ) ) { foreach ( $m[1] as $v ) { $add( 'block_category', $v ); } }
			if ( preg_match_all( "/const CATEGORY = '([^']+)'/", $s, $m ) ) { foreach ( $m[1] as $v ) { $add( 'block_category', $v ); } }
		}
		if ( 'php' === $ext || 'js' === $ext ) {
			if ( preg_match_all( '/\b(data-[a-z][a-z0-9-]*)/', $s, $m ) ) { foreach ( $m[1] as $v ) { $add( 'data_attr', $v ); } }
		}
		if ( 'php' === $ext && preg_match_all( '/class="([^"<>{}$]+)"/', $s, $m ) ) {
			foreach ( $m[1] as $c ) { foreach ( preg_split( '/\s+/', trim( $c ) ) as $v ) { if ( preg_match( '/^(ftt|filmcalc|fwcc|fc)[a-z0-9_-]*$/', $v ) ) { $add( 'css_class', $v ); } } }
		}
		if ( 'css' === $ext && preg_match_all( '/\.((?:ftt|filmcalc|fwcc)[a-z0-9_-]*)/', $s, $m ) ) { foreach ( $m[1] as $v ) { $add( 'css_class', $v ); } }
	}
	ksort( $out );
	foreach ( $out as $k => $v ) { $v = array_keys( $v ); sort( $v ); $out[ $k ] = $v; }
	return $out;
}
if ( PHP_SAPI === 'cli' && isset( $argv[1] ) && realpath( $argv[0] ) === __FILE__ ) {
	echo json_encode( ftt_surface( $argv[1] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
}
