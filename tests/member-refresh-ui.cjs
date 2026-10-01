const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),base='http://www.xiu.cn',out='tests/artifacts/';
const fixture=JSON.parse(fs.readFileSync(out+'member-refresh-fixtures.json','utf8')),checks=[];
const pass=(value,label)=>{assert(value,label);checks.push(label);console.log('PASS',label)};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const login=async(kind)=>{await page.goto(base+'/deng-lu/');await page.getByLabel('账号',{exact:true}).fill(fixture.users[kind].username);await page.getByLabel('密码',{exact:true}).fill('QARefresh2026');await Promise.all([page.waitForURL(base+'/yong-hu-zhong-xin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);};
  await login('ui');
  for(const width of [1440,390,320]){
   await page.setViewportSize({width,height:900});
   for(const [name,route] of [['dashboard','/yong-hu-zhong-xin/'],['published','/yong-hu-zhong-xin/wo-de-fa-bu/?status=published']]){
    const response=await page.goto(base+route);pass(response.status()===200,width+' '+name+' HTTP 200');
    pass(await page.getByRole('button',{name:'刷新',exact:true}).count()===2,width+' '+name+' published listings have refresh buttons');
    pass(await page.getByRole('button',{name:'刷新',exact:true}).first().isEnabled(),width+' '+name+' available free refresh enabled');
    pass((await page.locator('.member-refresh-tip').innerText()).includes('剩余1次'),width+' '+name+' remaining quota shown');
    pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' '+name+' no horizontal overflow');
    await page.screenshot({path:out+'member-refresh-'+name+'-'+width+'.png',fullPage:true});
   }
  }
  const target=fixture.listings.ui[0];
  const before=await page.locator('.account-row[data-listing-id="'+target.id+'"] .status').innerText();
  await Promise.all([page.waitForNavigation(),page.locator('.account-row[data-listing-id="'+target.id+'"] .member-refresh-button').click()]);
  pass(new URL(page.url()).searchParams.get('status')==='published','Refresh click retains current published filter');
  pass((await page.locator('.notice').innerText()).includes('刷新成功'),'Refresh click reports success');
  pass((await page.locator('.member-refresh-tip').innerText()).includes('已用完'),'Successful click exhausts daily quota');
  pass(await page.locator('.member-refresh-button:disabled').count()===2,'All own published refresh buttons disabled after using quota');
  pass(await page.locator('.account-row[data-listing-id="'+target.id+'"] .status').innerText()===before,'Refresh preserves published status');
  pass((await page.locator('.account-row[data-listing-id="'+target.id+'"] .member-refresh-time').innerText()).includes('上次刷新'),'Refresh timestamp visible');
  await page.reload();pass(await page.locator('.member-refresh-button:disabled').count()===2,'Quota survives reload');
  await page.screenshot({path:out+'member-refresh-used-320.png',fullPage:true});
  await page.goto(base+fixture.category_url);
  pass(await page.locator('.listing-row h2 a').first().getAttribute('href')===target.url,'User button click puts selected information first in its category');
  await page.goto(base+'/');pass(await page.locator('.latest-list a').first().getAttribute('href')===target.url,'Refreshed item leads homepage information');
  await page.goto(base+fixture.listings.company[0].url);
  const related=await page.locator('.repair-related li a').first().getAttribute('href');pass(related===target.url,'Related information follows refreshed priority');
  await page.goto(base+'/yong-hu-zhong-xin/');await page.getByRole('button',{name:'退出',exact:true}).click();await login('owner');
  for(const status of ['pending','rejected','offline']){
   await page.goto(base+'/yong-hu-zhong-xin/wo-de-fa-bu/?status='+status);
   pass(await page.locator('.member-refresh-button').count()===0,status+' information has no refresh action');
  }
  await page.goto(base+'/yong-hu-zhong-xin/');pass((await page.locator('.member-refresh-tip').innerText()).includes('已用完'),'Management home shares same daily quota');
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync(out+'member-refresh-ui-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('SUCCESS',checks.length,'refresh browser checks.');
 }finally{await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
