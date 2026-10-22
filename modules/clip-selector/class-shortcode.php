<?php
if (!defined('ABSPATH')) exit;

/**
 * [ftt_clip_selector player="auto|html5|youtube|vimeo|fluent|fv" player_selector="#id" clip_id="FIRE-0001" fps="29.97df" title="Story title" product_id="123"]
 * (alias [fw_clip_cart]). Place it under any video player: the core Video block / an uploaded <video>, a YouTube or
 * Vimeo embed, FluentPlayer or FV Player. player_selector attaches it to one particular player.
 * Cart mode (FluentCart active and a clip product from product_id or the default in settings): clips go to the cart.
 * Request mode (otherwise): visitors send the list as a clip request (FTT_Clip_Request).
 * title defaults to the product's _fw_clip_title meta, then the product title, then the page title.
 */
class FTT_Clip_Shortcode
{
    /** Set while rendering a preview of a page when the module is switched off (FTT_Plugin::clip_preview). */
    public static $demo = false;

    const PLAYERS = ['auto', 'html5', 'youtube', 'vimeo', 'fluent', 'fv'];

    public static function init()
    {
        add_shortcode('ftt_clip_selector', [__CLASS__, 'render']);
        if (!shortcode_exists('fw_clip_cart')) add_shortcode('fw_clip_cart', [__CLASS__, 'render']);
    }

    /** Buyer-facing tooltip text. Edit with the fwcc/tooltips filter. */
    public static function tips()
    {
        return apply_filters('ftt/clip/tooltips', [
            'set_in'    => 'Mark the start of your clip at the current video time (shortcut: I)',
            'set_out'   => 'Mark the end of your clip at the current video time; the video pauses there (shortcut: O)',
            'jump_in'   => 'Move the video to your In point and pause there (shortcut: Shift+I)',
            'jump_out'  => 'Move the video to your Out point and pause there (shortcut: Shift+O)',
            'trim'      => [
                '-1s' => 'Trim: move the %s point back 1 second (shortcut: Ctrl+Left arrow)',
                '-1f' => 'Trim: move the %s point back 1 frame (shortcut: Left arrow)',
                '+1f' => 'Trim: move the %s point forward 1 frame (shortcut: Right arrow)',
                '+1s' => 'Trim: move the %s point forward 1 second (shortcut: Ctrl+Right arrow)',
            ],
            'trim_label'=> 'Fine-tune the %s point by one frame (f) or one second (s)',
            'preview'   => 'Play just the part you selected, from In to Out',
            'clear'     => 'Clear the In and Out marks and start this clip again',
            'name'      => 'Give this clip a name (you can change the suggested one)',
            'addlist'   => 'Add this In/Out selection to your clip list below',
            'edit'      => 'Edit this clip\'s name',
            'remove'    => 'Remove this clip from your list',
            'addcart'   => 'Add all clips in your list to the cart',
            'request'   => 'Send your clip list to us; we reply with the final price',
            'copy'      => 'Copy the clip list (names and timecodes) to paste into an email',
        ]);
    }

    /** Help panel HTML (inside <details>). Edit with the fwcc/help_html filter or the fwcc_help_html option. */
    public static function help_html()
    {
        $default = '<ol>'
            . '<li>Play the video and find where you want your clip to start.</li>'
            . '<li>Click <b>Set In</b>.</li>'
            . '<li>Keep playing to where you want the clip to end.</li>'
            . '<li>Click <b>Set Out</b>. The video pauses there so you can see the last frame.</li>'
            . '<li>Use <b>Jump to In</b> or <b>Jump to Out</b> to go back to either point at any time.</li>'
            . '<li>Use the <b>Trim</b> buttons to fine-tune the In or Out point by one frame (f) or one second (s).</li>'
            . '<li>Click <b>Preview range</b> to watch just your selection.</li>'
            . '<li>Check the clip name, or type your own.</li>'
            . '<li>Click <b>Add to clip list</b>. Repeat to add more clips from the same video.</li>'
            . '<li>When your list is ready, click <b>Add clips to cart</b> (or <b>Send clip request</b>). <b>Copy list</b> copies the names and timecodes.</li>'
            . '</ol>'
            . '<p><b>Keyboard shortcuts</b> (with the mouse over the video or this panel, not while typing a name):</p>'
            . '<ul class="fwcc-keys">'
            . '<li><kbd>L</kbd> play; press again for 2x, then 4x (if the video allows)</li>'
            . '<li><kbd>K</kbd> pause</li>'
            . '<li><kbd>J</kbd> step backwards (about 0.1 s at a time; YouTube cannot play in reverse)</li>'
            . '<li><kbd>I</kbd> Set In &nbsp; <kbd>O</kbd> Set Out (pauses on the Out frame)</li>'
            . '<li><kbd>Shift</kbd>+<kbd>I</kbd> Jump to In &nbsp; <kbd>Shift</kbd>+<kbd>O</kbd> Jump to Out</li>'
            . '<li><kbd>&larr;</kbd> / <kbd>&rarr;</kbd> one frame back / forward (moves the In or Out point you just set, otherwise the video)</li>'
            . '<li><kbd>Ctrl</kbd>+<kbd>&larr;</kbd> / <kbd>Ctrl</kbd>+<kbd>&rarr;</kbd> one second back / forward</li>'
            . '</ul>'
            . '<p>The price shown here is an estimate. The final price is confirmed in the cart.</p>'
            . '<p>Times are shown as timecode: hours:minutes:seconds:frames (for example 00:01:15;12 is 1 minute, 15 seconds and 12 frames). A semicolon before the frames means drop-frame timecode, used for 29.97 fps video.</p>';
        $opt = get_option('ftt_clip_help_html', get_option('fwcc_help_html', ''));
        return apply_filters('ftt/clip/help_html', apply_filters('fwcc/help_html', $opt !== '' ? $opt : $default));
    }

    private static function trim_buttons($which)
    {
        $all = self::tips(); $tips = $all['trim'];
        $out = '';
        foreach ($tips as $d => $tip) {
            $t = sprintf($tip, strtoupper($which) === 'IN' ? 'In' : 'Out');
            $out .= sprintf('<button type="button" class="button fwcc-trim-btn" data-d="%1$s" title="%2$s" aria-label="%2$s">%1$s</button>', esc_attr($d), esc_attr($t));
        }
        return $out;
    }

    public static function render($atts)
    {
        $a = shortcode_atts(['product_id' => 0, 'clip_id' => '', 'fps' => '', 'player' => 'auto', 'player_selector' => '', 'title' => '', 'heading' => ''], $atts, 'ftt_clip_selector');
        $heading = FTT_Clip_Settings::heading_for($a); // 1.5.3
        $set = FTT_Clip_Settings::get();
        $pid = absint($a['product_id']) ?: (int) $set['default_product_id'];
        $mode = FTT_Clip_Settings::is_clip_product($pid) ? 'cart' : 'request';
        if ($mode === 'request') $pid = 0;
        $page_id = (int) get_the_ID();
        // Frame rate: shortcode fps="29.97df" overrides; else the product's _fw_fps (cart mode); else the settings default.
        // In cart mode the server validates against the product's rate, so set _fw_fps on the product to match.
        $fps = FTT_Timecode::norm($a['fps']) ?: ($pid ? FTT_Clip::product_fps($pid) : $set['default_fps']);
        $offset = $pid ? FTT_Clip::product_offset_frames($pid) : 0;
        $title = $a['title'] !== '' ? sanitize_text_field($a['title']) : ($pid ? FTT_Clip::default_title($pid) : ($page_id ? get_the_title($page_id) : __('Clip', 'film-transfer-toolkit')));
        $clip_id = preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string) $a['clip_id']) ? (string) $a['clip_id'] : '';
        if ($clip_id === '' && $pid) $clip_id = (string) get_post_meta($pid, '_fw_clip_id', true);
        if ($clip_id === '') {
            $slug = $page_id ? (string) get_post_field('post_name', $page_id) : '';
            $clip_id = substr(strtoupper(preg_replace('/[^A-Za-z0-9._-]+/', '-', $slug)), 0, 64) ?: 'CLIP';
        }
        $player = in_array(strtolower($a['player']), self::PLAYERS, true) ? strtolower($a['player']) : 'auto';
        $a['clip_id'] = $clip_id;
        $card = FTT_Clip_Rate_Card::get();
        $T = self::tips();
        $ta = function ($k, $arg = null) use ($T) { $v = $arg === null ? $T[$k] : sprintf($T[$k], $arg); return 'title="' . esc_attr($v) . '" aria-label="' . esc_attr($v) . '"'; };
        wp_enqueue_style('ftt-clip', FTT_CLIP_URL . 'assets/clip-selector.css', ['ftt-base'], FTT_VERSION);
        wp_enqueue_script('ftt-clip-players', FTT_CLIP_URL . 'assets/clip-players.js', [], FTT_VERSION, true);
        wp_enqueue_script('ftt-clip', FTT_CLIP_URL . 'assets/clip-selector.js', ['ftt-core', 'ftt-clip-players'], FTT_VERSION, true);
        wp_localize_script('ftt-clip', 'FTT_CLIP', [
            'tips'  => ['edit' => $T['edit'], 'remove' => $T['remove'], 'rowname' => $T['name']],
            'rest'  => esc_url_raw(rest_url(FTT_Clip_Rest::NS)),
            'nonce' => wp_create_nonce('wp_rest'),
            'card'  => $card,
        ]);
        ob_start(); ?>
<div class="ftt-tool fwcc" data-mode="<?php echo esc_attr($mode); ?>"<?php echo self::$demo ? ' data-demo="1"' : ''; ?> data-page="<?php echo esc_attr($page_id); ?>" data-product="<?php echo esc_attr($pid); ?>" data-clip="<?php echo esc_attr($a['clip_id']); ?>" data-fps="<?php echo esc_attr($fps); ?>" data-offset="<?php echo esc_attr($offset); ?>" data-player="<?php echo esc_attr($player); ?>" data-player-selector="<?php echo esc_attr(sanitize_text_field($a['player_selector'])); ?>" data-title="<?php echo esc_attr($title); ?>">
<?php if (self::$demo) : ?>  <p class="fwcc-demo"><strong><?php esc_html_e('Preview only:', 'film-transfer-toolkit'); ?></strong> <?php esc_html_e('the Clip selector module is switched off on this site, so visitors do not see this and nothing can be sent.', 'film-transfer-toolkit'); ?></p>
<?php endif; ?>
<?php if ($heading !== '') : ?>  <h3 class="ftt-tool__title fwcc-heading"><?php echo esc_html($heading); ?></h3>
<?php endif; ?>
  <div class="fwcc-row fwcc-jumpbar" role="group" aria-label="<?php esc_attr_e('Jump to a mark', 'film-transfer-toolkit'); ?>">
    <button type="button" class="button fwcc-jump" data-which="in" aria-keyshortcuts="Shift+I" <?php echo $ta('jump_in'); ?>><?php esc_html_e('Jump to In', 'film-transfer-toolkit'); ?></button>
    <button type="button" class="button fwcc-jump" data-which="out" aria-keyshortcuts="Shift+O" <?php echo $ta('jump_out'); ?>><?php esc_html_e('Jump to Out', 'film-transfer-toolkit'); ?></button>
  </div>
  <div class="fwcc-marks">
    <button type="button" class="button fwcc-set" data-which="in" <?php echo $ta('set_in'); ?>>Set In</button>
    <span class="fwcc-tc" data-tc="in"></span>
    <span class="fwcc-trim-label" <?php echo $ta('trim_label', 'In'); ?>>Trim In</span>
    <span class="fwcc-nudge" data-which="in"><?php echo self::trim_buttons('in'); // phpcs:ignore ?></span>

    <button type="button" class="button fwcc-set" data-which="out" <?php echo $ta('set_out'); ?>>Set Out</button>
    <span class="fwcc-tc" data-tc="out"></span>
    <span class="fwcc-trim-label" <?php echo $ta('trim_label', 'Out'); ?>>Trim Out</span>
    <span class="fwcc-nudge" data-which="out"><?php echo self::trim_buttons('out'); // phpcs:ignore ?></span>
  </div>
  <div class="fwcc-row fwcc-namebar">
    <label>Clip <b class="fwcc-nextlabel"></b> name
      <input type="text" class="fwcc-name" maxlength="120" placeholder="" <?php echo $ta('name'); ?>>
    </label>
  </div>
  <div class="fwcc-row">
    <span>Duration: <b class="fwcc-dur">0.000s</b></span>
    <span>Estimate: <b class="fwcc-est">$0.00</b></span>
    <button type="button" class="button fwcc-preview" <?php echo $ta('preview'); ?>>Preview range</button>
    <button type="button" class="button fwcc-clear" <?php echo $ta('clear'); ?>>Clear</button>
    <button type="button" class="button fwcc-addlist" <?php echo $ta('addlist'); ?>>Add to clip list</button>
  </div>
  <table class="fwcc-list"><thead><tr><th>Clip #</th><th>Name</th><th>In</th><th>Out</th><th>Duration</th><th>Est.</th><th></th></tr></thead><tbody></tbody></table>
  <div class="fwcc-row"><b>List total (estimate): <span class="fwcc-total">$0.00</span></b>
    <button type="button" class="button fwcc-copy" <?php echo $ta('copy'); ?>>Copy list</button>
    <button type="button" class="button fwcc-addcart" <?php echo $mode === 'request' ? $ta('request') : $ta('addcart'); ?>><?php echo $mode === 'request' ? esc_html__('Send clip request', 'film-transfer-toolkit') : esc_html__('Add clips to cart', 'film-transfer-toolkit'); ?></button></div>
<?php if ($mode === 'request') : ?>
  <div class="fwcc-request" hidden>
    <p><?php esc_html_e('Send this clip list to us. We reply by email with the final price; nothing is charged now.', 'film-transfer-toolkit'); ?></p>
    <div class="fwcc-row">
      <label><?php esc_html_e('Your name', 'film-transfer-toolkit'); ?> <input type="text" class="fwcc-rq-name" maxlength="100" autocomplete="name" required></label>
      <label><?php esc_html_e('Email', 'film-transfer-toolkit'); ?> <input type="email" class="fwcc-rq-email" maxlength="200" autocomplete="email" required></label>
    </div>
    <div class="fwcc-row fwcc-rq-notewrap"><label><?php esc_html_e('Note (optional)', 'film-transfer-toolkit'); ?> <textarea class="fwcc-rq-note" rows="2" maxlength="2000"></textarea></label></div>
    <div class="fwcc-hp" aria-hidden="true"><label>Website <input type="text" class="fwcc-rq-hp" tabindex="-1" autocomplete="off"></label></div>
    <div class="fwcc-row"><button type="button" class="button fwcc-send"><?php esc_html_e('Send request', 'film-transfer-toolkit'); ?></button></div>
  </div>
<?php endif; ?>
  <p class="fwcc-note">Timecode: <?php echo esc_html(FTT_Timecode::LABELS[$fps]); ?> fps<?php echo $offset ? ' (master offset ' . esc_html(FTT_Timecode::frames_to_tc($offset, $fps)) . ')' : ''; ?>. <?php echo esc_html($card['label']); ?> &mdash; <?php echo $mode === 'request' ? esc_html__('estimate only; we confirm the final price when we reply.', 'film-transfer-toolkit') : esc_html__('final price is set by the server at checkout.', 'film-transfer-toolkit'); ?></p>
  <div class="fwcc-msg" aria-live="polite"></div>
  <details class="fwcc-help">
    <summary>How to select a clip</summary>
    <div class="fwcc-help-body"><?php echo wp_kses_post(self::help_html()); ?></div>
  </details>
</div>
<?php
        return ob_get_clean();
    }
}
