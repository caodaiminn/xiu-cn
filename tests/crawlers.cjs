const fs=require('fs'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const php='D:/phpstudy_pro/Extensions/php/php8.0.2nts/php.exe',base='http://www.xiu.cn',checks=[];
const cli=(...args)=>execFileSync(php,['tests/crawlers-fixture.php',...args],{encoding:'utf8'});
const read=path=>JSON.parse(fs.readFileSync(path,'utf8').replace(/^\uFEFF/,''));
const pass=(value,label)=>{assert(value,label);checks.push(label);};
(async()=>{
 let browser,ready=false;
 try {
  cli('setup');ready=true;const fixture=read('tests/artifacts/crawlers-fixture.json'),token=fixture.token;
  browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const admin=await browser.newContext({viewport:{width:1440,height:1000}}),publicContext=await browser.newContext();
  await admin.addCookies([{name:'xiu_session',value:fixture.session,domain:'www.xiu.cn',path:'/'}]);
  const page=await admin.newPage(),errors=[],dialogs=[],policyErrors=[];page.on('pageerror',error=>errors.push(error.message));page.on('console',message=>{if(message.type()==='error'&&/Content Security Policy|Refused to/i.test(message.text())) policyErrors.push(message.text());});page.on('dialog',async dialog=>{dialogs.push(dialog.message());await dialog.dismiss();});
  const ua=agent=>({headers:{'User-Agent':agent+' '+token}});
  const before=JSON.parse(cli('visits')).length;
  const probes=[['Baiduspider/2.0','/','GET',200,'baidu'],['Googlebot/2.1','/services','GET',301,'google'],['bingbot/2.0','/'+token+'/missing','GET',404,'bing'],['Sogou web spider/4.0','/robots.txt','GET',200,'sogou'],['GPTBot/1.4','/sitemap.xml','GET',200,'gptbot'],['OAI-SearchBot/1.0','/'+token+'/missing-two','HEAD',404,'oai-search'],['ClaudeBot/1.0 <script>alert(1)</script>','/'+token+'/unsafe','GET',404,'claude'],['PerplexityBot/1.0','/robots.txt','HEAD',200,'perplexity'],['FutureCrawler/1.0','/'+token+'/unknown','GET',404,'other']];
  for(const [agent,path,method,status,bot] of probes) {const response=await publicContext.request.fetch(base+path,{...ua(agent),method,maxRedirects:0});pass(response.status()===status,'Capture HTTP response '+method+' '+path);}
  let visits;for(let attempt=0;attempt<20;attempt++){visits=JSON.parse(cli('visits'));if(visits.length===before+probes.length) break;await new Promise(resolve=>setTimeout(resolve,100));}
  pass(visits.length===before+probes.length,'Exactly one visit saved per crawler request, including redirects and 404s');
  const captured=visits.slice(before);for(let i=0;i<probes.length;i++){const [agent,path,method,status,bot]=probes[i];pass(captured[i].bot_key===bot&&captured[i].path===path&&captured[i].method===method&&Number(captured[i].status_code)===status,'Recorded identity, path, method and final status: '+bot);}
  await publicContext.request.get(base+'/'+token+'/query?password=private-test-value',ua('Baiduspider/2.0'));
  await publicContext.request.get(base+'/'+token+'/normal',ua('Mozilla/5.0 Chrome/130 Safari/537.36'));
  for(const path of ['/dgadmin/index.php?section=dashboard','/deng-lu/','/zhu-ce/','/fa-bu-xin-xi/','/yong-hu-zhong-xin/','/api/city-location']) await publicContext.request.get(base+path,ua('GPTBot/1.4'));
  visits=JSON.parse(cli('visits'));pass(visits.length===before+probes.length+1,'Browser and private/API visits do not enter crawler statistics');
  pass(visits.at(-1).path==='/'+token+'/query'&&!JSON.stringify(visits).includes('private-test-value'),'Query parameters are not stored');
  const url=(period,extra={})=>base+'/dgadmin/index.php?'+new URLSearchParams({section:'dashboard',period,...extra});
  const knownGroups={baidu:'search',google:'search',bing:'search',sogou:'search',gptbot:'ai','oai-search':'ai',claude:'ai','chatgpt-user':'ai',perplexity:'ai',other:'other'};
  const all=[...fixture.baseline,...visits];
  for(const period of ['today','yesterday','week','month']) {
   const model=JSON.parse(cli('snapshot',period)),selected=all.filter(v=>v.occurred_at>=model.range.start&&v.occurred_at<model.range.end);
   pass(model.totals.visits===selected.length,'Exact range totals: '+period);
   pass(model.totals.errors===selected.filter(v=>Number(v.status_code)>=400).length,'Exact abnormal counts: '+period);
   for(const bot of ['baidu','google','bing','sogou','gptbot','oai-search','claude','chatgpt-user','perplexity','other']) {const rows=selected.filter(v=>v.bot_key===bot),provider=model.providers[bot];pass(Number(provider.visits)===rows.length&&Number(provider.urls)===new Set(rows.map(v=>v.path)).size,'Counts and distinct URLs: '+period+' '+bot);}
   pass(Object.values(model.trend).reduce((sum,p)=>sum+p.search+p.ai+p.other,0)===selected.length,'Trend reconciles to total: '+period);
   await page.goto(url(period),{waitUntil:'domcontentloaded'});
   pass(await page.locator('[data-crawler-metric=visits]').innerText()===selected.length.toLocaleString('en-US'),'Rendered metric for '+period);
   pass(await page.locator('.crawler-periods .active').getAttribute('data-crawler-period')===period,'Active time selector '+period);
  }
  await page.goto(url('today'));await Promise.all([page.waitForNavigation(),page.locator('[data-crawler-period=yesterday]').click()]);pass(new URL(page.url()).searchParams.get('period')==='yesterday','Time buttons perform actual navigation');
  await page.goto(url('today'));await page.locator('#crawler-bot').selectOption('gptbot');await Promise.all([page.waitForNavigation(),page.locator('.crawler-details form button').click()]);
  pass(await page.locator('.crawler-detail-table tbody tr').count()===20,'Crawler-filtered details use 20-record pagination');
  pass((await page.locator('.crawler-detail-table tbody tr').allTextContents()).every(text=>text.includes('GPTBot')),'Detail filter displays only selected crawler');
  await Promise.all([page.waitForNavigation(),page.locator('.crawler-details .pagination a',{hasText:'下一页'}).click()]);pass(new URL(page.url()).searchParams.get('bot')==='gptbot'&&new URL(page.url()).searchParams.get('crawler_page')==='2','Pagination preserves period and crawler filter');
  await page.goto(url('today',{bot:'claude'}));await page.locator('.crawler-detail-table details').first().locator('summary').click();
  pass((await page.locator('.crawler-detail-table').innerText()).includes('<script>alert(1)</script>')&&await page.locator('.crawler-detail-table script').count()===0&&dialogs.length===0,'UA text escaped without executing HTML');
  await page.goto(url('invalid',{bot:'invalid',crawler_page:'999999'}));pass(await page.locator('[data-crawler-period=today]').getAttribute('aria-current')==='page','Invalid filters default safely');
  for(const width of [2048,1440,900,390,320]) {await page.setViewportSize({width,height:1000});await page.goto(url('month'));pass(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Dashboard fits '+width+'px');await page.screenshot({path:'tests/artifacts/crawlers-dashboard-'+width+'.png',fullPage:true});}
  const guest=await publicContext.request.get(url('month'),{maxRedirects:0});pass(guest.status()===303&&guest.headers().location.includes('section=login'),'Crawler statistics require admin authentication');
  pass(await page.locator('.crawler-chart rect.ai').evaluateAll(nodes=>nodes.some(node=>Number(node.getAttribute('height'))>0&&getComputedStyle(node).fill==='rgb(53, 120, 184)')),'Trend chart renders colored nonzero bars');
  pass(errors.length===0&&policyErrors.length===0,'Dashboard has no browser errors or blocked CSP content');
  fs.writeFileSync('tests/artifacts/crawlers-ui-results.json',JSON.stringify({passed:checks.length,checks},null,2));console.log('PASS '+checks.length+' crawler HTTP and dashboard checks');
 } finally {if(browser) await browser.close();if(ready) console.log(cli('cleanup'));}
})().catch(error=>{console.error(error);process.exit(1);});
