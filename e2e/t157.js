// 1.5.7 e2e: readability on light / dark palettes, dark page simulation, auto-contrast, override hook. Shots: /workspace/ftt-shots/157/
const {chromium}=require('playwright');const cp=require('child_process');const fs=require('fs');
const B='http://localhost:8088', SH='/workspace/ftt-shots/157/'; fs.mkdirSync(SH,{recursive:true});
const wp=(a)=>{try{return cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'}).trim();}catch(e){return '';}};
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
fs.writeFileSync('/tmp/t157.html',`<!-- wp:video --><figure class="wp-block-video"><video controls src="${B}/ftt-test-media/sample.webm"></video></figure><!-- /wp:video -->
<!-- wp:shortcode -->[ftt_clip_selector player="html5" clip_id="T157" fps="25"]<!-- /wp:shortcode -->
<!-- wp:shortcode -->[ftt_calculator]<!-- /wp:shortcode -->`);
const PID=wp('post create /tmp/t157.html --post_type=page --post_status=publish --post_title="Readability 1.5.7" --porcelain');
const LIGHT=':root{--global-palette1:#2b6cb0;--global-palette3:#1a202c;--global-palette4:#2d3748;--global-palette9:#ffffff}';
const DARK=':root{--global-palette1:#d4a24c;--global-palette3:#ffffff;--global-palette4:#e6e6e6;--global-palette9:#141414}body{background:#141414!important;color:#e6e6e6!important}';
const DARKPAGE='body,.site,.content-bg,.entry-content-wrap,.site-main{background:#2b2b2b!important;color:#eee!important}';
const scen=[
 ['light palette',LIGHT,false],
 ['dark palette (Kadence dark)',DARK,false],
 ['dark page simulation #2b2b2b, light palette',LIGHT+DARKPAGE,false],
 ['broken pair (transparent bg, dark text on dark page)',LIGHT+DARKPAGE+':root{--ftt-site-bg:transparent;--ftt-site-text:#333}',true],
 ['override hook --ftt-site-bg/--ftt-site-text',LIGHT+DARKPAGE+':root{--ftt-site-bg:#202830;--ftt-site-text:#f0f0f0}',false],
];
const lum=c=>{const v=c.slice(0,3).map(x=>{x/=255;return x<=.03928?x/12.92:Math.pow((x+.055)/1.055,2.4)});return .2126*v[0]+.7152*v[1]+.0722*v[2]};
(async()=>{const b=await chromium.launch();const errs=[];
try{for(const [w,tag] of [[1280,'desktop'],[390,'390']]){let i=0;for(const [name,css,expectAuto] of scen){i++;
 const v=await (await b.newContext({viewport:{width:w,height:900}})).newPage();v.on('pageerror',e=>errs.push(e.message));
 await v.addInitScript(c=>{document.addEventListener('DOMContentLoaded',()=>{const s=document.createElement('style');s.textContent=c;document.head.appendChild(s);});},css);
 await v.goto(B+'/?page_id='+PID,{waitUntil:'load'});await v.waitForTimeout(300);
 const r=await v.evaluate(()=>[...document.querySelectorAll('.ftt-tool')].map(t=>{const p=s=>{const m=s.match(/[\d.]+/g).map(Number);return m};
   const h=t.querySelector('.ftt-tool__title,.fwcc-heading,.filmcalc__title,h3,h4');const lab=t.querySelector('label,.fwcc-label,p');
   return {auto:t.classList.contains('ftt--auto-contrast'),ratio:+t.dataset.fttContrast,bg:p(getComputedStyle(t).backgroundColor),fg:p(getComputedStyle(t).color),h:h?p(getComputedStyle(h).color):null,cls:t.className}}));
 ok(`${tag} ${name}: tools found`,r.length>=2,r.length);
 for(const t of r){const L=a=>lum(a),cr=(a,b2)=>{const x=L(a),y=L(b2);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05)};
  const bg=t.bg.length>3&&t.bg[3]===0?null:t.bg; const label=`${tag} ${name} [${t.cls.split(' ').slice(0,2).join(' ')}]`;
  ok(label+': auto-contrast '+(expectAuto?'added':'not needed'),t.auto===expectAuto||(!expectAuto&&t.cls.includes('filmcalc--')),JSON.stringify(t));
  if(bg){ok(label+': text contrast >= 4.5',cr(t.fg,bg)>=4.5,cr(t.fg,bg).toFixed(2));if(t.h)ok(label+': heading contrast >= 4.5',cr(t.h,bg)>=4.5,cr(t.h,bg).toFixed(2));}
  else ok(label+': has an opaque background',false,JSON.stringify(t.bg));
 }
 await v.screenshot({path:`${SH}${String(i).padStart(2,'0')}-${tag}-${name.replace(/[^a-z0-9]+/gi,'-').replace(/-$/,'')}.png`,fullPage:true});await v.close();}}
 ok('no JS errors',errs.length===0,errs.join(' | '));
}catch(e){ok('run',false,e.stack);}finally{await b.close();wp('post delete '+PID+' --force');}
console.log(out.join('\n'));const p=out.filter(x=>x.startsWith('PASS')).length;console.log(`t157: ${p}/${out.length} passed`);})();
