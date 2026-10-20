<?php
/**
 * The FTT admin menu: Dashboard, Setup wizard, Settings, Prices, Help, plus WordPress help tabs
 * and the notice shown while the standalone Film Calculator plugin is active.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'order_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'standalone_notice' ) );
		add_action( 'admin_post_ftt_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );
		add_action( 'current_screen', array( __CLASS__, 'help_tabs' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FTT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/** True on any toolkit admin screen (its pages and its post types). */
	public static function is_toolkit_screen( $screen = null ) {
		$screen = $screen ? $screen : ( function_exists( 'get_current_screen' ) ? get_current_screen() : null );
		if ( ! $screen ) {
			return false;
		}
		if ( in_array( $screen->post_type, array( 'ftt_estimate', 'ftt_rfq', 'ftt_rfq_quote', 'ftt_provider' ), true ) && '' !== $screen->post_type ) {
			return true;
		}
		return (bool) preg_match( '/(^|_)page_ftt(-|$)|toplevel_page_ftt$/', (string) $screen->id );
	}

	public static function menu() {
		$cap = 'manage_options';
		add_menu_page( __( 'FTT', 'film-transfer-toolkit' ), __( 'FTT', 'film-transfer-toolkit' ), $cap, 'ftt', array( __CLASS__, 'render_dashboard' ), 'dashicons-video-alt2', 82 );
		add_submenu_page( 'ftt', __( 'Dashboard', 'film-transfer-toolkit' ), __( 'Dashboard', 'film-transfer-toolkit' ), $cap, 'ftt', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'ftt', __( 'Setup wizard', 'film-transfer-toolkit' ), __( 'Setup wizard', 'film-transfer-toolkit' ), $cap, 'ftt-setup', array( 'FTT_Setup', 'render' ) );
		add_submenu_page( 'ftt', __( 'Settings', 'film-transfer-toolkit' ), __( 'Settings', 'film-transfer-toolkit' ), $cap, 'ftt-settings', array( 'FTT_Plugin', 'render_modules' ) );
		add_submenu_page( 'ftt', __( 'Prices', 'film-transfer-toolkit' ), __( 'Prices', 'film-transfer-toolkit' ), $cap, 'ftt-prices', array( 'FTT_Price_Table', 'render_admin' ) );
		add_submenu_page( 'ftt', __( 'Help', 'film-transfer-toolkit' ), __( 'Help', 'film-transfer-toolkit' ), $cap, 'ftt-help', array( __CLASS__, 'render_help' ) );
		if ( 'on' === ( FTT_Plugin::$status['calculator'] ?? '' ) ) {
			add_submenu_page( 'ftt', __( 'Calculator look', 'film-transfer-toolkit' ), __( 'Calculator look', 'film-transfer-toolkit' ), $cap, 'ftt-calculator', 'ftt_calc_render_settings_page' );
		}
		do_action( 'ftt/admin_menu' );
		add_submenu_page( 'ftt', __( 'Mail log', 'film-transfer-toolkit' ), __( 'Mail log', 'film-transfer-toolkit' ), $cap, 'ftt-mail', array( 'FTT_Plugin', 'render_mail_log' ) );
	}

	/** Dashboard, Setup, Settings, Prices, Help first; module screens and post types next; Mail log last. */
	public static function order_menu() {
		global $submenu;
		if ( empty( $submenu['ftt'] ) || ! is_array( $submenu['ftt'] ) ) {
			return;
		}
		$first = array( 'ftt', 'ftt-setup', 'ftt-settings', 'ftt-prices', 'ftt-help' );
		$head  = array();
		$mid   = array();
		$tail  = array();
		foreach ( $submenu['ftt'] as $item ) {
			$slug = (string) ( $item[2] ?? '' );
			$i    = array_search( $slug, $first, true );
			if ( false !== $i ) {
				$head[ $i ] = $item;
			} elseif ( 'ftt-mail' === $slug ) {
				$tail[] = $item;
			} else {
				$mid[] = $item;
			}
		}
		ksort( $head );
		$submenu['ftt'] = array_merge( array_values( $head ), $mid, $tail ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- reordering our own submenu.
	}

	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( false !== strpos( (string) $hook, 'ftt' ) || self::is_toolkit_screen( $screen ) ) {
			wp_enqueue_style( 'ftt-admin', FTT_URL . 'assets/css/admin.css', array(), FTT_VERSION );
			wp_enqueue_script( 'ftt-admin-ui', FTT_URL . 'assets/js/admin-ui.js', array(), FTT_VERSION, true );
			wp_localize_script( 'ftt-admin-ui', 'FTT_ADMIN_UI', array( 'copied' => __( 'Copied', 'film-transfer-toolkit' ), 'copy' => __( 'Copy', 'film-transfer-toolkit' ), 'failed' => __( 'Press Ctrl+C (Cmd+C on a Mac) to copy', 'film-transfer-toolkit' ) ) );
		}
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=ftt' ) ) . '">' . esc_html__( 'Dashboard', 'film-transfer-toolkit' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=ftt-help' ) ) . '">' . esc_html__( 'Help', 'film-transfer-toolkit' ) . '</a>'
		);
		return $links;
	}

	public static function img( $file ) {
		return FTT_URL . 'assets/img/help/' . rawurlencode( $file );
	}

	public static function help_url( $tool = '' ) {
		return add_query_arg( array_filter( array( 'page' => 'ftt-help', 'tool' => $tool ) ), admin_url( 'admin.php' ) );
	}

	/** A shortcode in <code> with a Copy button. */
	public static function copy( $text, $label = '' ) {
		return '<span class="ftt-copywrap"><code class="ftt-code">' . esc_html( $text ) . '</code> <button type="button" class="button button-small ftt-copy" data-ftt-copy="' . esc_attr( $text ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: text */ __( 'Copy %s', 'film-transfer-toolkit' ), $label ? $label : $text ) ) . '">' . esc_html__( 'Copy', 'film-transfer-toolkit' ) . '</button></span>';
	}

	private static function status_pill( $key ) {
		$s = FTT_Plugin::$status[ $key ] ?? 'off';
		if ( 'on' === $s ) {
			return '<span class="ftt-pill ftt-pill--on">' . esc_html__( 'On', 'film-transfer-toolkit' ) . '</span>';
		}
		if ( 'off' === $s ) {
			return '<span class="ftt-pill">' . esc_html__( 'Off', 'film-transfer-toolkit' ) . '</span>';
		}
		return '<span class="ftt-pill ftt-pill--wait">' . esc_html__( 'Waiting', 'film-transfer-toolkit' ) . '</span>';
	}

	/* ------------------------------------------------------------- dashboard */

	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$t     = FTT_Price_Table::get();
		$g     = FTT_Plugin::general();
		$state = FTT_Setup::state();
		?>
		<div class="wrap ftt-admin ftt-dash">
			<h1><?php esc_html_e( 'FTT', 'film-transfer-toolkit' ); ?> <small>v<?php echo esc_html( FTT_VERSION ); ?></small></h1>
			<?php if ( 'pending' === $state ) : ?>
				<div class="ftt-hero">
					<h2><?php esc_html_e( 'Welcome! Let\'s get your tools on your site.', 'film-transfer-toolkit' ); ?></h2>
					<p><?php esc_html_e( 'The setup wizard takes about two minutes: choose your tools, create their pages, pick a look, and set your prices and email.', 'film-transfer-toolkit' ); ?></p>
					<p><a class="button button-primary button-hero" href="<?php echo esc_url( FTT_Setup::url() ); ?>"><?php esc_html_e( 'Start setup', 'film-transfer-toolkit' ); ?></a></p>
				</div>
			<?php else : ?>
				<p class="ftt-lead"><?php esc_html_e( 'Calculators, estimates and quote tools for film transfer businesses. Each tool can go on any page as a block or a shortcode.', 'film-transfer-toolkit' ); ?>
					<a href="<?php echo esc_url( FTT_Setup::url() ); ?>"><?php esc_html_e( 'Run the setup wizard again', 'film-transfer-toolkit' ); ?></a></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Getting started', 'film-transfer-toolkit' ); ?></h2>
			<ol class="ftt-getting-started">
				<li><?php esc_html_e( 'Switch on the tools you want under Settings (or in the setup wizard).', 'film-transfer-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Add a tool to a page: in the block editor click +, search "FTT" and pick the tool; in the classic editor paste its shortcode. Help has step-by-step pictures.', 'film-transfer-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Replace the EXAMPLE prices with yours under Prices and mark them Final.', 'film-transfer-toolkit' ); ?></li>
				<li><?php esc_html_e( 'When you are happy with your test requests in the Mail log, switch email to Send under Settings.', 'film-transfer-toolkit' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Your tools', 'film-transfer-toolkit' ); ?></h2>
			<div class="ftt-cards">
			<?php
			foreach ( FTT_Registry::tools() as $k => $tool ) :
				?>
				<div class="ftt-card ftt-tool">
					<h3><span class="dashicons dashicons-<?php echo esc_attr( $tool['icon'] ); ?>" aria-hidden="true"></span> <?php echo esc_html( $tool['title'] ); ?> <?php echo self::status_pill( $k ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></h3>
					<p><?php echo esc_html( $tool['summary'] ); ?></p>
					<?php foreach ( $tool['embeds'] as $e ) : ?>
						<p class="ftt-tool__sc"><?php if ( count( $tool['embeds'] ) > 1 ) : ?><span class="ftt-tool__sub"><?php echo esc_html( $e['label'] ); ?></span><br><?php endif; ?><?php echo self::copy( '[' . $e['shortcode'] . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy(). ?></p>
						<?php
						if ( ! empty( $e['page'] ) && 'on' === ( FTT_Plugin::$status[ $k ] ?? '' ) ) :
							$found = FTT_Registry::find_pages( $e, 3 );
							?>
							<p class="ftt-tool__pages">
							<?php if ( $found ) : ?>
								<?php esc_html_e( 'On:', 'film-transfer-toolkit' ); ?>
								<?php foreach ( $found as $i => $p ) : ?><?php echo $i ? ', ' : ''; ?><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $p ) ?: wp_make_link_relative( get_permalink( $p ) ) ); ?></a><?php echo 'publish' !== $p->post_status ? ' <span class="ftt-pill">' . esc_html( get_post_status_object( $p->post_status )->label ?? $p->post_status ) . '</span>' : ''; ?><?php endforeach; ?>
							<?php else : ?>
								<em><?php esc_html_e( 'Not on any page yet.', 'film-transfer-toolkit' ); ?></em>
							<?php endif; ?>
							</p>
						<?php endif; ?>
					<?php endforeach; ?>
					<p class="ftt-tool__links"><a class="button" href="<?php echo esc_url( self::help_url( $k ) ); ?>"><?php esc_html_e( 'How to add it to a page', 'film-transfer-toolkit' ); ?></a></p>
				</div>
			<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'At a glance', 'film-transfer-toolkit' ); ?></h2>
			<table class="widefat striped ftt-glance"><tbody>
				<tr><th><?php esc_html_e( 'Prices', 'film-transfer-toolkit' ); ?></th><td><?php echo esc_html( $t['label'] . ' (' . strtoupper( $t['status'] ) . ')' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt-prices' ) ); ?>"><?php esc_html_e( 'Edit prices', 'film-transfer-toolkit' ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'film-transfer-toolkit' ); ?></th><td><?php echo esc_html( 'send' === FTT_Mail::mode() ? __( 'Send', 'film-transfer-toolkit' ) : __( 'Log only (nothing is sent)', 'film-transfer-toolkit' ) ); ?>; <?php echo esc_html( $g['notify_email'] ? sprintf( /* translators: %s: email */ __( 'notify %s', 'film-transfer-toolkit' ), $g['notify_email'] ) : __( 'no notify address', 'film-transfer-toolkit' ) ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'film-transfer-toolkit' ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Version', 'film-transfer-toolkit' ); ?></th><td><?php echo esc_html( FTT_VERSION ); ?></td></tr>
			</tbody></table>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ help */

	public static function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		$tools = FTT_Registry::tools();
		$tool  = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		$all   = 'options' === $tool ? 'options' : '';
		$tool  = isset( $tools[ $tool ] ) ? $tool : '';
		?>
		<div class="wrap ftt-admin ftt-help">
			<h1><?php esc_html_e( 'FTT help', 'film-transfer-toolkit' ); ?></h1>
			<nav class="nav-tab-wrapper ftt-help__tabs" aria-label="<?php esc_attr_e( 'Help topics', 'film-transfer-toolkit' ); ?>">
				<a class="nav-tab<?php echo '' === $tool && '' === $all ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::help_url() ); ?>"<?php echo '' === $tool && '' === $all ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Start here', 'film-transfer-toolkit' ); ?></a>
				<?php foreach ( $tools as $k => $t ) : ?>
					<a class="nav-tab<?php echo $k === $tool ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::help_url( $k ) ); ?>"<?php echo $k === $tool ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $t['title'] ); ?></a>
				<?php endforeach; ?>
				<a class="nav-tab<?php echo 'options' === $all ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tool', 'options', self::help_url() ) ); ?>"<?php echo 'options' === $all ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All options', 'film-transfer-toolkit' ); ?></a>
			</nav>
			<?php
			if ( 'options' === $all ) {
				self::help_all_options();
			} else {
				'' === $tool ? self::help_start() : self::help_tool( $tool, $tools[ $tool ] );
			}
			?>
		</div>
		<?php
	}

	private static function help_start() {
		?>
		<div class="ftt-help__body">
			<h2><?php esc_html_e( 'How the toolkit works', 'film-transfer-toolkit' ); ?></h2>
			<p><?php esc_html_e( 'Each tool (calculator, estimator, price table and so on) is something you place on a page. There are two ways to place one, and they give exactly the same result:', 'film-transfer-toolkit' ); ?></p>
			<ul class="ftt-list">
				<li><strong><?php esc_html_e( 'A block', 'film-transfer-toolkit' ); ?></strong> — <?php esc_html_e( 'if your site uses the block editor (the default since WordPress 5.0). The tools are in the "FTT" group of the block inserter.', 'film-transfer-toolkit' ); ?></li>
				<li><strong><?php esc_html_e( 'A shortcode', 'film-transfer-toolkit' ); ?></strong> — <?php esc_html_e( 'a short code in square brackets, such as [ftt_calculator], that you paste into the classic editor, a Shortcode block, a page builder\'s shortcode widget, or a text widget.', 'film-transfer-toolkit' ); ?></li>
			</ul>
			<div class="ftt-help__two">
				<figure class="ftt-figure"><img src="<?php echo esc_url( self::img( 'block-editor-steps.svg' ) ); ?>" alt="<?php esc_attr_e( 'The block editor: click the plus button, search for FTT, click a tool, then adjust its settings in the right-hand panel.', 'film-transfer-toolkit' ); ?>" width="560" height="320" loading="lazy"><figcaption><?php esc_html_e( 'Block editor: + → search → click the tool → settings on the right.', 'film-transfer-toolkit' ); ?></figcaption></figure>
				<figure class="ftt-figure"><img src="<?php echo esc_url( self::img( 'classic-editor-steps.svg' ) ); ?>" alt="<?php esc_attr_e( 'The classic editor: the shortcode typed on its own line in the editor, then Update.', 'film-transfer-toolkit' ); ?>" width="560" height="320" loading="lazy"><figcaption><?php esc_html_e( 'Classic editor: paste the shortcode on its own line → Update.', 'film-transfer-toolkit' ); ?></figcaption></figure>
			</div>
			<h2><?php esc_html_e( 'Pick a tool', 'film-transfer-toolkit' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Tool', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Block (in the inserter)', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Shortcode', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Status', 'film-transfer-toolkit' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( FTT_Registry::tools() as $k => $t ) : foreach ( $t['embeds'] as $e ) : ?>
					<tr><td><a href="<?php echo esc_url( self::help_url( $k ) ); ?>"><?php echo esc_html( $e['label'] ); ?></a></td><td><?php echo esc_html( $e['label'] ); ?></td><td><?php echo self::copy( '[' . $e['shortcode'] . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy(). ?></td><td><?php echo self::status_pill( $k ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></td></tr>
				<?php endforeach; endforeach; ?>
				</tbody>
			</table>
			<?php self::help_general_faq(); ?>
		</div>
		<?php
	}

	private static function help_general_faq() {
		$faq = array(
			__( 'The page shows the shortcode as text, like [ftt_estimate].', 'film-transfer-toolkit' ) => __( 'The tool is switched off. Turn it on under FTT > Settings. Also check the shortcode is spelled exactly as shown, with straight square brackets.', 'film-transfer-toolkit' ),
			__( 'The block says "Your site doesn\'t include support for this block".', 'film-transfer-toolkit' ) => __( 'The tool behind that block is switched off. Turn it on under Settings and reload the editor.', 'film-transfer-toolkit' ),
			__( 'The tool shows but nothing happens when I type.', 'film-transfer-toolkit' ) => __( 'A speed or caching plugin may be delaying or combining JavaScript. Clear its cache, and if that does not help, exclude files containing "film-transfer-toolkit" from JavaScript optimisation.', 'film-transfer-toolkit' ),
			__( 'I changed a setting but the page looks the same.', 'film-transfer-toolkit' ) => __( 'Clear your caching plugin or host cache (for example SiteGround Speed Optimizer > Purge cache), then reload the page.', 'film-transfer-toolkit' ),
			__( 'Can I use the tools in Elementor, Divi or another page builder?', 'film-transfer-toolkit' ) => __( 'Yes. Add the builder\'s Shortcode element (or a text element) and paste the shortcode.', 'film-transfer-toolkit' ),
		);
		self::faq_list( __( 'Troubleshooting', 'film-transfer-toolkit' ), $faq );
	}

	private static function faq_list( $heading, $faq ) {
		echo '<h2>' . esc_html( $heading ) . '</h2><div class="ftt-faq">';
		foreach ( $faq as $q => $a ) {
			echo '<details><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( $a ) . '</p></details>';
		}
		echo '</div>';
	}

	private static function help_tool( $key, $t ) {
		$on = 'on' === ( FTT_Plugin::$status[ $key ] ?? '' );
		?>
		<div class="ftt-help__body">
			<?php if ( ! $on ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This tool is switched off, so its block is hidden and its shortcode shows as plain text.', 'film-transfer-toolkit' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ftt-settings' ) ); ?>"><?php esc_html_e( 'Turn it on under Settings', 'film-transfer-toolkit' ); ?></a></p></div>
			<?php endif; ?>
			<div class="ftt-help__intro">
				<div>
					<h2><span class="dashicons dashicons-<?php echo esc_attr( $t['icon'] ); ?>" aria-hidden="true"></span> <?php echo esc_html( $t['title'] ); ?></h2>
					<p class="ftt-lead"><?php echo esc_html( $t['summary'] ); ?></p>
					<p><?php esc_html_e( 'Shortcode:', 'film-transfer-toolkit' ); ?> <?php foreach ( $t['embeds'] as $e ) { echo self::copy( '[' . $e['shortcode'] . ']' ) . ' '; } // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy(). ?></p>
				</div>
				<figure class="ftt-figure ftt-figure--shot"><img src="<?php echo esc_url( self::img( $t['image'] ) ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: tool */ __( 'What the %s looks like on a page', 'film-transfer-toolkit' ), $t['title'] ) ); ?>" loading="lazy"><figcaption><?php esc_html_e( 'How it looks to visitors (your theme\'s fonts may differ).', 'film-transfer-toolkit' ); ?></figcaption></figure>
			</div>

			<?php if ( 'clips' === $key ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'The clip selector goes directly under a video player on any page (a Video block, an uploaded video, a YouTube or Vimeo embed, FluentPlayer or FV Player), rather than on a page of its own. Use player_selector to attach it to one particular player.', 'film-transfer-toolkit' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'rfq' === $key ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Quote comparison uses four pages, one for each piece below. The setup wizard can create all four and connect them for you; if you make them yourself, choose them on the RFQ requests settings page so emailed links point to the right place.', 'film-transfer-toolkit' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Add it to a page', 'film-transfer-toolkit' ); ?></h2>
			<?php $first = $t['embeds'][0]; ?>
			<div class="ftt-help__two">
				<section class="ftt-card">
					<h3><?php esc_html_e( 'Block editor', 'film-transfer-toolkit' ); ?></h3>
					<ol class="ftt-steps-list">
						<li><?php echo esc_html( 'clips' === $key ? __( 'Open the product page for editing and click just below the video player.', 'film-transfer-toolkit' ) : __( 'Go to Pages and open the page you want (or click Add New Page).', 'film-transfer-toolkit' ) ); ?></li>
						<li><?php esc_html_e( 'Click the + button where you want the tool to appear (top left of the editor, or the + that shows between blocks).', 'film-transfer-toolkit' ); ?></li>
						<li><?php /* translators: %s: block name */ echo esc_html( sprintf( __( 'Type "FTT" in the search box, or scroll to the FTT group, then click "%s".', 'film-transfer-toolkit' ), $first['label'] ) ); ?></li>
						<li><?php esc_html_e( 'A grey box appears in the editor. That is normal: the working tool only shows on the real page. Change its options in the Block settings panel on the right (click the gear icon at the top right if the panel is closed).', 'film-transfer-toolkit' ); ?></li>
						<li><?php esc_html_e( 'Click Publish (or Update / Save), then View Page to try it.', 'film-transfer-toolkit' ); ?></li>
					</ol>
					<figure class="ftt-figure"><img src="<?php echo esc_url( self::img( 'block-editor-steps.svg' ) ); ?>" alt="<?php esc_attr_e( 'Block editor steps: plus button, search for FTT, click the tool, settings panel on the right.', 'film-transfer-toolkit' ); ?>" width="560" height="320" loading="lazy"></figure>
					<p class="description"><?php esc_html_e( 'Pasting a shortcode into the block editor also works: it turns into the matching block (or a Shortcode block).', 'film-transfer-toolkit' ); ?></p>
				</section>
				<section class="ftt-card">
					<h3><?php esc_html_e( 'Classic editor', 'film-transfer-toolkit' ); ?></h3>
					<ol class="ftt-steps-list">
						<li><?php esc_html_e( 'Click Copy next to the shortcode below.', 'film-transfer-toolkit' ); ?></li>
						<li><?php echo esc_html( 'clips' === $key ? __( 'Open the product for editing and put the cursor on the line after the video player shortcode.', 'film-transfer-toolkit' ) : __( 'Go to Pages, open the page, and click where the tool should appear.', 'film-transfer-toolkit' ) ); ?></li>
						<li><?php esc_html_e( 'Paste (Ctrl+V, or Cmd+V on a Mac). Put the shortcode on a line of its own. It works in both the Visual and the Text tab.', 'film-transfer-toolkit' ); ?></li>
						<li><?php esc_html_e( 'To change an option, add it inside the brackets, for example title="My heading". The table below lists every option.', 'film-transfer-toolkit' ); ?></li>
						<li><?php esc_html_e( 'Click Update (or Publish), then View Page.', 'film-transfer-toolkit' ); ?></li>
					</ol>
					<figure class="ftt-figure"><img src="<?php echo esc_url( self::img( 'classic-editor-steps.svg' ) ); ?>" alt="<?php esc_attr_e( 'Classic editor: the shortcode on its own line, then the Update button.', 'film-transfer-toolkit' ); ?>" width="560" height="320" loading="lazy"></figure>
				</section>
			</div>

			<?php foreach ( $t['embeds'] as $e ) : ?>
				<h2><?php echo esc_html( count( $t['embeds'] ) > 1 ? $e['label'] : __( 'Options', 'film-transfer-toolkit' ) ); ?></h2>
				<p><?php esc_html_e( 'Shortcode:', 'film-transfer-toolkit' ); ?> <?php echo self::copy( '[' . $e['shortcode'] . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy(). ?>
				<?php if ( ! empty( $e['aliases'] ) ) : ?><span class="description"><?php /* translators: %s: shortcodes */ echo esc_html( sprintf( __( '(also answers to %s)', 'film-transfer-toolkit' ), '[' . implode( '], [', $e['aliases'] ) . ']' ) ); ?></span><?php endif; ?>
				&nbsp; <?php esc_html_e( 'Block:', 'film-transfer-toolkit' ); ?> <strong><?php echo esc_html( $e['label'] ); ?></strong></p>
				<?php self::atts_table( $e ); ?>
				<h3><?php esc_html_e( 'Examples', 'film-transfer-toolkit' ); ?></h3>
				<ul class="ftt-examples">
				<?php self::example_items( $e ); ?>
				</ul>
				<?php
				if ( ! empty( $e['page'] ) && $on ) :
					$found = FTT_Registry::find_pages( $e, 5 );
					?>
					<p class="ftt-onpages"><strong><?php esc_html_e( 'Pages that already show it:', 'film-transfer-toolkit' ); ?></strong>
					<?php if ( $found ) : foreach ( $found as $i => $p ) : ?><?php echo $i ? ', ' : ' '; ?><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $p ) ); ?></a> (<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ?: '' ); ?>"><?php esc_html_e( 'edit', 'film-transfer-toolkit' ); ?></a>)<?php endforeach; else : ?> <em><?php esc_html_e( 'none yet', 'film-transfer-toolkit' ); ?></em><?php endif; ?></p>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php self::faq_list( __( 'Questions and troubleshooting', 'film-transfer-toolkit' ), $t['faq'] ); ?>
			<details class="ftt-faq ftt-faq--general"><summary><?php esc_html_e( 'General troubleshooting (all tools)', 'film-transfer-toolkit' ); ?></summary><?php self::help_general_faq(); ?></details>
		</div>
		<?php
	}

	/** Options table for one shortcode/block, generated from the registry (same data as the block sidebar). */
	public static function atts_table( $e ) {
		if ( ! $e['atts'] ) {
			echo '<p class="description">' . esc_html__( 'This piece has no options: just add it to its page.', 'film-transfer-toolkit' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped ftt-atts" data-ftt-atts="' . esc_attr( $e['shortcode'] ) . '"><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Shortcode option', 'film-transfer-toolkit' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Block sidebar label', 'film-transfer-toolkit' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Allowed values', 'film-transfer-toolkit' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Default (when left out)', 'film-transfer-toolkit' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'What it does', 'film-transfer-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $e['atts'] as $a ) {
			$vals = FTT_Registry::att_values( $a );
			if ( $vals ) {
				$cells = array();
				foreach ( $vals as $v => $l ) {
					$cells[] = '<code>' . esc_html( $v ) . '</code>' . ( $v !== $l ? ' ' . esc_html( $l ) : '' );
				}
				$vt = implode( '<br>', $cells );
			} else {
				$vt = esc_html( FTT_Registry::att_values_text( $a ) ) . ( ! empty( $a['example'] ) ? '<br><span class="description">' . esc_html__( 'e.g.', 'film-transfer-toolkit' ) . ' <code>' . esc_html( $a['example'] ) . '</code></span>' : '' );
			}
			$d = '' !== (string) $a['default'] ? '<code>' . esc_html( FTT_Registry::default_text( $a ) ) . '</code>' : '<em>' . esc_html( FTT_Registry::default_text( $a ) ) . '</em>';
			echo '<tr data-att="' . esc_attr( $a['name'] ) . '"><th scope="row"><code>' . esc_html( $a['name'] ) . '</code></th><td>' . esc_html( FTT_Registry::att_label( $a ) ) . '</td><td>' . $vt . '</td><td>' . $d . '</td><td>' . esc_html( $a['help'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- every piece escaped above.
		}
		echo '</tbody></table>';
	}

	/** Copy-ready examples: the hand-written ones, then every option at its default, then the block markup. */
	private static function example_items( $e ) {
		foreach ( $e['examples'] as $code => $what ) {
			echo '<li>' . self::copy( $code ) . ' <span>' . esc_html( $what ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy().
		}
		if ( $e['atts'] ) {
			echo '<li class="ftt-ex-all">' . self::copy( FTT_Registry::full_example( $e ) ) . ' <span>' . esc_html__( 'Every option, at its default. Fill in only the ones you want to change; empty ones keep their default.', 'film-transfer-toolkit' ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy().
		}
		echo '<li class="ftt-ex-block">' . self::copy( FTT_Registry::block_example( $e ) ) . ' <span>' . esc_html__( 'The same tool as block markup (paste into the Code editor view of the block editor).', 'film-transfer-toolkit' ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy().
	}

	/** Help > All options: every shortcode and block with every option, generated from the registry. */
	private static function help_all_options() {
		$tools = FTT_Registry::tools();
		echo '<div class="ftt-help__body ftt-help--options"><h2>' . esc_html__( 'All options for every tool', 'film-transfer-toolkit' ) . '</h2>';
		echo '<p>' . esc_html__( 'Each tool works as a shortcode or as a block. The options are the same in both: in a shortcode you type them inside the brackets; in the block editor they are in the block\'s Settings panel on the right, under the label shown here. This page is built from the same list the blocks use, so it is always up to date.', 'film-transfer-toolkit' ) . '</p>';
		echo '<ul class="ftt-toc">';
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			echo '<li><a href="#ftt-opt-' . esc_attr( $tag ) . '">' . esc_html( $e['label'] ) . '</a> <code>[' . esc_html( $tag ) . ']</code></li>';
		}
		echo '</ul>';
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			$on = 'on' === ( FTT_Plugin::$status[ $e['module'] ] ?? '' );
			echo '<section class="ftt-opt" id="ftt-opt-' . esc_attr( $tag ) . '"><h3>' . esc_html( $e['label'] ) . ( $on ? '' : ' <span class="ftt-pill ftt-pill--off">' . esc_html__( 'switched off', 'film-transfer-toolkit' ) . '</span>' ) . '</h3>';
			echo '<p>' . esc_html__( 'Shortcode:', 'film-transfer-toolkit' ) . ' ' . self::copy( '[' . $tag . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in copy().
			if ( ! empty( $e['aliases'] ) ) {
				/* translators: %s: shortcodes */
				echo ' <span class="description">' . esc_html( sprintf( __( '(also answers to %s)', 'film-transfer-toolkit' ), '[' . implode( '], [', $e['aliases'] ) . ']' ) ) . '</span>';
			}
			echo ' &nbsp; ' . esc_html__( 'Block:', 'film-transfer-toolkit' ) . ' <strong>' . esc_html( $e['label'] ) . '</strong> <code>' . esc_html( $e['block'] ) . '</code> &nbsp; <a href="' . esc_url( self::help_url( $e['module'] ) ) . '">' . esc_html__( 'Step-by-step help', 'film-transfer-toolkit' ) . '</a></p>';
			self::atts_table( $e );
			echo '<h4>' . esc_html__( 'Examples (click Copy)', 'film-transfer-toolkit' ) . '</h4><ul class="ftt-examples">';
			self::example_items( $e );
			echo '</ul></section>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------- help tabs */

	public static function help_tabs( $screen ) {
		if ( ! self::is_toolkit_screen( $screen ) ) {
			return;
		}
		$screen->add_help_tab(
			array(
				'id'      => 'ftt-overview',
				'title'   => __( 'FTT', 'film-transfer-toolkit' ),
				'content' => '<p>' . esc_html__( 'Each toolkit tool can be placed on any page as a block (search "FTT" in the block inserter) or as a shortcode (paste it into the classic editor).', 'film-transfer-toolkit' ) . '</p><p>' . esc_html__( 'Switch tools on or off under Settings. Emails stay in the Mail log until Mail mode is set to Send.', 'film-transfer-toolkit' ) . '</p>',
			)
		);
		$rows = '';
		foreach ( FTT_Registry::embeds() as $tag => $e ) {
			$rows .= '<li><code>[' . esc_html( $tag ) . ']</code> ' . esc_html( $e['label'] ) . ' — <a href="' . esc_url( self::help_url( $e['module'] ) ) . '">' . esc_html__( 'help', 'film-transfer-toolkit' ) . '</a></li>';
		}
		$screen->add_help_tab(
			array(
				'id'      => 'ftt-shortcodes',
				'title'   => __( 'Shortcodes and blocks', 'film-transfer-toolkit' ),
				'content' => '<ul>' . $rows . '</ul>',
			)
		);
		$here = array(
			'toplevel_page_ftt' => __( 'The dashboard lists every tool, whether it is on, which pages show it, and its shortcode with a Copy button.', 'film-transfer-toolkit' ),
			'ftt-setup'         => __( 'The setup wizard saves each step only when you press Save and continue. "Skip this step" changes nothing. Pages that already show a tool are reused, never copied.', 'film-transfer-toolkit' ),
			'ftt-settings'      => __( 'Tick a tool to switch it on. Mail mode "Log only" writes emails to the Mail log instead of sending them; the notify address may be left empty.', 'film-transfer-toolkit' ),
			'ftt-prices'        => __( 'These prices feed the price table and the estimate builder. A banner shows on the site until the table status is Final.', 'film-transfer-toolkit' ),
			'ftt-help'          => __( 'Pick a tool from the tabs for step-by-step instructions, every option, copyable examples and troubleshooting.', 'film-transfer-toolkit' ),
			'ftt-mail'          => __( 'Every email the toolkit sends or would send is listed here with its result.', 'film-transfer-toolkit' ),
		);
		foreach ( $here as $id => $text ) {
			if ( $screen->id === $id || substr( (string) $screen->id, -strlen( '_page_' . $id ) ) === '_page_' . $id ) {
				$screen->add_help_tab( array( 'id' => 'ftt-this-screen', 'title' => __( 'This screen', 'film-transfer-toolkit' ), 'content' => '<p>' . esc_html( $text ) . '</p>' ) );
			}
		}
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'More help', 'film-transfer-toolkit' ) . '</strong></p>'
			. '<p><a href="' . esc_url( self::help_url() ) . '">' . esc_html__( 'FTT help pages', 'film-transfer-toolkit' ) . '</a></p>'
			. '<p><a href="' . esc_url( FTT_Setup::url() ) . '">' . esc_html__( 'Setup wizard', 'film-transfer-toolkit' ) . '</a></p>'
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=ftt-settings' ) ) . '">' . esc_html__( 'Settings', 'film-transfer-toolkit' ) . '</a></p>'
		);
	}

	/* ------------------------------------------------- standalone plugin notice */

	public static function standalone_notice() {
		if ( ! defined( 'FILMCALC_VERSION' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen  = get_current_screen();
		$toolkit = self::is_toolkit_screen( $screen );
		if ( ! $toolkit && ( ! $screen || 'plugins' !== $screen->id || get_user_meta( get_current_user_id(), 'ftt_dismiss_filmcalc', true ) ) ) {
			return;
		}
		?>
		<div class="notice notice-info ftt-standalone-notice">
			<p><strong><?php esc_html_e( 'FTT and the standalone Film Calculator plugin', 'film-transfer-toolkit' ); ?></strong></p>
			<p><?php
			/* translators: %s: version */
			echo esc_html( sprintf( __( 'The standalone Film Calculator plugin (version %s) is active. The toolkit includes the same calculator, so you do not need both. While the standalone plugin is active it keeps drawing every [film_calculator] on your site, exactly as before. The toolkit\'s own calculator is [ftt_calculator] and the "Film calculator" block.', 'film-transfer-toolkit' ), FILMCALC_VERSION ) );
			?></p>
			<p><?php esc_html_e( 'If you deactivate the standalone plugin, your existing [film_calculator] pages switch to the toolkit automatically and use the settings under FTT > Calculator look. Nothing has been deactivated; this is your choice.', 'film-transfer-toolkit' ); ?></p>
			<?php if ( ! $toolkit ) : ?>
				<p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ftt_dismiss_notice' ), 'ftt_dismiss_notice' ) ); ?>"><?php esc_html_e( 'Hide this message on the Plugins screen', 'film-transfer-toolkit' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function dismiss_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'film-transfer-toolkit' ), 403 );
		}
		check_admin_referer( 'ftt_dismiss_notice' );
		update_user_meta( get_current_user_id(), 'ftt_dismiss_filmcalc', defined( 'FILMCALC_VERSION' ) ? FILMCALC_VERSION : '1' );
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
}
