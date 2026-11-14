// 1.5.12 e2e: clip selector quote + add-to-cart succeed when the page carries a stale/bad wp_rest nonce
// (guest and signed-in; baked-in bad nonce; first fresh nonce rejected -> automatic retry; page cached before the nonce expired).
// Needs FluentCart active and mu-plugin zz-t1512-nonce-life.php (60 s nonces when option ftt_t1512_short_nonce=1).
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH=process.env.SHOTS||'/workspace/ftt-shots/1512';
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(typeof d==='string'?d:JSON.stringify(d)).slice(0,300):''));};
fs.mkdirSync(SH,{recursive:true});
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T1512 clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps any`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
fs.writeFileSync('/tmp/t1512.html',`<!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T1512-VID" fps="25" product_id="${PROD}"]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t1512.html --post_type=page --post_status=publish --post_title="T1512 nonce refresh" --porcelain');
const URL_=B+'/?page_id='+PID;
const server=+wp(`eval 'echo FTT_Clip::build(${PROD},"T1512-VID",1,2,"25",1,"A")["price_cents"];'`);
const NRX=/("nonce":")([0-9a-f]{10})(")/;
async function login(ctx){const v=await ctx.newPage();await v.goto(B+'/wp-login.php');await v.fill('#user_login','admin');await v.fill('#user_pass','admin');
 await Promise.all([v.waitForNavigation({timeout:30000}).catch(()=>{}),v.click('#wp-submit')]);await v.close();}
async function run(L,ctx,opt){ // opt: {html: page html to serve (cached copy), badBaked, firstFreshBad, shot}
 const v=await ctx.newPage();const errs=[];v.on('pageerror',e=>errs.push(e.message));
 const net=[];v.on('response',r=>{const u=r.url();if(/ftt_clip_nonce|clip%2F(quote|add|nonce)|clip\/(quote|add|nonce)/.test(u))net.push({u:u.replace(B,''),s:r.status(),m:r.request().method(),n:r.request().headers()['x-wp-nonce']||'',probe:!!r.request().headers()['x-t1512-probe']});});
 let baked='';
 await v.route(u=>u.href.startsWith(URL_),async r=>{
  let h; if(opt.html){h=opt.html;} else {const resp=await r.fetch();h=await resp.text();}
  if(opt.badBaked)h=h.replace(NRX,'$1bad0000000$3'.replace('bad0000000','bad000000'+'0'));
  const m=h.match(NRX);baked=m?m[2]:'';
  await r.fulfill({status:200,contentType:'text/html; charset=UTF-8',body:h});});
 let first=true;
 if(opt.firstFreshBad) await v.route(/ftt_clip_nonce/,async r=>{if(first){first=false;await r.fulfill({status:200,contentType:'application/json',body:JSON.stringify({nonce:'0badfresh0',ttl:86400})});}else await r.continue();});
 await v.goto(URL_);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
 // the nonce baked into this page: does the server accept it? (proves the page is stale)
 const direct=await v.evaluate(async n=>{const r=await fetch(FTT_CLIP.rest+'/quote',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':n,'X-T1512-Probe':'1'},body:JSON.stringify({product_id:0,clip_id:'X',in:1,out:2,fps:'25'})});let j={};try{j=await r.json();}catch(e){}return {s:r.status,code:j.code||'',msg:j.message||''};},baked);
 if(opt.expectStale) ok(L+': baked nonce in the page is rejected by the server (403 '+direct.code+')',direct.s===403&&/rest_cookie_invalid_nonce|fwcc_nonce/.test(direct.code),direct);
 const vid=v.locator('video').first();const seek=async t=>vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),t);
 await seek(1);await S.locator('.fwcc-set[data-which=in]').click();await seek(2);await S.locator('.fwcc-set[data-which=out]').click();
 await S.locator('.fwcc-addlist').click();
 await v.waitForResponse(r=>/quote/.test(r.url())&&r.status()===200,{timeout:15000}).catch(()=>{});await v.waitForTimeout(600);
 const tot=await S.locator('.fwcc-total').innerText();
 ok(L+': quote succeeded, list shows the server price '+(server/100).toFixed(2),tot==='$'+(server/100).toFixed(2),tot);
 await S.locator('.fwcc-addcart').click();
 const done=await S.locator('.fwcc-msg .fwcc-viewcart').waitFor({timeout:20000}).then(()=>true,()=>false);
 const msg=(await S.locator('.fwcc-msg').innerText()).trim();
 ok(L+': add to cart succeeded ("'+msg.slice(0,40)+'")',done&&/added to your cart/.test(msg)&&!/Cookie check failed|Nothing was added/.test(msg),msg);
 const app=net.filter(x=>x.m==='POST'&&!x.probe), adds=app.filter(x=>/add/.test(x.u));
 if(opt.firstFreshBad) ok(L+': first request with the rejected nonce got 403, automatic retry with a new nonce got 200, then /add 200',app.length===3&&app[0].n==='0badfresh0'&&app[0].s===403&&app[1].u===app[0].u&&app[1].s===200&&app[1].n!=='0badfresh0'&&adds.length===1&&adds[0].s===200,app);
 else ok(L+': /quote 200 then one /add 200, no 403s',app.length===2&&app.every(x=>x.s===200)&&adds.length===1,app);
 ok(L+': the stale baked nonce was never sent by the selector',!!baked&&!app.some(x=>x.n===baked),app);
 ok(L+': fresh nonce fetched from admin-ajax',net.some(x=>/ftt_clip_nonce/.test(x.u)&&x.s===200),net);
 ok(L+': no page errors',!errs.length,errs);
 await S.screenshot({path:SH+'/'+opt.shot+'.png'});await v.screenshot({path:SH+'/'+opt.shot+'-page.png',fullPage:false});
 await v.close();return net;
}
(async()=>{const b=await chromium.launch();
 try{
 for(const who of ['guest','signedin']){
  const mk=async()=>{const c=await b.newContext({viewport:{width:1280,height:900}});if(who==='signedin')await login(c);return c;};
  let c=await mk();await run(who+' / baked bad nonce',c,{badBaked:true,expectStale:true,shot:who+'-1-baked-bad-nonce'});await c.close();
  c=await mk();await run(who+' / first fresh nonce rejected -> retry',c,{badBaked:true,firstFreshBad:true,shot:who+'-2-retry'});await c.close();
 }
 // page cached before the nonce expired: 60 s nonces, grab the page, wait 65 s, serve the cached copy
 wp('option update ftt_t1512_short_nonce 1');
 const cg=await b.newContext({viewport:{width:1280,height:900}});const cs=await b.newContext({viewport:{width:1280,height:900}});await login(cs);
 const grab=async c=>{const v=await c.newPage();const r=await v.goto(URL_);const h=await r.text();await v.close();return h;};
 const hg=await grab(cg), hs=await grab(cs);
 ok('cached copies captured with a nonce',NRX.test(hg)&&NRX.test(hs));
 console.log('waiting 65 s for the cached pages\' nonces to expire...');await new Promise(r=>setTimeout(r,65000));
 await run('guest / page cached before expiry',cg,{html:hg,expectStale:true,shot:'guest-3-cached-before-expiry'});
 await run('signedin / page cached before expiry',cs,{html:hs,expectStale:true,shot:'signedin-3-cached-before-expiry'});
 await cg.close();await cs.close();
 } finally {
 wp('option delete ftt_t1512_short_nonce');
 await b.close();wp(`post delete ${PID} ${PROD} --force`);}
 out.forEach(l=>console.log(l));console.log('t1512: '+out.filter(l=>l.startsWith('PASS')).length+'/'+out.length+' passed');
})().catch(e=>{wp('option delete ftt_t1512_short_nonce');console.log('ERROR '+e.stack);out.forEach(l=>console.log(l));process.exit(1);});
