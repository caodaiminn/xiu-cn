const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('assert'),fs=require('fs'),path=require('path');
const base='http://www.xiu.cn',out=path.join(__dirname,'artifacts');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const checks=[],errors=[];
 const create=async(ip,fail=false)=>{const context=await browser.newContext();await context.route('https://api*.ipify.org/**',route=>fail?route.abort():route.fulfill({contentType:'application/json',body:JSON.stringify({ip}),headers:{'access-control-allow-origin':'*'}}));const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));return {context,page};};
 const cityName=page=>page.locator('.header-city-location > span');
 try {
  const {context,page}=await create('113.243.0.1');await page.goto(base+'/');await assert.doesNotReject(()=>page.waitForFunction(()=>document.querySelector('.header-city-location > span')?.textContent==='郴州'));
  assert((await page.locator('.latest-panel h2').innerText()).includes('郴州'));assert.deepEqual([...new Set(await page.locator('.latest-list .muted').allTextContents())],['郴州']);
  assert.equal(await page.locator('.header-search').getAttribute('action'),'/chen-zhou/');
  assert.equal(await page.locator('.primary-nav a').filter({hasText:'分类信息'}).getAttribute('href'),'/chen-zhou/');
  assert((await page.locator('.category-parent').first().getAttribute('href')).startsWith('/chen-zhou/'));
  checks.push('first local visit uses browser public IP and filters homepage to Chenzhou');
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}]]) {await page.setViewportSize(viewport);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.screenshot({path:path.join(out,`ip-city-home-${name}.png`),fullPage:true});checks.push(name+' homepage layout');}
  await page.goto(base+'/selectcity.html');const changsha=page.locator('[data-city-item]').filter({hasText:/^长沙$/});const manualUrl=await changsha.getAttribute('href');await changsha.click();
  assert.equal(await cityName(page).innerText(),'长沙');await page.goto(base+'/');assert.equal(await cityName(page).innerText(),'长沙');checks.push('manual switch overrides IP on return to home');
  const cookies=(await context.cookies()).filter(c=>c.name==='xiu_city_choice');assert(cookies.length===1 && decodeURIComponent(cookies[0].value).startsWith('m:'));const second=await browser.newContext();await second.addCookies(cookies);const secondPage=await second.newPage();await secondPage.goto(base+'/');assert.equal(await cityName(secondPage).innerText(),'长沙');assert.equal(await secondPage.locator('[data-ip-city-location]').count(),0);checks.push('manual city persists without the original session');await second.close();
  await page.goto(base+'/selectcity.html');await page.getByRole('link',{name:'全国信息',exact:true}).click();await page.goto(base+'/');assert.equal(await cityName(page).innerText(),'全国');assert.equal(await page.locator('[data-ip-city-location]').count(),0);checks.push('explicit nationwide remains nationwide');
  const pending=await create('113.243.0.1',true);await pending.page.goto(base+'/');const token=await pending.page.locator('[data-ip-city-location]').getAttribute('data-csrf');
  await pending.context.request.get(base+manualUrl);const race=await pending.context.request.post(base+'/api/city-location/',{form:{csrf:token,ip:'113.243.0.1'}});assert.equal((await race.json()).city.name,'长沙');checks.push('late automatic request cannot overwrite manual selection');await pending.context.close();
  await context.close();
  const failed=await create('113.243.0.1',true);await failed.page.goto(base+'/');await failed.page.waitForFunction(()=>Number(sessionStorage.getItem('xiu-city-location-retry'))>Date.now());assert.equal(await cityName(failed.page).innerText(),'全国');checks.push('network failure keeps nationwide and manual switch usable');await failed.context.close();
  const unknown=await create('8.8.8.8');await unknown.page.goto(base+'/');await unknown.page.waitForFunction(()=>Number(sessionStorage.getItem('xiu-city-location-retry'))>Date.now());assert.equal(await cityName(unknown.page).innerText(),'全国');checks.push('foreign or unmatched IP remains nationwide');await unknown.context.close();
  const forbidden=await browser.newContext();const response=await forbidden.request.post(base+'/api/city-location/',{form:{ip:'113.243.0.1'}});assert.equal(response.status(),403);await forbidden.close();checks.push('location POST requires CSRF');
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'city-location-results.json'),JSON.stringify({passed:checks.length,checks,errors},null,2));console.log(JSON.stringify({passed:checks.length,checks}));
 }finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
