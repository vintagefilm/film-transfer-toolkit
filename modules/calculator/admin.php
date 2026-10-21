<?php
/**
 * wp-admin: FTT > Calculator (settings + help).
 * Settings use the Settings API (options.php handles the nonce and capability check).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'ftt_calc_register_settings' );
function ftt_calc_register_settings() {
	register_setting(
		'ftt_calc',
		'ftt_calc_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ftt_calc_sanitize_options',
			'default'           => ftt_calc_default_options(),
			'show_in_rest'      => false,
		)
	);

	add_settings_section( 'ftt_calc_defaults', __( 'Defaults', 'film-transfer-toolkit' ), '__return_false', 'ftt-calculator' );
	add_settings_field( 'default_format', __( 'Default format', 'film-transfer-toolkit' ), 'ftt_calc_field_format', 'ftt-calculator', 'ftt_calc_defaults', array( 'label_for' => 'ftt_calc_default_format' ) );
	add_settings_field( 'default_fps', __( 'Default frame rate (fps)', 'film-transfer-toolkit' ), 'ftt_calc_field_fps', 'ftt-calculator', 'ftt_calc_defaults', array( 'label_for' => 'ftt_calc_default_fps' ) );

	add_settings_section( 'ftt_calc_look', __( 'Appearance', 'film-transfer-toolkit' ), '__return_false', 'ftt-calculator' );
	add_settings_field( 'theme', __( 'Colour scheme', 'film-transfer-toolkit' ), 'ftt_calc_field_theme', 'ftt-calculator', 'ftt_calc_look' );

	add_settings_section( 'ftt_calc_fields', __( 'Fields', 'film-transfer-toolkit' ), 'ftt_calc_fields_intro', 'ftt-calculator' );
	foreach ( ftt_calc_field_toggle_labels() as $key => $l ) {
		add_settings_field( $key, $l[0], 'ftt_calc_field_checkbox', 'ftt-calculator', 'ftt_calc_fields', array( 'key' => $key, 'text' => $l[1] ) );
	}

	add_settings_section( 'ftt_calc_more', __( 'More appearance options', 'film-transfer-toolkit' ), '__return_false', 'ftt-calculator' );
	add_settings_field( 'accent', __( 'Accent colour', 'film-transfer-toolkit' ), 'ftt_calc_field_accent', 'ftt-calculator', 'ftt_calc_more', array( 'label_for' => 'ftt_calc_accent' ) );
	add_settings_field( 'heading_font', __( 'Heading font', 'film-transfer-toolkit' ), 'ftt_calc_field_heading_font', 'ftt-calculator', 'ftt_calc_more', array( 'label_for' => 'ftt_calc_heading_font' ) );
	add_settings_section( 'ftt_calc_extras', __( 'Extras', 'film-transfer-toolkit' ), '__return_false', 'ftt-calculator' );
	add_settings_field( 'show_switch', __( 'Switch button', 'film-transfer-toolkit' ), 'ftt_calc_field_checkbox', 'ftt-calculator', 'ftt_calc_extras', array( 'key' => 'show_switch', 'text' => __( 'Show the Length → Time / Time → Length switch', 'film-transfer-toolkit' ) ) );
	add_settings_field( 'show_table', __( 'Reference table', 'film-transfer-toolkit' ), 'ftt_calc_field_checkbox', 'ftt-calculator', 'ftt_calc_extras', array( 'key' => 'show_table', 'text' => __( 'Show the format reference table', 'film-transfer-toolkit' ) ) );
}

/** Field toggles shown on the Settings page and in the setup wizard: key => [title, checkbox text]. */
function ftt_calc_field_toggle_labels() {
	return array(
		'show_meters'  => array( __( 'Length in meters', 'film-transfer-toolkit' ), __( 'Show the Length (meters) field', 'film-transfer-toolkit' ) ),
		'show_frames'  => array( __( 'Frame count', 'film-transfer-toolkit' ), __( 'Show the Frames field', 'film-transfer-toolkit' ) ),
		'show_fps'     => array( __( 'Frame rate picker', 'film-transfer-toolkit' ), __( 'Show the Frame rate (fps) picker (when off, visitors get the default frame rate above)', 'film-transfer-toolkit' ) ),
		'show_speed'   => array( __( 'Film speed', 'film-transfer-toolkit' ), __( 'Show the film speed readout (feet per minute)', 'film-transfer-toolkit' ) ),
		'show_seconds' => array( __( 'Total seconds', 'film-transfer-toolkit' ), __( 'Show the total seconds readout', 'film-transfer-toolkit' ) ),
	);
}

function ftt_calc_fields_intro() {
	echo '<p>' . esc_html__( 'Choose which fields every calculator on this site shows. Feet and running time are always shown. A page can override each one with block settings or a shortcode attribute: meters="no", frames="no", fps_picker="no", speed="no", seconds="no" (or "yes").', 'film-transfer-toolkit' ) . '</p>';
}

function ftt_calc_field_format() {
	$o = ftt_calc_get_options();
	echo '<select id="ftt_calc_default_format" name="ftt_calc_options[default_format]">';
	foreach ( ftt_calc_formats() as $key => $f ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $o['default_format'], $key, false ), esc_html( $f['label'] . ' – ' . $f['fpf_label'] . ' fr/ft' ) );
	}
	echo '</select>';
}

function ftt_calc_field_fps() {
	$o = ftt_calc_get_options();
	printf( '<input type="text" inputmode="decimal" id="ftt_calc_default_fps" name="ftt_calc_options[default_fps]" value="%s" class="small-text" list="ftt_calc_fps_list">', esc_attr( $o['default_fps'] ) );
	echo '<datalist id="ftt_calc_fps_list">';
	foreach ( ftt_calc_speeds() as $s ) {
		printf( '<option value="%s"></option>', esc_attr( $s ) );
	}
	echo '</datalist>';
	echo '<p class="description">' . esc_html__( '16, 18, 24, 25, 29.97 or 30, or any custom value up to 1000.', 'film-transfer-toolkit' ) . '</p>';
}

function ftt_calc_field_theme() {
	$o       = ftt_calc_get_options();
	$choices = array(
		'site'  => __( 'Site theme (same colours and fonts as every FTT tool; recommended)', 'film-transfer-toolkit' ),
		'dark'  => __( 'Dark (amber accent)', 'film-transfer-toolkit' ),
		'light' => __( 'Light (FilmTransfer.ai palette, Yeseva One headings)', 'film-transfer-toolkit' ),
	);
	echo '<fieldset>';
	foreach ( $choices as $value => $label ) {
		printf( '<label><input type="radio" name="ftt_calc_options[theme]" value="%s" %s> %s</label><br>', esc_attr( $value ), checked( $o['theme'], $value, false ), esc_html( $label ) );
	}
	echo '</fieldset>';
}

function ftt_calc_field_accent() {
	$o = ftt_calc_get_options();
	printf( '<input type="text" id="ftt_calc_accent" name="ftt_calc_options[accent]" value="%s" class="regular-text" placeholder="#ffb700" pattern="#([0-9a-fA-F]{3}){1,2}" maxlength="7">', esc_attr( $o['accent'] ) );
	echo '<p class="description">' . esc_html__( 'Optional hex color such as #ffb700. Leave blank to use the theme preset.', 'film-transfer-toolkit' ) . '</p>';
}

function ftt_calc_field_heading_font() {
	$o       = ftt_calc_get_options();
	$choices = array(
		'preset' => __( 'Preset (dark: site theme font, light: Yeseva One)', 'film-transfer-toolkit' ),
		'theme'  => __( 'Site theme heading font', 'film-transfer-toolkit' ),
		'plain'  => __( 'Plain system font', 'film-transfer-toolkit' ),
		'yeseva' => __( 'Yeseva One (bundled, no external requests)', 'film-transfer-toolkit' ),
	);
	echo '<select id="ftt_calc_heading_font" name="ftt_calc_options[heading_font]">';
	foreach ( $choices as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $o['heading_font'], $value, false ), esc_html( $label ) );
	}
	echo '</select>';
}

function ftt_calc_field_checkbox( $args ) {
	$o   = ftt_calc_get_options();
	$key = $args['key'];
	printf(
		'<label><input type="checkbox" name="ftt_calc_options[%1$s]" value="1" %2$s> %3$s</label>',
		esc_attr( $key ),
		checked( ! empty( $o[ $key ] ), true, false ),
		esc_html( $args['text'] )
	);
}

function ftt_calc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Calculator settings', 'film-transfer-toolkit' ); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e( 'These are the defaults for every [ftt_calculator] (and [film_calculator]) shortcode. Shortcode attributes override them per page.', 'film-transfer-toolkit' ); ?></p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'ftt_calc' );
			do_settings_sections( 'ftt-calculator' );
			submit_button( __( 'Save changes', 'film-transfer-toolkit' ) );
			?>
		</form>
		<hr>
		<?php ftt_calc_render_help_page( false ); ?>
	</div>
	<?php
}

function ftt_calc_render_help_page( $wrap = true ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'film-transfer-toolkit' ) );
	}
	$attrs = array(
		array( 'format', '8, s8, 16, 35-4, 35-3, 35-2', __( 'Starting film format.', 'film-transfer-toolkit' ) ),
		array( 'fps', '16, 18, 24, 25, 29.97, 30 or any number up to 1000', __( 'Starting frame rate. Non-standard values preselect Custom.', 'film-transfer-toolkit' ) ),
		array( 'theme', 'site, dark, light', __( 'Color preset. Light uses the FilmTransfer.ai palette.', 'film-transfer-toolkit' ) ),
		array( 'accent', '#rgb or #rrggbb', __( 'Accent color. Invalid values are ignored.', 'film-transfer-toolkit' ) ),
		array( 'heading_font', 'preset, theme, plain, yeseva', __( 'Font for the calculator title.', 'film-transfer-toolkit' ) ),
		array( 'extras', 'yes, no', __( 'Show film speed (ft/min) and total seconds.', 'film-transfer-toolkit' ) ),
		array( 'switch', 'yes, no', __( 'Show the Switch button (Length → Time / Time → Length).', 'film-transfer-toolkit' ) ),
		array( 'table', 'yes, no', __( 'Show the format reference table.', 'film-transfer-toolkit' ) ),
		array( 'title', __( 'any text', 'film-transfer-toolkit' ), __( 'Replace the "Film Calculator" heading.', 'film-transfer-toolkit' ) ),
	);
	$examples = array(
		'[ftt_calculator]'                                      => __( 'Uses the defaults from Settings.', 'film-transfer-toolkit' ),
		'[ftt_calculator format="s8" fps="18"]'                 => __( 'Super 8 at 18 fps.', 'film-transfer-toolkit' ),
		'[ftt_calculator theme="light" table="yes"]'            => __( 'Light FilmTransfer.ai look with the reference table.', 'film-transfer-toolkit' ),
		'[ftt_calculator accent="#d3d379" extras="no"]'         => __( 'Custom accent, no ft/min or seconds.', 'film-transfer-toolkit' ),
		'[ftt_calculator format="16" fps="12" heading_font="plain"]' => __( '16mm at a custom 12 fps with a plain title font.', 'film-transfer-toolkit' ),
	);
	?>
	<?php if ( $wrap ) : ?><div class="wrap"><?php endif; ?>
		<h2><?php esc_html_e( 'Calculator help', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Add the shortcode to any page or post (in the block editor use a Shortcode block). Pick a format and frame rate, then type into feet, meters, running time or frames; the other fields update as you type.', 'film-transfer-toolkit' ); ?></p>
		<p><?php esc_html_e( 'Running time accepts seconds (200), m:ss (3:20) or h:mm:ss (1:06:40); decimals are allowed.', 'film-transfer-toolkit' ); ?></p>
		<h2><?php esc_html_e( 'Shortcode attributes', 'film-transfer-toolkit' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th><?php esc_html_e( 'Attribute', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'Values', 'film-transfer-toolkit' ); ?></th><th><?php esc_html_e( 'What it does', 'film-transfer-toolkit' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $attrs as $a ) : ?>
				<tr><td><code><?php echo esc_html( $a[0] ); ?></code></td><td><?php echo esc_html( $a[1] ); ?></td><td><?php echo esc_html( $a[2] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Examples', 'film-transfer-toolkit' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
			<?php foreach ( $examples as $code => $desc ) : ?>
				<tr><td><code><?php echo esc_html( $code ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Styling with CSS custom properties', 'film-transfer-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Override these on .filmcalc in your theme or Customizer CSS:', 'film-transfer-toolkit' ); ?> <code>--fc-bg</code>, <code>--fc-text</code>, <code>--fc-accent</code>, <code>--fc-field-bg</code>, <code>--fc-heading-font</code> (<?php esc_html_e( 'used when the heading font is Yeseva One', 'film-transfer-toolkit' ); ?>).</p>
		<p><?php esc_html_e( 'Standards used: 8mm 80, Super 8 72, 16mm 40, 35mm 4-perf 16, 3-perf 21⅓, 2-perf 32 frames per foot; 1 ft = 0.3048 m.', 'film-transfer-toolkit' ); ?></p>
	<?php if ( $wrap ) : ?></div><?php endif; ?>
	<?php
}
