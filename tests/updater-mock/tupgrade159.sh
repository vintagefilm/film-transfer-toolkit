#!/bin/bash
# Simulated update chain on the fresh site: 1.5.8 (zip) -> 1.5.9 (zip, deploy path) -> 1.5.10 dummy via the built-in updater (mock GitHub).
W=/workspace/wpfresh; M=/workspace/ftt159/mock; cd $W; P=0; F=0; ok(){ if [ "$2" = 0 ]; then P=$((P+1)); else F=$((F+1)); echo "FAIL $1"; fi; }
snap(){ wp eval 'global $wpdb; echo md5(json_encode($wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE \"ftt\\_%\" AND option_name NOT LIKE \"%transient%\" AND option_name<>\"ftt_db_version\" ORDER BY option_name", ARRAY_A)));'; }
rm -f wp-content/mu-plugins/ftt-mock.php; wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1; rm -rf wp-content/plugins/film-transfer-toolkit; bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
wp plugin install /workspace/ftt158/film-transfer-toolkit-1.5.8.zip --activate >/dev/null 2>&1; wp ftt prices example >/dev/null 2>&1
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.8 ]; ok "start 1.5.8" $?
wp option patch update ftt_clip_settings require_login 1 >/dev/null 2>&1; S0=$(snap); PG0=$(wp post list --post_type=page --format=count)
wp plugin install /workspace/ftt159/film-transfer-toolkit-1.5.9.zip --force >/dev/null 2>&1
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.9 ] && [ "$(wp plugin get film-transfer-toolkit --field=status)" = active ]; ok "1.5.8 -> 1.5.9 active" $?
[ "$(snap)" = "$S0" ]; ok "options unchanged 1.5.8 -> 1.5.9" $?
mkdir -p wp-content/mu-plugins; echo '<?php add_filter("ftt_updater_api_base", function(){ return "http://127.0.0.1:8099"; });' > wp-content/mu-plugins/ftt-mock.php
rm -f $M/state/pause.json $M/state/private; python3 $M/release.py v1.5.10 /workspace/ftt159/dummy-film-transfer-toolkit-1.5.10.zip --sha body
wp ftt-updater check >/dev/null 2>&1; wp plugin list --name=film-transfer-toolkit --fields=name,version,update,update_version 2>&1 | tail -1
[ "$(wp plugin list --name=film-transfer-toolkit --field=update_version)" = 1.5.10 ]; ok "1.5.10 offered in WP update list" $?
python3 $M/release.py v1.5.10 /workspace/ftt159/dummy-film-transfer-toolkit-1.5.10.zip --sha bad; wp ftt-updater check >/dev/null 2>&1
wp plugin update film-transfer-toolkit 2>&1 | grep -qi "sha256"; ok "bad sha256: update stopped with message" $?
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.9 ] && [ "$(wp plugin get film-transfer-toolkit --field=status)" = active ]; ok "bad sha256: still 1.5.9 active" $?
echo '{"pause":true}' > $M/state/pause.json; python3 $M/release.py v1.5.10 /workspace/ftt159/dummy-film-transfer-toolkit-1.5.10.zip; wp ftt-updater check >/dev/null 2>&1
[ "$(wp plugin list --name=film-transfer-toolkit --field=update)" = none ]; ok "paused: no update listed" $?
rm -f $M/state/pause.json; wp ftt-updater check >/dev/null 2>&1
wp plugin auto-updates enable film-transfer-toolkit >/dev/null 2>&1; [ "$(wp plugin auto-updates status film-transfer-toolkit --field=status)" = enabled ]; ok "auto-updates enabled" $?
# WordPress auto-update path (what cron does)
wp eval 'define("DOING_CRON",true); delete_option("auto_updater.lock"); include_once ABSPATH."wp-admin/includes/class-wp-upgrader.php"; include_once ABSPATH."wp-admin/includes/update.php"; wp_update_plugins(); $u=new WP_Automatic_Updater(); $u->run();' >/tmp/au.log 2>&1; echo "after auto: $(wp plugin get film-transfer-toolkit --field=version) $(wp plugin get film-transfer-toolkit --field=status)"
[ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.10 ] && [ "$(wp plugin get film-transfer-toolkit --field=status)" = active ]; ok "auto-update 1.5.9 -> 1.5.10 active" $?
[ -f wp-content/plugins/film-transfer-toolkit/film-transfer-toolkit.php ] && [ ! -d wp-content/plugins/film-transfer-toolkit-1.5.10 ]; ok "folder name kept" $?
[ "$(snap)" = "$S0" ]; ok "options unchanged through 1.5.10" $?
[ "$(wp post list --post_type=page --format=count)" = "$PG0" ]; ok "no pages added" $?
wp eval 'echo do_shortcode("[ftt_price_table]");' 2>/dev/null | grep -q 'ftt-table'; ok "price table renders after update" $?
# manual wp plugin update path: back to 1.5.9 zip, then update
wp plugin install /workspace/ftt159/film-transfer-toolkit-1.5.9.zip --force >/dev/null 2>&1; wp ftt-updater check >/dev/null 2>&1
wp plugin update film-transfer-toolkit >/tmp/mu.log 2>&1; [ "$(wp plugin get film-transfer-toolkit --field=version)" = 1.5.10 ]; ok "wp plugin update 1.5.9 -> 1.5.10" $?
wp ftt-updater check >/dev/null 2>&1; [ "$(wp plugin list --name=film-transfer-toolkit --field=update)" = none ]; ok "1.5.10 installed: nothing more offered" $?
rm -f wp-content/mu-plugins/ftt-mock.php $M/state/latest.json; wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1; rm -rf wp-content/plugins/film-transfer-toolkit; unzip -qo /workspace/ftt159/film-transfer-toolkit-1.5.9.zip -d wp-content/plugins; bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
echo "$P passed, $F failed"
