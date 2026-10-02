const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base='http://www.xiu.cn',route='/yong-hu-zhong-xin/shou-ji-hao-ma/',file='tests/artifacts/member-phone-fixture.json',php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe';
const fixture={token:'mobileqa_'+Date.now(),password:'MemberPhoneQa2026!',sessions:[]};
if(fs.existsSync(file)) throw new Error('Clean up the previous member phone fixture first.');
const save=()=>fs.writeFileSync(file,JSON.stringify(fixture,null,2));save();
const cli=mode=>execFileSync(php,['tests/member-phone-fixture.php',mode],{encoding:'utf8'});
const checks=[],pass=(condition,label)=>{assert(condition,label);checks.push(label);};
(async()=>{
 let browser;const contexts=[];let ready=false;
 try {
  Object.assign(fixture,JSON.parse(cli('setup')));ready=true;save();
  browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:1440,height:1000}});contexts.push(context);const page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
  const guest=await context.request.get(base+route,{maxRedirects:0});pass(guest.status()===303 && guest.headers().location==='/deng-lu/','Anonymous access requires login');
  await page.goto(base+'/deng-lu/');await page.getByLabel('账号',{exact:true}).fill(fixture.old_username);await page.getByLabel('密码',{exact:true}).fill(fixture.password);
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  pass(await page.locator('.member-nav').getByRole('link',{name:'手机号码',exact:true}).count()===1,'Member sidebar includes the phone entry');
  pass(await page.locator('.member-profile').getByRole('link',{name:'添加',exact:true}).count()===1,'Accounts without a phone have a profile shortcut');
  await page.locator('.member-nav').getByRole('link',{name:'手机号码',exact:true}).click();
  pass(await page.locator('.member-nav a[aria-current=page]').innerText()==='手机号码','Phone navigation has the selected state');
  pass((await page.locator('meta[name=robots]').getAttribute('content')).includes('noindex'),'Account phone page is private to search engines');
  pass(await page.getByRole('heading',{name:'添加手机号码',exact:true}).count()===1 && await page.getByLabel('手机号码',{exact:true}).inputValue()==='','A legacy account can add a phone');
  const csrf=await page.locator('.member-phone-form [name=csrf]').inputValue();
  const blocked=await page.request.post(base+route,{form:{csrf:'invalid',phone:fixture.first,password:fixture.password}});pass(blocked.status()===403,'CSRF is required');
  for(const phone of ['0735-0000000','abc123456','1380000000','12800000000']) {
   const response=await page.request.post(base+route,{form:{csrf,phone,password:fixture.password}});
   pass(response.status()===200 && (await response.text()).includes('请输入有效的11位手机号码'),'Invalid mobile rejected: '+phone);
  }
  for(const [password,message] of [['','请输入当前登录密码'],['wrong','当前登录密码不正确']]) {
   const response=await page.request.post(base+route,{form:{csrf,phone:fixture.first,password}});
   const html=await response.text();pass(html.includes(message) && html.includes('value="'+fixture.first+'"'),'Password verification fails safely and preserves entered phone');
  }
  const duplicate=await page.request.post(base+route,{form:{csrf,phone:fixture.occupied,password:fixture.password}});pass((await duplicate.text()).includes('手机号码已被其他账号使用'),'A number used by another account is rejected');
  pass(JSON.parse(cli('verify-original')).verified,'Failed saves leave both accounts and existing listing unchanged');
  const saved=await page.request.post(base+route,{maxRedirects:0,form:{csrf,phone:fixture.first,password:fixture.password,user_id:String(fixture.other_id)}});
  pass(saved.status()===303 && saved.headers().location===route,'Adding a phone saves and returns to the phone page');
  pass(JSON.parse(cli('verify-first')).verified,'Phone and login account update together; forged user ID cannot change another account');
  await page.goto(base+route);pass((await page.locator('.member-phone-form').innerText()).includes(fixture.first),'Saved phone appears in the member page');
  pass(await page.getByLabel('当前登录密码',{exact:true}).inputValue()==='','Passwords are not echoed back');
  await page.goto(base+'/fa-bu-xin-xi/');pass(await page.locator('[name=phone]').inputValue()===fixture.first,'New publication defaults to the added phone');
  await page.goto(base+'/fa-bu-xin-xi/?id='+fixture.listing_id);pass(await page.locator('[name=phone]').inputValue()==='0735-0000000','Existing listing keeps its own contact number');
  await page.goto(base+route);await page.getByLabel('手机号码',{exact:true}).fill(fixture.second);await page.getByLabel('当前登录密码',{exact:true}).fill(fixture.password);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.getByRole('button',{name:'保存手机号码',exact:true}).click()]);
  pass(JSON.parse(cli('verify-second')).verified && await page.getByLabel('手机号码',{exact:true}).inputValue()===fixture.second,'An existing phone can be changed');
  const fresh=await browser.newContext();contexts.push(fresh);const login=await fresh.newPage();await login.goto(base+'/deng-lu/');
  const loginCsrf=await login.locator('[name=csrf]').inputValue();
  const old=await login.request.post(base+'/deng-lu/',{form:{csrf:loginCsrf,username:fixture.first,password:fixture.password}});pass((await old.text()).includes('账号或密码不正确'),'The previous phone no longer logs into the changed account');
  await login.getByLabel('账号',{exact:true}).fill(fixture.second);await login.getByLabel('密码',{exact:true}).fill(fixture.password);
  await Promise.all([login.waitForURL('**/yong-hu-zhong-xin/'),login.getByRole('button',{name:'登录',exact:true}).click()]);
  await login.goto(base+'/fa-bu-xin-xi/');pass(await login.locator('[name=phone]').inputValue()===fixture.second,'The new phone and unchanged password work in a fresh session');
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:1000});await page.goto(base+route);
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Phone management fits '+width+'px');
   await page.screenshot({path:'tests/artifacts/member-phone-'+width+'.png',fullPage:true});
  }
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync('tests/artifacts/member-phone-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('PASS '+checks.length+' member phone checks');
 } finally {
  for(const context of contexts) for(const cookie of await context.cookies()) if(cookie.name==='xiu_session') fixture.sessions.push(cookie.value);
  save();if(browser) await browser.close();if(ready) console.log(cli('cleanup'));
 }
})().catch(error=>{console.error(error);process.exit(1)});
