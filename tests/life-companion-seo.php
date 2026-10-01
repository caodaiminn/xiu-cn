<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/scripts/local-test-topics.php';
$checks=[];
$check=function(bool $condition,string $label) use (&$checks): void {
    if(!$condition) throw new RuntimeException($label);
    $checks[]=$label; echo 'PASS '.$label.PHP_EOL;
};
$names=['陪护公司','陪练车','陪诊师','陪驾公司','陪护','陪餐','陪爬山'];
$parent=row("SELECT * FROM xiu_categories WHERE kind='service' AND slug='life' AND parent_id IS NULL");
$categories=categories(); $catalog=array_column($categories,null,'id');
$before=json_decode(file_get_contents(ROOT.'/backup/2026-10-01-life-companion-seo/categories-before.json'),true,512,JSON_THROW_ON_ERROR);
foreach($before as $record) $check(row('SELECT * FROM xiu_categories WHERE id=?',[$record['id']])===$record,'Existing category preserved: '.$record['id']);
$city=row("SELECT * FROM xiu_cities WHERE seo_slug='chen-zhou' AND enabled=1");
if(!$city) throw new RuntimeException('Chenzhou is missing.');
$new=[];
foreach($names as $name) {
    $matches=rows("SELECT * FROM xiu_categories WHERE parent_id=? AND kind='service' AND name=?",[$parent['id'],$name]);
    $check(count($matches)===1,'Exactly one requested life-service child: '.$name);
    $record=$matches[0]; $id=(int)$record['id'];
    $check(isset($catalog[$id]) && $catalog[$id]['depth']===2 && (int)$record['show_in_filter']===1,'Active second-level navigation and publishing category: '.$name);
    $check($record['seo_slug']===pinyinSlug($name),'Fully spelled category URL: '.$name);
    $groups=rows('SELECT s.city_id,COUNT(*) amount,SUM(l.status=\'published\' AND l.is_demo=1 AND l.category_id=s.category_id AND l.city_id=s.city_id) valid FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id JOIN xiu_cities c ON c.id=s.city_id AND c.enabled=1 WHERE s.category_id=? GROUP BY s.city_id',[$id]);
    $check(count($groups)===count(cityList()) && count(array_filter($groups,fn($group)=>(int)$group['amount']===5 && (int)$group['valid']===5))===count($groups),'All 393 cities have five published test records: '.$name);
    $topics=localTestTopics($catalog[$id],$catalog);
    $mismatch=(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE s.category_id=? AND (l.body NOT LIKE ? OR l.body NOT LIKE ? OR l.body NOT LIKE ? OR l.body NOT LIKE ? OR l.body NOT LIKE ?)',[$id,'%本地测试信息%','%所属分类：'.$catalog[$id]['label'].'。%','%服务主题：%','%服务范围与安排%','%服务完成后核对约定项目%']);
    foreach($topics as $slot=>$topic) $mismatch+=(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE s.category_id=? AND s.slot=? AND l.body NOT LIKE ?',[$id,$slot+1,'%服务主题：'.$topic.'。%']);
    $check($mismatch===0,'Companion-service topics match their own category: '.$name);
    $new[]=['id'=>$id,'name'=>$name,'url'=>listUrl(['city'=>$city['id'],'category'=>$id])];
}
$check(count($categories)===147,'147 active service categories');
$check((int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds')===288855,'288855 generated records after incremental fill');
$value=fn($key,$default='')=>$default; $item=null; $_SERVER['REQUEST_METHOD']='GET';
ob_start(); require ROOT.'/views/publish-category-picker.php'; $publish=ob_get_clean();
file_put_contents(ROOT.'/tests/artifacts/life-companion-publish-options.html',$publish);
$pages=[];
foreach($categories as $category) $pages[]=['id'=>(int)$category['id'],'name'=>$category['name'],'url'=>listUrl(['city'=>$city['id'],'category'=>$category['id']]),'title'=>$city['name'].$category['name'].'|'.$city['name'].$category['name'].'电话 - '.siteName()];
$report=['passed'=>count($checks),'checks'=>$checks,'city'=>$city['name'],'parent_id'=>(int)$parent['id'],'brand'=>siteName(),'new_categories'=>$new,'pages'=>$pages,'listing_total'=>(int)scalar('SELECT COUNT(*) FROM xiu_listings')];
file_put_contents(ROOT.'/tests/artifacts/life-companion-seo-data.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'SUCCESS '.count($checks).' data checks.'.PHP_EOL;
