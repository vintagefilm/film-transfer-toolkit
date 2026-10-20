// 1.4.1: clip selector shortcode + block render in the browser with the module on (FW Clip Cart prototype active, like live);
// switched off: visitors see nothing, editors see a note; never raw text. Also the block is in the inserter.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt141-shots/';fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const M0=wp('option get ftt_modules --format=json'), FW0=wp('plugin get fw-clip-cart --field=status');
const setClips=v=>wp(`eval '$m=get_option("ftt_modules"); $m["clips"]=${v}; update_option("ftt_modules",$m);'`);
const vid=wp("eval 'echo FTT_Testbench::sample_video_url();'");
const content=`<!-- wp:video --><figure class="wp-block-video"><video controls src="${vid}"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T141-SC" title="Shortcode copy"]<!-- /wp:shortcode -->
<!-- wp:ftt/clip-selector {"player":"html5","clip_id":"T141-BL","title":"Block copy"} /-->`;
fs.writeFileSync('/tmp/t141.html',content);
const PID=wp('post create /tmp/t141.html --post_type=page --post_status=publish --post_title="Clip selector 1.4.1 check" --porcelain');
(async()=>{const b=await chromium.launch();const errs=[];
try{
wp('plugin activate fw-clip-cart');setClips(1);
const url=wp('post url '+PID);
const v=await (await b.newContext({viewport:{width:1150,height:900}})).newPage();v.on('pageerror',e=>errs.push(e.message));
await v.goto(url);const body=await v.textContent('body');
ok('ON + prototype active: visitor sees 2 clip selectors (shortcode + block)',await v.locator('.fwcc').count()===2,await v.locator('.fwcc').count());
ok('ON: no raw [ftt_clip_selector] text',!body.includes('[ftt_clip_selector'));
const vh=await v.content();ok('ON: block copy keeps its title option',vh.includes('Block copy')&&vh.includes('Shortcode copy'));
ok('ON: clip assets enqueued',await v.locator('script[src*="clip-selector"]').count()>=1);
await v.screenshot({path:SH+'01-clips-on-visitor.png',fullPage:true});
const a=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();a.on('pageerror',e=>errs.push(e.message));
await a.goto(B+'/wp-login.php');await a.fill('#user_login','admin');await a.fill('#user_pass','admin');await a.click('#wp-submit');await a.waitForLoadState();
await a.goto(B+'/wp-admin/admin.php?page=ftt-settings');const dash=await a.textContent('#wpbody');
ok('settings: clip selector Active with prototype note',dash.includes('FW Clip Cart prototype plugin is also active')&&!dash.includes('Deactivate it to switch'));
await a.screenshot({path:SH+'02-settings-clips-active-with-prototype.png',fullPage:true});
await a.goto(B+'/wp-admin/post-new.php?post_type=page');await a.waitForTimeout(2500);
await a.evaluate(()=>{try{wp.data.dispatch('core/edit-post').toggleFeature&&0;}catch(e){}});
const has=await a.evaluate(()=>!!(window.wp&&wp.blocks&&wp.blocks.getBlockType('ftt/clip-selector')));
ok('editor: Clip selector block available',has);
setClips(0);
await v.goto(url+(url.includes('?')?'&':'?')+'r=1');const vb=await v.textContent('body');
ok('OFF: visitor sees no selector, no note, no raw text',await v.locator('.fwcc').count()===0&&await v.locator('.ftt-off-note').count()===0&&!vb.includes('[ftt_clip_selector'));
await v.screenshot({path:SH+'03-clips-off-visitor.png',fullPage:true});
await a.goto(url);
ok('OFF: editor sees 2 notes (shortcode + block), no raw text',await a.locator('.ftt-off-note[data-ftt-off="ftt_clip_selector"]').count()===2&&!(await a.textContent('body')).includes('[ftt_clip_selector'));
await a.screenshot({path:SH+'04-clips-off-editor-note.png',fullPage:true});
ok('no JS errors',errs.length===0,errs.join(' | '));
}finally{
wp('post delete '+PID+' --force');wp(`option update ftt_modules '${M0}' --format=json`);wp('plugin '+(FW0==='active'?'activate':'deactivate')+' fw-clip-cart');
await b.close();}
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+'/'+out.length+' passed');})();
