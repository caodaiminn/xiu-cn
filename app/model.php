<?php
function db(): PDO {
    static $connection;
    if (!$connection) {
        $settings = (require ROOT . '/app/config.php')['database'];
        $connection = new SitePDO("mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['name']};charset=utf8mb4", $settings['user'], $settings['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    return $connection;
}
function query(string $sql, array $params = []): PDOStatement { $statement=db()->prepare($sql); $statement->execute($params); publicCacheDatabaseWrite($sql); return $statement; }
function rows(string $sql, array $params = []): array { return query($sql,$params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { return query($sql,$params)->fetch() ?: null; }
function scalar(string $sql, array $params = []) { return query($sql,$params)->fetchColumn(); }
function categories(string $kind = 'service', bool $active = true): array {
    $result=publicDataRemember('category-tree',[$kind],60,fn()=>categoryTree($kind));
    return $active ? array_values(array_filter($result,fn($v)=>$v['active'])) : $result;
}
function categoryTree(string $kind): array {
    $all=rows('SELECT * FROM xiu_categories WHERE kind=? ORDER BY sort_order,id',[$kind]);
    $map=array_column($all,null,'id');
    $result=[];
    $walk=function($parent,$depth,$prefix,$ancestorsEnabled) use (&$walk,&$result,$all) {
        foreach ($all as $item) if ((int)$item['parent_id']===$parent) {
            $item['depth']=$depth; $item['label']=$prefix.$item['name'];
            $item['active']=$ancestorsEnabled && (bool)$item['enabled']; $result[]=$item;
            if ($depth<3) $walk((int)$item['id'],$depth+1,$prefix.$item['name'].' / ',$item['active']);
        }
    };
    $walk(0,1,'',true);
    return $result;
}
function categoryIds(string $kind, int $parent = 0): array {
    $all=categories($kind); $ids=[]; $selected=$parent===0;
    foreach ($all as $item) {
        if ($parent===0 || (int)$item['id']===$parent || in_array((int)$item['parent_id'],$ids,true)) $ids[]=(int)$item['id'];
    }
    return $ids;
}
function cityList(bool $active = true): array { return publicDataRemember('city-list',[$active],60,fn()=>rows('SELECT * FROM xiu_cities'.($active?' WHERE enabled=1':'').' ORDER BY sort_order,id')); }
function cityDirectory(): array {
    $data=json_decode(file_get_contents(ROOT.'/app/data/nationwide-cities.json'),true,512,JSON_THROW_ON_ERROR);
    $order=array_column($data['provinces'],'code','name'); $groups=[];
    foreach(cityList() as $city) {
        $province=$city['province'];
        if(!isset($groups[$province])) $groups[$province]=['name'=>$province,'anchor'=>'sheng-'.($order[$province]??substr(hash('sha256',$province),0,10)),'cities'=>[]];
        $groups[$province]['cities'][]=$city;
    }
    uasort($groups,fn($a,$b)=>[(int)($order[$a['name']]??99),$a['name']]<=>[(int)($order[$b['name']]??99),$b['name']]);
    return array_values($groups);
}
function districtList(int $city=0,bool $active=true): array {
    $where=[]; $params=[];
    if($city) { $where[]='d.city_id=?'; $params[]=$city; }
    if($active) { $where[]='d.enabled=1'; $where[]='c.enabled=1'; }
    return rows('SELECT d.*,c.name city_name FROM xiu_districts d JOIN xiu_cities c ON c.id=d.city_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY c.sort_order,c.id,d.sort_order,d.id',$params);
}
function serviceNavigation(int $selected): array {
    $all=categories(); $map=array_column($all,null,'id'); $root=$selected;
    while($root && !empty($map[$root]['parent_id'])) $root=(int)$map[$root]['parent_id'];
    $shown=function($item) use ($map) { do { if(!$item['show_in_filter']) return false; $item=$map[$item['parent_id']]??null; } while($item); return true; };
    $groups=array_values(array_filter($all,fn($item)=>$item['depth']===1 && $shown($item)));
    $items=$root?array_values(array_filter($all,fn($item)=>(int)$item['id']!==$root && in_array($root,categoryAncestors((int)$item['id']),true) && $shown($item))):$groups;
    if($root) usort($items,fn($left,$right)=>[$left['sort_order'],$left['id']]<=>[$right['sort_order'],$right['id']]);
    return ['root'=>$root,'groups'=>$groups,'items'=>$items];
}
function visibleListing(array $listing): bool {
    return $listing['status']==='published' && (in_array((int)$listing['id'],nationwideListingIds(),true) || (bool)scalar('SELECT enabled FROM xiu_cities WHERE id=?',[$listing['city_id']])) && (empty($listing['district_id']) || (bool)scalar('SELECT enabled FROM xiu_districts WHERE id=? AND city_id=?',[$listing['district_id'],$listing['city_id']])) && in_array((int)$listing['category_id'],categoryIds('service'),true) && (bool)scalar('SELECT enabled FROM xiu_users WHERE id=?',[$listing['user_id']]);
}
function visibleArticle(array $article): bool { return $article['status']==='published' && in_array((int)$article['category_id'],categoryIds($article['kind']),true); }
function listingSearch(array $filters, int $page=1, int $perPage=12): array {
    return publicListingResults($filters,$page,$perPage,true);
}
function listingPreview(array $filters,int $limit): array {
    return publicListingResults($filters,1,$limit,false);
}
function publicListingResults(array $filters,int $page,int $perPage,bool $paginate): array {
    $filters=['category'=>(int)($filters['category']??0),'city'=>(int)($filters['city']??0),'district'=>(int)($filters['district']??0),'type'=>(string)($filters['type']??''),'q'=>mb_substr((string)($filters['q']??''),0,100)];
    $page=max(1,$page); $perPage=max(1,min(100,$perPage));
    $load=fn()=>listingSearchUncached($filters,$page,$perPage,$paginate);
    if($filters['q']!=='' || $page>10) { publicCacheStat('bypass'); return $load(); }
    return publicDataRemember('listing-results',[$filters,$page,$perPage,$paginate],60,$load);
}
function listingSearchUncached(array $filters,int $page,int $perPage,bool $paginate): array {
    $eligibility=publicDataRemember('listing-eligibility',[],60,function() {
    $eligibility=['cities'=>array_map('intval',array_column(cityList(),'id')),'users'=>array_map('intval',array_column(rows('SELECT id FROM xiu_users WHERE enabled=1'),'id')),'company'=>[],'personal'=>[]];
    foreach(rows("SELECT user_id,type FROM xiu_user_verifications WHERE status='approved'") as $verified) $eligibility[$verified['type']][]=(int)$verified['user_id'];
    return $eligibility;
    });
    $ids=categoryIds('service',(int)($filters['category']??0));
    $nationwideIds=implode(',',nationwideListingIds()?:[0]);
    $where=["l.status='published'",'(l.city_id IN ('.implode(',',$eligibility['cities']?:[0]).') OR l.id IN ('.$nationwideIds.'))','l.user_id IN ('.implode(',',$eligibility['users']?:[0]).')','l.category_id IN ('.implode(',', $ids?:[0]).')']; $params=[];
    if (!empty($filters['city'])) { $where[]='(l.city_id=? OR l.id IN ('.$nationwideIds.'))'; $params[]=(int)$filters['city']; }
    $where[]='(l.district_id IS NULL OR EXISTS (SELECT 1 FROM xiu_districts allowed_district WHERE allowed_district.id=l.district_id AND allowed_district.city_id=l.city_id AND allowed_district.enabled=1))';
    if (!empty($filters['district'])) { $where[]='l.district_id=?'; $params[]=(int)$filters['district']; }
    if (!empty($filters['type']) && in_array($filters['type'],['service','request'],true)) { $where[]='l.type=?'; $params[]=$filters['type']; }
    if (!empty($filters['q'])) { $where[]="(l.title LIKE ? ESCAPE '!' OR l.body LIKE ? ESCAPE '!')"; $keyword='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_substr($filters['q'],0,100)).'%'; $params[]=$keyword; $params[]=$keyword; }
    $base=" FROM xiu_listings l FORCE INDEX (PRIMARY) JOIN xiu_cities c ON c.id=l.city_id JOIN xiu_users u ON u.id=l.user_id LEFT JOIN xiu_districts d ON d.id=l.district_id";
    $condition=' WHERE '.implode(' AND ',$where);
    if($paginate) {
        $countIndex=$filters['q']===''?' FORCE INDEX (listings_category_filter)':'';
        $count=fn()=>(int)scalar('SELECT COUNT(*) FROM xiu_listings l'.$countIndex.$condition,$params);
        $total=$filters['q']!==''?$count():publicDataRemember('listing-count',[$filters],60,$count);
        $pages=max(1,(int)ceil($total/$perPage)); $page=min(max(1,$page),$pages);
    }
    $verification=" LEFT JOIN xiu_user_verifications v ON v.user_id=l.user_id AND v.status='approved'";
    // Sort lightweight identifiers before loading bodies and images for the current page.
    $rank='CASE WHEN l.user_id IN ('.implode(',',$eligibility['company']?:[0]).') THEN 2 WHEN l.user_id IN ('.implode(',',$eligibility['personal']?:[0]).') THEN 1 ELSE 0 END';
    if($page===1) {
        // Read each priority group in index order instead of sorting every published identifier.
        $buckets=[['l.refresh_order>0','l.refresh_order DESC,'.$rank.' DESC,l.id DESC']];
        foreach(['company','personal'] as $type) if($eligibility[$type]) $buckets[]=['l.refresh_order=0 AND l.user_id IN ('.implode(',',$eligibility[$type]).')','l.id DESC'];
        $verified=array_merge($eligibility['company'],$eligibility['personal']);
        $buckets[]=['l.refresh_order=0'.($verified?' AND l.user_id NOT IN ('.implode(',',$verified).')':''),'l.id DESC'];
        $selected=[];
        foreach($buckets as [$bucket,$order]) {
            $remaining=$perPage-count($selected); if($remaining<=0) break;
            $nationwide=!$filters['category'] && !$filters['city'] && !$filters['district'] && $filters['q']==='';
            $index=$nationwide && ($bucket===$buckets[0][0] || $bucket===$buckets[count($buckets)-1][0])?' FORCE INDEX (listings_refresh)':'';
            $selected=array_merge($selected,array_map('intval',array_column(rows('SELECT l.id FROM xiu_listings l'.$index.$condition.' AND '.$bucket.' ORDER BY '.$order.' LIMIT '.$remaining,$params),'id')));
        }
    } else $selected=array_map('intval',array_column(rows('SELECT l.id FROM xiu_listings l'.$condition.' ORDER BY l.refresh_order DESC,'.$rank.' DESC,l.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage),$params),'id'));
    $items=[];
    if($selected) {
        $records=rows('SELECT l.*,c.name city_name,k.name category_name,d.name district_name,v.type verification_type,(SELECT i.id FROM xiu_images i WHERE i.listing_id=l.id ORDER BY i.id LIMIT 1) image_id'.$base.' JOIN xiu_categories k ON k.id=l.category_id'.$verification.$condition.' AND (c.enabled=1 OR l.id IN ('.$nationwideIds.')) AND u.enabled=1 AND l.id IN ('.implode(',',$selected).')',$params);
        $map=array_column($records,null,'id');
        foreach($selected as $id) if(isset($map[$id])) $items[]=listingWithServiceScope($map[$id]);
    }
    return $paginate?compact('items','total','page','pages'):compact('items');
}
function articleSearch(string $kind, array $filters, int $page=1): array {
    $ids=categoryIds($kind,(int)($filters['category']??0)); $params=[$kind];
    $where=["a.status='published'",'a.kind=?','a.category_id IN ('.implode(',',$ids?:[0]).')'];
    if (!empty($filters['q'])) { $where[]="(a.title LIKE ? ESCAPE '!' OR a.body LIKE ? ESCAPE '!')"; $keyword='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_substr($filters['q'],0,100)).'%'; $params[]=$keyword; $params[]=$keyword; }
    $from=' FROM xiu_articles a JOIN xiu_categories k ON k.id=a.category_id WHERE '.implode(' AND ',$where);
    $total=(int)scalar('SELECT COUNT(*)'.$from,$params); $pages=max(1,(int)ceil($total/10)); $page=min(max(1,$page),$pages);
    $items=rows('SELECT a.*,k.name category_name,(SELECT id FROM xiu_images WHERE article_id=a.id ORDER BY id LIMIT 1) image_id'.$from.' ORDER BY a.id DESC LIMIT 10 OFFSET '.(($page-1)*10),$params);
    return compact('items','total','page','pages');
}
