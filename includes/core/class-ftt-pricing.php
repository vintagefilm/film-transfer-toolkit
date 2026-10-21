<?php
/**
 * PHP mirror of FTTCore.pricing (core/ftt-core.js). Used for server-side re-pricing.
 * Keep the two in step: tests/core-tests.php and tests/core-tests.js run the same vectors,
 * and tests/parity.js compares both engines on random requests.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Pricing {
	const LIMITS = array( 'lines' => 50, 'reels' => 999, 'feet' => 100000, 'addons' => 20, 'qty' => 999 );

	public static function to_cents( $mills ) {
		return (int) floor( ( $mills + 5 ) / 10 );
	}

	private static function int( $v ) {
		return is_numeric( $v ) ? (int) round( (float) $v ) : 0;
	}

	private static function find( $list, $pred ) {
		foreach ( (array) $list as $x ) {
			if ( is_array( $x ) && $pred( $x ) ) {
				return $x;
			}
		}
		return null;
	}

	private static function str( $v ) {
		return is_scalar( $v ) ? (string) $v : '';
	}

	private static function num( $v ) {
		if ( is_bool( $v ) ) {
			return $v ? 1.0 : 0.0;
		}
		return is_numeric( $v ) ? (float) $v : NAN;
	}

	private static function err( $line, $code, $message ) {
		return array( 'line' => $line, 'code' => $code, 'message' => $message );
	}

	/**
	 * @param array $table   Price table (money in integer mills).
	 * @param array $request { lines: [ {format, service, reels, size?, feet?, addons[]} ], order_addons: [] }
	 * @return array Same shape as FTTCore.pricing.quote().
	 */
	public static function quote( $table, $request ) {
		$table   = is_array( $table ) ? $table : array();
		$request = is_array( $request ) ? $request : array();
		$fees    = isset( $table['fees'] ) && is_array( $table['fees'] ) ? $table['fees'] : array();
		$min_ft  = max( 0, self::int( $fees['min_feet_per_reel'] ?? 0 ) );
		$lines   = array();
		$errors  = array();
		$rq      = isset( $request['lines'] ) && is_array( $request['lines'] ) ? array_values( $request['lines'] ) : array();
		if ( count( $rq ) > self::LIMITS['lines'] ) {
			$errors[] = self::err( -1, 'too_many_lines', 'Too many lines in one estimate.' );
			$rq       = array_slice( $rq, 0, self::LIMITS['lines'] );
		}
		$sum_cents = 0;
		$sum_bill  = 0;
		$sum_feet  = 0;
		$sum_sec   = 0;

		foreach ( $rq as $i => $l ) {
			$l   = is_array( $l ) ? $l : array();
			$fid = self::str( $l['format'] ?? '' );
			$fmt = self::find( $table['formats'] ?? array(), function ( $f ) use ( $fid ) { return ( $f['id'] ?? null ) === $fid; } );
			if ( ! $fmt ) { $errors[] = self::err( $i, 'bad_format', 'Unknown film format.' ); continue; }
			$sid = self::str( $l['service'] ?? '' );
			$svc = self::find( $table['services'] ?? array(), function ( $s ) use ( $sid ) { return ( $s['id'] ?? null ) === $sid; } );
			if ( ! $svc ) { $errors[] = self::err( $i, 'bad_service', 'Unknown service.' ); continue; }
			$reels = ( ! isset( $l['reels'] ) || '' === $l['reels'] ) ? 1.0 : self::num( $l['reels'] );
			if ( ! ( $reels >= 1 && $reels <= self::LIMITS['reels'] && floor( $reels ) == $reels ) ) { $errors[] = self::err( $i, 'bad_reels', 'Number of reels must be a whole number from 1 to 999.' ); continue; }
			$reels    = (int) $reels;
			$has_feet = isset( $l['feet'] ) && '' !== $l['feet'];
			$feet     = $has_feet ? self::num( $l['feet'] ) : 0.0;
			if ( $has_feet && ! ( $feet > 0 && $feet <= self::LIMITS['feet'] ) ) { $errors[] = self::err( $i, 'bad_feet', 'Footage must be more than 0 and at most 100000 feet.' ); continue; }
			$size = isset( $l['size'] ) ? self::str( $l['size'] ) : '';
			$reel = null;
			if ( '' !== $size ) {
				$reel = self::find( $table['reels'] ?? array(), function ( $r ) use ( $fmt, $size ) { return ( $r['format'] ?? null ) === $fmt['id'] && ( $r['size'] ?? null ) === $size; } );
				if ( ! $reel ) { $errors[] = self::err( $i, 'bad_size', 'That reel size is not listed for this format.' ); continue; }
			}
			if ( ! $has_feet && ! $reel ) { $errors[] = self::err( $i, 'need_length', 'Enter a reel size or the footage.' ); continue; }
			$total_feet = $has_feet ? $feet : $reel['feet'] * $reels;

			$reel_rate = ( ! $has_feet && $reel ) ? self::find( $table['rates'] ?? array(), function ( $r ) use ( $fmt, $svc, $size ) {
				return ( $r['format'] ?? null ) === $fmt['id'] && ( $r['service'] ?? null ) === $svc['id'] && ( $r['unit'] ?? null ) === 'reel' && ( $r['size'] ?? null ) === $size; } ) : null;
			$foot_rate = self::find( $table['rates'] ?? array(), function ( $r ) use ( $fmt, $svc ) {
				return ( $r['format'] ?? null ) === $fmt['id'] && ( $r['service'] ?? null ) === $svc['id'] && ( $r['unit'] ?? null ) === 'foot'; } );
			if ( $reel_rate ) {
				$method = 'reel';
				$base   = self::int( $reel_rate['price'] ?? 0 ) * $reels;
				$bill   = (int) $reel['feet'] * $reels;
			} elseif ( $foot_rate ) {
				$method = 'foot';
				$per    = max( $min_ft, (int) ceil( $total_feet / $reels - 1e-9 ) );
				$bill   = $per * $reels;
				$base   = self::int( $foot_rate['price'] ?? 0 ) * $bill;
			} else { $errors[] = self::err( $i, 'no_rate', 'This service is not offered for this format.' ); continue; }

			$ids  = isset( $l['addons'] ) && is_array( $l['addons'] ) ? array_slice( array_values( $l['addons'] ), 0, self::LIMITS['addons'] ) : array();
			$seen = array();
			$adds = array();
			$addc = 0;
			$bad  = null;
			foreach ( $ids as $raw ) {
				$id = self::str( $raw );
				if ( isset( $seen[ $id ] ) ) { continue; }
				$seen[ $id ] = 1;
				$ad = self::find( $table['addons'] ?? array(), function ( $x ) use ( $id ) { return ( $x['id'] ?? null ) === $id; } );
				if ( ! $ad ) { $bad = self::err( $i, 'bad_addon', 'Unknown add-on.' ); break; }
				if ( in_array( $ad['unit'] ?? '', array( 'order', 'custom' ), true ) ) { $bad = self::err( $i, 'order_addon_on_line', 'That add-on applies to the whole order.' ); break; }
				$fl = isset( $ad['formats'] ) && is_array( $ad['formats'] ) ? $ad['formats'] : array();
				if ( $fl && ! in_array( $fmt['id'], $fl, true ) ) { $bad = self::err( $i, 'addon_not_available', 'That add-on is not available for this format.' ); break; }
				$c      = self::to_cents( self::int( $ad['price'] ?? 0 ) * ( 'reel' === ( $ad['unit'] ?? '' ) ? $reels : $bill ) );
				$adds[] = array( 'id' => $ad['id'], 'label' => $ad['label'] ?? '', 'cents' => $c );
				$addc  += $c;
			}
			if ( $bad ) { $errors[] = $bad; continue; }
			$base_c  = self::to_cents( $base );
			$seconds = (int) round( $total_feet * $fmt['fpf'] / $fmt['fps'] );
			$lines[] = array(
				'index' => $i, 'format' => $fmt['id'], 'format_label' => $fmt['label'] ?? '', 'service' => $svc['id'], 'service_label' => $svc['label'] ?? '',
				'reels' => $reels, 'size' => $size, 'feet' => $total_feet, 'billable_feet' => $bill, 'method' => $method, 'seconds' => $seconds,
				'base_cents' => $base_c, 'addons' => $adds, 'addons_cents' => $addc, 'line_cents' => $base_c + $addc,
			);
			$sum_cents += $base_c + $addc;
			$sum_bill  += $bill;
			$sum_feet  += $total_feet;
			$sum_sec   += $seconds;
		}

		$pct   = 0;
		$tiers = isset( $table['tiers'] ) && is_array( $table['tiers'] ) ? array_values( $table['tiers'] ) : array();
		usort( $tiers, function ( $x, $y ) { return self::int( $y['min_feet'] ?? 0 ) <=> self::int( $x['min_feet'] ?? 0 ); } );
		foreach ( $tiers as $t ) {
			if ( $sum_bill >= self::int( $t['min_feet'] ?? 0 ) ) { $pct = min( 100, max( 0, self::int( $t['pct'] ?? 0 ) ) ); break; }
		}
		$discount = (int) floor( ( $sum_cents * $pct + 50 ) / 100 );

		$oadds = array();
		$oc    = 0;
		$oids  = isset( $request['order_addons'] ) && is_array( $request['order_addons'] ) ? array_slice( array_values( $request['order_addons'] ), 0, self::LIMITS['addons'] ) : array();
		$oseen = array();
		foreach ( $oids as $raw ) {
			// An order add-on is an id, or { id, qty } for a per-custom-unit add-on (qty defaults to 1). Mirrors ftt-core.js.
			$is_obj = is_array( $raw ) && ( ! $raw || array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) );
			$oid    = $is_obj ? self::str( $raw['id'] ?? '' ) : self::str( $raw );
			if ( isset( $oseen[ $oid ] ) ) { continue; }
			$oseen[ $oid ] = 1;
			$oa = self::find( $table['addons'] ?? array(), function ( $x ) use ( $oid ) { return ( $x['id'] ?? null ) === $oid && in_array( $x['unit'] ?? '', array( 'order', 'custom' ), true ); } );
			if ( ! $oa ) { $errors[] = self::err( -1, 'bad_order_addon', 'Unknown order add-on.' ); continue; }
			if ( 'custom' === $oa['unit'] ) {
				$qv = $is_obj && isset( $raw['qty'] ) ? $raw['qty'] : 1;
				if ( is_int( $qv ) ) { $qn = $qv; } elseif ( is_float( $qv ) && is_finite( $qv ) && floor( $qv ) === $qv && $qv >= 1 && $qv <= self::LIMITS['qty'] ) { $qn = (int) $qv; } elseif ( is_string( $qv ) && preg_match( '/^[0-9]{1,4}$/', $qv ) ) { $qn = (int) $qv; } else { $qn = 0; }
				if ( $qn < 1 || $qn > self::LIMITS['qty'] ) { $errors[] = self::err( -1, 'bad_quantity', 'Quantity must be a whole number from 1 to 999.' ); continue; }
				$qc      = self::to_cents( self::int( $oa['price'] ?? 0 ) * $qn );
				$oadds[] = array( 'id' => $oa['id'], 'label' => $oa['label'] ?? '', 'cents' => $qc, 'qty' => $qn, 'unit_label' => $oa['unit_label'] ?? '', 'unit_mills' => self::int( $oa['price'] ?? 0 ) );
				$oc     += $qc;
				continue;
			}
			$c       = self::to_cents( self::int( $oa['price'] ?? 0 ) );
			$oadds[] = array( 'id' => $oa['id'], 'label' => $oa['label'] ?? '', 'cents' => $c );
			$oc     += $c;
		}
		$setup   = $lines ? self::to_cents( self::int( $fees['setup'] ?? 0 ) ) : 0;
		$minimum = self::to_cents( self::int( $fees['min_order'] ?? 0 ) );
		$pre     = $lines ? $sum_cents - $discount + $oc + $setup : 0;
		$adjust  = ( $lines && $pre < $minimum ) ? $minimum - $pre : 0;
		return array(
			'ok' => ! $errors && $lines, 'currency' => $table['currency'] ?? 'USD', 'status' => $table['status'] ?? 'example',
			'label' => $table['label'] ?? '', 'as_of' => $table['as_of'] ?? '', 'lines' => $lines, 'errors' => $errors,
			'totals' => array(
				'feet' => $sum_feet, 'billable_feet' => $sum_bill, 'seconds' => $sum_sec, 'lines_cents' => $sum_cents,
				'discount_pct' => $pct, 'discount_cents' => $discount, 'order_addons' => $oadds, 'order_addons_cents' => $oc,
				'setup_cents' => $setup, 'minimum_cents' => $minimum, 'minimum_adjust_cents' => $adjust, 'total_cents' => $pre + $adjust,
			),
		);
	}

	public static function format_money( $cents, $currency = 'USD' ) {
		$syms = array( 'USD' => '$', 'CAD' => 'CA$', 'EUR' => "\u{20ac}", 'GBP' => "\u{00a3}", 'AUD' => 'A$' );
		$c    = (int) $cents;
		$sym  = $syms[ $currency ] ?? ( $currency . ' ' );
		return ( $c < 0 ? '-' : '' ) . $sym . number_format( intdiv( abs( $c ), 100 ) ) . '.' . sprintf( '%02d', abs( $c ) % 100 );
	}

	/** "USB drive: 3 × USB drive @ $15.00" for a per-custom-unit order add-on, else the label. Mirrors estimate.js orderAddonText(). Plain text: escape on output. */
	public static function order_addon_text( $a, $currency = 'USD' ) {
		if ( empty( $a['qty'] ) ) {
			return (string) ( $a['label'] ?? '' );
		}
		return sprintf( "%s: %d \u{00D7} %s @ %s", $a['label'] ?? '', (int) $a['qty'], $a['unit_label'] ?? '', self::format_mills( (int) ( $a['unit_mills'] ?? 0 ), $currency ) );
	}

	public static function format_mills( $mills, $currency = 'USD' ) {
		$m = (int) $mills;
		if ( 0 === $m % 10 ) {
			return self::format_money( intdiv( $m, 10 ), $currency );
		}
		return self::format_money( intdiv( $m, 10 ), $currency ) . ( $m % 10 );
	}
}
