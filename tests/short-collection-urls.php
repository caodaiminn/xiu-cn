<?php
/** Read-only route round trips, alias collision checks and sitemap validation. */
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$shortened=[];$fixtures=[];
function shortUrlAssert(bool $ok,string $message): void { global $checks;$checks++;if(!$ok) throw new RuntimeException($message); }
function shortUrlRoute(string $url,string $controller,array $expected): void {
    unset($GLOBALS['route_filters']);
    shortUrlAssert(resolveSeoRoute(rtrim(parse_url($url,PHP_URL_PATH),'/'))===$controller,'Wrong controller '.$url);
    foreach($expected as $key=>$value) shortUrlAssert(($GLOBALS['route_filters'][$key]??($key==='page'?1:0))===$value,'Wrong '.$key.' '.$url);
}
function oldCollectionUrl(array $city,array $category,string $type=''): string {
    return '/'.recordSlug($city).'/'.recordSlug($category).'/'.($type!==''?'lei-xing/'.($type==='service'?'wei-xiu-fu-wu':'qiu-xiu-xu-qiu').'/':'');
}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
foreach(['xiu_categories','xiu_cities','xiu_districts'] as $table) {
    $seen=[];$count=0;
    foreach(rows('SELECT * FROM '.$table) as $record) {
        $scope=$table==='xiu_categories'?['kind'=>$record['kind']]:($table==='xiu_districts'?['city_id'=>$record['city_id']]:[]);
        $slug=collectionSlug($record);$key=json_encode($scope).':'.$slug;
        shortUrlAssert(!isset($seen[$key]),'Alias collision '.$table.' '.$slug);$seen[$key]=$record['id'];
        shortUrlAssert((int)(collectionRecord($table,$slug,$scope)['id']??0)===(int)$record['id'],'Alias shadows original spelling '.$slug);
        shortUrlAssert((int)(collectionRecord($table,recordSlug($record),$scope)['id']??0)===(int)$record['id'],'Original spelling lost');
        if($slug!==recordSlug($record)) {
            $count++;
            shortUrlAssert(strlen($slug)<strlen(recordSlug($record)),'Alias longer than original');
            shortUrlAssert(collectionSlug(array_replace($record,['name'=>'Renamed']))===$slug,'Rename changed address');
            shortUrlAssert(collectionRecord($table,'wronginitials-'.$record['id'],$scope)===null,'Unchecked record ID alias');
        }
    }
    $shortened[$table]=$count;
}
$synthetic=['id'=>900001,'name'=>'Ignored','seo_slug'=>'gong-gong-an-quan-she-bei-wei-xiu'];
shortUrlAssert(collectionSlug($synthetic)==='ggaqsbwx-900001','Initials spelling');
shortUrlAssert(collectionSlug(array_replace($synthetic,['id'=>900002]))!==collectionSlug($synthetic),'Same initials ambiguous');
shortUrlAssert(collectionSlug(['id'=>1,'seo_slug'=>str_repeat('a',24)])===str_repeat('a',24),'24 character boundary');
shortUrlAssert(collectionSlug(['id'=>1,'seo_slug'=>'3d-da-yin-ji-wei-xiu-fu-wu'])==='dyjwxfw-1','Numeric leading token');
foreach(cityList() as $city) {
    shortUrlRoute(cityUrl($city),'/services',['city'=>(int)$city['id']]);
    shortUrlRoute('/cheng-shi-fu-wu/'.recordSlug($city).'/', '/services',['city'=>(int)$city['id']]);
}
$city=row("SELECT * FROM xiu_cities WHERE seo_slug='qin-zhou' AND enabled=1");
shortUrlAssert($city!==null,'Example city missing');
$example=row("SELECT * FROM xiu_categories WHERE seo_slug='gong-gong-an-quan-she-bei-wei-xiu' AND kind='service'");
shortUrlAssert($example!==null,'Example category missing');
foreach(['service','knowledge','encyclopedia'] as $kind) foreach(categories($kind) as $category) {
    $filters=['city'=>(int)$city['id'],'category'=>(int)$category['id']];
    if($kind==='service') {
        foreach(['','service','request'] as $type) {
            $f=array_replace($filters,['type'=>$type]);$expected=['city'=>$f['city'],'category'=>$f['category']];if($type!=='') $expected['type']=$type;
            shortUrlRoute(listUrl($f,2),'/services',$expected+['page'=>2]);
            shortUrlRoute(oldCollectionUrl($city,$category,$type). 'di-2-ye/','/services',$expected+['page'=>2]);
        }
    } else {
        $controller=$kind==='knowledge'?'/baixiuquan':'/baike';
        shortUrlRoute(articleCollectionUrl($kind,$filters,2),$controller,['category'=>$filters['category'],'page'=>2]);
        shortUrlRoute(publicPath($kind).recordSlug($category).'/di-2-ye/',$controller,['category'=>$filters['category'],'page'=>2]);
    }
}
foreach(rows('SELECT * FROM xiu_districts WHERE enabled=1') as $district) {
    $districtCity=seoRecord('xiu_cities',(int)$district['city_id']);if(!$districtCity['enabled']) continue;
    $f=['city'=>(int)$districtCity['id'],'district'=>(int)$district['id'],'category'=>(int)$example['id'],'type'=>'request'];
    shortUrlRoute(listUrl($f,2),'/services',$f+['page'=>2]);
    $old='/'.recordSlug($districtCity).'/'.recordSlug($example).'/qu-yu/'.recordSlug($district).'/lei-xing/qiu-xiu-xu-qiu/di-2-ye/';
    shortUrlRoute($old,'/services',$f+['page'=>2]);
    shortUrlAssert(collectionRecord('xiu_districts',collectionSlug($district),['city_id'=>999999999])===null,'District escaped city scope');
}
$mapCount=repairCategoryLandingSitemap()['count'];$seen=[];$parts=(int)ceil($mapCount/1000);
for($part=1;$part<=$parts;$part++) foreach(repairCategoryLandingSitemap($part) as $url=>$modified) {
    shortUrlAssert(!isset($seen[$url]),'Duplicate sitemap URL');$seen[$url]=true;
    shortUrlAssert(!str_contains($url,'/gong-gong-an-quan-she-bei-wei-xiu/'),'Old long slug in sitemap');
}
shortUrlAssert(count($seen)===$mapCount,'Sitemap lost records');
$long=array_values(array_filter(categories(),fn($category)=>collectionSlug($category)!==recordSlug($category)));
foreach(array_merge([$example],array_slice($long,0,7)) as $category) foreach(['','service','request'] as $type) {
    $fixtures[]=['old'=>oldCollectionUrl($city,$category,$type),'new'=>listUrl(['city'=>$city['id'],'category'=>$category['id'],'type'=>$type])];
}
foreach(array_slice(array_values(array_filter(cityList(),fn($item)=>collectionSlug($item)!==recordSlug($item))),0,3) as $longCity) {
    $fixtures[]=['old'=>'/'.recordSlug($longCity).'/','new'=>cityUrl($longCity)];
    $fixtures[]=['old'=>oldCollectionUrl($longCity,$example,'service'),'new'=>listUrl(['city'=>$longCity['id'],'category'=>$example['id'],'type'=>'service'])];
}
$fixtures[]=['old'=>'/fen-lei-xin-xi/di-2-ye/','new'=>listUrl([],2)];
$fixtures[]=['old'=>'/fen-lei-xin-xi/lei-xing/wei-xiu-fu-wu/di-2-ye/','new'=>listUrl(['type'=>'service'],2)];
$fixtures[]=['old'=>'/guang-zhou/kong-tiao-qing-xi/qu-yu/tian-he/lei-xing/wei-xiu-fu-wu/','new'=>'/guang-zhou/kong-tiao-qing-xi/qy/tian-he/lx/wxfw/'];
$result=['passed'=>true,'checks'=>$checks,'shortened'=>$shortened,'sitemap_urls'=>$mapCount,'example_old'=>oldCollectionUrl($city,$example,'service'),'example_new'=>listUrl(['city'=>$city['id'],'category'=>$example['id'],'type'=>'service']),'http'=>$fixtures];
if(isset($argv[1])) file_put_contents($argv[1],json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
unset($result['http']);echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
