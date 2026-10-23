<?php
/**
 * FTT 1.5.9: built-in GitHub release updater (no third-party updater plugin).
 *
 * How it works
 * - Source: the latest published GitHub release of FTT_GITHUB_REPO (default vintagefilm/film-transfer-toolkit),
 *   via GET https://api.github.com/repos/<repo>/releases/latest (drafts and pre-releases are never offered).
 * - Version: the release tag without a leading "v" (v1.5.9 -> 1.5.9). Offered only when newer than FTT_VERSION.
 * - Package: the release asset named film-transfer-toolkit-<version>.zip (top folder film-transfer-toolkit/).
 * - Checksum (required): the sha256 of that zip, taken from (1) a line "sha256: <64 hex>" or
 *   "<64 hex>  film-transfer-toolkit-<version>.zip" in the release notes, else (2) an asset named
 *   film-transfer-toolkit-<version>.zip.sha256 or SHA256SUMS.txt. No checksum = no update offered.
 *   The download is hashed before WordPress unzips it; a mismatch aborts the update (the old version stays).
 * - Pause switch: no update is offered while (a) the latest release tag or title contains "hold", or
 *   (b) the repo's default branch has ftt-pause.json with {"pause": true}. Delete the file / retag to resume.
 * - Private repo: each site needs a read-only token: define( 'FTT_GITHUB_TOKEN', '...' ) in wp-config.php
 *   (preferred) or the option ftt_github_token (FTT > Settings > Updates, or wp ftt-updater token <value>).
 *   The token is only sent in the Authorization header to api.github.com; it is never printed on a page, in
 *   the plugin details, in the REST API or in WP-CLI output. A public repo needs no token.
 * - WordPress core filters used: pre_set_site_transient_update_plugins (offer the update / list in no_update so
 *   the auto-update toggle shows), plugins_api (View details), upgrader_pre_download (authenticated download +
 *   sha256 check), upgrader_source_selection (keep the folder name film-transfer-toolkit).
 * - The GitHub answer is cached for 6 hours (site transient ftt_gh_release); Dashboard > Updates "Check again"
 *   and wp ftt-updater check refresh it.
 * - WP-CLI: wp ftt-updater status | check | token <value> | token --delete
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Updater {
	const SLUG      = 'film-transfer-toolkit';
	const CACHE     = 'ftt_gh_release';
	const TOKEN_OPT = 'ftt_github_token';
	const TTL       = 21600;
	const MARK      = '#ftt-gh-asset=';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'source_selection' ), 10, 4 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_flush' ) );
		if ( is_admin() ) {
			FTT_Updater_Admin::init();
		}
	}

	public static function basename() {
		return plugin_basename( FTT_FILE );
	}

	public static function repo() {
		$r = defined( 'FTT_GITHUB_REPO' ) ? FTT_GITHUB_REPO : 'vintagefilm/film-transfer-toolkit';
		$r = apply_filters( 'ftt_updater_repo', $r );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r ) ? $r : 'vintagefilm/film-transfer-toolkit';
	}

	/** API base; filterable only so tests can point at a local mock. */
	public static function api() {
		return untrailingslashit( apply_filters( 'ftt_updater_api_base', 'https://api.github.com' ) );
	}

	/** Never echo this. */
	private static function token() {
		if ( defined( 'FTT_GITHUB_TOKEN' ) && FTT_GITHUB_TOKEN ) {
			return (string) FTT_GITHUB_TOKEN;
		}
		return (string) get_option( self::TOKEN_OPT, '' );
	}

	public static function token_source() {
		if ( defined( 'FTT_GITHUB_TOKEN' ) && FTT_GITHUB_TOKEN ) {
			return 'wp-config';
		}
		return get_option( self::TOKEN_OPT, '' ) ? 'option' : 'none';
	}

	public static function set_token( $t ) {
		$t = trim( (string) $t );
		if ( '' === $t ) {
			delete_option( self::TOKEN_OPT );
		} else {
			update_option( self::TOKEN_OPT, sanitize_text_field( $t ), false );
		}
		delete_site_transient( self::CACHE );
	}

	private static function headers( $accept = 'application/vnd.github+json' ) {
		$h = array( 'Accept' => $accept, 'User-Agent' => 'FTT-Updater/' . FTT_VERSION, 'X-GitHub-Api-Version' => '2022-11-28' );
		$t = self::token();
		if ( '' !== $t ) {
			$h['Authorization'] = 'Bearer ' . $t;
		}
		return $h;
	}

	private static function get( $path, $accept = 'application/vnd.github+json' ) {
		return wp_remote_get( self::api() . $path, array( 'timeout' => 15, 'headers' => self::headers( $accept ) ) );
	}

	/** Parse a release JSON into what we need (or a reason string why nothing is offered). */
	public static function parse_release( $rel, $sums_text = '' ) {
		if ( ! is_array( $rel ) || empty( $rel['tag_name'] ) ) {
			return array( 'error' => 'no release' );
		}
		if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
			return array( 'error' => 'draft/prerelease' );
		}
		$tag = (string) $rel['tag_name'];
		$out = array( 'tag' => $tag, 'name' => (string) ( $rel['name'] ?? '' ), 'url' => (string) ( $rel['html_url'] ?? '' ), 'published' => (string) ( $rel['published_at'] ?? '' ), 'body' => (string) ( $rel['body'] ?? '' ) );
		if ( false !== stripos( $tag, 'hold' ) || false !== stripos( $out['name'], 'hold' ) ) {
			$out['error'] = 'hold';
			return $out;
		}
		$ver = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+\.\d+(\.\d+)*$/', $ver ) ) {
			$out['error'] = 'bad tag';
			return $out;
		}
		$out['version'] = $ver;
		$zip = self::SLUG . '-' . $ver . '.zip';
		$asset = null;
		$sum_asset = null;
		foreach ( (array) ( $rel['assets'] ?? array() ) as $a ) {
			$n = $a['name'] ?? '';
			if ( $n === $zip ) {
				$asset = $a;
			} elseif ( $n === $zip . '.sha256' || 'SHA256SUMS.txt' === $n ) {
				$sum_asset = $a;
			}
		}
		if ( ! $asset ) {
			$out['error'] = 'no zip asset';
			return $out;
		}
		$out['asset_id']   = (int) $asset['id'];
		$out['asset_name'] = $zip;
		$out['size']       = (int) ( $asset['size'] ?? 0 );
		$sha = self::find_sha( $out['body'], $zip );
		if ( ! $sha && $sums_text ) {
			$sha = self::find_sha( $sums_text, $zip );
		}
		$out['sum_asset_id'] = $sum_asset ? (int) $sum_asset['id'] : 0;
		if ( $sha ) {
			$out['sha256'] = $sha;
		} elseif ( ! $sum_asset ) {
			$out['error'] = 'no sha256';
		}
		return $out;
	}

	public static function find_sha( $text, $zip ) {
		$q = preg_quote( $zip, '/' );
		if ( preg_match( '/\b([a-f0-9]{64})\b[ \t*]+' . $q . '/i', $text, $m ) ) {
			return strtolower( $m[1] );
		}
		if ( preg_match( '/sha-?256\s*[:=]\s*`?([a-f0-9]{64})\b/i', $text, $m ) ) {
			return strtolower( $m[1] );
		}
		if ( preg_match( '/^\s*([a-f0-9]{64})\s*$/im', $text, $m ) ) {
			return strtolower( $m[1] );
		}
		return '';
	}

	/** Latest release info (cached). $force skips the cache. */
	public static function release( $force = false ) {
		$c = get_site_transient( self::CACHE );
		if ( ! $force && is_array( $c ) ) {
			return $c;
		}
		$info = self::fetch();
		set_site_transient( self::CACHE, $info, isset( $info['error'] ) && 'http' === substr( $info['error'], 0, 4 ) ? 1800 : self::TTL );
		return $info;
	}

	private static function fetch() {
		$repo = self::repo();
		$r = self::get( "/repos/$repo/releases/latest" );
		if ( is_wp_error( $r ) ) {
			return array( 'error' => 'http: ' . $r->get_error_code() );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 200 !== $code ) {
			// A private repo without (or with a wrong) token answers 404.
			return array( 'error' => 'http ' . $code . ( 404 === $code && 'none' === self::token_source() ? ' (private repo needs FTT_GITHUB_TOKEN)' : '' ) );
		}
		$rel = json_decode( wp_remote_retrieve_body( $r ), true );
		$info = self::parse_release( $rel );
		if ( empty( $info['error'] ) && empty( $info['sha256'] ) && $info['sum_asset_id'] ) {
			$tf = wp_tempnam( 'ftt-sums' );
			$txt = true === self::download_asset( $info['sum_asset_id'], $tf ) ? (string) file_get_contents( $tf ) : '';
			@unlink( $tf ); // phpcs:ignore
			$info = self::parse_release( $rel, $txt );
			if ( empty( $info['sha256'] ) ) {
				$info['error'] = 'no sha256';
			}
		}
		$p = self::get( "/repos/$repo/contents/ftt-pause.json", 'application/vnd.github.raw+json' );
		if ( ! is_wp_error( $p ) && 200 === (int) wp_remote_retrieve_response_code( $p ) ) {
			$j = json_decode( wp_remote_retrieve_body( $p ), true );
			if ( is_array( $j ) && ! empty( $j['pause'] ) ) {
				$info['paused'] = 1;
				$info['error'] = 'paused (ftt-pause.json)';
			}
		}
		$info['checked'] = time();
		return $info;
	}

	/** GitHub answers asset downloads with a 302 to a signed URL: follow it WITHOUT the Authorization header. */
	private static function download_asset( $id, $file ) {
		$args = array( 'timeout' => 300, 'redirection' => 0, 'headers' => self::headers( 'application/octet-stream' ) );
		$r = wp_remote_get( self::api() . '/repos/' . self::repo() . '/releases/assets/' . (int) $id, $args + array( 'stream' => true, 'filename' => $file ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( $code >= 300 && $code < 400 ) {
			$loc = wp_remote_retrieve_header( $r, 'location' );
			if ( ! $loc || ! preg_match( '#^https?://#', $loc ) ) {
				return new WP_Error( 'ftt_dl', 'bad redirect' );
			}
			$r = wp_remote_get( $loc, array( 'timeout' => 300, 'stream' => true, 'filename' => $file, 'headers' => array( 'User-Agent' => 'FTT-Updater/' . FTT_VERSION ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$code = (int) wp_remote_retrieve_response_code( $r );
		}
		return 200 === $code ? true : new WP_Error( 'ftt_dl', 'download HTTP ' . $code );
	}

	public static function is_newer( $info ) {
		return empty( $info['error'] ) && ! empty( $info['version'] ) && ! empty( $info['sha256'] ) && version_compare( $info['version'], FTT_VERSION, '>' );
	}

	private static function item( $info ) {
		return (object) array(
			'id'            => 'github.com/' . self::repo(),
			'slug'          => self::SLUG,
			'plugin'        => self::basename(),
			'new_version'   => $info['version'] ?? FTT_VERSION,
			'url'           => 'https://github.com/' . self::repo(),
			'package'       => self::is_newer( $info ) ? 'https://github.com/' . self::repo() . '/releases/download/' . rawurlencode( $info['tag'] ) . '/' . $info['asset_name'] . self::MARK . $info['asset_id'] : '',
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'tested'        => get_bloginfo( 'version' ),
			'icons'         => array(),
			'banners'       => array(),
		);
	}

	public static function inject( $t ) {
		if ( ! is_object( $t ) ) {
			$t = new stdClass();
		}
		$info = self::release( false );
		$b = self::basename();
		if ( self::is_newer( $info ) ) {
			$t->response[ $b ] = self::item( $info );
			unset( $t->no_update[ $b ] );
		} else {
			unset( $t->response[ $b ] );
			$i = self::item( array() );
			$t->no_update[ $b ] = $i;
		}
		return $t;
	}

	public static function maybe_flush() {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_site_transient( self::CACHE );
		}
	}

	public static function plugins_api( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $res;
		}
		$info = self::release( false );
		$notes = isset( $info['body'] ) ? $info['body'] : '';
		return (object) array(
			'name'          => 'FTT',
			'slug'          => self::SLUG,
			'version'       => $info['version'] ?? FTT_VERSION,
			'author'        => 'Vintage Film Transfer',
			'homepage'      => 'https://github.com/' . self::repo(),
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'last_updated'  => $info['published'] ?? '',
			'download_link' => self::is_newer( $info ) ? self::item( $info )->package : '',
			'sections'      => array( 'changelog' => wp_kses_post( wpautop( esc_html( $notes ) ) ) ),
		);
	}

	/** Our package marker: authenticated download, then sha256 check before WordPress unzips anything. */
	public static function pre_download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		if ( false !== $reply || ! is_string( $package ) || false === strpos( $package, self::MARK ) ) {
			return $reply;
		}
		$id = (int) substr( $package, strpos( $package, self::MARK ) + strlen( self::MARK ) );
		$info = self::release( false );
		if ( empty( $info['asset_id'] ) || (int) $info['asset_id'] !== $id || empty( $info['sha256'] ) ) {
			$info = self::release( true );
		}
		if ( ! self::is_newer( $info ) || (int) $info['asset_id'] !== $id ) {
			return new WP_Error( 'ftt_update', 'FTT update not available (paused, withdrawn or no checksum).' );
		}
		$tmp = wp_tempnam( $info['asset_name'] );
		$ok = self::download_asset( $id, $tmp );
		if ( is_wp_error( $ok ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return $ok;
		}
		$got = hash_file( 'sha256', $tmp );
		if ( ! hash_equals( $info['sha256'], (string) $got ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'ftt_sha256', 'FTT update stopped: sha256 of the download does not match the release.' );
		}
		return $tmp;
	}

	public static function source_selection( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || self::basename() !== $hook_extra['plugin'] ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( trailingslashit( $source ) === $want || ! $wp_filesystem ) {
			return $source;
		}
		return $wp_filesystem->move( $source, $want ) ? $want : new WP_Error( 'ftt_folder', 'Could not rename the update folder.' );
	}

	/** Safe status (no token). */
	public static function status( $force = false ) {
		$i = self::release( $force );
		return array(
			'repo'      => self::repo(),
			'installed' => FTT_VERSION,
			'latest'    => $i['version'] ?? '',
			'tag'       => $i['tag'] ?? '',
			'sha256'    => $i['sha256'] ?? '',
			'update'    => self::is_newer( $i ) ? 'yes' : 'no',
			'state'     => $i['error'] ?? 'ok',
			'token'     => self::token_source(),
			'auto'      => in_array( self::basename(), (array) get_site_option( 'auto_update_plugins', array() ), true ) ? 'on' : 'off',
		);
	}
}

/** FTT > Settings > Updates: status and the per-site read token (write-only field; the value is never shown). */
class FTT_Updater_Admin {
	public static function init() {
		add_action( 'admin_post_ftt_updater_token', array( __CLASS__, 'save' ) );
	}

	public static function tab() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$st = FTT_Updater::status( false );
		echo '<h2>' . esc_html__( 'Updates from GitHub', 'film-transfer-toolkit' ) . '</h2>';
		if ( isset( $_GET['ftt_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Token setting saved.', 'film-transfer-toolkit' ) . '</p></div>';
		}
		echo '<table class="form-table" role="presentation">';
		foreach ( array( 'repo' => 'Repository', 'installed' => 'Installed', 'latest' => 'Latest release', 'update' => 'Update available', 'state' => 'Status', 'token' => 'Read token', 'auto' => 'Auto-updates' ) as $k => $l ) {
			echo '<tr><th>' . esc_html( $l ) . '</th><td>' . esc_html( (string) $st[ $k ] ) . '</td></tr>';
		}
		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Updates come from the latest GitHub release and are checked against its sha256 before installing. Publish a release tagged "hold" or add ftt-pause.json {"pause": true} to the repository to pause updates on every site.', 'film-transfer-toolkit' ) . '</p>';
		if ( 'wp-config' === $st['token'] ) {
			echo '<p>' . esc_html__( 'The token is set by FTT_GITHUB_TOKEN in wp-config.php.', 'film-transfer-toolkit' ) . '</p>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ftt_updater_token' );
		echo '<input type="hidden" name="action" value="ftt_updater_token">';
		echo '<p><label for="ftt-gh-token">' . esc_html__( 'GitHub read token (only needed while the repository is private)', 'film-transfer-toolkit' ) . '</label><br><input id="ftt-gh-token" type="password" class="regular-text" name="ftt_token" value="" autocomplete="off" placeholder="' . esc_attr( 'none' === $st['token'] ? __( 'not set', 'film-transfer-toolkit' ) : __( 'set (hidden), type a new one to replace', 'film-transfer-toolkit' ) ) . '"></p>';
		echo '<p><label><input type="checkbox" name="ftt_token_delete" value="1"> ' . esc_html__( 'Remove the saved token', 'film-transfer-toolkit' ) . '</label></p>';
		submit_button( __( 'Save token', 'film-transfer-toolkit' ) );
		echo '</form>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ftt_updater_token' ) ) {
			wp_die( 'Not allowed' );
		}
		if ( ! empty( $_POST['ftt_token_delete'] ) ) {
			FTT_Updater::set_token( '' );
		} elseif ( isset( $_POST['ftt_token'] ) && '' !== trim( wp_unslash( $_POST['ftt_token'] ) ) ) { // phpcs:ignore
			FTT_Updater::set_token( wp_unslash( $_POST['ftt_token'] ) ); // phpcs:ignore
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'ftt-settings', 'tab' => 'updates', 'ftt_token' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** wp ftt-updater status|check|token. Never prints the token. */
	class FTT_Updater_CLI {
		/** Show updater status (cached). */
		public function status() {
			foreach ( FTT_Updater::status( false ) as $k => $v ) {
				WP_CLI::line( "$k: $v" );
			}
		}
		/** Ask GitHub now (refreshes the cache and the update transient). */
		public function check() {
			foreach ( FTT_Updater::status( true ) as $k => $v ) {
				WP_CLI::line( "$k: $v" );
			}
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
		}
		/**
		 * Save or delete the GitHub read token option.
		 *
		 * [<value>]
		 * [--delete]
		 */
		public function token( $args, $assoc ) {
			FTT_Updater::set_token( ! empty( $assoc['delete'] ) ? '' : ( $args[0] ?? '' ) );
			WP_CLI::success( 'token: ' . FTT_Updater::token_source() );
		}
	}
	WP_CLI::add_command( 'ftt-updater', 'FTT_Updater_CLI' );
}
