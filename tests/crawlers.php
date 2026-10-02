<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$pass=function(bool $value,string $label) use(&$checks): void { if(!$value) throw new RuntimeException($label); $checks[]=$label; };
$cases=['Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'=>'baidu','Googlebot-Image/1.0'=>'google','bingbot/2.0'=>'bing','Sogou web spider/4.0'=>'sogou','360Spider'=>'360','GPTBot/1.4'=>'gptbot','oai-searchbot/1.0'=>'oai-search','ChatGPT-User/1.0'=>'chatgpt-user','ClaudeBot/1.0'=>'claude','Claude-SearchBot/1.0'=>'claude-search','Claude-User/1.0'=>'claude-user','PerplexityBot/1.0'=>'perplexity','Perplexity-User/1.0'=>'perplexity-user','Bytespider'=>'bytespider','CCBot/2.0'=>'ccbot','meta-externalagent/1.1'=>'meta-agent','meta-externalfetcher/1.0'=>'meta-fetcher','Amazonbot/0.1'=>'amazon','SomeNewCrawler/1.0'=>'other','Mozilla/5.0 Chrome/130 Safari/537.36'=>null,'MyGooglebotFake/1.0'=>null,'Mozilla/5.0 +https://example.com/Googlebot'=>null,'Google-Extended'=>null];
foreach($cases as $agent=>$expected) $pass(identifyCrawler($agent)===$expected,'UA classification: '.$agent);
foreach(['/','/robots.txt','/sitemap.xml','/wei-xiu-xin-xi/missing.html','/missing-page'] as $path) $pass(crawlerPublicRequest('GET',$path),'Public capture: '.$path);
foreach(['/dgadmin/index.php?section=dashboard','/api/ai-description','/yong-hu-zhong-xin/shou-ji-hao-ma/','/user','/fa-bu-xin-xi/','/zhu-ce/','/%64gadmin/index.php'] as $path) $pass(!crawlerPublicRequest('GET',$path),'Private request excluded: '.$path);
$pass(!crawlerPublicRequest('POST','/') && !crawlerPublicRequest('DELETE','/') && crawlerPublicRequest('HEAD','/'),'Only public GET and HEAD are counted');
$now=new DateTimeImmutable('2026-10-02 10:00:00',new DateTimeZone('Asia/Shanghai'));
foreach(['today'=>['2026-10-02 00:00:00','2026-10-03 00:00:00'],'yesterday'=>['2026-10-01 00:00:00','2026-10-02 00:00:00'],'week'=>['2026-09-28 00:00:00','2026-10-05 00:00:00'],'month'=>['2026-10-01 00:00:00','2026-11-01 00:00:00']] as $key=>$expected) { $range=crawlerPeriod($key,$now); $pass([$range['start'],$range['end']]===$expected,'Exact calendar bounds: '.$key); }
$pass(crawlerPeriod('invalid',$now)['key']==='today','Invalid period defaults to today');
$pass(crawlerPeriod('month',new DateTimeImmutable('2026-01-31 18:00:00 UTC'))['start']==='2026-02-01 00:00:00','Timezone conversion across month boundary');
$pass(crawlerPeriod('week',new DateTimeImmutable('2026-10-05 00:00:00 +08:00'))['start']==='2026-10-05 00:00:00','Monday begins a new week');
$pass(crawlerPeriod('yesterday',new DateTimeImmutable('2027-01-01 00:01:00 +08:00'))['start']==='2026-12-31 00:00:00','Yesterday crosses year boundary');
$pass(crawlerCleanText("<script>\n\x00x",100)==='<script>x','Controls removed; output remains escaped by view');
file_put_contents(ROOT.'/tests/artifacts/crawlers-model-results.json',json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'PASS '.count($checks).' crawler classification, privacy and calendar checks'.PHP_EOL;
