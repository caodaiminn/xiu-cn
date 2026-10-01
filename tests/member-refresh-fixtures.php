<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$token='xiuqa_'.time(); $fixtures=['token'=>$token,'users'=>[],'listings'=>[],'articles'=>[],'categories'=>[],'cities'=>[],'districts'=>[]];
$remember=function($kind,$value) use(&$fixtures) { $fixtures[$kind][]=$value; file_put_contents(ROOT.'/tests/artifacts/fixtures.json',json_encode($fixtures)); };
$categories=[];
foreach(['main','other'] as $kind) {
    query('INSERT INTO xiu_categories (name,slug,sort_order) VALUES (?,?,?)',[$token.' category',$token.'-'.$kind,-1000]);
    $id=(int)db()->lastInsertId(); $remember('categories',$id); ensureSeoSlug('xiu_categories',$id); $categories[$kind]=$id;
}
query('INSERT INTO xiu_districts (city_id,name,seo_slug) VALUES (?,?,?)',[2,$token.' district',$token]); $district=(int)db()->lastInsertId(); $remember('districts',$district);
$users=[];$listings=[];
foreach(['owner','other','company','yesterday','race','ui'] as $kind) {
    $name=$token.'_'.$kind; query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$name,password_hash('QARefresh2026',PASSWORD_DEFAULT)]); $users[$kind]=['id'=>(int)db()->lastInsertId(),'username'=>$name]; $remember('users',$name);
    if($kind==='company') query("INSERT INTO xiu_user_verifications (user_id,type,subject_name,status) VALUES (?,'company',?,'approved')",[$users[$kind]['id'],'Synthetic company for refresh checks']);
    $statuses=$kind==='owner'?['published','published','pending','rejected','offline','published']:['published','published'];
    foreach($statuses as $index=>$status) {
        $category=($kind==='owner' && $index===5)?$categories['other']:$categories['main'];
        query('INSERT INTO xiu_listings (user_id,category_id,city_id,district_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)',[$users[$kind]['id'],$category,2,$district,'service',$token.' '.$kind.' listing '.$index,'QA synthetic address','演示联系人','00000000000','仅用于每日刷新验收的模拟演示信息。',$status]);
        $id=(int)db()->lastInsertId(); $remember('listings',$id); ensureSeoSlug('xiu_listings',$id); $listing=row('SELECT * FROM xiu_listings WHERE id=?',[$id]);$listings[$kind][]=['id'=>$id,'url'=>listingUrl($listing),'status'=>$status];
    }
}
$data=compact('token','users','listings','categories','district'); $data['category_url']=listUrl(['category'=>$categories['main']]);$data['other_category_url']=listUrl(['category'=>$categories['other']]); $data['city_category_url']=listUrl(['category'=>$categories['main'],'city'=>2]); $data['district_url']=listUrl(['category'=>$categories['main'],'city'=>2,'district'=>$district]);
file_put_contents(ROOT.'/tests/artifacts/member-refresh-fixtures.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "Refresh fixtures ready: {$token}\n";
