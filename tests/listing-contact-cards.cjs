const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert'),{execFileSync}=require('child_process');
const php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe',base='http://www.xiu.cn',out=path.join(__dirname,'artifacts');
const checks=[],pass=(condition,label)=>{assert(condition,label);checks.push(label);console.log('PASS',label);};
const data=JSON.parse(execFileSync(php,['-r',`require 'app/bootstrap.php'; echo json_encode(listingSearch([],1,12),JSON_UNESCAPED_UNICODE);`],{encoding:'utf8'}));
const helper=JSON.parse(execFileSync(php,['-r',`require 'app/bootstrap.php'; $now=strtotime('2026-10-01 20:00:00'); $labels=[]; foreach([0,59,60,3599,3600,18000,86400,2592000,31536000,-3600] as $seconds) $labels[]=relativeListingTime(date('Y-m-d H:i:s',$now-$seconds),$now); $labels[]=relativeListingTime('invalid',$now); echo json_encode($labels,JSON_UNESCAPED_UNICODE);`],{encoding:'utf8'}));
pass(JSON.stringify(helper)===JSON.stringify(['刚刚','刚刚','1分钟前','59分钟前','1小时前','5小时前','1天前','1个月前','1年前','刚刚','时间待更新']),'Relative time uses actual elapsed minutes hours days months and years');
const render=execFileSync(php,['-r',`require 'app/bootstrap.php'; $item=['id'=>1,'short_slug'=>'test','refreshed_at'=>null,'created_at'=>'2026-10-01 12:00:00','phone'=>'123<img src=x onerror=alert(1)>','image_id'=>null,'title'=>'<script>alert(1)</script>','type'=>'service','city_name'=>'测试','category_name'=>'维修','is_demo'=>0,'contact'=>'<img src=x onerror=alert(1)>']; require 'views/listing-card.php';`],{encoding:'utf8'});
pass(!render.includes('<script>')&&!render.includes('<img src=x')&&render.includes('&lt;script&gt;'),'Card escapes title contact and telephone values');
const refreshed=execFileSync(php,['-r',`require 'app/bootstrap.php'; $item=['id'=>1,'short_slug'=>'test','refreshed_at'=>date('Y-m-d H:i:s',time()-3600),'created_at'=>'2020-01-01 12:00:00','phone'=>'123456789','image_id'=>null,'title'=>'测试刷新时间','type'=>'service','city_name'=>'测试','category_name'=>'维修','is_demo'=>0,'contact'=>'测试联系人']; require 'views/listing-card.php';`],{encoding:'utf8'});
pass(refreshed.includes('>1小时前</time>')&&refreshed.includes('title="刷新时间：'),'Refreshed card uses refresh time rather than original publication time');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const response=await page.goto(base+'/fen-lei-xin-xi/');pass(response.status()===200,'Public classification list loads');
  const cards=page.locator('.listing-contact-card');pass(await cards.count()===data.items.length,'All published results use new card layout');
  pass(await page.locator('.listing-cover img').evaluateAll(async images=>{images.forEach(image=>{image.loading='eager';});await Promise.all(images.map(image=>image.decode()));return images.every(image=>image.naturalWidth>0);}), 'Uploaded images and default covers load correctly');
  const actualIds=await cards.evaluateAll(nodes=>nodes.map(n=>Number(n.dataset.listingId)));
  pass(JSON.stringify(actualIds)===JSON.stringify(data.items.map(i=>Number(i.id))),'Existing refresh and certification ordering retained');
  for(let index=0;index<data.items.length;index++){
   const item=data.items[index],card=cards.nth(index);
   pass((await card.locator('h2').innerText())===item.title,'Title retained '+item.id);
   pass((await card.locator('.listing-publisher').innerText()).includes(item.contact),'Publisher retained '+item.id);
   const source=await card.locator('.listing-cover img').getAttribute('src');
   pass(source===(item.image_id?'/media/'+item.image_id+'/':'/assets/listing-cover.svg'),'Uploaded image or fallback used '+item.id);
   const time=card.locator('time'),date=item.refreshed_at||item.created_at;
   pass((await time.getAttribute('title')).includes(date)&&(await time.getAttribute('datetime')).includes(date.slice(0,10))&&/^(刚刚|\d+(分钟|小时|天|个月|年)前)$/.test(await time.innerText()),'Actual publication or refresh time shown '+item.id);
   pass(await card.locator('.listing-phone').isHidden(),'Telephone hidden before consultation '+item.id);
   await card.locator('summary').click();
   pass(await card.locator('.listing-phone').isVisible()&&(await card.locator('.listing-phone a').innerText())===item.phone&&(await card.locator('.listing-phone a').getAttribute('href'))==='tel:'+item.phone.replace(/[^0-9+]/g,''),'Consultation reveals matching callable telephone '+item.id);
   await card.locator('summary').click();
   pass(await card.locator('.listing-phone').isHidden(),'Consultation closes '+item.id);
   const badge=card.locator('.listing-verification');
   pass(await badge.count()===(item.verification_type?1:0),'Only approved identity badge displayed '+item.id);
  }
  for(const width of [1440,900,390,320]){
   await page.setViewportSize({width,height:width>800?1000:844});await page.goto(base+'/fen-lei-xin-xi/');
   const first=page.locator('.listing-contact-card').first();await first.scrollIntoViewIfNeeded();
   await first.locator('.listing-cover img').evaluate(img=>img.decode());
   pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No page overflow at '+width);
   pass(await first.evaluate(card=>[...card.querySelectorAll('h2,.listing-meta,.listing-publisher')].every(n=>n.scrollWidth<=n.clientWidth+1)),'Complete Chinese titles metadata and publisher at '+width);
   await first.locator('summary').focus();await page.keyboard.press('Enter');
   pass(await first.locator('.listing-phone').isVisible(),'Keyboard reveals telephone at '+width);
   pass(await first.evaluate(card=>card.scrollWidth<=card.clientWidth+1),'Expanded card fits at '+width);
   await page.screenshot({path:path.join(out,'listing-contact-'+width+'.png')});
   if(width===1440)await first.screenshot({path:path.join(out,'listing-contact-desktop-card.png')});
  }
  const nojs=await browser.newContext({javaScriptEnabled:false});const plain=await nojs.newPage();await plain.goto(base+'/fen-lei-xin-xi/');
  await plain.locator('.listing-consult summary').first().click();
  pass(await plain.locator('.listing-phone').first().isVisible(),'Consultation works without JavaScript');await nojs.close();
  await page.goto(base+'/fen-lei-xin-xi/?q='+encodeURIComponent(data.items[0].title));
  pass((await page.locator('.listing-contact-card').first().innerText()).includes(data.items[0].title),'Keyword filter uses matching cards');
  if(data.pages>1){await page.goto(base+'/fen-lei-xin-xi/?page=2');pass(await page.locator('.listing-contact-card').count()>0,'Pagination continues displaying cards');}
  await page.locator('.listing-contact-card h2 a').first().click();pass(await page.locator('.repair-information-header h1').count()===1,'Title opens repair detail page');
  pass(errors.length===0,'No browser script errors');
  fs.writeFileSync(path.join(out,'listing-contact-results.json'),JSON.stringify({passed:checks.length,checks,errors},null,2));console.log('SUCCESS',checks.length,'checks; read-only business data verification');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
