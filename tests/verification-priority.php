<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$token='xiuqa_'.time(); $fixtures=['token'=>$token,'users'=>[],'listings'=>[],'articles'=>[],'categories'=>[],'cities'=>[],'districts'=>[]]; $checks=[];
$remember=function(string $kind,$value) use(&$fixtures) { $fixtures[$kind][]=$value; file_put_contents(ROOT.'/tests/artifacts/fixtures.json',json_encode($fixtures,JSON_THROW_ON_ERROR)); };
$check=function(bool $value,string $label) use(&$checks) { if(!$value) throw new RuntimeException($label); $checks[]=$label; echo "PASS {$label}\n"; };
query('INSERT INTO xiu_categories (name,slug,sort_order) VALUES (?,?,?)',[$token.' category',$token,-1000]); $category=(int)db()->lastInsertId(); $remember('categories',$category); ensureSeoSlug('xiu_categories',$category);
$city=2; query('INSERT INTO xiu_districts (city_id,name,seo_slug) VALUES (?,?,?)',[$city,$token.' district',$token]); $district=(int)db()->lastInsertId(); $remember('districts',$district);
$users=[];
foreach(['company','personal','none','pending','rejected'] as $kind) {
    $username=$token.'_'.$kind; query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$username,password_hash('QAPriority2026',PASSWORD_DEFAULT)]); $users[$kind]=(int)db()->lastInsertId(); $remember('users',$username);
    if($kind!=='none') query('INSERT INTO xiu_user_verifications (user_id,type,subject_name,status) VALUES (?,?,?,?)',[$users[$kind],in_array($kind,['company','pending'],true)?'company':'personal','Private synthetic identity '.$token,in_array($kind,['company','personal'],true)?'approved':$kind]);
}
$listings=[];
foreach(['company','personal','none','company','personal','pending','rejected',...array_fill(0,13,'none')] as $index=>$kind) {
    query("INSERT INTO xiu_listings (user_id,category_id,city_id,district_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,?,?,?,?,?,?,?,?,?, 'published',1)",[$users[$kind],$category,$city,$district,$index===3?'request':'service',$token.' '.$kind.' listing '.$index,'QA synthetic address','演示联系人','00000000000','模拟测试信息，仅用于认证排序验收。']);
    $id=(int)db()->lastInsertId();$remember('listings',$id);ensureSeoSlug('xiu_listings',$id);$listings[]=['id'=>$id,'kind'=>$kind,'url'=>listingUrl(row('SELECT * FROM xiu_listings WHERE id=?',[$id]))];
}
$expected=array_column($listings,'id'); usort($expected,function($left,$right) use($listings) { $ranks=array_column(array_map(fn($item)=>['id'=>$item['id'],'rank'=>['company'=>2,'personal'=>1][$item['kind']]??0],$listings),'rank','id'); return [$ranks[$right],$right]<=>[$ranks[$left],$left]; });
foreach([['q'=>$token],['category'=>$category],['category'=>$category,'city'=>$city,'district'=>$district]] as $filters) {
    $first=listingSearch($filters,1);$second=listingSearch($filters,2);
    $check($first['total']===20 && $first['pages']===2,'Pagination retains exact count');
    $check(array_column(array_merge($first['items'],$second['items']),'id')===$expected,'Global certification ordering precedes pagination');
}
$serviceIds=array_column(array_filter($listings,fn($item)=>$item['id']!==$listings[3]['id']),'id');$expectedServices=array_values(array_intersect($expected,$serviceIds));
$check(array_column(listingSearch(['q'=>$token,'type'=>'service'],1,100)['items'],'id')===$expectedServices,'Service filter retains certification ranking');
$check(array_column(listingSearch(['q'=>$token,'type'=>'request'])['items'],'id')===[$listings[3]['id']],'Request filter retains matching records');
$check(array_column(listingSearch(['category'=>$category],1,5)['items'],'id')===array_slice($expected,0,5),'Homepage and related result limit respects certification ranking');
query('UPDATE xiu_users SET enabled=0 WHERE id=?',[$users['company']]);
$check(!array_intersect(array_column(listingSearch(['q'=>$token],1,100)['items'],'id'),[$listings[0]['id'],$listings[3]['id']]),'Certification cannot expose disabled publisher');query('UPDATE xiu_users SET enabled=1 WHERE id=?',[$users['company']]);
file_put_contents(ROOT.'/tests/artifacts/verification-priority-fixtures.json',json_encode(['token'=>$token,'category'=>$category,'city'=>$city,'district'=>$district,'users'=>$users,'listings'=>$listings,'expected'=>$expected,'category_url'=>listUrl(['category'=>$category]),'city_category_url'=>listUrl(['category'=>$category,'city'=>$city]),'district_url'=>listUrl(['category'=>$category,'city'=>$city,'district'=>$district])],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents(ROOT.'/tests/artifacts/verification-priority-model-results.json',json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT));
echo 'SUCCESS '.count($checks)." model checks. Fixtures retained for browser checks.\n";
