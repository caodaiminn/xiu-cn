const { chromium } = require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const base = process.env.XIU_BASE_URL || 'http://www.xiu.cn';
const artifacts = path.join(__dirname, 'artifacts');
const results = [];
fs.mkdirSync(artifacts, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.XIU_CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const routes = [['home','/'],['services','/services/'],['cities','/cities/'],['knowledge','/baixiuquan/'],['encyclopedia','/baike/'],['information','/info/28/'],['article','/baixiuquan/1.html'],['register','/register/']];
    for (const [name, viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}],['small-mobile',{width:320,height:760}]]) {
      await page.setViewportSize(viewport);
      for (const [routeName, route] of routes) {
        const response = await page.goto(base + route);
        assert.strictEqual(response.status(),200,routeName+' status');
        await page.locator('main').waitFor({state:'visible'});
        const layout = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, headings: Array.from(document.querySelectorAll('h1')).map(element => element.innerText), font: getComputedStyle(document.body).fontFamily }));
        assert(layout.scrollWidth <= layout.width+1,`${name} ${routeName} horizontal overflow: ${JSON.stringify(layout)}`);
        assert(layout.headings.length === 1,`${name} ${routeName} should have one main heading`);
        results.push({ viewport: name, route, ...layout });
        if (name !== 'small-mobile') await page.screenshot({path:path.join(artifacts,routeName+'-'+name+'.png'),fullPage:true});
        if (routeName === 'home' && name !== 'small-mobile') await page.screenshot({path:path.join(artifacts,'home-'+name+'-top.png')});
      }
    }
    // Browser form navigation: keyword search and account registration.
    await page.setViewportSize({width:1440,height:1000});
    await page.goto(base+'/');
    await page.getByLabel('搜索维修服务',{exact:true}).fill('杭州');
    await Promise.all([page.waitForURL('**/fen-lei-xin-xi/?q=*'),page.locator('.header-search button').click()]);
    assert((await page.locator('.listing-row').count()) > 0,'Search returns matching information');
    const fixtures = JSON.parse(fs.readFileSync(path.join(artifacts,'fixtures.json'),'utf8'));
    const username = fixtures.token+'_ui';
    await page.goto(base+'/register/');
    await page.getByLabel('账号',{exact:true}).fill(username);
    await page.getByLabel('密码',{exact:true}).fill('UITestPassword2026');
    await page.getByLabel('确认密码',{exact:true}).fill('UITestPassword2026');await page.getByLabel('联系电话',{exact:true}).fill('13800000000');
    await Promise.all([page.waitForURL('**/yong-hu-zhong-xin/'),page.getByRole('button',{name:'注册并登录',exact:true}).click()]);
    fixtures.users.push(username);
    fs.writeFileSync(path.join(artifacts,'fixtures.json'),JSON.stringify(fixtures));
    await page.goto(base+'/publish/');
    for (const [name, viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}]]) {
      await page.setViewportSize(viewport);
      await page.screenshot({path:path.join(artifacts,'publish-'+name+'.png'),fullPage:true});
      assert(await page.evaluate(() => document.documentElement.scrollWidth<=innerWidth+1),'Publishing form overflow');
      results.push({viewport:name,route:'/publish/',form:true});
    }
    await page.goto(base+'/dgadmin/login/');
    await page.getByLabel('账号',{exact:true}).fill('daiit');
    await page.getByLabel('密码',{exact:true}).fill(process.env.XIU_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL(base+'/dgadmin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
    for (const [name, viewport] of [['desktop',{width:1440,height:1000}],['mobile',{width:390,height:844}]]) {
      await page.setViewportSize(viewport);
      for (const section of ['','listings/','categories/','cities/','users/','articles/','settings/']) {
        await page.goto(base+'/dgadmin/'+section);
        assert(await page.evaluate(() => document.documentElement.scrollWidth<=innerWidth+1),'Admin '+section+' overflow');
        results.push({viewport:name,route:'/dgadmin/'+section});
        if(['','listings/','articles/'].includes(section)) await page.screenshot({path:path.join(artifacts,'admin-'+(section.replace('/','')||'dashboard')+'-'+name+'.png'),fullPage:true});
      }
    }
    assert.deepStrictEqual(errors,[],'No browser JavaScript errors');
    fs.writeFileSync(path.join(artifacts,'ui-results.json'),JSON.stringify({passed:results.length,results,errors},null,2));
    console.log(`PASS ${results.length} route/viewport checks, browser search and registration; no JavaScript errors.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
