// 1.5.0 admin: 4-item menu, a card per tool, tabs with Advanced, old URLs redirect (keeping query args), and saving one
// form never touches the other option (keys unchanged). Restores ftt_modules / ftt_general / ftt_calc_options.
const {chromium}=require('playwright');const cp=require('child_process');
const B='http://localhost:8088';
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const keep={}; for(const o of ['ftt_modules','ftt_general','ftt_calc_options']) keep[o]=wp(`option get ${o} --format=json`);
const restore=()=>{for(const o in keep){ if(keep[o]) cp.execSync(`cd /workspace/wp && wp option update ${o} --format=json`,{input:keep[o]}); else wp('option delete '+o);}};
(async()=>{const b=await chromium.launch();const errs=[];
try{
const p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();p.on('pageerror',e=>errs.push(e.message));
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
await p.goto(B+'/wp-admin/admin.php?page=ftt');
const menu=(await p.locator('#toplevel_page_ftt ul.wp-submenu li a').allTextContents()).map(s=>s.trim()).filter(Boolean);
ok('menu is Dashboard | Tools | Settings | Help',menu.join('|')==='Dashboard|Tools|Settings|Help',menu.join('|'));
ok('dashboard: 7 tool cards',await p.locator('.ftt-toolcard').count()===7,await p.locator('.ftt-toolcard').count());
ok('every card has on/off, one line, Add to a page, Help',await p.locator('.ftt-toolcard .ftt-pill').count()>=7 && await p.locator('.ftt-toolcard__line').count()===7 && await p.locator('.ftt-toolcard__add').count()===7 && await p.locator('.ftt-toolcard__links a:has-text("Help")').count()===7);
ok('one h1 on dashboard',await p.locator('#wpbody h1').count()===1);
const red=[['ftt-calculator','calculator'],['ftt-prices','prices'],['ftt-storage','storage'],['ftt-clips','clips'],['ftt-rfq','rfq'],['ftt-mail','email']];
for(const [o,t] of red){await p.goto(B+`/wp-admin/admin.php?page=${o}&ftt_msg=saved`);const u=new URL(p.url());
 ok(`old ${o} -> Settings tab ${t}`,u.searchParams.get('page')==='ftt-settings'&&u.searchParams.get('tab')===t&&u.searchParams.get('ftt_msg')==='saved',p.url());
 ok(`tab ${t}: active tab + one h1`,(await p.locator('.ftt-tabs .nav-tab-active').count())===1&&(await p.locator('#wpbody h1').count())===1);}
for(const t of ['email','calculator','prices','clips','rfq']){await p.goto(B+`/wp-admin/admin.php?page=ftt-settings&tab=${t}`);
 const adv=p.locator('details.ftt-advanced').first();ok(`tab ${t}: Advanced is folded`,await adv.count()>0 && !(await adv.evaluate(d=>d.open)));
 if(await adv.count()){await adv.locator(':scope > summary').click();ok(`tab ${t}: Advanced opens`,await adv.evaluate(d=>d.open));}}
// save Tools: ftt_general untouched
const g0=wp('option get ftt_general --format=json');
await p.goto(B+'/wp-admin/admin.php?page=ftt-tools');await p.uncheck('input[name="ftt_modules[reels]"]');await p.click('#submit');await p.waitForLoadState();
ok('Tools save turns reels off',wp(`eval '$m=get_option("ftt_modules"); echo (int)$m["reels"];'`)==='0');
ok('Tools save leaves ftt_general as it was',wp('option get ftt_general --format=json')===g0);
ok('Tools save stays on Tools',p.url().includes('page=ftt-tools'),p.url());
await p.check('input[name="ftt_modules[reels]"]');await p.click('#submit');await p.waitForLoadState();
// save Email: modules untouched, keys unchanged
const m0=wp('option get ftt_modules --format=json');
await p.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=email');await p.click('#submit');await p.waitForLoadState();
ok('Email save leaves ftt_modules as it was',wp('option get ftt_modules --format=json')===m0);
const gk=Object.keys(JSON.parse(wp('option get ftt_general --format=json')||'{}')).sort().join(',');
ok('ftt_general keys unchanged',gk===Object.keys(JSON.parse(g0||'{}')).sort().join(','),gk);
ok('Email save stays on Email tab',p.url().includes('tab=email'),p.url());
// calculator save keeps keys
const c0=Object.keys(JSON.parse(keep.ftt_calc_options||'{}')).sort().join(',');
await p.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=calculator');await p.click('form[action="options.php"] #submit');await p.waitForLoadState();
const c1=Object.keys(JSON.parse(wp('option get ftt_calc_options --format=json')||'{}')).sort().join(',');
ok('calculator save keeps its option keys',!c0||c0===c1,c0+' vs '+c1);
await p.goto(B+'/wp-admin/admin.php?page=ftt-setup');ok('setup wizard opens (hidden page, titled)',(await p.title()).indexOf('Error')<0 && (await p.title()).includes('FTT setup wizard'),await p.title());
ok('no page errors',errs.length===0,errs.join(' | '));
}catch(e){ok('run',false,e.stack)}finally{restore();await b.close();}
console.log(out.join('\n'));const f=out.filter(s=>s.startsWith('FAIL')).length;console.log(`t150: ${out.length-f}/${out.length}`);process.exit(f?1:0);})();
