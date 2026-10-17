<?php
if (!defined('ABSPATH')) exit;

/**
 * Cut list / ffmpeg output for clip lines (prototype of Phase 3 fulfilment).
 * Timecodes and seconds come from FTT_Timecode, the same math as the widget and server quote.
 *   wp ftt clip-cutlist <order_id> [--format=csv|ffmpeg]
 */
class FTT_Clip_Cutlist
{
    /** @param array $clips list of fw_clip arrays (re-derived with FTT_Clip::from_stored) */
    public static function rows(array $clips)
    {
        $rows = [];
        foreach ($clips as $c) {
            if (!is_array($c)) continue; // skip WP_Error / invalid
            $fps = $c['fps']; $off = (int) ($c['tc_offset_frames'] ?? 0);
            $rows[] = [
                'clip_label'  => $c['clip_label'],
                'clip_name'   => $c['clip_name'],
                'clip_id'     => $c['clip_id'],
                'fps'         => FTT_Timecode::LABELS[$fps],
                'in_tc'       => FTT_Timecode::frames_to_tc($c['in_frame'] + $off, $fps),
                'out_tc'      => FTT_Timecode::frames_to_tc($c['out_frame'] + $off, $fps),
                'in_frame'    => $c['in_frame'],
                'out_frame'   => $c['out_frame'],
                'frames'      => $c['out_frame'] - $c['in_frame'],
                'in_sec'      => sprintf('%.6f', FTT_Timecode::frames_to_seconds($c['in_frame'], $fps)),
                'out_sec'     => sprintf('%.6f', FTT_Timecode::frames_to_seconds($c['out_frame'], $fps)),
                'duration_s'  => sprintf('%.6f', FTT_Timecode::frames_to_seconds($c['out_frame'] - $c['in_frame'], $fps)),
            ];
        }
        return $rows;
    }

    public static function csv(array $rows)
    {
        $fh = fopen('php://temp', 'w+');
        if ($rows) fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($fh, $r);
        rewind($fh); $out = stream_get_contents($fh); fclose($fh);
        return $out;
    }

    /** Frame-accurate re-encode cuts from the master file ($SRC). Out frame is exclusive. */
    public static function ffmpeg(array $rows)
    {
        $lines = ['#!/bin/sh', '# fw-clip-cart cut list. Set SRC to the master file for this story.', 'SRC="${SRC:-master.mov}"'];
        foreach ($rows as $r) {
            $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $r['clip_label'] . '_' . $r['clip_name']);
            $lines[] = sprintf('# %s "%s"  %s -> %s (%s fps, %d frames)', $r['clip_label'], $r['clip_name'], $r['in_tc'], $r['out_tc'], $r['fps'], $r['frames']);
            $lines[] = sprintf('ffmpeg -n -i "$SRC" -ss %s -t %s -frames:v %d -c:v prores_ks -profile:v 3 -c:a pcm_s16le "%s.mov"', $r['in_sec'], $r['duration_s'], $r['frames'], $safe);
        }
        return implode("\n", $lines) . "\n";
    }

    public static function clips_for_order($order_id)
    {
        global $wpdb;
        $t = $wpdb->prefix . 'fct_order_items';
        $items = $wpdb->get_results($wpdb->prepare("SELECT object_id, other_info FROM {$t} WHERE order_id = %d", $order_id), ARRAY_A);
        $clips = [];
        foreach ($items as $it) {
            if (!FTT_Clip::is_clip_id((string) $it['object_id'])) continue;
            $c = FTT_Clip::from_stored($it['other_info']);
            if (!is_wp_error($c)) $clips[] = $c;
        }
        return $clips;
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ftt clip-cutlist', function ($args, $assoc) {
        $rows = FTT_Clip_Cutlist::rows(FTT_Clip_Cutlist::clips_for_order((int) ($args[0] ?? 0)));
        echo (($assoc['format'] ?? 'csv') === 'ffmpeg') ? FTT_Clip_Cutlist::ffmpeg($rows) : FTT_Clip_Cutlist::csv($rows);
    });
}
