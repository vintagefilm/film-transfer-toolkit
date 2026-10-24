// 1.5.10 e2e: each clip selector row (Set, timecode, Trim label, -1s -1f +1f +1s, Jump) is ONE line whenever the selector is >= 710px wide,
// wraps only below that, never overflows, at several viewport widths and at the default 800px tool width. Screenshots in /workspace/ftt-shots/1510/.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/1510/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
wp('transient delete --all');
fs.writeFileSync('/tmp/t1510.html','<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T1510-VID" fps="25"]<!-- /wp:shortcode -->');
const PID=wp('post create /tmp/t1510.html --post_type=page --post_status=publish --post_title="Clip 1.5.10 layout test" --porcelain');
(async()=>{const b=await chromium.launch();const errs=[];
try{
const v=await (await b.newContext({viewport:{width:1920,height:900}})).newPage();v.on('pageerror',e=>errs.push(e.message));
await v.goto(`${B}/?page_id=${PID}`);await v.waitForSelector('.fwcc-marks');await v.waitForTimeout(800);
for(const w of [1920,1440,1366,1280,1024,900,820,700,600,390,320]){
  await v.setViewportSize({width:w,height:900});await v.waitForTimeout(250);
  const r=await v.evaluate(()=>{const S=document.querySelector('.fwcc'),m=S.querySelector('.fwcc-marks');const cs=getComputedStyle(S);const cw=S.clientWidth-parseFloat(cs.paddingLeft)-parseFloat(cs.paddingRight);
    const mid=e=>{const b=e.getBoundingClientRect();return b.y+b.height/2;};
    const row=w=>[...m.children].filter(c=>c.dataset.which===w||c.dataset.tc===w||c.querySelector&&0).length;
    const kids=[...m.children];const rows=[kids.slice(0,5),kids.slice(5,10)].map(a=>Math.max(...a.map(mid))-Math.min(...a.map(mid)));
    const labels=kids.filter(k=>k.matches('.button')).every(b=>b.scrollWidth<=b.clientWidth+1);
    return {cw:Math.round(cw),spread:rows.map(Math.round),overflow:m.scrollWidth>m.clientWidth+1||document.documentElement.scrollWidth>window.innerWidth+1,labels,ys:[kids[0],kids[4],kids[5],kids[9]].map(k=>Math.round(k.getBoundingClientRect().y))};});
  const one=r.spread.every(s=>s<6);
  if(r.cw>=710) ok(`${w}px viewport (${r.cw}px selector): In row and Out row are each ONE line`,one,JSON.stringify(r));
  else ok(`${w}px viewport (${r.cw}px selector): narrow, wraps without overflow`,!r.overflow,JSON.stringify(r));
  ok(`${w}px: no horizontal overflow, labels not clipped`,!r.overflow&&r.labels,JSON.stringify(r));
  ok(`${w}px: Out row below In row`,r.ys[2]>r.ys[0]);
  if([1440,1280,820,390].includes(w)){const el=await v.$('.fwcc');await el.scrollIntoViewIfNeeded();await el.screenshot({path:`${SH}dev-${w}.png`});}
}
// theme-independent: forced selector widths around the breakpoint and a larger theme text size
for(const [fs_,cw] of [[16,712],[16,760],[18,712],[18,800],[20,800]]){
  await v.setViewportSize({width:1920,height:900});
  const r=await v.evaluate(([fs_,cw])=>{const S=document.querySelector('.fwcc');S.style.fontSize=fs_+'px';const pad=parseFloat(getComputedStyle(S).paddingLeft)+1;S.style.maxWidth=S.style.width=(cw+2*pad)+'px';
    const m=S.querySelector('.fwcc-marks'),mid=e=>{const b=e.getBoundingClientRect();return b.y+b.height/2;};const kids=[...m.children];
    return {spread:[kids.slice(0,5),kids.slice(5,10)].map(a=>Math.round(Math.max(...a.map(mid))-Math.min(...a.map(mid)))),overflow:m.scrollWidth>m.clientWidth+1};},[fs_,cw]);
  ok(`text ${fs_}px, selector ${cw}px: one line each, no overflow`,r.spread.every(s=>s<6)&&!r.overflow,JSON.stringify(r));
}
ok('no page errors',errs.length===0,errs.join('|'));
}catch(e){ok('script ran',false,e.stack);}
await b.close();wp(`post delete ${PID} --force`);
console.log(out.join('\n'));const f=out.filter(l=>l.startsWith('FAIL')).length;console.log(`${out.length-f}/${out.length} passed`);process.exit(f?1:0);})();
