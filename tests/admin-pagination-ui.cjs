const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const out='tests/artifacts/',base='http://www.xiu.cn',fixture=JSON.parse(fs.readFileSync(out+'fixtures.json','utf8')),qa=fixture.admin_pagination;
const checks=[],pass=(condition,label)=>{assert(condition,label);checks.push(label);console.log('PASS',label);};
const cache='storage/cache/admin-listing-counts.json';
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/dgadmin/login/');await page.getByLabel('账号',{exact:true}).fill('daiit');await page.getByLabel('密码',{exact:true}).fill(process.env.XIU_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL('**/dgadmin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  await page.goto(base+'/dgadmin/listings/');pass(await page.locator('.review-row').count()===20,'Default page contains 20 records');
  pass(await page.locator('.admin-listing-controls').count()===2,'Pagination controls visible above and below records');
  pass(fs.existsSync(cache),'Large listing volume enables count cache');
  let snapshot=JSON.parse(fs.readFileSync(cache,'utf8'));pass(snapshot.counts.total===qa.baseline.total+1001,'Cached total includes all records');
  const expires=snapshot.expires_at;await page.reload();pass(JSON.parse(fs.readFileSync(cache,'utf8')).expires_at===expires,'Repeated request reuses unexpired count cache');
  const ids=await page.locator('.review-row h2 a').evaluateAll(nodes=>nodes.map(node=>node.getAttribute('href')));
  await page.locator('.admin-listing-controls').first().getByRole('link',{name:'下一页',exact:true}).click();
  pass(new URL(page.url()).searchParams.get('page')==='2','Next page navigates to second page');
  pass(!(await page.locator('.review-row h2 a').evaluateAll(nodes=>nodes.map(node=>node.getAttribute('href')))).some(url=>ids.includes(url)),'Second page contains distinct records');
  for(const size of [30,50]){
   await page.goto(base+'/dgadmin/listings/?status=pending&per_page='+size);
   pass(await page.locator('.review-row').count()===size,size+' records per page supported');
   await page.locator('.admin-listing-controls').first().getByRole('link',{name:'下一页',exact:true}).click();
   const query=new URL(page.url()).searchParams;pass(query.get('status')==='pending'&&query.get('per_page')===String(size),'Status and page size retained '+size);
  }
  await page.goto(base+'/dgadmin/listings/?status=pending&per_page=20&page=999999999');
  const expectedPages=Math.ceil((qa.baseline.pending+1001)/20);
  pass((await page.locator('.admin-listing-controls').first().innerText()).includes('第 '+expectedPages+' / '+expectedPages+' 页'),'Excessive page number clamps to last page');
  await page.goto(base+'/dgadmin/listings/?status=invalid&per_page=999&page=-2');pass(await page.locator('.review-row').count()===20,'Invalid filters and page sizes safely default');
  await page.goto(base+'/dgadmin/listings/?status=pending&page=2&per_page=30');
  pass((await page.locator('.review-description').first().innerText()).length===180,'Large body is limited to 180-character admin preview');
  const controls=page.locator('.admin-listing-controls').first();await controls.getByLabel('跳转页码',{exact:true}).fill('3');await controls.getByRole('button',{name:'跳转',exact:true}).click();
  pass(new URL(page.url()).searchParams.get('page')==='3','Jump form reaches requested page');
  const csrf=await page.locator('.review-row input[name=csrf]').first().inputValue(),id=fixture.listings[0];
  const missing=await page.request.post(base+'/dgadmin/listings/',{form:{action:'reject',id,reason:'模拟驳回原因'}});pass(missing.status()===403,'Review action still requires CSRF');
  const changed=await page.request.post(base+'/dgadmin/listings/?status=pending&page=2&per_page=30',{form:{csrf,action:'reject',id,reason:'模拟分页与缓存验证，不是实际信息'}});
  const params=new URL(changed.url()).searchParams;pass(params.get('status')==='pending'&&params.get('page')==='2'&&params.get('per_page')==='30','Review returns to original filter page and size');
  snapshot=JSON.parse(fs.readFileSync(cache,'utf8'));pass(snapshot.counts.pending===qa.baseline.pending+1000&&snapshot.counts.rejected===qa.baseline.rejected+1,'Review immediately invalidates cached status counts');
  await page.goto(base+'/dgadmin/listings/?status=pending&per_page=50');pass(Number(await page.locator('.admin-nav a[href="/dgadmin/listings/"] span').innerText())===snapshot.counts.pending,'Navigation pending badge shares updated listing counts');
  await page.getByRole('link',{name:'全部信息',exact:true}).click();pass(new URL(page.url()).searchParams.get('per_page')==='50','All-information tab preserves selected page size');
  await page.goto(base+'/dgadmin/listings/?status=rejected');pass((await page.locator('.review-row').first().innerText()).includes(fixture.token),'Rejected status displayed immediately');
  fs.writeFileSync(cache,'{broken');await page.reload();pass(JSON.parse(fs.readFileSync(cache,'utf8')).counts.total===qa.baseline.total+1001,'Corrupt cache safely rebuilt from database');
  snapshot=JSON.parse(fs.readFileSync(cache,'utf8'));snapshot.expires_at=0;fs.writeFileSync(cache,JSON.stringify(snapshot));await page.reload();pass(JSON.parse(fs.readFileSync(cache,'utf8')).expires_at>Math.floor(Date.now()/1000),'Expired cache refreshed');
  const member=await browser.newContext(),owner=await member.newPage();await owner.goto(base+'/deng-lu/');await owner.getByLabel('账号',{exact:true}).fill(qa.username);await owner.getByLabel('密码',{exact:true}).fill(qa.password);await Promise.all([owner.waitForURL('**/yong-hu-zhong-xin/'),owner.getByRole('button',{name:'登录',exact:true}).click()]);
  const memberCsrf=await owner.locator('input[name=csrf]').first().inputValue();
  await member.request.post(base+'/yong-hu-zhong-xin/xia-jia/',{form:{csrf:memberCsrf,id:fixture.listings[1]}});await page.reload();
  pass(JSON.parse(fs.readFileSync(cache,'utf8')).counts.offline===qa.baseline.offline+1,'Member withdrawal immediately invalidates count cache');
  await member.request.post(base+'/fa-bu-xin-xi/',{form:{csrf:memberCsrf,id,type:'service',title:fixture.token+' 模拟重提信息',category_id:2,province:'广东省',city_id:3,district_id:0,address:'模拟服务地址',contact:'模拟联系人',phone:'00000000000',body:'仅用于后台分页与统计缓存检查，不提供实际服务。'}});await page.reload();
  pass(JSON.parse(fs.readFileSync(cache,'utf8')).counts.rejected===qa.baseline.rejected,'Member edit and resubmission invalidates count cache');await member.close();
  for(const width of [1440,390,320]){
   await page.setViewportSize({width,height:950});await page.goto(base+'/dgadmin/listings/?status=pending&per_page=20');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Admin controls and content fit '+width);
   await page.screenshot({path:out+'admin-pagination-'+width+'.png'});
  }
  const guest=await browser.newContext(),anonymous=await guest.newPage();await anonymous.goto(base+'/dgadmin/listings/');pass(anonymous.url().includes('/dgadmin/login/'),'Unauthenticated access remains blocked');
  const privateCache=await guest.request.get(base+'/storage/cache/admin-listing-counts.json');pass(privateCache.status()!==200,'Cache is outside public access');await guest.close();
  pass(errors.length===0,'No browser errors');fs.writeFileSync(out+'admin-pagination-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('SUCCESS',checks.length,'admin pagination and cache checks');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
