#!/bin/bash
# 1.4.1 regression: clip selector shortcode + block render when the module is on (with or without the FW Clip Cart
# prototype active); switched-off tools never show raw text: editors get a note, visitors nothing.
# Usage: bash tests/v141-tests.sh /path/to/wp   (needs wp-cli; fw-clip-cart prototype must be installed there)
WP=${1:-/workspace/wp}; cd "$WP" || exit 1
P=0; F=0; ok(){ if [ "$2" = 1 ]; then P=$((P+1)); else F=$((F+1)); echo "FAIL $1 :: $3"; fi; }
M0=$(wp option get ftt_modules --format=json 2>/dev/null); FW0=$(wp plugin get fw-clip-cart --field=status 2>/dev/null)
setmod(){ wp eval "\$m=get_option('ftt_modules'); \$m['$1']=$2; update_option('ftt_modules',\$m);" >/dev/null 2>&1; }
PID=$(wp post create --post_type=page --post_status=publish --post_title="v141 probe" --porcelain 2>/dev/null)
probe(){ # $1 user (0 = visitor)
 PU=$1 PP=$PID wp eval '
  $u=(int)getenv("PU"); wp_set_current_user($u); global $post; $post=get_post((int)getenv("PP")); setup_postdata($post);
  $r=WP_Block_Type_Registry::get_instance();
  $s=do_shortcode("[ftt_clip_selector clip_id=\"T1\" title=\"Probe\"]");
  $b=do_blocks("<!-- wp:ftt/clip-selector {\"clip_id\":\"T1\",\"title\":\"Probe\"} /-->");
  $c=apply_filters("the_content","<!-- wp:shortcode -->[ftt_clip_selector clip_id=\"T2\"]<!-- /wp:shortcode -->\n<!-- wp:ftt/storage-calculator /-->\n[ftt_storage_calculator]");
  echo json_encode(array("st"=>FTT_Plugin::$status["clips"],"sst"=>FTT_Plugin::$status["storage"],"sc"=>shortcode_exists("ftt_clip_selector"),"blk"=>$r->is_registered("ftt/clip-selector"),"s"=>$s,"b"=>$b,"c"=>$c));' 2>/dev/null; }
j(){ python3 -c "import json,sys;d=json.loads(sys.argv[1]);print(eval(sys.argv[2]))" "$1" "$2"; }

for FW in active inactive; do
 if [ $FW = active ]; then wp plugin activate fw-clip-cart >/dev/null 2>&1; else wp plugin deactivate fw-clip-cart >/dev/null 2>&1; fi
 setmod clips 1; setmod storage 1
 for U in 0 1; do R=$(probe $U)
  ok "[$FW fwcc, user $U] clips status on" "$(j "$R" "int(d['st']=='on')")" "$(j "$R" "d['st']")"
  ok "[$FW fwcc, user $U] shortcode + block registered" "$(j "$R" "int(d['sc'] and d['blk'])")"
  ok "[$FW fwcc, user $U] shortcode renders the widget" "$(j "$R" "int('class=\"fwcc' in d['s'] or 'fwcc ' in d['s'])")" "$(j "$R" "d['s'][:200]")"
  ok "[$FW fwcc, user $U] block renders the widget" "$(j "$R" "int('fwcc' in d['b'] and 'Probe' in d['b'])")"
  ok "[$FW fwcc, user $U] block == shortcode" "$(j "$R" "int(d['b']==d['s'])")"
  ok "[$FW fwcc, user $U] no raw shortcode in content" "$(j "$R" "int('[ftt_' not in d['c'])")"
 done
 setmod clips 0; setmod storage 0
 R=$(probe 0)
 ok "[$FW fwcc] off: shortcode + block still registered" "$(j "$R" "int(d['sc'] and d['blk'] and d['st']=='off')")"
 ok "[$FW fwcc] off, visitor: shortcode empty" "$(j "$R" "int(d['s']=='')")" "$(j "$R" "d['s'][:200]")"
 ok "[$FW fwcc] off, visitor: block empty" "$(j "$R" "int(d['b']=='')")"
 ok "[$FW fwcc] off, visitor: no raw text, no note in content" "$(j "$R" "int('[ftt_' not in d['c'] and 'ftt-off-note' not in d['c'] and 'fwcc' not in d['c'])")" "$(j "$R" "d['c'][:300]")"
 R=$(probe 1)
 ok "[$FW fwcc] off, editor: clip shortcode shows note" "$(j "$R" "int('data-ftt-off=\"ftt_clip_selector\"' in d['s'] and 'switched off' in d['s'])")" "$(j "$R" "d['s'][:200]")"
 ok "[$FW fwcc] off, editor: clip block shows the same note" "$(j "$R" "int(d['b']==d['s'])")"
 ok "[$FW fwcc] off, editor: storage shortcode + block show notes, no raw text" "$(j "$R" "int(d['c'].count('data-ftt-off=\"ftt_storage_calculator\"')==2 and '[ftt_' not in d['c'])")" "$(j "$R" "d['c'][:300]")"
done
# bench preview still works when off (editor on the test bench)
BID=$(wp eval 'echo FTT_Testbench::page_id();' 2>/dev/null)
if [ "${BID:-0}" != 0 ]; then
 R=$(PB=$BID wp eval 'wp_set_current_user(1); global $post; $post=get_post((int)getenv("PB")); setup_postdata($post); echo do_shortcode("[ftt_clip_selector clip_id=\"B\"]");' 2>/dev/null)
 ok "off: editor on test bench gets the preview-only selector" "$(echo "$R" | grep -q 'data-demo="1"' && echo 1)"
fi
wp post delete "$PID" --force >/dev/null 2>&1
wp option update ftt_modules "$M0" --format=json >/dev/null 2>&1
[ "$FW0" = active ] && wp plugin activate fw-clip-cart >/dev/null 2>&1 || wp plugin deactivate fw-clip-cart >/dev/null 2>&1
echo "v141-tests.sh: $P passed, $F failed"; [ $F = 0 ]
