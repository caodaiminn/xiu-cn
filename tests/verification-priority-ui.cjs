const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const base='http://www.xiu.cn',out='tests/artifacts/',fixture=JSON.parse(fs.readFileSync(out+'verification-priority-fixtures.json','utf8'));
const php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe';
const checks=[],pass=(value,label)=>{assert(value,label);checks.push(label);console.log('PASS',label)};
const updateStatus=(kind,status)=>execFileSync(php,['-r',`require 'app/bootstrap.php'; query('UPDATE xiu_user_verifications SET status=? WHERE user_id=?',['${status}',${fixture.users[kind]}]);`]);
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const ids=async()=>await page.locator('.listing-row h2 a').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
  const expected=fixture.expected.map(id=>fixture.listings.find(item=>item.id===id).url);
  for(const [name,route] of [['keyword','/fen-lei-xin-xi/?q='+fixture.token],['category',fixture.category_url],['city',fixture.city_category_url],['district',fixture.district_url]]){
   await page.goto(base+route);const first=await ids();await page.getByRole('link',{name:'2',exact:true}).click();
   pass(JSON.stringify(first.concat(await ids()))===JSON.stringify(expected),name+' HTTP pagination respects enterprise > personal > unverified');
  }
  await page.goto(base+'/');const home=await page.locator('.latest-list a').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
  pass(JSON.stringify(home.slice(0,4))===JSON.stringify(expected.slice(0,4)),'Homepage certified items precede newer unverified items');
  const categoryItems=await page.locator('.category-section').filter({has:page.getByRole('heading',{name:fixture.token+' category',exact:true})}).locator('.text-list a').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
  pass(JSON.stringify(categoryItems)===JSON.stringify(expected.slice(0,5)),'Homepage category section shares ranking');
  for(const width of [1440,390,320]){
   await page.setViewportSize({width,height:900});
   for(const kind of ['company','personal','none','pending','rejected']){
    const listing=fixture.listings.find(item=>item.kind===kind);const r=await page.goto(base+listing.url);
    pass(r.status()===200,width+' '+kind+' detail HTTP 200');
    const badges=page.locator('[data-verification-type]');const approved=['company','personal'].includes(kind);
    pass(await badges.count()===(approved?2:0),width+' '+kind+' only approved identity is marked');
    if(approved){
     pass(await page.locator('.repair-basic [data-verification-type='+kind+']').isVisible(),width+' '+kind+' identity visible beside main contact');
     pass(await page.locator('.repair-contact-card [data-verification-type='+kind+']').isVisible(),width+' '+kind+' identity visible in contact card');
    }
    pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' '+kind+' no horizontal overflow');
    pass(!(await page.content()).includes('Private synthetic identity') && !(await page.content()).includes('/zheng-jian/'),width+' '+kind+' private identity details not leaked');
    if(approved)await page.screenshot({path:out+'verification-'+kind+'-'+width+'.png',fullPage:true});
   }
   await page.goto(base+'/fen-lei-xin-xi/?q='+fixture.token);
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),width+' ranked list no horizontal overflow');
  }
  await page.setViewportSize({width:1440,height:900});
  const company=fixture.listings.find(item=>item.kind==='company');
  for(const status of ['pending','rejected']){
   updateStatus('company',status);await page.goto(base+company.url);
   pass(await page.locator('[data-verification-type]').count()===0,status+' certification immediately loses detail badge');
   await page.goto(base+'/fen-lei-xin-xi/?q='+fixture.token);
   pass((await ids())[0]===fixture.listings.filter(item=>item.kind==='personal').at(-1).url,status+' certification immediately loses ranking priority');
  }
  updateStatus('company','approved');await page.goto(base+company.url);
  pass(await page.locator('[data-verification-type=company]').count()===2,'Approval restores badges without editing listing');
  const related=await page.locator('.repair-related li a').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
  pass(JSON.stringify(related)===JSON.stringify(expected.slice(0,5).filter(url=>url!==company.url)),'Related items use shared certification ranking');
  await page.goto(base+'/fen-lei-xin-xi/?q='+fixture.token);pass((await ids())[0]===expected[0],'Approval restores ranking without editing listing');
  await page.goto(base+'/wei-xiu-xin-xi/bjxyxyjw8812.html');pass(await page.locator('h1').count()===1,'Requested existing detail URL still renders');
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync(out+'verification-priority-ui-results.json',JSON.stringify({passed:checks.length,checks},null,2));
  console.log('SUCCESS',checks.length,'ranking and identity browser checks.');
 }finally{updateStatus('company','approved');await browser.close()}
})().catch(error=>{console.error(error);process.exit(1)});
