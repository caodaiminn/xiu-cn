const { chromium } = require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
(async () => {
 const origin=process.argv[2] || 'http://www.xiu.cn';
 const folder='reports/guides/2026-10-04';
 const fixture=JSON.parse(fs.readFileSync(folder+'/http-fixture.json','utf8'));
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const results=[];
 for(const width of [1440,390]) {
  const page=await browser.newPage({viewport:{width,height:1000}});
  for(const [name,path] of [['collection',fixture.collections.find(c=>c.category_id===234).path],['detail',Object.keys(fixture.details)[0]],['directory','/wei-xiu-zhi-nan/']]) {
   const response=await page.goto(origin+path,{waitUntil:'networkidle'});
   if(response.status()!==200) throw new Error('HTTP '+response.status());
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw new Error('Horizontal overflow '+name+' '+width);
   if(name==='collection' && await page.locator('.repair-guide-card').count()!==5) throw new Error('Guide count');
   if(name==='detail') {
    await page.locator('.repair-guide-toc a').first().click();
    if(!page.url().includes('#guide-section-0')) throw new Error('TOC link');
   }
   await page.screenshot({path:folder+'/ui-'+(origin.startsWith('https:')?'online':'local')+'-'+name+'-'+width+'.png',fullPage:name!=='directory'});
   results.push({name,width,passed:true});
  }
  await page.close();
 }
 await browser.close();fs.writeFileSync(folder+'/ui-results.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results));
})().catch(error=>{console.error(error);process.exit(1)});
