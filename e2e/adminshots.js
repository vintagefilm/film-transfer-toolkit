// Screenshot every FTT admin page (full page) + the open FTT menu. Usage: node adminshots.js <outdir>
const {chromium}=require('playwright');const fs=require('fs');const B='http://localhost:8088';const SH=process.argv[2];fs.mkdirSync(SH,{recursive:true});
const PAGES=(process.env.PAGES||'ftt,ftt-setup,ftt-settings,ftt-prices,ftt-help,ftt-calculator,ftt-storage,ftt-clips,ftt-rfq,ftt-mail').split(',');
(async()=>{const b=await chromium.launch();const p=await (await b.newContext({viewport:{width:1280,height:900}})).newPage();
await p.goto(B+'/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','admin');await p.click('#wp-submit');await p.waitForLoadState();
let i=0;for(const pg of PAGES){const [slug,q]=pg.split('?');await p.goto(B+'/wp-admin/admin.php?page='+slug+(q?'&'+q:''));await p.waitForTimeout(400);
 const name=String(++i).padStart(2,'0')+'-'+pg.replace(/[?=&]/g,'-');await p.screenshot({path:SH+name+'.png',fullPage:true});console.log(name,p.url().replace(B,''));}
await p.goto(B+'/wp-admin/admin.php?page=ftt');const m=await p.$('#toplevel_page_ftt');await m.screenshot({path:SH+'00-menu.png'});
console.log('menu:',(await p.$$eval('#toplevel_page_ftt .wp-submenu li a',as=>as.map(a=>a.textContent.trim()))).join(' | '));await b.close();})();
