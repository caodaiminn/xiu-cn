<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/local-test-topics.php';
$apply=in_array('--apply',$argv,true);
$cities=cityList(); $categories=categories(); $catalog=array_column($categories,null,'id');
$countPerPair=5;
$exists=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_local_test_seeds'");
$already=$exists?(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds'):0;
$expected=count($cities)*count($categories)*$countPerPair;
$report=['mode'=>$apply?'apply':'dry-run','cities'=>count($cities),'categories'=>count($categories),'per_city_category'=>$countPerPair,'target_records'=>$expected,'existing_generated'=>$already,'inserted'=>0,'completed_cities'=>0,'started_at'=>date(DATE_ATOM)];
echo json_encode($report,JSON_UNESCAPED_UNICODE).PHP_EOL;
if(!$apply) exit;
$lock=fopen(ROOT.'/storage/limits/nationwide-test-seed.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('A test seed job is already running.');
if($exists) {
    $ids=implode(',',array_map('intval',array_keys($catalog)));
    $missing=(int)scalar('SELECT COUNT(*) FROM xiu_cities c CROSS JOIN xiu_categories k LEFT JOIN (SELECT city_id,category_id,COUNT(*) amount FROM xiu_local_test_seeds WHERE slot BETWEEN 1 AND 5 GROUP BY city_id,category_id) s ON s.city_id=c.id AND s.category_id=k.id WHERE c.enabled=1 AND k.id IN ('.$ids.') AND COALESCE(s.amount,0)<5');
    if($missing===0) { echo json_encode(['complete'=>true,'inserted'=>0,'reason'=>'all active city/category pairs already have five generated records'],JSON_UNESCAPED_UNICODE).PHP_EOL; flock($lock,LOCK_UN); fclose($lock); exit; }
}
if(!is_dir(ROOT.'/reports/seeds')) mkdir(ROOT.'/reports/seeds',0700,true);
$backup=ROOT.'/backup/2026-10-01-nationwide-test-info';
if(!is_dir($backup)) mkdir($backup,0700,true);
$backupFile=$backup.'/database-before-'.date('H-i-s').'-'.bin2hex(random_bytes(3)).'.sql';
$stream=fopen($backupFile,'xb'); if(!$stream) throw new RuntimeException('Cannot create database backup.');
fwrite($stream,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach(rows('SHOW TABLES') as $tableRow) {
    $table=array_values($tableRow)[0]; if(!preg_match('/^xiu_[a-z_]+$/',$table)) continue;
    $create=row('SHOW CREATE TABLE `'.$table.'`'); fwrite($stream,'DROP TABLE IF EXISTS `'.$table."`;\n".$create['Create Table'].";\n");
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $statement=query('SELECT * FROM `'.$table.'`');
    while($record=$statement->fetch()) {
        $columns=implode(',',array_map(fn($name)=>'`'.$name.'`',array_keys($record)));
        $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
        if(fwrite($stream,'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n")===false) throw new RuntimeException('Database backup write failed.');
    }
    $statement->closeCursor(); db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
}
fwrite($stream,"SET FOREIGN_KEY_CHECKS=1;\n"); fclose($stream);
$report['backup']=$backupFile;
foreach(explode(';',file_get_contents(ROOT.'/database/local-test-seed-schema.sql')) as $sql) if(trim($sql)) db()->exec(trim($sql));
$user=scalar("SELECT id FROM xiu_users WHERE username='local_test_seed'");
if(!$user) { query('INSERT INTO xiu_users (username,password_hash) VALUES (?,?)',['local_test_seed',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]); $user=(int)db()->lastInsertId(); }
if(!(bool)scalar('SELECT enabled FROM xiu_users WHERE id=?',[$user]) || scalar('SELECT id FROM xiu_user_verifications WHERE user_id=?',[$user])) throw new RuntimeException('Test owner must be active and unverified.');
$surnames=mb_str_split('赵钱孙李周吴郑王冯陈褚卫蒋沈韩杨朱秦尤许何吕施张孔曹严华金魏陶姜戚谢邹喻柏水窦章云苏潘葛奚范彭郎鲁韦昌马苗凤方俞任袁柳鲍史唐费廉岑薛雷贺倪汤滕殷罗毕郝安常于傅卞齐康伍余元顾孟平黄和穆萧尹姚邵汪祁毛禹狄米贝明臧计伏成戴宋茅庞熊纪舒屈项祝董梁杜阮蓝闵席季麻强贾路娄江童颜郭梅林钟徐邱骆高夏蔡田樊胡凌霍虞万支柯管卢莫经房裘缪干解应宗丁宣邓郁单杭洪包诸左石崔吉钮龚程嵇邢裴陆荣翁荀羊甄曲封芮储靳汲邴糜松井段富巫乌焦巴弓牧隗山谷车侯宓蓬全班仰秋仲伊宫宁仇栾甘厉戎祖武符刘景詹束龙叶幸司韶郜黎薄印宿白怀蒲邰鄂索咸籍赖卓蔺屠蒙池乔阴胥能苍双闻莘党翟谭贡劳逄姬申扶堵冉宰郦雍桑桂濮牛寿通边扈燕冀浦尚农温庄晏柴瞿阎连茹习艾鱼容向古易慎戈廖庾终暨居衡步都耿满弘匡国文寇广禄阙东殴殳沃利蔚越夔隆师巩厍聂晁勾敖融冷訾辛阚那简饶空曾毋沙乜养鞠须丰巢关蒯相查后荆红游竺权逯盖益桓公');
$usedSlugs=[];
foreach(rows('SELECT short_slug FROM xiu_listings WHERE short_slug IS NOT NULL') as $existing) $usedSlugs[$existing['short_slug']]=true;
$insert=db()->prepare("INSERT INTO xiu_listings (user_id,category_id,city_id,district_id,type,title,short_slug,seo_slug,address,contact,phone,body,status,is_demo,created_at,updated_at) VALUES (?,?,?,?,'service',?,?,?,?,?,?,?,'published',1,NOW(),NOW())");
$journal=db()->prepare('INSERT INTO xiu_local_test_seeds (listing_id,city_id,category_id,slot) VALUES (?,?,?,?)');
$reportFile=ROOT.'/reports/seeds/nationwide-test-'.date('Y-m-d-His').'-'.bin2hex(random_bytes(3)).'.json';
foreach($cities as $city) {
    $existingSlots=[];
    foreach(rows('SELECT category_id,slot FROM xiu_local_test_seeds WHERE city_id=?',[$city['id']]) as $slot) $existingSlots[$slot['category_id'].':'.$slot['slot']]=true;
    $districts=districtList((int)$city['id']);
    db()->beginTransaction();
    $cityInserted=0;
    try {
        foreach($categories as $category) {
            $topics=localTestTopics($category,$catalog);
            $spelling=pinyinSlug($city['name'].$category['label']);
            $letters=preg_replace('/[^a-z]/','',$spelling);
            $prefix=strlen($letters)<=8?$letters:implode('',array_map(fn($word)=>preg_replace('/[^a-z]/','',$word)[0]??'',explode('-',$spelling)));
            $prefix=substr($prefix?:'xinxi',0,8);
            for($slot=1;$slot<=$countPerPair;$slot++) {
                if(isset($existingSlots[$category['id'].':'.$slot])) continue;
                $topic=$topics[$slot-1];
                $title=preg_replace('/[^\p{L}\p{N}]/u','',$city['name'].$category['label'].$topic.'服务');
                $title=mb_substr($title,0,120);
                $attempt=0;
                do { $slug=$prefix.sprintf('%04d',random_int(0,9999)); if(++$attempt>1000) throw new RuntimeException('Short URL prefix is full: '.$prefix); } while(isset($usedSlugs[$slug]));
                $district=$districts?$districts[random_int(0,count($districts)-1)]:null;
                $address=$city['name'].($district['name']??'').'本地测试服务点';
                $contact=$surnames[random_int(0,count($surnames)-1)].'师傅';
                $phone='138'.sprintf('%08d',random_int(0,99999999));
                $body="【本地测试信息】以下内容、联系人和号码由系统生成，仅供本地展示，不代表真实商家，不提供真实服务。\n\n服务主题：{$topic}。\n所属分类：{$category['label']}。\n服务区域：{$city['name']}".($district['name']??'全市')."。\n\n本条信息围绕{$category['name']}中的{$topic}展开，可先沟通服务项目、具体需求和预约时间，再确认服务范围与安排。服务完成后核对约定项目和完成情况。\n\n联系人：{$contact}\n测试联系电话：{$phone}\n测试服务地址：{$address}";
                $insert->execute([$user,$category['id'],$city['id'],$district['id']??null,$title,$slug,$slug,$address,$contact,$phone,$body]);
                $id=(int)db()->lastInsertId();
                $journal->execute([$id,$city['id'],$category['id'],$slot]);
                $usedSlugs[$slug]=true; $cityInserted++;
            }
        }
        db()->commit(); clearAdminListingCountCache();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    $report['inserted']+=$cityInserted; $report['completed_cities']++;
    $report['last_city']=$city['name']; $report['updated_at']=date(DATE_ATOM);
    file_put_contents($reportFile,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
    if($report['completed_cities']%10===0 || $report['completed_cities']===count($cities)) echo json_encode(['completed_cities'=>$report['completed_cities'],'inserted'=>$report['inserted'],'last_city'=>$city['name']],JSON_UNESCAPED_UNICODE).PHP_EOL;
}
$report['finished_at']=date(DATE_ATOM); $report['generated_total']=(int)scalar('SELECT COUNT(*) FROM xiu_local_test_seeds');
file_put_contents($reportFile,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
echo json_encode(['complete'=>true,'inserted'=>$report['inserted'],'generated_total'=>$report['generated_total'],'report'=>$reportFile,'peak_memory_mb'=>round(memory_get_peak_usage(true)/1048576,1)],JSON_UNESCAPED_UNICODE).PHP_EOL;
flock($lock,LOCK_UN); fclose($lock);
