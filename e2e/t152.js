// 1.5.2 e2e: Prices admin (custom-unit add-on with description; services rename/reorder/add/remove) and the
// estimate builder (quantity input, "3 × USB drive @ $15.00", helper text, server total = browser total).
// Restores ftt_price_table and ftt_price_migrations. Screenshots in /workspace/ftt-shots/152/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/152/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const J=(o)=>JSON.parse(wp(`option get ${o} --format=json`)||'null');
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const keep={}; for(const o of ['ftt_price_table','ftt_price_migrations']) keep[o]=wp(`option get ${o} --format=json`);
const restore=()=>{for(const o in keep){ if(keep[o]) cp.execSync(`cd /workspace/wp && wp option update ${o} --format=json`,{input:keep[o]}); else wp('option delete '+o);}};
wp(`eval 'update_option(FTT_Price_Table::OPTION, FTT_Price_Table::preset("draft"), false);'`);
const t0=J('ftt_price_table');
wp('transient delete --all'); // local test site: reset the request rate limit between runs
(async()=>{const b=await chromium.launch();const errs=[];
try{
const p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();p.on('pageerror',e=>errs.push(e.message));
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
await p.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=prices');
const svcIds=async()=>p.locator('tbody[data-ftt-rows=services] tr input[name$="[id]"]').evaluateAll(a=>a.map(x=>x.value));
ok('Services table lists the 4 built-ins',(await svcIds()).join()==='hd,4k,4k_out,1080_out',(await svcIds()).join());
ok('existing service ids are read-only',await p.locator('tbody[data-ftt-rows=services] input[name="services[0][id]"][readonly]').count()===1);
// rename 4K output, move 1080p output above it, add a 2K scan
await p.fill('tbody[data-ftt-rows=services] tr[data-ftt-service="4k_out"] input[name$="[label]"]','4K output (UHD file)');
await p.fill('tbody[data-ftt-rows=services] tr[data-ftt-service="4k_out"] input[name$="[description]"]','A 3840x2160 MP4 file of every reel.');
await p.click('tbody[data-ftt-rows=services] tr[data-ftt-service="1080_out"] [data-ftt-up]');
ok('arrow moves 1080p output up',(await svcIds()).join()==='hd,4k,1080_out,4k_out',(await svcIds()).join());
await p.click('[data-ftt-add=services]');
const ns=p.locator('tbody[data-ftt-rows=services] tr').last();
await ns.locator('input[name$="[id]"]').fill('scan2k');await ns.locator('input[name$="[label]"]').fill('2K scan');
// custom-unit add-on with a description
await p.click('[data-ftt-add=addons]');
const r=p.locator('tbody[data-ftt-rows=addons] tr').last();globalThis.r=r;
await r.locator('input[name$="[id]"]').fill('usb32');await r.locator('input[name$="[label]"]').fill('USB drive');
await r.locator('select[name$="[unit]"]').selectOption('custom');await r.locator('input[name$="[unit_label]"]').fill('USB drive');
await r.locator('input[name$="[price]"]').fill('15');
await r.locator('input[name$="[description]"]').fill('32 GB USB 3.0 drive with your files as MP4. <b>Sturdy</b>');
await p.click('#submit');await p.waitForLoadState();
let t=J('ftt_price_table');
ok('save: notice',(await p.locator('.notice-success').count())>0,await p.textContent('#wpbody'));
ok('save: services order hd,4k,1080_out,4k_out,scan2k',t.services.map(s=>s.id).join()==='hd,4k,1080_out,4k_out,scan2k',t.services.map(s=>s.id).join());
const s4=t.services.find(s=>s.id==='4k_out');
ok('rename changed the label only (id kept) + description stored',s4&&s4.label==='4K output (UHD file)'&&s4.description==='A 3840x2160 MP4 file of every reel.',JSON.stringify(s4));
ok('rates untouched by rename/reorder/add',JSON.stringify(t.rates)===JSON.stringify(t0.rates));
const u=t.addons.find(a=>a.id==='usb32');
ok('custom add-on stored: unit custom, unit_label, $15 = 15000 mills, tags stripped from description',u&&u.unit==='custom'&&u.unit_label==='USB drive'&&u.price===15000&&u.description==='32 GB USB 3.0 drive with your files as MP4. Sturdy',JSON.stringify(u));
ok('other add-ons unchanged',JSON.stringify(t.addons.filter(a=>a.id!=='usb32'))===JSON.stringify(t0.addons));
ok('not-offered-yet note names 2K scan',(await p.textContent('#wpbody')).includes('Not offered yet (no rates): 2K scan'));
await p.locator('#ftt-services').scrollIntoViewIfNeeded();
await p.screenshot({path:SH+'01-prices-services-editor.png',clip:await (async()=>{const a=await p.locator('#ftt-services').boundingBox();const z=await p.locator('[data-ftt-add=services]').boundingBox();return {x:0,y:a.y-10,width:1280,height:z.y+z.height-a.y+70};})()});
await r.scrollIntoViewIfNeeded(); await p.locator('tbody[data-ftt-rows=addons]').locator('xpath=ancestor::table').screenshot({path:SH+'02-prices-addon-editor-custom-unit.png'});
// validation: custom without a unit name
await p.click('[data-ftt-add=addons]');const r2=p.locator('tbody[data-ftt-rows=addons] tr').last();
await r2.locator('input[name$="[id]"]').fill('nounit');await r2.locator('select[name$="[unit]"]').selectOption('custom');await r2.locator('input[name$="[price]"]').fill('3');
ok('unit name box appears only for per custom unit, and is required',await r2.locator('.ftt-addon-unit').isVisible()&&await r2.locator('.ftt-addon-unit').evaluate(e=>e.required)&&!(await p.locator('tbody[data-ftt-rows=addons] tr').first().locator('.ftt-addon-unit').isVisible()));
ok('browser blocks saving a custom add-on with no unit name',!(await r2.locator('.ftt-addon-unit').evaluate(e=>e.form.checkValidity())));
await r2.locator('.ftt-addon-unit').evaluate(e=>{e.required=false;}); // prove the server refuses it too
await p.click('#submit');await p.waitForLoadState();
ok('custom add-on without unit name refused, nothing saved',(await p.textContent('.notice-error')).includes('type the unit name')&&JSON.stringify(J('ftt_price_table'))===JSON.stringify(t));
// estimate builder
await p.goto(B+'/ftt-estimate/');await p.waitForSelector('[data-ftt-qty=usb32]');
const svc=await p.locator('.ftt-line select').nth(1).evaluate(s=>[...s.options].map(o=>o.textContent));
ok('builder service list follows the admin order and new label',svc.join('|')==='HD 1080p scan|4K scan|1080p output|4K output (UHD file)',svc.join('|'));
const ql=await p.textContent('label:has([data-ftt-qty=usb32])');
ok('quantity input labelled with the unit name + price',ql.includes('USB drive – $15.00 per USB drive. How many?'),ql);
ok('add-on description shown as helper text',ql.includes('32 GB USB 3.0 drive with your files as MP4. Sturdy'));
await p.fill('[data-ftt-qty=usb32]','3');
const sum=await p.textContent('.ftt-est__totals');
ok('estimate reads "USB drive: 3 × USB drive @ $15.00" = $45.00',sum.includes('USB drive: 3 × USB drive @ $15.00$45.00'),sum);
await p.locator('.ftt-line select').nth(1).selectOption('4k_out');
ok('service description shown under the Service choice',(await p.textContent('.ftt-line')).includes('A 3840x2160 MP4 file of every reel.'));
ok('custom-unit add-on is not offered per reel',!(await p.textContent('.ftt-line')).includes('USB drive ($15.00)'));
await p.evaluate(()=>{const a=document.getElementById('wpadminbar');if(a)a.style.display='none';});
await p.locator('.ftt-est').first().screenshot({path:SH+'03-estimate-builder-custom-unit.png'});
const total=await p.textContent('.ftt-est__grand td');
await p.fill('[data-ftt-qty=usb32]','1000');
ok('qty 1000 -> plain error next to the order add-ons',(await p.textContent('[data-line-err="-1"]')).includes('whole number from 1 to 999'));
await p.fill('[data-ftt-qty=usb32]','0');ok('qty 0 = not wanted, no row',!(await p.textContent('.ftt-est__totals')).includes('USB drive:'));
await p.fill('[data-ftt-qty=usb32]','3');
await p.fill('[data-e=form] input[name=name]','Qty Tester');await p.fill('[data-e=form] input[name=email]','qty@example.com');await p.check('[data-e=form] input[name=consent]');
await p.click('[data-e=send]');
await p.waitForFunction(()=>/E-\d+/.test(document.querySelector('[data-e=msg]').textContent)||/wrong|Could not/.test(document.querySelector('[data-e=msg]').textContent),null,{timeout:15000});
const msg=await p.textContent('[data-e=msg]');
ok('request accepted at the browser total (server re-pricing agrees)',msg.includes(total)&&!msg.includes('updated'),msg+' / '+total);
const ref=(msg.match(/E-(\d+)/)||[])[1];
const q=JSON.parse(wp(`eval 'echo wp_json_encode(get_post_meta(${ref},"_ftt_quote",true) ?: get_post_meta(${ref},"_ftt_meta",true));'`)||'null');
const sumtxt=wp(`eval 'echo FTT_Estimate::text_summary(${ref});'`);
ok('stored request/email text has "USB drive: 3 × USB drive @ $15.00 $45.00"',sumtxt.includes('USB drive: 3 × USB drive @ $15.00 $45.00'),sumtxt);
// remove a rated service: its rates go with it
await p.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=prices');
await p.click('tbody[data-ftt-rows=services] tr[data-ftt-service="1080_out"] [data-ftt-remove]');
await p.click('tbody[data-ftt-rows=services] tr[data-ftt-service="scan2k"] [data-ftt-remove]');
await p.click('#submit');await p.waitForLoadState();
t=J('ftt_price_table');
ok('remove: 1080p output and 2K scan gone, notice says rates were removed',t.services.map(s=>s.id).join()==='hd,4k,4k_out'&&(await p.textContent('#wpbody')).includes('Rates for the removed service(s) 1080_out were removed too.'),t.services.map(s=>s.id).join());
ok('remove: only 1080_out rates dropped',JSON.stringify(t.rates)===JSON.stringify(t0.rates.filter(x=>x.service!=='1080_out')));
wp(`post delete ${ref} --force`);
}catch(e){ok('no exception',false,e.stack);}finally{restore();await b.close();}
ok('no JS errors',!errs.length,errs.join('; '));
console.log(out.join('\n'));console.log('t152: '+out.filter(x=>x.startsWith('PASS')).length+' passed, '+out.filter(x=>x.startsWith('FAIL')).length+' failed');
})();
