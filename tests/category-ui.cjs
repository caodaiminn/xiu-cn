const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs');const path=require('path');const assert=require('assert');
const base=process.env.XIU_BASE_URL||'http://www.xiu.cn';const out=path.join(__dirname,'artifacts');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const results=[];
 try {
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}],['small-mobile',{width:320,height:760}]]) {
   await page.setViewportSize(viewport);
   for(const route of ['/fen-lei-xin-xi/','/guang-zhou/','/guang-zhou/kong-tiao-qing-xi/','/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/']) {
    const r=await page.goto(base+route);assert.equal(r.status(),200);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${name} ${route} overflow`);
    assert.equal(await page.locator('h1').count(),1);assert.equal(await page.locator('.category-filter').count(),1);
    assert.equal(await page.locator('.category-filter select').count(),0);
    assert(await page.locator('.category-filter-links a[href]').count()>8);
    results.push({name,route});
   }
   await page.goto(base+'/guang-zhou/kong-tiao-qing-xi/');
   const active=page.locator('#service-category-links a[aria-current="page"]');assert.equal(await active.innerText(),'空调清洗');
   assert.equal(await active.evaluate(e=>getComputedStyle(e).color),'rgb(255, 102, 0)');
   await page.locator('.category-filter').screenshot({path:path.join(out,'category-filter-'+name+'.png')});
   await page.screenshot({path:path.join(out,'category-page-'+name+'.png'),fullPage:true});
   const toggle=page.getByRole('button',{name:'收起类别'});await toggle.click();assert.equal(await page.locator('.category-filter-toggle').getAttribute('aria-expanded'),'false');
   await page.getByRole('button',{name:'展开类别'}).click();
   assert.equal(await page.locator('.category-filter-toggle').getAttribute('aria-expanded'),'true');
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/guang-zhou/kong-tiao-qing-xi/');
  await Promise.all([page.waitForURL('**/qu-yu/tian-he/'),page.getByRole('link',{name:'天河',exact:true}).click()]);
  assert.equal(await page.locator('.category-filter-region [aria-current="page"]').innerText(),'天河');
  await Promise.all([page.waitForURL('**/kong-tiao-qing-xi/'),page.getByRole('link',{name:'全广州',exact:true}).click()]);
  await Promise.all([page.waitForURL('**/jia-ju-wei-xiu/'),page.locator('#service-category-links').getByRole('link',{name:'家具维修',exact:true}).click()]);
  assert((await page.locator('h1').innerText()).includes('家具维修'));
  await page.goto(base+'/dgadmin/login/');await page.getByLabel('账号',{exact:true}).fill('daiit');await page.getByLabel('密码',{exact:true}).fill(process.env.XIU_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL(base+'/dgadmin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}]]) {
   await page.setViewportSize(viewport);
   for(const route of ['/dgadmin/categories/?edit=18','/dgadmin/districts/?city=3']) {
    const r=await page.goto(base+route);assert.equal(r.status(),200);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));results.push({name,route});
   }
   await page.screenshot({path:path.join(out,'district-admin-'+name+'.png'),fullPage:true});
  }
  assert.deepEqual(errors,[]);
  fs.writeFileSync(path.join(out,'category-ui-results.json'),JSON.stringify({passed:results.length,results,errors},null,2));
  console.log('SUCCESS',results.length,'category viewport checks; category/area navigation and toggle passed.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
