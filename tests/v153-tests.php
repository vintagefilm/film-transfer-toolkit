<?php
/**
 * 1.5.3: clip selector heading (setting, shortcode/block option, registry/help) and Jump to In/Out markup.
 * Run: wp eval-file tests/v153-tests.php   (restores ftt_clip_settings)
 */
$P = 0; $F = 0;
$ok = function ( $n, $c, $d = '' ) use ( &$P, &$F ) { if ( $c ) { $P++; } else { $F++; echo "FAIL $n" . ( '' !== $d ? ' :: ' . ( is_string( $d ) ? $d : wp_json_encode( $d ) ) : '' ) . "\n"; } };
$keep = get_option( 'ftt_clip_settings', null );
wp_set_current_user( 1 );
delete_option( 'ftt_clip_settings' );
$d = FTT_Clip_Settings::get();
$ok( 'default heading', 'Select Stock Footage' === $d['heading'] );
$old = array( 'default_product_id' => 15496, 'default_fps' => '23.976', 'auto_attach' => 'off' ); // live 1.5.2 shape
$ok( '1.5.2 settings (no heading key) get the default heading', 'Select Stock Footage' === FTT_Clip_Settings::sanitize( $old )['heading'] );
$ok( '1.5.2 settings keep their other values', array_diff_key( FTT_Clip_Settings::sanitize( $old ), array( 'heading' => 1 ) ) === $old );
$ok( 'blank saved heading stays blank', '' === FTT_Clip_Settings::sanitize( array( 'heading' => '   ' ) )['heading'] );
$ok( 'heading strips tags and collapses space', 'Pick a clip' === FTT_Clip_Settings::sanitize( array( 'heading' => "<b>Pick</b>\n a   clip<script>x</script>" ) )['heading'] );
$ok( 'heading capped at 120 chars', 120 === strlen( FTT_Clip_Settings::sanitize( array( 'heading' => str_repeat( 'a', 300 ) ) )['heading'] ) );
$ok( 'non-scalar heading -> blank', '' === FTT_Clip_Settings::sanitize( array( 'heading' => array( 'x' ) ) )['heading'] );
$ok( 'heading_for: missing -> setting', 'Select Stock Footage' === FTT_Clip_Settings::heading_for( array() ) );
$ok( 'heading_for: empty -> setting', 'Select Stock Footage' === FTT_Clip_Settings::heading_for( array( 'heading' => '' ) ) );
$ok( 'heading_for: none / NONE -> hidden', '' === FTT_Clip_Settings::heading_for( array( 'heading' => 'none' ) ) && '' === FTT_Clip_Settings::heading_for( array( 'heading' => 'NONE' ) ) );
$ok( 'heading_for: own text', 'Our reels' === FTT_Clip_Settings::heading_for( array( 'heading' => 'Our reels' ) ) );
$ok( 'heading_for: string atts (no attributes) -> setting', 'Select Stock Footage' === FTT_Clip_Settings::heading_for( '' ) );

$h = function ( $html ) { return preg_match( '#<h3 class="ftt-tool__title fwcc-heading">(.*?)</h3>#', $html, $m ) ? $m[1] : null; };
$r = do_shortcode( '[ftt_clip_selector clip_id="V153"]' );
$ok( 'render: default heading in an h3.ftt-tool__title', 'Select Stock Footage' === $h( $r ), $h( $r ) );
$ok( 'render: heading before the jump bar, jump bar before the marks', strpos( $r, 'fwcc-heading' ) < strpos( $r, 'fwcc-jumpbar' ) && strpos( $r, 'fwcc-jumpbar' ) < strpos( $r, 'fwcc-marks' ) );
$ok( 'render: two jump buttons with key shortcuts', 1 === substr_count( $r, 'class="button fwcc-jump" data-which="in" aria-keyshortcuts="Shift+I"' ) && 1 === substr_count( $r, 'class="button fwcc-jump" data-which="out" aria-keyshortcuts="Shift+O"' ) );
$ok( 'render: jump buttons labelled', false !== strpos( $r, '>Jump to In</button>' ) && false !== strpos( $r, '>Jump to Out</button>' ) && false !== strpos( $r, 'aria-label="Move the video to your In point' ) );
$ok( 'render: jump group labelled', false !== strpos( $r, 'role="group" aria-label="Jump to a mark"' ) );
$ok( 'render: help mentions Shift+I', false !== strpos( $r, '<kbd>Shift</kbd>+<kbd>I</kbd> Jump to In' ) );
$ok( 'render: escaped override', '&lt;i&gt;Hi&lt;/i&gt; &amp; co' === $h( do_shortcode( '[ftt_clip_selector heading="&lt;i&gt;Hi&lt;/i&gt; &amp; co"]' ) ) || 'Hi &amp; co' === $h( do_shortcode( '[ftt_clip_selector heading="<i>Hi</i> & co"]' ) ), $h( do_shortcode( '[ftt_clip_selector heading="<i>Hi</i> & co"]' ) ) );
$ok( 'render: heading="none" hides', null === $h( do_shortcode( '[ftt_clip_selector heading="none"]' ) ) && false === strpos( do_shortcode( '[ftt_clip_selector heading="none"]' ), 'fwcc-heading' ) );
$ok( 'render: heading="" uses the setting (registry contract)', 'Select Stock Footage' === $h( do_shortcode( '[ftt_clip_selector heading=""]' ) ) );
$ok( 'render: block attr', 'From block' === $h( do_blocks( '<!-- wp:ftt/clip-selector {"heading":"From block"} /-->' ) ) );
$ok( 'render: block attr none', null === $h( do_blocks( '<!-- wp:ftt/clip-selector {"heading":"none"} /-->' ) ) );
$ok( 'render: block default = bare shortcode', $h( do_blocks( '<!-- wp:ftt/clip-selector /-->' ) ) === $h( do_shortcode( '[ftt_clip_selector]' ) ) );
update_option( 'ftt_clip_settings', array( 'heading' => '' ) );
$ok( 'blank setting hides on every plain selector', false === strpos( do_shortcode( '[ftt_clip_selector]' ), 'fwcc-heading' ) );
$ok( 'blank setting: per-selector override still shows', 'Own' === $h( do_shortcode( '[ftt_clip_selector heading="Own"]' ) ) );
$ok( 'blank setting: jump buttons still there', false !== strpos( do_shortcode( '[ftt_clip_selector]' ), 'fwcc-jumpbar' ) );
update_option( 'ftt_clip_settings', array( 'heading' => 'Site wide' ) );
$ok( 'saved setting used', 'Site wide' === $h( do_shortcode( '[ftt_clip_selector]' ) ) );
$ok( 'auto placement uses the setting', 'Site wide' === $h( FTT_Clip_Shortcode::render( array() ) ) );

// registry / help
$emb = FTT_Registry::embeds()['ftt_clip_selector'];
$att = wp_list_filter( $emb['atts'], array( 'name' => 'heading' ) ); $att = reset( $att );
$ok( 'registry: heading option, text, linked to clip:heading', $att && 'text' === $att['type'] && 'clip:heading' === $att['setting'] && '' === $att['default'] );
$ok( 'registry: current value shown in help/sidebar', false !== strpos( FTT_Registry::default_text( $att ), 'Site wide' ), FTT_Registry::default_text( $att ) );
$ok( 'registry: example', isset( $emb['examples']['[ftt_clip_selector heading="Choose your clip"]'] ) );
$faq = implode( ' ', array_keys( FTT_Registry::tools()['clips']['faq'] ) );
$ok( 'FAQ entries', false !== strpos( $faq, 'In or Out point' ) && false !== strpos( $faq, 'Select Stock Footage' ) );
$ok( 'block registered with a heading attribute', isset( WP_Block_Type_Registry::get_instance()->get_registered( 'ftt/clip-selector' )->attributes['heading'] ) );
ob_start(); ftt_clip_render_settings(); $adm = ob_get_clean();
$ok( 'admin: Heading field in the main clip settings', false !== strpos( $adm, 'id="ftt-clip-heading"' ) && false !== strpos( $adm, 'name="ftt_clip_settings[heading]"' ) && false !== strpos( $adm, 'value="Site wide"' ) );
$ok( 'admin: field is outside the Advanced box', strpos( $adm, 'ftt-clip-heading' ) < strpos( $adm, 'id="ftt-adv-clips"' ) );
$css = file_get_contents( FTT_DIR . 'modules/clip-selector/assets/clip-selector.css' );
$ok( 'css: no hard-coded font for the heading (uses ftt-base title style)', ! preg_match( '/fwcc-heading[^}]*font-family/', $css ) );
$ok( 'version 1.5.3', '1.5.3' === FTT_VERSION );

if ( null === $keep ) { delete_option( 'ftt_clip_settings' ); } else { update_option( 'ftt_clip_settings', $keep ); }
echo "v153 php: $P passed, $F failed\n";
