<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';

function acBrandPrefix(int $cityId,int $categoryId): string {
    $encode=static function(int $number): string {
        if($number<0 || $number>=26**3) throw new RuntimeException('Identifier exceeds short URL prefix size.');
        $text='';
        for($place=0;$place<3;$place++) { $text=chr(97+$number%26).$text; $number=intdiv($number,26); }
        return $text;
    };
    return 'ab'.$encode($cityId).$encode($categoryId);
}

// Eight illustrative entries per enabled city and catalog brand. No live business contacts.
function acBrandContent(string $city,string $brand,int $slot): array {
    $topics=[
        ['制冷不佳检查与维修','服务项目：检查滤网、换热器、风量、温度设置及制冷系统运行情况。适用于出风不凉、降温慢或制冷时断时续等现象。先核对设备型号、使用年限和故障表现，再安排现场检测；需要更换配件或处理制冷剂时，应说明检测依据及费用，确认后施工。'],
        ['室内机漏水与排水维修','服务项目：检查接水盘、排水管堵塞、排水坡度、保温及安装位置。适用于室内机滴水、墙面潮湿或排水不畅。先区分冷凝水与其他水源，清理疏通后进行排水验证；需要调整安装或更换管件时，提前沟通施工范围。'],
        ['异响与运行异常检修','服务项目：检查室内外风机、机壳固定、减震部件、电源连接和运行状态。适用于风扇异响、振动、频繁停机或异常提示。根据具体型号查阅故障说明，检测后提出维修方案，不仅凭错误代码直接判定配件损坏。'],
        ['清洁保养与使用检查','服务项目：清理滤网、检查换热器积尘、排水通畅情况和设备固定状态，适合换季使用前或长期运行后的保养需求。清洁前断电并保护周围物品；完成后检查出风、排水与运行噪声，说明日常滤网维护方式。深度拆洗范围需事先确认。'],
        ['不制冷求修需求','设备故障：空调可以启动，但使用一段时间后房间仍不凉，出风温度与往常不同。维修需求：希望检查风量、滤网、室外机及制冷系统，明确原因后报价维修。预约时可补充型号、安装年限、房间面积及最近是否做过清洁；希望先检测，再确认维修项目。'],
        ['无法开机求修需求','设备故障：接通电源后不能正常开机，可能出现指示灯闪烁或面板异常提示。维修需求：希望检查供电、控制线路及对应型号的故障提示。可补充遥控器状态、插座供电情况和故障代码照片；需要更换配件时，希望说明配件规格与费用。'],
        ['室内滴水求修需求','设备故障：制冷运行后室内机下方出现滴水，排水情况与以前不同，担心影响墙面和家具。维修需求：检查排水管、接水盘、保温及安装坡度，确认漏水原因并处理。预约时说明漏水位置、发生时间和是否近期移机，处理后希望验证排水恢复正常。'],
        ['噪声与停机求修需求','设备故障：运行时出现明显异响、室外机振动或不定时停机，影响日常使用。维修需求：希望检查风机、固定支架、电源及设备运行状态，确定维修范围。可补充噪声发生时段、设备位置及面板提示；涉及高处作业时，需提前确认现场条件与安排。'],
    ];
    [$topic,$description]=$topics[$slot-1];
    $type=$slot<=4?'service':'request';
    $title=$city.$brand.$topic;
    $area=$type==='service'?'服务范围':'需求地区';
    $body="设备品牌：{$brand}。\n信息主题：{$topic}。\n{$area}：{$city}，具体地址、上门范围及时间需另行沟通。\n\n{$description}\n\n沟通说明：请说明壁挂式、柜式或中央空调等设备类型及具体型号；故障原因以现场检查为准，费用和配件需双方确认。\n\n本条为页面展示用的示例信息，不代表真实商家或实际委托，未提供有效联系电话。";
    return [$type,$title,$body];
}

$apply=in_array('--apply',$argv,true);
$lock=fopen(ROOT.'/storage/limits/ac-brand-listings.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Brand listing import already running.');
$catalog=json_decode(file_get_contents(ROOT.'/app/data/air-conditioner-brands.json'),true,512,JSON_THROW_ON_ERROR);
$parent=row("SELECT id FROM xiu_categories WHERE kind='service' AND slug='air-conditioner' AND enabled=1");
if(!$parent) throw new RuntimeException('Enabled air-conditioner category not found.');
$brands=[];
foreach($catalog as $brand) {
    $category=row("SELECT id,name FROM xiu_categories WHERE parent_id=? AND kind='service' AND slug=? AND enabled=1",[$parent['id'],'ac-brand-'.$brand['slug']]);
    if(!$category || $category['name']!==$brand['name']) throw new RuntimeException('Expected enabled brand missing: '.$brand['name']);
    $brands[]=$category;
}
$cities=rows('SELECT id,name FROM xiu_cities WHERE enabled=1 ORDER BY id');
$ids=implode(',',array_map(fn($brand)=>(int)$brand['id'],$brands));
$journalExists=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_local_test_seeds'");
$slots=$journalExists?rows('SELECT city_id,category_id,slot FROM xiu_local_test_seeds WHERE category_id IN ('.$ids.') AND slot BETWEEN 1 AND 8'):[];
$existing=[];
foreach($slots as $entry) $existing[$entry['city_id'].':'.$entry['category_id'].':'.$entry['slot']]=true;
$missing=0;
foreach($cities as $city) foreach($brands as $brand) for($slot=1;$slot<=8;$slot++) if(!isset($existing[$city['id'].':'.$brand['id'].':'.$slot])) $missing++;
$report=['mode'=>$apply?'apply':'dry-run','cities'=>count($cities),'brands'=>count($brands),'per_pair'=>8,'target'=>count($cities)*count($brands)*8,'missing'=>$missing,'inserted'=>0,'started_at'=>date(DATE_ATOM)];
echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
if(!$apply || !$missing) exit;
$user=row("SELECT id,enabled FROM xiu_users WHERE username='local_test_seed'");
if(!$user || !$user['enabled'] || scalar('SELECT id FROM xiu_user_verifications WHERE user_id=?',[$user['id']])) throw new RuntimeException('An enabled, unverified sample owner is required.');

$backup=ROOT.'/backup/'.date('Y-m-d').'-air-conditioner-brand-listings/'.date('His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup directory.');
$report['original_listings']=(int)scalar('SELECT COUNT(*) FROM xiu_listings');
$stream=fopen($backup.'/database-before.sql','xb');
if(!$stream) throw new RuntimeException('Cannot create database backup.');
fwrite($stream,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach(rows('SHOW TABLES') as $tableRow) {
    $table=array_values($tableRow)[0];
    if(!preg_match('/^xiu_[a-z_]+$/',$table)) continue;
    $create=row('SHOW CREATE TABLE `'.$table.'`');
    if(fwrite($stream,'DROP TABLE IF EXISTS `'.$table."`;\n".$create['Create Table'].";\n")===false) throw new RuntimeException('Backup write failed.');
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    $statement=query('SELECT * FROM `'.$table.'`');
    while($record=$statement->fetch()) {
        $columns=implode(',',array_map(fn($name)=>'`'.$name.'`',array_keys($record)));
        $values=implode(',',array_map(fn($value)=>$value===null?'NULL':db()->quote((string)$value),array_values($record)));
        if(fwrite($stream,'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.$values.");\n")===false) throw new RuntimeException('Backup write failed.');
    }
    $statement->closeCursor(); db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
}
fwrite($stream,"SET FOREIGN_KEY_CHECKS=1;\n");
if(!fflush($stream) || !fclose($stream)) throw new RuntimeException('Backup flush failed.');
echo json_encode(['backup'=>$backup.'/database-before.sql'],JSON_UNESCAPED_UNICODE).PHP_EOL;
if(!$journalExists) foreach(explode(';',file_get_contents(ROOT.'/database/local-test-seed-schema.sql')) as $sql) if(trim($sql)) db()->exec(trim($sql));
$rollback=fopen($backup.'/rollback-added-listings.sql','xb');
if(!$rollback) throw new RuntimeException('Cannot create rollback file.');
fwrite($rollback,"-- Only removes exact IDs recorded by this run, with sample-owner and demo checks.\n");
$insert=db()->prepare("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,short_slug,seo_slug,address,contact,phone,body,status,is_demo,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'published',1,NOW(),NOW())");
$journal=db()->prepare("INSERT INTO xiu_local_test_seeds (listing_id,city_id,category_id,slot,seed_version) VALUES (?,?,?,?,'ac-brands-v1')");
$used=[];
foreach(rows('SELECT short_slug FROM xiu_listings WHERE short_slug IS NOT NULL') as $record) $used[$record['short_slug']]=true;
foreach($cities as $index=>$city) {
    $created=[];
    db()->beginTransaction();
    try {
        foreach($brands as $brand) for($slot=1;$slot<=8;$slot++) {
            if(isset($existing[$city['id'].':'.$brand['id'].':'.$slot])) continue;
            [$type,$title,$body]=acBrandContent($city['name'],$brand['name'],$slot);
            $prefix=acBrandPrefix((int)$city['id'],(int)$brand['id']);
            $attempt=0;
            do { $slug=$prefix.sprintf('%04d',random_int(0,9999)); if(++$attempt>1000) throw new RuntimeException('Short URL space exhausted.'); } while(isset($used[$slug]));
            $insert->execute([$user['id'],$brand['id'],$city['id'],$type,$title,$slug,$slug,$city['name'].'（具体地址待沟通）','示例信息','00000000000',$body]);
            $id=(int)db()->lastInsertId();
            $journal->execute([$id,$city['id'],$brand['id'],$slot]);
            $created[]=$id; $used[$slug]=true;
        }
        // Write recovery IDs before commit, so a stopped process remains recoverable.
        if($created && (fwrite($rollback,'DELETE FROM xiu_listings WHERE id IN ('.implode(',',$created).') AND user_id='.(int)$user['id']." AND is_demo=1;\n")===false || !fflush($rollback))) throw new RuntimeException('Recovery record write failed.');
        db()->commit();
        if($created) clearAdminListingCountCache();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
    $report['inserted']+=count($created); $report['completed_cities']=$index+1; $report['last_city']=$city['name'];
    if(file_put_contents($backup.'/report.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Cannot persist progress report.');
    if(($index+1)%25===0 || $index+1===count($cities)) echo json_encode(['cities_completed'=>$index+1,'inserted'=>$report['inserted'],'last_city'=>$city['name']],JSON_UNESCAPED_UNICODE).PHP_EOL;
}
fclose($rollback);
$report['finished_at']=date(DATE_ATOM);
$report['final_listings']=(int)scalar('SELECT COUNT(*) FROM xiu_listings');
file_put_contents($backup.'/report.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
echo json_encode(['complete'=>true,'inserted'=>$report['inserted'],'report'=>$backup.'/report.json'],JSON_UNESCAPED_UNICODE).PHP_EOL;
flock($lock,LOCK_UN); fclose($lock);
