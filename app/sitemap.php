<?php
function sitemapContent(string $kind,int $part=0): array {
    if($kind==='zhi-nan') { $guides=repairGuideEntries(); return $part?array_slice($guides,($part-1)*1000,1000):['count'=>count($guides)]; }
    if($kind==='xin-xi') {
        $ids=categoryIds('service');
        $nationwide=implode(',',nationwideListingIds()?:[0]);
        $sql="SELECT l.* FROM xiu_listings l JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_users u ON u.id=l.user_id WHERE l.status='published' AND l.is_demo=0 AND (l.district_id IS NULL OR EXISTS (SELECT 1 FROM xiu_districts d WHERE d.id=l.district_id AND d.city_id=l.city_id AND d.enabled=1)) AND (c.enabled=1 OR l.id IN (".$nationwide.")) AND u.enabled=1 AND l.category_id IN (".implode(',',$ids?:[0]).')';
    } else {
        $articleKind=$kind==='bai-ke'?'encyclopedia':'knowledge'; $ids=categoryIds($articleKind);
        $sql="SELECT a.* FROM xiu_articles a WHERE a.status='published' AND a.is_demo=0 AND a.kind=".db()->quote($articleKind).' AND a.category_id IN ('.implode(',',$ids?:[0]).')';
    }
    if(!$part) return ['count'=>(int)scalar('SELECT COUNT(*) FROM ('.$sql.') AS public_content')];
    return rows($sql.' ORDER BY id LIMIT 1000 OFFSET '.(($part-1)*1000));
}
function categoryAncestors(int $id): array {
    $result=[]; $visited=[];
    while($id && !in_array($id,$visited,true)) { $visited[]=$id; $record=seoRecord('xiu_categories',$id); if(!$record) break; $result[]=$id; $id=(int)$record['parent_id']; }
    return $result;
}
/** Include content-backed category pages and existing published collections. */
function sitemapPageEntries(): array {
    $entries=['/'=>null,publicPath('cities')=>null,publicPath('sitemap')=>null]; $counts=[];
    if(repairGuidesAvailable()) $entries['/wei-xiu-zhi-nan/']=null;
    foreach(repairCategoryLandingIds() as $categoryId) $entries[listUrl(['category'=>$categoryId])]=null;
    $ids=categoryIds('service');
    $nationwide=implode(',',nationwideListingIds()?:[0]);
    $groups=rows("SELECT l.category_id,l.city_id,l.district_id,l.type,(l.id IN (".$nationwide.")) nationwide,COUNT(*) amount FROM xiu_listings l JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_users u ON u.id=l.user_id WHERE l.status='published' AND l.is_demo=0 AND (l.district_id IS NULL OR EXISTS (SELECT 1 FROM xiu_districts d WHERE d.id=l.district_id AND d.city_id=l.city_id AND d.enabled=1)) AND (c.enabled=1 OR l.id IN (".$nationwide.")) AND u.enabled=1 AND l.category_id IN (".implode(',',$ids?:[0]).') GROUP BY l.category_id,l.city_id,l.district_id,l.type,nationwide');
    foreach($groups as $group) {
        $categoryIds=array_merge([0],categoryAncestors((int)$group['category_id']));
        foreach($group['nationwide']?[0]:[0,(int)$group['city_id']] as $cityId) foreach($categoryIds as $categoryId) foreach(['',$group['type']] as $type) foreach(array_unique([0,(int)$group['district_id']]) as $districtId) {
            if($districtId && !$cityId) continue;
            $filters=['city'=>$cityId,'category'=>$categoryId,'district'=>$districtId,'type'=>$type]; $url=listUrl($filters);
            if(!isset($counts[$url])) $counts[$url]=['kind'=>'service','filters'=>$filters,'count'=>0];
            $counts[$url]['count']+=(int)$group['amount'];
        }
    }
    foreach(['knowledge','encyclopedia'] as $kind) {
        $ids=categoryIds($kind);
        foreach(rows("SELECT category_id,COUNT(*) amount FROM xiu_articles WHERE status='published' AND is_demo=0 AND kind=? AND category_id IN (".implode(',',$ids?:[0]).') GROUP BY category_id',[$kind]) as $group) {
            foreach(array_merge([0],categoryAncestors((int)$group['category_id'])) as $categoryId) {
                $filters=['category'=>$categoryId]; $url=articleCollectionUrl($kind,$filters);
                if(!isset($counts[$url])) $counts[$url]=['kind'=>$kind,'filters'=>$filters,'count'=>0];
                $counts[$url]['count']+=(int)$group['amount'];
            }
        }
    }
    foreach($counts as $url=>$collection) {
        $pages=(int)ceil($collection['count']/($collection['kind']==='service'?12:10));
        for($page=1;$page<=$pages;$page++) {
            if($page===1 && $collection['kind']==='service' && !empty($collection['filters']['city']) && repairCategoryLandingEligible($collection['filters'])) continue;
            $entries[$collection['kind']==='service'?listUrl($collection['filters'],$page):articleCollectionUrl($collection['kind'],$collection['filters'],$page)]=null;
        }
    }
    return $entries;
}
function xmlEscape(string $value): string { return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
function sitemapController(string $path): bool {
    if($path==='/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: public, max-age=300');
        echo "User-agent: *\nAllow: /\nDisallow: /dgadmin/\nDisallow: /user/\nDisallow: /yong-hu-zhong-xin/\nDisallow: /app/\nDisallow: /storage/\nDisallow: /backup/\nDisallow: /scripts/\nDisallow: /tests/\nSitemap: ".absoluteUrl('/sitemap.xml')."\n"; return true;
    }
    if($path==='/sitemap.xml') {
        header('Content-Type: application/xml; charset=utf-8'); header('Cache-Control: public, max-age=60');
        echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $pageParts=max(1,(int)ceil(count(sitemapPageEntries())/1000));
        for($part=1;$part<=$pageParts;$part++) echo '<sitemap><loc>'.xmlEscape(absoluteUrl('/sitemap-dao-hang-'.$part.'.xml')).'</loc></sitemap>';
        foreach(['xin-xi','zhi-shi','bai-ke','zhi-nan'] as $kind) { $pages=(int)ceil(sitemapContent($kind)['count']/1000); for($part=1;$part<=$pages;$part++) echo '<sitemap><loc>'.xmlEscape(absoluteUrl('/sitemap-'.$kind.'-'.$part.'.xml')).'</loc></sitemap>'; }
        $cityParts=(int)ceil(repairCategoryLandingSitemap()['count']/1000);
        for($part=1;$part<=$cityParts;$part++) echo '<sitemap><loc>'.xmlEscape(absoluteUrl('/sitemap-cheng-shi-fen-lei-'.$part.'.xml')).'</loc></sitemap>';
        echo '</sitemapindex>'; return true;
    }
    if(preg_match('#^/sitemap-(dao-hang|xin-xi|zhi-shi|bai-ke|zhi-nan|cheng-shi-fen-lei)-([1-9][0-9]{0,6})\.xml$#',$path,$match)) {
        $kind=$match[1]; $part=(int)$match[2]; $entries=[];
        if($kind==='cheng-shi-fen-lei') {if($part>(int)ceil(repairCategoryLandingSitemap()['count']/1000)) abortPage(404,'站点地图分页不存在。');$entries=repairCategoryLandingSitemap($part);}
        elseif($kind==='dao-hang') { $all=sitemapPageEntries(); if($part>max(1,(int)ceil(count($all)/1000))) abortPage(404,'站点地图分页不存在。'); $entries=array_slice($all,($part-1)*1000,1000,true); }
        else { if($part>(int)ceil(sitemapContent($kind)['count']/1000)) abortPage(404,'站点地图分页不存在。'); foreach(sitemapContent($kind,$part) as $item) $entries[$kind==='xin-xi'?listingUrl($item):($kind==='zhi-nan'?repairGuideUrl($item):articleUrl($item))]=date(DATE_ATOM,strtotime($item['updated_at'])); }
        header('Content-Type: application/xml; charset=utf-8'); header('Cache-Control: public, max-age=60');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach($entries as $url=>$modified) { echo '<url><loc>'.xmlEscape(absoluteUrl($url)).'</loc>'; if($modified) echo '<lastmod>'.xmlEscape($modified).'</lastmod>'; echo '</url>'; }
        echo '</urlset>'; return true;
    }
    return false;
}
