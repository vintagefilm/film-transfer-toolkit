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

    public static function render_line_meta($info)
    {
        $item = self::arr($info['item'] ?? ($info['cart_item'] ?? []));
        $clip = $item['other_info']['fw_clip'] ?? null;
        if (!is_array($clip)) return;
        printf('<div class="fwcc-line-meta">%s &ldquo;%s&rdquo; &middot; In %s &middot; Out %s &middot; %ss @ %s fps</div>',
            esc_html($clip['clip_label']), esc_html($clip['clip_name']), esc_html($clip['in_tc']), esc_html($clip['out_tc']), esc_html($clip['duration']), esc_html($clip['fps_label'] ?? $clip['fps']));
    }
}
