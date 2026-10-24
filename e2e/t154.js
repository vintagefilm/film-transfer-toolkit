// 1.5.4 e2e: Jump buttons in the Set In/Out rows (desktop + mobile wrap, keyboard), any-fps product page,
// "FTT Calculator" heading, admin delivery block. Screenshots in /workspace/ftt-shots/154/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/154/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
wp('transient delete --all');
const PROD=wp(`post create --post_type=fluent-products --post_status=private --post_title="T154 any-fps clip product" --porcelain`);
wp(`post meta update ${PROD} _fw_fps any`); wp(`post meta update ${PROD} _fw_clip_cart_enabled 1`);
const content=`<!-- wp:video --><figure class="wp-block-video"><video controls preload="auto" src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T154-VID" product_id="${PROD}" fps="50"]<!-- /wp:shortcode -->`;
fs.writeFileSync('/tmp/t154.html',content);
const PID=wp('post create /tmp/t154.html --post_type=page --post_status=publish --post_title="Clip 1.5.4 test" --porcelain');
fs.writeFileSync('/tmp/t154c.html','<!-- wp:shortcode -->[ftt_calculator]<!-- /wp:shortcode -->');
const CID=wp('post create /tmp/t154c.html --post_type=page --post_status=publish --post_title="Calc 1.5.4 test" --porcelain');
const rowCheck=async(v,label)=>{
  const S=v.locator('.fwcc').first();
  const bb=async s=>await S.locator(s).boundingBox();
  const si=await bb('.fwcc-set[data-which=in]'), ji=await bb('.fwcc-jump[data-which=in]'), so=await bb('.fwcc-set[data-which=out]'), jo=await bb('.fwcc-jump[data-which=out]');
  const pi=await bb('.fwcc-nudge[data-which=in] .fwcc-trim-btn:last-child'), po=await bb('.fwcc-nudge[data-which=out] .fwcc-trim-btn:last-child');
  const lastIn=await S.locator('.fwcc-nudge[data-which=in] .fwcc-trim-btn').last().textContent(), lastOut=await S.locator('.fwcc-nudge[data-which=out] .fwcc-trim-btn').last().textContent();
  ok(label+': trim rows end with +1s',lastIn.trim()==='+1s'&&lastOut.trim()==='+1s',lastIn+' '+lastOut);
  const mid=r=>r.y+r.height/2, same=(p,q)=>Math.abs(mid(p)-mid(q))<4, after=(j,p)=>(same(j,p)&&j.x>=p.x+p.width-1)||j.y>=p.y+p.height-2;
  ok(label+': Jump to In comes after the In +1s button',after(ji,pi),JSON.stringify([pi,ji]));
  ok(label+': Jump to Out comes after the Out +1s button',after(jo,po),JSON.stringify([po,jo]));
  ok(label+': Jump to In stays above the Out row',ji.y+ji.height<=so.y+2,JSON.stringify([ji,so]));
  const cw=await S.evaluate(x=>{const c=getComputedStyle(x);return x.clientWidth-parseFloat(c.paddingLeft)-parseFloat(c.paddingRight);});
  const fsz=await S.evaluate(x=>parseFloat(getComputedStyle(x).fontSize));
  if(cw>=Math.max(710,38*fsz)){ok(label+': Jump to In at the end of the In row (same line as Set In and +1s)',same(ji,pi)&&same(ji,si),JSON.stringify([si,pi,ji]));ok(label+': Jump to Out at the end of the Out row',same(jo,po)&&same(jo,so),JSON.stringify([so,po,jo]));}
  else {ok(label+' ('+cw+'px selector): Jump buttons line up (same left edge)',Math.abs(ji.x-jo.x)<2,JSON.stringify([ji.x,jo.x]));}
  ok(label+': Out row below In row',so.y>si.y+si.height-2);
  const ov=await v.evaluate(()=>{const m=document.querySelector('.fwcc-marks');return [m.scrollWidth,m.clientWidth,document.documentElement.scrollWidth,window.innerWidth];});
  ok(label+': no horizontal overflow',ov[0]<=ov[1]+1&&ov[2]<=ov[3]+1,JSON.stringify(ov));
  const txt=await S.locator('.fwcc-marks .button').evaluateAll(a=>a.map(b=>b.scrollWidth<=b.clientWidth+1));
  ok(label+': button labels not clipped',txt.every(Boolean),JSON.stringify(txt));
  ok(label+': touch size >= 24px',ji.height>=24&&jo.height>=24,ji.height);
};
(async()=>{const b=await chromium.launch();const errs=[];
try{
for(const [w,h,name] of [[1280,900,'desktop'],[390,844,'mobile-390'],[320,700,'mobile-320']]){
  const v=await (await b.newContext({viewport:{width:w,height:h},deviceScaleFactor:name==='desktop'?1:2})).newPage();v.on('pageerror',e=>errs.push(e.message));
  await v.goto(B+'/?page_id='+PID);await v.waitForSelector('.fwcc');
  await rowCheck(v,name);
  const S=v.locator('.fwcc').first();
  if(name==='desktop'){
    ok('any-fps product: page uses the video rate (data-fps=50)',await S.getAttribute('data-fps')==='50'&&await S.getAttribute('data-mode')==='cart',await S.getAttribute('data-fps')+' '+await S.getAttribute('data-mode'));
    ok('timecode note says 50 fps',/Timecode: 50 fps/.test(await S.locator('.fwcc-note').textContent()));
    ok('marks group labelled',await S.locator('.fwcc-marks').getAttribute('role')==='group'&&!!await S.locator('.fwcc-marks').getAttribute('aria-label'));
    const vid=v.locator('video').first();
    await vid.evaluate(x=>new Promise(r=>{x.currentTime=2.0;x.addEventListener('seeked',r,{once:true});}));
    await S.locator('.fwcc-set[data-which=in]').click();
    await vid.evaluate(x=>new Promise(r=>{x.currentTime=5.5;x.addEventListener('seeked',r,{once:true});}));
    await S.locator('.fwcc-set[data-which=out]').click();
    ok('50 fps timecode on In',/^00:00:02:00$/.test((await S.locator('[data-tc=in]').textContent()).trim()),await S.locator('[data-tc=in]').textContent());
    await vid.evaluate(x=>{x.currentTime=8;});await v.waitForTimeout(300);
    await S.locator('.fwcc-set[data-which=in]').focus();await v.keyboard.press('Shift+I');await v.waitForTimeout(400);
    ok('Shift+I jumps to In',Math.abs(await vid.evaluate(x=>x.currentTime)-2.0)<0.05);
    ok('Shift+I did not move the In mark',/^00:00:02:00$/.test((await S.locator('[data-tc=in]').textContent()).trim()));
    await v.keyboard.press('Shift+O');await v.waitForTimeout(400);
    ok('Shift+O jumps to Out',Math.abs(await vid.evaluate(x=>x.currentTime)-5.5)<0.05);
    const order=await S.locator('.fwcc-marks .button').evaluateAll(a=>a.map(x=>x.textContent.trim()).join('|'));
    ok('tab order: Set In, In trims ... +1s, Jump to In, Set Out, Out trims ... +1s, Jump to Out',/^Set In\|[^]*\+1s\|Jump to In\|Set Out\|[^]*\+1s\|Jump to Out$/.test(order),order);
    await S.locator('.fwcc-jump[data-which=in]').focus();
    ok('Jump to In focusable by keyboard',await v.evaluate(()=>document.activeElement.classList.contains('fwcc-jump')));
  }
  await S.scrollIntoViewIfNeeded();
  await S.screenshot({path:SH+({desktop:'01-desktop-set-jump-rows.png','mobile-390':'02-mobile-390-wrap.png','mobile-320':'03-mobile-320-wrap.png'})[name]});
  await v.close();
}
const c=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();
await c.goto(B+'/?page_id='+CID);await c.waitForSelector('.filmcalc');
const ct=await c.locator('.filmcalc').first().innerText();
ok('calculator default heading "FTT Calculator"',ct.includes('FTT Calculator')&&!ct.includes('Film Calculator'),ct.slice(0,120));
await c.locator('.filmcalc').first().screenshot({path:SH+'04-calculator-heading.png'});
// admin delivery block (as sent in the admin order email) and the order line text staff see
const html=cp.execSync(`cd /workspace/wp && wp eval '$c=FTT_Clip::build(${PROD},"T154-VID",12.5,20,"50",1,"Harbor"); add_filter("ftt/clip/show_delivery_details","__return_true"); $v=FTT_Clip::to_variation($c); $oi=new \\FluentCart\\App\\Models\\OrderItem(); $oi->forceFill(["post_id"=>${PROD},"post_title"=>$v["post_title"],"title"=>$v["title"],"object_id"=>$v["object_id"],"other_info"=>$v["other_info"]]); echo "<html><body style=\\"font-family:Arial;max-width:760px;margin:20px\\"><h3>Admin order line (staff view)</h3><p style=\\"font-size:13px\\">".esc_html($oi->post_title." - ".$oi->variation_display_title)."</p><h3>Admin order email block</h3>".FTT_Clip_FluentCart_Hooks::delivery_block([$c])."</body></html>";'`,{encoding:'utf8'});
fs.writeFileSync('/workspace/wp/ftt-test-media/t154-delivery.html',html);
ok('delivery block has ffmpeg at 50 fps',html.includes('-frames:v 375')&&html.includes('Frame rate: 50 fps'),html.slice(0,400));
await c.goto(B+'/ftt-test-media/t154-delivery.html');await c.screenshot({path:SH+'05-admin-delivery-details.png',fullPage:true});
}catch(e){ok('no exception',false,e.stack);}
finally{
ok('no page errors',errs.length===0,errs.join(' | '));
await b.close(); wp(`post delete ${PID} ${CID} ${PROD} --force`); try{fs.unlinkSync('/workspace/wp/ftt-test-media/t154-delivery.html');}catch(e){}
console.log(out.join('\n'));const f=out.filter(x=>x.startsWith('FAIL')).length;console.log(`${out.length-f}/${out.length} passed, ${f} failed`);}
})();
