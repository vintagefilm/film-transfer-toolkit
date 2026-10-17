// Capture bundled help illustrations (element screenshots of each tool with typical input).
const {chromium}=require('playwright');const cp=require('child_process');
const B='http://localhost:8088', OUT='/workspace/ftt-help-src/';
cp.execSync('cd /workspace/wp && wp ftt prices example 2>/dev/null');
(async()=>{
const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:820,height:1000},deviceScaleFactor:1});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(e.message));
async function typeIn(sel,text){await p.click(sel);await p.keyboard.press('Control+A');await p.keyboard.press('Backspace');await p.keyboard.type(String(text),{delay:15});}
async function shot(sel,name){const el=p.locator(sel).first();await el.scrollIntoViewIfNeeded();await p.waitForTimeout(300);await el.screenshot({path:OUT+name+'.png'});console.log('shot',name);}
await p.goto(B+'/ftt-calc/');await p.selectOption('[data-ftt-calc] [data-fc=format]','s8');await p.selectOption('[data-ftt-calc] [data-fc=speed]','18');await typeIn('[data-ftt-calc] [data-fc=feet]','400');await shot('[data-ftt-calc]','calculator');
await p.goto(B+'/ftt-reels/');await p.selectOption('[data-r=format]','s8');await p.selectOption('[data-r=size]','7');await typeIn('[data-r=count]','2');await shot('.ftt-reels','reels');
await p.goto(B+'/ftt-prices/');await shot('.ftt-prices','prices');
await p.goto(B+'/ftt-estimate/');await p.selectOption('[data-line="0"] select >> nth=0','s8');await p.selectOption('[data-line="0"] select >> nth=2','7');await typeIn('[data-line="0"] input[type=number] >> nth=0','3');await p.check('[data-line="0"] input[value=sound]');await shot('.ftt-est','estimate');
await p.goto(B+'/ftt-clips/');await shot('.fwcc','clips');
await p.goto(B+'/request-quotes/?labs=22,23');await shot('.ftt-rfq-form','rfq');
await p.goto(B+'/ftt-storage-test/');await p.waitForTimeout(500);await shot('.ftt-store','storage');
console.log('errors',JSON.stringify(errs));await b.close();})();
