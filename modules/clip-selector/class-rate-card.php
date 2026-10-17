<?php
if (!defined('ABSPATH')) exit;

/**
 * Clip rate card. Values come from Film Transfer Toolkit > Clip selector settings.
 * Defaults are EXAMPLE placeholders (the same as the fw-clip-cart 0.4.0 prototype), not real pricing.
 * All money is integer cents (FluentCart convention).
 */
class FTT_Clip_Rate_Card
{
    public static function defaults()
    {
        return [
            'label'            => 'EXAMPLE rate card (placeholder, not real pricing)',
            'per_second_cents' => 200,
            'min_seconds'      => 5,
            'max_seconds'      => 300,
            'min_charge_cents' => 1000,
        ];
    }

    public static function sanitize($in)
    {
        $d = self::defaults();
        $in = is_array($in) ? $in : [];
        $int = function ($k, $min, $max) use ($in, $d) { $v = isset($in[$k]) && is_numeric($in[$k]) ? (int) $in[$k] : $d[$k]; return max($min, min($max, $v)); };
        return [
            'label'            => isset($in['label']) && trim((string) $in['label']) !== '' ? mb_substr(sanitize_text_field((string) $in['label']), 0, 120) : $d['label'],
            'per_second_cents' => $int('per_second_cents', 0, 1000000),
            'min_seconds'      => $int('min_seconds', 0, 3600),
            'max_seconds'      => $int('max_seconds', 1, 36000),
            'min_charge_cents' => $int('min_charge_cents', 0, 10000000),
        ];
    }

    public static function get()
    {
        $card = self::sanitize(get_option('ftt_clip_rate_card', []));
        $card = apply_filters('fwcc/rate_card', $card); // legacy prototype filter
        return self::sanitize(apply_filters('ftt/clip/rate_card', $card));
    }

    /** Billable seconds: whole seconds, rounded up, never below the minimum. */
    public static function billable_seconds($duration)
    {
        $c = self::get();
        return (int) max($c['min_seconds'], ceil(round($duration, 3)));
    }

    public static function price_cents($duration)
    {
        $c = self::get();
        $cents = self::billable_seconds($duration) * (int) $c['per_second_cents'];
        return (int) max($c['min_charge_cents'], $cents);
    }
}
