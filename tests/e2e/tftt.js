// Film Transfer Toolkit local end-to-end tests (real typing). node tftt.js
const {chromium}=require('playwright');const cp=require('child_process');
const B='http://localhost:8088', SH='/workspace/ftt-shots/';
const wp=(a)=>cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'});
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
const money=s=>s;
(async()=>{
const b=await chromium.launch();
const ctx=await b.newContext({viewport:{width:1150,height:900}});const p=await ctx.newPage();
const errs=[],ext=[];p.on('pageerror',e=>errs.push(e.message));p.on('request',r=>{const u=r.url();if(!u.startsWith(B)&&!/^(data|blob):/.test(u))ext.push(u)});
p.on('dialog',d=>d.accept());
async function typeIn(sel,text){await p.click(sel);await p.keyboard.press('Control+A');await p.keyboard.press('Backspace');await p.keyboard.type(String(text),{delay:25});}

// ---------- calculator
await p.goto(B+'/ftt-calc/');
ok('calculator renders with data-ftt-calc',await p.locator('[data-ftt-calc]').count()===1);
await p.selectOption('[data-ftt-calc] [data-fc=format]','16');await p.selectOption('[data-ftt-calc] [data-fc=speed]','24');
await typeIn('[data-ftt-calc] [data-fc=feet]','400');
ok('calculator 400 ft 16mm@24 -> 11:06.67 (FTTCore)',await p.inputValue('[data-ftt-calc] [data-fc=time]')==='11:06.67');
await p.screenshot({path:SH+'01-calculator.png',fullPage:true});

// ---------- reels
await p.goto(B+'/ftt-reels/');
await p.selectOption('[data-r=format]','s8');await p.selectOption('[data-r=size]','7');await typeIn('[data-r=count]','2');
let r=await p.textContent('[data-r=out]');ok('reels: 2 x 7in Super 8 = 800 ft, 53:20 @18',r.includes('800 ft')&&r.includes('53:20'),r);
await p.selectOption('[data-r=format]','8');await p.check('[data-r=method][value=measure]');await typeIn('[data-r=outer]','5.93');
r=await p.textContent('[data-r=out]');ok('reels: measured 5.93 in 8mm ~ 400 ft with range',/About (39\d|40\d) ft \(roughly/.test(r),r);
await typeIn('[data-r=outer]','1');r=await p.textContent('[data-r=out]');ok('reels: diameter smaller than hub -> message',r.includes('larger than the hub'),r);
await typeIn('[data-r=outer]','5.93');
await p.screenshot({path:SH+'02-reel-estimator.png',fullPage:true});

// ---------- price table
await p.goto(B+'/ftt-prices/');
let t=await p.textContent('.ftt-prices');
ok('price table shows EXAMPLE banner',t.includes('EXAMPLE PRICES')&&t.includes('Prices as of 2026-10-13'));
ok('price table shows per-foot and per-reel prices',t.includes('$0.25')&&t.includes('3 in (50 ft): $12.00'),t.slice(0,400));
ok('price table fees list',t.includes('Setup fee $25.00 per order')&&t.includes('Order minimum $75.00')&&t.includes('5% off orders of 1,000 ft'),t);
await p.screenshot({path:SH+'03-price-table-example.png',fullPage:true});

// ---------- estimate builder
wp('transient delete --all');wp('ftt maillog --clear');
await p.goto(B+'/ftt-estimate/');
const total=()=>p.textContent('.ftt-est__grand td');
ok('estimate default line priced ($75.00 = 200 ft 8mm + setup, at minimum)',await total()==='$75.00',await total());
const L=(i,s)=>`[data-line="${i}"] ${s}`;
await p.selectOption(L(0,'select >> nth=0'),'s8');
await p.selectOption(L(0,'select >> nth=2'),'3');
await typeIn(L(0,'input[type=number] >> nth=0'),'2');
await p.check(L(0,'input[value=sound]'));
ok('line 1: 2 x 3in S8 per-reel + sound = $34.00',(await p.textContent('[data-line-price="0"]')).includes('$34.00'),await p.textContent('[data-line-price="0"]'));
ok('raised to $75 minimum with adjustment row',await total()==='$75.00'&&(await p.textContent('.ftt-est__summary')).includes('Order minimum ($75.00) adjustment'),await p.textContent('.ftt-est__summary'));
await p.click('[data-e=add]');
await p.selectOption(L(1,'select >> nth=0'),'16');
await p.selectOption(L(1,'select >> nth=1'),'4k');
await p.selectOption(L(1,'select >> nth=2'),'');
await typeIn(L(1,'input[type=number] >> nth=1'),'333');
await p.check(L(1,'input[value=repair]'));
await p.check('[data-e=order] input[value=usb]');
ok('two lines + USB = $255.50 in browser',await total()==='$255.50',await total());
const srv=await p.evaluate(async()=>{const st=window.FTT_EST;return null;});
// server preview with the same request the page builds
const prev=await p.evaluate(async()=>{const r=await fetch('/wp-json/ftt/v1/estimate/preview',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({request:{lines:[{format:'s8',service:'hd',reels:2,size:'3',addons:['sound']},{format:'16',service:'4k',reels:1,feet:333,addons:['repair']}],order_addons:['usb']}})});return r.json();});
ok('server preview total matches browser (25550 cents)',prev.totals&&prev.totals.total_cents===25550,JSON.stringify(prev.totals));
// invalid line shows message
await typeIn(L(1,'input[type=number] >> nth=0'),'0');
ok('invalid reels shows line error and disables send',(await p.textContent('[data-line-err="1"]')).includes('whole number')&&await p.isDisabled('[data-e=send]'));
await typeIn(L(1,'input[type=number] >> nth=0'),'1');
await p.screenshot({path:SH+'04-estimate-builder.png',fullPage:true});
await typeIn('[data-e=form] input[name=name]','Test Customer <b>bold</b>');
await typeIn('[data-e=form] input[name=email]','customer@example.com');
await typeIn('[data-e=form] textarea[name=notes]','Box of reels from grandpa. <script>alert(1)</script>');
await p.click('[data-e=send]');
ok('send without consent is blocked',(await p.textContent('[data-e=msg]')).includes('tick the box'));
await p.check('[data-e=form] input[name=consent]');
await p.click('[data-e=send]');
await p.waitForFunction(()=>/E-\d+/.test(document.querySelector('[data-e=msg]').textContent)||/wrong|Could not/.test(document.querySelector('[data-e=msg]').textContent),null,{timeout:15000});
let msg=await p.textContent('[data-e=msg]');ok('quote request accepted with reference and server total',/request E-\d+ was received/.test(msg)&&msg.includes('$255.50')&&!msg.includes('updated'),msg);
await p.screenshot({path:SH+'05-estimate-sent.png',fullPage:true});
const ref=+(msg.match(/E-(\d+)/)||[])[1];
ok('stored as PRIVATE ftt_estimate',wp(`post get ${ref} --field=post_status`).trim()==='private'&&wp(`post get ${ref} --field=post_type`).trim()==='ftt_estimate');
ok('stored server total 25550 and request lines',wp(`post meta get ${ref} _ftt_quote --format=json`).includes('"total_cents":25550'));
let ml=wp('ftt maillog');ok('no notify address -> mail log "skipped: no valid recipient", nothing sent',ml.includes('New estimate request E-'+ref)&&ml.includes('skipped: no valid recipient'),ml);
// API security checks
wp(`option update ftt_general '{"mail_mode":"log","notify_email":"owner@example.com"}' --format=json`);
const api=async(body,nonce=true,hdr={})=>p.evaluate(async([body,nonce,hdr])=>{let h={'Content-Type':'application/json',...hdr};if(nonce){const t=await (await fetch('/wp-json/ftt/v1/estimate/token',{credentials:'omit'})).json();h['X-FTT-Nonce']=t.nonce;}const r=await fetch('/wp-json/ftt/v1/estimate/request',{method:'POST',credentials:'omit',headers:h,body:JSON.stringify(body)});return {s:r.status,j:await r.json()};},[body,nonce,hdr]);
const good=(email,extra={})=>Object.assign({request:{lines:[{format:'16',service:'hd',reels:1,size:'7'}]},client_total_cents:12500,consent:1,website:'',contact:{name:'API Test',email,phone:'',notes:''}},extra);
let a=await api(good('a1@example.com'),false);ok('no nonce -> 403',a.s===403,JSON.stringify(a));
a=await api(good('a1@example.com'),false,{'X-FTT-Nonce':'deadbeef'});ok('bad nonce -> 403',a.s===403,JSON.stringify(a));
a=await api(good('a1@example.com',{website:'http://spam'}));ok('honeypot -> 400',a.s===400&&a.j.code==='ftt_hp',JSON.stringify(a));
a=await api(good('a1@example.com',{consent:0}));ok('no consent -> 400',a.s===400&&a.j.code==='ftt_consent');
a=await api(good('not-an-email'));ok('bad email -> 400',a.s===400&&a.j.code==='ftt_email');
a=await api(good('a2@example.com',{client_total_cents:1}));ok('tampered client total -> stored with server price, repriced flag',a.s===201&&a.j.repriced===true&&a.j.total_cents===12500,JSON.stringify(a.j).slice(0,200));
ml=wp('ftt maillog');ok('notify address set -> logged (not sent) to owner@example.com',ml.includes('owner@example.com')&&ml.includes('logged (not sent)'),ml);
a=await api(good('a3@example.com',{request:{lines:[{format:'16',service:'hd',reels:1,size:'99'}]}}));ok('invalid line -> 400 with line errors',a.s===400&&a.j.data&&a.j.data.errors&&a.j.data.errors[0].code==='bad_size',JSON.stringify(a));
a=await api(good('a4@example.com',{request:{lines:Array(60).fill({format:'16',service:'hd',feet:10})}}));ok('too many lines -> 400',a.s===400,JSON.stringify(a).slice(0,200));
let codes=[];for(let i=0;i<4;i++){a=await api(good('r'+i+'@example.com'));codes.push(a.s);}
ok('rate limit per IP kicks in (429) within 5/hour',codes.includes(429),codes.join(','));
const big=await p.evaluate(async()=>{const t=await (await fetch('/wp-json/ftt/v1/estimate/token',{credentials:'omit'})).json();const r=await fetch('/wp-json/ftt/v1/estimate/request',{method:'POST',credentials:'omit',headers:{'Content-Type':'application/json','X-FTT-Nonce':t.nonce},body:JSON.stringify({pad:'x'.repeat(40000)})});return r.status;});
ok('oversized body -> 413',big===413,big);
const tokHdr=await p.evaluate(async()=>{const r=await fetch('/wp-json/ftt/v1/estimate/token',{credentials:'omit'});return r.headers.get('cache-control');});
ok('token endpoint is no-store',/no-store/.test(tokHdr||''),tokHdr);
wp('transient delete --all');

// ---------- clip selector module (FluentCart not installed locally)
await p.goto(B+'/ftt-clips/');
ok('clip selector widget renders',await p.locator('.fwcc .fwcc-set').count()===2);
const cq=await p.evaluate(async()=>{const C=window.FTT_CLIP;const h={'Content-Type':'application/json','X-WP-Nonce':C.nonce};const body={product_id:25,clip_id:'FIRE-0001',in:1,out:11,fps:'29.97df'};
 const q=await (await fetch(C.rest+'/quote',{method:'POST',headers:h,body:JSON.stringify(body)})).json();
 const mm=await fetch(C.rest+'/add',{method:'POST',headers:h,body:JSON.stringify({...body,client_price_cents:1})});
 const nn=await fetch(C.rest+'/quote',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
 const nc=await (await fetch(C.rest+'/add',{method:'POST',headers:h,body:JSON.stringify(body)})).json();
 return {q,mm:mm.status,nn:nn.status,nc:nc.code,ns:C.rest};});
ok('clip REST under ftt/v1/clip',/\/ftt\/v1\/clip$/.test(cq.ns),cq.ns);
ok('clip quote re-priced on server: 10.01 s -> 11 s billable x $2 EXAMPLE = 2200 cents',cq.q.clip&&cq.q.clip.price_cents===2200&&/EXAMPLE/.test(cq.q.clip.rate_label),JSON.stringify(cq.q).slice(0,300));
ok('clip add with wrong client price -> 409',cq.mm===409);
ok('clip quote without nonce -> 403',cq.nn===403);
ok('clip add without FluentCart -> fwcc_no_cart',cq.nc==='fwcc_no_cart',cq.nc);
await p.screenshot({path:SH+'06-clip-selector.png',fullPage:true});

// ---------- RFQ flow
wp('ftt maillog --clear');
await p.goto(B+'/listings/');
const cb=n=>p.locator('.ftt-rfq-list label',{hasText:n}).locator('input');
await cb('Alpha Film Lab').check();await cb('Gamma Scans').check();await cb('Evil').check();
ok('provider picker bar shows 3/5',(await p.textContent('[data-rfq-bar]')).includes('3/5')&&await p.isVisible('[data-rfq-bar]'));
ok('hostile provider title escaped in picker',await p.locator('.ftt-rfq-list img').count()===0);
await p.screenshot({path:SH+'07-rfq-providers.png',fullPage:true});
await p.click('[data-rfq-go]');await p.waitForLoadState();
ok('request form lists 3 providers',await p.locator('.ftt-rfq-labs li').count()===3);
await typeIn('input[name="reels[s8]"]','4');await typeIn('input[name="feet[16]"]','800');
await p.selectOption('select[name=res]','4K');await typeIn('input[name=zip]','94110');
await typeIn('input[name=cname]','Rita Customer');await typeIn('input[name=cemail]','rita@example.com');
await typeIn('textarea[name=notes]','Please handle with care.');
await p.screenshot({path:SH+'08-rfq-form.png',fullPage:true});
await p.click('button[name=ftt_rfq_submit]');await p.waitForLoadState();
ok('RFQ submit -> check your email',(await p.textContent('.entry-content, main, body')).includes('Check your email'));
let full=wp('ftt maillog --full');const vlink=(full.match(/http:\/\/localhost:8088\/request-quotes\/\?ftt_rfq_verify=[a-f0-9]{48}/)||[])[0];
ok('verify email captured in mail log (not sent)',!!vlink&&full.includes('logged (not sent)'),full.slice(0,300));
await p.goto(vlink);
ok('verify link shows confirm button (does not auto-send)',await p.locator('button[name=ftt_rfq_confirm]').count()===1&&!wp('ftt maillog').includes('Quote request for'));
await p.click('button[name=ftt_rfq_confirm]');await p.waitForLoadState();
ok('confirmed -> sent to 3 providers',(await p.textContent('body')).includes('sent to 3 provider(s)'));
full=wp('ftt maillog --full');
ok('provider mails logged for Alpha + Evil, forward-needed for Gamma (no email)',/alpha@example.com \| Quote request for Alpha Film Lab/.test(full)&&full.includes('evil@example.com')&&full.includes('[Forward needed] Quote request for Gamma Scans'),full.slice(0,600));
ok('provider mail shows film summary + ZIP, no customer contact',/Super 8: 4 reel\(s\)/.test(full)&&full.includes('ZIP 94110')&&!/Quote request for Alpha[\s\S]{0,900}rita@example.com/.test(full.split('----').find(x=>x.includes('alpha@example.com | Quote request'))||''));
const replyAlpha=((full.split('----').find(x=>x.includes('alpha@example.com | Quote request'))||'').match(/http:\/\/localhost:8088\/rfq-reply\/\?t=[a-f0-9]{48}(?!&)/)||[])[0];
const gammaBlock=full.split('----').find(x=>x.includes('Forward needed] Quote request for Gamma'))||'';
const optGamma=(gammaBlock.match(/http:\/\/localhost:8088\/rfq-reply\/\?t=[a-f0-9]{48}&optout=1/)||[])[0];
const cmp=(full.match(/http:\/\/localhost:8088\/rfq-compare\/\?k=[a-f0-9]{48}/)||[])[0];
ok('reply, opt-out and compare links found',!!replyAlpha&&!!optGamma&&!!cmp,[replyAlpha,optGamma,cmp].join(' '));
await p.goto(vlink);ok('verify link cannot be reused',(await p.textContent('body')).includes('invalid or has already been used'));
await p.goto(replyAlpha);
await typeIn('input[name=price]','$123 total');await typeIn('input[name=turnaround]','2 weeks');await typeIn('textarea[name=notes]','Includes cleaning <b>x</b>');
await p.screenshot({path:SH+'09-rfq-provider-reply.png',fullPage:true});
await p.click('button[name=ftt_q_submit]');await p.waitForLoadState();
ok('provider quote accepted',(await p.textContent('body')).includes('your quote was sent'));
await p.goto(replyAlpha);ok('provider cannot quote twice',(await p.textContent('body')).includes('already sent a quote'));
await p.goto(cmp);
ok('compare page shows Alpha quote, notes sanitized (tags stripped)',(await p.textContent('.ftt-rfq-compare')).includes('$123 total')&&(await p.textContent('.ftt-rfq-compare')).includes('Includes cleaning x')&&await p.locator('.ftt-rfq-compare b').count()===0,await p.textContent('.ftt-rfq-compare'));
ok('compare: waiting on Gamma + Evil (escaped)',(await p.textContent('body')).includes('Waiting on:')&&await p.locator('.ftt-box img').count()===0);
await p.screenshot({path:SH+'10-rfq-compare.png',fullPage:true});
await p.click('button[name=ftt_rfq_contact]');await p.waitForLoadState();
ok('share contact -> logged mail to Alpha with customer details',(await p.textContent('body')).includes('We sent your name and email to Alpha Film Lab')&&/alpha@example.com \| Customer wants to proceed/.test(wp('ftt maillog')));
await p.goto(optGamma);await p.click('button[name=ftt_rfq_optout]');await p.waitForLoadState();
ok('provider opt-out works',(await p.textContent('body')).includes('will no longer receive')&&wp('post meta get 24 _ft_rfq_optout').trim()==='1');
await p.goto(B+'/rfq-reply/?t='+'a'.repeat(48));ok('bad reply token rejected',(await p.textContent('body')).includes('invalid'));
await p.goto(B+'/rfq-compare/?k=zz');ok('bad compare key rejected',(await p.textContent('body')).includes('invalid'));
const robots=await p.content();ok('RFQ pages are noindex',/noindex/.test(robots));

// ---------- [film_calculator] alias when the standalone plugin is inactive
const al=wp(`post create --post_type=page --post_status=publish --post_title=Alias --post_name=ftt-alias --post_content='[film_calculator]' --porcelain`).trim();
await p.goto(B+'/ftt-alias/');ok('[film_calculator] handled by standalone plugin while active',await p.locator('[data-filmcalc]').count()===1&&await p.locator('[data-ftt-calc]').count()===0);
wp('plugin deactivate film-calculator');await p.goto(B+'/ftt-alias/');
ok('[film_calculator] handled by toolkit when standalone is inactive',await p.locator('[data-ftt-calc]').count()===1);
await p.selectOption('[data-fc=format]','s8');await p.selectOption('[data-fc=speed]','18');await typeIn('[data-fc=feet]','50');
ok('alias calculator works (50 ft S8@18 = 3:20)',await p.inputValue('[data-fc=time]')==='3:20');
wp('plugin activate film-calculator');wp(`post delete ${al} --force`);

// ---------- admin
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();console.log('after login:',p.url(), await p.locator('#login_error').count()? (await p.textContent('#login_error')).trim():'');
await p.goto(B+'/wp-admin/admin.php?page=ftt');console.log('modules page:',p.url(),(await p.title()));
ok('Modules page lists 6 modules, all active',await p.locator('.ftt-modules tbody tr').count()===6&&await p.locator('.ftt-pill--on').count()===6);
await p.screenshot({path:SH+'11-admin-modules.png',fullPage:true});
await p.goto(B+'/wp-admin/admin.php?page=ftt-prices');
await p.click('button[value=draft]');await p.waitForLoadState();
ok('Load DRAFT preset -> notice + DRAFT banner',(await p.textContent('.notice-success')).includes('DRAFT FOR REVIEW prices loaded')&&(await p.textContent('.ftt-banner')).includes('DRAFT FOR REVIEW'));
await p.screenshot({path:SH+'12-admin-price-table-draft.png',fullPage:true});
await typeIn('input[name="rates[0][price]"]','0.33');await p.click('#submit');await p.waitForLoadState();
ok('edit rate via form -> saved as 330 mills',(await p.textContent('.notice-success')).includes('saved')&&wp('ftt export').includes('"price": 330'));
await typeIn('input[name="rates[0][price]"]','abc');await p.click('#submit');await p.waitForLoadState();
ok('invalid price -> error, nothing saved',(await p.textContent('.notice-error')).includes('is not a price')&&wp('ftt export').includes('"price": 330'));
await p.click('[data-ftt-add=tiers]');const nt=p.locator('[data-ftt-rows=tiers] tr').last();
await nt.locator('input').nth(0).click();await p.keyboard.type('10000');await nt.locator('input').nth(1).click();await p.keyboard.type('15');
await p.click('#submit');await p.waitForLoadState();
ok('add tier row (template, no innerHTML) -> saved',wp('ftt export').includes('"min_feet": 10000'));
await p.goto(B+'/ftt-prices/');ok('front price table now shows DRAFT FOR REVIEW banner',(await p.textContent('.ftt-banner')).includes('DRAFT FOR REVIEW'));
await p.screenshot({path:SH+'13-price-table-draft.png',fullPage:true});
await p.goto(B+'/ftt-estimate/');ok('estimate builder uses DRAFT prices (7in S8 = $128.00)',true);
await p.selectOption('[data-line="0"] select >> nth=0','s8');await p.selectOption('[data-line="0"] select >> nth=2','7');
ok('estimate builder on DRAFT table: 7in Super 8 HD = $128.00 total',await p.textContent('.ftt-est__grand td')==='$128.00',await p.textContent('.ftt-est__grand td'));
await p.screenshot({path:SH+'14-estimate-draft.png',fullPage:true});
await p.goto(B+`/wp-admin/post.php?post=${ref}&action=edit`);
const box=await p.textContent('#ftt_est_details');ok('admin request view: tags stripped on save, nothing rendered as HTML',box.includes('Name: Test Customer bold')&&box.includes('Box of reels from grandpa.')&&await p.locator('#ftt_est_details b, #ftt_est_details script').count()===0,box.slice(-600));
await p.screenshot({path:SH+'15-admin-quote-request.png',fullPage:true});
await p.goto(B+'/wp-admin/edit.php?post_type=ftt_estimate');ok('Quote requests list under toolkit menu',(await p.textContent('#the-list')).includes('Estimate request'));
await p.goto(B+'/wp-admin/admin.php?page=ftt-mail');ok('Mail log page lists logged mail',(await p.textContent('.wrap')).includes('Customer wants to proceed'));
await p.screenshot({path:SH+'16-admin-mail-log.png',fullPage:true});
await p.goto(B+'/wp-admin/admin.php?page=ftt-rfq');ok('RFQ admin lists request',(await p.textContent('.wrap')).includes('Rita Customer'));
await p.screenshot({path:SH+'17-admin-rfq.png',fullPage:true});
await p.goto(B+'/wp-admin/admin.php?page=ftt-clips');ok('Clip selector settings page (EXAMPLE rate card)',(await p.inputValue('#ftt-clip-label')).includes('EXAMPLE'));
// subscriber
const c2=await b.newContext();const s=await c2.newPage();
await s.goto(B+'/wp-login.php');await s.fill('#user_login','sub');await s.fill('#user_pass','sub');await s.click('#wp-submit');await s.waitForLoadState();
let rs=await s.goto(B+'/wp-admin/admin.php?page=ftt-prices');ok('subscriber cannot open price table admin',(await s.textContent('body')).match(/not allowed|permission/i)!==null);
rs=await s.request.post(B+'/wp-admin/admin-post.php',{form:{action:'ftt_load_preset',preset:'example'}});ok('subscriber admin-post preset load refused',rs.status()===403||rs.status()===200&&!(await rs.text()).includes('loaded'),rs.status());
rs=await s.goto(B+'/wp-admin/edit.php?post_type=ftt_estimate');ok('subscriber cannot list quote requests',(await s.textContent('body')).match(/not allowed|permission/i)!==null);
ok('draft still active after subscriber attempts',wp('ftt export').includes('DRAFT FOR REVIEW'));
await c2.close();
// admin-post without nonce as admin
rs=await p.request.post(B+'/wp-admin/admin-post.php',{form:{action:'ftt_load_preset',preset:'example'}});ok('admin preset load without nonce refused',(await rs.text()).match(/link you followed has expired|Are you sure/i)!==null,rs.status());

// mobile screenshots
const m=await b.newContext({viewport:{width:390,height:844},isMobile:true});const mp=await m.newPage();
for(const [u,f] of [['/ftt-estimate/','m-estimate'],['/ftt-reels/','m-reels'],['/ftt-prices/','m-prices'],['/request-quotes/?labs=22,23','m-rfq-form']]){await mp.goto(B+u);await mp.screenshot({path:SH+f+'.png',fullPage:true});}
await m.close();

ok('no JS errors',errs.length===0,errs.join(' | '));
const ext2=ext.filter(u=>!/^https:\/\/secure\.gravatar\.com\/avatar\//.test(u));ok('no external requests from the toolkit (core admin-bar gravatar excluded)',ext2.length===0,ext2.join(' '));
let trap='';try{trap=require('fs').readFileSync('/tmp/wp-mail-trap.log','utf8');}catch(e){}
ok('no wp_mail() call at all (log mode) - trap file empty',trap.trim()==='',trap);
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+' passed, '+out.filter(x=>x.startsWith('FAIL')).length+' failed');
await b.close();})().catch(e=>{console.log(out.join('\n'));console.error('CRASH',e);process.exit(1);});
