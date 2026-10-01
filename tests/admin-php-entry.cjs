const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const out=path.join(__dirname,'artifacts'),fixturePath=path.join(out,'admin-php-entry-fixture.json');
const fixture=JSON.parse(fs.readFileSync(fixturePath,'utf8'));
const checks=[],errors=[],entry='/dgadmin/index.php';
const url=(base,section,params={})=>base+entry+'?'+new URLSearchParams({section,...params});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const contexts=[];
 try {
  for(const base of ['http://127.0.0.1:8817','http://www.xiu.cn']) {
   const ctx=await browser.newContext();contexts.push(ctx);await ctx.addCookies([{name:'xiu_session',value:fixture.session,url:base}]);
   const page=await ctx.newPage();page.on('pageerror',e=>errors.push(e.message));
   for(const [section,title] of Object.entries({dashboard:'运营概览',listings:'信息审核',categories:'分类管理',cities:'城市管理',districts:'区域管理',users:'用户管理',verifications:'身份认证审核',articles:'百科与知识库',navigation:'导航栏管理',settings:'站点设置',notifications:'邮件通知'})) {
    const res=await page.goto(url(base,section));assert.equal(res.status(),200,section);assert.equal(await page.locator('h1').innerText(),title);
    assert.match(res.headers()['x-robots-tag'],/noindex/);
    const links=await page.locator('a[href]').evaluateAll(nodes=>nodes.map(n=>n.getAttribute('href')).filter(h=>h.startsWith('/dgadmin')));
    assert(links.every(h=>h.startsWith('/dgadmin/index.php')),section+' links use PHP');
    const badForms=await page.locator('form').evaluateAll(nodes=>nodes.filter(f=>new URL(f.getAttribute('action')||location.href,location.href).pathname!=='/dgadmin/index.php').length);assert.equal(badForms,0,section+' form action');
    checks.push(base+' '+section+' PHP page and links');
   }
   await page.goto(url(base,'articles',{edit:8,kind:'encyclopedia'}));assert(await page.locator('.ql-editor').count());assert.equal(await page.locator('input[name=id]').first().inputValue(),'8');
   await page.locator('.topic-nav a').filter({hasText:'维修知识库'}).click();assert.equal(new URL(page.url()).searchParams.get('section'),'articles');assert.equal(new URL(page.url()).searchParams.get('kind'),'knowledge');checks.push(base+' article editor and kind switching');
   await page.goto(url(base,'cities'));await page.locator('.table-actions a').filter({hasText:'编辑'}).first().click();assert.equal(new URL(page.url()).searchParams.get('section'),'cities');assert(Number(new URL(page.url()).searchParams.get('edit'))>0);checks.push(base+' city edit link');
   await page.goto(url(base,'districts'));await page.locator('form[method=get] select[name=city]').selectOption({index:1});await Promise.all([page.waitForNavigation(),page.locator('form[method=get] button').click()]);assert.equal(new URL(page.url()).searchParams.get('section'),'districts');checks.push(base+' district GET filter');
   await page.goto(url(base,'listings',{status:'published',per_page:20}));await page.locator('.admin-page-size').first().locator('select').selectOption('30');await Promise.all([page.waitForNavigation(),page.locator('.admin-page-size').first().locator('button').click()]);assert.equal(new URL(page.url()).searchParams.get('section'),'listings');assert.equal(new URL(page.url()).searchParams.get('per_page'),'30');
   if(await page.locator('.pagination a').count()) {await page.locator('.pagination a').filter({hasText:'下一页'}).first().click();assert.equal(new URL(page.url()).searchParams.get('section'),'listings');assert.equal(new URL(page.url()).searchParams.get('page'),'2');}checks.push(base+' listing size and pagination');
   await page.goto(url(base,'verifications'));await page.locator('.topic-nav a').filter({hasText:'已通过'}).click();assert.equal(new URL(page.url()).searchParams.get('section'),'verifications');assert.equal(new URL(page.url()).searchParams.get('status'),'approved');checks.push(base+' verification status');
   assert.equal((await ctx.request.get(url(base,'missing'))).status(),404);
   assert.equal((await ctx.request.post(url(base,'navigation'),{form:{action:'delete',id:'0'}})).status(),403);checks.push(base+' invalid section and CSRF');
   for(const [section,id] of [['document',fixture.document],['media',fixture.image]]) { const image=await ctx.request.get(url(base,section,{id}));assert.equal(image.status(),200);assert.match(image.headers()['content-type'],/image\/png/);assert((await image.body()).length>20);checks.push(base+' '+section+' image without rewrite'); }
   await page.goto(url(base,'verifications'));const documentLink=page.locator(`a[href*="section=document"][href*="id=${fixture.document}"]`).first();assert(await documentLink.count());assert(await documentLink.locator('img').evaluate(img=>img.complete&&img.naturalWidth===32));checks.push(base+' verification material renders');
   await page.goto(url(base,'navigation'));await page.locator('input[name=label]').fill(fixture.label);await page.locator('input[name=url]').fill('/');await page.locator('input[name=sort_order]').fill('999999');await page.locator('select[name=enabled]').selectOption('0');
   await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'保存导航',exact:true}).click()]);assert.equal(new URL(page.url()).searchParams.get('section'),'navigation');
   const row=page.locator('.navigation-row').filter({hasText:fixture.label});await row.getByRole('link',{name:'编辑',exact:true}).click();assert.equal(new URL(page.url()).searchParams.get('section'),'navigation');await page.locator('input[name=url]').fill('/selectcity.html');await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'保存导航',exact:true}).click()]);assert(await row.innerText().then(t=>t.includes('/selectcity.html')));
   page.once('dialog',d=>d.accept());await Promise.all([page.waitForNavigation(),row.getByRole('button',{name:'删除',exact:true}).click()]);assert.equal(await row.count(),0);checks.push(base+' create edit delete through PHP forms');
   if(base.includes('www.xiu.cn')) {
    const legacy=await ctx.request.get(base+'/dgadmin/articles/?edit=8&kind=encyclopedia',{maxRedirects:0});assert.equal(legacy.status(),301);assert.equal(legacy.headers().location,entry+'?section=articles&edit=8&kind=encyclopedia');
    assert.equal((await ctx.request.get(base+'/dgadmin/')).status(),200);
    assert.equal((await ctx.request.get(base+'/')).status(),200);assert.equal((await ctx.request.get(base+'/wei-xiu-bai-ke/')).status(),200);assert.equal((await ctx.request.get(base+'/anything.php')).status(),404);checks.push('Nginx old URL compatibility and frontend preserved');
    await page.goto(url(base,'articles',{edit:8,kind:'encyclopedia'}));await page.screenshot({path:path.join(out,'admin-php-entry.png'),fullPage:true});
   }
  }
  const anon=await browser.newContext();contexts.push(anon);const base='http://127.0.0.1:8817';const denied=await anon.request.get(base+entry,{maxRedirects:0});assert.equal(denied.status(),303);assert.equal(denied.headers().location,entry+'?section=login');
  const page=await anon.newPage();await page.goto(url(base,'login'));await page.locator('input[name=username]').fill('invalid_entry_qa');await page.locator('input[name=password]').fill('invalid-entry-password');await Promise.all([page.waitForNavigation(),page.locator('form').filter({has:page.locator('input[name=password]')}).locator('button').click()]);assert(new URL(page.url()).searchParams.get('section')==='login');assert((await page.innerText('body')).includes('账号或密码不正确'));checks.push('no-rewrite login entry and invalid login form');
  for(const [section,id] of [['document',fixture.document],['media',fixture.image]]) assert.equal((await anon.request.get(url(base,section,{id}),{maxRedirects:0})).status(),303);checks.push('PHP images require admin authentication');
  const ctx=contexts[0];const logoutPage=await ctx.newPage();await logoutPage.goto(url(base,'dashboard'));await Promise.all([logoutPage.waitForNavigation(),logoutPage.locator('.admin-header form button').click()]);assert.equal(new URL(logoutPage.url()).searchParams.get('section'),'login');assert.equal((await ctx.request.get(url(base,'dashboard'),{maxRedirects:0})).status(),303);checks.push('logout and session invalidation');
  assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'admin-php-entry-results.json'),JSON.stringify({passed:checks.length,checks,errors},null,2));console.log(JSON.stringify({passed:checks.length,checks}));
 } finally {
  fixture.extraSessions=[];for(const context of contexts)for(const cookie of await context.cookies())if(cookie.name==='xiu_session')fixture.extraSessions.push(cookie.value);fs.writeFileSync(fixturePath,JSON.stringify(fixture,null,2));await browser.close();
 }
})().catch(e=>{console.error(e);process.exitCode=1});
