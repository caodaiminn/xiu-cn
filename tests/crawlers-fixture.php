<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$path=ROOT.'/tests/artifacts/crawlers-fixture.json'; $mode=$argv[1]??'';
if($mode==='setup') {
    if(is_file($path)) throw new RuntimeException('Prior crawler fixture requires cleanup.');
    $fixture=['token'=>'crawlerqa-'.bin2hex(random_bytes(8)),'session'=>'crawlerqa-admin-'.bin2hex(random_bytes(16)),'baseline'=>rows('SELECT *,HEX(path_hash) path_hash FROM xiu_crawler_visits ORDER BY id'),'started_at'=>scalar('SELECT started_at FROM xiu_crawler_tracking WHERE id=1')];
    $admin=(int)scalar('SELECT id FROM xiu_admins ORDER BY id LIMIT 1'); if(!$admin) throw new RuntimeException('Admin missing.');
    if(file_put_contents($path,json_encode($fixture,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Fixture journal failed.');
    session_save_path(ROOT.'/storage/sessions');session_name('xiu_session');session_id($fixture['session']);session_start();$_SESSION['admin_id']=$admin;session_write_close();
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Shanghai'));
    $entries=[['baidu',$today,200],['baidu',$today,301],['bing',$today,404],['gptbot',$today,200],['oai-search',$today,200],['claude',$today,503],['sogou',$today->modify('-1 day'),200],['chatgpt-user',$today->modify('-1 day'),200],['perplexity',$today->modify('first day of this month'),200],['google',$today->modify('-'.((int)$today->format('N')-1).' days'),200],['other',$today->modify('-45 days'),200]];
    foreach($entries as $i=>[$bot,$at,$status]) saveCrawlerVisit($bot,'/'.$fixture['token'].'/'.($i<2?'same':$i),'GET',$status,'127.0.0.1','Simulated crawler check '.$fixture['token'],$at->setTime(0,0,$i+1)->format('Y-m-d H:i:s'));
    for($i=0;$i<22;$i++) saveCrawlerVisit('gptbot','/'.$fixture['token'].'/page-'.$i,'HEAD',200,'127.0.0.1','Simulated crawler check '.$fixture['token'],$today->setTime(0,1,$i)->format('Y-m-d H:i:s'));
    echo json_encode(['ready'=>true,'token'=>$fixture['token']]);exit;
}
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^crawlerqa-[a-f0-9]{16}$/',$fixture['token']) || !preg_match('/^crawlerqa-admin-[a-f0-9]{32}$/',$fixture['session'])) throw new RuntimeException('Invalid fixture marker.');
if($mode==='snapshot') {
    $result=crawlerDashboard($argv[2]??'today',$argv[3]??'',(int)($argv[4]??1));
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
if($mode==='visits') { echo json_encode(rows('SELECT *,HEX(path_hash) path_hash FROM xiu_crawler_visits WHERE user_agent LIKE ? ORDER BY id',['%'.$fixture['token'].'%']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
if($mode!=='cleanup') throw new RuntimeException('Unknown fixture mode.');
$ids=array_column(rows('SELECT id FROM xiu_crawler_visits WHERE user_agent LIKE ?',['%'.$fixture['token'].'%']),'id');
if($ids) { echo 'Cleanup crawler visit IDs: '.implode(',',$ids).PHP_EOL; query('DELETE FROM xiu_crawler_visits WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND user_agent LIKE ?',[...$ids,'%'.$fixture['token'].'%']); }
$current=array_column(rows('SELECT *,HEX(path_hash) path_hash FROM xiu_crawler_visits ORDER BY id'),null,'id');
foreach($fixture['baseline'] as $visit) if(($current[$visit['id']]??null)!==$visit) throw new RuntimeException('An original crawler visit changed.');
if(scalar('SELECT started_at FROM xiu_crawler_tracking WHERE id=1')!==$fixture['started_at']) throw new RuntimeException('Tracking start changed.');
$session=ROOT.'/storage/sessions/sess_'.$fixture['session']; if(is_file($session)) { echo 'Cleanup file: '.$session.PHP_EOL; unlink($session); }
echo 'Cleanup file: '.$path.PHP_EOL; unlink($path);
echo 'Original crawler visits and tracking start verified unchanged.'.PHP_EOL;
