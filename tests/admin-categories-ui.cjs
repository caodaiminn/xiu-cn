const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
const base=process.env.XIU_BASE_URL||'http://www.xiu.cn',out=path.join(__dirname,'artifacts');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const context=await browser.newContext(),page=await context.newPage(),errors=[],checks=[],created=[];
 const token='xiuqa_'+Date.now(); page.on('pageerror',e=>errors.push(e.message));
 const pass=(condition,label)=>{assert(condition,label);checks.push(label);console.log('PASS',label);};
 const post=async(data)=>{
  const r=await context.request.get(base+'/dgadmin/categories/');
  const csrf=(await r.text()).match(/name="csrf" value="([a-f0-9]+)"/)[1];
  return context.request.post(base+'/dgadmin/categories/',{form:{csrf,...data}});
 };
 try {
  await page.goto(base+'/dgadmin/login/');await page.getByLabel('账号',{exact:true}).fill('daiit');await page.getByLabel('密码',{exact:true}).fill(process.env.XIU_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL(base+'/dgadmin/'),page.getByRole('button',{name:'登录',exact:true}).click()]);
  for(const [name,viewport] of [['desktop',{width:1665,height:1000}],['mobile',{width:390,height:844}],['small-mobile',{width:320,height:760}]]) {
   await page.setViewportSize(viewport);await page.goto(base+'/dgadmin/categories/?category=35');
   pass(await page.locator('[data-level="1"] .is-selected').innerText()==='工控产品维修',name+' selected root');
   pass(await page.locator('[data-level="2"] .category-choice').count()===3,name+' only corresponding children');
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' no horizontal overflow');
   pass(await page.locator('.category-choice').evaluateAll(items=>items.every(e=>e.scrollHeight<=e.clientHeight+1)),name+' complete category labels');
   await page.locator('.category-manager').screenshot({path:path.join(out,'admin-categories-'+name+'.png')});
   await page.screenshot({path:path.join(out,'admin-categories-page-'+name+'.png'),fullPage:true});
  }
  await page.setViewportSize({width:1440,height:1000});await page.goto(base+'/dgadmin/categories/');
  await page.locator('[data-level="2"]').getByRole('link',{name:'空调维修',exact:true}).click();
  pass(await page.locator('[data-level="3"] .category-choice').count()===2,'Third level remains accessible');
  await page.locator('[data-level="2"]').getByRole('link',{name:'＋ 新增二级分类',exact:true}).click();
  pass(await page.locator('[name="parent_id"]').inputValue()==='1','New second level preselects root');
  await page.getByLabel('分类名称',{exact:true}).fill(token+' category');
  await Promise.all([page.waitForURL(/category=\d+/),page.getByRole('button',{name:'保存分类',exact:true}).click()]);
  const id=Number(new URL(page.url()).searchParams.get('category'));created.push(id);
  pass(await page.locator('[data-level="2"] .is-selected').innerText()===token+' category','Save retains selected child');
  const row=page.locator('tr').filter({has:page.locator('td').getByText(token+' category',{exact:true})});
  const publicUrl=await row.getByRole('link',{name:'查看页面'}).getAttribute('href');
  await page.getByRole('link',{name:'编辑当前分类',exact:true}).click();
  pass((await page.locator('[name="slug"]').inputValue()).length>0,'Compatibility slug generated automatically');
  await page.getByLabel('分类名称',{exact:true}).fill(token+' renamed');await page.locator('[name="sort_order"]').fill('-99');
  await Promise.all([page.waitForURL(/category=\d+/),page.getByRole('button',{name:'保存分类',exact:true}).click()]);
  pass((await page.locator('[data-level="2"] .category-choice').first().innerText())===token+' renamed','Sort order applied within parent');
  pass(await page.locator('tr').filter({hasText:token+' renamed'}).getByRole('link',{name:'查看页面'}).getAttribute('href')===publicUrl,'Rename preserves public URL');
  await page.locator('tr').filter({hasText:token+' renamed'}).getByRole('link',{name:'新增子分类'}).click();
  pass(await page.locator('[name="parent_id"]').inputValue()===String(id),'New third level preselects second level');
  await page.getByLabel('分类名称',{exact:true}).fill(token+' third');
  await Promise.all([page.waitForURL(/category=\d+/),page.getByRole('button',{name:'保存分类',exact:true}).click()]);
  const child=Number(new URL(page.url()).searchParams.get('category'));created.push(child);
  pass(await page.locator('[data-level="3"] .is-selected').innerText()===token+' third','Third level create and selection');
  let r=await post({action:'delete',id});pass((await r.text()).includes('仍被引用'),'Parent with children cannot be deleted');
  r=await post({name:token+' fourth',kind:'service',parent_id:child,enabled:1});pass((await r.text()).includes('最多支持三级'),'Forged fourth level rejected');
  r=await context.request.post(base+'/dgadmin/categories/',{form:{csrf:'bad',name:token+' forged',kind:'service',enabled:1}});pass(r.status()===403,'Writes require valid CSRF');
  r=await post({name:token+' category',kind:'knowledge',parent_id:0,enabled:1});
  let source=await r.text(),otherId=Number(new URL(r.url()).searchParams.get('category'));created.push(otherId);
  pass(otherId>0 && source.includes('操作已保存'),'Other purposes support automatic unique slugs');
  await page.goto(base+'/dgadmin/categories/?kind=knowledge&category='+otherId);
  pass(!(await page.locator('[data-level="1"]').innerText()).includes('工控产品维修'),'Category purposes remain separate');
  await page.goto(base+'/dgadmin/categories/?edit='+id);
  pass(await page.locator('#category-parent option[value="'+id+'"]').count()===0 && await page.locator('#category-parent option[value="'+child+'"]').count()===0,'Editor excludes self and descendants');
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync(path.join(out,'admin-categories-ui-results.json'),JSON.stringify({passed:checks.length,checks,errors},null,2));
 } finally {
  for(const id of created.reverse()) { const r=await post({action:'delete',id});assert((await r.text()).includes('操作已保存'),'Fixture cleanup '+id); }
  await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1);});
