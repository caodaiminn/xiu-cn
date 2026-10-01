const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const out=path.join(__dirname,'artifacts'),fixturePath=path.join(out,'ai-description-fixture.json');
const fixture=JSON.parse(fs.readFileSync(fixturePath,'utf8')),base='http://www.xiu.cn',adminRoute='/dgadmin/index.php?section=ai';
const checks=[],errors=[],contexts=[];
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try {
  const admin=await browser.newContext();contexts.push(admin);await admin.addCookies([{name:'xiu_session',value:fixture.admin_session,url:base}]);const settings=await admin.newPage();settings.on('pageerror',e=>errors.push(e.message));
  assert.equal((await settings.goto(base+adminRoute)).status(),200);assert.equal(await settings.locator('h1').innerText(),'AI生成设置');assert.equal(await settings.locator('[name=api_key]').inputValue(),'');assert(!(await settings.content()).includes('qa-only-fake-api-key'));checks.push('admin settings and key not exposed');
  await settings.locator('[data-ai-model-preset]').selectOption('glm-5.3');assert.equal(await settings.locator('[name=model]').inputValue(),'glm-5.3');await settings.locator('[name=model]').fill('custom-model-qa');assert.equal(await settings.locator('[data-ai-model-preset]').inputValue(),'');
  await Promise.all([settings.waitForNavigation(),settings.getByRole('button',{name:'保存AI设置'}).click()]);assert.equal(await settings.locator('[name=model]').inputValue(),'custom-model-qa');assert((await settings.locator('[name=api_key]').getAttribute('placeholder')).includes('已设置'));checks.push('preset and custom model saved with blank key retained');
  const user=await browser.newContext();contexts.push(user);await user.addCookies([{name:'xiu_session',value:fixture.user_session,url:base}]);const page=await user.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/fa-bu-xin-xi/');await page.locator('#listing-category').selectOption(String(fixture.category),{force:true});await page.locator('[data-publish-category-next]').click();
  const button=page.locator('[data-ai-description]'),title=page.locator('[name=title]'),body=page.locator('[name=body]'),status=page.locator('[data-ai-status]');assert.equal(await button.getAttribute('type'),'button');assert(await button.isEnabled());checks.push('AI button beside detailed description');
  for(const width of [1440,390,320]) {await page.setViewportSize({width,height:1000});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));const heading=await page.locator('.publish-description-heading').evaluate(e=>{const a=e.querySelector('label').getBoundingClientRect(),b=e.querySelector('button').getBoundingClientRect();return Math.abs((a.top+a.bottom)/2-(b.top+b.bottom)/2)<2&&a.right<b.left});assert(heading);await page.screenshot({path:path.join(out,'ai-description-publish-'+width+'.png'),fullPage:true});checks.push(width+' AI button layout');}
  let captured=[],calls=0,mode='success',release;
  await page.route('**/api/ai-description/',async route=>{
   calls++;captured.push(Object.fromEntries(new URLSearchParams(route.request().postData())));
   if(mode==='network')return route.abort('failed');
   if(mode==='delayed')await new Promise(resolve=>release=resolve);
   if(mode==='failure')return route.fulfill({status:502,contentType:'application/json',body:JSON.stringify({ok:false,message:'AI生成失败，原描述已保留。'})});
   if(mode==='html')return route.fulfill({status:403,contentType:'text/html',body:'Expired'});
   return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,body:captured.at(-1).type==='request'?'家中空调出现不制冷的情况，希望师傅上门检查并确认故障原因。\n维修项目和时间可沟通确认。':'提供空调清洗和常见故障检修服务，可沟通设备情况及上门安排。\n具体服务范围可沟通确认。'})});
  });
  await title.fill('短');await button.click();assert((await status.innerText()).includes('5–120'));assert.equal(calls,0);checks.push('title validation avoids request');
  await title.fill('郴州空调清洗维修服务');await body.fill('');await button.click();await page.waitForFunction(()=>document.querySelector('[data-ai-status]').textContent.includes('已生成'));assert((await body.inputValue()).startsWith('提供空调清洗'));assert.equal(captured[0].title,'郴州空调清洗维修服务');assert.equal(captured[0].type,'service');assert(captured[0].csrf);assert(!('api_key' in captured[0]));checks.push('service generation fills body from title');
  const original=await body.inputValue();page.once('dialog',d=>d.dismiss());await button.click();assert.equal(calls,1);assert.equal(await body.inputValue(),original);checks.push('replacement cancellation preserves body');
  await page.locator('[name=type][value=request]').check();await title.fill('郴州空调不制冷需要维修');page.once('dialog',d=>d.accept());await button.click();await page.waitForFunction(()=>document.querySelector('[name=body]').value.startsWith('家中空调'));assert.equal(captured.at(-1).type,'request');checks.push('request generation uses selected type');
  for(const failure of ['failure','network','html']){mode=failure;const before=await body.inputValue();page.once('dialog',d=>d.accept());await button.click();await page.waitForFunction(()=>!document.querySelector('[data-ai-description]').disabled);assert.equal(await body.inputValue(),before);assert((await status.innerText()).includes('原描述已保留'));checks.push(failure+' preserves previous description');}
  mode='delayed';await body.fill('');await button.click();await page.waitForFunction(()=>document.querySelector('[data-ai-description]').disabled);while(!release)await new Promise(resolve=>setTimeout(resolve,10));assert.equal(await button.innerText(),'生成中…');const beforeCalls=calls;await button.dispatchEvent('click');assert.equal(calls,beforeCalls);await body.fill('用户正在手动填写的描述应当保留。');release();await page.waitForFunction(()=>!document.querySelector('[data-ai-description]').disabled);assert.equal(await body.inputValue(),'用户正在手动填写的描述应当保留。');checks.push('loading blocks duplicate generation and preserves concurrent editing');
  mode='success';await body.fill('生成后手动修改内容。');assert.equal(await body.inputValue(),'生成后手动修改内容。');checks.push('generated content remains editable');
  const csrf=await page.locator('[name=csrf]').first().inputValue();
  assert.equal((await user.request.post(base+'/api/ai-description/',{form:{title:'郴州空调清洗维修服务',type:'service'}})).status(),403);
  assert.equal((await user.request.post(base+'/api/ai-description/',{form:{csrf,title:'短',type:'service'}})).status(),422);
  assert.equal((await user.request.get(base+'/api/ai-description/')).status(),405);checks.push('real endpoint CSRF title and method checks');
  const forbidden=await user.request.get(base+adminRoute,{maxRedirects:0});assert.equal(forbidden.status(),303);checks.push('member cannot change AI settings');
  const anon=await browser.newContext();contexts.push(anon);assert.equal((await anon.request.post(base+'/api/ai-description/',{form:{title:'郴州空调清洗维修服务',type:'service'}})).status(),401);checks.push('generation requires signed-in member');
  await settings.locator('[name=enabled]').selectOption('0');await settings.locator('[data-ai-model-preset]').selectOption('glm-4.7-flash');await Promise.all([settings.waitForNavigation(),settings.getByRole('button',{name:'保存AI设置'}).click()]);
  const disabled=await user.request.post(base+'/api/ai-description/',{form:{csrf,title:'郴州空调清洗维修服务',type:'service'}});assert.equal(disabled.status(),503);assert.equal((await disabled.json()).ok,false);await page.goto(base+'/fa-bu-xin-xi/');assert(await page.locator('[data-ai-description]').isDisabled());checks.push('admin switch disables generation and manual description remains');
  await settings.setViewportSize({width:1440,height:1000});await settings.screenshot({path:path.join(out,'ai-description-admin.png'),fullPage:true});
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'ai-description-browser-results.json'),JSON.stringify({passed:checks.length,checks,errors,generation:'browser mocked; live key not provided'},null,2));console.log(JSON.stringify({passed:checks.length,checks}));
 } finally {fixture.extraSessions=[];for(const context of contexts)for(const cookie of await context.cookies())if(cookie.name==='xiu_session')fixture.extraSessions.push(cookie.value);fs.writeFileSync(fixturePath,JSON.stringify(fixture,null,2));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
