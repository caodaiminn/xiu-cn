<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/repair-guide-content.php';
$options=getopt('',['input:','apply']);
$path=$options['input']??ROOT.'/reports/imports/2026-10-05-workbook/batch.json';
$batch=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(($batch['nationwide_confirmed']??false)!==true || !preg_match('/^[a-f0-9]{64}$/D',$batch['source_hash']??'')) throw new RuntimeException('Missing source hash or nationwide confirmation.');
$plans=array_column($batch['category_plan'],null,'key');$records=$batch['records'];$keys=[];
foreach($records as $item) {
    if(!isset($plans[$item['category_key']]) || !preg_match('/^[a-f0-9]{64}$/D',$item['fingerprint']) || !preg_match('/^(?:1[3-9]\d{9}|0\d{9,11}|(?:400|800)\d{7})$/D',$item['normalized_phone'])) throw new RuntimeException('Invalid import row '.$item['source_row']);
    if(mb_strlen($item['optimized_title'])>120 || mb_strlen($item['contact'])>64 || preg_match('/[<>\x00-\x1f]/u',$item['optimized_title'].$item['contact'])) throw new RuntimeException('Invalid title/contact.');
    if(isset($keys[$item['fingerprint']])) throw new RuntimeException('Duplicate batch fingerprint.');
    $keys[$item['fingerprint']]=true;
}
$report=['mode'=>isset($options['apply'])?'apply':'preview','source_hash'=>$batch['source_hash'],'rows'=>count($records),'target_categories'=>count($plans),'cities'=>count(cityList()),'inserted'=>0,'reused'=>0,'created_categories'=>0,'records'=>[]];
$nativeRows=rows("SELECT l.* FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id WHERE l.is_demo=0 AND u.username<>'workbook_import' ORDER BY l.id");
$report['existing_real_count']=count($nativeRows);$report['existing_real_hash']=hash('sha256',json_encode($nativeRows,JSON_THROW_ON_ERROR));
foreach($plans as $plan) if($plan['id']) {
    $category=seoRecord('xiu_categories',(int)$plan['id']);
    if(!$category || $category['name']!==$plan['name'] || !in_array((int)$category['id'],categoryIds('service'),true)) throw new RuntimeException('Current category differs from preview.');
}
if(!isset($options['apply'])) {echo json_encode(array_diff_key($report,['records'=>true]),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;exit;}
$lock=fopen(ROOT.'/storage/limits/workbook-import.lock','c');if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another workbook import is running.');
$backup=ROOT.'/backup/'.date('Y-m-d').'-workbook-data/'.date('His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) throw new RuntimeException('Backup directory unavailable.');
$stream=fopen($backup.'/database-before.sql','xb');if(!$stream) throw new RuntimeException('Recovery backup unavailable.');
fwrite($stream,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach(rows('SHOW TABLES') as $tableRow) {
    $table=array_values($tableRow)[0];if(!preg_match('/^xiu_[a-z_]+$/D',$table)) continue;
    $create=row('SHOW CREATE TABLE `'.$table.'`');fwrite($stream,'DROP TABLE IF EXISTS `'.$table."`;\n".$create['Create Table'].";\n");
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $statement=query('SELECT * FROM `'.$table.'`');
    while($record=$statement->fetch()) {
        $columns=implode(',',array_map(fn($key)=>'`'.$key.'`',array_keys($record)));
        $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
        if(fwrite($stream,'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n")===false) throw new RuntimeException('Backup write failed.');
    }
    $statement->closeCursor();db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
}
fwrite($stream,"SET FOREIGN_KEY_CHECKS=1;\n");if(!fflush($stream) || !fclose($stream)) throw new RuntimeException('Backup flush failed.');
db()->exec(file_get_contents(ROOT.'/database/workbook-import.sql'));invalidatePublicDataCache();
$created=[];$inserted=[];$newUser=null;$resolved=[];
db()->beginTransaction();
try {
    $createCategory=function(string $name,?int $parent) use (&$created): int {
        $existing=scalar("SELECT id FROM xiu_categories WHERE kind='service' AND name=? AND parent_id <=> ?",[$name,$parent]);
        if($existing) {if(!in_array((int)$existing,categoryIds('service'),true)) throw new RuntimeException('Existing matching category is disabled.');return (int)$existing;}
        $slug='wb-'.substr(hash('sha256',($parent??0).'|'.$name),0,20);
        query("INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order,enabled,show_in_filter) VALUES (?,'service',?,?,1000,1,1)",[$parent,$name,$slug]);
        $id=(int)db()->lastInsertId();ensureSeoSlug('xiu_categories',$id);$created[]=$id;return $id;
    };
    foreach($plans as $key=>$plan) {
        if($plan['id']) {$resolved[$key]=(int)$plan['id'];continue;}
        $parent=$plan['parent_id']?(int)$plan['parent_id']:null;
        if(!$parent && empty($plan['is_root'])) $parent=$createCategory($plan['root_name'],null);
        $resolved[$key]=$createCategory($plan['name'],$parent);
    }
    $owner=scalar("SELECT id FROM xiu_users WHERE username='workbook_import'");
    if(!$owner) {query('INSERT INTO xiu_users (username,password_hash,enabled) VALUES (?,?,1)',['workbook_import',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$owner=(int)db()->lastInsertId();$newUser=$owner;}
    if(!scalar('SELECT enabled FROM xiu_users WHERE id=?',[$owner]) || scalar('SELECT id FROM xiu_user_verifications WHERE user_id=?',[$owner])) throw new RuntimeException('Import account must be enabled and unverified.');
    $anchor=(int)cityList()[0]['id'];$catalog=array_column(categories(),null,'id');$profiles=repairGuideProfiles();
    foreach($records as $item) {
        $existing=scalar('SELECT listing_id FROM xiu_workbook_imports WHERE fingerprint=? OR (source_hash=? AND source_row=?)',[$item['fingerprint'],$batch['source_hash'],$item['source_row']]);
        if($existing) {$report['reused']++;continue;}
        $category=$catalog[$resolved[$item['category_key']]];$rootCategory=$category;
        while(!empty($rootCategory['parent_id'])) $rootCategory=$catalog[(int)$rootCategory['parent_id']];
        if($rootCategory['name']==='物品回收') $profile=['prepare'=>['物品清单、型号、数量与实物照片','搬运通道、楼层及拆卸条件','估价与结算时间'],'quote'=>['按件或按重量的估价依据','搬运、拆卸和运输费用是否另收','现场核对差异后的最终价格'],'accept'=>['物品、数量与约定收购范围','搬运后的现场清理情况','结算金额与交接凭据'],'risk'=>'处置电脑或存储设备前，妥善迁移和清除个人资料；拆卸用电、用水设备时先确认安全条件，贵重物品保留交接凭据。'];
        elseif($rootCategory['name']==='工具仪器维修') $profile=$profiles['small'];
        else $profile=$profiles[repairGuideProfileKey($category,$catalog)];
        $company=preg_replace('/第\d+年$/u','',$item['company']);
        $body="服务项目：{$item['optimized_title']}。\n维修分类：{$category['name']}。\n服务范围：全国，可在本网站各城市页面预约咨询；具体上门地址、到场时间及是否采用寄修等方式，请与服务方确认。\n联系人：{$item['contact']}。\n联系电话：{$item['phone']}。\n\n";
        if($company!=='') $body.=(mb_strlen($company)<100?'服务方资料：':'服务说明：').$company."\n\n";
        $body.="预约前准备\n请先说明实际服务地点、设备或施工对象，以及当前问题和希望完成的项目。可以准备：".implode('；',$profile['prepare'])."。资料越具体，越便于沟通检测安排与所需时间。\n\n费用与项目确认\n预约时核对".implode('；',$profile['quote'])."。现场检查后再确认处理范围；新增项目应先沟通原因、费用及施工安排，双方确认后进行。\n\n完成后的交接\n建议核对".implode('；',$profile['accept'])."。保留检测记录、配件或材料信息和维修凭据，具体保障范围以双方书面约定为准。\n\n现场提醒\n".$profile['risk'];
        query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status,is_demo) VALUES (?,?,?,'service',?,?,?,?,?,'published',0)",[$owner,$category['id'],$anchor,$item['optimized_title'],'全国服务，具体上门地址及服务方式请电话确认',$item['contact'],$item['phone'],$body]);
        $id=(int)db()->lastInsertId();ensureSeoSlug('xiu_listings',$id);$inserted[]=$id;
        query('INSERT INTO xiu_workbook_imports (listing_id,source_hash,source_row,fingerprint,source_title,source_category,source_payload,nationwide) VALUES (?,?,?,?,?,?,?,1)',[$id,$batch['source_hash'],$item['source_row'],$item['fingerprint'],$item['title'],$item['source_category']??$item['category'],json_encode($item,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $record=row('SELECT * FROM xiu_listings WHERE id=?',[$id]);$report['records'][]=['id'=>$id,'category_id'=>(int)$category['id'],'source_row'=>$item['source_row'],'url'=>listingUrl($record)];
    }
    $rollback="-- Remove only IDs added by this workbook import.\n";
    if($inserted) $rollback.='DELETE FROM xiu_listings WHERE id IN ('.implode(',',$inserted).') AND user_id='.(int)$owner.";\n";
    foreach(array_reverse($created) as $id) $rollback.='DELETE FROM xiu_categories WHERE id='.(int)$id.";\n";
    if($newUser) $rollback.='DELETE FROM xiu_users WHERE id='.(int)$newUser.";\n";
    if(file_put_contents($backup.'/rollback-added.sql',$rollback,LOCK_EX)===false) throw new RuntimeException('Recovery journal unavailable.');
    $nativeAfter=rows("SELECT l.* FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id WHERE l.is_demo=0 AND u.username<>'workbook_import' ORDER BY l.id");
    if(hash('sha256',json_encode($nativeAfter,JSON_THROW_ON_ERROR))!==$report['existing_real_hash']) throw new RuntimeException('Existing genuine records changed.');
    db()->commit();clearAdminListingCountCache();$report['inserted']=count($inserted);$report['created_categories']=count($created);$report['backup']=$backup;$report['category_map']=$resolved;
    file_put_contents($backup.'/result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    echo json_encode(array_diff_key($report,['records'=>true,'category_map'=>true]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch(Throwable $error) {if(db()->inTransaction()) db()->rollBack();throw $error;}
flock($lock,LOCK_UN);fclose($lock);
