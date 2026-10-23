<?php
if (!defined('ABSPATH')) exit;

/**
 * Re-validate every fw-clip line whenever FluentCart touches it:
 *  - cart quantity/update requests (fluent_cart/cart/validate_custom_item,
 *    fluent_cart/cart/custom_item_quantity_changed)
 *  - payment / order building (fluent_cart/payment/validate_custom_item)
 * The price is always recomputed from the HMAC-signed clip fields.
 */
class FTT_Clip_FluentCart_Hooks
{
    public static function init()
    {
        add_filter('fluent_cart/cart/validate_custom_item', [__CLASS__, 'validate_cart_item'], 10, 2);
        add_filter('fluent_cart/cart/custom_item_quantity_changed', [__CLASS__, 'quantity_changed'], 10, 2);
        add_filter('fluent_cart/payment/validate_custom_item', [__CLASS__, 'validate_payment_item'], 10, 2);
        add_action('fluent_cart/cart/line_item/line_meta', [__CLASS__, 'render_line_meta'], 10, 1);
        // 1.5.4: name the delivery-detail attributes in the admin order and order emails.
        foreach (array_keys(FTT_Clip::delivery_labels()) as $k) {
            add_filter('fluent_cart/item_display_attr_' . $k, [__CLASS__, 'label_attr'], 10, 2);
        }
        // Source and ffmpeg rows are for staff only: hidden from the cart, checkout, customer
        // portal and every email body; admin-bound order emails get a delivery block appended.
        add_filter('fluent_cart/item_display_attr', [__CLASS__, 'staff_only_rows'], 10, 2);
        add_filter('fluent_cart/parse_email_block_content', [__CLASS__, 'mail_render_start'], 1, 1);
        add_filter('fluent_cart/email_notification/mailer', [__CLASS__, 'mailer'], 10, 2);
    }

    private static function is_ours($id)
    {
        return FTT_Clip::is_clip_id($id);
    }

    private static function arr($x)
    {
        if (is_object($x)) $x = json_decode(wp_json_encode($x), true);
        return is_array($x) ? $x : [];
    }

    private static function revalidate($existing)
    {
        $existing = self::arr($existing);
        $info = $existing['other_info'] ?? [];
        $clip = FTT_Clip::from_stored(self::arr($info) ?: $info);
        if (is_wp_error($clip)) return $clip;
        if ((int) ($existing['id'] ?? $existing['object_id'] ?? 0) !== FTT_Clip::item_id($clip)) {
            return new WP_Error('fwcc_bad_id', 'Item id does not match clip.');
        }
        return $clip;
    }

    public static function validate_cart_item($variation, $args)
    {
        $id = $args['item_id'] ?? '';
        if (!self::is_ours($id)) return $variation;
        if (empty($variation)) return null; // no instant checkout / no blind adds for clips
        $clip = self::revalidate($variation);
        if (is_wp_error($clip)) return null;
        $v = FTT_Clip::to_variation($clip);
        return (object) $v;
    }

    public static function quantity_changed($variation, $args)
    {
        $arr = self::arr($variation);
        if (!self::is_ours($arr['id'] ?? '')) return $variation;
        $clip = self::revalidate($arr);
        if (is_wp_error($clip)) { $arr['quantity'] = 0; return (object) $arr; }
        $v = FTT_Clip::to_variation($clip);
        // A clip is a single licence line: quantity 0 removes it, anything else is 1.
        $v['quantity'] = ((int) ($args['new_quantity'] ?? 1)) > 0 ? 1 : 0;
        return (object) $v;
    }

    public static function validate_payment_item($pair, $data)
    {
        if (!is_array($pair) || count($pair) !== 2) return $pair;
        [$product, $variation] = $pair;
        $arr = self::arr($variation);
        $id = $arr['object_id'] ?? ($arr['id'] ?? '');
        if (!self::is_ours($id)) return $pair;
        $arr['id'] = $id;
        $clip = self::revalidate($arr);
        if (is_wp_error($clip)) return [null, null];
        $v = FTT_Clip::to_variation($clip);
        foreach (['item_price', 'price', 'unit_price'] as $k) { $variation->$k = $v[$k]; }
        $variation->other_info = $v['other_info'];
        return [$product, $variation];
    }

    /** @var bool true while FluentCart renders an email body */
    private static $in_mail = false;

    public static function mail_render_start($v)
    {
        self::$in_mail = true;
        return $v;
    }

    /** Staff context: an admin (manage_options) in wp-admin or the FluentCart admin REST API, not inside an email. */
    public static function is_staff_view()
    {
        $staff = !self::$in_mail && current_user_can('manage_options') && (is_admin() || (defined('REST_REQUEST') && REST_REQUEST));
        return (bool) apply_filters('ftt/clip/show_delivery_details', $staff);
    }

    public static function staff_only_rows($atts, $ctx = [])
    {
        if (!is_array($atts) || self::is_staff_view()) return $atts;
        unset($atts['fw_source'], $atts['fw_ffmpeg']);
        return $atts;
    }

    /** Order clip rows for an email/admin view: from the Order model or its items. */
    public static function clips_from_order($order)
    {
        $items = is_object($order) && isset($order->order_items) ? $order->order_items : [];
        $clips = [];
        foreach ($items ?: [] as $it) {
            $it = self::arr($it);
            if (!FTT_Clip::is_clip_id((string) ($it['object_id'] ?? ''))) continue;
            $c = FTT_Clip::from_stored($it['other_info'] ?? []);
            if (is_array($c)) $clips[] = $c;
        }
        if (!$clips && is_object($order) && !empty($order->id)) {
            $clips = FTT_Clip_Cutlist::clips_for_order((int) $order->id);
        }
        return $clips;
    }

    /** HTML block with the manual delivery details (admin emails). */
    public static function delivery_block(array $clips)
    {
        if (!$clips) return '';
        $h = '<div class="ftt-clip-delivery" style="margin:16px 24px;padding:12px;border:1px solid #ddd;font-family:Arial,sans-serif;font-size:13px">'
            . '<p style="margin:0 0 8px"><strong>Clip delivery details (manual; nothing is sent to the customer automatically)</strong></p>';
        foreach ($clips as $c) {
            $s = FTT_Clip::source_info($c);
            $h .= '<p style="margin:8px 0 2px"><strong>' . esc_html($c['clip_label'] . ' "' . $c['clip_name'] . '"') . '</strong><br>'
                . 'Source video: ' . esc_html($s['file'] !== '' ? $s['file'] . ' (' . $s['id'] . ')' : $s['id']) . '<br>'
                . 'In: ' . esc_html($c['in_tc']) . ' &nbsp; Out: ' . esc_html($c['out_tc']) . ' &nbsp; Frame rate: ' . esc_html($c['fps_label']) . ' fps</p>'
                . '<pre style="white-space:pre-wrap;word-break:break-all;background:#f6f6f6;padding:6px;margin:0">' . esc_html(FTT_Clip::ffmpeg_command($c)) . '</pre>';
        }
        return $h . '</div>';
    }

    public static function mailer($mailer, $ctx = [])
    {
        self::$in_mail = false;
        if (!is_object($mailer) || ($ctx['recipient'] ?? '') !== 'admin') return $mailer;
        $order = $ctx['data']['order'] ?? null;
        $block = self::delivery_block(self::clips_from_order($order));
        if ($block === '' || !method_exists($mailer, 'body')) return $mailer;
        try {
            $get = \Closure::bind(function () { return $this->body; }, $mailer, get_class($mailer));
            $body = (string) $get();
        } catch (\Throwable $e) {
            return $mailer;
        }
        if (strpos($body, 'ftt-clip-delivery') !== false) return $mailer;
        $pos = stripos($body, '</body>');
        $body = $pos === false ? $body . $block : substr($body, 0, $pos) . $block . substr($body, $pos);
        $mailer->body($body);
        return $mailer;
    }

    public static function label_attr($row, $ctx = [])
    {
        $labels = FTT_Clip::delivery_labels();
        $k = is_array($row) ? ($row['attr_key'] ?? '') : '';
        if (isset($labels[$k])) $row['display_title'] = $labels[$k];
        return $row;
    }

    public static function render_line_meta($info)
    {
        $item = self::arr($info['item'] ?? ($info['cart_item'] ?? []));
        $clip = $item['other_info']['fw_clip'] ?? null;
        if (!is_array($clip)) return;
        printf('<div class="fwcc-line-meta">%s &ldquo;%s&rdquo; &middot; In %s &middot; Out %s &middot; %ss @ %s fps</div>',
            esc_html($clip['clip_label']), esc_html($clip['clip_name']), esc_html($clip['in_tc']), esc_html($clip['out_tc']), esc_html($clip['duration']), esc_html($clip['fps_label'] ?? $clip['fps']));
    }
}
