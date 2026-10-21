<?php
/**
 * Price table storage, presets (EXAMPLE and DRAFT FOR REVIEW), validation and the admin editor.
 * Money is stored as integer mills (1/1000 of the currency unit) so per-foot prices like $0.325 are exact.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Price_Table {
	const OPTION = 'ftt_price_table';
	const PRESETS = array(
		'example' => 'price-table-example.json',
		'draft'   => 'price-table-vfc-draft.json',
	);

	public static function init() {
		add_action( 'admin_post_ftt_save_prices', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ftt_load_preset', array( __CLASS__, 'handle_preset' ) );
		add_action( 'admin_post_ftt_import_prices', array( __CLASS__, 'handle_import' ) );
	}

	public static function preset( $key ) {
		if ( ! isset( self::PRESETS[ $key ] ) ) {
			return null;
		}
		$raw = file_get_contents( FTT_DIR . 'core/data/' . self::PRESETS[ $key ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local bundled file.
		$t   = json_decode( (string) $raw, true );
		return is_array( $t ) ? self::sanitize( $t )['table'] : null;
	}

	/** The active table. Falls back to the EXAMPLE preset when nothing valid is stored. */
	public static function get() {
		$t = get_option( self::OPTION, null );
		if ( is_array( $t ) && ! empty( $t['formats'] ) ) {
			return $t;
		}
		return self::preset( 'example' );
	}

	public static function save( $table ) {
		$r = self::sanitize( $table );
		if ( $r['errors'] ) {
			return $r['errors'];
		}
		update_option( self::OPTION, $r['table'], false );
		return array();
	}

	/* ------------------------------------------------------------ validation */

	private static function id( $v ) {
		$v = is_scalar( $v ) ? strtolower( trim( (string) $v ) ) : '';
		return preg_match( '/^[a-z0-9][a-z0-9_.-]{0,31}$/', $v ) ? $v : '';
	}

	private static function inum( $v, $min, $max ) {
		if ( ! is_numeric( $v ) ) {
			return null;
		}
		$n = (int) round( (float) $v );
		return ( $n < $min || $n > $max ) ? null : $n;
	}

	private static function fnum( $v, $min, $max ) {
		if ( ! is_numeric( $v ) ) {
			return null;
		}
		$n = (float) $v;
		return ( $n < $min || $n > $max ) ? null : $n;
	}

	/** "12", "0.325", "$1,250.50" -> mills, or null. Exact (no float rounding). */
	public static function dollars_to_mills( $v ) {
		$v = is_scalar( $v ) ? preg_replace( '/[\s$,]/', '', (string) $v ) : '';
		if ( ! preg_match( '/^(\d{1,7})(?:\.(\d{1,3}))?$/', $v, $m ) ) {
			return null;
		}
		return (int) $m[1] * 1000 + (int) str_pad( $m[2] ?? '', 3, '0' );
	}

	public static function mills_to_dollars( $m ) {
		$m = (int) $m;
		$s = intdiv( $m, 1000 ) . '.' . sprintf( '%03d', $m % 1000 );
		return preg_replace( '/0$/', '', $s ); // 0.320 -> 0.32, 0.325 stays.
	}

	/**
	 * @return array { table: array, errors: string[] }
	 */
	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$err = array();
		$t   = array(
			'schema'     => 1,
			'currency'   => in_array( $in['currency'] ?? 'USD', array( 'USD', 'CAD', 'EUR', 'GBP', 'AUD' ), true ) ? $in['currency'] : 'USD',
			'status'     => in_array( $in['status'] ?? '', array( 'example', 'draft', 'final' ), true ) ? $in['status'] : 'example',
			'label'      => FTT_Util::text( $in['label'] ?? '', 160 ),
			'disclaimer' => FTT_Util::text( $in['disclaimer'] ?? '', 400 ),
			'as_of'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['as_of'] ?? '' ) ) ? $in['as_of'] : gmdate( 'Y-m-d' ),
			'formats'    => array(),
			'services'   => array(),
			'reels'      => array(),
			'rates'      => array(),
			'addons'     => array(),
			'fees'       => array(),
			'tiers'      => array(),
		);
		if ( 'example' === $t['status'] && '' === $t['label'] ) {
			$t['label'] = 'EXAMPLE PRICES - placeholders, not real rates';
		}
		if ( 'draft' === $t['status'] && false === stripos( $t['label'], 'DRAFT' ) ) {
			$t['label'] = trim( 'DRAFT FOR REVIEW - ' . $t['label'], ' -' );
		}
		foreach ( array_slice( (array) ( $in['formats'] ?? array() ), 0, 20 ) as $f ) {
			$id  = self::id( $f['id'] ?? '' );
			$fpf = self::fnum( $f['fpf'] ?? null, 1, 200 );
			$fps = self::fnum( $f['fps'] ?? null, 1, 120 );
			if ( ! $id || null === $fpf || null === $fps ) { $err[] = 'A film format row is incomplete (id, frames per foot 1-200, fps 1-120).'; continue; }
			$t['formats'][] = array( 'id' => $id, 'label' => FTT_Util::text( $f['label'] ?? $id, 80 ), 'fpf' => $fpf, 'fps' => $fps );
		}
		$fids = array_column( $t['formats'], 'id' );
		if ( ! $fids ) { $err[] = 'At least one film format is required.'; }
		foreach ( array_slice( (array) ( $in['services'] ?? array() ), 0, 20 ) as $s ) {
			$id = self::id( $s['id'] ?? '' );
			if ( ! $id ) { $err[] = 'A service row needs an id.'; continue; }
			$t['services'][] = array( 'id' => $id, 'label' => FTT_Util::text( $s['label'] ?? $id, 80 ) );
		}
		$sids = array_column( $t['services'], 'id' );
		if ( ! $sids ) { $err[] = 'At least one service is required.'; }
		foreach ( array_slice( (array) ( $in['reels'] ?? array() ), 0, 100 ) as $r ) {
			$fmt  = self::id( $r['format'] ?? '' );
			$size = self::id( $r['size'] ?? '' );
			$feet = self::inum( $r['feet'] ?? null, 1, 100000 );
			if ( ! in_array( $fmt, $fids, true ) || ! $size || null === $feet ) { $err[] = 'A reel size row is incomplete or uses an unknown format.'; continue; }
			$t['reels'][] = array( 'format' => $fmt, 'size' => $size, 'label' => FTT_Util::text( $r['label'] ?? $size, 40 ), 'feet' => $feet );
		}
		$seen = array();
		foreach ( array_slice( (array) ( $in['rates'] ?? array() ), 0, 500 ) as $r ) {
			$fmt   = self::id( $r['format'] ?? '' );
			$svc   = self::id( $r['service'] ?? '' );
			$unit  = ( $r['unit'] ?? '' ) === 'reel' ? 'reel' : 'foot';
			$size  = 'reel' === $unit ? self::id( $r['size'] ?? '' ) : '';
			$price = self::inum( $r['price'] ?? null, 0, 100000000 );
			if ( ! in_array( $fmt, $fids, true ) || ! in_array( $svc, $sids, true ) || null === $price ) { $err[] = 'A rate row is incomplete or uses an unknown format or service.'; continue; }
			if ( 'reel' === $unit && ! array_filter( $t['reels'], function ( $x ) use ( $fmt, $size ) { return $x['format'] === $fmt && $x['size'] === $size; } ) ) { $err[] = "A per-reel rate uses reel size \"$size\", which is not listed for that format."; continue; }
			$k = "$fmt|$svc|$unit|$size";
			if ( isset( $seen[ $k ] ) ) { $err[] = "Duplicate rate for $fmt / $svc / $unit $size."; continue; }
			$seen[ $k ] = 1;
			$row        = array( 'format' => $fmt, 'service' => $svc, 'unit' => $unit, 'price' => $price );
			if ( 'reel' === $unit ) {
				$row = array( 'format' => $fmt, 'service' => $svc, 'unit' => 'reel', 'size' => $size, 'price' => $price );
			}
			$t['rates'][] = $row;
		}
		$aseen = array();
		foreach ( array_slice( (array) ( $in['addons'] ?? array() ), 0, 50 ) as $a ) {
			$id    = self::id( $a['id'] ?? '' );
			$unit  = in_array( $a['unit'] ?? '', array( 'foot', 'reel', 'order' ), true ) ? $a['unit'] : '';
			$price = self::inum( $a['price'] ?? null, 0, 100000000 );
			if ( ! $id || ! $unit || null === $price || isset( $aseen[ $id ] ) ) { $err[] = 'An add-on row is incomplete or its id is repeated.'; continue; }
			$aseen[ $id ] = 1;
			$fl           = array_values( array_intersect( array_map( array( __CLASS__, 'id' ), (array) ( $a['formats'] ?? array() ) ), $fids ) );
			$t['addons'][] = array( 'id' => $id, 'label' => FTT_Util::text( $a['label'] ?? $id, 80 ), 'unit' => $unit, 'price' => $price, 'formats' => $fl );
		}
		$fees      = (array) ( $in['fees'] ?? array() );
		$t['fees'] = array(
			'setup'             => (int) self::inum( $fees['setup'] ?? 0, 0, 100000000 ),
			'min_order'         => (int) self::inum( $fees['min_order'] ?? 0, 0, 100000000 ),
			'min_feet_per_reel' => (int) self::inum( $fees['min_feet_per_reel'] ?? 0, 0, 10000 ),
		);
		foreach ( array_slice( (array) ( $in['tiers'] ?? array() ), 0, 10 ) as $x ) {
			$mf  = self::inum( $x['min_feet'] ?? null, 1, 10000000 );
			$pct = self::inum( $x['pct'] ?? null, 0, 100 );
			if ( null === $mf || null === $pct ) { $err[] = 'A volume tier needs minimum feet (1 or more) and a whole percent 0-100.'; continue; }
			$t['tiers'][] = array( 'min_feet' => $mf, 'pct' => $pct );
		}
		usort( $t['tiers'], function ( $a, $b ) { return $a['min_feet'] <=> $b['min_feet']; } );
		return array( 'table' => $t, 'errors' => array_values( array_unique( $err ) ) );
	}

	/* ---------------------------------------------------------- admin forms */

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'film-transfer-toolkit' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( $code, $errors = array() ) {
		if ( $errors ) {
			set_transient( 'ftt_price_errors_' . get_current_user_id(), $errors, 300 );
		}
		wp_safe_redirect( FTT_Admin_UX::url( 'ftt-settings', 'prices', array( 'ftt_msg' => $code ) ) );
		exit;
	}

	public static function handle_save() {
		self::guard( 'ftt_save_prices' );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard().
		$cur = self::get();
		$adv = array();
		$err = array();
		foreach ( array( 'formats', 'services', 'reels' ) as $k ) {
			$raw = isset( $p[ 'json_' . $k ] ) ? (string) $p[ 'json_' . $k ] : '';
			$dec = json_decode( $raw, true );
			if ( ! is_array( $dec ) ) { $err[] = "The $k JSON is not valid; it was not changed."; $dec = $cur[ $k ]; }
			$adv[ $k ] = $dec;
		}
		$money = function ( $v, $label ) use ( &$err ) {
			$m = self::dollars_to_mills( $v );
			if ( null === $m ) { $err[] = "$label: \"" . sanitize_text_field( (string) $v ) . '" is not a price (use 12, 0.32 or 0.325).'; }
			return $m;
		};
		$rates = array();
		foreach ( (array) ( $p['rates'] ?? array() ) as $r ) {
			if ( ! is_array( $r ) || '' === trim( (string) ( $r['price'] ?? '' ) ) ) { continue; }
			$rates[] = array( 'format' => $r['format'] ?? '', 'service' => $r['service'] ?? '', 'unit' => $r['unit'] ?? 'foot', 'size' => $r['size'] ?? '', 'price' => $money( $r['price'], 'Rate' ) );
		}
		$addons = array();
		foreach ( (array) ( $p['addons'] ?? array() ) as $a ) {
			if ( ! is_array( $a ) || '' === trim( (string) ( $a['id'] ?? '' ) ) ) { continue; }
			$addons[] = array( 'id' => $a['id'], 'label' => $a['label'] ?? '', 'unit' => $a['unit'] ?? '', 'price' => $money( $a['price'] ?? '', 'Add-on ' . sanitize_key( $a['id'] ) ), 'formats' => array_filter( array_map( 'trim', explode( ',', (string) ( $a['formats'] ?? '' ) ) ) ) );
		}
		$tiers = array();
		foreach ( (array) ( $p['tiers'] ?? array() ) as $x ) {
			if ( is_array( $x ) && '' !== trim( (string) ( $x['min_feet'] ?? '' ) ) ) { $tiers[] = array( 'min_feet' => $x['min_feet'], 'pct' => $x['pct'] ?? '' ); }
		}
		$table = array(
			'currency' => $p['currency'] ?? 'USD', 'status' => $p['status'] ?? 'example', 'label' => $p['label'] ?? '', 'disclaimer' => $p['disclaimer'] ?? '', 'as_of' => $p['as_of'] ?? '',
			'formats' => $adv['formats'], 'services' => $adv['services'], 'reels' => $adv['reels'], 'rates' => $rates, 'addons' => $addons, 'tiers' => $tiers,
			'fees' => array( 'setup' => $money( $p['fee_setup'] ?? '0', 'Setup fee' ), 'min_order' => $money( $p['fee_min_order'] ?? '0', 'Order minimum' ), 'min_feet_per_reel' => $p['fee_min_feet'] ?? 0 ),
		);
		if ( $err ) {
			self::back( 'error', $err );
		}
		$err = self::save( $table );
		self::back( $err ? 'error' : 'saved', $err );
	}

	public static function handle_preset() {
		self::guard( 'ftt_load_preset' );
		$key = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard().
		$t   = self::preset( $key );
		if ( ! $t ) {
			self::back( 'error', array( 'Unknown preset.' ) );
		}
		update_option( self::OPTION, $t, false );
		self::back( 'preset_' . $key );
	}

	public static function handle_import() {
		self::guard( 'ftt_import_prices' );
		$raw = isset( $_POST['json'] ) ? (string) wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard().
		if ( strlen( $raw ) > 200000 ) {
			self::back( 'error', array( 'That JSON is too large.' ) );
		}
		$t = json_decode( $raw, true );
		if ( ! is_array( $t ) ) {
			self::back( 'error', array( 'That is not valid JSON.' ) );
		}
		$err = self::save( $t );
		self::back( $err ? 'error' : 'imported', $err );
	}

	/* ------------------------------------------------------------- display */

	public static function banner( $t ) {
		if ( 'final' === ( $t['status'] ?? '' ) ) {
			return '';
		}
		$cls = 'draft' === $t['status'] ? 'ftt-banner--draft' : 'ftt-banner--example';
		return '<p class="ftt-banner ' . esc_attr( $cls ) . '" role="note"><strong>' . esc_html( $t['label'] ) . '</strong>'
			. ( '' !== $t['disclaimer'] ? '<br>' . esc_html( $t['disclaimer'] ) : '' )
			. ' <span class="ftt-banner__date">' . esc_html( sprintf( /* translators: %s: date */ __( 'Prices as of %s.', 'film-transfer-toolkit' ), $t['as_of'] ) ) . '</span></p>';
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
		}
		wp_enqueue_script( 'ftt-admin', FTT_URL . 'assets/js/admin.js', array(), FTT_VERSION, true );
		$t    = self::get();
		$msg  = isset( $_GET['ftt_msg'] ) ? sanitize_key( wp_unslash( $_GET['ftt_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		$errs = get_transient( 'ftt_price_errors_' . get_current_user_id() );
		delete_transient( 'ftt_price_errors_' . get_current_user_id() );
		$notes = array( 'saved' => __( 'Price table saved.', 'film-transfer-toolkit' ), 'imported' => __( 'Price table imported.', 'film-transfer-toolkit' ), 'preset_example' => __( 'EXAMPLE prices loaded.', 'film-transfer-toolkit' ), 'preset_draft' => __( 'DRAFT FOR REVIEW prices loaded.', 'film-transfer-toolkit' ) );
		$fmt_opts = array_column( $t['formats'], 'label', 'id' );
		$svc_opts = array_column( $t['services'], 'label', 'id' );
		$sel = function ( $name, $opts, $val ) {
			$h = '<select name="' . esc_attr( $name ) . '">';
			foreach ( $opts as $k => $l ) { $h .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $l ) . '</option>'; }
			return $h . '</select>';
		};
		$rate_row = function ( $i, $r ) use ( $sel, $fmt_opts, $svc_opts ) {
			return '<tr><td>' . $sel( "rates[$i][format]", $fmt_opts, $r['format'] ?? '' ) . '</td><td>' . $sel( "rates[$i][service]", $svc_opts, $r['service'] ?? '' ) . '</td><td>'
				. $sel( "rates[$i][unit]", array( 'foot' => 'per foot', 'reel' => 'per reel' ), $r['unit'] ?? 'foot' ) . '</td><td><input type="text" class="small-text" name="' . esc_attr( "rates[$i][size]" ) . '" value="' . esc_attr( $r['size'] ?? '' ) . '" placeholder="7"></td>'
				. '<td>$<input type="text" class="small-text" inputmode="decimal" name="' . esc_attr( "rates[$i][price]" ) . '" value="' . esc_attr( isset( $r['price'] ) ? self::mills_to_dollars( $r['price'] ) : '' ) . '"></td><td><button type="button" class="button-link-delete" data-ftt-remove>' . esc_html__( 'Remove', 'film-transfer-toolkit' ) . '</button></td></tr>';
		};
		$addon_row = function ( $i, $a ) use ( $sel ) {
			return '<tr><td><input type="text" class="small-text" name="' . esc_attr( "addons[$i][id]" ) . '" value="' . esc_attr( $a['id'] ?? '' ) . '"></td><td><input type="text" class="regular-text" name="' . esc_attr( "addons[$i][label]" ) . '" value="' . esc_attr( $a['label'] ?? '' ) . '"></td><td>'
				. $sel( "addons[$i][unit]", array( 'foot' => 'per foot', 'reel' => 'per reel', 'order' => 'per order' ), $a['unit'] ?? 'foot' ) . '</td><td>$<input type="text" class="small-text" inputmode="decimal" name="' . esc_attr( "addons[$i][price]" ) . '" value="' . esc_attr( isset( $a['price'] ) ? self::mills_to_dollars( $a['price'] ) : '' ) . '"></td>'
				. '<td><input type="text" name="' . esc_attr( "addons[$i][formats]" ) . '" value="' . esc_attr( implode( ',', $a['formats'] ?? array() ) ) . '" placeholder="all"></td><td><button type="button" class="button-link-delete" data-ftt-remove>' . esc_html__( 'Remove', 'film-transfer-toolkit' ) . '</button></td></tr>';
		};
		$tier_row = function ( $i, $x ) {
			return '<tr><td><input type="number" min="1" name="' . esc_attr( "tiers[$i][min_feet]" ) . '" value="' . esc_attr( $x['min_feet'] ?? '' ) . '"></td><td><input type="number" min="0" max="100" class="small-text" name="' . esc_attr( "tiers[$i][pct]" ) . '" value="' . esc_attr( $x['pct'] ?? '' ) . '"> %</td><td><button type="button" class="button-link-delete" data-ftt-remove>' . esc_html__( 'Remove', 'film-transfer-toolkit' ) . '</button></td></tr>';
		};
		$json = function ( $v ) { return wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); };
		// The row builders above escape every value; the static markup is safe.
		?>
		<div class="wrap ftt-admin">
			<h1><?php esc_html_e( 'Price table', 'film-transfer-toolkit' ); ?></h1>
			<?php if ( isset( $notes[ $msg ] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notes[ $msg ] ); ?></p></div><?php endif; ?>
			<?php if ( is_array( $errs ) && $errs ) : ?><div class="notice notice-error"><p><?php esc_html_e( 'Nothing was saved:', 'film-transfer-toolkit' ); ?></p><ul><?php foreach ( $errs as $e ) { echo '<li>' . esc_html( $e ) . '</li>'; } ?></ul></div><?php endif; ?>
			<?php echo self::banner( $t ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in banner(). ?>
			<p><?php esc_html_e( 'Used by the [ftt_price_table] shortcode and the estimate builder. The server always re-prices estimates with this table; the browser figure is only a preview.', 'film-transfer-toolkit' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ftt-inline-form">
				<input type="hidden" name="action" value="ftt_load_preset"><?php wp_nonce_field( 'ftt_load_preset' ); ?>
				<button class="button" name="preset" value="example" data-ftt-confirm="<?php esc_attr_e( 'Replace the current table with the EXAMPLE placeholder prices?', 'film-transfer-toolkit' ); ?>"><?php esc_html_e( 'Load EXAMPLE prices', 'film-transfer-toolkit' ); ?></button>
				<button class="button" name="preset" value="draft" data-ftt-confirm="<?php esc_attr_e( 'Replace the current table with the DRAFT FOR REVIEW prices?', 'film-transfer-toolkit' ); ?>"><?php esc_html_e( 'Load DRAFT FOR REVIEW prices', 'film-transfer-toolkit' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ftt_save_prices"><?php wp_nonce_field( 'ftt_save_prices' ); ?>
				<h2><?php esc_html_e( 'Status', 'film-transfer-toolkit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="ftt-status"><?php esc_html_e( 'Status', 'film-transfer-toolkit' ); ?></label></th><td><select id="ftt-status" name="status"><?php foreach ( array( 'example' => 'EXAMPLE (placeholders)', 'draft' => 'DRAFT FOR REVIEW', 'final' => 'Final (no banner)' ) as $k => $l ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $t['status'], $k, false ) . '>' . esc_html( $l ) . '</option>'; } ?></select>
						<p class="description"><?php esc_html_e( 'EXAMPLE and DRAFT tables always show a warning banner wherever prices are displayed.', 'film-transfer-toolkit' ); ?></p></td></tr>
					<tr><th><label for="ftt-label"><?php esc_html_e( 'Banner label', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-label" class="large-text" name="label" value="<?php echo esc_attr( $t['label'] ); ?>"></td></tr>
					<tr><th><label for="ftt-disc"><?php esc_html_e( 'Disclaimer', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-disc" class="large-text" name="disclaimer" value="<?php echo esc_attr( $t['disclaimer'] ); ?>"></td></tr>
					<tr><th><label for="ftt-asof"><?php esc_html_e( 'Prices as of', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-asof" type="date" name="as_of" value="<?php echo esc_attr( $t['as_of'] ); ?>"> <select name="currency" aria-label="<?php esc_attr_e( 'Currency', 'film-transfer-toolkit' ); ?>"><?php foreach ( array( 'USD', 'CAD', 'EUR', 'GBP', 'AUD' ) as $c ) { echo '<option' . selected( $t['currency'], $c, false ) . '>' . esc_html( $c ) . '</option>'; } ?></select></td></tr>
				</table>
				<h2><?php esc_html_e( 'Fees and minimums', 'film-transfer-toolkit' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="ftt-setup"><?php esc_html_e( 'Setup fee (per order)', 'film-transfer-toolkit' ); ?></label></th><td>$<input id="ftt-setup" class="small-text" name="fee_setup" value="<?php echo esc_attr( self::mills_to_dollars( $t['fees']['setup'] ) ); ?>"></td></tr>
					<tr><th><label for="ftt-min"><?php esc_html_e( 'Order minimum', 'film-transfer-toolkit' ); ?></label></th><td>$<input id="ftt-min" class="small-text" name="fee_min_order" value="<?php echo esc_attr( self::mills_to_dollars( $t['fees']['min_order'] ) ); ?>"></td></tr>
					<tr><th><label for="ftt-minft"><?php esc_html_e( 'Minimum billed feet per reel', 'film-transfer-toolkit' ); ?></label></th><td><input id="ftt-minft" type="number" min="0" class="small-text" name="fee_min_feet" value="<?php echo esc_attr( $t['fees']['min_feet_per_reel'] ); ?>"> ft</td></tr>
				</table>
				<h2><?php esc_html_e( 'Rates', 'film-transfer-toolkit' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Per-foot rates are required for every format and service you offer. Per-reel rates are optional: they apply when a customer picks a full reel of that size. Partial reels (footage typed in) use the per-foot rate.', 'film-transfer-toolkit' ); ?></p>
				<table class="widefat striped ftt-rows"><thead><tr><th><?php esc_html_e( 'Format', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Service', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Unit', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Reel size', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Price', 'film-transfer-toolkit' ); ?></th><th></th></tr></thead>
					<tbody data-ftt-rows="rates"><?php foreach ( $t['rates'] as $i => $r ) { echo $rate_row( $i, $r ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
				<template data-ftt-template="rates"><?php echo $rate_row( '__i__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
				<p><button type="button" class="button" data-ftt-add="rates"><?php esc_html_e( 'Add rate', 'film-transfer-toolkit' ); ?></button></p>
				<h2><?php esc_html_e( 'Add-ons', 'film-transfer-toolkit' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Formats: comma-separated format ids (for example s8,16); leave blank for all formats. Per-order add-ons are chosen once for the whole estimate.', 'film-transfer-toolkit' ); ?></p>
				<table class="widefat striped ftt-rows"><thead><tr><th>ID</th><th><?php esc_html_e( 'Label', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Unit', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Price', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Formats', 'film-transfer-toolkit' ); ?></th><th></th></tr></thead>
					<tbody data-ftt-rows="addons"><?php foreach ( $t['addons'] as $i => $a ) { echo $addon_row( $i, $a ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
				<template data-ftt-template="addons"><?php echo $addon_row( '__i__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
				<p><button type="button" class="button" data-ftt-add="addons"><?php esc_html_e( 'Add add-on', 'film-transfer-toolkit' ); ?></button></p>
				<h2><?php esc_html_e( 'Volume discounts', 'film-transfer-toolkit' ); ?></h2>
				<table class="widefat striped ftt-rows" style="max-width:520px"><thead><tr><th><?php esc_html_e( 'From total billed feet', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Discount', 'film-transfer-toolkit' ); ?></th><th></th></tr></thead>
					<tbody data-ftt-rows="tiers"><?php foreach ( $t['tiers'] as $i => $x ) { echo $tier_row( $i, $x ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
				<template data-ftt-template="tiers"><?php echo $tier_row( '__i__', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
				<p><button type="button" class="button" data-ftt-add="tiers"><?php esc_html_e( 'Add tier', 'film-transfer-toolkit' ); ?></button></p>
				<details class="ftt-advanced" id="ftt-adv-price-json"><summary><?php esc_html_e( 'Advanced: formats, services and reel sizes (JSON)', 'film-transfer-toolkit' ); ?></summary>
					<?php foreach ( array( 'formats' => 'id, label, fpf (frames per foot), fps (for running time)', 'services' => 'id, label', 'reels' => 'format, size (id), label, feet (nominal full-reel length)' ) as $k => $h ) : ?>
						<p><label for="ftt-json-<?php echo esc_attr( $k ); ?>"><strong><?php echo esc_html( ucfirst( $k ) ); ?></strong> <span class="description"><?php echo esc_html( $h ); ?></span></label><br>
						<textarea id="ftt-json-<?php echo esc_attr( $k ); ?>" name="json_<?php echo esc_attr( $k ); ?>" rows="8" class="large-text code"><?php echo esc_textarea( $json( $t[ $k ] ) ); ?></textarea></p>
					<?php endforeach; ?>
				</details>
				<?php submit_button( __( 'Save price table', 'film-transfer-toolkit' ) ); ?>
			</form>

			<details class="ftt-advanced" id="ftt-adv-price-export"><summary><?php esc_html_e( 'Advanced: export or import the whole table (JSON)', 'film-transfer-toolkit' ); ?></summary><div class="ftt-advanced__body">
			<p><label for="ftt-export"><?php esc_html_e( 'Current table as JSON (same format the mobile app core reads):', 'film-transfer-toolkit' ); ?></label></p>
			<textarea id="ftt-export" rows="6" class="large-text code" readonly><?php echo esc_textarea( $json( $t ) ); ?></textarea>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ftt_import_prices"><?php wp_nonce_field( 'ftt_import_prices' ); ?>
				<p><label for="ftt-import"><?php esc_html_e( 'Import (replaces the whole table after validation):', 'film-transfer-toolkit' ); ?></label></p>
				<textarea id="ftt-import" name="json" rows="6" class="large-text code"></textarea>
				<?php submit_button( __( 'Import JSON', 'film-transfer-toolkit' ), 'secondary' ); ?>
			</form>
			</div></details>
		</div>
		<?php
	}
}
