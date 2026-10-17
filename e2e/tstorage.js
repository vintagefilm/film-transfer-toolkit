// Storage calculator local end-to-end test (real typing). node tstorage.js
const {chromium,devices}=require('playwright');
const B='http://localhost:8088', SH='/workspace/ftt-shots/storage-';
const cp=require('child_process');const wp=(a)=>cp.execSync('cd /workspace/wp && wp '+a+' 2>/dev/null',{encoding:'utf8'});
// 1.3.0: this suite checks the 1.1.0 maths from a Super 8 / ProRes 422 HQ HD start, so it pins those as the site defaults
// (the shipped 1.3.0 defaults are covered by t130.js) and removes the option again at the end.
wp(`option update ftt_storage '{"default_format":"s8","deliverables":[{"kind":"prores","profile":"hq","size":"hd","fps":"film","audio_kbps":"0"}]}' --format=json`);
const out=[];const ok=(n,c,d)=>{out.push((c?'PASS ':'FAIL ')+n+(d!==undefined&&!c?' :: '+String(d).slice(0,300):''));};
(async()=>{
const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:900}});const p=await ctx.newPage();
const errs=[],ext=[];p.on('pageerror',e=>errs.push(e.message));p.on('console',m=>{if(m.type()==='error'&&!/Failed to load resource/.test(m.text()))errs.push(m.text());});
p.on('request',r=>{const u=r.url();if(!u.startsWith(B)&&!u.startsWith('data:')&&!u.startsWith('blob:'+B))ext.push(u);});
const nf=[];p.on('response',r=>{if(r.status()>=400)nf.push(r.status()+' '+r.url());});
const R='[data-ftt-storage]';
const lab=(t,i=0)=>p.locator(R+' label.ftt-field').filter({has:p.locator('xpath=./span[normalize-space(.)='+JSON.stringify(t)+']')}).nth(i).locator('select,input');
async function typeIn(loc,text){await loc.click();await p.keyboard.press('Control+A');await p.keyboard.press('Backspace');await p.keyboard.type(String(text),{delay:30});}
const total=async()=>{const t=await p.getAttribute(R+' [data-s="total"]','aria-label');return (t||'').trim();};
const drive=async()=>(await p.textContent(R+' [data-s="drive"]')||'').trim();

await p.goto(B+'/ftt-storage-test/');await p.waitForSelector(R+' [data-s="total"]');
ok('default 1 x 7in Super 8 @18, ProRes HQ HD frame-for-frame = 26.4 GB (24.6 GiB)',await total()==='26.4 GB (24.6 GiB)',await total());
ok('default note says scaled from Apple 24p',/132 Mb\/s · scaled from Apple’s 24p figure/.test(await p.textContent(R+' [data-del-info="0"]')),await p.textContent(R+' [data-del-info="0"]'));
ok('default drive 128 GB',await drive()==='128 GB',await drive());
ok('estimate button hidden when no estimate on page',!(await p.locator(R+' [data-s="from-estimate"]').isVisible()));
ok('ProRes source link is Apple white paper',(await p.getAttribute(R+' [data-del-note="0"] a','href'))==='https://www.apple.com/final-cut-pro/docs/Apple_ProRes_White_Paper.pdf');
await typeIn(lab('How many reels'),'3');
ok('3 reels = 79.2 GB',(await total()).startsWith('79.2 GB'),await total());
await lab('Output frame rate').selectOption('24');
ok('output 24 fps (Apple 176 Mb/s) = 105.6 GB -> "106 GB"',(await total()).startsWith('106 GB'),await total());
ok('info shows 176 Mb/s unscaled',/^176 Mb\/s$/.test((await p.textContent(R+' [data-del-info="0"]')).trim()),await p.textContent(R+' [data-del-info="0"]'));
await p.click(R+' [data-s="add-del"]');
await typeIn(lab('Video bitrate (Mbps)'),'20');
ok('+ H.264 20 Mbps: total 117.6 GB -> "118 GB"',(await total()).startsWith('118 GB'),await total());
ok('drive 256 GB with 20% headroom',await drive()==='256 GB',await drive());
await p.click(R+' [data-s="add-del"]');
await lab('File type',2).selectOption('youtube');await lab('Resolution').selectOption('2160p');
ok('YouTube 4K at 18 fps uses 45 Mb/s (range 35-45)',/45 Mb\/s \(YouTube range 35–45\)/.test(await p.textContent(R+' [data-del-info="2"]')),await p.textContent(R+' [data-del-info="2"]'));
ok('three deliverables: 144.6 GB -> "145 GB"',(await total()).startsWith('145 GB'),await total());
ok('per-deliverable subtotals shown',(await p.locator(R+' tfoot tr').count())===4);
// second reel group: 16mm, 1 hour running time
await p.click(R+' [data-s="add-line"]');
await lab('Film format',1).selectOption('16');await lab('Film speed',1).selectOption('24');await lab('Length from',1).selectOption('time');
await typeIn(lab('Total running time (h:mm:ss)'),'1:00:00');
const t2=await total();
ok('+ 1 h of 16mm @24: 144.6 + 79.2 + 9 + 20.25 = 253.05 GB -> "253 GB"',t2.startsWith('253 GB'),t2);
ok('drive 500 GB',await drive()==='500 GB',await drive());
await typeIn(lab('Drive headroom (%)'),'0');
ok('0% headroom: needs 253 GB -> 256 GB drive',await drive()==='256 GB',await drive());
await typeIn(lab('Drive headroom (%)'),'20');
const sum=await p.textContent(R+' .ftt-store__sum');
ok('decimal and binary totals shown',/253\.05 GB/.test(sum)&&/235\.67 GiB/.test(sum),sum);
await p.screenshot({path:SH+'desktop.png',fullPage:true});
await p.locator(R+' [data-s="out"]').screenshot({path:SH+'results-closeup.png'});
await p.locator(R+' [data-s="dels"]').screenshot({path:SH+'deliverables-closeup.png'});
// errors
await typeIn(lab('How many reels'),'0');
ok('0 reels shows an error and drops that group',/whole number from 1 to 999/.test(await p.textContent(R+' [data-line-err="0"]'))&&(await total()).startsWith('108 GB'),await total());
await typeIn(lab('How many reels'),'3');
await lab('File type',1).selectOption('dpx');await lab('Frame size',1).selectOption('custom');
await typeIn(lab('Width (px)'),'8');
ok('custom width 8 px rejected',/Unknown frame size/.test(await p.textContent(R+' [data-del-err="1"]')),await p.textContent(R+' [data-del-err="1"]'));
await typeIn(lab('Width (px)'),'4096');await typeIn(lab('Height (px)'),'3112');await lab('Bit depth').selectOption('16');
ok('DPX 16-bit 4096x3112 frame size shown (76.5 MB per frame)',/76\.5 MB per frame/.test(await p.textContent(R+' [data-del-info="1"]')),await p.textContent(R+' [data-del-info="1"]'));
ok('sources list has 7 links',(await p.locator(R+' [data-s="sources"] a').count())===7);
// remove a deliverable
await p.locator(R).getByRole('button',{name:'Remove deliverable 2'}).click();
ok('remove deliverable leaves 2',(await p.locator(R+' [data-s="dels"] .ftt-line').count())===2);

// estimate + storage on one page
await p.goto(B+'/ftt-storage-estimate/');await p.waitForSelector(R+' [data-s="total"]');
ok('preset dpx-2k shows 12.7 MB per frame',/12\.7 MB per frame/.test(await p.textContent(R+' [data-del-info="0"]')),await p.textContent(R+' [data-del-info="0"]'));
ok('headroom attribute 25',(await lab('Drive headroom (%)').inputValue())==='25');
const fe=p.locator(R+' [data-s="from-estimate"]');
ok('"use estimate reels" button visible',await fe.isVisible());
await fe.click();
const est=await p.locator('[data-ftt-estimate] .ftt-line').count(), st=await p.locator(R+' [data-s="lines"] .ftt-line').count();
ok('storage got the estimate reel groups ('+est+')',est===st&&st>0,est+' vs '+st);
await p.screenshot({path:SH+'with-estimate.png',fullPage:true});

// mobile
const m=await b.newContext({...devices['Pixel 7']});const mp=await m.newPage();mp.on('pageerror',e=>errs.push(e.message));
await mp.goto(B+'/ftt-storage-test/');await mp.waitForSelector(R+' [data-s="total"]');
const ow=await mp.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1);
ok('mobile: no horizontal overflow',ow);
await mp.screenshot({path:SH+'mobile.png',fullPage:true});

// calculator badge (local stand-in for FilmTransfer.ai ft-core filter)
await p.goto(B+'/ftt-calc-badge/');
ok('calculator: badge inside the Film format label',await p.locator('.filmcalc__f-format > span .ftt-test-badge[data-cur="s8"]').count()===1);
ok('no JS errors',errs.length===0,errs.join(' | '));
ok('no 4xx/5xx for toolkit assets (WP core dist files missing locally are ignored: '+nf.length+')',nf.filter(x=>/film-transfer-toolkit|ftt/.test(x)).length===0,nf.join(' | '));
ok('no external requests',ext.length===0,ext.join(' '));
console.log(out.join('\n'));console.log(out.filter(x=>x.startsWith('PASS')).length+' passed, '+out.filter(x=>x.startsWith('FAIL')).length+' failed');await b.close();wp('option delete ftt_storage');
})().catch(e=>{console.log(out.join('\n'));console.error('CRASH',e.message);process.exit(1);});
