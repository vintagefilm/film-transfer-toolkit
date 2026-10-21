#!/bin/bash
# Live-like 1.5.1 -> 1.5.2: real 1.5.1 zip, an owner-edited FINAL price table stored, then swap to the 1.5.2 zip.
set -e; cd /workspace/wpfresh; P=wp-content/plugins
restart(){ pkill -f "php -S localhost:8090" || true; sleep 1; (nohup php -S localhost:8090 >/tmp/php8090.log 2>&1 &); sleep 1; }
bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
rm -rf $P/film-transfer-toolkit $P/film-calculator
unzip -qo /workspace/ftt151-out/film-transfer-toolkit-1.5.1.zip -d $P; unzip -qo /workspace/ftt130-out/film-calculator-1.2.0.zip -d $P
restart
wp plugin activate film-transfer-toolkit >/dev/null 2>&1
wp eval 'update_option("ftt_setup",array("state"=>"existing")); $t=FTT_Price_Table::preset("draft"); $t["status"]="final"; $t["fees"]["setup"]=12340; $t["rates"][0]["price"]=333; update_option(FTT_Price_Table::OPTION,$t,false);' >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Film Storage Calculator" --post_name=film-storage-calculator --post_content='<!-- wp:ftt/storage-calculator /-->' >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Calc block" --post_name=calc-block --post_content='<!-- wp:ftt/calculator {"format":"16"} /-->' >/dev/null 2>&1
wp post create --post_type=page --post_status=publish --post_title="Estimate" --post_name=estimate --post_content='<!-- wp:ftt/estimate /-->' >/dev/null 2>&1
Q='$r=array(array("lines"=>array(array("format"=>"16","service"=>"hd","reels"=>2,"size"=>"7","addons"=>array("sound"))),"order_addons"=>array("usb")),array("lines"=>array(array("format"=>"s8","service"=>"4k","feet"=>1234.4),array("format"=>"8","service"=>"hd","reels"=>3)),"order_addons"=>array("dvd","ownhd")),array("lines"=>array(array("format"=>"35","service"=>"4k","reels"=>7,"size"=>"10")),"order_addons"=>array())); $o=array(); foreach($r as $x){$o[]=FTT_Pricing::quote(FTT_Price_Table::get(),$x);} echo json_encode($o);'
OPTS='$o=array(); foreach(wp_load_alloptions() as $k=>$v) if(strpos($k,"ftt")===0||strpos($k,"filmcalc")===0) $o[$k]=maybe_unserialize($v); foreach(array("ftt_price_table","ftt_price_migrations") as $k){ $v=get_option($k,null); if(null!==$v) $o[$k]=$v; } ksort($o); echo json_encode($o);'
wp eval "$OPTS" > /tmp/upg-opts-before.json 2>/dev/null; wp eval "$Q" > /tmp/upg-quotes-before.json 2>/dev/null
wp post list --post_type=page --post_status=any --format=ids > /tmp/upg-pages-before.txt 2>/dev/null
rm -rf $P/film-transfer-toolkit; unzip -qo /workspace/ftt152-out/film-transfer-toolkit-1.5.2.zip -d $P
restart
curl -s -o /dev/null http://localhost:8090/ # first request after the update runs the one-time migration (on init)
wp plugin list --fields=name,status,version 2>/dev/null | grep -E "film"
wp eval "$OPTS" > /tmp/upg-opts-after.json 2>/dev/null; wp eval "$Q" > /tmp/upg-quotes-after.json 2>/dev/null
python3 - <<'PY'
import json
a=json.load(open('/tmp/upg-opts-before.json')); b=json.load(open('/tmp/upg-opts-after.json'))
ch=sorted(k for k in set(a)|set(b) if a.get(k)!=b.get(k)); print('CHANGED-OPTIONS', ch)
ta,tb=a['ftt_price_table'],b['ftt_price_table']
new=[s['id'] for s in tb['services'][len(ta['services']):]]
rest=all(ta[k]==tb[k] for k in ta if k!='services') and set(ta)==set(tb) and tb['services'][:len(ta['services'])]==ta['services']
print('TABLE', 'ONLY-SERVICES-APPENDED' if rest and new==['4k_out','1080_out'] else 'UNEXPECTED', new)
print('MIGRATION-FLAG', b.get('ftt_price_migrations'))
print('QUOTES', 'IDENTICAL' if open('/tmp/upg-quotes-before.json').read()==open('/tmp/upg-quotes-after.json').read() else 'DIFFERENT')
PY
