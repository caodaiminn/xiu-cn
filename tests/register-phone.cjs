const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const base='http://www.xiu.cn',file='tests/artifacts/register-phone-fixture.json',php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe';
const fixture={token:'phoneqa_'+Date.now(),password:'PhoneQa2026!',phone:'13800000000',edit_phone:'0735-0000000',sessions:[]};
if(fs.existsSync(file)) throw new Error('Clean up previous register phone fixture before running.');
const save=()=>fs.writeFileSync(file,JSON.stringify(fixture,null,2)); save();
const cli=mode=>execFileSync(php,['tests/register-phone-fixture.php',mode],{encoding:'utf8'});
const checks=[],pass=(condition,label)=>{assert(condition,label);checks.push(label);};
(async()=>{
 let browser;const contexts=[]; const errors=[];
 try {
  Object.assign(fixture,JSON.parse(cli('setup')));save(); browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext();contexts.push(context);const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  await page.goto(base+'/zhu-ce/'); const phone=page.getByLabel('手机号码',{exact:true});
  pass(await phone.getAttribute('type')==='tel' && await phone.getAttribute('maxlength')==='11' && await phone.getAttribute('required')!==null,'Registration has one required 11-digit mobile account field');
  pass(await page.locator('[name=phone]').count()===0 && await page.locator('.auth-card input:not([type=hidden])').count()===3,'Registration merges the account and contact phone inputs');
  await phone.fill('0735-0000000');pass(await phone.evaluate(input=>!input.checkValidity()),'Browser rejects a landline number');
  for(const badPhone of ['','0735-0000000','abc123456','12800000000','1380000000','138000000000','138 0000 0000']) {
   const csrf=await page.locator('[name=csrf]').inputValue();
   const response=await page.request.post(base+'/zhu-ce/',{form:{csrf,username:badPhone,password:fixture.password,confirm_password:fixture.password,phone:fixture.phone}});
   const html=await response.text(); pass(response.status()===200 && html.includes('请输入有效的11位手机号码'),'Server rejects invalid mobile account '+JSON.stringify(badPhone));
  }
  await phone.fill(fixture.phone);
  await page.getByLabel('密码',{exact:true}).fill(fixture.password);await page.getByLabel('确认密码',{exact:true}).fill(fixture.password);
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'注册并登录',exact:true}).click()]);
  const verified=JSON.parse(cli('verify'));pass(verified.phone_saved,'Registered phone is stored on the account');
  pass((await page.locator('.member-profile').innerText()).includes(fixture.phone),'The mobile number is the account identifier');
  await page.goto(base+'/fa-bu-xin-xi/');pass(await page.locator('[name=phone]').inputValue()===fixture.phone,'New publication defaults to the registered phone');
  await page.locator('[data-publish-category-id="1"]').click();await page.locator('[data-publish-category-id="2"]').click();await page.getByRole('button',{name:'下一步',exact:true}).click();
  await page.locator('[name=phone]').fill(fixture.edit_phone);await page.locator('[name=type][value=request]').check();
  pass(await page.locator('[name=phone]').inputValue()===fixture.edit_phone,'Phone can be changed and survives service/request switching');
  const csrf=await page.locator('.publish-wizard [name=csrf]').inputValue();
  const failed=await page.request.post(base+'/fa-bu-xin-xi/',{form:{csrf,id:'0',type:'request',category_id:'2',city_id:'1',title:'短',body:'维修需求内容完整',address:'示例地址',contact:'示例联系人',phone:fixture.edit_phone}});
  pass((await failed.text()).includes('value="'+fixture.edit_phone+'"'),'Failed publication preserves the manually entered phone');
  const blank=await page.request.post(base+'/fa-bu-xin-xi/',{form:{csrf,id:'0',title:'短',phone:''}});
  pass(/name="phone" value=""/.test(await blank.text()),'An explicitly cleared phone is not silently replaced on submission');
  await page.goto(base+'/fa-bu-xin-xi/?id='+verified.listing_id);pass(await page.locator('[name=phone]').inputValue()===fixture.edit_phone,'Editing preserves the listing phone instead of replacing it with the account phone');
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:1000});await page.goto(base+'/zhu-ce/');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Registration fits '+width+'px');
   await page.screenshot({path:'tests/artifacts/register-phone-'+width+'.png',fullPage:true});
  }
  const fresh=await browser.newContext();contexts.push(fresh);const login=await fresh.newPage();
  await login.goto(base+'/deng-lu/');pass(await login.locator('[name=phone]').count()===0,'Login form keeps its existing fields');
  const duplicateCsrf=await login.locator('[name=csrf]').inputValue();
  const duplicate=await login.request.post(base+'/zhu-ce/',{form:{csrf:duplicateCsrf,username:fixture.phone,password:fixture.password,confirm_password:fixture.password}});
  pass((await duplicate.text()).includes('这个手机号码已注册'),'Duplicate mobile accounts are rejected');
  await login.getByLabel('账号',{exact:true}).fill(fixture.phone);await login.getByLabel('密码',{exact:true}).fill(fixture.password);
  await Promise.all([login.waitForURL('**/yong-hu-zhong-xin/'),login.getByRole('button',{name:'登录',exact:true}).click()]);
  await login.goto(base+'/fa-bu-xin-xi/');pass(await login.locator('[name=phone]').inputValue()===fixture.phone,'Phone is loaded from the account after a fresh login');
  const legacy=await browser.newContext();contexts.push(legacy);const old=await legacy.newPage();await old.goto(base+'/deng-lu/');
  await old.getByLabel('账号',{exact:true}).fill(fixture.token+'_old');await old.getByLabel('密码',{exact:true}).fill(fixture.password);
  await Promise.all([old.waitForURL('**/yong-hu-zhong-xin/'),old.getByRole('button',{name:'登录',exact:true}).click()]);
  await old.goto(base+'/fa-bu-xin-xi/');pass(await old.locator('[name=phone]').inputValue()==='','Existing accounts without a stored phone can still log in and publish');
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync('tests/artifacts/register-phone-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('PASS '+checks.length+' register phone checks');
 } finally {
  for(const context of contexts) for(const cookie of await context.cookies()) if(cookie.name==='xiu_session') fixture.sessions.push(cookie.value);
  save();if(browser) await browser.close();console.log(cli('cleanup'));
 }
})().catch(error=>{console.error(error);process.exit(1)});
