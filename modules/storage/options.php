<?php
/**
 * Storage calculator defaults (1.3.0): the film format and the deliverables every storage calculator starts with.
 * Option ftt_storage. Shipped defaults: 16mm; ProRes 422 HQ UHD 3840x2160 and H.264 16 Mbps, both at the film
 * frame rate (frame for frame) with PCM 48 kHz 24-bit stereo audio.
 *
 * @package FilmTransferToolkit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FTT_STORAGE_MAX_DEFAULT_DELS = 4;

function ftt_storage_default_options() {
	return array(
		'default_format' => '16',
		'deliverables'   => array(
			array( 'kind' => 'prores', 'profile' => 'hq', 'size' => 'uhd', 'fps' => 'film', 'audio_kbps' => '2304' ),
			array( 'kind' => 'h264', 'mbps' => '16', 'fps' => 'film', 'audio_kbps' => '2304' ),
		),
	);
}

/** Choices used by the settings form and the sanitizer, from core/data/datarate-presets.json. */
function ftt_storage_choices() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$p     = FTT_Datarate::presets();
	$sizes = array();
	foreach ( $p['sizes'] as $z ) {
		$sizes[ $z['id'] ] = $z['label'];
	}
	$prores_sizes = array_intersect_key( $sizes, $p['prores']['mbps'] );
	$profiles     = array();
	foreach ( $p['prores']['profiles'] as $x ) {
		$profiles[ $x['id'] ] = $x['label'];
	}
	$yt = array();
	foreach ( $p['youtube']['res'] as $x ) {
		$yt[ $x['id'] ] = $x['label'];
	}
	$types = array();
	foreach ( $p['uncompressed']['types'] as $x ) {
		$types[ $x['id'] ] = $x['label'];
	}
	$c = array(
		'formats'      => array( '8' => '8mm', 's8' => 'Super 8', '16' => '16mm', '35-4' => '35mm 4-perf', '35-3' => '35mm 3-perf', '35-2' => '35mm 2-perf' ),
		'kinds'        => array( '' => __( '(none)', 'film-transfer-toolkit' ), 'prores' => 'Apple ProRes (.mov)', 'h264' => 'H.264 MP4', 'h265' => 'H.265 / HEVC MP4', 'youtube' => __( 'YouTube upload (H.264, recommended bitrate)', 'film-transfer-toolkit' ), 'dpx' => __( 'DPX image sequence', 'film-transfer-toolkit' ), 'tiff' => __( 'TIFF image sequence', 'film-transfer-toolkit' ), 'uncompressed' => __( 'Uncompressed video (.mov)', 'film-transfer-toolkit' ), 'custom' => __( 'Custom bitrate (Mbps)', 'film-transfer-toolkit' ) ),
		'profiles'     => $profiles,
		'sizes'        => $sizes,
		'prores_sizes' => $prores_sizes,
		'yt_res'       => $yt,
		'depths'       => array( 'dpx' => array_map( 'strval', array_keys( $p['image']['dpx']['depths'] ) ), 'tiff' => array_map( 'strval', array_keys( $p['image']['tiff']['depths'] ) ) ),
		'types'        => $types,
		'fps'          => array( 'film' => __( 'Same as film speed (frame for frame)', 'film-transfer-toolkit' ), '23.976' => '23.976', '24' => '24', '25' => '25', '29.97' => '29.97', '30' => '30', '50' => '50', '59.94' => '59.94', '60' => '60' ),
		'audio'        => array( '0' => __( 'No audio', 'film-transfer-toolkit' ), '384' => __( 'AAC stereo 384 kbps', 'film-transfer-toolkit' ), '2304' => __( 'PCM 48 kHz 24-bit stereo (2304 kbps)', 'film-transfer-toolkit' ) ),
	);
	return $c;
}

/** One deliverable row, or null when the row is empty / unusable. */
function ftt_storage_sanitize_deliverable( $d ) {
	$c = ftt_storage_choices();
	if ( ! is_array( $d ) ) {
		return null;
	}
	$kind = isset( $d['kind'] ) && is_string( $d['kind'] ) ? $d['kind'] : '';
	if ( '' === $kind || ! isset( $c['kinds'][ $kind ] ) ) {
		return null;
	}
	$pick = function ( $k, array $allowed, $fallback ) use ( $d ) {
		$v = isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? (string) $d[ $k ] : '';
		return in_array( $v, array_map( 'strval', $allowed ), true ) ? $v : $fallback; // array_keys() turns '422' into int 422.
	};
	$o = array( 'kind' => $kind, 'fps' => $pick( 'fps', array_map( 'strval', array_keys( $c['fps'] ) ), 'film' ) );
	switch ( $kind ) {
		case 'prores':
			$o['profile'] = $pick( 'profile', array_keys( $c['profiles'] ), 'hq' );
			$o['size']    = $pick( 'size', array_map( 'strval', array_keys( $c['prores_sizes'] ) ), 'hd' );
			break;
		case 'youtube':
			$o['res'] = $pick( 'res', array_keys( $c['yt_res'] ), '1080p' );
			$o['hdr'] = ! empty( $d['hdr'] );
			break;
		case 'dpx':
		case 'tiff':
			$o['size']  = $pick( 'size', array_map( 'strval', array_keys( $c['sizes'] ) ), 'dpx' === $kind ? '2k-fa' : 'hd' );
			$o['depth'] = $pick( 'depth', $c['depths'][ $kind ], 'dpx' === $kind ? '10' : '16' );
			break;
		case 'uncompressed':
			$o['type'] = $pick( 'type', array_keys( $c['types'] ), 'v210' );
			$o['size'] = $pick( 'size', array_map( 'strval', array_keys( $c['sizes'] ) ), 'hd' );
			break;
		default: // h264, h265, custom
			$m         = isset( $d['mbps'] ) && is_numeric( $d['mbps'] ) ? (float) $d['mbps'] : ( 'h265' === $kind ? 10 : 20 );
			$o['mbps'] = (string) max( 0.01, min( 20000, round( $m, 2 ) ) );
	}
	if ( 'dpx' !== $kind && 'tiff' !== $kind ) {
		$o['audio_kbps'] = $pick( 'audio_kbps', array_map( 'strval', array_keys( $c['audio'] ) ), '0' );
	}
	return $o;
}

function ftt_storage_sanitize_options( $in ) {
	$d   = ftt_storage_default_options();
	$c   = ftt_storage_choices();
	$in  = is_array( $in ) ? $in : array();
	$fmt = isset( $in['default_format'] ) && is_scalar( $in['default_format'] ) ? (string) $in['default_format'] : '';
	$out = array( 'default_format' => isset( $c['formats'][ $fmt ] ) ? $fmt : $d['default_format'], 'deliverables' => array() );
	foreach ( array_slice( isset( $in['deliverables'] ) && is_array( $in['deliverables'] ) ? array_values( $in['deliverables'] ) : array(), 0, FTT_STORAGE_MAX_DEFAULT_DELS ) as $row ) {
		$r = ftt_storage_sanitize_deliverable( $row );
		if ( $r ) {
			$out['deliverables'][] = $r;
		}
	}
	if ( ! $out['deliverables'] ) {
		$out['deliverables'] = $d['deliverables']; // At least one deliverable: fall back to the shipped list.
		if ( function_exists( 'add_settings_error' ) && did_action( 'admin_init' ) ) {
			add_settings_error( 'ftt_storage', 'ftt_storage_none', __( 'Choose at least one deliverable. The shipped defaults were restored.', 'film-transfer-toolkit' ) );
		}
	}
	return $out;
}

function ftt_storage_get_options() {
	$saved = get_option( 'ftt_storage', null );
	return is_array( $saved ) ? ftt_storage_sanitize_options( $saved ) : ftt_storage_default_options();
}

/** Plain-language label for a deliverable row (Settings, wizard, Help). */
function ftt_storage_deliverable_label( array $d ) {
	$c = ftt_storage_choices();
	switch ( $d['kind'] ) {
		case 'prores':
			$s = $c['profiles'][ $d['profile'] ] . ', ' . $c['sizes'][ $d['size'] ];
			break;
		case 'youtube':
			$s = 'YouTube ' . $c['yt_res'][ $d['res'] ] . ( ! empty( $d['hdr'] ) ? ' HDR' : '' );
			break;
		case 'dpx':
		case 'tiff':
			$s = strtoupper( $d['kind'] ) . ' ' . $c['sizes'][ $d['size'] ] . ' ' . $d['depth'] . '-bit';
			break;
		case 'uncompressed':
			$s = $c['types'][ $d['type'] ] . ', ' . $c['sizes'][ $d['size'] ];
			break;
		default:
			$s = ( 'custom' === $d['kind'] ? __( 'Custom', 'film-transfer-toolkit' ) : strtoupper( str_replace( 'h', 'H.', $d['kind'] ) ) ) . ' ' . $d['mbps'] . ' Mbps';
	}
	$s .= ', ' . ( 'film' === $d['fps'] ? __( 'film frame rate', 'film-transfer-toolkit' ) : $d['fps'] . ' fps' );
	if ( isset( $d['audio_kbps'] ) && '0' !== $d['audio_kbps'] ) {
		$s .= ', ' . ( '2304' === $d['audio_kbps'] ? __( 'PCM audio', 'film-transfer-toolkit' ) : __( 'AAC audio', 'film-transfer-toolkit' ) );
	}
	return $s;
}

/**
 * The default format + deliverables form rows. $name is the input name prefix (ftt_storage on the settings page,
 * storage in the setup wizard). Every field is plain HTML; admin-ui.js only hides the fields a file type does not use.
 */
function ftt_storage_render_fields( array $o, $name ) {
	$c   = ftt_storage_choices();
	$sel = function ( $field, array $opts, $val, $id, $kinds = '' ) use ( $name ) {
		$h = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . $field ) . '"' . ( $kinds ? ' data-ftt-kinds="' . esc_attr( $kinds ) . '"' : '' ) . '>';
		foreach ( $opts as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	};
	echo '<table class="form-table" role="presentation"><tr><th><label for="ftt-st-format">' . esc_html__( 'Film format shown first', 'film-transfer-toolkit' ) . '</label></th><td>';
	echo $sel( '[default_format]', $c['formats'], $o['default_format'], 'ftt-st-format' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
	echo '</td></tr></table>';
	echo '<p>' . esc_html__( 'Deliverables the calculator starts with (visitors can change, remove or add more). Leave a row on "(none)" to skip it.', 'film-transfer-toolkit' ) . '</p>';
	for ( $i = 0; $i < FTT_STORAGE_MAX_DEFAULT_DELS; $i++ ) {
		$d  = $o['deliverables'][ $i ] ?? array( 'kind' => '' );
		$p  = '[deliverables][' . $i . ']';
		$id = 'ftt-st-' . $i . '-';
		/* translators: %d: row number */
		echo '<fieldset class="ftt-st-row" data-ftt-st-row><legend><strong>' . esc_html( sprintf( __( 'Deliverable %d', 'film-transfer-toolkit' ), $i + 1 ) ) . '</strong></legend><div class="ftt-st-grid">';
		$f = function ( $label, $html, $kinds = '' ) {
			echo '<label class="ftt-st-field"' . ( $kinds ? ' data-ftt-kinds="' . esc_attr( $kinds ) . '"' : '' ) . '><span>' . esc_html( $label ) . '</span> ' . $html . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $html is built from escaped parts.
		};
		$f( __( 'File type', 'film-transfer-toolkit' ), str_replace( '<select ', '<select data-ftt-kind ', $sel( $p . '[kind]', $c['kinds'], $d['kind'], $id . 'kind' ) ) );
		$f( __( 'ProRes flavour', 'film-transfer-toolkit' ), $sel( $p . '[profile]', $c['profiles'], $d['profile'] ?? 'hq', $id . 'profile' ), 'prores' );
		$f( __( 'Frame size', 'film-transfer-toolkit' ), $sel( $p . '[size_prores]', $c['prores_sizes'], $d['size'] ?? 'hd', $id . 'sizep' ), 'prores' );
		$f( __( 'Frame size', 'film-transfer-toolkit' ), $sel( $p . '[size]', $c['sizes'], $d['size'] ?? ( 'dpx' === $d['kind'] ? '2k-fa' : 'hd' ), $id . 'size' ), 'dpx tiff uncompressed' );
		$f( __( 'YouTube resolution', 'film-transfer-toolkit' ), $sel( $p . '[res]', $c['yt_res'], $d['res'] ?? '1080p', $id . 'res' ), 'youtube' );
		$f( __( 'Bit depth', 'film-transfer-toolkit' ), $sel( $p . '[depth]', array( '8' => '8-bit', '10' => '10-bit', '12' => '12-bit', '16' => '16-bit' ), $d['depth'] ?? '10', $id . 'depth' ), 'dpx tiff' );
		$f( __( 'Pixel format', 'film-transfer-toolkit' ), $sel( $p . '[type]', $c['types'], $d['type'] ?? 'v210', $id . 'type' ), 'uncompressed' );
		$f( __( 'Video bitrate (Mbps)', 'film-transfer-toolkit' ), '<input type="number" min="0.01" max="20000" step="any" class="small-text" id="' . esc_attr( $id . 'mbps' ) . '" name="' . esc_attr( $name . $p . '[mbps]' ) . '" value="' . esc_attr( $d['mbps'] ?? '20' ) . '">', 'h264 h265 custom' );
		$f( __( 'Output frame rate', 'film-transfer-toolkit' ), $sel( $p . '[fps]', $c['fps'], $d['fps'] ?? 'film', $id . 'fps' ), 'prores h264 h265 youtube dpx tiff uncompressed custom' );
		$f( __( 'Audio', 'film-transfer-toolkit' ), $sel( $p . '[audio_kbps]', $c['audio'], $d['audio_kbps'] ?? '0', $id . 'audio' ), 'prores h264 h265 youtube uncompressed custom' );
		echo '</div></fieldset>';
	}
	echo '<p class="description">' . esc_html__( 'Shipped defaults: 16mm; ProRes 422 HQ UHD 3840×2160 and H.264 16 Mbps, both at the film frame rate with PCM audio.', 'film-transfer-toolkit' ) . '</p>';
}

/** Map the form's two size selects (ProRes has its own list) back to one size key, then sanitize. */
function ftt_storage_from_form( $in ) {
	$in = is_array( $in ) ? $in : array();
	if ( isset( $in['deliverables'] ) && is_array( $in['deliverables'] ) ) {
		foreach ( $in['deliverables'] as $i => $row ) {
			if ( is_array( $row ) && isset( $row['kind'] ) && 'prores' === $row['kind'] && isset( $row['size_prores'] ) ) {
				$in['deliverables'][ $i ]['size'] = $row['size_prores'];
			}
		}
	}
	return ftt_storage_sanitize_options( $in );
}

add_action( 'admin_init', function () {
	register_setting( 'ftt_storage', 'ftt_storage', array( 'type' => 'array', 'sanitize_callback' => 'ftt_storage_from_form', 'show_in_rest' => false ) );
} );

add_action( 'ftt/admin_menu', function () {
	add_submenu_page( 'ftt', __( 'Storage calculator', 'film-transfer-toolkit' ), __( 'Storage calculator', 'film-transfer-toolkit' ), 'manage_options', 'ftt-storage', 'ftt_storage_render_settings' );
} );

function ftt_storage_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
	}
	$o = ftt_storage_get_options();
	?>
	<div class="wrap ftt-admin">
		<h1><?php esc_html_e( 'Storage calculator', 'film-transfer-toolkit' ); ?></h1>
		<?php settings_errors( 'ftt_storage' ); ?>
		<p><?php esc_html_e( 'What every storage calculator on this site starts with. A page can still start with one particular file format using the preset attribute (block: Settings panel).', 'film-transfer-toolkit' ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'ftt_storage' ); ?>
			<?php ftt_storage_render_fields( $o, 'ftt_storage' ); ?>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Currently', 'film-transfer-toolkit' ); ?></h2>
		<ul class="ul-disc"><?php foreach ( $o['deliverables'] as $d ) : ?><li><?php echo esc_html( ftt_storage_deliverable_label( $d ) ); ?></li><?php endforeach; ?></ul>
	</div>
	<?php
}
