<?php
if (!defined('ABSPATH')) exit;

/**
 * A clip = product + source clip id + in/out (seconds) + fps.
 * Canonical data is signed with an HMAC so the server can re-check every
 * cart line it sees and recompute its price from the signed fields only.
 */
class FTT_Clip
{
    /**
     * FluentCart stores object_id in a BIGINT UNSIGNED column, so clip line ids
     * must be integers. Clip ids live in a reserved high band [ID_BASE, ID_BASE+ID_SPAN)
     * that real variation ids never reach, and stay below 2^53 so JS keeps them exact.
     */
    const ID_BASE = 8000000000000000;
    const ID_SPAN = 1000000000000000;

    public static function is_clip_id($id)
    {
        if (!is_numeric($id)) return false;
        $n = (int) $id;
        return (string) $n === (string) $id || (is_int($id) && $id === $n)
            ? ($n >= self::ID_BASE && $n < self::ID_BASE + self::ID_SPAN)
            : false;
    }

    /** Validate raw input and build a canonical clip array, or WP_Error. */
    public static function build($product_id, $clip_id, $in, $out, $fps, $seq = 1, $name = '')
    {
        $card = FTT_Clip_Rate_Card::get();
        $product_id = absint($product_id);
        $clip_id = sanitize_text_field((string) $clip_id);
        if (!is_numeric($in) || !is_numeric($out)) {
            return new WP_Error('fwcc_bad_input', 'In and out must be numbers (seconds).', ['status' => 400]);
        }
        $fps = FTT_Timecode::norm($fps);
        if ($fps === null) return new WP_Error('fwcc_bad_fps', 'Unsupported frame rate.', ['status' => 400]);
        // The product decides the frame rate; the browser must agree with it.
        if ($fps !== self::product_fps($product_id)) {
            return new WP_Error('fwcc_fps_mismatch', 'Frame rate does not match this video.', ['status' => 400]);
        }

        // Snap to whole frames (same floor+epsilon rule as the browser) so in/out are repeatable.
        $in_f  = FTT_Timecode::seconds_to_frames($in, $fps);
        $out_f = FTT_Timecode::seconds_to_frames($out, $fps);
        if ($in_f < 0 || $out_f <= $in_f) {
            return new WP_Error('fwcc_bad_range', 'Out must be after In.', ['status' => 400]);
        }
        $duration = FTT_Timecode::frames_to_seconds($out_f - $in_f, $fps);
        if ($duration > $card['max_seconds']) {
            return new WP_Error('fwcc_too_long', 'Clip is longer than the maximum allowed.', ['status' => 400]);
        }
        if ($clip_id === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $clip_id)) {
            return new WP_Error('fwcc_bad_clip', 'Invalid clip id.', ['status' => 400]);
        }
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'fluent-products' || !in_array($product->post_status, ['publish', 'private'], true)) {
            return new WP_Error('fwcc_bad_product', 'Unknown product.', ['status' => 400]);
        }
        if (get_post_meta($product_id, '_fw_clip_cart_enabled', true) !== '1') {
            return new WP_Error('fwcc_not_enabled', 'This product does not sell clips.', ['status' => 400]);
        }
        $allowed_clip = get_post_meta($product_id, '_fw_clip_id', true);
        if ($allowed_clip !== '' && $allowed_clip !== $clip_id) {
            return new WP_Error('fwcc_clip_mismatch', 'Clip id does not belong to this product.', ['status' => 400]);
        }

        $seq = (int) $seq;
        if ($seq < 1 || $seq > 999) {
            return new WP_Error('fwcc_bad_seq', 'Invalid clip sequence number.', ['status' => 400]);
        }
        $label = $clip_id . '-' . sprintf('%02d', $seq);
        $name = trim(preg_replace('/\s+/', ' ', sanitize_text_field((string) $name)));
        if (function_exists('mb_substr')) { $name = mb_substr($name, 0, 120); } else { $name = substr($name, 0, 120); }
        if ($name === '') {
            $name = self::default_title($product_id) . ' ' . sprintf('%02d', $seq);
        }

        $off = self::product_offset_frames($product_id);
        return [
            'v'           => 3,
            'seq'         => $seq,
            'clip_label'  => $label,
            'clip_name'   => $name,
            'product_id'  => $product_id,
            'clip_id'     => $clip_id,
            'fps'         => $fps,
            'fps_label'   => FTT_Timecode::LABELS[$fps],
            'in_frame'    => $in_f,
            'out_frame'   => $out_f,
            'tc_offset_frames' => $off,
            'in_tc'       => FTT_Timecode::frames_to_tc($in_f + $off, $fps),   // master TC (preview TC + offset)
            'out_tc'      => FTT_Timecode::frames_to_tc($out_f + $off, $fps),
            'preview_in_tc'  => FTT_Timecode::frames_to_tc($in_f, $fps),
            'preview_out_tc' => FTT_Timecode::frames_to_tc($out_f, $fps),
            'in_sec'      => round(FTT_Timecode::frames_to_seconds($in_f, $fps), 6),
            'out_sec'     => round(FTT_Timecode::frames_to_seconds($out_f, $fps), 6),
            'duration'    => round($duration, 3),
            'billable_s'  => FTT_Clip_Rate_Card::billable_seconds($duration),
            'price_cents' => FTT_Clip_Rate_Card::price_cents($duration),
            'rate_label'  => FTT_Clip_Rate_Card::get()['label'],
        ];
    }

    /** Frame rate for a product: post meta _fw_fps, default 29.97 DF. */
    public static function product_fps($product_id)
    {
        $k = FTT_Timecode::norm(get_post_meta($product_id, '_fw_fps', true));
        return $k ?: apply_filters('fwcc/default_fps', '29.97df');
    }

    /** Master timecode offset: post meta _fw_tc_offset as a timecode (e.g. 01:00:00;00), default 0. */
    public static function product_offset_frames($product_id)
    {
        $tc = get_post_meta($product_id, '_fw_tc_offset', true);
        if ($tc === '') return 0;
        $f = FTT_Timecode::tc_to_frames($tc, self::product_fps($product_id));
        return $f === null ? 0 : (int) $f;
    }

    public static function default_title($product_id)
    {
        $t = get_post_meta($product_id, '_fw_clip_title', true);
        if ($t === '') { $p = get_post($product_id); $t = $p ? $p->post_title : 'Clip'; }
        return wp_strip_all_tags($t);
    }

    public static function item_id(array $clip)
    {
        $h = hash('sha256', $clip['product_id'] . '|' . $clip['clip_id'] . '|' . $clip['in_frame'] . '|' . $clip['out_frame'] . '|' . $clip['fps'] . '|' . $clip['seq'] . '|' . $clip['clip_name']);
        // 13 hex digits = 52 bits; reduce into the reserved band.
        return self::ID_BASE + (hexdec(substr($h, 0, 13)) % self::ID_SPAN);
    }

    public static function sign(array $clip)
    {
        $msg = implode('|', [$clip['v'], $clip['product_id'], $clip['clip_id'], $clip['fps'], $clip['in_frame'], $clip['out_frame'], $clip['seq'], $clip['clip_name']]);
        return hash_hmac('sha256', $msg, wp_salt('auth') . 'fwcc');
    }

    /**
     * Re-derive a clip from stored (possibly tampered) data. Only the signed
     * fields are trusted; price etc. are recomputed. Returns array or WP_Error.
     */
    public static function from_stored($stored)
    {
        if (is_string($stored)) $stored = json_decode($stored, true);
        if (!is_array($stored)) return new WP_Error('fwcc_missing', 'Missing clip data.');
        $clip = $stored['fw_clip'] ?? null; $sig = $stored['fw_sig'] ?? '';
        if (!is_array($clip)) return new WP_Error('fwcc_missing', 'Missing clip data.');
        $fps = FTT_Timecode::norm($clip['fps'] ?? '');
        if ($fps === null) return new WP_Error('fwcc_bad', 'Bad clip data.');
        $rebuilt = self::build($clip['product_id'] ?? 0, $clip['clip_id'] ?? '', FTT_Timecode::frames_to_seconds((int) ($clip['in_frame'] ?? 0), $fps), FTT_Timecode::frames_to_seconds((int) ($clip['out_frame'] ?? 0), $fps), $fps, $clip['seq'] ?? 0, $clip['clip_name'] ?? '');
        if (is_wp_error($rebuilt)) return $rebuilt;
        if (!hash_equals(self::sign($rebuilt), (string) $sig)) {
            return new WP_Error('fwcc_bad_sig', 'Clip signature check failed.');
        }
        return $rebuilt;
    }

    /** FluentCart custom "variation" for a clip. */
    public static function to_variation(array $clip)
    {
        $product = get_post($clip['product_id']);
        $title = sprintf('%s "%s"  %s - %s (%ss)', $clip['clip_label'], $clip['clip_name'], $clip['in_tc'], $clip['out_tc'], rtrim(rtrim(number_format($clip['duration'], 3, '.', ''), '0'), '.'));
        return [
            'id'                => self::item_id($clip),
            'object_id'         => self::item_id($clip),
            'post_id'           => $clip['product_id'],
            'post_title'        => $product ? $product->post_title : 'Clip',
            'title'             => $title,
            'variation_title'   => $title,
            'item_price'        => $clip['price_cents'],
            'price'             => $clip['price_cents'],
            'unit_price'        => $clip['price_cents'],
            'payment_type'      => 'onetime',
            'fulfillment_type'  => 'digital',
            'sold_individually' => true,
            'is_custom'         => true,
            'quantity'          => 1,
            'view_url'          => $product ? get_permalink($product) : '',
            'other_info'        => [
                'fw_clip' => $clip,
                'fw_sig'  => self::sign($clip),
            ],
        ];
    }
}
