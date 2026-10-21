<?php
if (!defined('ABSPATH')) exit;

/**
 * Clip rate card. Values come from FTT > Clip selector settings.
 * Defaults are EXAMPLE placeholders (the same as the fw-clip-cart 0.4.0 prototype), not real pricing.
 * All money is integer cents (FluentCart convention).
 */
class FTT_Clip_Rate_Card
{
    /** How a clip's length becomes whole billed seconds. 'up' was the only behaviour before 1.5.1. */
    const ROUNDING = ['up', 'down', 'nearest'];

    public static function defaults()
    {
        return [
            'label'            => 'EXAMPLE rate card (placeholder, not real pricing)',
            'per_second_cents' => 200,
            'min_seconds'      => 5,
            'max_seconds'      => 300,
            'min_charge_cents' => 1000,
            'rounding'         => 'up', // up | down | nearest (whole billed seconds)
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
            'rounding'         => isset($in['rounding']) && in_array($in['rounding'], self::ROUNDING, true) ? $in['rounding'] : $d['rounding'],
        ];
    }

    public static function get()
    {
        $card = self::sanitize(get_option('ftt_clip_rate_card', []));
        $card = apply_filters('fwcc/rate_card', $card); // legacy prototype filter
        return self::sanitize(apply_filters('ftt/clip/rate_card', $card));
    }

    /**
     * Billable seconds: whole seconds (rounded per the card's 'rounding': up | down | nearest), never below the minimum.
     * The duration is first rounded to the millisecond, exactly like clip-selector.js estimate(), so quote and cart match.
     * Filter: ftt/clip/billable_seconds ($secs, $duration, $card). Note the browser estimate cannot see this filter.
     */
    public static function billable_seconds($duration, $card = null)
    {
        $c = is_array($card) ? $card : self::get();
        $ms = round((float) $duration, 3);
        switch ($c['rounding'] ?? 'up') {
            case 'down':    $whole = floor($ms); break;
            case 'nearest': $whole = round($ms); break;
            default:        $whole = ceil($ms);
        }
        $secs = (int) max($c['min_seconds'], $whole);
        return (int) apply_filters('ftt/clip/billable_seconds', $secs, $duration, $c);
    }

    public static function price_cents($duration)
    {
        $c = self::get();
        $cents = self::billable_seconds($duration, $c) * (int) $c['per_second_cents'];
        return (int) max($c['min_charge_cents'], $cents);
    }
}
