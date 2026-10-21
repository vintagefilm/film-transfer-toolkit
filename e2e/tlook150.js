// 1.5.0 look and feel: every FTT tool shares one container (width, left edge, padding, radius, border), the theme font,
// one title size, one input style and one button style, on desktop and mobile, under Kadence (live theme) and Twenty Twenty-Five.
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt150-shots/look/';fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,400):''));};
const T0=wp('eval "echo get_stylesheet();"');const meta={};
(async()=>{const b=await chromium.launch();const errs=[];
try{
for(const theme of ['kadence','twentytwentyfive']){
 wp('theme activate '+theme);
 for(const [vw,vh,tag] of [[1280,900,'desktop'],[390,844,'mobile']]){
  const ctx=await b.newContext({viewport:{width:vw,height:vh},deviceScaleFactor:tag==='mobile'?2:1});const p=await ctx.newPage();p.on('pageerror',e=>errs.push(e.message));
  await p.goto(B+'/ftt-look/');await p.waitForTimeout(600);
  const m=await p.$$eval('.ftt-tool',els=>els.map(e=>{const r=e.getBoundingClientRect(),c=getComputedStyle(e);const t=e.querySelector('.ftt-box__title,.filmcalc__title,.ftt-tool__title,h3');const i=e.querySelector('input:not([type=checkbox]):not([type=radio]):not([type=hidden]),select');const btn=e.querySelector('.ftt-btn:not(.ftt-btn--ghost):not(.ftt-btn--link),.button:not(.ftt-btn--ghost),.filmcalc__switch');
   const cs=x=>x?getComputedStyle(x):null;const tc=cs(t),ic=cs(i),bc=cs(btn);
   return {cls:e.className.split(' ').filter(x=>x!=='ftt-tool')[0],x:Math.round(r.left),w:Math.round(r.width),pad:c.paddingLeft,rad:c.borderTopLeftRadius,bw:c.borderTopWidth,font:c.fontFamily,mt:c.marginTop,
     tsize:tc&&tc.fontSize,tfont:tc&&tc.fontFamily,irad:ic&&ic.borderTopLeftRadius,ih:ic&&Math.round(parseFloat(ic.height)),ifs:ic&&ic.fontSize,brad:bc&&bc.borderTopLeftRadius,bbg:bc&&bc.backgroundColor};}));
  meta[theme+'-'+tag]=m;
  const names=m.map(x=>x.cls).join(',');
  ok(`[${theme} ${tag}] all 7 tools on the page (${names})`,m.length===7,names);
  const same=(k)=>new Set(m.map(x=>x[k])).size===1;
  for(const [k,label] of [['x','left edge'],['w','width'],['pad','padding'],['rad','corner radius'],['bw','border'],['font','font family (theme)'],['mt','outer margin']]) ok(`[${theme} ${tag}] same ${label}`,same(k),JSON.stringify(m.map(x=>x.cls+'='+x[k])));
  const bodyFont=await p.evaluate(()=>getComputedStyle(document.querySelector('.entry-content,.wp-block-post-content,main')||document.body).fontFamily);
  ok(`[${theme} ${tag}] tools use the theme's font`,m.every(x=>x.font===bodyFont),bodyFont+' vs '+m[0].font);
  const tit=m.filter(x=>x.tsize);ok(`[${theme} ${tag}] same title size`,new Set(tit.map(x=>x.tsize)).size===1,JSON.stringify(tit.map(x=>x.cls+'='+x.tsize)));
  const inp=m.filter(x=>x.irad);ok(`[${theme} ${tag}] same input radius, height and font size`,new Set(inp.map(x=>x.irad+'/'+x.ih+'/'+x.ifs)).size===1,JSON.stringify(inp.map(x=>x.cls+'='+x.irad+'/'+x.ih+'/'+x.ifs)));
  const bt=m.filter(x=>x.brad);ok(`[${theme} ${tag}] same button radius and colour`,new Set(bt.map(x=>x.brad+'/'+x.bbg)).size===1,JSON.stringify(bt.map(x=>x.cls+'='+x.brad+'/'+x.bbg)));
  const cw=await p.evaluate(()=>{const c=document.querySelector('.ftt-tool').parentElement;const r=c.getBoundingClientRect();return Math.round(r.width);});
  ok(`[${theme} ${tag}] tools fit the content area (no overflow)`,m.every(x=>x.w<=cw+1)&&await p.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),cw);
  const els=await p.$$('.ftt-tool');let i=0;for(const e of els){await e.screenshot({path:`${SH}${theme}-${tag}-${String(++i).padStart(2,'0')}-${m[i-1].cls}.png`});}
  await p.screenshot({path:`${SH}${theme}-${tag}-full-page.png`,fullPage:true});
  await ctx.close();
 }
}
ok('no JS errors',errs.length===0,errs.join(' | '));
}finally{wp('theme activate '+T0);await b.close();}
fs.writeFileSync(SH+'measurements.json',JSON.stringify(meta,null,1));
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+'/'+out.length+' passed');})();
