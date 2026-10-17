<?php
/**
 * PHP mirror of FTTCore.datarate (core/ftt-core.js): storage and data-rate maths.
 * Presets come from core/data/datarate-presets.json (the JS core embeds the same file).
 * Keep in step with the JS: tests/core-tests.php and tests/parity.js compare both engines.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FTT_Datarate {
	const KINDS       = array( 'prores', 'h264', 'h265', 'youtube', 'dpx', 'tiff', 'uncompressed', 'custom' );
	const DEFAULT_FPS = array( '8' => 16, 's8' => 18, '16' => 24, '35-4' => 24, '35-3' => 24, '35-2' => 24 );
	const FPF         = array( '8' => 80, 's8' => 72, '16' => 40, '35-4' => 16, '35-3' => 64 / 3, '35-2' => 32 );
	const LABELS      = array( '8' => '8mm (Regular / Double 8)', 's8' => 'Super 8', '16' => '16mm', '35-4' => '35mm 4-perf', '35-3' => '35mm 3-perf', '35-2' => '35mm 2-perf' );
	/* Same nominal reel capacities as FTTCore.reels.REELS (feet). */
	const REELS = array(
		'8'  => array( '3' => 50, '5' => 200, '6' => 300, '7' => 400 ),
		's8' => array( '3' => 50, '5' => 200, '6' => 300, '7' => 400 ),
		'16' => array( '3' => 50, '4' => 100, '5' => 200, '7' => 400, '10' => 800, '12' => 1200, '14' => 1600 ),
		'35' => array( '6' => 200, '10' => 1000, '15' => 2000 ),
	);

	private static $dr = null;

	public static function presets() {
		if ( null === self::$dr ) {
			$raw      = file_get_contents( dirname( __DIR__, 2 ) . '/core/data/datarate-presets.json' ); // Local file, not a URL.
			self::$dr = json_decode( (string) $raw, true );
		}
		return self::$dr;
	}

	private static function num( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) {
			return is_finite( (float) $v ) ? $v : NAN;
		}
		if ( is_string( $v ) && preg_match( '/^-?\d+(\.\d+)?$/', $v ) ) {
			return 0 + $v;
		}
		return NAN;
	}
	private static function blank( $v ) { return null === $v || '' === $v; }
	private static function str( $v ) {
		if ( is_string( $v ) ) { return $v; }
		if ( is_int( $v ) || is_float( $v ) ) { return self::nstr( $v ); }
		return '';
	}
	/* Number to string the way JavaScript's String(n) does for the ranges used here. */
	private static function nstr( $n ) {
		if ( is_int( $n ) ) { return (string) $n; }
		if ( floor( $n ) == $n && abs( $n ) < 1e15 ) { return (string) (int) $n; }
		return (string) json_encode( $n ); // serialize_precision -1: shortest round-trip, like JS.
	}
	private static function r0( $x ) { return floor( $x + 0.5 ); }
	private static function err( $line, $d, $code, $msg ) { return array( 'line' => $line, 'deliverable' => $d, 'code' => $code, 'message' => $msg ); }
	private static function find( $list, $id ) {
		foreach ( $list as $x ) { if ( $x['id'] === $id ) { return $x; } }
		return null;
	}
	/* JSON array vs object, without array_is_list() (PHP 8.1+): plugin supports PHP 7.4. */
	private static function is_list( $a ) { return array() === $a || array_keys( $a ) === range( 0, count( $a ) - 1 ); }
	private static function obj( $v ) { return is_array( $v ) && ( ! $v || ! self::is_list( $v ) ) ? $v : array(); }
	private static function lst( $v ) { return is_array( $v ) && self::is_list( $v ) ? $v : array(); }

	private static function size( $spec, $L ) {
		$dr = self::presets();
		if ( 'custom' === ( $spec['size'] ?? null ) ) {
			$w = self::num( $spec['width'] ?? null );
			$h = self::num( $spec['height'] ?? null );
			if ( ! ( $w >= $L['px_min'] && $w <= $L['px_max'] && floor( $w ) == $w && $h >= $L['px_min'] && $h <= $L['px_max'] && floor( $h ) == $h ) ) {
				return null;
			}
			return array( 'id' => 'custom', 'label' => (int) $w . '×' . (int) $h, 'w' => (int) $w, 'h' => (int) $h );
		}
		return self::find( $dr['sizes'], self::str( $spec['size'] ?? null ) );
	}

	public static function prores_mbps( $size_id, $profile, $fps ) {
		$dr = self::presets();
		if ( empty( $dr['prores']['mbps'][ $size_id ] ) ) { return null; }
		$tab  = $dr['prores']['mbps'][ $size_id ];
		$keys = array_map( 'intval', array_keys( $tab ) );
		sort( $keys );
		$best = $keys[0];
		foreach ( $keys as $k ) {
			if ( abs( $fps - $k ) / $k <= $dr['prores']['fps_tolerance'] ) {
				return array( 'mbps' => $tab[ $k ][ $profile ], 'listed' => $k, 'scaled' => false );
			}
			if ( abs( $fps - $k ) < abs( $fps - $best ) ) { $best = $k; }
		}
		return array( 'mbps' => $tab[ $best ][ $profile ] * $fps / $best, 'listed' => $best, 'scaled' => true );
	}

	private static function deliverable( $spec, $L ) {
		$dr   = self::presets();
		$spec = self::obj( $spec );
		$kind = self::str( $spec['kind'] ?? null );
		$d    = array( 'kind' => $kind, 'source' => '', 'label' => '' );
		if ( ! in_array( $kind, self::KINDS, true ) ) { return array( 'error' => array( 'bad_kind', 'Unknown deliverable type.' ) ); }
		$fps = $spec['fps'] ?? null;
		if ( self::blank( $fps ) || 'film' === $fps ) {
			$d['fps'] = 'film';
		} else {
			$f = self::num( $fps );
			if ( ! ( $f >= $L['fps_min'] && $f <= $L['fps_max'] ) ) { return array( 'error' => array( 'bad_out_fps', 'Output frame rate must be from 1 to 120 fps.' ) ); }
			$d['fps'] = $f;
		}
		$ak = self::blank( $spec['audio_kbps'] ?? null ) ? 0 : self::num( $spec['audio_kbps'] );
		if ( ! ( $ak >= 0 && $ak <= $L['audio_kbps_max'] ) ) { return array( 'error' => array( 'bad_audio', 'Audio bitrate must be from 0 to 20000 kbps.' ) ); }
		$d['audio_kbps'] = $ak;
		if ( 'prores' === $kind ) {
			$p = self::find( $dr['prores']['profiles'], self::str( $spec['profile'] ?? null ) );
			if ( ! $p ) { return array( 'error' => array( 'bad_profile', 'Unknown ProRes profile.' ) ); }
			$ps = self::find( $dr['sizes'], self::str( $spec['size'] ?? null ) );
			if ( ! $ps ) { return array( 'error' => array( 'bad_frame_size', 'Unknown frame size.' ) ); }
			if ( empty( $dr['prores']['mbps'][ $ps['id'] ] ) ) { return array( 'error' => array( 'prores_size', 'Apple publishes no ProRes target rate for that frame size.' ) ); }
			$d['profile'] = $p['id']; $d['size'] = $ps['id']; $d['size_label'] = $ps['label']; $d['label'] = $p['label'] . ' ' . $ps['label']; $d['source'] = $dr['prores']['source'];
		} elseif ( 'h264' === $kind || 'h265' === $kind || 'custom' === $kind ) {
			$m = self::num( $spec['mbps'] ?? null );
			if ( ! ( $m >= $L['mbps_min'] && $m <= $L['mbps_max'] ) ) { return array( 'error' => array( 'bad_mbps', 'Bitrate must be from 0.01 to 20000 Mbps.' ) ); }
			$d['mbps']   = $m;
			$d['label']  = ( 'h264' === $kind ? 'H.264 MP4 ' : ( 'h265' === $kind ? 'H.265 MP4 ' : 'Custom ' ) ) . self::nstr( $m ) . ' Mbps';
			$d['source'] = 'custom' === $kind ? '' : $kind;
		} elseif ( 'youtube' === $kind ) {
			$y = self::find( $dr['youtube']['res'], self::str( $spec['res'] ?? null ) );
			if ( ! $y ) { return array( 'error' => array( 'bad_res', 'Unknown YouTube resolution.' ) ); }
			$h = $spec['hdr'] ?? null;
			$d['res'] = $y['id']; $d['hdr'] = true === $h || 1 === $h || '1' === $h; $d['label'] = 'YouTube ' . $y['label'] . ( $d['hdr'] ? ' HDR' : ' SDR' ); $d['source'] = $dr['youtube']['source'];
		} elseif ( 'dpx' === $kind || 'tiff' === $kind ) {
			$im  = $dr['image'][ $kind ];
			$sz  = self::size( $spec, $L );
			$dep = self::str( $spec['depth'] ?? null );
			if ( ! $sz ) { return array( 'error' => array( 'bad_frame_size', 'Unknown frame size.' ) ); }
			if ( ! preg_match( '/^(0|[1-9]\d*)$/', $dep ) || ! isset( $im['depths'][ $dep ] ) ) { return array( 'error' => array( 'bad_depth', 'That bit depth is not offered for this format.' ) ); }
			$row = (int) ceil( $sz['w'] * $im['depths'][ $dep ] / $im['row_align'] ) * $im['row_align'];
			$d['size'] = $sz['id']; $d['size_label'] = $sz['label']; $d['depth'] = (int) $dep; $d['frame_bytes'] = $row * $sz['h'] + $im['header_bytes'];
			$d['label'] = ( 'dpx' === $kind ? 'DPX ' : 'TIFF ' ) . $dep . '-bit ' . $sz['label']; $d['source'] = $im['source'];
		} else {
			$ut = self::find( $dr['uncompressed']['types'], self::str( $spec['type'] ?? null ) );
			$us = self::size( $spec, $L );
			if ( ! $ut ) { return array( 'error' => array( 'bad_type', 'Unknown uncompressed type.' ) ); }
			if ( ! $us ) { return array( 'error' => array( 'bad_frame_size', 'Unknown frame size.' ) ); }
			$d['type'] = $ut['id']; $d['size'] = $us['id']; $d['size_label'] = $us['label']; $d['frame_bytes'] = (int) ceil( $us['w'] / $ut['group_px'] ) * $ut['group_bytes'] * $us['h'];
			$d['label'] = $ut['label'] . ' ' . $us['label']; $d['source'] = $dr['uncompressed']['source'];
		}
		return array( 'd' => $d );
	}

	private static function item( $d, $frames, $film_fps ) {
		$dr     = self::presets();
		$out    = 'film' === $d['fps'] ? $film_fps : $d['fps'];
		$n      = abs( $out - $film_fps ) < 1e-9 ? $frames : self::r0( $frames / $film_fps * $out );
		$secs   = $n / $out;
		$scaled = false;
		$range  = null;
		if ( ! empty( $d['frame_bytes'] ) ) {
			$bytes = $n * $d['frame_bytes'];
			$mbps  = $d['frame_bytes'] * 8 * $out / 1e6;
		} else {
			if ( 'prores' === $d['kind'] ) {
				$pr = self::prores_mbps( $d['size'], $d['profile'], $out ); $mbps = $pr['mbps']; $scaled = $pr['scaled'];
			} elseif ( 'youtube' === $d['kind'] ) {
				$t = $dr['youtube'][ $d['hdr'] ? 'hdr' : 'sdr' ][ $d['res'] ][ $out >= $dr['youtube']['high_fps_from'] ? 'high' : 'std' ]; $range = array( $t[0], $t[1] ); $mbps = $t[1];
			} else {
				$mbps = $d['mbps'];
			}
			$bytes = self::r0( $mbps * 125000 * $secs );
		}
		if ( $d['audio_kbps'] > 0 ) { $bytes += self::r0( $d['audio_kbps'] * 125 * $secs ); }
		return array( 'out_fps' => $out, 'frames' => $n, 'mbps' => $mbps, 'scaled' => $scaled, 'range' => $range, 'bytes_per_reel' => $bytes );
	}

	public static function units( $bytes ) {
		return array( 'bytes' => $bytes, 'gb' => $bytes / 1e9, 'tb' => $bytes / 1e12, 'gib' => $bytes / 1073741824, 'tib' => $bytes / 1099511627776 );
	}

	public static function suggest_drive( $bytes, $headroom ) {
		if ( ! ( $bytes > 0 ) ) { return null; }
		$list = self::presets()['drives_gb'];
		$need = ceil( $bytes * ( 100 + $headroom ) / 100 );
		foreach ( $list as $gb ) { if ( $gb * 1e9 >= $need ) { return array( 'gb' => $gb, 'count' => 1, 'need_bytes' => $need ); } }
		$big = end( $list );
		return array( 'gb' => $big, 'count' => (int) ceil( $need / ( $big * 1e9 ) ), 'need_bytes' => $need );
	}

	public static function storage( $request ) {
		$dr      = self::presets();
		$L       = $dr['limits'];
		$request = self::obj( $request );
		$errors  = array(); $dels = array(); $lines = array();
		$rd = self::lst( $request['deliverables'] ?? null );
		if ( count( $rd ) > $L['deliverables'] ) { $errors[] = self::err( -1, -1, 'too_many_deliverables', 'Too many deliverables.' ); $rd = array_slice( $rd, 0, $L['deliverables'] ); }
		foreach ( $rd as $j => $spec ) {
			$r = self::deliverable( $spec, $L );
			if ( isset( $r['error'] ) ) { $errors[] = self::err( -1, $j, $r['error'][0], $r['error'][1] ); } else { $r['d']['index'] = $j; $r['d']['bytes'] = 0; $dels[] = $r['d']; }
		}
		if ( ! $rd ) { $errors[] = self::err( -1, -1, 'no_deliverables', 'Add at least one deliverable.' ); }
		$head = self::blank( $request['headroom_pct'] ?? null ) ? 20 : self::num( $request['headroom_pct'] );
		if ( ! ( $head >= 0 && $head <= $L['headroom_max'] ) ) { $errors[] = self::err( -1, -1, 'bad_headroom', 'Headroom must be from 0 to 100%.' ); $head = 20; }
		$rl = self::lst( $request['lines'] ?? null );
		if ( count( $rl ) > $L['lines'] ) { $errors[] = self::err( -1, -1, 'too_many_lines', 'Too many lines.' ); $rl = array_slice( $rl, 0, $L['lines'] ); }
		$sum_sec = 0; $sum_frames = 0; $total = 0;
		foreach ( $rl as $i => $q ) {
			$q   = self::obj( $q );
			$fid = self::str( $q['format'] ?? null );
			$fid = '35' === $fid ? '35-4' : $fid;
			if ( ! isset( self::FPF[ $fid ] ) || ! is_string( $fid ) ) { $errors[] = self::err( $i, -1, 'bad_format', 'Unknown film format.' ); continue; }
			$fpf = self::FPF[ $fid ];
			$fps = self::blank( $q['fps'] ?? null ) ? self::DEFAULT_FPS[ $fid ] : self::num( $q['fps'] );
			if ( ! ( $fps >= $L['fps_min'] && $fps <= $L['fps_max'] ) ) { $errors[] = self::err( $i, -1, 'bad_fps', 'Film speed must be from 1 to 120 fps.' ); continue; }
			$reels = self::blank( $q['reels'] ?? null ) ? 1 : self::num( $q['reels'] );
			if ( ! ( $reels >= 1 && $reels <= $L['reels'] && floor( $reels ) == $reels ) ) { $errors[] = self::err( $i, -1, 'bad_reels', 'Number of reels must be a whole number from 1 to 999.' ); continue; }
			if ( ! self::blank( $q['seconds'] ?? null ) ) {
				$s = self::num( $q['seconds'] );
				if ( ! ( $s > 0 && $s <= $L['seconds'] ) ) { $errors[] = self::err( $i, -1, 'bad_seconds', 'Running time must be more than 0 and at most 100 hours.' ); continue; }
				$frames = self::r0( $s / $reels * $fps ); $method = 'time';
			} elseif ( ! self::blank( $q['feet'] ?? null ) ) {
				$ft = self::num( $q['feet'] );
				if ( ! ( $ft > 0 && $ft <= $L['feet'] ) ) { $errors[] = self::err( $i, -1, 'bad_feet', 'Footage must be more than 0 and at most 100000 feet.' ); continue; }
				$frames = self::r0( $ft / $reels * $fpf ); $method = 'feet';
			} elseif ( ! self::blank( $q['size'] ?? null ) ) {
				$g  = 0 === strpos( $fid, '35' ) ? '35' : $fid;
				$sz = self::str( $q['size'] );
				if ( ! preg_match( '/^\d+$/', $sz ) || ! isset( self::REELS[ $g ][ $sz ] ) ) { $errors[] = self::err( $i, -1, 'bad_size', 'That reel size is not listed for this format.' ); continue; }
				$frames = self::r0( self::REELS[ $g ][ $sz ] * $fpf ); $method = 'size';
			} else { $errors[] = self::err( $i, -1, 'need_length', 'Enter a reel size, footage or running time.' ); continue; }
			if ( $frames < 1 ) { $errors[] = self::err( $i, -1, 'too_short', 'Less than one frame per reel.' ); continue; }
			$items = array(); $line_bytes = 0;
			foreach ( $dels as $x => $d ) {
				$it                = self::item( $d, $frames, $fps );
				$it['deliverable'] = $d['index'];
				$it['bytes']       = $it['bytes_per_reel'] * $reels;
				$items[]           = $it;
				$line_bytes       += $it['bytes'];
				$dels[ $x ]['bytes'] += $it['bytes'];
			}
			$secs    = $frames / $fps;
			$lines[] = array( 'index' => $i, 'format' => $fid, 'format_label' => self::LABELS[ $fid ], 'fps' => $fps, 'reels' => $reels, 'method' => $method,
				'frames_per_reel' => $frames, 'feet_per_reel' => $frames / $fpf, 'seconds_per_reel' => $secs, 'items' => $items, 'bytes' => $line_bytes );
			$sum_sec += $secs * $reels; $sum_frames += $frames * $reels; $total += $line_bytes;
		}
		if ( ! $rl ) { $errors[] = self::err( -1, -1, 'no_lines', 'Add at least one reel.' ); }
		return array( 'ok' => ! $errors && $lines && $dels, 'as_of' => $dr['as_of'], 'lines' => $lines, 'deliverables' => $dels, 'errors' => $errors,
			'totals' => array( 'seconds' => $sum_sec, 'frames' => $sum_frames, 'size' => self::units( $total ), 'headroom_pct' => $head, 'drive' => self::suggest_drive( $total, $head ) ) );
	}

	public static function fmt_bytes( $bytes, $binary = false ) {
		$u = $binary ? array( 'B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB' ) : array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
		$b = $binary ? 1024 : 1000; $i = 0; $v = (float) $bytes;
		while ( $v >= $b && $i < count( $u ) - 1 ) { $v /= $b; $i++; }
		$d = 0 === $i ? 0 : ( $v < 10 ? 2 : ( $v < 100 ? 1 : 0 ) );
		return number_format( $v, $d, '.', '' ) . ' ' . $u[ $i ];
	}

	public static function fmt_drive( $dr ) {
		if ( ! $dr ) { return ''; }
		$s = $dr['gb'] >= 1000 ? self::nstr( $dr['gb'] / 1000 ) . ' TB' : $dr['gb'] . ' GB';
		return ( $dr['count'] > 1 ? $dr['count'] . ' × ' : '' ) . $s;
	}
}
