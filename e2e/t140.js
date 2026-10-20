// FTT 1.4.0 browser tests (local, port 8088): renamed menu, Help > All options, every block's sidebar, badge compat.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt140-shots/';fs.mkdirSync(SH,{recursive:true});
const WPD='/workspace/wp', MU=WPD+'/wp-content/mu-plugins/';
const wp=(a)=>cp.execSync('cd '+WPD+' && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,400):''));};
// Simulated FilmTransfer.ai setup: ft-core's filmcalc_format_badge filter (same signature and markup) plus a compat
// shim that runs ft-core's format-change script against the toolkit calculator (handle ftt-calculator, root [data-ftt-calc]).
const SIM=`<?php
add_filter('filmcalc_format_badge', function($h,$cur){ $u=['8'=>'https://example.test/8mm.png','s8'=>'https://example.test/super-8.png','16'=>'https://example.test/16mm.png','35-4'=>'','35-3'=>'','35-2'=>''];
  $c=$u[$cur]??''; static $js=false; if(!$js){ $js=true; foreach(['film-calculator','ftt-calculator'] as $hd) wp_add_inline_script($hd,"document.addEventListener('change',function(e){var s=e.target.closest&&e.target.closest('select[data-fc=\\"format\\"]');if(!s)return;var r=s.closest('[data-filmcalc],[data-ftt-calc]'),b=r&&r.querySelector('[data-ft-badges]');if(!b)return;var m=JSON.parse(b.getAttribute('data-ft-badges')),u=m[s.value]||'',i=b.querySelector('img'),f=b.querySelector('.ft-fmt-fallback');if(u){i.src=u;i.hidden=false;f.hidden=true;}else{i.hidden=true;i.removeAttribute('src');f.hidden=false;}});"); }
  return $h.'<span class="ft-calc-badge" data-ft-badges="'.esc_attr(wp_json_encode($u)).'"><img class="ft-fmt-badge" alt="" width="24" height="24"'.($c?' src="'.esc_url($c).'"':' hidden').'><span class="ft-fmt-badge ft-fmt-fallback" aria-hidden="true"'.($c?' hidden':'').'></span></span>'; },10,2);
`;
(async()=>{
const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:900},deviceScaleFactor:1.5});const p=await ctx.newPage();
const sideShot=async(f)=>{const bb=await p.locator('.interface-complementary-area, .block-editor-block-inspector').first().boundingBox();await p.screenshot({path:SH+f,clip:{x:Math.max(0,bb.x-8),y:Math.max(0,bb.y-4),width:bb.width+16,height:Math.min(900,bb.y+bb.height)-Math.max(0,bb.y-4)}});};
const errs=[];p.on('pageerror',e=>errs.push(p.url()+' '+e.message));
const badge=MU+'zz-test-badge.php', badgeAside='/tmp/zz-test-badge.php.t140';
if(fs.existsSync(badge))fs.renameSync(badge,badgeAside);
try{
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();

// ---- renamed menu + plugins list
await p.goto(B+'/wp-admin/admin.php?page=ftt');
const mname=(await p.textContent('#toplevel_page_ftt .wp-menu-name')).trim();
ok('admin menu item reads "FTT"',mname==='FTT',mname);
ok('menu slug unchanged (admin.php?page=ftt)',(await p.getAttribute('#toplevel_page_ftt > a','href')).includes('page=ftt'));
const subs=await p.$$eval('#toplevel_page_ftt .wp-submenu li a',xs=>xs.map(x=>x.textContent.trim()));
ok('submenu has Help and Settings',subs.some(s=>/Help/.test(s))&&subs.some(s=>/Settings/.test(s)),subs.join('|'));
ok('dashboard heading is FTT',/^FTT\s*v1\.4\.0/.test((await p.textContent('.wrap h1')).trim()),await p.textContent('.wrap h1'));
ok('no old name on the dashboard',!(await p.content()).includes('Film Transfer Toolkit'));
await p.locator('#adminmenuwrap').screenshot({path:SH+'renamed-admin-menu.png'});
await p.screenshot({path:SH+'renamed-dashboard.png'});
await p.goto(B+'/wp-admin/plugins.php');
const prow=p.locator('tr[data-plugin="film-transfer-toolkit/film-transfer-toolkit.php"]').first();
ok('plugins list: same plugin file, name FTT (WordPress derives the row data-slug from the name: now '+await prow.getAttribute('data-slug')+')',(await prow.locator('.plugin-title strong').textContent()).trim()==='FTT');
await prow.screenshot({path:SH+'renamed-plugins-row.png'});

// ---- Help > All options
await p.goto(B+'/wp-admin/admin.php?page=ftt-help&tool=options');
ok('All options tab active',(await p.textContent('.nav-tab-active')).trim()==='All options');
const reg=JSON.parse(wp(`eval 'echo wp_json_encode(array_map(function($e){return array("block"=>$e["block"],"atts"=>wp_list_pluck($e["atts"],"name"),"labels"=>array_map(array("FTT_Registry","att_label"),$e["atts"]),"full"=>FTT_Registry::full_example($e));},FTT_Registry::embeds()));'`));
for(const [tag,e] of Object.entries(reg)){
  const sec=p.locator('#ftt-opt-'+tag);
  ok(`All options: section for [${tag}]`,await sec.count()===1);
  const rows=await sec.locator('tr[data-att]').evaluateAll(xs=>xs.map(x=>x.getAttribute('data-att')));
  ok(`All options: [${tag}] lists every option (${e.atts.length})`,JSON.stringify(rows)===JSON.stringify(e.atts),rows.join(','));
  if(e.atts.length) ok(`All options: [${tag}] copy-ready every-option example`,(await sec.locator('.ftt-ex-all code').textContent()).trim()===e.full);
  ok(`All options: [${tag}] block markup example`,(await sec.locator('.ftt-ex-block code').textContent()).includes('wp:'+e.block));
}
await p.screenshot({path:SH+'help-all-options.png'});
await p.locator('#ftt-opt-ftt_calculator').screenshot({path:SH+'help-all-options-calculator.png'});
await p.locator('#ftt-opt-ftt_storage_calculator').screenshot({path:SH+'help-all-options-storage.png'});
await p.locator('#ftt-opt-ftt_clip_selector').screenshot({path:SH+'help-all-options-clip-selector.png'});
// copy button copies the generated example
await ctx.grantPermissions(['clipboard-read','clipboard-write'],{origin:B});
const cbtn=p.locator('#ftt-opt-ftt_storage_calculator .ftt-ex-all button').first();
if(await cbtn.count()){await cbtn.click();await p.waitForTimeout(300);const clip=await p.evaluate(()=>navigator.clipboard.readText().catch(()=>''));ok('Copy puts the every-option example on the clipboard',clip===reg.ftt_storage_calculator.full,clip);}
else ok('Copy button next to every-option example',false);
await p.goto(B+'/wp-admin/admin.php?page=ftt-help&tool=calculator');
ok('per-tool help: generated table with sidebar labels',(await p.locator('table[data-ftt-atts="ftt_calculator"] tr[data-att="meters"]').textContent()).includes('Show Length (meters)'));
await p.locator('table[data-ftt-atts="ftt_calculator"]').screenshot({path:SH+'help-calculator-options-table.png'});

// ---- block editor: every block's sidebar shows every option
await p.goto(B+'/wp-admin/post-new.php?post_type=page');
await p.waitForFunction(()=>window.wp&&wp.data&&wp.data.select('core/block-editor')&&window.FTT_BLOCKS,null,{timeout:30000});
await p.waitForTimeout(1500);
for(const sel of ['.components-modal__header button[aria-label="Close"]','.components-modal__header button']){if(await p.locator(sel).count()){try{await p.locator(sel).first().click({timeout:1500});}catch(e){}}}
const cat=await p.evaluate(()=>wp.blocks.getCategories().find(c=>c.slug==='film-transfer-toolkit'));
ok('inserter category: slug film-transfer-toolkit, title FTT',cat&&cat.title==='FTT',JSON.stringify(cat));
const titles=await p.evaluate(()=>wp.blocks.getBlockTypes().filter(t=>t.category==='film-transfer-toolkit').map(t=>t.title));
ok('no block title shows the old name',titles.length>=9&&!titles.some(t=>/Film Transfer Toolkit/i.test(t)),titles.join('|'));
// inserter search by new and old name
await p.click('button.editor-document-tools__inserter-toggle, button[aria-label="Block Inserter"], button[aria-label="Toggle block inserter"]');
await p.waitForTimeout(800);await p.keyboard.type('FTT');await p.waitForTimeout(1200);
let items=await p.$$eval('.block-editor-block-types-list__item',xs=>xs.map(x=>x.textContent.trim()));
ok('inserter search "FTT" finds the tools',items.some(t=>t.includes('Film calculator'))&&items.some(t=>t.includes('Storage calculator')),items.join('|'));
await p.screenshot({path:SH+'block-inserter-ftt.png'});
await p.fill('.block-editor-inserter__search input, input[type=search]','film transfer toolkit');await p.waitForTimeout(1200);
items=await p.$$eval('.block-editor-block-types-list__item',xs=>xs.map(x=>x.textContent.trim()));
ok('inserter search with the old name still finds the tools',items.some(t=>t.includes('Film calculator')),items.join('|'));
await p.locator('.block-editor-block-types-list__item:has-text("Film calculator")').first().click();await p.waitForTimeout(1200);
if(!(await p.locator('.block-editor-block-inspector').count())){try{await p.click('button[aria-label="Settings"]');}catch(e){}await p.waitForTimeout(500);}
const insp=p.locator('.block-editor-block-inspector');
const fmt=insp.locator('.ftt-ctl--format');
ok('calculator sidebar: "Film format" label',(await fmt.locator('label').first().textContent()).trim()==='Film format');
const fh=(await fmt.locator('.components-base-control__help').textContent()).trim();
ok('calculator sidebar: help gives default and shortcode option',/Default: The default film format/.test(fh)&&/Shortcode option: format=/.test(fh),fh);
ok('calculator sidebar: "use the settings default" shows the current value',(await fmt.locator('option[value=""]').textContent()).includes('now:'));
await fmt.locator('select').selectOption('s8');await p.waitForTimeout(400);
const canvas=p.frameLocator('iframe[name="editor-canvas"]');
const scText=await canvas.locator('.ftt-block-ph__sc code').first().textContent().catch(async()=>await p.locator('.ftt-block-ph__sc code').first().textContent());
ok('sidebar change shows up in the shortcode line',scText.includes('format="s8"'),scText);
ok('Reset all to defaults button appears after a change',await insp.locator('button:has-text("Reset all to defaults")').count()===1);
await sideShot('block-sidebar-calculator.png');
await p.screenshot({path:SH+'block-editor-calculator.png'});
await insp.locator('button:has-text("Reset all to defaults")').click();await p.waitForTimeout(300);
ok('Reset restores the defaults',await p.evaluate(()=>{const b=wp.data.select('core/block-editor').getSelectedBlock();return b&&b.attributes.format==='';}));
// every block, every option
for(const [tag,e] of Object.entries(reg)){
  const r=await p.evaluate(async(name)=>{const be=wp.data.dispatch('core/block-editor');const blk=wp.blocks.createBlock(name);await be.insertBlocks(blk);be.selectBlock(blk.clientId);
    await new Promise(r=>setTimeout(r,500));const ins=document.querySelector('.block-editor-block-inspector');if(!ins)return {err:'no inspector'};
    return {ctl:[...ins.querySelectorAll('.ftt-ctl')].map(c=>({cls:[...c.classList].find(x=>x.indexOf('ftt-ctl--')===0).slice(9),label:(c.querySelector('label')||{}).textContent,help:(c.querySelector('.components-base-control__help')||{}).textContent||''})),
      defaults:Object.fromEntries(Object.entries(wp.blocks.getBlockType(name).attributes).map(([k,v])=>[k,v.default]))};},e.block);
  if(r.err){ok(`${e.block}: sidebar`,false,r.err);continue;}
  const names=r.ctl.map(c=>c.cls);
  ok(`${e.block}: sidebar has a control for every shortcode option (${e.atts.length})`,JSON.stringify(names)===JSON.stringify(e.atts),names.join(','));
  ok(`${e.block}: every control labelled in plain English`,r.ctl.every((c,i)=>c.label&&c.label.trim()===e.labels[i]&&c.label.trim()!==e.atts[i]),r.ctl.map(c=>c.label).join('|'));
  ok(`${e.block}: every control has help with default + shortcode option`,r.ctl.every(c=>/Default:/.test(c.help)&&/Shortcode option:/.test(c.help)),r.ctl.map(c=>c.help.slice(0,40)).join('|'));
  if(tag==='ftt_storage_calculator'){await sideShot('block-sidebar-storage.png');}
  if(tag==='ftt_clip_selector'){await sideShot('block-sidebar-clip-selector.png');}
}
const sbData=await p.evaluate(()=>JSON.stringify(window.FTT_BLOCKS.blocks.map(b=>[b.shortcode,b.atts.map(a=>[a.name,a['default']])])));
const regDef=JSON.parse(wp(`eval 'echo wp_json_encode(array_values(array_map(function($e){return array($e["shortcode"],array_map(function($a){return array($a["name"],(string)$a["default"]);},$e["atts"]));},FTT_Blocks::active_embeds())));'`));
ok('sidebar defaults = registry/shortcode defaults for every block',sbData===JSON.stringify(regDef));

// ---- FilmTransfer.ai badge (ft-core filter) with the toolkit calculator
fs.writeFileSync(MU+'zz-ftcore-badge-sim.php',SIM);
try{
  await p.goto(B+'/calc-toggles-test/');await p.waitForTimeout(800);
  const c=p.locator('[data-ftt-calc]').first();
  const img=c.locator('.ft-calc-badge img');
  ok('badge rendered inside the Film format label',await c.locator('.filmcalc__f-format .ft-calc-badge').count()===1);
  const s0=await img.getAttribute('src');
  await c.locator('select[data-fc="format"]').selectOption('s8');await p.waitForTimeout(300);
  const s1=await img.getAttribute('src');
  ok('changing the format swaps the badge (ft-core script still works)',s1==='https://example.test/super-8.png'&&s0!==s1,s0+' -> '+s1);
  await c.locator('select[data-fc="format"]').selectOption('35-4');await p.waitForTimeout(300);
  ok('format without a badge shows the fallback',await img.evaluate(x=>x.hidden)&&await c.locator('.ft-fmt-fallback').evaluate(x=>!x.hidden));
  await c.locator('select[data-fc="format"]').selectOption('16');
  await c.locator('.filmcalc__head').scrollIntoViewIfNeeded();
  await c.screenshot({path:SH+'ft-badge-compat-sim.png'});
  // calculator maths still right with the badge in place
  await c.locator('[data-fc="feet"]').fill('400');await c.locator('[data-fc="speed"]').selectOption('24');await p.waitForTimeout(300);
  ok('calculator still works with the badge (400 ft 16mm @24 = 11:06.67)',(await c.locator('[data-fc="time"]').inputValue()).includes('11:06.67'),await c.locator('[data-fc="time"]').inputValue());
}finally{fs.unlinkSync(MU+'zz-ftcore-badge-sim.php');}

ok('no JS errors',errs.length===0,errs.join(' | '));
}finally{ if(fs.existsSync(badgeAside))fs.renameSync(badgeAside,badge); await b.close(); }
console.log(out.join('\n'));const f=out.filter(x=>x.startsWith('FAIL')).length;console.log(`${out.length-f} passed, ${f} failed`);process.exit(f?1:0);
})();
