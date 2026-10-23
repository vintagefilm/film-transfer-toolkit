// 1.5.9 e2e: guest + login_mode add_then_prompt: (1) header mini-cart shows the server price right after the add and after a
// reload; (2) the selector estimate/list total match the server price (VFC card: $1/s, $29 minimum, rounding down).
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088';
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const [LP,RP]=fs.readFileSync('/workspace/ftt155-pages.txt','utf8').trim().split(/\s+/);
const SAVED=wp('option get ftt_clip_settings --format=json')||'{}'; const CARD=wp('option get ftt_clip_rate_card --format=json')||'';
fs.writeFileSync('/tmp/t159c.json',JSON.stringify({label:'VFC clip licence',per_second_cents:100,min_seconds:1,max_seconds:36000,min_charge_cents:2900,rounding:'down'}));wp('option update ftt_clip_rate_card --format=json < /tmp/t159c.json');
fs.writeFileSync('/tmp/t159o.json',JSON.stringify(Object.assign((x=>Array.isArray(x)?{}:x)(JSON.parse(SAVED)),{require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'cart',login_mode:'add_then_prompt'})));wp('option update ftt_clip_settings --format=json < /tmp/t159o.json');
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T159 clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps any`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
fs.writeFileSync('/tmp/t159.html',`<!-- wp:shortcode -->[fluent_cart_mini_cart]<!-- /wp:shortcode --><!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T159-VID" fps="25" product_id="${PROD}"]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t159.html --post_type=page --post_status=publish --post_title="T159 mini cart" --porcelain');
const server=+wp(`eval 'echo FTT_Clip::build(${PROD},"T159-VID",1,2,"25",1,"A")["price_cents"];'`);
(async()=>{const b=await chromium.launch();const errs=[];
 for(const vp of [{width:1280,height:900},{width:390,height:844}]){
  const L=vp.width<500?'mobile':'desktop';
  const ctx=await b.newContext({viewport:vp});const v=await ctx.newPage();v.on('pageerror',e=>errs.push(e.message));
  await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
  const mini=async()=>((await v.locator('[data-fluent-cart-cart-total-price]').first().innerText().catch(()=>'none'))||'').trim();
  ok(L+': mini-cart starts at $0.00',/0\.00/.test(await mini()),await mini());
  const est0=await S.locator('.fwcc-est').innerText();
  ok(L+': nothing marked -> estimate shows the minimum, not $0.00',est0.includes('29.00'),est0);
  const vid=v.locator('video').first();
  const seek=async t=>vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),t);
  await seek(1);await S.locator('.fwcc-set[data-which=in]').click();await seek(2);await S.locator('.fwcc-set[data-which=out]').click();
  const est=await S.locator('.fwcc-est').innerText();
  ok(L+': estimate = server price ('+server+')',est==='$'+(server/100).toFixed(2),est);
  await S.locator('.fwcc-addlist').click();
  const tot=await S.locator('.fwcc-total').innerText();
  ok(L+': list total = server price',tot==='$'+(server/100).toFixed(2),tot);
  await S.locator('.fwcc-addcart').click();await S.locator('.fwcc-msg .fwcc-viewcart').waitFor({timeout:15000});
  await v.waitForTimeout(1500);
  const m1=await mini();ok(L+': mini-cart updated right after the add',m1.includes((server/100).toFixed(2)),m1);
  const tot2=await S.locator('.fwcc-total').innerText();
  ok(L+': after the add the selector shows what was added, not $0.00',tot2.includes((server/100).toFixed(2)),tot2);
  await v.reload();await v.waitForSelector('.fwcc');const m2=await mini();
  ok(L+': mini-cart after reload',m2.includes((server/100).toFixed(2)),m2);
  // Page-cache simulation: serve the page with the header cart rendered empty ($0.00), as a stale cached copy would be.
  await v.route(/page_id=/,async r=>{const resp=await r.fetch();let h=await resp.text();h=h.replace(/(data-fluent-cart-cart-total-price[^>]*>)\s*[^<]*/,'$1 $0.00');await r.fulfill({response:resp,body:h});});
  await v.reload();await v.waitForSelector('.fwcc');await v.waitForTimeout(1500);const m3=await mini();
  ok(L+': stale cached header cart is corrected on load',m3.includes((server/100).toFixed(2)),m3);
  await v.unroute(/page_id=/);
  const hd=await (await v.request.get(B+'/?page_id='+PID)).headers();
  ok(L+': guest with a cart gets no-cache headers',/no-cache|no-store/.test(hd['cache-control']||''),hd['cache-control']);
  await ctx.close();
 }
 ok('no page errors',!errs.length,errs.join(' | '));
 await b.close();
 fs.writeFileSync('/tmp/t159o.json',SAVED);wp('option update ftt_clip_settings --format=json < /tmp/t159o.json');
 if(CARD){fs.writeFileSync('/tmp/t159c.json',CARD);wp('option update ftt_clip_rate_card --format=json < /tmp/t159c.json');}else wp('option delete ftt_clip_rate_card');
 wp(`post delete ${PID} ${PROD} --force`);
 out.forEach(l=>console.log(l));console.log('t159cart: '+out.filter(l=>l.startsWith('PASS')).length+'/'+out.length+' passed');
})().catch(e=>{console.log('ERROR '+e.message);out.forEach(l=>console.log(l));process.exit(1);});
