#!/bin/bash
# Live-like: real 1.4.1 zip folders, film-calculator 1.2.0 present but INACTIVE, set up via CLI, then swap to 1.5.0 zip.
set -e; cd /workspace/wpfresh; P=wp-content/plugins
restart(){ pkill -f "php -S localhost:8090" || true; sleep 1; (nohup php -S localhost:8090 >/tmp/php8090.log 2>&1 &); sleep 1; }
bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
rm -rf $P/film-transfer-toolkit $P/film-calculator
unzip -qo /workspace/ftt141-out/film-transfer-toolkit-1.4.1.zip -d $P; unzip -qo /workspace/ftt130-out/film-calculator-1.2.0.zip -d $P
restart
wp plugin activate film-transfer-toolkit >/dev/null 2>&1
wp eval 'update_option("ftt_setup",array("state"=>"existing")); $o=get_option("ftt_calc_look",array()); ' >/dev/null 2>&1 || true
wp post create --post_type=page --post_status=publish --post_title="Film Storage Calculator" --post_name=film-storage-calculator --post_content='<!-- wp:ftt/storage-calculator /-->' >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Calc block" --post_name=calc-block --post_content='<!-- wp:ftt/calculator {"format":"16"} /-->' >/dev/null 2>&1
wp eval '$o=array(); foreach(wp_load_alloptions() as $k=>$v) if(strpos($k,"ftt")===0||strpos($k,"filmcalc")===0) $o[$k]=$v; ksort($o); echo json_encode($o);' > /tmp/upg-opts-before.json 2>/dev/null
wp post list --post_type=page --post_status=any --format=ids > /tmp/upg-pages-before.txt 2>/dev/null
wp plugin list --fields=name,status,version 2>/dev/null
rm -rf $P/film-transfer-toolkit; unzip -qo /workspace/ftt150-out/film-transfer-toolkit-1.5.0.zip -d $P
restart
wp plugin list --fields=name,status,version 2>/dev/null
wp eval '$o=array(); foreach(wp_load_alloptions() as $k=>$v) if(strpos($k,"ftt")===0||strpos($k,"filmcalc")===0) $o[$k]=$v; ksort($o); echo json_encode($o);' > /tmp/upg-opts-after.json 2>/dev/null
cmp /tmp/upg-opts-before.json /tmp/upg-opts-after.json && echo OPTIONS-IDENTICAL
