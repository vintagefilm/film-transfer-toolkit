const {chromium}=require('playwright');
const B=process.env.B||'http://localhost:8088', SH=process.env.SH||'/workspace/ftt120-shots/', U=process.env.U||'admin';
(async()=>{const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:1280,height:900}});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(p.url()+' '+e.message));p.on('console',m=>{if(m.type()==='error')errs.push(p.url()+' console '+m.text())});
await p.goto(B+'/wp-login.php');await p.fill('#user_login',U);await p.fill('#user_pass',U);await p.click('#wp-submit');await p.waitForLoadState();
const pages=(process.env.PAGES||'ftt,ftt-settings,ftt-prices,ftt-help,ftt-help&tool=calculator,ftt-help&tool=storage,ftt-help&tool=rfq,ftt-help&tool=clips,ftt-setup').split(',');
for(const pg of pages){await p.goto(B+'/wp-admin/admin.php?page='+pg);const body=await p.textContent('body');
 const bad=/(Fatal error|Warning:|Notice:|Deprecated:|There has been a critical error)/.exec(body);console.log(pg, bad?'PHP-ISSUE '+bad[0]:'ok', await p.title());
 await p.screenshot({path:SH+'adm-'+pg.replace(/[&=]/g,'_')+'.png',fullPage:true});}
console.log('errors',JSON.stringify(errs));await b.close();})();
