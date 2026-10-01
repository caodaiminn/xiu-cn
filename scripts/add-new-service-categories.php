<?php
/** Import the approved charging, smart home, accessibility and eldercare categories. */
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';

$groups=[
    '新能源汽车充电设施售后'=>[
        '充电桩安装','充电桩移机','充电桩拆除','充电线路升级','漏电排查','跳闸检修','无法充电检修',
        '通信故障检修','充电模块维修','充电枪及线缆维修更换','充电桩主板维修','充电桩检测','充电桩维护保养',
    ],
    '智能家居售后'=>[
        '智能马桶维修','智能门锁安装维修','智能窗帘安装维修','扫地机器人维修','洗地机维修','智能晾衣架安装维修',
        '智能灯具安装维修','摄像头安装维修','智能音箱维修','智能家居网关调试维修','全屋智能安装调试',
    ],
    '家庭适老化改造'=>[
        '老人卫生间改造','防滑地面改造','安全扶手安装','坐便器改造','床边扶手安装','无障碍坡道改造',
        '智能监护设备安装','紧急呼叫器安装','老人房改造',
    ],
    '居家养老服务'=>['上门助浴','上门助洁','陪同出行','陪诊服务','紧急上门协助','居家陪护'],
];
// Fail before writing if a new name would receive an untranslated URL token.
foreach($groups as $name=>$children) foreach(array_merge([$name],$children) as $label) {
    if(preg_match('/(?:^|-)u[0-9a-f]+(?:-|$)/',pinyinSlug($label))) throw new RuntimeException('Missing pinyin for '.$label);
}
if(in_array('--preview',$argv,true)) { echo json_encode($groups,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit; }
$lock=fopen(ROOT.'/storage/new-service-categories.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Category import already running.');
$backup=ROOT.'/backup/2026-10-01-new-service-categories';
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
    foreach($groups as $name=>$children) {
        $root=$insert($name,null,$rootOrder++);
        foreach($children as $order=>$child) $insert($child,$root,$order);
        $result[]=['id'=>$root,'name'=>$name,'url'=>listUrl(['category'=>$root]),'children'=>rows('SELECT id,name,seo_slug FROM xiu_categories WHERE parent_id=? ORDER BY sort_order,id',[$root])];
    }
    db()->commit();
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
echo json_encode(['created'=>count($created),'groups'=>$result],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
