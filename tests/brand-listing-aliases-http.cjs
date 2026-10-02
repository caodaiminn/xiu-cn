const fs=require('fs'),assert=require('assert');
const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const data=JSON.parse(fs.readFileSync('tests/artifacts/brand-listing-aliases-results.json','utf8'));
 assert(data.passed,'Run PHP alias verification first');
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const context=await browser.newContext();const base='http://www.xiu.cn',results=[];
 try {
  for(let start=0;start<data.samples.length;start+=6) {
   await Promise.all(data.samples.slice(start,start+6).map(async sample=>{
    const old=await context.request.get(base+sample.old,{maxRedirects:0});
    assert(old.status()===301 && old.headers().location===base+sample.current,'Old URL must redirect to its own canonical detail: '+sample.old);
    const current=await context.request.get(base+sample.current);
    assert(current.status()===200 && (await current.text()).includes(sample.title),'Canonical detail must display the matching listing');
    const followed=await context.request.get(base+sample.old);
    assert(followed.status()===200 && followed.url()===base+sample.current,'Shared old URL must open successfully after redirect');
    results.push({old:sample.old,current:sample.current,id:sample.id,redirect_status:old.status(),detail_status:current.status(),follow_status:followed.status()});
   }));
  }
  const missing=await context.request.get(base+'/wei-xiu-xin-xi/azzzzzzz9999.html');assert(missing.status()===404,'Unknown aliases remain 404');
  const supplied=data.samples.find(sample=>sample.old==='/wei-xiu-xin-xi/ab0b406a2025.html');assert(supplied,'User-reported URL is covered');
  const page=await context.newPage();const response=await page.goto(base+supplied.old,{waitUntil:'domcontentloaded'});
  assert(response.status()===200 && page.url()===base+supplied.current && await page.locator('h1').innerText()===supplied.title,'User-reported URL renders the correct detail in a browser');
  fs.writeFileSync('tests/artifacts/brand-listing-aliases-http-results.json',JSON.stringify({passed:true,sampled_old_urls:results.length,checks:results.length*3+2,reported_url_title:supplied.title,results},null,2));
  console.log(JSON.stringify({passed:true,sampled_old_urls:results.length,checks:results.length*3+2,reported_url_title:supplied.title}));
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
