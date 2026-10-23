// 1.5.8 e2e (login_mode): "Require sign-in to add to cart". Guest selects clips -> Add clips to cart -> Create account / Sign in
// (FluentAuth 3.0.5 forms via the VFC [vfc_auth_page] shortcode, which always land on /account/, plus the wp-login.php
// fallback) -> clips arrive in the FluentCart cart at the server price. Screenshots in /workspace/ftt-shots/158/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/158/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const [LP,RP,CP,AP]=fs.readFileSync('/workspace/ftt155-pages.txt','utf8').trim().split(/\s+/);
wp('transient delete --all'); wp(`eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}fls_login_hashes WHERE use_type=\\"signup_verification\\"");'`); // local only: FluentAuth allows 5 signups per IP per hour
const SAVED=wp('option get ftt_clip_settings --format=json')||'{}';
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T158 clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps 25`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
fs.writeFileSync('/tmp/t158.html',`<!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T158-VID" product_id="${PROD}"]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t158.html --post_type=page --post_status=publish --post_title="Clip 1.5.5 sign-in test" --porcelain');
const SLUG=wp(`post get ${PID} --field=post_name`); const onPage=u=>{const x=new URL(String(u));return x.pathname.replace(/\//g,'')===SLUG||x.searchParams.get('page_id')===String(PID);};
const CART0=wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fct_carts WHERE cart_data LIKE \\"%T158-VID%\\"");'`);
const setOpt=o=>{fs.writeFileSync('/tmp/t158opt.json',JSON.stringify(Object.assign((x=>Array.isArray(x)?{}:x)(JSON.parse(SAVED||'{}')),o)));wp('option update ftt_clip_settings --format=json < /tmp/t158opt.json');};
const price=+wp(`eval 'echo FTT_Clip::build(${PROD},"T158-VID",2,5.5,"25",1,"Harbor")["price_cents"];'`);
const cartOf=login=>wp(`eval '$u=get_user_by("login","${login}"); global $wpdb; $r=$wpdb->get_results($wpdb->prepare("SELECT cart_data FROM {$wpdb->prefix}fct_carts WHERE user_id=%d AND stage!=\\"completed\\"",$u->ID)); $o=[]; foreach($r as $x){foreach((array)json_decode($x->cart_data,true) as $i){$o[]=[$i["title"]??"",(int)($i["unit_price"]??0),(int)($i["quantity"]??0),(string)($i["id"]??"")];}} echo json_encode($o);'`);
const guestCart=hash=>JSON.parse(wp(`eval 'global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT user_id,cart_data FROM {$wpdb->prefix}fct_carts WHERE cart_hash=%s",\"${hash}\")); echo json_encode($r?["user"=>(int)$r->user_id,"items"=>array_map(function($i){return [$i["title"]??"",(int)($i["quantity"]??0)];},(array)json_decode($r->cart_data,true))]:null);'`)||'null');
const cartCookie=async ctx=>((await ctx.cookies()).find(c=>/fct.*cart|cart.*hash/i.test(c.name))||{}).value;
const markClip=async(v,S,a,z)=>{const vid=v.locator('video').first();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),a);await S.locator('.fwcc-set[data-which=in]').click();
  await vid.evaluate((x,t)=>new Promise(r=>{x.currentTime=t;x.addEventListener('seeked',r,{once:true});}),z);await S.locator('.fwcc-set[data-which=out]').click();
  await S.locator('.fwcc-name').fill('Harbor');await S.locator('.fwcc-addlist').click();};
(async()=>{const b=await chromium.launch();const errs=[];
const flow=async(label,vp,how,after)=>{
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:after,login_mode:'add_then_prompt'});
  const ctx=await b.newContext(vp.width<500?{viewport:vp,deviceScaleFactor:2}:{viewport:vp});const v=await ctx.newPage();v.on('pageerror',e=>errs.push(e.message));v.on('response',async rs=>{if(rs.url().includes('/clip/add'))console.log('ADDRESP',rs.status(),(await rs.text()).slice(0,300));});
  await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
  {const fl=await S.getAttribute('data-require-login');ok(label+': selector flagged prompt',fl==='prompt',fl+' '+wp('option get ftt_clip_settings --format=json'));}
  await markClip(v,S,2,5.5);await S.locator('.fwcc-addcart').click();
  await S.locator('.fwcc-msg .fwcc-register').waitFor({timeout:15000}).catch(async e=>{throw new Error('msg: '+await S.locator('.fwcc-msg').innerText());});
  const m=await S.locator('.fwcc-msg').innerText();
  ok(label+': guest add -> "added" + Sign in / Create account / View cart',/1 clip\(s\) added to your cart/.test(m)&&await S.locator('.fwcc-msg .fwcc-signin').count()===1&&await S.locator('.fwcc-msg .fwcc-viewcart').count()===1,m);
  const hash=await cartCookie(ctx);const g=guestCart(hash);
  ok(label+': clip in the guest cart now (no user yet), qty 1',g&&g.user===0&&g.items.length===1&&g.items[0][1]===1&&g.items[0][0].includes('Harbor'),JSON.stringify(g)+' '+hash);
  ok(label+': no resume token created',wp(`eval 'global $wpdb; echo (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \\"_transient_ftt_clip_pending_%\\"");'`)==='0');
  const n0=label.replace(/\W+/g,'-');await S.scrollIntoViewIfNeeded();await S.screenshot({path:SH+n0+'-1-guest-added-prompt.png'});
  let U;
  if(how==='register'){
    await S.locator('.fwcc-msg .fwcc-register').click();await v.waitForSelector('#flsRegistrationForm');await v.waitForLoadState('load');await v.waitForTimeout(500);
    U='t158reg'+Date.now()%100000;
    await v.fill('#fls_first_name','Test');await v.fill('#fls_reg_username',U);await v.fill('#fls_reg_email',U+'@example.com');await v.fill('#fls_reg_password','T158-pass-word!9');
    await v.click('#fls_submit');
    await v.waitForSelector('input[name=_email_verification_token]',{timeout:20000});
    let code='';for(let i=0;i<20&&!code;i++){try{code=(fs.readFileSync('/tmp/wp-mail-body-'+U+'@example.com.txt','utf8').replace(/<[^>]+>/g,' ').match(/\b(\d{6})\b/)||[])[1]||'';}catch(e){}if(!code)await v.waitForTimeout(500);}
    await v.fill('input[name=_email_verification_token]',code);
    await Promise.all([v.waitForNavigation({timeout:30000}).catch(()=>{}),v.click('#fls_verification_submit')]);
  } else {
    U='t158in'+Date.now()%100000; wp(`user create ${U} ${U}@example.com --role=subscriber --user_pass=T158-pass-word!9`);
    await S.locator('.fwcc-msg .fwcc-signin').click();await v.waitForSelector('#user_login');await v.waitForLoadState('load');await v.waitForTimeout(500);
    await v.fill('#user_login',U);await v.fill('#user_pass','T158-pass-word!9');
    await Promise.all([v.waitForURL(u=>!String(u).includes('/login'),{timeout:30000}).catch(()=>{}),v.click('#wp-submit')]);await v.waitForLoadState('load');await v.waitForTimeout(1500);
  }
  await v.waitForLoadState('load');await v.waitForTimeout(800);
  if(after==='cart')ok(label+': lands on the cart',v.url().includes('/cart'),v.url()); else ok(label+': back on the clip page',onPage(v.url()),v.url());
  if(after==='page'){const nt=v.locator('.ftt-clip-resume-notice');ok(label+': "clips are in your cart" notice',(await nt.count())===1&&/Your clips are in your cart/.test(await nt.innerText()),await nt.count()?await nt.innerText():'none');}
  ok(label+': return cookie cleared',!(await ctx.cookies()).find(c=>c.name==='ftt_clip_back'));
  const c=JSON.parse(cartOf(U)||'[]');
  ok(label+': same cart now the user\'s, clip once at server price',c.length===1&&c[0][2]===1&&c[0][1]===price,JSON.stringify(c)+' price '+price);
  const g2=guestCart(hash);ok(label+': cart hash kept (same cart row)',g2&&g2.items.length===1&&g2.user>0,JSON.stringify(g2));
  await v.goto(B+'/cart/');await v.waitForLoadState('load');await v.waitForTimeout(1200);
  ok(label+': cart page shows the clip',/Harbor/.test(await v.locator('body').innerText()));
  await v.screenshot({path:SH+n0+'-2-after-'+how+'-cart.png'});
  return {ctx,v,U,hash};
};
try{
  // A: desktop + FluentAuth registration, after_login=page
  let r=await flow('desktop-register',{width:1280,height:900},'register','page');await r.ctx.close();
  // B: mobile + sign in, after_login=cart
  r=await flow('mobile-signin',{width:390,height:844},'signin','cart');await r.ctx.close();
  // C: mobile + registration, D: desktop + sign in
  r=await flow('mobile-register',{width:390,height:844},'register','cart');await r.ctx.close();
  r=await flow('desktop-signin',{width:1280,height:900},'signin','page');
  // E: duplicate attempts (signed in now): add the same clip again via the selector, then claim a resume token holding it
  { const {v,U}=r; await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');const S=v.locator('.fwcc').first();
    await markClip(v,S,2,5.5);await S.locator('.fwcc-addcart').click();
    await v.waitForFunction(()=>/added to cart|rejected/.test(document.querySelector('.fwcc-msg').textContent),null,{timeout:15000});
    let c=JSON.parse(cartOf(U)||'[]');ok('dup: same clip added again -> still one line, qty 1',c.length===1&&c[0][2]===1,JSON.stringify(c));
    const tok=wp(`eval '$c=FTT_Clip::build(${PROD},"T158-VID",2,5.5,"25",1,"Harbor"); $t=wp_generate_password(32,false,false); set_transient("ftt_clip_pending_".$t,["clips"=>[["fw_clip"=>$c,"fw_sig"=>FTT_Clip::sign($c)]],"return"=>home_url("/"),"exp"=>time()+600],600); echo $t;'`);
    await v.goto(B+'/?page_id='+PID+'&ftt_clip_resume='+tok);await v.waitForLoadState('load');
    c=JSON.parse(cartOf(U)||'[]');ok('dup: resume token with the same clip does not re-add',c.length===1&&c[0][2]===1,JSON.stringify(c));
    ok('dup: token used once',wp(`eval 'echo get_transient("ftt_clip_pending_${tok}")===false?"gone":"left";'`)==='gone');
    await v.screenshot({path:SH+'dup-after-resume.png'}); }
  await r.ctx.close();
  // F: block (default) unchanged
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'page',login_mode:'block'});
  { const ctx=await b.newContext({viewport:{width:1280,height:900}});const y=await ctx.newPage();await y.goto(B+'/?page_id='+PID);await y.waitForSelector('.fwcc');const Y=y.locator('.fwcc').first();
    ok('block: flag 1',await Y.getAttribute('data-require-login')==='1');await markClip(y,Y,1,2);await Y.locator('.fwcc-addcart').click();await Y.locator('.fwcc-msg .fwcc-signin').waitFor();
    ok('block: nothing in a cart, token saved',!(await cartCookie(ctx))||!(guestCart(await cartCookie(ctx))||{items:[]}).items.length);
    const st=await y.evaluate(async()=>{const r=await fetch(FTT_CLIP.rest+'/add',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':FTT_CLIP.nonce},body:JSON.stringify({product_id:+document.querySelector('.fwcc').dataset.product,clip_id:'T158-VID',in:1,out:2,fps:'25'})});return r.status;});
    ok('block: guest /add still refused (401)',st===401,st); await ctx.close(); }
  // G: admin radio
  setOpt({require_login:1,login_page_id:+LP,register_page_id:+RP,after_login:'page',login_mode:'add_then_prompt'});
  { const ctx=await b.newContext({viewport:{width:1280,height:1100}});const a=await ctx.newPage();
    await a.goto(B+'/wp-login.php');await a.fill('#user_login','admin');await a.fill('#user_pass','admin');await Promise.all([a.waitForNavigation(),a.click('#wp-submit')]);
    await a.goto(B+'/wp-admin/admin.php?page=ftt-clips');const rd=a.locator('input[name="ftt_clip_settings[login_mode]"][value=add_then_prompt]');
    ok('admin: login_mode radio shows saved value',await rd.isChecked());await rd.scrollIntoViewIfNeeded();await a.screenshot({path:SH+'admin-login-mode.png'});await ctx.close(); }
}catch(e){ok('no exception',false,e.stack);}
finally{
  ok('no page errors',errs.length===0,errs.join(' | '));
  await b.close(); fs.writeFileSync('/tmp/t158opt.json',SAVED); wp('option update ftt_clip_settings --format=json < /tmp/t158opt.json');
  wp(`post delete ${PID} ${PROD} --force`); for(const u of wp('user list --field=user_login').split('\n').filter(s=>/^t158/.test(s))) wp(`user delete ${u} --yes`);
  console.log(out.join('\n'));const f=out.filter(x=>x.startsWith('FAIL')).length;console.log(`t158login: ${out.length-f}/${out.length} passed, ${f} failed`);}
})();
