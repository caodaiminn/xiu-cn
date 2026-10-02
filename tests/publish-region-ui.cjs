const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),base='http://www.xiu.cn',out='tests/artifacts/';
const token='xiuqa_'+Date.now(),username='139'+String(Date.now()).slice(-8),password='QARegion2026',checks=[];
const fixtures={token,users:[username],mobile_accounts:{[username]:password},listings:[],articles:[],categories:[],cities:[],districts:[]};
fs.writeFileSync(out+'fixtures.json',JSON.stringify(fixtures));
const remember=id=>{if(!fixtures.listings.includes(id)){fixtures.listings.push(id);fs.writeFileSync(out+'fixtures.json',JSON.stringify(fixtures));}};
const pass=(value,label)=>{assert(value,label);checks.push(label);console.log('PASS',label)};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/zhu-ce/');await page.getByLabel('手机号码',{exact:true}).fill(username);await page.getByLabel('密码',{exact:true}).fill(password);await page.getByLabel('确认密码',{exact:true}).fill(password);
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'注册并登录',exact:true}).click()]);
  const response=await page.goto(base+'/fa-bu-xin-xi/'),source=await response.text();
  await page.locator('[data-publish-category-id="1"]').click();await page.locator('[data-publish-category-id="2"]').click();await page.getByRole('button',{name:'下一步',exact:true}).click();
  const cities=await page.evaluate(source=>Array.from(new DOMParser().parseFromString(source,'text/html').querySelectorAll('#listing-city option[data-province]')).map(option=>({id:option.value,province:option.dataset.province})),source);
  const province=page.getByLabel('省份',{exact:true}),city=page.getByLabel('城市',{exact:true}),district=page.getByLabel('区域',{exact:true});
  pass(await city.isDisabled() && await district.isDisabled(),'New form waits for province before city and area');
  const provinces=await province.locator('option').evaluateAll(nodes=>nodes.map(node=>node.value).filter(Boolean));pass(provinces.length===34,'Province selector includes all 34 active province groups');
  for(const name of provinces){
   await province.selectOption(name);const actual=await city.locator('option').evaluateAll(nodes=>nodes.map(node=>node.value).filter(Boolean));
   pass(JSON.stringify(actual)===JSON.stringify(cities.filter(item=>item.province===name).map(item=>item.id)),name+' cities are filtered by actual parent');
  }
  await province.selectOption('广东省');await city.selectOption('3');
  const tianhe=await district.locator('option').filter({hasText:'天河'}).getAttribute('value');await district.selectOption(tianhe);
  pass(await district.inputValue()===tianhe && await district.isEnabled(),'Guangzhou Tianhe can be selected');
  await city.selectOption('4');pass(await district.inputValue()==='0' && await district.isDisabled(),'Changing city clears former district and supports no-area city');
  pass((await page.locator('#listing-region-status').innerText()).includes('按全市发布'),'No-area city explains full-city publication');
  await province.selectOption('浙江省');pass(await city.inputValue()==='' && await district.inputValue()==='0','Changing province resets both lower levels');
  pass(await city.locator('option[value="3"]').count()===0,'Other-province cities are removed from native dropdown');
  await city.selectOption('6');pass(await district.isDisabled(),'Hangzhou can publish without district data');
  await province.selectOption('');pass(await city.isDisabled() && await district.isDisabled(),'Clearing province disables both lower levels');
  await province.selectOption('广东省');await city.selectOption('3');await district.selectOption(tianhe);
  await page.locator('[name=title]').fill(token+' repair service');await page.locator('[name=address]').fill('QA synthetic address');await page.locator('[name=contact]').fill('QA synthetic contact');await page.locator('[name=phone]').fill('00000000000');await page.locator('[name=body]').fill('仅用于三级省市区域选择验证的模拟测试信息。');
  for(const width of [1440,390,320]){
   await page.setViewportSize({width,height:1000});pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' form no horizontal overflow');
   const boxes=await Promise.all([province.boundingBox(),city.boundingBox(),district.boundingBox()]);
   pass(width===1440?Math.abs(boxes[0].y-boxes[2].y)<2:boxes[0].y<boxes[1].y && boxes[1].y<boxes[2].y,width+' region controls follow desktop or mobile layout');
   await page.screenshot({path:out+'publish-region-'+width+'.png',fullPage:true});await page.locator('.publish-service-region').screenshot({path:out+'publish-region-controls-'+width+'.png'});
  }
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'提交审核',exact:true}).click()]);
  const id=Number(await page.locator('.account-row').first().getAttribute('data-listing-id'));remember(id);pass((await page.locator('.notice').innerText()).includes('信息已提交'),'Service with three region levels submitted');
  await page.goto(base+'/fa-bu-xin-xi/?id='+id);
  pass(await province.inputValue()==='广东省' && await city.inputValue()==='3' && await district.inputValue()===tianhe,'Editing derives province and restores city and district');
  const csrf=await page.locator('[name=csrf]').first().inputValue();const form={csrf,id,type:'service',title:token+' altered service',category_id:2,province:'浙江省',city_id:3,district_id:tianhe,address:'QA altered address',contact:'QA synthetic contact',phone:'00000000000',body:'仅用于拒绝错误省市对应关系的测试信息。'};
  const wrongProvince=await context.request.post(base+'/fa-bu-xin-xi/',{form});pass((await wrongProvince.text()).includes('请选择所选省份下的城市'),'Server rejects mismatched province and city');
  const wrongDistrict=await context.request.post(base+'/fa-bu-xin-xi/',{form:{...form,city_id:6}});pass((await wrongDistrict.text()).includes('请选择所选城市下已开通的区域'),'Server rejects cross-city district');
  const failed=await context.request.post(base+'/fa-bu-xin-xi/',{form:{...form,province:'广东省',phone:'bad'}});
  await page.goto(base+'/fa-bu-xin-xi/?id='+id);pass(await page.locator('[name=title]').inputValue()===token+' repair service' && await city.inputValue()==='3','Rejected region submissions preserve stored information');
  const failedBody=await failed.body();
  await page.route('**/fa-bu-xin-xi/?qa-error=1',route=>route.fulfill({status:failed.status(),body:failedBody,contentType:'text/html; charset=utf-8'}));
  await page.goto(base+'/fa-bu-xin-xi/?qa-error=1');pass(await province.inputValue()==='广东省' && await city.inputValue()==='3' && await district.inputValue()===tianhe,'Failed validation restores submitted province city and area');
  await page.unroute('**/fa-bu-xin-xi/?qa-error=1');
  const nojs=await browser.newContext({javaScriptEnabled:false}),fallback=await nojs.newPage();
  await fallback.goto(base+'/deng-lu/');await fallback.getByLabel('账号',{exact:true}).fill(username);await fallback.getByLabel('密码',{exact:true}).fill(password);await Promise.all([fallback.waitForURL('**/yong-hu-zhong-xin/'),fallback.getByRole('button',{name:'登录',exact:true}).click()]);
  await fallback.goto(base+'/fa-bu-xin-xi/');await fallback.locator('[name=type][value=request]').check();await fallback.locator('[name=title]').fill(token+' request without JavaScript');await fallback.locator('[name=category_id]').selectOption('2');await fallback.getByLabel('省份',{exact:true}).selectOption('浙江省');await fallback.getByLabel('城市',{exact:true}).selectOption('6');await fallback.getByLabel('区域',{exact:true}).selectOption('0');await fallback.locator('[name=address]').fill('QA synthetic no-area address');await fallback.locator('[name=contact]').fill('QA synthetic contact');await fallback.locator('[name=phone]').fill('00000000000');await fallback.locator('[name=body]').fill('仅用于无脚本情况下全市求修发布验证的模拟信息。');
  await Promise.all([fallback.waitForURL('**/yong-hu-zhong-xin/'),fallback.getByRole('button',{name:'提交审核',exact:true}).click()]);
  const requestId=Number(await fallback.locator('.account-row').first().getAttribute('data-listing-id'));remember(requestId);
  pass((await fallback.locator('.notice').innerText()).includes('信息已提交'),'No JavaScript request submission works with full-city scope');
  await page.goto(base+'/fa-bu-xin-xi/?id='+requestId);pass(await province.inputValue()==='浙江省' && await city.inputValue()==='6' && await district.inputValue()==='0' && await district.isDisabled(),'No-area request persists and restores correct province city scope');
  pass(errors.length===0,'No browser script errors');await nojs.close();
  fs.writeFileSync(out+'publish-region-ui-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('SUCCESS',checks.length,'three-level publication region checks.');
 }finally{await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
