<?php
/**
 * Test bench (1.3.0): one permanently-draft page holding every toolkit tool (plus the standalone [film_calculator]),
 * rebuilt and checked with WP-CLI as part of each release:
 *   wp ftt testbench            create the page, or rebuild its content
 *   wp ftt testbench --check    render it server-side and check every tool (exit 1 on failure)
 * The page is marked with the _ftt_test_bench meta: it is kept a draft (publishing is refused), marked noindex,
 * and kept out of navigation menus. When the Clip selector module is off, editors still see a preview-only
 * selector there (nothing can be sent).
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Testbench {
	const META  = '_ftt_test_bench';
	const TITLE = 'FTT Test Bench (draft - do not publish)';
	const SLUG  = 'ftt-test-bench';

	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'keep_draft' ), 99, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 99 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'no_menu' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'revert_publish' ), 1, 3 );
	}

	/** wp_publish_post() and the scheduler skip wp_insert_post_data: put the bench straight back to draft. */
	public static function revert_publish( $new, $old, $post ) {
		if ( in_array( $new, array( 'publish', 'future', 'private' ), true ) && $post && 'page' === $post->post_type && self::is_bench( $post->ID ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $post->ID );
			$post->post_status = 'draft';
		}
	}

	public static function page_id() {
		$ids = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ), 'meta_key' => self::META, 'fields' => 'ids', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
		return $ids ? (int) $ids[0] : 0;
	}

	public static function is_bench( $id ) {
		return $id && '' !== (string) get_post_meta( (int) $id, self::META, true );
	}

	/** Refuse to publish (or schedule, or make private) the test bench: it always stays a draft. */
	public static function keep_draft( $data, $postarr ) {
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id && self::is_bench( $id ) && ! in_array( $data['post_status'], array( 'draft', 'trash', 'auto-draft', 'inherit' ), true ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public static function robots( $robots ) {
		if ( is_singular() && self::is_bench( get_queried_object_id() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
		}
		return $robots;
	}

	/** A menu item pointing at the test bench is removed as soon as it is saved. */
	public static function no_menu( $menu_id, $item_id, $args ) {
		if ( isset( $args['menu-item-object-id'], $args['menu-item-object'] ) && 'page' === $args['menu-item-object'] && self::is_bench( (int) $args['menu-item-object-id'] ) ) {
			wp_delete_post( $item_id, true );
		}
	}

	/** May the current request show a preview-only clip selector? */
	public static function clip_preview_allowed() {
		$id      = (int) get_the_ID();
		$allowed = $id && current_user_can( 'edit_post', $id ) && ( is_preview() || self::is_bench( $id ) );
		return (bool) apply_filters( 'ftt/clip/preview_allowed', $allowed, $id );
	}

	/** Sample video for the clip selector rows: an existing video in the Media Library (never uploads one). */
	public static function sample_video_url() {
		$v = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'video', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'suppress_filters' => true ) );
		return $v ? (string) wp_get_attachment_url( (int) $v[0] ) : '';
	}

	private static function h( $text, $level = 2 ) {
		return '<!-- wp:heading' . ( 2 === $level ? '' : ' {"level":' . $level . '}' ) . ' --><h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '><!-- /wp:heading -->' . "\n\n";
	}

	private static function p( $html ) {
		return '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->' . "\n\n";
	}

	private static function sc( $code ) {
		return '<!-- wp:shortcode -->' . $code . '<!-- /wp:shortcode -->' . "\n\n";
	}

	/** Block markup for the whole bench. $video: URL of an HTML5 sample video; $youtube: a YouTube watch URL. */
	public static function content( $video = '', $youtube = '' ) {
		$c  = self::p( esc_html( sprintf( 'Internal test page for FTT %s. Keep it a DRAFT: do not publish it or add it to a menu. Rebuilt with "wp ftt testbench" and checked with "wp ftt testbench --check" at every release.', FTT_VERSION ) ) );
		$st = FTT_Plugin::$status;
		foreach ( FTT_Registry::tools() as $key => $t ) {
			$state = 'on' === ( $st[ $key ] ?? 'off' ) ? 'module on' : 'module OFF on this site';
			$c    .= self::h( $t['title'] . ' (' . $state . ')' );
			if ( 'clips' === $key ) {
				$c .= self::p( esc_html( 'Clip selector under a plain HTML5 video and under a YouTube embed. With the module off, only logged-in editors previewing this page see it, marked "Preview only".' ) );
				if ( $video ) {
					$c .= '<!-- wp:video --><figure class="wp-block-video"><video controls preload="metadata" src="' . esc_url( $video ) . '"></video></figure><!-- /wp:video -->' . "\n\n";
					$c .= self::sc( '[ftt_clip_selector player="html5" clip_id="BENCH-HTML5" title="Test bench sample video" fps="29.97df"]' );
				}
				if ( ! $video && ! $youtube ) {
					$c .= self::p( esc_html( 'No sample video was given (wp ftt testbench --video=URL or --youtube=URL), so this selector has no player.' ) );
					$c .= self::sc( '[ftt_clip_selector clip_id="BENCH-NOVIDEO" title="Test bench (no video)"]' );
				}
				if ( $youtube ) {
					$c .= '<!-- wp:embed {"url":"' . esc_url( $youtube ) . '","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->' . "\n"
						. '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">' . "\n" . esc_url( $youtube ) . "\n" . '</div></figure>' . "\n" . '<!-- /wp:embed -->' . "\n\n";
					$c .= self::sc( '[ftt_clip_selector player="youtube" clip_id="BENCH-YT" title="Test bench YouTube sample" fps="29.97df"]' );
				}
				continue;
			}
			foreach ( $t['embeds'] as $e ) {
				if ( count( $t['embeds'] ) > 1 ) {
					$c .= self::h( $e['label'], 3 );
				}
				$c .= FTT_Registry::block_markup( $e ) . "\n\n";
			}
			if ( 'calculator' === $key ) {
				$c .= self::h( 'Film calculator with fields switched off (meters, frames, seconds)', 3 );
				$c .= '<!-- wp:ftt/calculator {"meters":"no","frames":"no","seconds":"no","title":"Calculator: feet, time and film speed only"} /-->' . "\n\n";
			}
		}
		$c .= self::h( 'Standalone Film Calculator plugin (film_calculator shortcode)' );
		$c .= self::p( esc_html( defined( 'FILMCALC_VERSION' ) ? 'Rendered by the standalone Film Calculator plugin ' . FILMCALC_VERSION . '.' : 'The standalone plugin is not active, so the toolkit calculator renders this shortcode.' ) );
		$c .= self::sc( '[film_calculator]' );
		return $c;
	}

	/** Create or rebuild the bench page. Returns the page ID or WP_Error. */
	public static function build( $video = null, $youtube = '' ) {
		$video = null === $video ? self::sample_video_url() : $video;
		$id    = self::page_id();
		$arr   = array(
			'post_type'      => 'page',
			'post_status'    => 'draft',
			'post_title'     => self::TITLE,
			'post_name'      => self::SLUG,
			'post_content'   => self::content( $video, $youtube ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);
		if ( $id ) {
			$arr['ID'] = $id;
			$r = wp_update_post( wp_slash( $arr ), true );
		} else {
			$r = wp_insert_post( wp_slash( $arr ), true );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		update_post_meta( $r, self::META, FTT_VERSION );
		update_post_meta( $r, '_yoast_wpseo_meta-robots-noindex', '1' );
		update_post_meta( $r, '_yoast_wpseo_meta-robots-nofollow', '1' );
		return (int) $r;
	}

	/**
	 * Render the bench server-side (as the current user) and check it. Returns a list of [name, pass, detail].
	 */
	public static function check( $id = 0 ) {
		$id  = $id ? (int) $id : self::page_id();
		$out = array();
		$ok  = function ( $n, $c, $d = '' ) use ( &$out ) { $out[] = array( $n, (bool) $c, (string) $d ); };
		$p   = $id ? get_post( $id ) : null;
		$ok( 'bench page exists', (bool) $p, $id );
		if ( ! $p ) {
			return $out;
		}
		$ok( 'status is draft', 'draft' === $p->post_status, $p->post_status );
		$ok( 'marked as test bench', self::is_bench( $id ) );
		global $wpdb;
		$menu = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->postmeta} t ON t.post_id = pm.post_id AND t.meta_key = '_menu_item_object' AND t.meta_value = 'page' WHERE pm.meta_key = '_menu_item_object_id' AND pm.meta_value = %s", (string) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok( 'not in any menu', 0 === $menu, $menu );
		$GLOBALS['post'] = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $p );
		$GLOBALS['wp_query']->is_singular = true;
		$GLOBALS['wp_query']->queried_object    = $p;
		$GLOBALS['wp_query']->queried_object_id = $id;
		$robots = apply_filters( 'wp_robots', array() );
		$ok( 'robots noindex,nofollow', ! empty( $robots['noindex'] ) && ! empty( $robots['nofollow'] ), wp_json_encode( $robots ) );
		add_filter( 'ftt/clip/preview_allowed', '__return_true' );
		$html = apply_filters( 'the_content', $p->post_content );
		$ok( 'no raw shortcodes left', ! preg_match( '/\[(?:ftt_[a-z_]+|film_calculator|fw_clip_cart)[\s\]]/', $html ) );
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			$on = 'on' === ( FTT_Plugin::$status[ $e['module'] ] ?? '' );
			if ( 'clips' === $e['module'] ) {
				$n = preg_match_all( '/class="(?:[^"]*\\s)?fwcc(?:\\s[^"]*)?"/', $html );
				$ok( 'clip selector rendered' . ( $on ? '' : ' (preview only, module off)' ), $n >= 1 && ( $on || false !== strpos( $html, 'data-demo="1"' ) ), $n . ' selector(s)' );
				continue;
			}
			if ( ! $on ) {
				$ok( $tag . ' skipped (module off)', true );
				continue;
			}
			$one = FTT_Blocks::render( $tag, array() );
			$ok( $tag . ' renders', strlen( trim( $one ) ) > 50 && false !== strpos( $html, substr( trim( $one ), 0, 30 ) ), strlen( $one ) . ' bytes' );
		}
		if ( 'on' === ( FTT_Plugin::$status['calculator'] ?? '' ) ) {
			$ok( 'calculator with toggles hides meters and frames', false !== strpos( $html, 'data-hide="meters frames"' ) );
		}
		if ( defined( 'FILMCALC_VERSION' ) ) {
			$ok( 'standalone [film_calculator] rendered once', 1 === substr_count( $html, 'data-filmcalc ' ), substr_count( $html, 'data-filmcalc ' ) . ' / ' . substr_count( $html, 'data-ftt-calc' ) );
		} elseif ( 'on' === ( FTT_Plugin::$status['calculator'] ?? '' ) ) {
			// 1.5.0: the bench may use the [film_calculator]/[ftt_calculator] shortcode or the calculator block (editors may change the bench).
			$src   = (string) get_post_field( 'post_content', $id );
			$uses  = array_filter( array( 'shortcode' => (bool) preg_match( '/\[(?:film_calculator|ftt_calculator)[\s\]]/', $src ), 'block' => false !== strpos( $src, '<!-- wp:ftt/calculator' ) ) );
			$n     = substr_count( $html, 'data-ftt-calc' );
			$ok( 'calculator rendered by the toolkit (shortcode or block; standalone plugin inactive)', $uses && $n >= 1 && ! preg_match( '/\[film_calculator[\s\]]/', $html ), implode( '+', array_keys( $uses ) ) . ': ' . $n . ' rendered' );
		}
		if ( defined( 'FILMCALC_VERSION' ) ) {
			$ok( 'standalone version', true, FILMCALC_VERSION );
		}
		remove_filter( 'ftt/clip/preview_allowed', '__return_true' );
		wp_reset_postdata();
		return $out;
	}
}
