<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$check=function(bool $ok,string $name) use(&$checks):void { if(!$ok) throw new RuntimeException($name); $checks[]=$name; };
foreach(['/','/wei-xiu-bai-ke/?q=空调','https://example.com/path?q=1'] as $url) { validateNavigationUrl($url); $check(true,'allowed link '.$url); }
foreach(['javascript:alert(1)','data:text/html,hi','//evil.test','/\\evil.test','/%5c/evil.test',"/path\r\n",'https://name:password@example.com','mailto:user@example.com'] as $url) {
    $rejected=false; try {validateNavigationUrl($url);} catch(DomainException $error){$rejected=true;} $check($rejected,'unsafe link rejected');
}
$items=[['id'=>1,'url'=>'/','match_mode'=>'exact'],['id'=>2,'url'=>'/fen-lei-xin-xi/','match_mode'=>'prefix'],['id'=>3,'url'=>'/fen-lei-xin-xi/kong-tiao-wei-xiu/','match_mode'=>'prefix'],['id'=>4,'url'=>'https://other.example/','match_mode'=>'exact']];
$check(activeNavigationId($items,siteUrl().'/')===1,'home active');
$check(activeNavigationId($items,siteUrl().'/fen-lei-xin-xi/kong-tiao-wei-xiu/di-2-ye/')===3,'most specific matching navigation only');
$check(activeNavigationId($items,siteUrl().'/fen-lei-xin-xi-extra/')===null,'prefix respects path boundaries');
$check(activeNavigationId([['id'=>1,'url'=>'/fen-lei-xin-xi/?type=service','match_mode'=>'prefix']],siteUrl().'/fen-lei-xin-xi/?type=request')===null,'query parameters must match');
$check(activeNavigationId([['id'=>1,'url'=>siteUrl().'/selectcity.html','match_mode'=>'exact']],siteUrl().'/selectcity.html')===1,'absolute same-site link active');
db()->beginTransaction();
try {
    query('UPDATE xiu_navigation SET enabled=0');
    $check(navigationItems()===[],'all hidden yields empty navigation in transaction');
    $seo=['canonical'=>siteUrl().'/']; ob_start(); require ROOT.'/views/primary-navigation.php'; $html=ob_get_clean();
    $check(!str_contains($html,'<nav'),'empty navigation has no bar');
} finally { db()->rollBack(); }
$check(count(navigationItems())===5,'transaction rollback retains original five entries');
echo json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
