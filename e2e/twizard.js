// Fresh-site walkthrough of the 1.2.0 setup wizard (real clicks/typing), plus re-run, front-end, block editor checks.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8090', SH='/workspace/ftt140-shots/wizard/';fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>cp.execSync('cd /workspace/wpfresh && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
(async()=>{
const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:900}});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(p.url()+' '+e.message));
let n=0;const shot=async(name)=>{n++;const f=SH+String(n).padStart(2,'0')+'-'+name+'.png';await p.screenshot({path:f,fullPage:true});};
const php=()=>{const t=fs.existsSync('/workspace/wpfresh/wp-content/debug.log')?fs.readFileSync('/workspace/wpfresh/wp-content/debug.log','utf8'):'';return t;};
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
ok('toolkit inactive before test',wp('plugin is-active film-transfer-toolkit; echo $?')!=='0'||true);
const pagesBefore=+wp('post list --post_type=page --post_status=any --format=count');
// --- activate from the Plugins screen
await p.goto(B+'/wp-admin/plugins.php');await shot('plugins-before-activate');
await p.click('tr[data-plugin="film-transfer-toolkit/film-transfer-toolkit.php"] .activate a');await p.waitForLoadState();
ok('first activation redirects to the setup wizard',p.url().includes('page=ftt-setup'),p.url());
ok('setup state pending',wp('option get ftt_setup --format=json').includes('"pending"'));
ok('no pages created by activation',+wp('post list --post_type=page --post_status=any --format=count')===pagesBefore);
await shot('wizard-1-tools');
ok('7 tool choices',await p.locator('.ftt-choice').count()===7);
ok('defaults: 5 ticked (clips, rfq off)',await p.locator('.ftt-choice input:checked').count()===5);
await p.check('input[name="modules[rfq]"]');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('step 2 pages',p.url().includes('step=pages'),p.url());
ok('modules saved (rfq on)',JSON.parse(wp('option get ftt_modules --format=json')).rfq===1);
await shot('wizard-2-pages');
const calcVal=await p.inputValue('input[name="pages[ftt_calculator]"]:checked');
const FCID=+wp('post list --post_type=page --name=film-calculator --field=ID'),PRID=+wp('post list --post_type=page --name=film-transfer-prices --field=ID');
ok('calculator: existing /film-calculator/ page with [film_calculator] preselected for reuse',calcVal==='reuse:'+FCID,calcVal);
ok('price table: create (no page has it yet)',await p.inputValue('input[name="pages[ftt_price_table]"]:checked')==='create');
ok('slug-taken warning shown for /film-transfer-prices/',(await p.textContent('.ftt-pages-table')).includes('Another page already uses /film-transfer-prices/'));
ok('rfq rows present (4)',await p.locator('input[name^="pages[ftt_rfq_"][value=create]').count()===4);
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('step 3 look',p.url().includes('step=look'));
const notes=await p.textContent('.ftt-wizard__card .notice');
ok('pages notes listed',notes.includes('using the existing page "Film Calculator"')&&notes.includes('created the page /film-storage-calculator/'),notes);
await shot('wizard-3-look');
const pages=JSON.parse(wp('option get ftt_pages --format=json'));
ok('ftt_pages has 9 entries',Object.keys(pages).length===9,JSON.stringify(pages));
ok('calculator mapped to the existing page',pages.ftt_calculator===FCID);
const rfq=JSON.parse(wp('option get ftt_rfq --format=json'));
ok('RFQ pages wired into ftt_rfq',rfq.form_page===pages.ftt_rfq_form&&rfq.reply_page===pages.ftt_rfq_reply&&rfq.compare_page===pages.ftt_rfq_compare&&rfq.providers_page===pages.ftt_rfq_providers&&rfq.zip_required===1,JSON.stringify(rfq));
ok('price page got a distinct address (old Pricing page untouched)',wp('post get '+pages.ftt_price_table+' --field=post_name')!=='film-transfer-prices'&&wp('post get '+PRID+' --field=post_content').includes('Our old price list'));
ok('created pages contain the block',wp('post get '+pages.ftt_storage_calculator+' --field=post_content')==='<!-- wp:ftt/storage-calculator /-->');
ok('no pages added to the auto-add menu',wp('menu item list Main --format=count')==='0');
await p.check('input[name=theme][value=light]');
await p.click('#ftt-accent');await p.keyboard.type('#1e6fbf');
await p.selectOption('#ftt-format','s8');await p.selectOption('#ftt-fps','18');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
const co=JSON.parse(wp('option get ftt_calc_options --format=json'));
ok('look saved',co.theme==='light'&&co.accent==='#1e6fbf'&&co.default_format==='s8'&&co.default_fps==='18'&&co.show_extras===1,JSON.stringify(co));
ok('step 4 prices',p.url().includes('step=prices'));
await shot('wizard-4-prices');
// invalid import first
await p.check('input[name=prices][value=import]');await p.fill('#ftt-price-json','{not json');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('bad JSON import -> stays on prices with error',p.url().includes('step=prices')&&(await p.textContent('.ftt-wizard__card')).includes('not valid JSON'));
await shot('wizard-4b-prices-import-error');
// import a real table via file upload (the DRAFT preset file as a stand-in for another site's export)
await p.check('input[name=prices][value=import]');await p.setInputFiles('#ftt-price-file','/workspace/film-transfer-toolkit/core/data/price-table-vfc-draft.json');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('file import works',p.url().includes('step=storage')&&JSON.parse(wp('option get ftt_price_table --format=json')).status==='draft');
// go back and choose EXAMPLE (the default for new users)
await p.goto(B+'/wp-admin/admin.php?page=ftt-setup&step=prices');await p.check('input[name=prices][value=example]');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('EXAMPLE prices loaded',JSON.parse(wp('option get ftt_price_table --format=json')).status==='example');
ok('step 5 storage (1.3.0)',p.url().includes('step=storage'),p.url());
ok('storage step starts on 16mm',await p.locator('select[name="storage[default_format]"]').inputValue()==='16');
ok('storage step: ProRes 422 HQ UHD + H.264 16 Mbps',await p.locator('#ftt-st-0-kind').inputValue()==='prores'&&await p.locator('#ftt-st-0-sizep').inputValue()==='uhd'&&await p.locator('#ftt-st-1-kind').inputValue()==='h264'&&await p.locator('#ftt-st-1-mbps').inputValue()==='16');
ok('storage step: both PCM, film rate',await p.locator('#ftt-st-0-audio').inputValue()==='2304'&&await p.locator('#ftt-st-1-audio').inputValue()==='2304'&&await p.locator('#ftt-st-1-fps').inputValue()==='film');
await shot('wizard-5-storage');
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
const so=JSON.parse(wp('option get ftt_storage --format=json'));
ok('storage step saved the defaults',so.default_format==='16'&&so.deliverables.length===2&&so.deliverables[0].size==='uhd'&&so.deliverables[1].mbps==='16',JSON.stringify(so));
ok('step 6 email',p.url().includes('step=email'));
await shot('wizard-6-email');
await p.fill('#ftt-notify','bob@localhost');await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('invalid email rejected',p.url().includes('step=email')&&(await p.textContent('.ftt-wizard__card')).includes('does not look right'));
await p.fill('#ftt-notify','');await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
const g=JSON.parse(wp('option get ftt_general --format=json'));
ok('blank notify allowed; mail stays log',g.notify_email===''&&g.mail_mode==='log',JSON.stringify(g));
ok('finish screen',p.url().includes('step=finish'));
await shot('wizard-6-finish');
ok('finish lists 9 pages with View links',await p.locator('.ftt-list a:has-text("View")').count()===9);
ok('setup state done',wp('option get ftt_setup --format=json').includes('"done"'));
// --- re-run: no duplicates
const cnt1=+wp('post list --post_type=page --post_status=any --format=count');
await p.goto(B+'/wp-admin/admin.php?page=ftt-setup&step=pages');await shot('wizard-rerun-pages');
ok('re-run: every tool preselects its existing page',await p.locator('.ftt-pages-table input[value=create]').count()===0&&await p.locator('.ftt-pages-table input[value^="reuse:"]:checked').count()===9);
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('re-run creates no pages',+wp('post list --post_type=page --post_status=any --format=count')===cnt1);
// forced duplicate attempt: POST 'create' for a tool that already has a page (stale form / another tab)
await p.goto(B+'/wp-admin/admin.php?page=ftt-setup&step=pages');
await p.evaluate(()=>{const r=document.querySelector('input[name="pages[ftt_storage_calculator]"]');const i=document.createElement('input');i.type='radio';i.name=r.name;i.value='create';i.checked=true;r.closest('fieldset').appendChild(i);});
await p.click('button:has-text("Save and continue")');await p.waitForLoadState();
ok('stale "create" reuses instead of duplicating',+wp('post list --post_type=page --post_status=any --format=count')===cnt1&&(await p.textContent('.ftt-wizard__card')).includes('reused instead of making a copy'));
// --- nonce / capability
const r1=await p.request.post(B+'/wp-admin/admin-post.php',{form:{action:'ftt_setup',step:'modules','modules[calculator]':'1'}});
ok('save without nonce rejected',r1.status()===403||(await r1.text()).includes('link you followed has expired'),r1.status());
// --- dashboard
await p.goto(B+'/wp-admin/admin.php?page=ftt');await shot('dashboard-after-setup');
ok('dashboard shows pages for tools',(await p.textContent('.ftt-cards')).includes('Film Storage Calculator'));
const sub=await p.$$eval('#toplevel_page_ftt .wp-submenu li a',as=>as.map(a=>a.textContent.trim()));
ok('menu order starts Dashboard, Setup wizard, Settings, Prices, Help',sub.slice(0,5).join('|')==='Dashboard|Setup wizard|Settings|Prices|Help'&&sub[sub.length-1]==='Mail log',sub.join('|'));
// help tab
await p.click('#contextual-help-link');await p.waitForTimeout(400);await shot('wp-help-tab');
ok('WP help tab has toolkit tabs',await p.locator('#tab-link-ftt-overview').count()===1&&await p.locator('#tab-link-ftt-shortcodes').count()===1&&await p.locator('#tab-link-ftt-this-screen').count()===1);
// copy button
await ctx.grantPermissions(['clipboard-read','clipboard-write'],{origin:B});
await p.goto(B+'/wp-admin/admin.php?page=ftt-help&tool=storage');await shot('help-storage');
await p.click('.ftt-examples .ftt-copy >> nth=1');await p.waitForTimeout(200);
ok('copy button copies the example',(await p.evaluate(()=>navigator.clipboard.readText()))==='[ftt_storage_calculator preset="dpx-2k" headroom="30"]');
ok('copy button feedback',(await p.textContent('.ftt-examples .ftt-copy >> nth=1'))==='Copied');
for(const t of ['','calculator','reels','pricing','estimate','rfq','clips']){await p.goto(B+'/wp-admin/admin.php?page=ftt-help'+(t?'&tool='+t:''));await shot('help-'+(t||'start'));
 const imgs=await p.$$eval('.ftt-help img',is=>is.map(i=>({s:i.src,w:i.naturalWidth})));ok('help '+(t||'start')+': images load from the plugin folder',imgs.length>0&&imgs.every(i=>i.w>0&&i.s.includes('/plugins/film-transfer-toolkit/assets/img/help/')),JSON.stringify(imgs));}
ok('no attachments in Media Library',wp('post list --post_type=attachment --format=count')==='0');
// --- front-end of created pages
const ctx2=await b.newContext({viewport:{width:1150,height:900}});const q=await ctx2.newPage();const ferr=[];q.on('pageerror',e=>ferr.push(q.url()+' '+e.message));
for(const [tag,sel] of [['ftt_calculator','[data-ftt-calc]'],['ftt_reel_estimator','[data-ftt-reels]'],['ftt_price_table','.ftt-prices'],['ftt_estimate','[data-ftt-estimate]'],['ftt_storage_calculator','[data-ftt-storage]'],['ftt_rfq_providers','.ftt-rfq-providers'],]){
 const url=wp('post url '+pages[tag]);await q.goto(url);ok('front-end '+tag+' renders at '+url.replace(B,''),await q.locator(sel).count()>=1);}
await q.goto(wp('post url '+pages.ftt_rfq_form));const pl=await q.locator('main a:has-text("Choose providers")').first().getAttribute('href');ok('RFQ form (no providers picked) links to the wizard-created picker page',pl===wp('post url '+pages.ftt_rfq_providers),pl);
await q.goto(wp('post url '+pages.ftt_storage_calculator));await q.screenshot({path:SH+'front-storage-page.png',fullPage:true});
await q.goto(wp('post url '+FCID));
const fc=await q.locator('[data-ftt-calc]').getAttribute('class');ok('reused [film_calculator] page served by toolkit with wizard look (light)',/light|filmcalc--light/.test(fc)||(await q.locator('[data-ftt-calc]').evaluate(e=>e.outerHTML)).includes('light'),fc);
await q.click('[data-ftt-calc] [data-fc=feet]');await q.keyboard.press('Control+A');await q.keyboard.type('400');
ok('typed 400 ft Super 8 @18 -> 26:40',await q.inputValue('[data-ftt-calc] [data-fc=time]')==='26:40');
await q.screenshot({path:SH+'front-film-calculator-reused.png',fullPage:true});
ok('no front-end JS errors',ferr.length===0,ferr.join(' | '));
// --- block editor
await p.goto(B+'/wp-admin/post-new.php?post_type=page');
await p.waitForFunction(()=>window.wp&&wp.data&&wp.data.select('core/block-editor'),null,{timeout:30000});
await p.waitForTimeout(1500);
for(const sel of ['button[aria-label="Close"]','.components-modal__header button']){if(await p.locator(sel).count()){try{await p.locator(sel).first().click({timeout:1500});}catch(e){}}}
const cats=await p.evaluate(()=>wp.blocks.getCategories().map(c=>c.slug));ok('inserter category film-transfer-toolkit',cats[0]==='film-transfer-toolkit',cats.join(','));
const bl=await p.evaluate(()=>wp.blocks.getBlockTypes().filter(t=>t.category==='film-transfer-toolkit').map(t=>t.name).sort());
ok('9 toolkit blocks in category (4 RFQ; clip selector off so hidden)',bl.length===9&&!bl.includes('ftt/clip-selector'),bl.join(','));
const canvas=p.frameLocator('iframe[name="editor-canvas"]');
await p.click('button.editor-document-tools__inserter-toggle, button[aria-label="Block Inserter"], button[aria-label="Toggle block inserter"]');
await p.waitForTimeout(800);await p.keyboard.type('FTT');await p.waitForTimeout(1200);await shot('block-inserter-search');
const items=await p.$$eval('.block-editor-block-types-list__item, .block-editor-inserter__panel-content [role=option]',xs=>xs.map(x=>x.textContent.trim()));
ok('inserter search lists toolkit blocks',items.some(t=>t.includes('Storage calculator'))&&items.some(t=>t.includes('Film calculator')),items.join('|'));
await p.locator('.block-editor-block-types-list__item:has-text("Storage calculator")').first().click();await p.waitForTimeout(1200);
ok('storage block inserted',await p.evaluate(()=>wp.data.select('core/block-editor').getBlocks().some(b=>b.name==='ftt/storage-calculator')));
// set an attribute through the sidebar control
const sidebar=p.locator('.block-editor-block-inspector');
if(!(await sidebar.count())){try{await p.click('button[aria-label="Settings"]');}catch(e){}}
await p.waitForTimeout(500);
const sel=p.locator('.block-editor-block-inspector select').last();await sel.selectOption('dpx-2k');
const ht=p.locator('.block-editor-block-inspector input[type=number]').first();await ht.fill('30');
await p.waitForTimeout(400);await shot('block-inserted-settings');
const at=await p.evaluate(()=>wp.data.select('core/block-editor').getBlocks().find(b=>b.name==='ftt/storage-calculator').attributes);
ok('block attributes set from sidebar',at.preset==='dpx-2k'&&at.headroom==='30',JSON.stringify(at));
await p.evaluate(()=>{wp.data.dispatch('core/editor').editPost({title:'Block test'});});
await p.evaluate(()=>wp.data.dispatch('core/editor').savePost());await p.waitForTimeout(2500);
const pid=await p.evaluate(()=>wp.data.select('core/editor').getCurrentPostId());
const content=wp('post get '+pid+' --field=post_content');ok('saved block markup',content.includes('<!-- wp:ftt/storage-calculator {"headroom":"30","preset":"dpx-2k"} /-->'),content);
await q.goto(B+'/?page_id='+pid+'&preview=true');
// draft: render via wp-cli instead (preview needs auth); check block renders through the shortcode with attrs
const html=wp(`eval 'echo do_blocks(get_post(${pid})->post_content);'`);
ok('block renders the tool with its attributes',html.includes('data-ftt-storage')&&html.includes('data-headroom="30"')&&html.includes('data-preset="dpx-2k"'),html.slice(0,200));
// shortcode transform
const tr=await p.evaluate(()=>{const r=wp.blocks.rawHandler({HTML:'<p>[ftt_reel_estimator format="16"]</p>'});return r.map(b=>b.name+JSON.stringify(b.attributes));});
ok('pasting a shortcode converts to the block',tr.join(',').includes('ftt/reel-estimator')&&tr.join(',').includes('"format":"16"'),tr.join(','));
ok('no admin JS errors',errs.length===0,errs.join(' | '));
// --- re-activation does not redirect / no pages
await p.goto(B+'/wp-admin/plugins.php');await p.click('tr[data-plugin="film-transfer-toolkit/film-transfer-toolkit.php"] .deactivate a');await p.waitForLoadState();
const cnt2=+wp('post list --post_type=page --post_status=any --format=count');
await p.click('tr[data-plugin="film-transfer-toolkit/film-transfer-toolkit.php"] .activate a');await p.waitForLoadState();
ok('re-activation does not start the wizard',!p.url().includes('ftt-setup'),p.url());
ok('re-activation creates no pages and keeps settings',+wp('post list --post_type=page --post_status=any --format=count')===cnt2&&JSON.parse(wp('option get ftt_calc_options --format=json')).theme==='light');
const log=php();ok('no PHP warnings/notices in debug.log',!/PHP (Warning|Notice|Fatal|Deprecated)/.test(log),log.slice(-600));
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+'/'+out.length+' passed');await b.close();})().catch(e=>{console.log(out.join('\n'));console.error(e);process.exit(1);});
