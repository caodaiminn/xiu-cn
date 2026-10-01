<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$check=function(bool $value,string $label)use(&$checks){ if(!$value)throw new RuntimeException($label); $checks[]=$label; echo "PASS {$label}\n"; };
$cityCount=count(cityList()); $categoryCount=count(categories()); $expected=$cityCount*$categoryCount*5;
$check((int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds')===$expected,'Every active city/category combination contributes exactly five new listings');
$group=row('SELECT COUNT(*) pairs,MIN(amount) minimum,MAX(amount) maximum FROM (SELECT COUNT(*) amount FROM xiu_local_test_seeds GROUP BY city_id,category_id) s');
$check((int)$group['pairs']===$cityCount*$categoryCount && (int)$group['minimum']===5 && (int)$group['maximum']===5,'All city/category groups have five records');
$check(!(int)scalar("SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE l.city_id<>s.city_id OR l.category_id<>s.category_id OR l.is_demo<>1 OR l.status<>'published'"),'Generated data is published as test data in its exact city and category');
$check(!(int)scalar("SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE l.phone NOT REGEXP '^138[0-9]{8}$' OR CHAR_LENGTH(l.contact)<>3 OR RIGHT(l.contact,2)<>'师傅'"),'All contacts use a surname and all phone values use the requested 138 format');
$check(!(int)scalar("SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE l.short_slug IS NULL OR l.short_slug NOT REGEXP '^[a-z]{1,8}[0-9]{4}$' OR CHAR_LENGTH(l.short_slug)>12"),'All new URLs use at most eight spelling letters and four random digits');
$check(!(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id LEFT JOIN xiu_districts d ON d.id=l.district_id WHERE l.district_id IS NOT NULL AND (d.id IS NULL OR d.city_id<>l.city_id OR d.enabled<>1)'),'Every selected district belongs to the listing city');
$check(!(int)scalar("SELECT COUNT(*) FROM xiu_user_verifications v JOIN xiu_users u ON u.id=v.user_id WHERE u.username='local_test_seed'"),'Generated publishers have no fabricated identity certification');
$check(!(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listing_sources p ON p.listing_id=s.listing_id'),'Generated records are never described as sourced listings');
$check(!(int)scalar("SELECT COUNT(*) FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE l.refreshed_at IS NOT NULL OR l.refresh_order<>0 OR l.created_at<>l.updated_at"),'Generation uses actual creation timestamps and does not impersonate member refreshes');

$reportPath=ROOT.'/reports/seeds/nationwide-test-2026-10-01-213927-ded4e4.json';
$seedReport=json_decode(file_get_contents($reportPath),true,512,JSON_THROW_ON_ERROR);
$backup=file_get_contents($seedReport['backup']);
$preserved=0;
foreach(['xiu_listings','xiu_users','xiu_user_verifications','xiu_verification_files','xiu_listing_sources'] as $table) {
    preg_match_all('/INSERT INTO `'.$table.'` \([^\n]+\) VALUES \(\x27([0-9]+)\x27/',$backup,$matches);
    foreach($matches[1] as $id) {
        $record=row('SELECT * FROM `'.$table.'` WHERE id=?',[$id]);
        if(!$record) throw new RuntimeException('Existing record missing from '.$table);
        $columns=implode(',',array_map(fn($name)=>'`'.$name.'`',array_keys($record)));
        $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
        $sql='INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.');';
        if(strpos($backup,$sql)===false) throw new RuntimeException('Existing record changed in '.$table);
        $preserved++;
    }
}
$check($preserved>34,'Original listings, accounts, verification files and sourced data match the recovery backup');

db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
$statement=query('SELECT l.title,l.body,c.name city_name,k.name category_name FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_categories k ON k.id=l.category_id');
$validated=0;
while($item=$statement->fetch()) {
    $category=preg_replace('/[^\p{L}\p{N}]/u','',$item['category_name']);
    if(!preg_match('/^[\p{L}\p{N}]+$/Du',$item['title']) || mb_strpos($item['title'],$item['city_name'])!==0 || mb_strpos($item['title'],$category)===false || mb_strpos($item['body'],$item['category_name'])===false || mb_strpos($item['body'],'【本地测试信息】')!==0) throw new RuntimeException('Generated content differs from its city or category');
    $validated++;
}
$statement->closeCursor(); db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
$check($validated===$expected,'Every generated title and body is related to its assigned city/category and explicitly identifies test content');
$check(sitemapContent('xin-xi')['count']<50 && count(sitemapPageEntries())<1000,'Sitemaps exclude the generated test data instead of producing hundreds of thousands of synthetic SEO pages');

$samples=[];
foreach([['shanghai','home-appliance'],['beijing','home-air'],['guangzhou','computer'],['chengdu','office'],['shenzhen','inverter']] as [$citySlug,$categorySlug]) {
    $city=row('SELECT * FROM xiu_cities WHERE slug=?',[$citySlug]); $category=row('SELECT * FROM xiu_categories WHERE slug=?',[$categorySlug]);
    $items=rows('SELECT l.* FROM xiu_local_test_seeds s JOIN xiu_listings l ON l.id=s.listing_id WHERE s.city_id=? AND s.category_id=? ORDER BY s.slot',[$city['id'],$category['id']]);
    $check(count($items)===5,'Representative category has five generated records: '.$citySlug.'/'.$categorySlug);
    $samples[]=['city'=>$city['name'],'category'=>$category['name'],'url'=>listUrl(['city'=>$city['id'],'category'=>$category['id']]),'items'=>array_map(fn($item)=>['id'=>$item['id'],'url'=>listingUrl($item),'title'=>$item['title'],'phone'=>$item['phone'],'contact'=>$item['contact']],$items)];
}
$cache=adminListingCounts();
$check(is_file(ROOT.'/storage/cache/admin-listing-counts.json') && $cache['total']>275100,'Large-volume admin count caching is enabled');
$first=adminListingResults('',1,20); $last=adminListingResults('',999999,20);
$check(count($first['items'])===20 && $last['page']===$last['pages'] && count($last['items'])>0,'Admin pagination handles the first and last pages at the actual generated data volume');
file_put_contents(ROOT.'/tests/artifacts/nationwide-test-data-results.json',json_encode(['passed'=>count($checks),'checks'=>$checks,'validated_records'=>$validated,'preserved_records'=>$preserved,'samples'=>$samples,'admin_total'=>$cache['total']],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo 'SUCCESS '.count($checks)." data checks.\n";
