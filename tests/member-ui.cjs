const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('assert'),fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'}),checks=[],errors=[];
 const pass=(value,label)=>{assert(value,label);checks.push(label)};
 try {
  const fixtures=JSON.parse(fs.readFileSync('tests/artifacts/fixtures.json','utf8'));const context=await browser.newContext(),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://www.xiu.cn/deng-lu/');await page.getByLabel('账号',{exact:true}).fill(fixtures.users[0]);await page.getByLabel('密码',{exact:true}).fill('QAMember2026');
  await Promise.all([page.waitForURL('http://www.xiu.cn/yong-hu-zhong-xin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  const routes=[['dashboard','/yong-hu-zhong-xin/'],['listings','/yong-hu-zhong-xin/wo-de-fa-bu/'],['personal','/yong-hu-zhong-xin/shen-fen-ren-zheng/'],['company','/yong-hu-zhong-xin/shen-fen-ren-zheng/?type=company']];
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:900});
   for(const [name,route] of routes) {
    const r=await page.goto('http://www.xiu.cn'+route);pass(r.status()===200,width+' '+name+' HTTP 200');
    pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' '+name+' no horizontal overflow');
    pass(await page.locator('h1').count()===1,width+' '+name+' one main heading');
    pass((await page.locator('meta[name=robots]').getAttribute('content')).includes('noindex'),width+' '+name+' private page noindex');
    await page.screenshot({path:'tests/artifacts/member-'+name+'-'+width+'.png',fullPage:true});
   }
  }
  await page.goto('http://www.xiu.cn/yong-hu-zhong-xin/');pass(await page.locator('[data-stat=total] strong').innerText()==='1','Dashboard count is real owner data');
  await page.locator('[data-stat=offline]').click();pass(new URL(page.url()).searchParams.get('status')==='offline','Dashboard statistic opens matching management filter');
  pass(await page.locator('.account-row').count()===1,'Status filter displays own offline item');
  await page.getByRole('link',{name:'身份认证',exact:true}).click();await page.getByRole('link',{name:'企业认证',exact:true}).click();
  pass(await page.locator('input[type=file]').count()===1 && await page.locator('input[name="business_license[]"]').count()===1,'Enterprise form asks for one license');
  await page.getByRole('link',{name:'个人认证',exact:true}).click();pass(await page.locator('input[type=file]').count()===2,'Personal form asks for two sides');
  const state=await context.storageState(),plain=await browser.newContext({javaScriptEnabled:false,storageState:state}),plainPage=await plain.newPage();
  await plainPage.goto('http://www.xiu.cn/yong-hu-zhong-xin/shen-fen-ren-zheng/');await plainPage.getByRole('link',{name:'企业认证',exact:true}).click();pass(await plainPage.locator('input[name=type]').inputValue()==='company','Identity type switches without JavaScript');
  await page.goto('http://www.xiu.cn/dgadmin/login/');await page.getByLabel('账号',{exact:true}).fill('daiit');await page.getByLabel('密码',{exact:true}).fill(process.env.XIU_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL('http://www.xiu.cn/dgadmin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:900});const r=await page.goto('http://www.xiu.cn/dgadmin/verifications/');
   pass(r.status()===200 && await page.locator('.verification-review').count()>=2,width+' administrator review renders');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' review no horizontal overflow');
   await page.screenshot({path:'tests/artifacts/member-admin-review-'+width+'.png',fullPage:true});
  }
  pass(errors.length===0,'No page script errors or injected script execution');
  fs.writeFileSync('tests/artifacts/member-ui-results.json',JSON.stringify({passed:checks.length,checks,errors},null,2));console.log('SUCCESS',checks.length,'member browser checks.');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
