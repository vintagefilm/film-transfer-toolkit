// 1.5.3 e2e: clip selector "Jump to In"/"Jump to Out" (mouse + keyboard) and the heading (default, admin setting,
// blank hides, shortcode/block override, heading font). Restores ftt_clip_settings. Screenshots in /workspace/ftt-shots/153/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/153/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const keep=wp('option get ftt_clip_settings --format=json');
const restore=()=>{ if(keep) cp.execSync('cd /workspace/wp && wp option update ftt_clip_settings --format=json',{input:keep}); else wp('option delete ftt_clip_settings'); };
wp('option delete ftt_clip_settings'); wp('transient delete --all');
const content=`<!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T153-SC" title="Shortcode copy"]<!-- /wp:shortcode -->
<!-- wp:video --><figure class="wp-block-video" id="v2"><video id="vid2" controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" player_selector="#vid2" clip_id="T153-OWN" heading="Choose your clip &lt;b&gt;x&lt;/b&gt;"]<!-- /wp:shortcode -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T153-NONE" heading="none"]<!-- /wp:shortcode -->
<!-- wp:ftt/clip-selector {"player":"html5","clip_id":"T153-BL","heading":"Block heading"} /-->`;
fs.writeFileSync('/tmp/t153.html',content);
const PID=wp('post create /tmp/t153.html --post_type=page --post_status=publish --post_title="Clip 1.5.3 test" --porcelain');
(async()=>{const b=await chromium.launch();const errs=[];
try{
const v=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();v.on('pageerror',e=>errs.push(e.message));
await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');
const heads=await v.locator('.fwcc').evaluateAll(a=>a.map(x=>{const h=x.querySelector('.fwcc-heading');return h?h.textContent:null;}));
ok('default heading "Select Stock Footage" on the plain selector',heads[0]==='Select Stock Footage',JSON.stringify(heads));
ok('shortcode heading="..." overrides; markup is shown as text, never as HTML',heads[1]==='Choose your clip <b>x</b>'&&await v.locator('.fwcc-heading b').count()===0,JSON.stringify(heads));
ok('heading="none" hides it',heads[2]===null,JSON.stringify(heads));
ok('block sidebar value overrides',heads[3]==='Block heading',JSON.stringify(heads));
const h0=v.locator('.fwcc').first().locator('.fwcc-heading');
ok('heading is an h3 with the shared ftt-base title class',await h0.evaluate(h=>h.tagName==='H3'&&h.classList.contains('ftt-tool__title')));
const ff=await v.evaluate(()=>{const h=document.querySelector('.fwcc-heading');const ref=document.createElement('h2');document.body.appendChild(ref);const r=[getComputedStyle(h).fontFamily,getComputedStyle(ref).fontFamily];ref.remove();return r;});
ok('heading font = the site heading font (same as a theme h2)',ff[0]===ff[1],ff.join(' | '));
const order=await v.locator('.fwcc').first().evaluate(x=>[...x.children].map(c=>(c.className.match(/fwcc-(heading|marks)/)||[''])[0]).filter(Boolean).slice(0,2).join(',')+'|'+!!x.querySelector('.fwcc-marks .fwcc-jump'));
ok('order: heading, then In/Out marks holding the jump buttons (1.5.4 layout)',order==='fwcc-heading,fwcc-marks|true',order);
ok('ftt-base.css loaded',await v.locator('link[href*="ftt-base.css"]').count()===1);
await v.locator('.fwcc').first().scrollIntoViewIfNeeded();
await v.screenshot({path:SH+'01-heading-and-jump-buttons.png',fullPage:false});
// --- Jump buttons
const S=v.locator('.fwcc').first(), vid=v.locator('video').first();
const jIn=S.locator('.fwcc-jump[data-which=in]'), jOut=S.locator('.fwcc-jump[data-which=out]');
ok('jump buttons are <button>s with labels, tooltips and key shortcuts',await jIn.evaluate(b=>b.tagName==='BUTTON'&&b.getAttribute('aria-keyshortcuts')==='Shift+I'&&/In point/.test(b.title))&&await jOut.getAttribute('aria-keyshortcuts')==='Shift+O');
ok('before marks: aria-disabled=true but still a focusable, enabled button',await jIn.getAttribute('aria-disabled')==='true'&&await jIn.evaluate(b=>!b.disabled&&b.tabIndex===0));
await jIn.click({force:true}); ok('Jump to In with no In: plain message',/Set In first/.test(await S.locator('.fwcc-msg').textContent()));
await vid.evaluate(x=>new Promise(r=>{x.currentTime=2.0;x.addEventListener('seeked',r,{once:true});}));
await S.locator('.fwcc-set[data-which=in]').click();
await vid.evaluate(x=>new Promise(r=>{x.currentTime=5.5;x.addEventListener('seeked',r,{once:true});}));
await S.locator('.fwcc-set[data-which=out]').click();
const inTC=await S.locator('[data-tc=in]').textContent(), outTC=await S.locator('[data-tc=out]').textContent();
ok('after marks: aria-disabled=false',await jIn.getAttribute('aria-disabled')==='false'&&await jOut.getAttribute('aria-disabled')==='false');
await vid.evaluate(x=>new Promise(r=>{x.currentTime=8;x.addEventListener('seeked',r,{once:true});}));
const seekWait=()=>v.waitForTimeout(400);
await jIn.click();await seekWait();
let t=await vid.evaluate(x=>[x.currentTime,x.paused]);
ok('click Jump to In: video at the In frame (2.0 s) and paused',Math.abs(t[0]-2.0)<0.05&&t[1],JSON.stringify(t));
ok('message names the In timecode',(await S.locator('.fwcc-msg').textContent()).includes(inTC),await S.locator('.fwcc-msg').textContent());
await jOut.click();await seekWait();
t=await vid.evaluate(x=>[x.currentTime,x.paused]);
ok('click Jump to Out: video at the Out frame (5.5 s) and paused',Math.abs(t[0]-5.5)<0.05&&t[1],JSON.stringify(t));
await v.screenshot({path:SH+'02-jump-to-out.png'});
// keyboard: Tab focus + Enter / Space
await vid.evaluate(x=>{x.currentTime=8;});await seekWait();
await jIn.focus();await v.keyboard.press('Enter');await seekWait();
t=await vid.evaluate(x=>x.currentTime);ok('keyboard: Enter on focused Jump to In',Math.abs(t-2.0)<0.05,t);
await jOut.focus();await v.keyboard.press('Space');await seekWait();
t=await vid.evaluate(x=>x.currentTime);ok('keyboard: Space on focused Jump to Out',Math.abs(t-5.5)<0.05,t);
const focusRing=await jOut.evaluate(b=>{const s=getComputedStyle(b);return s.outlineStyle!=='none'||s.boxShadow!=='none';});
ok('focused jump button shows a focus indicator',focusRing);
await v.screenshot({path:SH+'03-keyboard-focus-jump-out.png'});
// shortcuts Shift+I / Shift+O (focus inside the selector, not in a text box)
await S.locator('.fwcc-preview').focus();
await v.keyboard.press('Shift+I');await seekWait();t=await vid.evaluate(x=>x.currentTime);
ok('Shift+I jumps to In',Math.abs(t-2.0)<0.05,t);
ok('Shift+I did not move the In mark',await S.locator('[data-tc=in]').textContent()===inTC);
await v.keyboard.press('Shift+O');await seekWait();t=await vid.evaluate(x=>x.currentTime);
ok('Shift+O jumps to Out',Math.abs(t-5.5)<0.05,t);
ok('plain I / O still set marks (unchanged behaviour)',await (async()=>{await vid.evaluate(x=>new Promise(r=>{x.currentTime=3;x.addEventListener('seeked',r,{once:true});}));await S.locator('.fwcc-preview').focus();await v.keyboard.press('i');return (await S.locator('[data-tc=in]').textContent())!==inTC;})());
// trimming the In point then jumping goes to the new In
await S.locator('.fwcc-nudge[data-which=in] button[data-d="+1s"]').click();await vid.evaluate(x=>{x.currentTime=9;});await seekWait();
await jIn.click();await seekWait();t=await vid.evaluate(x=>x.currentTime);ok('after Trim In +1s, Jump to In lands on the trimmed point (~4.0 s)',Math.abs(t-4.0)<0.06,t);
// the second selector controls its own player
const S2=v.locator('.fwcc').nth(1), v2=v.locator('#vid2');
await v2.evaluate(x=>new Promise(r=>{x.currentTime=1;x.addEventListener('seeked',r,{once:true});}));await S2.locator('.fwcc-set[data-which=in]').click();
await v2.evaluate(x=>{x.currentTime=6;});await seekWait();const before1=await vid.evaluate(x=>x.currentTime);
await S2.locator('.fwcc-jump[data-which=in]').click();await seekWait();
ok('second selector jumps its own player only',Math.abs(await v2.evaluate(x=>x.currentTime)-1)<0.05&&Math.abs(await vid.evaluate(x=>x.currentTime)-before1)<0.01);
// help panel mentions the buttons
await S.locator('.fwcc-help summary').click();ok('help panel lists Jump to In/Out and Shift+I / Shift+O',/Jump to In/.test(await S.locator('.fwcc-help-body').textContent())&&/Shift/.test(await S.locator('.fwcc-help-body').textContent()));
await S.locator('.fwcc-help').scrollIntoViewIfNeeded();await v.screenshot({path:SH+'04-help-panel.png'});
// mobile
const m=await (await b.newContext({viewport:{width:390,height:844},isMobile:true})).newPage();await m.goto(B+'/?page_id='+PID);await m.locator('.fwcc').first().scrollIntoViewIfNeeded();
const bw=await m.locator('.fwcc').first().evaluate(x=>x.scrollWidth<=x.clientWidth+1);ok('mobile: no horizontal overflow',bw);
await m.screenshot({path:SH+'05-mobile.png'});
// --- admin setting
const a=await (await b.newContext({viewport:{width:1280,height:1000}})).newPage();a.on('pageerror',e=>errs.push('admin: '+e.message));
await a.goto(B+'/wp-login.php');await a.fill('#user_login','admin');await a.fill('#user_pass','admin');await a.click('#wp-submit');await a.waitForLoadState();
await a.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=clips');
const hf=a.locator('#ftt-clip-heading');ok('admin: Heading field shows the default',await hf.inputValue()==='Select Stock Footage',await hf.count());
await hf.fill('Pick Your Footage');await a.screenshot({path:SH+'06-admin-heading-setting.png',fullPage:true});
await a.locator('#ftt-clip-heading').evaluate(x=>x.form.querySelector('[type=submit]').click());await a.waitForLoadState();
let o=JSON.parse(wp('option get ftt_clip_settings --format=json')||'{}');ok('admin save stores heading',o.heading==='Pick Your Footage',JSON.stringify(o));
ok('admin save keeps the other clip settings',o.default_fps==='29.97df'&&o.auto_attach==='off'&&o.default_product_id===0,JSON.stringify(o));
await v.goto(B+'/?page_id='+PID);let hs=await v.locator('.fwcc').evaluateAll(a=>a.map(x=>{const h=x.querySelector('.fwcc-heading');return h?h.textContent:null;}));
ok('site heading used where no override; overrides kept',hs.join('|')==='Pick Your Footage|Choose your clip <b>x</b>||Block heading',hs.join('|'));
await a.goto(B+'/wp-admin/admin.php?page=ftt-settings&tab=clips');await a.fill('#ftt-clip-heading','');
await a.locator('#ftt-clip-heading').evaluate(x=>x.form.querySelector('[type=submit]').click());await a.waitForLoadState();
o=JSON.parse(wp('option get ftt_clip_settings --format=json')||'{}');ok('blank setting saved as blank',o.heading==='',JSON.stringify(o));
await v.goto(B+'/?page_id='+PID);hs=await v.locator('.fwcc').evaluateAll(a=>a.map(x=>{const h=x.querySelector('.fwcc-heading');return h?h.textContent:null;}));
ok('blank setting hides the heading (overrides still show)',hs[0]===null&&hs[1]==='Choose your clip <b>x</b>'&&hs[3]==='Block heading',JSON.stringify(hs));
await v.locator('.fwcc').first().scrollIntoViewIfNeeded();await v.screenshot({path:SH+'07-blank-heading-hidden.png'});
// help > all options + block sidebar
await a.goto(B+'/wp-admin/admin.php?page=ftt-help&tool=options');const hb=await a.textContent('body');
ok('Help > All options lists the heading option',/heading/.test(await a.locator('#ftt-opt-ftt_clip_selector').textContent())&&hb.includes('heading="Choose your clip"'));
await a.locator('#ftt-opt-ftt_clip_selector tr[data-att="heading"]').scrollIntoViewIfNeeded();await a.screenshot({path:SH+'08-help-all-options-heading.png'});
await a.goto(B+'/wp-admin/admin.php?page=ftt-help&tool=clips');ok('Help > Clip selector FAQ covers both features',/Jump to In/.test(await a.textContent('body'))&&/Select Stock Footage/.test(await a.textContent('body')));
await a.goto(B+'/wp-admin/post.php?post='+PID+'&action=edit');await a.waitForFunction(()=>window.wp&&wp.data&&wp.data.select('core/block-editor')&&wp.data.select('core/block-editor').getBlocks().length>0,null,{timeout:60000});
const cid=await a.evaluate(()=>wp.data.select('core/block-editor').getBlocks().find(x=>x.name==='ftt/clip-selector').clientId);
await a.evaluate(c=>{const d=wp.data.dispatch('core/edit-post');d&&d.closeGeneralSidebar&&0;wp.data.dispatch('core/block-editor').selectBlock(c);(wp.data.dispatch('core/edit-post')||{}).openGeneralSidebar&&wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');},cid);
await a.waitForTimeout(1500);
const fr=a.frameLocator('iframe[name=editor-canvas]');
const side=await a.locator('.interface-complementary-area, .editor-sidebar').first().textContent().catch(()=> '');
ok('block sidebar has a Heading option from the registry',/Heading/.test(side),side.slice(0,200));
await a.screenshot({path:SH+'09-block-sidebar-heading.png'});
ok('no JS errors',errs.length===0,errs.join(' | '));
}catch(e){ok('run',false,e.stack);}
finally{await b.close();restore();wp('post delete '+PID+' --force');console.log(out.join('\n'));const f=out.filter(x=>x.startsWith('FAIL')).length;console.log(`t153: ${out.length-f}/${out.length} passed`);}
})();
