<?php
/**
 * FTT 1.5.0 admin structure for newcomers: a short menu (Dashboard, Tools, Settings, Help), one shared page header,
 * tool cards, a Tools page (switch tools on/off and find their requests) and Settings in one tab per tool, with rarely
 * used options under "Advanced". Old admin URLs redirect to their new place. No setting key changed.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Admin_UX {

	/** Old page slug => new location (page, tab). Extra query args (ftt_msg, settings-updated, ...) are kept. */
	const REDIRECTS = array(
		'ftt-calculator' => array( 'ftt-settings', 'calculator' ),
		'ftt-prices'     => array( 'ftt-settings', 'prices' ),
		'ftt-storage'    => array( 'ftt-settings', 'storage' ),
		'ftt-clips'      => array( 'ftt-settings', 'clips' ),
		'ftt-rfq'        => array( 'ftt-settings', 'rfq' ),
		'ftt-mail'       => array( 'ftt-settings', 'email' ),
	);

	/** The four menu items, in order. */
	const MENU = array( 'ftt', 'ftt-tools', 'ftt-settings', 'ftt-help' );

	public static function init() {
		// The setup wizard is a hidden page; give it a title (WordPress leaves it empty for parentless pages).
		add_action( 'load-admin_page_ftt-setup', function () { $GLOBALS['title'] = __( 'FTT setup wizard', 'film-transfer-toolkit' ); } ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		// admin_menu, not admin_init: wp-admin/menu.php refuses unregistered pages (403) before admin_init runs.
		add_action( 'admin_menu', array( __CLASS__, 'redirect_old_pages' ), 0 );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ), 99 );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ), 99 );
	}

	public static function url( $page, $tab = '', $args = array() ) {
		return add_query_arg( array_merge( array_filter( array( 'page' => $page, 'tab' => $tab ) ), $args ), admin_url( 'admin.php' ) );
	}

	public static function settings_url( $tab = '' ) {
		return self::url( 'ftt-settings', $tab );
	}

	/** Where an old admin.php?page=... URL now lives, or '' (also used by tests). */
	public static function redirect_target( $page, $query = array() ) {
		if ( ! isset( self::REDIRECTS[ $page ] ) ) {
			return '';
		}
		list( $to, $tab ) = self::REDIRECTS[ $page ];
		unset( $query['page'], $query['tab'] );
		return self::url( $to, $tab, array_map( 'rawurlencode', array_map( 'strval', $query ) ) );
	}

	public static function redirect_old_pages() {
		// $pagenow is unreliable under some servers (PHP's built-in server, some proxies); the old slugs are unique to FTT.
		if ( wp_doing_ajax() || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
			return;
		}
		$query  = array_map( 'sanitize_text_field', wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$target = self::redirect_target( (string) $query['page'], $query );
		if ( $target && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/** Keep only Dashboard, Tools, Settings, Help in the menu. Hidden pages (setup wizard, request lists) still open. */
	public static function trim_menu() {
		global $submenu;
		if ( empty( $submenu['ftt'] ) ) {
			return;
		}
		$keep = array();
		foreach ( $submenu['ftt'] as $item ) {
			$i = array_search( (string) ( $item[2] ?? '' ), self::MENU, true );
			if ( false !== $i ) {
				$keep[ $i ] = $item;
			}
		}
		ksort( $keep );
		$submenu['ftt'] = array_values( $keep ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- our own submenu.
	}

	/** Request lists (post types) open under FTT > Tools. */
	public static function parent_file( $file ) {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( isset( $_GET['page'] ) && 'ftt-setup' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'ftt';
		}
		return $s && in_array( $s->post_type, self::record_types(), true ) ? 'ftt' : $file;
	}

	public static function submenu_file( $file ) {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $s && in_array( $s->post_type, self::record_types(), true ) ) {
			return 'ftt-tools';
		}
		if ( isset( $_GET['page'] ) && 'ftt-setup' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'ftt';
		}
		return $file;
	}

	private static function record_types() {
		return array( 'ftt_estimate', 'ftt_clip_request', 'ftt_provider' );
	}

	/* --------------------------------------------------------- shared header */

	/** The same header on every FTT admin page: "FTT › Page", a one-line intro, and the four sections as buttons. */
	public static function header( $title, $intro = '', $current = '' ) {
		$items = array(
			'ftt'          => __( 'Dashboard', 'film-transfer-toolkit' ),
			'ftt-tools'    => __( 'Tools', 'film-transfer-toolkit' ),
			'ftt-settings' => __( 'Settings', 'film-transfer-toolkit' ),
			'ftt-help'     => __( 'Help', 'film-transfer-toolkit' ),
		);
		echo '<div class="ftt-header">';
		echo '<h1 class="ftt-header__title"><span class="ftt-header__brand">' . esc_html__( 'FTT', 'film-transfer-toolkit' ) . '</span>';
		if ( $title ) {
			echo ' <span class="ftt-header__sep" aria-hidden="true">›</span> ' . esc_html( $title );
		}
		echo ' <small class="ftt-header__ver">v' . esc_html( FTT_VERSION ) . '</small></h1>';
		echo '<nav class="ftt-header__nav" aria-label="' . esc_attr__( 'FTT sections', 'film-transfer-toolkit' ) . '">';
		foreach ( $items as $slug => $label ) {
			$on = $slug === $current;
			echo '<a class="button' . ( $on ? ' button-primary' : '' ) . '" href="' . esc_url( self::url( $slug ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a> ';
		}
		echo '</nav></div>';
		if ( $intro ) {
			echo '<p class="ftt-intro">' . esc_html( $intro ) . '</p>';
		}
		echo '<hr class="wp-header-end">';
	}

	/* ------------------------------------------------------------ tool facts */

	/** Plain one-line description of each tool. */
	public static function line( $k ) {
		$l = array(
			'calculator' => __( 'Turns a length of film into running time (and back) for 8mm, Super 8, 16mm and 35mm.', 'film-transfer-toolkit' ),
			'reels'      => __( 'Tells customers roughly how much film is on a reel from its size or diameter.', 'film-transfer-toolkit' ),
			'pricing'    => __( 'Shows your price list as a table on any page.', 'film-transfer-toolkit' ),
			'estimate'   => __( 'Lets customers build an estimate from your prices and send it to you.', 'film-transfer-toolkit' ),
			'storage'    => __( 'Works out how much disk space a transfer needs, per file format.', 'film-transfer-toolkit' ),
			'clips'      => __( 'Lets visitors mark In and Out points under a video and request (or buy) the clips.', 'film-transfer-toolkit' ),
			'rfq'        => __( 'Lets customers ask several providers for a quote and compare the answers.', 'film-transfer-toolkit' ),
		);
		return $l[ $k ] ?? '';
	}

	/** Settings tab for each tool ('' = no settings of its own). */
	public static function tool_tab( $k ) {
		$m = array( 'calculator' => 'calculator', 'reels' => '', 'pricing' => 'prices', 'estimate' => 'prices', 'storage' => 'storage', 'clips' => 'clips', 'rfq' => 'rfq' );
		return $m[ $k ] ?? '';
	}

	/** Lists of things a tool collects (requests, providers): label => URL. */
	public static function records( $k ) {
		$out = array();
		if ( 'on' !== ( FTT_Plugin::$status[ $k ] ?? '' ) ) {
			return $out;
		}
		if ( 'estimate' === $k && post_type_exists( 'ftt_estimate' ) ) {
			$out[ __( 'Quote requests', 'film-transfer-toolkit' ) ] = admin_url( 'edit.php?post_type=ftt_estimate' );
		}
		if ( 'clips' === $k && post_type_exists( 'ftt_clip_request' ) ) {
			$out[ __( 'Clip requests', 'film-transfer-toolkit' ) ] = admin_url( 'edit.php?post_type=ftt_clip_request' );
		}
		if ( 'rfq' === $k ) {
			$out[ __( 'RFQ requests', 'film-transfer-toolkit' ) ] = self::settings_url( 'rfq' ) . '#ftt-rfq-requests';
			if ( post_type_exists( 'ftt_provider' ) ) {
				$out[ __( 'Quote providers', 'film-transfer-toolkit' ) ] = admin_url( 'edit.php?post_type=ftt_provider' );
			}
		}
		return $out;
	}

	public static function pill( $k ) {
		$s = FTT_Plugin::$status[ $k ] ?? 'off';
		return 'on' === $s ? '<span class="ftt-pill ftt-pill--on">' . esc_html__( 'On', 'film-transfer-toolkit' ) . '</span>' : '<span class="ftt-pill">' . esc_html__( 'Off', 'film-transfer-toolkit' ) . '</span>';
	}

	/* ---------------------------------------------------------- dashboard */

	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$state = FTT_Setup::state();
		echo '<div class="wrap ftt-admin ftt-dash">';
		self::header( __( 'Dashboard', 'film-transfer-toolkit' ), __( 'Your film transfer tools at a glance. Each one can go on any page as a block or a shortcode.', 'film-transfer-toolkit' ), 'ftt' );
		if ( 'pending' === $state ) {
			echo '<div class="ftt-hero"><h2>' . esc_html__( 'Welcome! Let\'s get your tools on your site.', 'film-transfer-toolkit' ) . '</h2><p>' . esc_html__( 'The setup wizard takes about two minutes: choose your tools, create their pages, pick a look, and set your prices and email.', 'film-transfer-toolkit' ) . '</p><p><a class="button button-primary button-hero" href="' . esc_url( FTT_Setup::url() ) . '">' . esc_html__( 'Start setup', 'film-transfer-toolkit' ) . '</a></p></div>';
		}
		echo '<div class="ftt-cards">';
		foreach ( FTT_Registry::tools() as $k => $tool ) {
			self::card( $k, $tool );
		}
		echo '</div>';
		$t = FTT_Price_Table::get();
		echo '<h2 class="ftt-section">' . esc_html__( 'Good to know', 'film-transfer-toolkit' ) . '</h2><ul class="ftt-facts">';
		echo '<li><strong>' . esc_html__( 'Prices:', 'film-transfer-toolkit' ) . '</strong> ' . esc_html( $t['label'] . ' (' . strtoupper( $t['status'] ) . ')' ) . ' · <a href="' . esc_url( self::settings_url( 'prices' ) ) . '">' . esc_html__( 'Edit prices', 'film-transfer-toolkit' ) . '</a></li>';
		echo '<li><strong>' . esc_html__( 'Email:', 'film-transfer-toolkit' ) . '</strong> ' . esc_html( 'send' === FTT_Mail::mode() ? __( 'sent to customers and to you', 'film-transfer-toolkit' ) : __( 'test mode: nothing is sent, messages are kept in the Mail log', 'film-transfer-toolkit' ) ) . ' · <a href="' . esc_url( self::settings_url( 'email' ) ) . '">' . esc_html__( 'Email settings', 'film-transfer-toolkit' ) . '</a></li>';
		echo '<li><a href="' . esc_url( FTT_Setup::url() ) . '">' . esc_html__( 'Run the setup wizard', 'film-transfer-toolkit' ) . '</a> · <a href="' . esc_url( FTT_Admin::help_url() ) . '">' . esc_html__( 'Help: step-by-step pictures', 'film-transfer-toolkit' ) . '</a></li>';
		echo '</ul></div>';
	}

	/** One tool card: name + on/off, one line, "Add to a page" (shortcode and block), Settings and Help. */
	public static function card( $k, $tool ) {
		$on  = 'on' === ( FTT_Plugin::$status[ $k ] ?? '' );
		$tab = self::tool_tab( $k );
		echo '<section class="ftt-card ftt-toolcard' . ( $on ? '' : ' is-off' ) . '" data-ftt-card="' . esc_attr( $k ) . '">';
		echo '<h2 class="ftt-toolcard__title"><span class="dashicons dashicons-' . esc_attr( $tool['icon'] ) . '" aria-hidden="true"></span> ' . esc_html( $tool['title'] ) . ' ' . self::pill( $k ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput -- pill() escapes.
		echo '<p class="ftt-toolcard__line">' . esc_html( self::line( $k ) ) . '</p>';
		echo '<div class="ftt-toolcard__add"><h3>' . esc_html__( 'Add to a page', 'film-transfer-toolkit' ) . '</h3><dl>';
		foreach ( $tool['embeds'] as $e ) {
			if ( count( $tool['embeds'] ) > 1 ) {
				echo '<dt class="ftt-toolcard__part">' . esc_html( $e['label'] ) . '</dt>';
			}
			echo '<dd><span class="ftt-toolcard__how">' . esc_html__( 'Block:', 'film-transfer-toolkit' ) . '</span> ' . esc_html( sprintf( /* translators: %s: block title */ __( 'search "FTT" and pick %s', 'film-transfer-toolkit' ), $e['label'] ) ) . ' <code class="ftt-toolcard__block">' . esc_html( $e['block'] ) . '</code></dd>';
			echo '<dd><span class="ftt-toolcard__how">' . esc_html__( 'Shortcode:', 'film-transfer-toolkit' ) . '</span> ' . FTT_Admin::copy( '[' . $e['shortcode'] . ']' ) . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy().
		}
		echo '</dl></div>';
		$links = array();
		foreach ( $tool['embeds'] as $e ) {
			foreach ( FTT_Registry::find_pages( $e, 5 ) as $pg ) {
				$links[ $pg->ID ] = '<a href="' . esc_url( (string) get_edit_post_link( $pg->ID ) ) . '">' . esc_html( get_the_title( $pg ) ?: '#' . $pg->ID ) . '</a>' . ( 'publish' === $pg->post_status ? '' : ' <span class="description">(' . esc_html( $pg->post_status ) . ')</span>' );
			}
		}
		echo '<p class="ftt-toolcard__pages"><strong>' . esc_html__( 'On pages:', 'film-transfer-toolkit' ) . '</strong> ' . ( $links ? implode( ', ', $links ) : esc_html__( 'none yet', 'film-transfer-toolkit' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		echo '<p class="ftt-toolcard__links">';
		if ( ! $on ) {
			echo '<a class="button button-primary" href="' . esc_url( self::url( 'ftt-tools' ) . '#ftt-tool-' . $k ) . '">' . esc_html__( 'Turn on', 'film-transfer-toolkit' ) . '</a> ';
		}
		if ( $tab ) {
			echo '<a class="button" href="' . esc_url( self::settings_url( $tab ) ) . '">' . esc_html__( 'Settings', 'film-transfer-toolkit' ) . '</a> ';
		}
		echo '<a class="button" href="' . esc_url( FTT_Admin::help_url( $k ) ) . '">' . esc_html__( 'Help', 'film-transfer-toolkit' ) . '</a>';
		echo '</p>';
		if ( ! $tab ) {
			echo '<p class="description">' . esc_html__( 'No settings needed: choose its options per page in the block sidebar.', 'film-transfer-toolkit' ) . '</p>';
		}
		echo '</section>';
	}

	/* ---------------------------------------------------------------- tools */

	public static function render_tools() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$on = FTT_Plugin::enabled();
		echo '<div class="wrap ftt-admin ftt-tools-page">';
		self::header( __( 'Tools', 'film-transfer-toolkit' ), __( 'Switch tools on or off, and find what each tool has collected (requests, providers).', 'film-transfer-toolkit' ), 'ftt-tools' );
		settings_errors();
		echo '<form action="options.php" method="post">';
		settings_fields( 'ftt_modules' );
		echo '<table class="widefat striped ftt-modules"><thead><tr><th class="ftt-col-on">' . esc_html__( 'On', 'film-transfer-toolkit' ) . '</th><th>' . esc_html__( 'Tool', 'film-transfer-toolkit' ) . '</th><th>' . esc_html__( 'Requests and lists', 'film-transfer-toolkit' ) . '</th><th>' . esc_html__( 'More', 'film-transfer-toolkit' ) . '</th></tr></thead><tbody>';
		$tools = FTT_Registry::tools();
		foreach ( FTT_Plugin::modules() as $k => $m ) {
			$title = $tools[ $k ]['title'] ?? $m['label'];
			echo '<tr id="ftt-tool-' . esc_attr( $k ) . '"><td class="ftt-col-on"><input type="checkbox" id="ftt-mod-' . esc_attr( $k ) . '" name="ftt_modules[' . esc_attr( $k ) . ']" value="1" ' . checked( ! empty( $on[ $k ] ), true, false ) . '></td>';
			echo '<td><label for="ftt-mod-' . esc_attr( $k ) . '"><strong>' . esc_html( $title ) . '</strong></label> ' . self::pill( $k ) . '<br><span class="description">' . esc_html( self::line( $k ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'clips' === $k && FTT_Plugin::$fwcc_shared ) {
				echo '<br><span class="description">' . esc_html__( 'The FW Clip Cart prototype plugin is also active: it keeps [fw_clip_cart] and the cart hooks; [ftt_clip_selector] and the block work too.', 'film-transfer-toolkit' ) . '</span>';
			}
			echo '</td><td>';
			$r = self::records( $k );
			if ( $r ) {
				$links = array();
				foreach ( $r as $label => $u ) {
					$links[] = '<a href="' . esc_url( $u ) . '">' . esc_html( $label ) . '</a>';
				}
				echo implode( ' · ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="description">—</span>';
			}
			echo '</td><td>';
			$tab = self::tool_tab( $k );
			echo ( $tab ? '<a href="' . esc_url( self::settings_url( $tab ) ) . '">' . esc_html__( 'Settings', 'film-transfer-toolkit' ) . '</a> · ' : '' ) . '<a href="' . esc_url( FTT_Admin::help_url( $k ) ) . '">' . esc_html__( 'Help', 'film-transfer-toolkit' ) . '</a>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save changes', 'film-transfer-toolkit' ) );
		echo '</form>';
		echo '<h2 class="ftt-section">' . esc_html__( 'Other tools', 'film-transfer-toolkit' ) . '</h2><ul class="ftt-facts">';
		echo '<li><a href="' . esc_url( FTT_Setup::url() ) . '">' . esc_html__( 'Setup wizard', 'film-transfer-toolkit' ) . '</a>: ' . esc_html__( 'choose tools, create their pages and set the basics in a few steps.', 'film-transfer-toolkit' ) . '</li>';
		echo '<li><a href="' . esc_url( self::settings_url( 'email' ) . '#ftt-mail-log' ) . '">' . esc_html__( 'Mail log', 'film-transfer-toolkit' ) . '</a>: ' . esc_html__( 'every email FTT sent, or would have sent in test mode.', 'film-transfer-toolkit' ) . '</li>';
		echo '</ul></div>';
	}

	/* ------------------------------------------------------------- settings */

	/** Tabs: key => array( label, callback, tool key or '' ). */
	public static function tabs() {
		$t = array( 'email' => array( __( 'Email', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_email' ), '' ) );
		$t['calculator'] = array( __( 'Film calculator', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_calculator' ), 'calculator' );
		$t['prices']     = array( __( 'Prices', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_prices' ), '' );
		$t['storage']    = array( __( 'Storage calculator', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_storage' ), 'storage' );
		$t['clips']      = array( __( 'Clip selector', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_clips' ), 'clips' );
		$t['rfq']        = array( __( 'Quote comparison', 'film-transfer-toolkit' ), array( __CLASS__, 'tab_rfq' ), 'rfq' );
		return $t;
	}

	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'email'; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::tabs()[ $tab ] ) ? $tab : 'email';
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$tabs = self::tabs();
		$cur  = self::current_tab();
		echo '<div class="wrap ftt-admin ftt-settings-page" data-ftt-tab="' . esc_attr( $cur ) . '">';
		self::header( __( 'Settings', 'film-transfer-toolkit' ), __( 'One tab per tool. Everyday options come first; rarely needed ones are under Advanced.', 'film-transfer-toolkit' ), 'ftt-settings' );
		echo '<nav class="nav-tab-wrapper ftt-tabs" aria-label="' . esc_attr__( 'Settings tabs', 'film-transfer-toolkit' ) . '">';
		foreach ( $tabs as $k => $t ) {
			$off = $t[2] && 'on' !== ( FTT_Plugin::$status[ $t[2] ] ?? '' );
			echo '<a class="nav-tab' . ( $k === $cur ? ' nav-tab-active' : '' ) . ( $off ? ' is-off' : '' ) . '" href="' . esc_url( self::settings_url( $k ) ) . '"' . ( $k === $cur ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[0] ) . ( $off ? ' <span class="ftt-pill">' . esc_html__( 'Off', 'film-transfer-toolkit' ) . '</span>' : '' ) . '</a>';
		}
		echo '</nav><div class="ftt-tab-panel">';
		$t = $tabs[ $cur ];
		if ( $t[2] && 'on' !== ( FTT_Plugin::$status[ $t[2] ] ?? '' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( /* translators: %s: tool */ __( '%s is switched off, so its settings are hidden.', 'film-transfer-toolkit' ), $t[0] ) ) . ' <a href="' . esc_url( self::url( 'ftt-tools' ) . '#ftt-tool-' . $t[2] ) . '">' . esc_html__( 'Turn it on under Tools', 'film-transfer-toolkit' ) . '</a></p></div>';
		} else {
			call_user_func( $t[1] );
		}
		echo '</div></div>';
	}

	/** Print a 1.4 settings screen inside the tab: its own wrap and h1 are dropped (FTT's header replaces them). */
	public static function embed( $callback ) {
		ob_start();
		call_user_func( $callback );
		$h = (string) ob_get_clean();
		$h = preg_replace( '#^\s*<div class="wrap[^"]*">#', '', $h, 1 );
		$h = preg_replace( '#<h1[^>]*>.*?</h1>#s', '', $h, 1 );
		$h = preg_replace( '#</div>\s*$#', '', $h, 1 );
		echo $h; // phpcs:ignore WordPress.Security.EscapeOutput -- output of our own escaped renderers.
	}

	public static function advanced_open( $id, $label = '' ) {
		echo '<details class="ftt-advanced" id="' . esc_attr( $id ) . '"><summary>' . esc_html( $label ? $label : __( 'Advanced', 'film-transfer-toolkit' ) ) . '</summary><div class="ftt-advanced__body">';
	}

	public static function advanced_close() {
		echo '</div></details>';
	}

	public static function tab_email() {
		$g = FTT_Plugin::general();
		settings_errors();
		echo '<h2>' . esc_html__( 'Email', 'film-transfer-toolkit' ) . '</h2>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'ftt_general' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>' . esc_html__( 'Send emails?', 'film-transfer-toolkit' ) . '</th><td>';
		echo '<label class="ftt-radio"><input type="radio" name="ftt_general[mail_mode]" value="log" ' . checked( 'log', $g['mail_mode'], false ) . '> ' . esc_html__( 'Not yet (test mode): keep every message in the Mail log below and send nothing', 'film-transfer-toolkit' ) . '</label><br>';
		echo '<label class="ftt-radio"><input type="radio" name="ftt_general[mail_mode]" value="send" ' . checked( 'send', $g['mail_mode'], false ) . '> ' . esc_html__( 'Yes: send emails to customers and to you', 'film-transfer-toolkit' ) . '</label>';
		if ( defined( 'FTT_MAIL_LOG_ONLY' ) && FTT_MAIL_LOG_ONLY ) {
			echo '<p class="description"><strong>' . esc_html__( 'FTT_MAIL_LOG_ONLY is set in wp-config.php, so mail is logged only whatever this says.', 'film-transfer-toolkit' ) . '</strong></p>';
		}
		echo '</td></tr>';
		echo '<tr><th><label for="ftt-notify">' . esc_html__( 'Send new requests to', 'film-transfer-toolkit' ) . '</label></th><td><input id="ftt-notify" type="email" class="regular-text" name="ftt_general[notify_email]" value="' . esc_attr( $g['notify_email'] ) . '"><p class="description">' . esc_html__( 'Your email address for new estimate requests. Leave blank to keep requests in WordPress without an email.', 'film-transfer-toolkit' ) . '</p></td></tr>';
		echo '</table>';
		submit_button( __( 'Save changes', 'film-transfer-toolkit' ) );
		echo '</form>';
		echo '<div id="ftt-mail-log">';
		self::advanced_open( 'ftt-adv-mail-log', __( 'Mail log: recent emails', 'film-transfer-toolkit' ) );
		self::embed( array( 'FTT_Plugin', 'render_mail_log' ) );
		self::advanced_close();
		echo '</div>';
	}

	public static function tab_calculator() {
		settings_errors();
		echo '<h2>' . esc_html__( 'Film calculator', 'film-transfer-toolkit' ) . '</h2>';
		echo '<p>' . esc_html__( 'What every film calculator starts with. A page can still change any of this in the block sidebar.', 'film-transfer-toolkit' ) . '</p>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'ftt_calc' );
		self::sections( 'ftt-calculator', array( 'ftt_calc_more', 'ftt_calc_extras' ), 'ftt-adv-calculator' );
		submit_button( __( 'Save changes', 'film-transfer-toolkit' ) );
		echo '</form>';
		if ( function_exists( 'ftt_calc_render_help_page' ) ) {
			self::advanced_open( 'ftt-adv-calc-reference', __( 'Shortcode reference', 'film-transfer-toolkit' ) );
			ftt_calc_render_help_page( false );
			self::advanced_close();
		}
	}

	/** do_settings_sections(), with the listed sections inside one "Advanced" box. */
	public static function sections( $page, $advanced, $id ) {
		global $wp_settings_sections;
		$adv = false;
		foreach ( (array) ( $wp_settings_sections[ $page ] ?? array() ) as $section ) {
			$is_adv = in_array( $section['id'], $advanced, true );
			if ( $is_adv && ! $adv ) {
				self::advanced_open( $id );
				$adv = true;
			}
			if ( $section['title'] ) {
				echo '<h3>' . esc_html( $section['title'] ) . '</h3>';
			}
			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( $page, $section['id'] );
			echo '</table>';
		}
		if ( $adv ) {
			self::advanced_close();
		}
	}

	public static function tab_prices() {
		self::embed( array( 'FTT_Price_Table', 'render_admin' ) );
	}

	public static function tab_storage() {
		if ( function_exists( 'ftt_storage_render_settings' ) ) {
			self::embed( 'ftt_storage_render_settings' );
		}
	}

	public static function tab_clips() {
		if ( function_exists( 'ftt_clip_render_settings' ) ) {
			self::embed( 'ftt_clip_render_settings' );
		}
	}

	public static function tab_rfq() {
		if ( class_exists( 'FTT_RFQ' ) ) {
			self::embed( array( 'FTT_RFQ', 'render_admin' ) );
		}
	}
}
