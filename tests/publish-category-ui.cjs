const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),base='http://www.xiu.cn',out='tests/artifacts/';
const token='xiuqa_'+Date.now(),username=token+'_category',password='QACategory2026',checks=[];
const fixtures={token,users:[username],listings:[],articles:[],categories:[],cities:[],districts:[]};fs.writeFileSync(out+'fixtures.json',JSON.stringify(fixtures));
const pass=(value,label)=>{assert(value,label);checks.push(label);console.log('PASS',label)};
const remember=id=>{if(!fixtures.listings.includes(id)){fixtures.listings.push(id);fs.writeFileSync(out+'fixtures.json',JSON.stringify(fixtures));}};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/zhu-ce/');await page.getByLabel('账号',{exact:true}).fill(username);await page.getByLabel('密码',{exact:true}).fill(password);await page.getByLabel('确认密码',{exact:true}).fill(password);
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'注册并登录',exact:true}).click()]);
  const r=await page.goto(base+'/fa-bu-xin-xi/'),source=await r.text();
  const categories=await page.evaluate(source=>Array.from(new DOMParser().parseFromString(source,'text/html').querySelectorAll('#listing-category option[data-parent]')).map(option=>({id:option.value,parent:option.dataset.parent,depth:Number(option.dataset.depth),name:option.dataset.name})),source);
  const rootLevel=page.locator('[data-publish-category-level="1"]'),secondLevel=page.locator('[data-publish-category-level="2"]'),thirdLevel=page.locator('[data-publish-category-level="3"]');
  const next=page.getByRole('button',{name:'下一步',exact:true}),choose=id=>page.locator('[data-publish-category-id="'+id+'"]');
  pass(await page.locator('#publish-category-picker').isVisible() && await page.locator('#publish-details').isHidden(),'New publication starts at category selection');
  pass(await next.isDisabled(),'Next is disabled before selecting category');
  pass(await rootLevel.locator('button').count()===categories.filter(item=>item.depth===1).length,'All active first-level categories are displayed');
  for(const root of categories.filter(item=>item.depth===1)){
   await choose(root.id).click();const actual=await secondLevel.locator('button').evaluateAll(nodes=>nodes.map(node=>node.dataset.publishCategoryId));
   pass(JSON.stringify(actual)===JSON.stringify(categories.filter(item=>item.parent===root.id).map(item=>item.id)),root.name+' displays only its own second-level categories');
   pass(await rootLevel.locator('.is-selected').count()===1 && await choose(root.id).getAttribute('aria-pressed')==='true',root.name+' has one selected first-level button');
  }
  await choose('1').click();pass(await next.isDisabled(),'First-level category with children requires second-level choice for new publication');
  await choose('2').click();pass(await thirdLevel.isVisible() && await next.isEnabled(),'Second-level selection reveals optional third level and permits next');
  await choose('3').focus();await page.keyboard.press('Enter');
  pass(await choose('3').getAttribute('aria-pressed')==='true' && await choose('3').evaluate(node=>node===document.activeElement),'Keyboard selection preserves focus and selection state');
  pass((await page.locator('.publish-category-selection').innerText()).includes('家电、厨电维修 › 空调维修 › 家用空调'),'Selected path shows all three category names');
  for(const width of [1440,900,390,320]){
   await page.setViewportSize({width,height:1000});pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' category panel no horizontal overflow');
   const columns=await rootLevel.locator('.publish-category-grid').evaluate(node=>getComputedStyle(node).gridTemplateColumns.split(' ').length);
   pass(columns===(width>1000?5:width>540?3:2),width+' category grid uses expected column count');
   pass(await choose('3').evaluate(node=>node.scrollWidth<=node.clientWidth && node.scrollHeight<=node.clientHeight),width+' selected category text stays complete');
   await page.screenshot({path:out+'publish-category-'+width+'.png',fullPage:true});
  }
  await choose('1').click();pass(await thirdLevel.isHidden() && await page.locator('#listing-category').inputValue()==='1','Switching first level clears lower-level selection');
  await choose('2').click();await next.click();pass(await page.locator('#publish-details').isVisible() && await page.locator('#publish-category-picker').isHidden(),'Next opens information form');
  await page.locator('[name=title]').fill(token+' service');await page.locator('#listing-province').selectOption('广东省');await page.locator('#listing-city').selectOption('3');const district=await page.locator('#listing-district option').filter({hasText:'天河'}).getAttribute('value');await page.locator('#listing-district').selectOption(district);
  await page.locator('[name=address]').fill('QA synthetic address');await page.locator('[name=contact]').fill('演示联系人');await page.locator('[name=phone]').fill('00000000000');await page.locator('[name=body]').fill('仅用于分级选择分类与发布流程验证的模拟测试信息。');
  await page.locator('[name="images[]"]').setInputFiles({name:'synthetic.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=','base64')});
  await page.getByRole('button',{name:'重新选择分类',exact:true}).click();pass(await page.locator('#publish-category-picker').isVisible(),'User can return to category selection');
  await choose('3').click();await next.click();pass(await page.locator('[name=title]').inputValue()===token+' service' && await page.locator('#listing-district').inputValue()===district && await page.locator('[name="images[]"]').evaluate(node=>node.files.length)===1,'Changing category retains text region and selected image');
  await page.locator('[name="images[]"]').setInputFiles([]);
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'提交审核',exact:true}).click()]);
  const id=Number(await page.locator('.account-row').first().getAttribute('data-listing-id'));remember(id);pass((await page.locator('.notice').innerText()).includes('信息已提交'),'Three-level category service successfully submitted');
  await page.goto(base+'/fa-bu-xin-xi/?id='+id);pass(await page.locator('#publish-details').isVisible() && await page.locator('#listing-category').inputValue()==='3','Editing opens information form with original third-level category');
  await page.getByRole('button',{name:'重新选择分类',exact:true}).click();pass(await choose('1').getAttribute('aria-pressed')==='true' && await choose('2').getAttribute('aria-pressed')==='true' && await choose('3').getAttribute('aria-pressed')==='true','Editing restores the full selected category branch');
  await next.click();const csrf=await page.locator('input[name=csrf]').first().inputValue();const valid={csrf,id,type:'service',title:token+' service',category_id:3,province:'广东省',city_id:3,district_id:district,address:'QA synthetic address',contact:'演示联系人',phone:'00000000000',body:'仅用于分级选择分类与发布流程验证的模拟测试信息。'};
  const invalid=await context.request.post(base+'/fa-bu-xin-xi/',{form:{...valid,category_id:999999999}});pass((await invalid.text()).includes('请选择已开通的分类和城市'),'Server rejects forged category ID');
  const failure=await context.request.post(base+'/fa-bu-xin-xi/',{form:{...valid,phone:'invalid'}});const body=await failure.body();await page.route('**/fa-bu-xin-xi/?qa-error=1',route=>route.fulfill({status:failure.status(),body,contentType:'text/html; charset=utf-8'}));await page.goto(base+'/fa-bu-xin-xi/?qa-error=1');pass(await page.locator('#publish-details').isVisible() && await page.locator('#listing-category').inputValue()==='3','Failed submission restores category and keeps form open');await page.unroute('**/fa-bu-xin-xi/?qa-error=1');
  const nojs=await browser.newContext({javaScriptEnabled:false}),fallback=await nojs.newPage();await fallback.goto(base+'/deng-lu/');await fallback.getByLabel('账号',{exact:true}).fill(username);await fallback.getByLabel('密码',{exact:true}).fill(password);await Promise.all([fallback.waitForURL('**/yong-hu-zhong-xin/'),fallback.getByRole('button',{name:'登录',exact:true}).click()]);await fallback.goto(base+'/fa-bu-xin-xi/');
  pass(await fallback.locator('#listing-category').isVisible() && await fallback.locator('#publish-details').isVisible(),'No JavaScript keeps native selector and information form usable');
  await fallback.locator('#listing-category').selectOption('2');await fallback.locator('[name=type][value=request]').check();await fallback.locator('[name=title]').fill(token+' request');await fallback.locator('#listing-province').selectOption('浙江省');await fallback.locator('#listing-city').selectOption('6');await fallback.locator('[name=address]').fill('QA synthetic address');await fallback.locator('[name=contact]').fill('演示联系人');await fallback.locator('[name=phone]').fill('00000000000');await fallback.locator('[name=body]').fill('仅用于无脚本分类选择和求修发布验证的模拟信息。');await Promise.all([fallback.waitForURL('**/yong-hu-zhong-xin/'),fallback.getByRole('button',{name:'提交审核',exact:true}).click()]);remember(Number(await fallback.locator('.account-row').first().getAttribute('data-listing-id')));pass((await fallback.locator('.notice').innerText()).includes('信息已提交'),'No JavaScript request publication succeeds');
  await nojs.close();pass(errors.length===0,'No browser script errors');fs.writeFileSync(out+'publish-category-ui-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('SUCCESS',checks.length,'publication category checks.');
 }finally{await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
