#!/bin/bash
# 1.5.10 -> 1.5.11 on the fresh site with VFC-shaped options (sign-in settings saved, a pending selection transient). Nothing may be lost.
W=/workspace/wpfresh; cd $W; P=0; F=0; ok(){ if [ "$2" = 0 ]; then P=$((P+1)); else F=$((F+1)); echo "FAIL $1"; fi; }
snap(){ wp eval 'global $wpdb; $o=[]; foreach($wpdb->get_results("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE (option_name LIKE \"ftt\\_%\" OR option_name LIKE \"filmcalc%\") AND option_name NOT LIKE \"%transient%\" ORDER BY option_name", ARRAY_A) as $r){ $o[$r["option_name"]]=["v"=>maybe_unserialize($r["option_value"]),"a"=>$r["autoload"]]; } echo json_encode($o);'; }
wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1; rm -rf wp-content/plugins/film-transfer-toolkit wp-content/mu-plugins/ftt-mock.php; bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
wp plugin install /workspace/ftt1510/film-transfer-toolkit-1.5.10.zip --activate >/dev/null 2>&1; wp ftt prices example >/dev/null 2>&1
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.10 ]; ok "start 1.5.10" $?
echo '{"default_product_id":15496,"default_fps":"23.976","auto_attach":"off","require_login":1,"login_page_id":15343,"register_page_id":15344,"after_login":"cart","heading":"Select Stock Footage","login_mode":"add_then_prompt"}' > /tmp/u1511.json
wp option update ftt_clip_settings --format=json < /tmp/u1511.json >/dev/null 2>&1
wp transient set ftt_clip_pending_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa '{"x":1}' 7200 >/dev/null 2>&1
snap > /tmp/u1511.pre; PG0=$(wp post list --post_type=page --format=count)
wp plugin install /workspace/ftt1511/film-transfer-toolkit-1.5.11.zip --force >/dev/null 2>&1
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.11 ] && [ "$(wp plugin get film-transfer-toolkit --field=status)" = active ]; ok "1.5.10 -> 1.5.11 active" $?
wp eval 'echo 1;' >/dev/null 2>&1; snap > /tmp/u1511.post
cat > /tmp/u1511cmp.php <<'PHP'
<?php
$a = json_decode( file_get_contents( '/tmp/u1511.pre' ), true ); $b = json_decode( file_get_contents( '/tmp/u1511.post' ), true );
$R = array( 'require_login', 'login_page_id', 'register_page_id', 'after_login', 'login_mode' ); $bad = array();
foreach ( $a as $n => $r ) { if ( ! isset( $b[ $n ] ) ) { $bad[] = "lost $n"; } elseif ( 'ftt_clip_settings' !== $n && $b[ $n ] != $r ) { $bad[] = "changed $n"; } }
$new = array_values( array_diff( array_keys( $b ), array_keys( $a ) ) ); if ( array( 'ftt_clip_retired_login' ) !== $new ) { $bad[] = 'new options: ' . implode( ',', $new ); }
if ( $b['ftt_clip_settings']['v'] !== array_diff_key( $a['ftt_clip_settings']['v'], array_flip( $R ) ) ) { $bad[] = 'settings: ' . json_encode( $b['ftt_clip_settings']['v'] ); }
if ( 'no' !== ( $b['ftt_clip_retired_login']['a'] ?? '' ) && 'off' !== ( $b['ftt_clip_retired_login']['a'] ?? '' ) ) { $bad[] = 'retired option autoload ' . ( $b['ftt_clip_retired_login']['a'] ?? '?' ); }
if ( ( $b['ftt_clip_retired_login']['v']['values'] ?? null ) != array_intersect_key( $a['ftt_clip_settings']['v'], array_flip( $R ) ) ) { $bad[] = 'retired values not kept'; }
global $wpdb; if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%ftt_clip_pending_%'" ) ) { $bad[] = 'pending transient left'; }
echo $bad ? 'BAD ' . implode( '; ', $bad ) : 'OK';
PHP
[ "$(wp eval-file /tmp/u1511cmp.php 2>&1 | tail -1)" = OK ]; ok "options: only the five retired keys leave ftt_clip_settings (kept in ftt_clip_retired_login); nothing else changes; pending transient cleared" $?
wp eval-file /tmp/u1511cmp.php 2>&1 | tail -1 | grep -v '^OK$'
[ "$(wp post list --post_type=page --format=count)" = "$PG0" ]; ok "page count unchanged" $?
wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1; wp plugin activate film-transfer-toolkit >/dev/null 2>&1; [ "$(snap | md5sum)" = "$(cat /tmp/u1511.post | md5sum)" ]; ok "deactivate/activate: options unchanged" $?
echo "$P passed, $F failed"
