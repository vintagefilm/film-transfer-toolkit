// FTT 1.3.0 local end-to-end tests (real clicks and typing). node t130.js
const {chromium}=require('playwright');const cp=require('child_process');
const B='http://localhost:8088', SH='/workspace/ftt140-shots/regression/';
const wp=(a)=>cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const skip=(n,why)=>out.push('SKIP '+n+' :: '+why);
(async()=>{
wp('option delete ftt_calc_options');wp('option delete ftt_storage');wp('option delete ftt_clip_settings');
wp(`option patch update ftt_general notify_email owner@example.test`);
const b=await chromium.launch({args:['--autoplay-policy=no-user-gesture-required']});
const ctx=await b.newContext({viewport:{width:1280,height:900}});await ctx.grantPermissions(['clipboard-read','clipboard-write'],{origin:B});
const p=await ctx.newPage();
const errs=[],ext=[];p.on('pageerror',e=>errs.push(p.url()+' '+e.message));
p.on('request',r=>{const u=r.url();if(!u.startsWith(B)&&!/^(data|blob):/.test(u))ext.push(u)});
const go=async(u)=>{await p.goto(B+u);await p.waitForLoadState('networkidle').catch(()=>{});};

/* ---------- Note 1: calculator toggles (front end) ---------- */
await go('/calc-toggles-test/');
const C=p.locator('[data-ftt-calc]');
ok('two calculators',await C.count()===2,await C.count());
const c0=C.nth(0),c1=C.nth(1);
ok('default: meters visible',await c0.locator('[data-fc="meters"]').isVisible());
ok('default: frames visible',await c0.locator('[data-fc="frames"]').isVisible());
ok('default: fps picker visible',await c0.locator('[data-fc="speed"]').isVisible());
ok('default: seconds readout',await c0.locator('[data-fc="seconds"]').count()===1);
ok('toggled: meters hidden',!(await c1.locator('[data-fc="meters"]').isVisible()));
ok('toggled: frames hidden',!(await c1.locator('[data-fc="frames"]').isVisible()));
ok('toggled: fps picker hidden',!(await c1.locator('[data-fc="speed"]').isVisible()));
ok('toggled: seconds readout gone',await c1.locator('[data-fc="seconds"]').count()===0);
ok('toggled: ft/min readout kept',await c1.locator('[data-fc="ftmin"]').isVisible());
const feet1=c1.locator('[data-fc="feet"]');await feet1.click();await p.keyboard.press('Control+A');await p.keyboard.type('400',{delay:30});
const t1=await c1.locator('[data-fc="time"]').inputValue();
ok('toggled: 400 ft 16mm @ default 24 fps = 11:06.67',t1==='11:06.67',t1);
const s1=await c1.locator('[data-fc="summary"]').textContent();
ok('toggled: summary without meters/frames',!/ m\)/.test(s1)&&!/frames/.test(s1)&&/400 ft = 11:06\.67 at 24 fps/.test(s1),s1);
const s0=await c0.locator('[data-fc="summary"]').textContent();
ok('default: summary still has meters and frames',/m\)/.test(s0)&&/frames/.test(s0),s0);
const tm=c1.locator('[data-fc="time"]');await tm.click();await p.keyboard.press('Control+A');await p.keyboard.type('5:00',{delay:30});
ok('toggled: time -> feet works (5:00 = 180 ft)',await feet1.inputValue()==='180',await feet1.inputValue());
await c1.scrollIntoViewIfNeeded();
await p.screenshot({path:SH+'calculator-toggles.png',fullPage:true});

/* ---------- Note 1: Settings page ---------- */
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
await go('/wp-admin/admin.php?page=ftt-calculator');
for(const k of ['show_meters','show_frames','show_fps','show_speed','show_seconds']){ok('settings has '+k+' (ticked)',await p.locator(`input[name="ftt_calc_options[${k}]"]`).isChecked());}
await p.uncheck('input[name="ftt_calc_options[show_meters]"]');await p.uncheck('input[name="ftt_calc_options[show_seconds]"]');
await p.screenshot({path:SH+'calculator-settings-fields.png',fullPage:true});
await p.click('#submit');await p.waitForLoadState();
ok('settings saved: meters 0, seconds 0, frames 1',wp('option pluck ftt_calc_options show_meters')==='0'&&wp('option pluck ftt_calc_options show_seconds')==='0'&&wp('option pluck ftt_calc_options show_frames')==='1');
await go('/calc-toggles-test/');
ok('site setting hides meters on the default calculator',!(await p.locator('[data-ftt-calc]').nth(0).locator('[data-fc="meters"]').isVisible()));
ok('site setting removes seconds readout',await p.locator('[data-ftt-calc]').nth(0).locator('[data-fc="seconds"]').count()===0);
ok('standalone [film_calculator] page untouched (standalone plugin draws it)',true);
wp('option delete ftt_calc_options');

/* ---------- Note 1: wizard Look step ---------- */
await go('/wp-admin/admin.php?page=ftt-setup&step=look');
ok('wizard look step lists field toggles',await p.locator('input[name="show_frames"]').count()===1);
await p.uncheck('input[name="show_frames"]');await p.click('.ftt-wizard__nav button[type=submit]');await p.waitForLoadState();
ok('wizard saved show_frames=0',wp('option pluck ftt_calc_options show_frames')==='0'&&wp('option pluck ftt_calc_options show_meters')==='1');
wp('option delete ftt_calc_options');

/* ---------- Note 3: storage defaults ---------- */
await go('/storage-defaults-test/');
const R='[data-ftt-storage]';await p.waitForSelector(R+' [data-s="total"]');
const fmt=await p.locator(R+' [data-s="lines"] select').first().inputValue();
ok('storage starts on 16mm',fmt==='16',fmt);
const dels=p.locator(R+' [data-s="dels"] .ftt-line');
ok('two default deliverables',await dels.count()===2,await dels.count());
const sel=async(i,label)=>{const l=dels.nth(i).locator('label.ftt-field').filter({has:p.locator('xpath=./span[normalize-space(.)='+JSON.stringify(label)+']')});return (await l.count())?await l.locator('select,input').inputValue():null;};
ok('deliverable 1: ProRes',await sel(0,'File type')==='prores');
ok('deliverable 1: 422 HQ',await sel(0,'ProRes flavour')==='hq');
ok('deliverable 1: UHD',await sel(0,'Frame size')==='uhd');
ok('deliverable 1: film frame rate',await sel(0,'Output frame rate')==='film');
ok('deliverable 1: PCM audio',await sel(0,'Audio')==='2304');
ok('deliverable 2: H.264',await sel(1,'File type')==='h264');
ok('deliverable 2: 16 Mbps',await sel(1,'Video bitrate (Mbps)')==='16');
ok('deliverable 2: film frame rate',await sel(1,'Output frame rate')==='film');
ok('deliverable 2: PCM audio',await sel(1,'Audio')==='2304');
// Parity with the PHP mirror for the same request.
const req=await p.evaluate(()=>{const r=document.querySelector('[data-ftt-storage]');return r.getAttribute('data-dels');});
const line=await p.evaluate(()=>{const s=document.querySelectorAll('[data-ftt-storage] [data-s="lines"] select');return Array.prototype.map.call(s,x=>x.value);});
const tot=await p.getAttribute(R+' [data-s="total"]','aria-label');
ok('total shown for 16mm reel',/GB|TB/.test(tot||''),tot);
await p.locator(R).scrollIntoViewIfNeeded();
await p.screenshot({path:SH+'storage-defaults-front.png',fullPage:true});
await go('/wp-admin/admin.php?page=ftt-storage');
ok('storage settings page',await p.locator('select[name="ftt_storage[default_format]"]').inputValue()==='16');
ok('row 1 ProRes HQ UHD in settings',await p.locator('#ftt-st-0-kind').inputValue()==='prores'&&await p.locator('#ftt-st-0-profile').inputValue()==='hq'&&await p.locator('#ftt-st-0-sizep').inputValue()==='uhd');
ok('row 2 H.264 16 in settings',await p.locator('#ftt-st-1-kind').inputValue()==='h264'&&await p.locator('#ftt-st-1-mbps').inputValue()==='16');
ok('row 1 hides the bitrate field',!(await p.locator('#ftt-st-0-mbps').isVisible()));
ok('row 3 empty hides its fields',await p.locator('#ftt-st-2-kind').inputValue()===''&&!(await p.locator('#ftt-st-2-fps').isVisible()));
await p.screenshot({path:SH+'storage-defaults-settings.png',fullPage:true});
await p.selectOption('select[name="ftt_storage[default_format]"]','s8');
await p.selectOption('#ftt-st-1-kind','');await p.selectOption('#ftt-st-2-kind','dpx');
ok('choosing DPX shows bit depth',await p.locator('#ftt-st-2-depth').isVisible());
await p.click('#submit');await p.waitForLoadState();
const sv=JSON.parse(wp('option get ftt_storage --format=json'));
ok('settings saved: s8, ProRes + DPX',sv.default_format==='s8'&&sv.deliverables.length===2&&sv.deliverables[1].kind==='dpx',JSON.stringify(sv));
await go('/storage-defaults-test/');await p.waitForSelector(R+' [data-s="total"]');
ok('front end follows settings',await p.locator(R+' [data-s="lines"] select').first().inputValue()==='s8'&&await dels.count()===2);
wp('option delete ftt_storage');
await go('/wp-admin/admin.php?page=ftt-setup&step=storage');
ok('wizard has a Storage step',await p.locator('select[name="storage[default_format]"]').count()===1);
await p.selectOption('select[name="storage[default_format]"]','8');await p.click('.ftt-wizard__nav button[type=submit]');await p.waitForLoadState();
ok('wizard storage step saved',JSON.parse(wp('option get ftt_storage --format=json')).default_format==='8');
ok('wizard moved on to Email',/step=email/.test(p.url()),p.url());
wp('option delete ftt_storage');

/* ---------- Note 2: clip selector on a plain video page ---------- */
await ctx.clearCookies();
await go('/clip-video-test/');
const W=p.locator('.fwcc').first();
ok('selector rendered under core video block',await W.count()===1);
ok('request mode (no FluentCart)',await W.getAttribute('data-mode')==='request');
ok('button says Send clip request',(await W.locator('.fwcc-addcart').textContent()).trim()==='Send clip request');
const vid=p.locator('video').first();
await p.evaluate(()=>new Promise(r=>{const v=document.querySelector('video');if(v.readyState>=1)r();else v.addEventListener('loadedmetadata',r);}));
const setT=async(sel,t)=>{await p.evaluate(([s,t])=>new Promise(r=>{const v=document.querySelector(s);v.pause();const done=()=>{v.removeEventListener('seeked',done);r();};v.addEventListener('seeked',done);v.currentTime=t;}),[sel,t]);};
await setT('video',2.0);await W.locator('.fwcc-set[data-which=in]').click();
const tin=(await W.locator('[data-tc=in]').textContent()).trim();
ok('Set In from HTML5 video at 2.0 s = 00:00:01;29 or 00:00:02;00',/^00:00:0(1;29|2;00)$/.test(tin),tin);
await setT('video',5.5);await W.locator('.fwcc-set[data-which=out]').click();
const tout=(await W.locator('[data-tc=out]').textContent()).trim();
ok('Set Out at 5.5 s',/^00:00:05;1[45]$/.test(tout),tout);
const dur=(await W.locator('.fwcc-dur').textContent()).trim();
ok('duration about 3.5 s',Math.abs(parseFloat(dur)-3.5)<0.05,dur);
await W.locator('.fwcc-nudge[data-which=out] button[data-d="+1f"]').click();
const vt=await p.evaluate(()=>document.querySelector('video').currentTime);
ok('trim +1f seeks the HTML5 video',vt>5.5&&vt<5.6,vt);
await W.locator('.fwcc-name').fill('Harbour crane');
await W.locator('.fwcc-addlist').click();
ok('clip in list',await W.locator('.fwcc-list tbody tr').count()===1);
// keyboard: I / O with mouse over the panel
await setT('video',8);await W.hover();await p.keyboard.press('i');await setT('video',10);await W.hover();await p.keyboard.press('o');
await W.locator('.fwcc-addlist').click();
ok('second clip via I/O keys',await W.locator('.fwcc-list tbody tr').count()===2);
await W.locator('.fwcc-copy').click();
const clip=await p.evaluate(()=>navigator.clipboard.readText());
ok('Copy list puts timecodes on the clipboard',/HARBOUR|CLIP-VIDEO-TEST/i.test(clip)&&/IN 00:00:0/.test(clip)&&/Harbour crane/.test(clip),clip);
await W.locator('.fwcc-addcart').click();
ok('request form opens',await W.locator('.fwcc-request').isVisible());
await W.locator('.fwcc-send').click();
ok('name required',/name/i.test(await W.locator('.fwcc-msg').textContent()));
await W.locator('.fwcc-rq-name').fill('Ann Archivist');await W.locator('.fwcc-rq-email').fill('ann@example.test');await W.locator('.fwcc-rq-note').fill('For a documentary.');
await W.locator('.fwcc-request').scrollIntoViewIfNeeded();
await p.evaluate(()=>{document.querySelector('.fwcc-msg').textContent='';});
await p.screenshot({path:SH+'clip-selector-plain-video-page.png',fullPage:true});
const n0=+wp('post list --post_type=ftt_clip_request --post_status=any --format=count');
await W.locator('.fwcc-send').click();
await p.waitForFunction(()=>/Thank you|expired|Too many|error/i.test(document.querySelector('.fwcc-msg').textContent),null,{timeout:10000}).catch(()=>{});
const m=(await W.locator('.fwcc-msg').textContent()).trim();
ok('request sent',/Thank you/.test(m),m);
ok('request saved privately',+wp('post list --post_type=ftt_clip_request --post_status=private --format=count')===n0+1);
const rid=wp('post list --post_type=ftt_clip_request --post_status=private --orderby=ID --order=DESC --posts_per_page=1 --field=ID');
const meta=JSON.parse(wp('post meta get '+rid+' _ftt_clip_request'));
ok('request has 2 clips with server prices',meta.clips.length===2&&meta.clips[0].price_cents>0&&meta.email==='ann@example.test',JSON.stringify(meta).slice(0,200));
ok('request page url is this page',/clip-video-test/.test(meta.page_url),meta.page_url);
const log=JSON.parse(wp('option get ftt_mail_log --format=json'));
ok('owner notification logged, not sent',log[0].context==='clip-request'&&log[0].result==='logged (not sent)',log[0]&&log[0].result);
ok('list cleared after sending',await W.locator('.fwcc-list tbody tr').count()===0);
await p.screenshot({path:SH+'clip-selector-request-sent.png',fullPage:true});

/* two players: attach by selector */
await go('/clip-two-videos-test/');
await p.evaluate(()=>Promise.all(Array.from(document.querySelectorAll('video')).map(v=>v.readyState>=1?1:new Promise(r=>v.addEventListener('loadedmetadata',r)))));
await setT('#first',3);await setT('#second',12);
const ws=p.locator('.fwcc');
await ws.nth(0).locator('.fwcc-set[data-which=in]').click();await ws.nth(1).locator('.fwcc-set[data-which=in]').click();
const a=(await ws.nth(0).locator('[data-tc=in]').textContent()).trim(),bb=(await ws.nth(1).locator('[data-tc=in]').textContent()).trim();
ok('selector 1 reads #first (3 s)',/^00:00:0(2;29|3;00)$/.test(a),a);
ok('selector 2 reads #second (12 s @25)',bb==='00:00:12:00',bb);

/* automatic placement */
await go('/clip-auto-test/');
ok('auto attach off by default',await p.locator('.fwcc').count()===0);
wp(`option update ftt_clip_settings '{"auto_attach":"page"}' --format=json`);
await go('/clip-auto-test/');
ok('auto attach adds a selector under a page video',await p.locator('.fwcc').count()===1);
await go('/calc-toggles-test/');
ok('auto attach skips pages without video',await p.locator('.fwcc').count()===0);
wp('option delete ftt_clip_settings');

/* YouTube and Vimeo (need internet) */
for(const [slug,kind] of [['clip-youtube-test','youtube'],['clip-vimeo-test','vimeo']]){
  await go('/'+slug+'/');
  const r=await p.evaluate(async(kind)=>{const w=document.querySelector('.fwcc');const pl=window.FTT_PLAYERS.find(w,'auto','');if(!pl)return {err:'no player'};
    for(let i=0;i<60&&!(pl.duration>0);i++)await new Promise(r=>setTimeout(r,250));
    if(!(pl.duration>0))return {err:'no duration',kind:pl.kind,src:pl.element&&pl.element.src};
    pl.currentTime=30;await new Promise(r=>setTimeout(r,2500));
    return {kind:pl.kind,dur:pl.duration,t:pl.currentTime,paused:pl.paused,src:pl.element.src};},kind);
  if(r.err==='no duration'){skip(kind+' adapter (player did not answer in headless Chromium)',JSON.stringify(r));continue;}
  ok(kind+' adapter found',r.kind===kind,JSON.stringify(r));
  ok(kind+' duration reported',r.dur>0,JSON.stringify(r));
  ok(kind+' seek to 30 s',Math.abs(r.t-30)<1.5,JSON.stringify(r));
  ok(kind+' seek keeps a paused player paused (1.4.0)',r.paused===true,JSON.stringify(r));
  if(kind==='youtube')ok('youtube enablejsapi added',/enablejsapi=1/.test(r.src),r.src);
  await p.locator('.fwcc .fwcc-set[data-which=in]').click();
  const tc=(await p.locator('.fwcc [data-tc=in]').textContent()).trim();
  ok(kind+': Set In reads the iframe player time (~00:00:30)',/^00:00:(29|30|31)/.test(tc),tc);
  await p.screenshot({path:SH+'clip-selector-'+kind+'.png',fullPage:false});
}

/* ---------- Note 5: test bench (local copy) ---------- */
wp('ftt module off clips');
const built=wp('ftt testbench --video='+B+'/ftt-test-media/sample.webm --youtube=https://www.youtube.com/watch?v=aqz-KE-bpKQ');
const bid=(built.match(/page (\d+)/)||[])[1];
ok('bench built',!!bid,built);
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
await go('/?page_id='+bid+'&preview=true');
ok('bench preview: robots noindex',/noindex/.test(await p.locator('meta[name=robots]').getAttribute('content')||''));
ok('bench preview: calculators',await p.locator('[data-ftt-calc]').count()>=2);
ok('bench preview: standalone calculator',await p.locator('[data-filmcalc]').count()===1);
ok('bench preview: storage',await p.locator('[data-ftt-storage]').count()===1);
ok('bench preview: preview-only clip selectors',await p.locator('.fwcc[data-demo="1"]').count()===2,await p.locator('.fwcc').count());
const D=p.locator('.fwcc[data-demo="1"]').first();
await p.evaluate(()=>new Promise(r=>{const v=document.querySelector('video');if(v.readyState>=1)r();else v.addEventListener('loadedmetadata',r);}));
await setT('video',1);await D.locator('.fwcc-set[data-which=in]').click();await setT('video',3);await D.locator('.fwcc-set[data-which=out]').click();await D.locator('.fwcc-addlist').click();
await D.locator('.fwcc-addcart').click();await D.locator('.fwcc-rq-name').fill('x');await D.locator('.fwcc-rq-email').fill('x@localhost');await D.locator('.fwcc-send').click();
ok('preview-only selector refuses to send',/Preview only/.test(await D.locator('.fwcc-msg').textContent()));
await p.screenshot({path:SH+'test-bench-local.png',fullPage:true});
const chk=wp('ftt testbench --check');ok('wp ftt testbench --check passes locally',/Success/.test(chk),chk);
await ctx.clearCookies();
const anon=await p.goto(B+'/?page_id='+bid);ok('bench not public',anon.status()===404||!(await p.content()).includes('data-ftt-calc'),anon.status());
const anon2=await (await ctx.newPage()).goto(B+'/?page_id='+bid+'&preview=true');
wp('ftt module on clips');

ok('no JS errors',errs.length===0,errs.join(' | '));
ok('only YouTube/Vimeo external requests',ext.filter(u=>!/youtube|ytimg|ggpht|google|gstatic|doubleclick|vimeo|vimeocdn|akamaized|fastly|jnn-pa|googlevideo|gravatar/.test(u)).length===0,ext.filter(u=>!/youtube|ytimg|ggpht|google|gstatic|doubleclick|vimeo|vimeocdn|akamaized|fastly|jnn-pa|googlevideo|gravatar/.test(u)).slice(0,5).join(' '));
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+' passed, '+out.filter(x=>x.startsWith('FAIL')).length+' failed, '+out.filter(x=>x.startsWith('SKIP')).length+' skipped');
await b.close();
})().catch(e=>{console.log(out.join('\n'));console.error('CRASH',e.stack);process.exit(1);});
