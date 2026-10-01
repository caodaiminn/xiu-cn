<?php
declare(strict_types=1);
/** Add the requested second-level categories below the existing life-service parent. */
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$names=['陪护公司','陪练车','陪诊师','陪驾公司','陪护','陪餐','陪爬山'];
$parent=row("SELECT * FROM xiu_categories WHERE kind='service' AND slug='life' AND parent_id IS NULL");
if(!$parent || !$parent['enabled']) throw new RuntimeException('The active life-service parent is missing.');
foreach($names as $name) if(preg_match('/(?:^|-)u[0-9a-f]+(?:-|$)/',pinyinSlug($name))) throw new RuntimeException('Missing pinyin for '.$name);
if(!in_array('--apply',$argv,true)) {
    echo json_encode(['mode'=>'preview','parent'=>$parent['name'],'categories'=>array_map(fn($name)=>['name'=>$name,'slug'=>pinyinSlug($name)],$names)],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}
$lock=fopen(ROOT.'/storage/limits/companion-service-categories.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Category update already running.');
$backup=ROOT.'/backup/2026-10-01-life-companion-seo';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create category backup directory.');
if(!is_file($backup.'/categories-before.json')) {
    $snapshot=json_encode(rows('SELECT * FROM xiu_categories ORDER BY id'),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(file_put_contents($backup.'/categories-before.json',$snapshot,LOCK_EX)===false) throw new RuntimeException('Category backup failed.');
}
$created=[]; $result=[];
db()->beginTransaction();
try {
    $order=(int)scalar('SELECT COALESCE(MAX(sort_order),-1)+1 FROM xiu_categories WHERE parent_id=?',[$parent['id']]);
    foreach($names as $name) {
        $record=row("SELECT * FROM xiu_categories WHERE kind='service' AND parent_id=? AND name=?",[$parent['id'],$name]);
        if(!$record) {
            $base=pinyinSlug($name); $slug=$base;
            while(scalar('SELECT id FROM xiu_categories WHERE slug=?',[$slug])) $slug=$base.'-'.bin2hex(random_bytes(4));
            query("INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order,enabled,show_in_filter) VALUES (?,'service',?,?,?,1,1)",[$parent['id'],$name,$slug,$order++]);
            $id=(int)db()->lastInsertId(); ensureSeoSlug('xiu_categories',$id); $created[]=$id;
            $record=row('SELECT * FROM xiu_categories WHERE id=?',[$id]);
        }
        $result[]=['id'=>(int)$record['id'],'name'=>$name,'parent_id'=>(int)$parent['id'],'url'=>listUrl(['category'=>$record['id']])];
    }
    db()->commit();
} catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
echo json_encode(['created'=>count($created),'parent'=>$parent['name'],'categories'=>$result],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
flock($lock,LOCK_UN); fclose($lock);
