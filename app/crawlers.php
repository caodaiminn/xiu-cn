<?php
declare(strict_types=1);

/** UA identification only: it does not authenticate the crawler's network owner. */
function crawlerCatalog(): array {
    return [
        'baidu'=>['name'=>'百度','group'=>'search','tokens'=>['Baiduspider'],'purpose'=>'搜索抓取'],
        'google'=>['name'=>'谷歌','group'=>'search','tokens'=>['Googlebot','GoogleOther','Google-InspectionTool'],'purpose'=>'搜索及通用抓取'],
        'bing'=>['name'=>'必应','group'=>'search','tokens'=>['bingbot','msnbot','BingPreview'],'purpose'=>'搜索抓取'],
        'sogou'=>['name'=>'搜狗','group'=>'search','tokens'=>['Sogou web spider','Sogou inst spider','Sogou spider2','Sogou News Spider','Sogou Orion spider','Sogou spider','SogouSpider'],'purpose'=>'搜索抓取'],
        '360'=>['name'=>'360搜索','group'=>'search','tokens'=>['360Spider','HaosouSpider'],'purpose'=>'搜索抓取'],
        'yandex'=>['name'=>'Yandex','group'=>'search','tokens'=>['YandexBot','YandexImages'],'purpose'=>'搜索抓取'],
        'duckduckgo'=>['name'=>'DuckDuckGo','group'=>'search','tokens'=>['DuckDuckBot'],'purpose'=>'搜索抓取'],
        'yahoo'=>['name'=>'Yahoo','group'=>'search','tokens'=>['Slurp'],'purpose'=>'搜索抓取'],
        'apple'=>['name'=>'Apple','group'=>'search','tokens'=>['Applebot'],'purpose'=>'搜索抓取'],
        'gptbot'=>['name'=>'OpenAI · GPTBot','group'=>'ai','tokens'=>['GPTBot'],'purpose'=>'模型训练抓取'],
        'oai-search'=>['name'=>'OpenAI · OAI-SearchBot','group'=>'ai','tokens'=>['OAI-SearchBot'],'purpose'=>'AI搜索抓取'],
        'chatgpt-user'=>['name'=>'OpenAI · ChatGPT-User','group'=>'ai','tokens'=>['ChatGPT-User'],'purpose'=>'用户触发访问'],
        'claude'=>['name'=>'Claude · ClaudeBot','group'=>'ai','tokens'=>['ClaudeBot','anthropic-ai'],'purpose'=>'模型训练抓取'],
        'claude-search'=>['name'=>'Claude · SearchBot','group'=>'ai','tokens'=>['Claude-SearchBot'],'purpose'=>'AI搜索抓取'],
        'claude-user'=>['name'=>'Claude · User','group'=>'ai','tokens'=>['Claude-User'],'purpose'=>'用户触发访问'],
        'perplexity'=>['name'=>'Perplexity · Bot','group'=>'ai','tokens'=>['PerplexityBot'],'purpose'=>'AI搜索抓取'],
        'perplexity-user'=>['name'=>'Perplexity · User','group'=>'ai','tokens'=>['Perplexity-User'],'purpose'=>'用户触发访问'],
        'bytespider'=>['name'=>'字节跳动 · Bytespider','group'=>'ai','tokens'=>['Bytespider'],'purpose'=>'内容抓取'],
        'amazon'=>['name'=>'Amazon · Amazonbot','group'=>'ai','tokens'=>['Amazonbot'],'purpose'=>'内容抓取'],
        'meta-agent'=>['name'=>'Meta · ExternalAgent','group'=>'ai','tokens'=>['meta-externalagent'],'purpose'=>'模型训练抓取'],
        'meta-fetcher'=>['name'=>'Meta · ExternalFetcher','group'=>'ai','tokens'=>['meta-externalfetcher'],'purpose'=>'用户触发访问'],
        'ccbot'=>['name'=>'Common Crawl · CCBot','group'=>'ai','tokens'=>['CCBot'],'purpose'=>'公共语料抓取'],
        'other'=>['name'=>'其他爬虫','group'=>'other','tokens'=>[],'purpose'=>'未分类的爬虫标识'],
    ];
}
function identifyCrawler(string $agent): ?string {
    $agent=substr($agent,0,2048);
    // Avoid matching a vendor token that occurs only in the agent's documentation URL.
    $agent=preg_replace('~https?://[^\s;)]+~i','',$agent);
    foreach(crawlerCatalog() as $key=>$crawler) foreach($crawler['tokens'] as $token) {
        if(preg_match('~(?<![a-z0-9_-])'.preg_quote($token,'~').'(?![a-z0-9_])~i',$agent)) return (string)$key;
    }
    return preg_match('~\b[a-z0-9_-]*(?:bot|spider|crawler)(?:[/-][a-z0-9_.-]+)?\b~i',$agent)?'other':null;
}
function crawlerPublicRequest(string $method,string $uri): bool {
    if(!in_array($method,['GET','HEAD'],true)) return false;
    $path=parse_url($uri,PHP_URL_PATH);
    if(!is_string($path) || $path==='' || $path[0]!=='/') return false;
    $path=rawurldecode($path);
    return !preg_match('~^/(?:dgadmin|api|yong-hu-zhong-xin|user|login|register|publish|logout|change-city|deng-lu|zhu-ce|fa-bu-xin-xi|tui-chu)(?:/|$)~i',$path);
}
function crawlerCleanText(string $value,int $length): string {
    return mb_substr(preg_replace('/[\x00-\x1f\x7f]/','',mb_scrub($value,'UTF-8')),0,$length,'UTF-8');
}
function saveCrawlerVisit(string $bot,string $uri,string $method,int $status,string $ip,string $agent,string $occurredAt): void {
    if(!isset(crawlerCatalog()[$bot]) || !crawlerPublicRequest($method,$uri)) throw new InvalidArgumentException('Invalid crawler visit.');
    $path=crawlerCleanText((string)parse_url($uri,PHP_URL_PATH),1024);
    $ip=filter_var($ip,FILTER_VALIDATE_IP)?$ip:'';
    query('INSERT INTO xiu_crawler_visits (bot_key,occurred_at,path,path_hash,method,status_code,ip,user_agent) VALUES (?,?,?,UNHEX(?),?,?,?,?)',[$bot,$occurredAt,$path,hash('sha256',$path),$method,$status,$ip,crawlerCleanText($agent,1024)]);
}
function registerCrawlerTracking(): void {
    if(PHP_SAPI==='cli') return;
    $method=$_SERVER['REQUEST_METHOD']??''; $uri=$_SERVER['REQUEST_URI']??'/'; $agent=$_SERVER['HTTP_USER_AGENT']??'';
    if(!crawlerPublicRequest($method,$uri) || !($bot=identifyCrawler($agent))) return;
    $ip=locationClientIp(); $time=date('Y-m-d H:i:s');
    register_shutdown_function(static function() use($bot,$uri,$method,$agent,$ip,$time): void {
        try {
            $status=http_response_code()?:200;
            $fatal=error_get_last();
            if($fatal && in_array($fatal['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true) && $status<400) $status=500;
            saveCrawlerVisit($bot,$uri,$method,$status,$ip,$agent,$time);
        } catch(Throwable $error) { error_log('Crawler visit recording unavailable: '.$error->getCode()); }
    });
}
function crawlerPeriod(string $period,?DateTimeImmutable $now=null): array {
    $now=($now??new DateTimeImmutable())->setTimezone(new DateTimeZone('Asia/Shanghai'));
    $today=$now->setTime(0,0);
    if(!in_array($period,['today','yesterday','week','month'],true)) $period='today';
    if($period==='yesterday') { $start=$today->modify('-1 day'); $end=$today; }
    elseif($period==='week') { $start=$today->modify('-'.((int)$today->format('N')-1).' days'); $end=$start->modify('+7 days'); }
    elseif($period==='month') { $start=$today->modify('first day of this month'); $end=$start->modify('+1 month'); }
    else { $start=$today; $end=$today->modify('+1 day'); }
    return ['key'=>$period,'start'=>$start->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s'),'label'=>['today'=>'今天','yesterday'=>'昨天','week'=>'本周','month'=>'本月'][$period],'last_day'=>min($today,$end->modify('-1 day'))->format('Y-m-d'),'hourly'=>in_array($period,['today','yesterday'],true)];
}
function crawlerDashboard(string $period,string $bot='',int $page=1): array {
    $range=crawlerPeriod($period); $catalog=crawlerCatalog();
    if(!isset($catalog[$bot])) $bot='';
    $result=['range'=>$range,'bot'=>$bot,'page'=>1,'pages'=>1,'total'=>0,'items'=>[],'catalog'=>$catalog,'providers'=>[],'totals'=>['visits'=>0,'search'=>0,'ai'=>0,'other'=>0,'errors'=>0],'trend'=>[],'started_at'=>null,'available'=>false];
    try {
        $result['started_at']=scalar('SELECT started_at FROM xiu_crawler_tracking WHERE id=1')?:null;
        $params=[$range['start'],$range['end']];
        $where=' WHERE occurred_at>=? AND occurred_at<?';
        $counts=rows('SELECT bot_key,COUNT(*) visits,COUNT(DISTINCT path_hash) urls,SUM(status_code BETWEEN 200 AND 299) successful,SUM(status_code BETWEEN 300 AND 399) redirects,SUM(status_code>=400) errors,MAX(occurred_at) last_visit FROM xiu_crawler_visits'.$where.' GROUP BY bot_key',$params);
        foreach($catalog as $key=>$provider) $result['providers'][(string)$key]=$provider+['key'=>(string)$key,'visits'=>0,'urls'=>0,'successful'=>0,'redirects'=>0,'errors'=>0,'last_visit'=>null];
        foreach($counts as $count) {
            if(!isset($catalog[$count['bot_key']])) continue;
            $result['providers'][$count['bot_key']]=array_merge($result['providers'][$count['bot_key']],$count);
            $result['totals']['visits']+=(int)$count['visits'];
            $result['totals'][$catalog[$count['bot_key']]['group']]+=(int)$count['visits'];
            $result['totals']['errors']+=(int)$count['errors'];
        }
        $unit=$range['hourly']?'%H':'%Y-%m-%d';
        $trend=rows("SELECT DATE_FORMAT(occurred_at,'$unit') bucket,bot_key,COUNT(*) visits FROM xiu_crawler_visits".$where.' GROUP BY bucket,bot_key',$params);
        if($range['hourly']) for($i=0;$i<24;$i++) $result['trend'][sprintf('%02d',$i)]=['label'=>sprintf('%02d',$i),'search'=>0,'ai'=>0,'other'=>0];
        else for($day=new DateTimeImmutable($range['start']);$day->format('Y-m-d')<=$range['last_day'];$day=$day->modify('+1 day')) $result['trend'][$day->format('Y-m-d')]=['label'=>$day->format('m-d'),'search'=>0,'ai'=>0,'other'=>0];
        foreach($trend as $point) if(isset($result['trend'][$point['bucket']],$catalog[$point['bot_key']])) $result['trend'][$point['bucket']][$catalog[$point['bot_key']]['group']]+=(int)$point['visits'];
        $detailWhere=$where;
        if($bot!=='') { $detailWhere.=' AND bot_key=?'; $params[]=$bot; }
        $result['total']=(int)scalar('SELECT COUNT(*) FROM xiu_crawler_visits'.$detailWhere,$params);
        $result['pages']=max(1,(int)ceil($result['total']/20)); $result['page']=max(1,min($page,$result['pages']));
        $result['items']=rows('SELECT id,bot_key,occurred_at,path,method,status_code,ip,user_agent FROM xiu_crawler_visits'.$detailWhere.' ORDER BY occurred_at DESC,id DESC LIMIT 20 OFFSET '.(($result['page']-1)*20),$params);
        $result['available']=true;
    } catch(PDOException $error) { error_log('Crawler dashboard unavailable: '.$error->getCode()); }
    return $result;
}
