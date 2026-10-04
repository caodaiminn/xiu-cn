<?php
function adminListingPerPage($value): int { $size=(int)$value; return in_array($size,[20,30,50],true)?$size:20; }
function clearAdminListingCountCache(): void { $path=ROOT.'/storage/cache/admin-listing-counts.json'; if(is_file($path)) @unlink($path); invalidatePublicDataCache(); }
function adminListingCounts(): array {
    $path=ROOT.'/storage/cache/admin-listing-counts.json';
    $cached=is_file($path)?json_decode((string)@file_get_contents($path),true):null;
    if(is_array($cached) && ($cached['expires_at']??0)>time() && ($cached['expires_at']??0)<=time()+30 && isset($cached['counts']['total'])) {
        $counts=$cached['counts'];
        if(count(array_filter($counts,fn($count)=>is_int($count) && $count>=0))===5 && count($counts)===5 && isset($counts['pending'],$counts['published'],$counts['rejected'],$counts['offline']) && $counts['total']===array_sum(array_diff_key($counts,['total'=>true]))) return $counts;
    }
    $counts=['pending'=>0,'published'=>0,'rejected'=>0,'offline'=>0,'total'=>0];
    foreach(rows('SELECT status,COUNT(*) total FROM xiu_listings GROUP BY status') as $item) { $counts[$item['status']]=(int)$item['total']; $counts['total']+=(int)$item['total']; }
    if($counts['total']>=1000) {
        if(is_dir(dirname($path)) || @mkdir(dirname($path),0700,true)) @file_put_contents($path,json_encode(['expires_at'=>time()+30,'counts'=>$counts]),LOCK_EX);
    }
    return $counts;
}
function adminListingResults(string $status, int $requestedPage, int $perPage): array {
    if(!in_array($status,['pending','published','rejected','offline'],true)) $status='';
    $perPage=adminListingPerPage($perPage); $counts=adminListingCounts(); $total=$counts[$status?:'total'];
    $pages=max(1,(int)ceil($total/$perPage)); $page=min(max(1,$requestedPage),$pages);
    $where=$status!==''?' WHERE l.status=?':''; $params=$status!==''?[$status]:[];
    $items=rows('SELECT l.id,l.user_id,l.category_id,l.city_id,l.type,l.title,l.short_slug,l.seo_slug,l.created_at,l.address,l.contact,l.phone,LEFT(l.body,180) body,l.status,l.rejection_reason,l.is_demo,l.updated_at,u.username,k.name category_name,c.name city_name FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id JOIN xiu_categories k ON k.id=l.category_id JOIN xiu_cities c ON c.id=l.city_id'.$where.' ORDER BY l.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage),$params);
    return ['items'=>array_map('listingWithServiceScope',$items),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage,'status'=>$status,'counts'=>$counts];
}
