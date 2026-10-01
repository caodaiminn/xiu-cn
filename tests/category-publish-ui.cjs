const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs');const path=require('path');const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try {
  const page=await browser.newPage();const base=process.env.XIU_BASE_URL||'http://www.xiu.cn';
  const fixtures=JSON.parse(fs.readFileSync(path.join(__dirname,'artifacts/fixtures.json'),'utf8'));const username=fixtures.users.find(name=>name.endsWith('_ui'));
  assert(username,'Run smoke.py and ui.cjs first, before cleanup.php');
  await page.goto(base+'/deng-lu/');await page.getByLabel('账号',{exact:true}).fill(username);await page.getByLabel('密码',{exact:true}).fill('UITestPassword2026');
  await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  await page.goto(base+'/fa-bu-xin-xi/');
  await page.locator('[data-publish-category-id="1"]').click();await page.locator('[data-publish-category-id="2"]').click();await page.getByRole('button',{name:'下一步',exact:true}).click();
  await page.locator('#listing-province').selectOption('广东省');
  await page.locator('#listing-city').selectOption('3');
  const option=page.locator('#listing-district option').filter({hasText:'天河'});const district=await option.getAttribute('value');
  assert.equal(await option.evaluate(e=>e.disabled),false);
  await page.locator('#listing-district').selectOption(district);assert.equal(await page.locator('#listing-district').inputValue(),district);
  await page.locator('#listing-province').selectOption('浙江省');
  assert.equal(await page.locator('#listing-city').inputValue(),'');
  await page.locator('#listing-city').selectOption('6');assert.equal(await page.locator('#listing-district').inputValue(),'0');
  assert.equal(await option.count(),0);
  const results=[];
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}]]) {
   await page.setViewportSize(viewport);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   await page.screenshot({path:path.join(__dirname,'artifacts','district-publish-'+name+'.png'),fullPage:true});results.push({name,cityAreaLinked:true});
  }
  fs.writeFileSync(path.join(__dirname,'artifacts/category-publish-ui-results.json'),JSON.stringify({passed:results.length,results},null,2));
  console.log('SUCCESS city/district selection and switching reset; publishing desktop/mobile layouts.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
