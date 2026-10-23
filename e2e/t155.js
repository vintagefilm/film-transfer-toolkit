// 1.5.5 e2e: "Require sign-in to add to cart". Guest selects clips -> Add clips to cart -> Create account / Sign in
// (FluentAuth 3.0.5 forms via the VFC [vfc_auth_page] shortcode, which always land on /account/, plus the wp-login.php
// fallback) -> clips arrive in the FluentCart cart at the server price. Screenshots in /workspace/ftt-shots/155/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/155/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const [LP,RP,CP,AP]=fs.readFileSync('/workspace/ftt155-pages.txt','utf8').trim().split(/\s+/);
wp('transient delete --all'); wp(`eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}fls_login_hashes WHERE use_type=\\"signup_verification\\"");'`); // local only: FluentAuth allows 5 signups per IP per hour
const SAVED=wp('option get ftt_clip_settings --format=json')||'{}';
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T155 clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps 25`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
fs.writeFileSync('/tmp/t155.html',`<!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T155-VID" product_id="${PROD}"]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t155.html --post_type=page --post_status=publish --post_title="Clip 1.5.5 sign-in test" --porcelain');
const SLUG=wp(`post get ${PID} --field=post_name`); const onPage=u=>{const x=new URL(String(u));return x.pathname.replace(/\//g,'')===SLUG||x.searchParams.get('page_id')===String(PID);};
const CART0=wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_carts WHERE cart_data LIKE \\"%T155-VID%\\"");'`);
const setOpt=o=>{fs.writeFileSync('/tmp/t155opt.json',JSON.stringify(Object.assign((x=>Array.isArray(x)?{}:x)(JSON.parse(SAVED||'{}')),o)));wp('option update ftt_clip_settings --format=json < /tmp/t155opt.json');};
const price=+wp(`eval 'echo FTT_Clip::build(${PROD},"T155-VID",2,5.5,"25",1,"Harbor")["price_cents"];'`);
const cartOf=login=>wp(`eval '$u=get_user_by("login","${login}"); global $wpdb; $r=$wpdb->get_results($wpdb->prepare("SELECT cart_data FROM {$wpdb->prefix}fct_carts WHERE user_id=%d",$u->ID)); $o=[]; foreach($r as $x){foreach((array)json_decode($x->cart_data,true) as $i){$o[]=[$i["title"]??"",(int)($i["unit_price"]??0)];}} echo json_encode($o);'`);
const markClip=async(v,S,a,z)=>{const vid=v.locator('video').first();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),a);await S.locator('.fwcc-set[data-which=in]').click();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),z);await S.locator('.fwcc-set[data-which=out]').click();
  await S.locator('.fwcc-name').fill('Harbor');await S.locator('.fwcc-addlist').click();};
(async()=>{const b=await chromium.launch();const errs=[];
try{
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'page'});
  // ---- A: guest -> Create account (FluentAuth signup on /register/, lands on /account/) ----
  const ctx=await b.newContext({viewport:{width:1280,height:900}});const v=await ctx.newPage();v.on('pageerror',e=>errs.push(e.message));
  await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
  ok('guest: selector visible and usable',await S.isVisible()&&await S.getAttribute('data-require-login')==='1');
  await markClip(v,S,2,5.5);
  ok('guest: clip in list',await S.locator('.fwcc-list tbody tr').count()===1);
  await S.locator('.fwcc-addcart').click();
  await S.locator('.fwcc-msg .fwcc-register').waitFor({timeout:10000});
  const m=await S.locator('.fwcc-msg').innerText();
  ok('guest: Add to cart asks to sign in, offers both links',/sign in or create an account/i.test(m)&&await S.locator('.fwcc-msg .fwcc-signin').count()===1,m);
  const ls=await v.evaluate(()=>localStorage.getItem('ftt_clip_pending'));
  ok('guest: token saved in localStorage',/"token":"[A-Za-z0-9]{32}"/.test(ls||''),ls);
  const ck=(await ctx.cookies()).find(c=>c.name==='ftt_clip_resume');
  ok('guest: HttpOnly resume cookie set',ck&&ck.httpOnly&&/^[A-Za-z0-9]{32}$/.test(ck.value));
  const regHref=await S.locator('.fwcc-msg .fwcc-register').getAttribute('href');
  ok('register link: registration page + redirect_to back with token',regHref.includes('/register/')&&decodeURIComponent(regHref).includes(SLUG)&&decodeURIComponent(regHref).includes('ftt_clip_resume='),regHref);
  ok('guest: nothing in any cart yet',wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_carts WHERE cart_data LIKE \\"%T155-VID%\\"");'`)===CART0,CART0);
  await S.scrollIntoViewIfNeeded();await S.screenshot({path:SH+'01-guest-add-asks-sign-in.png'});
  await S.locator('.fwcc-msg .fwcc-register').click();await v.waitForSelector('#flsRegistrationForm');await v.waitForLoadState('load');await v.waitForTimeout(500);
  const U='t155reg'+Date.now()%100000;
  await v.fill('#fls_first_name','Test');await v.fill('#fls_reg_username',U);await v.fill('#fls_reg_email',U+'@example.com');await v.fill('#fls_reg_password','T155-pass-word!9');
  await v.screenshot({path:SH+'02-register-form.png'});
  await v.click('#fls_submit');
  // FluentAuth emails a verification code first (as on VFC); read it from the local mail trap.
  await v.waitForSelector('input[name=_email_verification_token]',{timeout:20000}).catch(async e=>{throw new Error('signup: '+(await v.locator('.vfc-auth-form').innerText()).slice(0,400));});
  let code='';for(let i=0;i<20&&!code;i++){try{code=(fs.readFileSync('/tmp/wp-mail-body-'+U+'@example.com.txt','utf8').replace(/<[^>]+>/g,' ').match(/\b(\d{6})\b/)||[])[1]||'';}catch(e){}if(!code)await v.waitForTimeout(500);}
  ok('registration: verification code received',/^\d{6}$/.test(code),code);
  await v.fill('input[name=_email_verification_token]',code);
  await Promise.all([v.waitForURL(onPage,{timeout:30000}).catch(()=>{}),v.click('#fls_verification_submit')]);
  await v.waitForLoadState('load');
  ok('after registration: back on the clip page',onPage(v.url())&&!v.url().includes('ftt_clip_resume'),v.url());
  const n=v.locator('.ftt-clip-resume-notice');
  ok('notice: 1 clip added + View cart link',(await n.count())===1&&/1 clip was added to your cart/.test(await n.innerText())&&(await n.locator('a.ftt-clip-resume-cart').getAttribute('href')).includes('/cart'),await n.count()?await n.innerText():'none');
  ok('user registered as subscriber',wp(`user get ${U} --field=roles`)==='subscriber');
  const c1=JSON.parse(cartOf(U)||'[]');
  ok('cart has the clip at the server price',c1.length===1&&c1[0][0].includes('Harbor')&&c1[0][1]===price,JSON.stringify(c1)+' price '+price);
  ok('token used once (transient gone)',wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \\"_transient_ftt_clip_pending_%\\"");'`)==='0');
  ok('resume cookie cleared',!(await ctx.cookies()).find(c=>c.name==='ftt_clip_resume'));
  ok('localStorage copy cleared',await v.evaluate(()=>localStorage.getItem('ftt_clip_pending'))===null);
  await v.screenshot({path:SH+'03-after-register-clip-added-notice.png'});
  await v.reload();ok('notice shown once',await v.locator('.ftt-clip-resume-notice').count()===0);
  // signed-in now: adds directly, no prompt
  const S2=v.locator('.fwcc').first();await markClip(v,S2,6,8);await S2.locator('.fwcc-addcart').click();
  await v.waitForFunction(()=>/added to cart|rejected/.test(document.querySelector('.fwcc-msg').textContent),null,{timeout:15000});
  ok('signed-in user adds directly (unaffected)',/1 clip\(s\) added to cart/.test(await S2.locator('.fwcc-msg').innerText())&&JSON.parse(cartOf(U)).length===2,await S2.locator('.fwcc-msg').innerText());
  await v.goto(B+'/cart/');await v.waitForLoadState('load');await v.waitForTimeout(1200);
  ok('cart page lists the clips',/T155|Harbor/.test(await v.locator('body').innerText()));
  await v.screenshot({path:SH+'04-cart-page.png',fullPage:false});
  await ctx.close();

  // ---- B: guest -> Sign in (FluentAuth login on /login/), after_login = cart ----
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'cart'});
  const U2='t155in'+Date.now()%100000; wp(`user create ${U2} ${U2}@example.com --role=subscriber --user_pass=T155-pass-word!9`);
  const ctx2=await b.newContext({viewport:{width:390,height:844},deviceScaleFactor:2});const w=await ctx2.newPage();w.on('pageerror',e=>errs.push(e.message));
  await w.goto(B+'/?page_id='+PID);await w.waitForSelector('.fwcc');const T=w.locator('.fwcc').first();
  await markClip(w,T,1,3);await T.locator('.fwcc-addcart').click();await T.locator('.fwcc-msg .fwcc-signin').waitFor();
  await T.scrollIntoViewIfNeeded();await T.screenshot({path:SH+'05-mobile-guest-sign-in-choices.png'});
  await T.locator('.fwcc-msg .fwcc-signin').click();await w.waitForSelector('#user_login');await w.waitForLoadState('load');await w.waitForTimeout(500);
  await w.fill('#user_login',U2);await w.fill('#user_pass','T155-pass-word!9');
  await Promise.all([w.waitForURL(u=>String(u).includes('/cart'),{timeout:30000}).catch(()=>{}),w.click('#wp-submit')]);
  await w.waitForLoadState('load');await w.waitForTimeout(1000);
  ok('sign-in path (after_login=cart): lands on the cart',w.url().includes('/cart'),w.url());
  const c2=JSON.parse(cartOf(U2)||'[]');
  ok('sign-in path: clip in the cart at server price',c2.length===1&&c2[0][1]===+wp(`eval 'echo FTT_Clip::build(${PROD},"T155-VID",1,3,"25",1,"Harbor")["price_cents"];'`),JSON.stringify(c2));
  await w.screenshot({path:SH+'06-mobile-after-sign-in-cart.png'});
  await ctx2.close();

  // ---- C: wp-login.php fallback (no pages set), after_login = page ----
  setOpt({require_login:1,login_page_id:0,register_page_id:0,after_login:'page'});
  const U3='t155wp'+Date.now()%100000; wp(`user create ${U3} ${U3}@example.com --role=subscriber --user_pass=T155-pass-word!9`);
  const ctx3=await b.newContext({viewport:{width:1280,height:900}});const x=await ctx3.newPage();x.on('pageerror',e=>errs.push(e.message));
  await x.goto(B+'/?page_id='+PID);await x.waitForSelector('.fwcc');const X=x.locator('.fwcc').first();
  await markClip(x,X,1,2);await X.locator('.fwcc-addcart').click();await X.locator('.fwcc-msg .fwcc-signin').waitFor();
  const sh=await X.locator('.fwcc-msg .fwcc-signin').getAttribute('href');
  ok('fallback: Sign in = wp-login.php with redirect_to',sh.includes('wp-login.php')&&decodeURIComponent(sh).includes('ftt_clip_resume='),sh);
  ok('fallback: Create account = wp-login.php?action=register',(await X.locator('.fwcc-msg .fwcc-register').getAttribute('href')).includes('action=register'));
  await X.locator('.fwcc-msg .fwcc-signin').click();await x.waitForSelector('#user_login');await x.fill('#user_login',U3);await x.fill('#user_pass','T155-pass-word!9');
  await Promise.all([x.waitForURL(onPage,{timeout:30000}).catch(()=>{}),x.click('#wp-submit')]);await x.waitForLoadState('load');
  ok('fallback: back on the page with notice',onPage(x.url())&&await x.locator('.ftt-clip-resume-notice').count()===1,x.url());
  ok('fallback: clip in cart',JSON.parse(cartOf(U3)||'[]').length===1);
  await x.screenshot({path:SH+'07-wp-login-fallback-notice.png'});
  await ctx3.close();

  // ---- D: setting off = 1.5.4 behaviour for guests; tampered /add price still refused ----
  setOpt({require_login:0});
  const ctx4=await b.newContext({viewport:{width:1280,height:900}});const y=await ctx4.newPage();
  await y.goto(B+'/?page_id='+PID);await y.waitForSelector('.fwcc');const Y=y.locator('.fwcc').first();
  ok('setting off: no data-require-login',await Y.getAttribute('data-require-login')===null);
  await markClip(y,Y,1,2);await Y.locator('.fwcc-addcart').click();
  await y.waitForFunction(()=>/added to cart|rejected/.test(document.querySelector('.fwcc-msg').textContent),null,{timeout:15000});
  ok('setting off: guest adds directly',/1 clip\(s\) added to cart/.test(await Y.locator('.fwcc-msg').innerText()),await Y.locator('.fwcc-msg').innerText());
  await ctx4.close();
  // ---- E: admin settings screen ----
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'page'});
  const ctx5=await b.newContext({viewport:{width:1280,height:1100}});const a=await ctx5.newPage();
  await a.goto(B+'/wp-login.php');await a.fill('#user_login','admin');await a.fill('#user_pass','admin');await Promise.all([a.waitForNavigation(),a.click('#wp-submit')]);
  await a.goto(B+'/wp-admin/admin.php?page=ftt-clips');
  ok('admin: Require sign-in checkbox checked',await a.locator('#ftt-clip-require-login').isChecked());
  ok('admin: login/register pages selected',await a.locator('#ftt-clip-login-page').inputValue()===LP&&await a.locator('#ftt-clip-register-page').inputValue()===RP);
  await a.locator('#ftt-clip-require-login').scrollIntoViewIfNeeded();await a.screenshot({path:SH+'08-admin-settings.png'});
  await ctx5.close();
}catch(e){ok('no exception',false,e.stack);}
finally{
  ok('no page errors',errs.length===0,errs.join(' | '));
  await b.close(); fs.writeFileSync('/tmp/t155opt.json',SAVED); wp('option update ftt_clip_settings --format=json < /tmp/t155opt.json');
  wp(`post delete ${PID} ${PROD} --force`); for(const u of wp('user list --field=user_login').split('\n').filter(s=>/^t155/.test(s))) wp(`user delete ${u} --yes`);
  console.log(out.join('\n'));const f=out.filter(x=>x.startsWith('FAIL')).length;console.log(`${out.length-f}/${out.length} passed, ${f} failed`);}
})();
