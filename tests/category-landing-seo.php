<?php
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$lengths=[];
function landingAssert(bool $ok,string $message): void {global $checks;$checks++;if(!$ok) throw new RuntimeException($message);}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
$cities=cityList();$categoryIds=repairCategoryLandingIds();
foreach($categoryIds as $id) {
    $filters=['category'=>$id,'city'=>$cities[0]['id'],'district'=>0,'type'=>'','q'=>''];
    $content=repairCategoryLandingContent($filters,$cities[0],0);
    landingAssert($content!==null,'Missing category copy '.$id);
    $text=$content['intro'].$content['status'];
    foreach($content['sections'] as $section) $text.=implode('',$section['paragraphs']).implode('',$section['items']);
    $lengths[]=mb_strlen($text);
    landingAssert(mb_strlen($text)>500,'Empty or thin copy '.$id);
    landingAssert(count($content['sections'])===4,'Missing sections');
    landingAssert(!preg_match('/1[3-9][0-9]{9}/',$text),'Fabricated phone');
    $seo=pageSeo('listings',['filters'=>$filters,'city'=>$cities[0],'result'=>['total'=>0,'page'=>1],'categoryContent'=>$content]);
    landingAssert(str_starts_with($seo['robots'],'index'),'Content page blocked');
    landingAssert(str_contains($seo['description'],$cities[0]['name']),'Wrong local description');
}
foreach([['q'=>'keyword'],['type'=>'service'],['district'=>1],['category'=>0]] as $override) {
    $filters=array_replace(['category'=>234,'city'=>$cities[0]['id'],'district'=>0,'type'=>'','q'=>''],$override);
    landingAssert(!repairCategoryLandingEligible($filters),'Filter copy indexed');
    $seo=pageSeo('listings',['filters'=>$filters,'city'=>$cities[0],'result'=>['total'=>0,'page'=>1],'categoryContent'=>null]);
    landingAssert($seo['robots']==='noindex, follow','Empty/filter robots');
}
landingAssert(!repairCategoryLandingEligible(['category'=>234],2),'Duplicate pagination copy');
$expected=count(array_unique(array_merge($categoryIds,nationwideListingCategoryIds())))*count($cities);
landingAssert(repairCategoryLandingSitemap()['count']===$expected,'Sitemap count');
$seen=[];$parts=(int)ceil($expected/1000);
for($part=1;$part<=$parts;$part++) {
    $entries=repairCategoryLandingSitemap($part);
    landingAssert(count($entries)===min(1000,$expected-($part-1)*1000),'Wrong part length');
    foreach($entries as $url=>$modified) {landingAssert(!isset($seen[$url]),'Duplicate URL');$seen[$url]=true;}
}
landingAssert(count($seen)===$expected,'Missing city/category URLs');
landingAssert(repairCategoryLandingSitemap($parts+1)===[],'Out of range map');
db()->beginTransaction();
try {
    query('UPDATE xiu_categories SET enabled=0 WHERE id=9');
    landingAssert(!in_array(234,repairCategoryLandingIds(),true),'Disabled category indexed');
    query('UPDATE xiu_cities SET enabled=0 WHERE id=?',[$cities[0]['id']]);
    landingAssert(repairCategoryLandingSitemap()['count']===(count($cities)-1)*count(array_unique(array_merge(repairCategoryLandingIds(),nationwideListingCategoryIds()))),'Disabled city indexed');
} finally {db()->rollBack();}
echo json_encode(['passed'=>true,'checks'=>$checks,'categories'=>count($categoryIds),'city_category_urls'=>$expected,'min_content_chars'=>min($lengths),'max_content_chars'=>max($lengths)]),PHP_EOL;
