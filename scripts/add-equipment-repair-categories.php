<?php
/** Add the requested service groups without moving existing categories or listings. */
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';

$groups=[
    '餐饮设备维修'=>['冷柜','制冰机','商用冰箱','咖啡机','烤箱','洗碗机','油烟机','蒸饭柜'],
    '工业设备维修'=>['空压机','叉车','起重机','升降机','激光切割机','数控机床','变频器','工业冷水机','电机','水泵'],
];
$lock=fopen(ROOT.'/storage/equipment-categories.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Category import already running.');
$backup=ROOT.'/backup/2026-10-01-equipment-repair-categories';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup directory.');
if(!is_file($backup.'/categories-before.json')) {
    $snapshot=json_encode(rows('SELECT * FROM xiu_categories ORDER BY id'),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(file_put_contents($backup.'/categories-before.json',$snapshot,LOCK_EX)===false) throw new RuntimeException('Category backup failed.');
}

$created=[]; $result=[];
$insert=function(string $name,?int $parent,int $order) use (&$created): int {
    $existing=row("SELECT id FROM xiu_categories WHERE kind='service' AND parent_id <=> ? AND name=?",[$parent,$name]);
    if($existing) return (int)$existing['id'];
    $base=rtrim(substr(pinyinSlug($name),0,65),'-'); $slug=$base;
    while(scalar('SELECT id FROM xiu_categories WHERE slug=?',[$slug])) $slug=$base.'-'.bin2hex(random_bytes(4));
    query("INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order,enabled,show_in_filter) VALUES (?,'service',?,?,?,1,1)",[$parent,$name,$slug,$order]);
    $id=(int)db()->lastInsertId(); ensureSeoSlug('xiu_categories',$id); $created[]=$id;
    return $id;
};
db()->beginTransaction();
try {
    $rootOrder=(int)scalar("SELECT COALESCE(MAX(sort_order),-1)+1 FROM xiu_categories WHERE kind='service' AND parent_id IS NULL");
    foreach($groups as $name=>$devices) {
        $root=$insert($name,null,$rootOrder++);
        foreach($devices as $order=>$device) $insert($device.'维修',$root,$order);
        $result[]=['id'=>$root,'name'=>$name,'url'=>listUrl(['category'=>$root]),'children'=>rows('SELECT id,name,seo_slug FROM xiu_categories WHERE parent_id=? ORDER BY sort_order,id',[$root])];
    }
    db()->commit();
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
echo json_encode(['created'=>count($created),'groups'=>$result],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
