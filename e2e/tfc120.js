// Standalone film-calculator 1.2.0 front-end toggles. node tfc120.js
const {chromium}=require('playwright');
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1100,height:900}});const errs=[];p.on('pageerror',e=>errs.push(e.message));
await p.goto('http://localhost:8088/fc-standalone-toggles/');
const C=p.locator('[data-filmcalc]');ok('two standalone calculators',await C.count()===2);
ok('defaults: meters + frames visible',await C.nth(0).locator('[data-fc="meters"]').isVisible()&&await C.nth(0).locator('[data-fc="frames"]').isVisible());
ok('toggled: meters + frames hidden',!(await C.nth(1).locator('[data-fc="meters"]').isVisible())&&!(await C.nth(1).locator('[data-fc="frames"]').isVisible()));
ok('toggled: seconds gone, ft/min kept',await C.nth(1).locator('[data-fc="seconds"]').count()===0&&await C.nth(1).locator('[data-fc="ftmin"]').count()===1);
const f=C.nth(1).locator('[data-fc="feet"]');await f.click();await p.keyboard.press('Control+A');await p.keyboard.type('400',{delay:30});
const s=await C.nth(1).locator('[data-fc="summary"]').textContent();
ok('toggled summary: 400 ft = 11:06.67 at 24 fps, no meters/frames',/400 ft = 11:06\.67 at 24 fps/.test(s)&&!/frames/.test(s),s);
ok('defaults summary unchanged',/m\) = .*frames/.test(await C.nth(0).locator('[data-fc="summary"]').textContent()));
ok('no JS errors',errs.length===0,errs.join('|'));
await p.screenshot({path:'/workspace/ftt130-shots/standalone-film-calculator-1.2.0-toggles.png',fullPage:true});
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+' passed, '+out.filter(x=>x.startsWith('FAIL')).length+' failed');await b.close();})();
