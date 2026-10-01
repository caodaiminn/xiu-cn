<?php
/** Add brand leaves under the existing air-conditioner repair category. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$brands=json_decode(file_get_contents(ROOT.'/app/data/air-conditioner-brands.json'),true,512,JSON_THROW_ON_ERROR);
foreach($brands as $brand) {
    if(!isset($brand['name'],$brand['slug']) || !preg_match('/^[a-z][a-z0-9-]{0,79}$/',$brand['slug'])) throw new RuntimeException('Invalid brand catalog.');
}
if(in_array('--preview',$argv,true)) { echo json_encode($brands,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit; }
$lock=fopen(ROOT.'/storage/air-conditioner-brands.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Brand category import already running.');
$backup=ROOT.'/backup/'.date('Y-m-d').'-air-conditioner-brand-categories';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create category backup.');
$created=[];
db()->beginTransaction();
try {
    $matches=rows("SELECT * FROM xiu_categories WHERE kind='service' AND (slug='air-conditioner' OR name='空调维修') FOR UPDATE");
    if(count($matches)!==1) throw new RuntimeException('Air-conditioner repair category is missing or ambiguous.');
    $parent=$matches[0]; $tree=array_column(categories('service',false),null,'id');
    if(($tree[$parent['id']]['depth']??0)!==2) throw new RuntimeException('Brand categories require a level-two parent.');
    $before=rows('SELECT * FROM xiu_categories ORDER BY id');
    if(!is_file($backup.'/categories-before.json') && file_put_contents($backup.'/categories-before.json',json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Category backup failed.');
    $order=(int)scalar('SELECT COALESCE(MAX(sort_order),-1)+1 FROM xiu_categories WHERE parent_id=?',[$parent['id']]);
    foreach($brands as $brand) {
        // Reruns preserve edited sort order, visibility and enabled state.
        if(scalar("SELECT id FROM xiu_categories WHERE kind='service' AND parent_id=? AND name=?",[$parent['id'],$brand['name']])) continue;
        $slug='ac-brand-'.$brand['slug'];
        if(scalar('SELECT id FROM xiu_categories WHERE slug=?',[$slug])) throw new RuntimeException('Brand slug already used: '.$slug);
        $seo=$brand['slug'];
        if(scalar("SELECT id FROM xiu_categories WHERE kind='service' AND seo_slug=?",[$seo])) $seo='kong-tiao-wei-xiu-'.$seo;
        if(scalar("SELECT id FROM xiu_categories WHERE kind='service' AND seo_slug=?",[$seo])) throw new RuntimeException('Brand SEO slug already used: '.$seo);
        query("INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order,enabled,show_in_filter,seo_slug) VALUES (?,'service',?,?,?,1,1,?)",[$parent['id'],$brand['name'],$slug,$order++,$seo]);
        $created[]=(int)db()->lastInsertId();
    }
    if($created) {
        $stamp=date('His').'-'.bin2hex(random_bytes(3));
        $rollback='-- Removes only the category IDs created by this import; referenced categories must be retained.'.PHP_EOL.'DELETE FROM xiu_categories WHERE id IN ('.implode(',',$created).') AND parent_id='.(int)$parent['id'].';'.PHP_EOL;
        if(file_put_contents($backup.'/created-'.$stamp.'.json',json_encode($created,JSON_THROW_ON_ERROR),LOCK_EX)===false || file_put_contents($backup.'/rollback-'.$stamp.'.sql',$rollback,LOCK_EX)===false) throw new RuntimeException('Import recovery record failed.');
    }
    db()->commit();
    $items=rows('SELECT id,name,parent_id,sort_order,enabled,show_in_filter,seo_slug FROM xiu_categories WHERE parent_id=? ORDER BY sort_order,id',[$parent['id']]);
    foreach($items as &$item) $item['url']=listUrl(['category'=>$item['id']]); unset($item);
    echo json_encode(['created'=>count($created),'created_ids'=>$created,'parent_id'=>(int)$parent['id'],'children'=>$items],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
finally { flock($lock,LOCK_UN); fclose($lock); }
