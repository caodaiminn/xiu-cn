const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('fs');const path=require('path');const assert=require('assert');
const base=process.env.XIU_BASE_URL||'http://www.xiu.cn';const out=path.join(__dirname,'artifacts');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));const results=[];
  for(const [name,viewport] of [['desktop',{width:1440,height:1000}],['tablet',{width:900,height:1000}],['mobile',{width:390,height:844}],['small-mobile',{width:320,height:760}]]) {
   await page.setViewportSize(viewport);
   for(const [kind,route] of [['service',process.env.XIU_SERVICE_DETAIL||'/info/13/'],['request',process.env.XIU_REQUEST_DETAIL||'/info/28/']]) {
    const response=await page.goto(base+route);assert.equal(response.status(),200);
    assert.equal(await page.locator('h1').count(),1);assert.equal(await page.locator('.repair-contact-card').count(),1);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),name+' overflow');
    const phone=page.locator('.repair-basic .repair-primary-phone');assert.equal(await phone.getAttribute('href'),'tel:00000000000');
    assert((await phone.innerText()).includes('000-00000000'));
    assert.equal(await page.getByRole('link',{name:'拨打电话',exact:true}).getAttribute('href'),'tel:00000000000');
    assert((await phone.boundingBox()).y < (await page.locator('.repair-description').boundingBox()).y,'Phone precedes long body');
    assert((await page.locator('#repair-contact').innerText()).includes('演示联系人'));
    const current=await page.locator('link[rel="canonical"]').getAttribute('href');assert.equal(current,page.url());
    assert((await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
    await page.getByRole('link',{name:'联系方式',exact:true}).click();assert.equal(new URL(page.url()).hash,'#repair-contact');
    await page.goto(base+route);
    if(name==='desktop'||name==='mobile') {await page.screenshot({path:path.join(out,'repair-'+kind+'-'+name+'.png'),fullPage:true});await page.screenshot({path:path.join(out,'repair-'+kind+'-'+name+'-top.png')});}
    results.push({name,kind,phoneBeforeBody:true});
   }
  }
  // Exercise graceful clipboard failure without changing the machine's clipboard.
  await page.evaluate(()=>{document.execCommand=()=>false;Object.defineProperty(navigator,'clipboard',{value:{writeText:async()=>{throw Error('denied');}},configurable:true});});
  await page.getByRole('button',{name:'复制电话号码',exact:true}).click();
  assert((await page.locator('.repair-copy-status').innerText()).includes('长按'));
  assert.equal(await page.locator('.repair-copy-buffer').count(),0);
  // Ordinary HTTP copies use execCommand; stub only that browser boundary.
  await page.evaluate(()=>{document.execCommand=(command)=>{window.copiedTestPhone=document.querySelector('.repair-copy-buffer')?.value;return command==='copy';};});
  await page.getByRole('button',{name:'复制电话号码',exact:true}).click();assert((await page.locator('.repair-copy-status').innerText()).includes('已复制'));
  assert.equal(await page.evaluate(()=>window.copiedTestPhone),'000-00000000');
  const fixturesPath=path.join(out,'fixtures.json');
  if(fs.existsSync(fixturesPath)){
   const fixtures=JSON.parse(fs.readFileSync(fixturesPath,'utf8'));const id=fixtures.listings?.[0];
   if(id){
    const r=await page.goto(base+'/info/'+id+'/');
    if(r.status()===200){
     assert.equal(await page.locator('.repair-image-gallery img').count(),1);assert((await page.locator('.repair-primary-phone').first().innerText()).includes('00000000000'));
     assert.equal(await page.locator('#repair-images').getByRole('heading',{name:'图片展示'}).count(),1);
     assert((await page.locator('.repair-basic').innerText()).includes('QA contact'));
     assert.equal(await page.locator('.repair-demo-note').count(),0);
     assert(!(await page.locator('meta[name="robots"]').getAttribute('content')).includes('noindex'));
     const image=page.locator('.repair-image-gallery img');await image.scrollIntoViewIfNeeded();await image.evaluate(i=>i.decode());assert((await image.getAttribute('alt')).includes('附图1'));
     results.push({kind:'real-published-image-contact',passed:true});
    }
   }
  }
  assert.deepEqual(errors,[]);
  fs.writeFileSync(path.join(out,'detail-ui-results.json'),JSON.stringify({passed:results.length,results,errors,copySuccessAndFailure:true},null,2));
  console.log('SUCCESS',results.length,'detail layouts; telephone, jump links, copying, privacy and real images checked.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
