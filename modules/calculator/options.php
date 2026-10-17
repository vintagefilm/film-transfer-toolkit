<?php
/**
 * Data, defaults and sanitization shared by the shortcode and the admin screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Film formats. Keep ids and frames-per-foot in sync with FORMATS in core/ftt-core.js.
 */
function ftt_calc_formats() {
	return array(
		'8'    => array( 'label' => __( '8mm (Regular / Double 8)', 'film-transfer-toolkit' ), 'fpf' => 80, 'fpf_label' => '80', 'std' => '16, 18, 24' ),
		's8'   => array( 'label' => __( 'Super 8', 'film-transfer-toolkit' ), 'fpf' => 72, 'fpf_label' => '72', 'std' => '18, 24, 25' ),
		'16'   => array( 'label' => __( '16mm', 'film-transfer-toolkit' ), 'fpf' => 40, 'fpf_label' => '40', 'std' => '16, 18, 24, 25' ),
		'35-4' => array( 'label' => __( '35mm 4-perf', 'film-transfer-toolkit' ), 'fpf' => 16, 'fpf_label' => '16', 'std' => '24, 25' ),
		'35-3' => array( 'label' => __( '35mm 3-perf', 'film-transfer-toolkit' ), 'fpf' => 64 / 3, 'fpf_label' => '21⅓', 'std' => '24, 25' ),
		'35-2' => array( 'label' => __( '35mm 2-perf', 'film-transfer-toolkit' ), 'fpf' => 32, 'fpf_label' => '32', 'std' => '24, 25' ),
	);
}

function ftt_calc_speeds() {
	return array( '16', '18', '24', '25', '29.97', '30' );
}

function ftt_calc_default_options() {
	return array(
		'default_format' => '16',
		'default_fps'    => '24',
		'theme'          => 'dark',
		'accent'         => '',
		'heading_font'   => 'preset',
		'show_extras'    => 1, // Legacy (1.2.0 and older): "film speed and total seconds". Kept equal to show_speed || show_seconds.
		'show_switch'    => 1,
		'show_table'     => 0,
		'show_meters'    => 1, // 1.3.0 field toggles. All on = the same calculator as before.
		'show_frames'    => 1,
		'show_fps'       => 1, // The frame rate picker. When off, the default frame rate is used.
		'show_speed'     => 1, // Film speed readout (ft/min).
		'show_seconds'   => 1, // Total seconds readout.
		'fields_v'       => 1,
	);
}

/** Field toggles: option key => shortcode / block attribute. */
function ftt_calc_field_toggles() {
	return array(
		'show_meters'  => 'meters',
		'show_frames'  => 'frames',
		'show_fps'     => 'fps_picker',
		'show_speed'   => 'speed',
		'show_seconds' => 'seconds',
	);
}

/**
 * Options saved before 1.3.0 have no field toggles: everything stays visible, and the old
 * "film speed and total seconds" checkbox decides both readouts.
 */
function ftt_calc_migrate_options( $saved ) {
	if ( ! is_array( $saved ) || isset( $saved['fields_v'] ) ) {
		return $saved;
	}
	$extras = ! isset( $saved['show_extras'] ) || ! empty( $saved['show_extras'] ) ? 1 : 0;
	$saved['show_meters']  = 1;
	$saved['show_frames']  = 1;
	$saved['show_fps']     = 1;
	$saved['show_speed']   = $extras;
	$saved['show_seconds'] = $extras;
	$saved['fields_v']     = 1;
	return $saved;
}

/** Returns a lowercase #rgb / #rrggbb colour, or '' when invalid. */
function ftt_calc_sanitize_hex( $value ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	return preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value ) ? strtolower( $value ) : '';
}

/** Returns a normalised fps string (0 < fps <= 1000), or $fallback. */
function ftt_calc_sanitize_fps( $value, $fallback = '24' ) {
	if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
		return $fallback;
	}
	$fps = (float) $value;
	if ( $fps <= 0 || $fps > 1000 ) {
		return $fallback;
	}
	return rtrim( rtrim( number_format( $fps, 3, '.', '' ), '0' ), '.' );
}

function ftt_calc_sanitize_format( $value, $fallback = '16' ) {
	$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	$formats = ftt_calc_formats();
	return isset( $formats[ $value ] ) ? $value : $fallback;
}

function ftt_calc_sanitize_choice( $value, array $allowed, $fallback ) {
	$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	return in_array( $value, $allowed, true ) ? $value : $fallback;
}

/** Settings API sanitize callback: whitelist every key, drop anything else. */
function ftt_calc_sanitize_options( $input ) {
	$d     = ftt_calc_default_options();
	$input = is_array( $input ) ? $input : array();
	$out   = array(
		'default_format' => ftt_calc_sanitize_format( isset( $input['default_format'] ) ? $input['default_format'] : '', $d['default_format'] ),
		'default_fps'    => ftt_calc_sanitize_fps( isset( $input['default_fps'] ) ? $input['default_fps'] : '', $d['default_fps'] ),
		'theme'          => ftt_calc_sanitize_choice( isset( $input['theme'] ) ? $input['theme'] : '', array( 'dark', 'light' ), $d['theme'] ),
		'accent'         => ftt_calc_sanitize_hex( isset( $input['accent'] ) ? $input['accent'] : '' ),
		'heading_font'   => ftt_calc_sanitize_choice( isset( $input['heading_font'] ) ? $input['heading_font'] : '', array( 'preset', 'theme', 'plain', 'yeseva' ), $d['heading_font'] ),
	);
	foreach ( array( 'show_switch', 'show_table', 'show_meters', 'show_frames', 'show_fps', 'show_speed', 'show_seconds' ) as $key ) {
		$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}
	$out['show_extras'] = ( $out['show_speed'] || $out['show_seconds'] ) ? 1 : 0;
	$out['fields_v']    = 1;
	return $out;
}

function ftt_calc_get_options() {
	$saved = get_option( 'ftt_calc_options', null );
	if ( ! is_array( $saved ) ) {
		// First run: inherit the standalone Film Calculator plugin's settings if they exist.
		$saved = get_option( 'filmcalc_options', null );
	}
	if ( ! is_array( $saved ) ) {
		return ftt_calc_default_options();
	}
	return ftt_calc_sanitize_options( ftt_calc_migrate_options( $saved ) );
}
