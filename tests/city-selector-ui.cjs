const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'}),checks=[],errors=[];
 const pass=(value,label)=>{assert(value,label);checks.push(label)};
 try {
  const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:900});await page.goto('http://www.xiu.cn/selectcity.html');
   pass(await page.locator('[data-city-item]').count()===393,width+' all city links rendered');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' no horizontal overflow');
   pass(await page.locator('[data-city-item]').evaluateAll(items=>items.every(e=>e.scrollHeight<=e.clientHeight+1)),width+' complete city names');
   await page.screenshot({path:'tests/artifacts/city-selector-'+width+'.png',fullPage:true});
   await page.getByLabel('查找城市',{exact:true}).fill('上海');
   pass(await page.locator('[data-city-item]:visible').count()===1,width+' Chinese city search');
   pass(await page.locator('[data-city-item]:visible').getAttribute('href')==='/shang-hai/',width+' root city URL');
   await page.getByLabel('查找城市',{exact:true}).fill('shanghai');
   pass(await page.locator('[data-city-item]:visible').count()===1,width+' joined pinyin search');
   await page.getByLabel('查找城市',{exact:true}).fill('gz');
   pass(await page.locator('[data-city-item]:visible').filter({hasText:'广州'}).count()===1,width+' initial-letter search');
   await page.getByLabel('查找城市',{exact:true}).fill('不存在的城市xyz');
   pass(await page.locator('#city-search-empty').isVisible(),width+' unmatched city feedback');
   await page.getByRole('button',{name:'清除',exact:true}).click();
   pass(await page.locator('[data-city-item]:visible').count()===393,width+' clearing restores all cities');
   await page.locator('.city-selector-index').getByRole('link',{name:'广东',exact:true}).click();
   pass(new URL(page.url()).hash==='#sheng-44',width+' province anchor navigation');
   await page.goto('http://www.xiu.cn/shang-hai/');pass(await page.locator('h1').innerText().then(t=>t.includes('上海')),width+' city listing works');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' city listing layout');
  }
  const nojs=await browser.newContext({javaScriptEnabled:false});const plain=await nojs.newPage();await plain.goto('http://www.xiu.cn/selectcity.html');
  pass(await plain.locator('[data-city-item]:visible').count()===393,'All cities accessible without JavaScript');
  await plain.locator('[data-city-item]').getByText('上海',{exact:true}).click();
  pass(plain.url()==='http://www.xiu.cn/shang-hai/','City navigation works without JavaScript');
  pass(errors.length===0,'No browser errors');
  fs.writeFileSync('tests/artifacts/city-selector-ui-results.json',JSON.stringify({passed:checks.length,checks,errors},null,2));
  console.log('SUCCESS',checks.length,'city selector browser checks.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
