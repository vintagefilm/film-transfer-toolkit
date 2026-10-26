// 1.5.11 e2e: standard cart flow. A signed-out visitor adds clips with no sign-in or create-account UI anywhere in the selector;
// after the add the selector offers "View cart"; FluentCart's own cart + checkout take it from there (guest checkout on).
// Also: stale 1.5.10 settings (require_login etc.) are ignored; the guest cart is recorded on the user at sign-in; the clip
// selector admin screen has no sign-in rows. Run with FluentCart (+ FluentAuth, vfc-login-page for the account page) active.
// Screenshots: /workspace/ftt-shots/1511/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/1511/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const LOGINRX=/sign[ -]?in|create (an )?account|log[ -]?in|register/i;
const SAVED=wp('option get ftt_clip_settings --format=json')||'{}'; const SAVEDR=wp('option get ftt_clip_retired_login --format=json');
const FCS=wp('option get fluent_cart_store_settings --format=json');
const setOpt=o=>{fs.writeFileSync('/tmp/t1511o.json',JSON.stringify(o));wp('option update ftt_clip_settings --format=json < /tmp/t1511o.json');};
// 1.5.10-style settings on the site (what VFC has): must be ignored
setOpt({default_product_id:0,default_fps:'29.97df',auto_attach:'off',heading:'Select Stock Footage',require_login:1,login_page_id:593,register_page_id:594,after_login:'cart',login_mode:'add_then_prompt'});
wp('transient delete --all');
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T1511 clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps 25`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
fs.writeFileSync('/tmp/t1511.html',`<!-- wp:shortcode -->[fluent_cart_mini_cart]<!-- /wp:shortcode --><!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T1511-VID" product_id="${PROD}" require_login="yes"]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t1511.html --post_type=page --post_status=publish --post_title="Clip 1.5.11 cart flow" --porcelain');
const CHK=wp(`post create --post_type=page --post_status=publish --post_title="Order T1511" --post_content="[fluent_cart_checkout]" --porcelain`);
if(FCS){const j=JSON.parse(FCS);j.checkout_page_id=String(CHK);fs.writeFileSync('/tmp/t1511f.json',JSON.stringify(j));wp('option update fluent_cart_store_settings --format=json < /tmp/t1511f.json');}
const price=+wp(`eval 'echo FTT_Clip::build(${PROD},"T1511-VID",2,5.5,"25",1,"Harbor")["price_cents"];'`);
const guestCart=hash=>JSON.parse(wp(`eval 'global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT user_id,cart_data FROM {$wpdb->prefix}fct_carts WHERE cart_hash=%s",\"${hash}\")); echo json_encode($r?["user"=>(int)$r->user_id,"items"=>array_map(function($i){return [$i["title"]??"",(int)($i["quantity"]??0),(int)($i["unit_price"]??0)];},(array)json_decode($r->cart_data,true))]:null);'`)||'null');
const cartCookie=async ctx=>((await ctx.cookies()).find(c=>/fct.*cart|cart.*hash/i.test(c.name))||{}).value;
const markClip=async(v,S,a,z)=>{const vid=v.locator('video').first();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),a);await S.locator('.fwcc-set[data-which=in]').click();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),z);await S.locator('.fwcc-set[data-which=out]').click();
  await S.locator('.fwcc-name').fill('Harbor');await S.locator('.fwcc-addlist').click();};
(async()=>{const b=await chromium.launch();const errs=[];
 for(const vp of [{width:1280,height:900},{width:390,height:844}]){
  const L=vp.width<500?'mobile':'desktop';
  const ctx=await b.newContext(vp.width<500?{viewport:vp,deviceScaleFactor:2}:{viewport:vp});const v=await ctx.newPage();v.on('pageerror',e=>errs.push(e.message));
  const reqs=[];v.on('request',r=>reqs.push(r.method()+' '+r.url()));let addStatus=0,addBody='';v.on('response',async rs=>{if(rs.url().includes('/clip/add')){addStatus=rs.status();addBody=await rs.text();}});
  await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
  const selText=await S.innerText();const selHtml=await S.evaluate(x=>x.outerHTML);
  ok(L+': signed-out selector shows no sign-in / create-account / register text',!LOGINRX.test(selText),selText.match(LOGINRX));
  ok(L+': no login data attributes (data-require-login, data-login-optout)',!/data-require-login|data-login-optout/.test(selHtml));
  ok(L+': legacy require_login="yes" + 1.5.10 settings ignored: Add clips to cart button is plain',await S.locator('.fwcc-addcart').count()===1);
  ok(L+': page JS config has no loggedIn / login strings',!/loggedIn|"signin"|"register"|login_needed/.test(await v.content()));
  await markClip(v,S,2,5.5);await S.scrollIntoViewIfNeeded();await S.screenshot({path:SH+L+'-1-selector-signed-out.png'});
  await S.locator('.fwcc-addcart').click();
  await S.locator('.fwcc-msg .fwcc-viewcart').waitFor({timeout:15000}).catch(async e=>{throw new Error('msg: '+await S.locator('.fwcc-msg').innerText());});
  const m=await S.locator('.fwcc-msg').innerText();
  ok(L+': after add -> "1 clip(s) added to your cart." + View cart',/1 clip\(s\) added to your cart/.test(m)&&/View cart/.test(m),m);
  ok(L+': the message has no Sign in / Create account',!LOGINRX.test(m.replace(/View cart/,'')),m);
  ok(L+': /clip/add answered 200 for a signed-out visitor',addStatus===200,addStatus+' '+addBody.slice(0,200));
  let jb={};try{jb=JSON.parse(addBody);}catch(e){}
  ok(L+': add reply has the server price + cart_url, no auth block',(jb.clip||{}).price_cents===price&&/\/cart\/?$/.test(jb.cart_url||'')&&!('auth' in jb),addBody.slice(0,300));
  ok(L+': no request to /clip/pending or /clip/resume',!reqs.some(r=>/clip\/(pending|resume)/.test(r)));
  const href=await S.locator('.fwcc-msg .fwcc-viewcart').getAttribute('href');ok(L+': View cart links to the FluentCart cart page',/\/cart\/?$/.test(href),href);
  await v.waitForTimeout(1500);
  const mini=((await v.locator('[data-fluent-cart-cart-total-price]').first().innerText().catch(()=>'none'))||'').trim();
  ok(L+': header mini-cart shows the new total',mini.includes((price/100).toFixed(2)),mini);
  const hash=await cartCookie(ctx);const g=guestCart(hash);
  ok(L+': clip is in the guest cart at the server price',g&&g.user===0&&g.items.length===1&&g.items[0][2]===price&&g.items[0][0].includes('Harbor'),JSON.stringify(g));
  ok(L+': no sign-in selection saved (no pending transient, no localStorage key)',wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \\"_transient_ftt_clip_pending_%\\"");'`)==='0'&&await v.evaluate(()=>localStorage.getItem('ftt_clip_pending'))===null);
  await S.screenshot({path:SH+L+'-2-added-view-cart.png'});
  if(L==='desktop')await v.screenshot({path:SH+L+'-2b-added-page-with-mini-cart.png'});
  await Promise.all([v.waitForNavigation(),S.locator('.fwcc-msg .fwcc-viewcart').click()]);await v.waitForLoadState('load');await v.waitForTimeout(1200);
  const cartTxt=await v.locator('body').innerText();
  ok(L+': View cart opens the cart with the clip',/\/cart/.test(v.url())&&/Harbor/.test(cartTxt),v.url());
  await v.screenshot({path:SH+L+'-3-cart-page.png',fullPage:true});
  // checkout as a guest
  const co=v.locator('main a:has-text("Checkout"),main button:has-text("Checkout"),.fluent-cart-cart-page a:has-text("Checkout")').filter({visible:true}).first();
  if(await co.count()){await Promise.all([v.waitForURL(u=>/checkout/.test(String(u)),{timeout:20000}).catch(()=>{}),co.click({timeout:8000}).catch(()=>{})]);await v.waitForLoadState('load');}
  if(!/checkout/.test(v.url()))await v.goto(B+'/?page_id='+CHK);
  await v.waitForTimeout(2500);
  const ck=await v.locator('body').innerText();
  ok(L+': guest checkout page opens for a signed-out visitor (not redirected to a login page)',!/\/login\/?|wp-login/.test(v.url())&&/Harbor|Order|Billing|Email|Payment/i.test(ck),v.url());
  await v.screenshot({path:SH+L+'-4-checkout-guest.png',fullPage:true});
  if(L==='desktop'){
   // sign in at the site (wp-login, as FluentCart's customers do): the guest cart is recorded on the user, items unchanged
   const U='t1511in'+Date.now()%100000;wp(`user create ${U} ${U}@example.com --role=subscriber --user_pass=T1511-pass-word!9`);
   await v.goto(B+'/wp-login.php');await v.fill('#user_login',U);await v.fill('#user_pass','T1511-pass-word!9');await Promise.all([v.waitForNavigation({timeout:30000}).catch(()=>{}),v.click('#wp-submit')]);await v.waitForLoadState('load');
   const g2=guestCart(hash);const uid=+wp(`user get ${U} --field=ID`);
   ok(L+': signing in keeps the cart: same cart now owned by the user, same item',g2&&g2.user===uid&&g2.items.length===1&&g2.items[0][2]===price,JSON.stringify(g2));
   await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');
   ok(L+': signed-in page: selector still has no login UI',!LOGINRX.test(await v.locator('.fwcc').first().innerText()));
   await v.goto(B+'/account/');await v.waitForLoadState('load');await v.waitForTimeout(1500);await v.screenshot({path:SH+L+'-5-account-signed-in.png',fullPage:true});
   wp(`user delete ${U} --yes`);
  }
  await ctx.close();
 }
 // signed-out account page (FluentAuth forms live here, not in the selector)
 {const ctx=await b.newContext({viewport:{width:1280,height:900}});const v=await ctx.newPage();await v.goto(B+'/account/');await v.waitForLoadState('load');await v.waitForTimeout(1500);await v.screenshot({path:SH+'desktop-6-account-signed-out.png',fullPage:true});await ctx.close();}
 // admin: the Clip selector settings page has no sign-in rows
 {const ctx=await b.newContext({viewport:{width:1280,height:1000}});const v=await ctx.newPage();
    await v.goto(B+'/wp-login.php');await v.fill('#user_login','admin');await v.fill('#user_pass','admin');await Promise.all([v.waitForNavigation({timeout:30000}).catch(()=>{}),v.click('#wp-submit')]);
  await v.goto(B+'/wp-admin/admin.php?page=ftt-clips');await v.waitForLoadState('load');await v.waitForTimeout(800);
  const t=await v.locator('#wpbody-content').innerText();
  ok('admin: Clip selector settings has no sign-in / login rows',!/Require sign-in|login_mode|When a signed-out visitor|Sign-in page|Registration page|After sign-in/i.test(t),t.match(/.{40}sign.{40}/i));
  ok('admin: note says the selector never asks for sign-in',/never asks anyone to sign in/.test(t));
  ok('admin: no sign-in form fields (names)',await v.locator('[name*="require_login"],[name*="login_mode"],[name*="login_page_id"],[name*="register_page_id"],[name*="after_login"]').count()===0);
  await v.screenshot({path:SH+'desktop-7-admin-clip-selector-settings.png',fullPage:true});
  // saving the screen drops nothing that matters
  await v.locator('#wpbody-content form input[type=submit],#wpbody-content form #submit').first().click().catch(()=>{});await v.waitForLoadState('load');
  const sv=JSON.parse(wp('option get ftt_clip_settings --format=json'));
  ok('admin: saving the screen stores only the four live settings',JSON.stringify(Object.keys(sv).sort())===JSON.stringify(['auto_attach','default_fps','default_product_id','heading']),JSON.stringify(sv));
  await ctx.close();}
 ok('no page errors',!errs.length,errs.join(' | '));
 await b.close();
 fs.writeFileSync('/tmp/t1511o.json',SAVED);wp('option update ftt_clip_settings --format=json < /tmp/t1511o.json');
 SAVEDR?(fs.writeFileSync('/tmp/t1511r.json',SAVEDR),wp('option update ftt_clip_retired_login --format=json < /tmp/t1511r.json')):wp('option delete ftt_clip_retired_login');
 if(FCS){fs.writeFileSync('/tmp/t1511f.json',FCS);wp('option update fluent_cart_store_settings --format=json < /tmp/t1511f.json');}
 wp(`post delete ${PID} ${PROD} ${CHK} --force`);
 out.forEach(l=>console.log(l));console.log('t1511: '+out.filter(l=>l.startsWith('PASS')).length+'/'+out.length+' passed');
})().catch(e=>{console.log('ERROR '+e.message);out.forEach(l=>console.log(l));process.exit(1);});
