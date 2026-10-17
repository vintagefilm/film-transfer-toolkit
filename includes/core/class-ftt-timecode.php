<?php
if (!defined('ABSPATH')) exit;

/** SMPTE 12M drop-frame / non-drop timecode math. Mirrors FTTCore.timecode in core/ftt-core.js (ported from fw-clip-cart) exactly. */
class FTT_Timecode
{
    // key => [num, den, nominal base, drop frames per minute]
    const RATES = [
        '23.976'   => [24000, 1001, 24, 0],
        '24'       => [24, 1, 24, 0],
        '25'       => [25, 1, 25, 0],
        '29.97df'  => [30000, 1001, 30, 2],
        '29.97ndf' => [30000, 1001, 30, 0],
        '30'       => [30, 1, 30, 0],
        '59.94df'  => [60000, 1001, 60, 4],
        '60'       => [60, 1, 60, 0],
    ];
    const LABELS = ['23.976' => '23.976', '24' => '24', '25' => '25', '29.97df' => '29.97 DF', '29.97ndf' => '29.97 NDF', '30' => '30', '59.94df' => '59.94 DF', '60' => '60'];

    /** Normalise a rate key; returns null if unsupported. */
    public static function norm($key)
    {
        $key = strtolower(preg_replace('/\s+/', '', (string) $key));
        if ($key === '29.97') $key = '29.97ndf';
        if ($key === '59.94') $key = '59.94df';
        return isset(self::RATES[$key]) ? $key : null;
    }

    public static function info($key)
    {
        $k = self::norm($key);
        if ($k === null) return null;
        [$num, $den, $base, $drop] = self::RATES[$k];
        return ['key' => $k, 'num' => $num, 'den' => $den, 'base' => $base, 'drop' => $drop, 'label' => self::LABELS[$k], 'fps' => $num / $den];
    }

    public static function seconds_to_frames($sec, $key)
    {
        $i = self::info($key);
        return (int) floor(((float) $sec) * $i['num'] / $i['den'] + 1e-6);
    }

    public static function frames_to_seconds($f, $key)
    {
        $i = self::info($key);
        return $f * $i['den'] / $i['num'];
    }

    public static function frames_to_tc($f, $key)
    {
        $i = self::info($key); $base = $i['base']; $d = $i['drop'];
        $neg = $f < 0; $f = (int) abs(round($f));
        if ($d) {
            $per10 = $base * 600 - $d * 9; $perMin = $base * 60 - $d;
            $tens = intdiv($f, $per10); $rem = $f % $per10;
            $f += $d * 9 * $tens + ($rem > $d ? $d * intdiv($rem - $d, $perMin) : 0);
        }
        $ff = $f % $base; $s = intdiv($f, $base);
        return ($neg ? '-' : '') . sprintf('%02d:%02d:%02d%s%02d', intdiv($s, 3600), intdiv($s, 60) % 60, $s % 60, $d ? ';' : ':', $ff);
    }

    /** Returns int frames, or WP_Error-free null on invalid input. */
    public static function tc_to_frames($tc, $key)
    {
        $i = self::info($key);
        if (!$i || !preg_match('/^(-)?(\d+):(\d\d):(\d\d)[:;.](\d\d)$/', trim((string) $tc), $m)) return null;
        $hh = (int) $m[2]; $mm = (int) $m[3]; $ss = (int) $m[4]; $ff = (int) $m[5];
        if ($mm > 59 || $ss > 59 || $ff >= $i['base']) return null;
        if ($i['drop'] && $ss === 0 && $mm % 10 !== 0 && $ff < $i['drop']) return null;
        $totalMin = $hh * 60 + $mm;
        $f = ($hh * 3600 + $mm * 60 + $ss) * $i['base'] + $ff - $i['drop'] * ($totalMin - intdiv($totalMin, 10));
        return $m[1] ? -$f : $f;
    }
}
