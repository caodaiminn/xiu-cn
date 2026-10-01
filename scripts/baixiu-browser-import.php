<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$options=getopt('', ['input:','apply','limit:']);
$limit=(int)($options['limit']??5);
if($limit<5 || $limit>10) throw new InvalidArgumentException('The city/category cap must be between 5 and 10.');
$path=$options['input']??ROOT.'/reports/imports/baixiu-browser/batch.json';
$batch=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if(!isset($batch['records']) || !is_array($batch['records'])) throw new InvalidArgumentException('Missing observed records.');
$apply=array_key_exists('apply',$options);
if(!is_dir(ROOT.'/storage/limits')) mkdir(ROOT.'/storage/limits',0700,true);
$lock=fopen(ROOT.'/storage/limits/baixiu-import.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another import is running.');
$exists=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_listing_sources'");
$existing=$exists?rows('SELECT source_url,fingerprint,city_id,category_id FROM xiu_listing_sources'):[];
$urls=[]; $fingerprints=[]; $counts=[];
foreach($existing as $item) { $urls[$item['source_url']]=true; $fingerprints[$item['fingerprint']]=true; $key=$item['city_id'].':'.$item['category_id']; $counts[$key]=($counts[$key]??0)+1; }
$cities=array_column(cityList(),null,'id'); $categories=array_column(categories(),null,'id');
$accepted=[]; $skipped=[];
foreach($batch['records'] as $item) {
    try {
        foreach(['source_url','source_title','source_category','source_category_url','city','city_id','category_id','category_name','title','address','company','contact','phone','published_at','collected_at'] as $field) if(!isset($item[$field]) || !is_scalar($item[$field]) || trim((string)$item[$field])==='') throw new InvalidArgumentException('Missing '.$field);
        if(!preg_match('#^https://www\.baixiu\.org/(?:repair/[0-9]+|service/wx[0-9]+)\.html$#',$item['source_url'])) throw new InvalidArgumentException('Invalid source URL');
        if(!preg_match('#^https://www\.baixiu\.org/[a-z]+/wx-[0-9-]+\.html$#',$item['source_category_url'])) throw new InvalidArgumentException('Invalid source category URL');
        $city=$cities[(int)$item['city_id']]??null; $category=$categories[(int)$item['category_id']]??null;
        if(!$city || $city['name']!==$item['city'] || !$category || $category['name']!==$item['category_name'] || $category['depth']<2) throw new InvalidArgumentException('City/category mapping differs from current catalog');
        if(mb_strpos($item['address'],$city['name'])!==0) throw new InvalidArgumentException('Service area differs from city');
        foreach(['title'=>120,'source_title'=>120,'source_category'=>120,'address'=>255,'company'=>120,'contact'=>64,'phone'=>32] as $field=>$length) if(mb_strlen($item[$field])>$length || preg_match('/[<>\x00-\x1f]/u',$item[$field])) throw new InvalidArgumentException('Invalid '.$field);
        if(!preg_match('/^(?:(?:400|800)[- ]?\d{3,4}[- ]?\d{3,4}|1[3-9]\d{9}|0\d{2,3}[- ]?\d{7,8})$/D',$item['phone'])) throw new InvalidArgumentException('Invalid public business phone');
        $posted=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$item['published_at']);
        $collected=new DateTimeImmutable($item['collected_at']);
        if(!$posted || $posted->format('Y-m-d H:i:s')!==$item['published_at'] || $posted->getTimestamp()>$collected->getTimestamp()+300 || $collected->getTimestamp()>time()+300) throw new InvalidArgumentException('Invalid observed timestamps');
        if(preg_match('/aliyun_waf|aliyunwaf|acw_sc__v2/i',json_encode($item))) throw new InvalidArgumentException('Access verification content');
        $fingerprint=hash('sha256',implode('|',[$city['id'],$category['id'],preg_replace('/[^\p{L}\p{N}]/u','',$item['title']),preg_replace('/[^0-9]/','',$item['phone'])]));
        $key=$city['id'].':'.$category['id'];
        if(isset($urls[$item['source_url']]) || isset($fingerprints[$fingerprint])) { $skipped[]=['source_url'=>$item['source_url'],'reason'=>'duplicate']; continue; }
        if(($counts[$key]??0)>=$limit) { $skipped[]=['source_url'=>$item['source_url'],'reason'=>'city/category cap']; continue; }
        $counts[$key]=($counts[$key]??0)+1; $urls[$item['source_url']]=true; $fingerprints[$fingerprint]=true;
        $item['fingerprint']=$fingerprint; $item['collected_at']=$collected->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
        $districtName=preg_replace('/^[市\-—\s]+|\s+$/u','',mb_substr($item['address'],mb_strlen($city['name'])));
        $item['district_id']=scalar('SELECT id FROM xiu_districts WHERE city_id=? AND name=? AND enabled=1',[$city['id'],$districtName])?:null;
        $accepted[]=$item;
    } catch(Throwable $error) { $skipped[]=['source_url'=>$item['source_url']??null,'reason'=>$error->getMessage()]; }
}
$ready=[];
foreach($accepted as $item) {
    if(($counts[$item['city_id'].':'.$item['category_id']]??0)<5) $skipped[]=['source_url'=>$item['source_url'],'reason'=>'held until city/category has five valid records'];
    else $ready[]=$item;
}
$accepted=$ready;
$result=['mode'=>$apply?'apply':'dry-run','city_category_cap'=>$limit,'input_count'=>count($batch['records']),'accepted'=>count($accepted),'imported'=>0,'skipped'=>$skipped,'records'=>[]];
if($apply && $accepted) {
    $backup=ROOT.'/backup/'.date('Y-m-d').'-baixiu-real-import';
    if(!is_dir($backup)) mkdir($backup,0700,true);
    $backupFile=$backup.'/database-before-'.date('H-i-s').'-'.bin2hex(random_bytes(3)).'.sql';
    $stream=fopen($backupFile,'xb'); if(!$stream) throw new RuntimeException('Cannot create recovery backup');
    fwrite($stream,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach(rows('SHOW TABLES') as $tableRow) {
        $table=array_values($tableRow)[0]; if(!preg_match('/^xiu_[a-z_]+$/',$table)) continue;
        $create=row('SHOW CREATE TABLE `'.$table.'`'); fwrite($stream,'DROP TABLE IF EXISTS `'.$table."`;\n".$create['Create Table'].";\n");
        $statement=query('SELECT * FROM `'.$table.'`');
        while($record=$statement->fetch()) {
            $columns=implode(',',array_map(fn($name)=>'`'.$name.'`',array_keys($record)));
            $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
            if(fwrite($stream,'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n")===false) throw new RuntimeException('Recovery backup write failed');
        }
    }
    fwrite($stream,"SET FOREIGN_KEY_CHECKS=1;\n"); fclose($stream);
    $result['backup']=str_replace(ROOT.'/','',$backupFile);
    foreach(explode(';',file_get_contents(ROOT.'/database/import-schema.sql')) as $sql) if(trim($sql)) db()->exec(trim($sql));
    db()->beginTransaction();
    try {
        $username='baixiu_import'; $user=scalar('SELECT id FROM xiu_users WHERE username=?',[$username]);
        if(!$user) { query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',[$username,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]); $user=(int)db()->lastInsertId(); }
        if(!(bool)scalar('SELECT enabled FROM xiu_users WHERE id=?',[$user]) || scalar('SELECT id FROM xiu_user_verifications WHERE user_id=?',[$user])) throw new RuntimeException('Import owner must be active and have no identity certification');
        foreach($accepted as $item) {
            $body="服务项目：{$item['category_name']}。\n服务区域：{$item['address']}。\n服务方：{$item['company']}。\n源站联系人：{$item['contact']}。\n公开联系电话：{$item['phone']}。\n\n以上为源站公开业务字段整理，具体服务内容和费用请联系服务方确认。\n来源：{$item['source_url']}\n源站发布时间：{$item['published_at']}\n采集时间：{$item['collected_at']}";
            query("INSERT INTO xiu_listings (user_id,category_id,city_id,district_id,type,title,address,contact,phone,body,status,is_demo,created_at,updated_at) VALUES (?,?,?,?,'service',?,?,?,?,?,'published',0,?,?)",[$user,$item['category_id'],$item['city_id'],$item['district_id'],$item['title'],$item['address'],$item['company'],$item['phone'],$body,$item['published_at'],$item['published_at']]);
            $id=(int)db()->lastInsertId(); ensureSeoSlug('xiu_listings',$id);
            query('INSERT INTO xiu_listing_sources (listing_id,source_url,fingerprint,city_id,category_id,source_title,source_category,source_category_url,company,source_published_at,collected_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,$item['source_url'],$item['fingerprint'],$item['city_id'],$item['category_id'],$item['source_title'],$item['source_category'],$item['source_category_url'],$item['company'],$item['published_at'],$item['collected_at']]);
            $result['records'][]=['id'=>$id,'url'=>listingUrl(row('SELECT * FROM xiu_listings WHERE id=?',[$id])),'city'=>$item['city'],'category'=>$item['category_name'],'source_url'=>$item['source_url']];
        }
        db()->commit(); clearAdminListingCountCache(); $result['imported']=count($accepted);
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}
if($apply) {
    $report=ROOT.'/reports/imports/baixiu-browser/import-'.date('Y-m-d-His').'-'.bin2hex(random_bytes(3)).'.json';
    file_put_contents($report,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
    $result['report']=str_replace(ROOT.'/','',$report);
}
flock($lock,LOCK_UN); fclose($lock);
echo json_encode(array_diff_key($result,['skipped'=>true,'records'=>true])+['skipped_count'=>count($skipped),'skipped_reasons'=>array_count_values(array_column($skipped,'reason'))],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
