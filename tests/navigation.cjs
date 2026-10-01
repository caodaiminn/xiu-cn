const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const out=path.join(__dirname,'artifacts');const fixture=JSON.parse(fs.readFileSync(path.join(out,'navigation-fixture.json'),'utf8'));
const base='http://www.xiu.cn';
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const context=await browser.newContext();await context.addCookies([{name:'xiu_session',value:fixture.session,url:base}]);
 const page=await context.newPage(),publicPage=await context.newPage();const errors=[],checks=[];page.on('pageerror',e=>errors.push(e.message));
 const route='/dgadmin/navigation/';const first=fixture.ids[0],second=fixture.ids[1];
 const action=async(id,name)=>{await page.goto(base+route);await Promise.all([page.waitForNavigation(),page.locator(`[data-navigation-id="${id}"]`).getByRole('button',{name,exact:true}).click()]);};
 try {
  await page.goto(base+route);assert.equal(await page.locator('h1').innerText(),'导航栏管理');assert(await page.locator('.admin-nav a.active').count());checks.push('admin entry and navigation list');
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}],['small-mobile',{width:320,height:760}]]){
   await page.setViewportSize(viewport);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' admin overflow');
   await page.screenshot({path:path.join(out,`navigation-admin-${name}.png`),fullPage:true});
   await publicPage.setViewportSize(viewport);await publicPage.goto(base+'/');assert(await publicPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' public overflow');
   await publicPage.locator('.primary-nav').screenshot({path:path.join(out,`navigation-public-${name}.png`)});checks.push(name+' admin and front layout');
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.locator('[data-navigation-preset]').selectOption('/fa-bu-xin-xi/');assert.equal(await page.locator('input[name="url"]').inputValue(),'/fa-bu-xin-xi/');assert.equal(await page.locator('input[name="label"]').inputValue(),'免费发布信息');checks.push('built-in function shortcut');
  await page.locator('input[name="label"]').fill(fixture.newLabel);await page.locator('input[name="sort_order"]').fill('90');
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'保存导航',exact:true}).click()]);
  await publicPage.goto(base+'/');assert(await publicPage.locator('.primary-nav a').filter({hasText:fixture.newLabel}).count());checks.push('add navigation appears immediately');
  await page.goto(base+route+'?edit='+first);await page.locator('input[name="label"]').fill(fixture.token+'_edited');
  await page.locator('input[name="url"]').fill('https://example.com/help');await page.locator('select[name="new_tab"]').selectOption('1');
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'保存导航',exact:true}).click()]);
  await publicPage.goto(base+'/');const external=publicPage.locator('.primary-nav a').filter({hasText:fixture.token+'_edited'});
  assert.equal(await external.getAttribute('href'),'https://example.com/help');assert.equal(await external.getAttribute('target'),'_blank');assert((await external.getAttribute('rel')).includes('noopener'));checks.push('edit name link and new window');
  await action(first,'下移');const ids=await page.locator('[data-navigation-id]').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.navigationId)));assert(ids.indexOf(first)>ids.indexOf(second));checks.push('move handles identical sort values');
  await action(first,'上移');const moved=await page.locator('[data-navigation-id]').evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.navigationId)));assert(moved.indexOf(first)<moved.indexOf(second));checks.push('move up restores order');
  await action(first,'隐藏');await publicPage.goto(base+'/');assert.equal(await publicPage.locator('.primary-nav a').filter({hasText:fixture.token+'_edited'}).count(),0);checks.push('hide invalidates frontend cache');
  await action(first,'显示');await publicPage.goto(base+'/');assert.equal(await publicPage.locator('.primary-nav a').filter({hasText:fixture.token+'_edited'}).count(),1);checks.push('show navigation immediately');
  await page.goto(base+route+'?edit='+first);const csrf=await page.locator('input[name="csrf"]').first().inputValue();
  const bad=await context.request.post(base+route,{form:{csrf,id:String(first),label:fixture.token+'_edited',url:'javascript:alert(1)',sort_order:'60',enabled:'1',match_mode:'exact'}});
  assert((await bad.text()).includes('链接须以'));await publicPage.goto(base+'/');assert.equal(await publicPage.locator('.primary-nav a').filter({hasText:fixture.token+'_edited'}).getAttribute('href'),'https://example.com/help');checks.push('unsafe link rejected and old link retained');
  const newRow=page.locator('.navigation-row').filter({hasText:fixture.newLabel});await page.goto(base+route);page.once('dialog',dialog=>dialog.accept());
  await Promise.all([page.waitForNavigation(),newRow.getByRole('button',{name:'删除',exact:true}).click()]);await publicPage.goto(base+'/');assert.equal(await publicPage.locator('.primary-nav a').filter({hasText:fixture.newLabel}).count(),0);checks.push('delete navigation leaves site functional');
  await publicPage.goto(base+'/wei-xiu-bai-ke/');assert.equal(await publicPage.locator('.primary-nav a[aria-current="page"]').innerText(),'维修百科');checks.push('selected navigation matches current section');
  const anon=await browser.newContext();const denied=await anon.request.get(base+route,{maxRedirects:0});assert.equal(denied.status(),303);assert.equal(denied.headers().location,'/dgadmin/login/');
  const invalid=await anon.request.post(base+route,{form:{id:String(first),action:'delete'}});assert.equal(invalid.status(),403);await anon.close();checks.push('admin login and CSRF protection');
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'navigation-results.json'),JSON.stringify({passed:checks.length,checks,errors},null,2));console.log(JSON.stringify({passed:checks.length,checks}));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
