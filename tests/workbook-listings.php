<?php
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function workbookAssert(bool $ok,string $message): void {global $checks;$checks++;if(!$ok) throw new RuntimeException($message);}
$batch=json_decode(file_get_contents($argv[1]??ROOT.'/reports/imports/2026-10-05-workbook/batch.json'),true,512,JSON_THROW_ON_ERROR);
$sources=rows('SELECT l.*,w.source_row,w.source_title,w.source_category,w.fingerprint FROM xiu_workbook_imports w JOIN xiu_listings l ON l.id=w.listing_id WHERE w.source_hash=?',[$batch['source_hash']]);
workbookAssert(count($sources)===count($batch['records']),'Import coverage differs');
$byRow=array_column($sources,null,'source_row');$counts=[];
foreach($batch['records'] as $item) {
    $stored=$byRow[$item['source_row']]??null;
    workbookAssert($stored!==null,'Missing source row');
    workbookAssert($stored['title']===$item['optimized_title'] && $stored['phone']===$item['phone'] && $stored['source_title']===$item['title'],'Source/title/phone mismatch');
    workbookAssert(!$stored['is_demo'] && $stored['status']==='published' && mb_strlen($stored['body'])>300,'Invalid service detail');
    $scope=listingWithServiceScope($stored);workbookAssert($scope['city_name']==='全国' && $scope['service_city_id']===0,'Incorrect area claim');
    $counts[(int)$stored['category_id']]=($counts[(int)$stored['category_id']]??0)+1;
}
$cities=cityList();$samples=[$cities[0],$cities[intdiv(count($cities),2)],$cities[count($cities)-1]];
foreach($counts as $category=>$count) foreach($samples as $city) {
    $result=listingSearch(['category'=>$category,'city'=>$city['id']],1,100);
    workbookAssert($result['total']>=$count,'Nationwide category missing in city');
}
$category=234;$reference=listingSearch(['category'=>$category,'city'=>$cities[0]['id']],1,100);$expected=array_column($reference['items'],'id');
foreach($cities as $city) {
    $result=listingSearch(['category'=>$category,'city'=>$city['id']],1,100);
    workbookAssert(array_column($result['items'],'id')===$expected,'City contents differ for nationwide services');
}
$district=scalar('SELECT id FROM xiu_districts WHERE enabled=1 AND city_id=? LIMIT 1',[$cities[0]['id']]);
if($district) { $result=listingSearch(['category'=>$category,'city'=>$cities[0]['id'],'district'=>$district]);workbookAssert(!array_intersect(array_column($result['items'],'id'),nationwideListingIds()),'Invented district service area'); }
$first=$sources[0];
db()->beginTransaction();
try {
    query("UPDATE xiu_listings SET status='offline' WHERE id=?",[$first['id']]);
    workbookAssert(!visibleListing(row('SELECT * FROM xiu_listings WHERE id=?',[$first['id']])),'Offline service still visible');
    query("UPDATE xiu_listings SET status='published' WHERE id=?",[$first['id']]);
    query('UPDATE xiu_cities SET enabled=0 WHERE id=?',[$first['city_id']]);
    workbookAssert(visibleListing(row('SELECT * FROM xiu_listings WHERE id=?',[$first['id']])),'Nationwide service depends on internal city anchor');
    query('UPDATE xiu_users SET enabled=0 WHERE id=?',[$first['user_id']]);
    workbookAssert(!visibleListing(row('SELECT * FROM xiu_listings WHERE id=?',[$first['id']])),'Disabled owner visible');
    $result=listingSearch(['category'=>$category,'city'=>$samples[1]['id']],1,100);
    workbookAssert(!array_intersect(array_column($result['items'],'id'),nationwideListingIds()),'Disabled import owner visible in collections');
} finally {db()->rollBack();}
$sitemapIds=array_values(array_unique(array_merge(repairCategoryLandingIds(),nationwideListingCategoryIds())));
workbookAssert(repairCategoryLandingSitemap()['count']===count($cities)*count($sitemapIds),'City sitemap coverage');
$details=sitemapContent('xin-xi');workbookAssert($details['count']>=count($sources),'Service details omitted from sitemap');
$fixture=['categories'=>[],'details'=>[],'sitemap_count'=>repairCategoryLandingSitemap()['count'],'sitemap_parts'=>(int)ceil(repairCategoryLandingSitemap()['count']/1000)];
foreach([2,234,28,33] as $id) $fixture['categories'][]=['id'=>$id,'path'=>listUrl(['city'=>$cities[0]['id'],'category'=>$id])];
foreach(array_slice(array_filter(array_keys($counts),fn($id)=>$id>247),0,3) as $id) $fixture['categories'][]=['id'=>$id,'path'=>listUrl(['city'=>$cities[0]['id'],'category'=>$id])];
foreach(array_slice($sources,0,5) as $item) $fixture['details'][]=listingUrl($item);
file_put_contents(ROOT.'/reports/imports/2026-10-05-workbook/http-fixture.json',json_encode($fixture,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['passed'=>true,'checks'=>$checks,'imported'=>count($sources),'target_categories'=>count($counts),'cities'=>count($cities),'city_category_sitemap_urls'=>$fixture['sitemap_count']]),PHP_EOL;
