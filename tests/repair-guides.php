<?php
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/repair-guide-content.php';
$checks=0;
function guideAssert(bool $condition,string $message): void {global $checks;$checks++;if(!$condition) throw new RuntimeException($message);}
$catalog=categories();$map=array_column($catalog,null,'id');$titleSet=[];$slugs=[];
guideAssert(repairGuidesAvailable(),'Guide tables missing');
foreach($catalog as $category) {
    $guides=repairGuidesForCategory((int)$category['id']);
    guideAssert(count($guides)===5,'Incomplete category '.$category['id']);
    guideAssert(array_map('intval',array_column($guides,'slot'))===[1,2,3,4,5],'Guide slots '.$category['id']);
    foreach($guides as $guide) {
        guideAssert(mb_strlen($guide['title'])<=120,'Title too long');
        $sections=json_decode($guide['sections'],true,512,JSON_THROW_ON_ERROR);$text=json_encode($sections,JSON_UNESCAPED_UNICODE);
        guideAssert(count($sections)>=4 && mb_strlen($text)>400,'Thin guide '.$guide['id']);
        guideAssert(!preg_match('/1[3-9][0-9]{9}/',$text),'Generated phone found');
        guideAssert(!str_contains($text,'本地测试信息'),'Demo content found');
        guideAssert(preg_match('#^/wei-xiu-zhi-nan/[a-z0-9-]+\.html$#',repairGuideUrl($guide))===1,'Invalid guide URL');
    }
    $drafts=buildRepairGuideContent($category,$map);
    guideAssert(count($drafts)===5,'Unsupported content category');
}
foreach(repairGuideEntries() as $guide) {
    guideAssert(!isset($titleSet[$guide['title']]),'Duplicate title');$titleSet[$guide['title']]=true;
    guideAssert(!isset($slugs[$guide['slug']]),'Duplicate slug');$slugs[$guide['slug']]=true;
}
guideAssert(repairGuidesForCategory(0)===[],'Zero category');
guideAssert(repairGuidesForCategory(999999)===[],'Unknown category');
guideAssert(array_column(repairGuidesForCategory(202),'id')===array_column(repairGuidesForCategory(2),'id'),'Brand duplicate detail pages');
guideAssert(sitemapContent('zhi-nan')['count']===count($slugs),'Sitemap guide count');
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
$guide=repairGuideEntries()[0];$guide['category_name']=$map[(int)$guide['category_id']]['name'];
$seo=pageSeo('repair-guide',['guide'=>$guide]);
guideAssert($seo['canonical']===absoluteUrl(repairGuideUrl($guide)),'Canonical guide');
guideAssert(str_starts_with($seo['robots'],'index'),'Guide indexing');
guideAssert(count(array_filter($seo['schema'],fn($item)=>$item['@type']==='Article'))===1,'Article structured data');
db()->beginTransaction();
try {
    query('UPDATE xiu_categories SET enabled=0 WHERE id=9');
    guideAssert(repairGuidesForCategory(227)===[],'Disabled ancestor visible');
    guideAssert(!in_array('g227-wen-ti-ji-lu',array_column(repairGuideEntries(),'slug'),true),'Disabled guide in sitemap');
    $shared=repairGuidesForCategory(61)[0];
    guideAssert((int)repairGuideVisibleCategory($shared)['id']===61,'Shared guide must remain readable through active alias');
    query('UPDATE xiu_categories SET enabled=0 WHERE id=61');
    guideAssert(repairGuideVisibleCategory($shared)===null,'Fully disabled shared guide remains visible');
} finally {db()->rollBack();}
echo json_encode(['passed'=>true,'checks'=>$checks,'cities'=>count(cityList()),'categories'=>count($catalog),'unique_guides'=>count($slugs),'assignments'=>count($catalog)*5]),PHP_EOL;
