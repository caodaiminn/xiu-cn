<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/repair-guide-content.php';
$apply=in_array('--apply',$argv,true);
$catalog=categories();$map=array_column($catalog,null,'id');$canonical=[];$names=[];$sources=[];
foreach($catalog as $category) {
    $source=$category;
    if(str_starts_with($category['slug'],'ac-brand-')) $source=$map[(int)$category['parent_id']];
    if(isset($names[$source['name']])) $source=$map[$names[$source['name']]];
    else $names[$source['name']]=(int)$source['id'];
    $canonical[(int)$category['id']]=(int)$source['id'];$sources[(int)$source['id']]=$source;
}
$content=[];foreach($sources as $source) $content[(int)$source['id']]=buildRepairGuideContent($source,$map);
$report=['mode'=>$apply?'apply':'preview','cities'=>count(cityList()),'categories'=>count($catalog),'per_category'=>5,'unique_guides'=>count($sources)*5,'category_assignments'=>count($catalog)*5,'city_category_coverage'=>count(cityList())*count($catalog),'created'=>0,'assigned'=>0,'reused'=>0];
if(!$apply) {echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;exit;}
$lock=fopen(ROOT.'/storage/limits/repair-guides-import.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Guide import already running.');
$backup=ROOT.'/backup/'.date('Y-m-d').'-repair-guides-data/'.date('His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create guide backup.');
$beforeTables=[];
foreach(['xiu_repair_guides','xiu_repair_guide_categories'] as $table) if(scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table])) $beforeTables[$table]=rows('SELECT * FROM `'.$table.'`');
file_put_contents($backup.'/before.json',json_encode($beforeTables,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
db()->exec(file_get_contents(ROOT.'/database/repair-guides.sql'));
db()->beginTransaction();
try {
    $idMap=[];
    foreach($content as $categoryId=>$guides) foreach($guides as $guide) {
        $existing=row('SELECT * FROM xiu_repair_guides WHERE category_id=? AND slot=? FOR UPDATE',[$categoryId,$guide['slot']]);
        if($existing) {$id=(int)$existing['id'];$report['reused']++;}
        else {
            query('INSERT INTO xiu_repair_guides (category_id,slot,slug,title,summary,sections) VALUES (?,?,?,?,?,?)',[$categoryId,$guide['slot'],$guide['slug'],$guide['title'],$guide['summary'],json_encode($guide['sections'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $id=(int)db()->lastInsertId();$report['created']++;
        }
        $idMap[$categoryId.':'.$guide['slot']]=$id;
    }
    foreach($canonical as $category=>$source) for($slot=1;$slot<=5;$slot++) {
        $guideId=$idMap[$source.':'.$slot];
        $existing=scalar('SELECT guide_id FROM xiu_repair_guide_categories WHERE category_id=? AND slot=?',[$category,$slot]);
        if($existing && (int)$existing!==$guideId) throw new RuntimeException('Conflicting guide assignment.');
        if(!$existing) {query('INSERT INTO xiu_repair_guide_categories (category_id,slot,guide_id) VALUES (?,?,?)',[$category,$slot,$guideId]);$report['assigned']++;}
    }
    foreach($catalog as $category) if((int)scalar('SELECT COUNT(*) FROM xiu_repair_guide_categories WHERE category_id=?',[$category['id']])!==5) throw new RuntimeException('Incomplete category coverage.');
    db()->commit();invalidatePublicDataCache();$report['backup']=$backup;
    file_put_contents($backup.'/result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
} catch(Throwable $error) {if(db()->inTransaction()) db()->rollBack();throw $error;}
flock($lock,LOCK_UN);fclose($lock);
