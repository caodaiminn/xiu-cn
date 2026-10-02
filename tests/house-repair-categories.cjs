const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe',base='http://www.xiu.cn',checks=[];
const read=file=>JSON.parse(fs.readFileSync(file,'utf8').replace(/^\uFEFF/,''));
const pass=(value,label)=>{assert(value,label);checks.push(label);};
(async()=>{
 let browser,ready=false;
 try {
  execFileSync(php,['tests/house-repair-categories-fixture.php','setup'],{stdio:'pipe'});ready=true;
  const fixture=read('tests/artifacts/house-repair-category-fixture.json'),data=read('tests/artifacts/house-repair-categories.json');
  browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:1440,height:1000}});
  await context.addCookies([{name:'xiu_session',value:fixture.user_session,domain:'www.xiu.cn',path:'/'}]);
  const page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
  for(const item of data.items) {
   const response=await context.request.get(base+item.url);
   pass(response.status()===200 && (await response.text()).includes(item.name),'Public category opens: '+item.name);
  }
  await page.goto(base+'/',{waitUntil:'domcontentloaded'});
  const group=page.locator('.category-item').filter({has:page.locator('.category-parent',{hasText:'房屋、防水维修'})});await group.hover();
  const names=await group.locator('.subcategory>a').allTextContents();
  pass(data.items.every(item=>names.includes(item.name)),'Home house category menu includes all requested entries');
  pass(await group.locator('.subcategory').evaluateAll(nodes=>{const rows={};for(const node of nodes){const top=Math.round(node.getBoundingClientRect().top);rows[top]=(rows[top]||0)+1;}return Object.values(rows).every(count=>count===5);}), 'House menu retains five categories per row');
  await page.screenshot({path:'tests/artifacts/house-repair-home-1440.png',fullPage:true});
  await page.goto(base+'/fa-bu-xin-xi/',{waitUntil:'domcontentloaded'});
  await page.locator('[data-publish-category-id="'+data.parent_id+'"]').click();
  for(const item of data.items) {
   await page.locator('[data-publish-category-id="'+item.id+'"]').click();
   pass(await page.locator('#listing-category').inputValue()===String(item.id),'Publication can select: '+item.name);
  }
  for(const width of [1440,390,320]) {
   await page.setViewportSize({width,height:1000});await page.goto(base+'/fa-bu-xin-xi/',{waitUntil:'domcontentloaded'});
   await page.locator('[data-publish-category-id="'+data.parent_id+'"]').click();
   await page.locator('[data-publish-category-id="'+data.items.at(-1).id+'"]').click();
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'House category selection fits '+width+'px');
   await page.screenshot({path:'tests/artifacts/house-repair-publish-'+width+'.png',fullPage:true});
   await page.getByRole('button',{name:'下一步',exact:true}).click();
   pass(await page.locator('#publish-details').isVisible() && await page.locator('#listing-category').inputValue()===String(data.items.at(-1).id),'Selected house category reaches publication details at '+width+'px');
  }
  const admin=await browser.newContext({viewport:{width:1440,height:1000}});await admin.addCookies([{name:'xiu_session',value:fixture.admin_session,domain:'www.xiu.cn',path:'/'}]);
  const management=await admin.newPage();await management.goto(base+'/dgadmin/index.php?section=categories&category='+data.parent_id,{waitUntil:'domcontentloaded'});
  const text=await management.locator('.category-level[data-level="2"]').innerText();
  pass(data.items.every(item=>text.includes(item.name)),'Admin house category management includes all entries');
  for(const item of [data.items[0],data.items.at(-1)]) {
   await management.goto(base+'/dgadmin/index.php?section=categories&edit='+item.id,{waitUntil:'domcontentloaded'});
   pass(await management.locator('[name=name]').inputValue()===item.name,'Admin can open category editor: '+item.name);
  }
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync('tests/artifacts/house-repair-categories-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('PASS '+checks.length+' house category checks');
 } finally {if(browser) await browser.close();if(ready) console.log(execFileSync(php,['tests/house-repair-categories-fixture.php','cleanup'],{encoding:'utf8'}));}
})().catch(error=>{console.error(error);process.exit(1)});
