<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$token='xiuqa_'.time(); $username=$token.'_pages';
$fixtures=['token'=>$token,'users'=>[$username],'listings'=>[],'articles'=>[],'categories'=>[],'cities'=>[]];
$path=ROOT.'/tests/artifacts/fixtures.json';
$baseline=adminListingCounts();
$fixtures['admin_pagination']=['baseline'=>$baseline,'username'=>$username,'password'=>'QAPages2026'];
file_put_contents($path,json_encode($fixtures));
db()->beginTransaction();
try {
    query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$username,password_hash('QAPages2026',PASSWORD_DEFAULT)]); $user=(int)db()->lastInsertId();
    for($index=1;$index<=1001;$index++) {
        query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,short_slug,address,contact,phone,body,status,is_demo) VALUES (?,?,?,'service',?,?,?,?,?,?,'pending',1)",[$user,2,3,$token.' 后台分页检查 '.$index,'qp'.substr($token,-5).str_pad((string)$index,4,'0',STR_PAD_LEFT),'模拟地址','模拟联系人','00000000000',str_repeat('后台分页验证模拟正文。',500)]);
        $fixtures['listings'][]=(int)db()->lastInsertId();
    }
    file_put_contents($path,json_encode($fixtures)); db()->commit(); clearAdminListingCountCache();
    echo "Created 1001 private pending QA records; no public sample information.\n";
} catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
