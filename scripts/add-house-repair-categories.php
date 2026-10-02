<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$catalog=json_decode(file_get_contents(ROOT.'/app/data/house-repair-categories.json'),true,512,JSON_THROW_ON_ERROR);
$apply=in_array('--apply',$argv,true);
$lock=fopen(ROOT.'/storage/limits/house-repair-categories.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('House category import already running.');
$created=[]; $reused=[]; $items=[];
try {
    db()->beginTransaction();
    $parents=rows("SELECT * FROM xiu_categories WHERE kind='service' AND (slug='house' OR name='房屋、防水维修') FOR UPDATE");
    if(count($parents)!==1 || $parents[0]['parent_id']!==null || !$parents[0]['enabled']) throw new RuntimeException('Enabled house repair root is missing or ambiguous.');
    $parent=$parents[0];
    $before=rows('SELECT * FROM xiu_categories ORDER BY id');
    $backup=null;
    if($apply) {
        $backup=ROOT.'/backup/'.date('Y-m-d').'-house-repair-categories/'.date('His').'-'.bin2hex(random_bytes(3));
        if(!mkdir($backup,0700,true) || file_put_contents($backup.'/categories-before.json',json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Category backup failed.');
    }
    $order=(int)scalar('SELECT COALESCE(MAX(sort_order),-1)+1 FROM xiu_categories WHERE parent_id=?',[$parent['id']]);
    foreach($catalog as $entry) {
        if(!isset($entry['name'],$entry['slug']) || !preg_match('/^[a-z][a-z0-9-]{0,79}$/',$entry['slug'])) throw new RuntimeException('Invalid house category catalog.');
        $names=[$entry['name'],...($entry['existing_names']??[])];
        $matches=rows("SELECT * FROM xiu_categories WHERE parent_id=? AND kind='service' AND name IN (".implode(',',array_fill(0,count($names),'?')).')',[$parent['id'],...$names]);
        if(count($matches)>1) throw new RuntimeException('Existing category is ambiguous: '.$entry['name']);
        if($matches) {
            $item=$matches[0]; $reused[]=$item['name'];
        } else {
            $slug='house-repair-'.$entry['slug']; $seo=$entry['slug'];
            if(scalar('SELECT id FROM xiu_categories WHERE slug=?',[$slug])) throw new RuntimeException('Category slug already in use.');
            if(scalar("SELECT id FROM xiu_categories WHERE kind='service' AND seo_slug=?",[$seo])) $seo='fang-wu-'.$seo;
            if(scalar("SELECT id FROM xiu_categories WHERE kind='service' AND seo_slug=?",[$seo])) throw new RuntimeException('Category public slug already in use.');
            if($apply) {
                query("INSERT INTO xiu_categories (parent_id,kind,name,slug,seo_slug,sort_order,enabled,show_in_filter) VALUES (?,'service',?,?,?,?,1,1)",[$parent['id'],$entry['name'],$slug,$seo,$order++]);
                $id=(int)db()->lastInsertId(); $created[]=$id;
                $item=row('SELECT * FROM xiu_categories WHERE id=?',[$id]);
            } else $item=['id'=>null,'name'=>$entry['name'],'parent_id'=>$parent['id'],'seo_slug'=>$seo,'enabled'=>1,'show_in_filter'=>1];
        }
        $items[]=['requested'=>$entry['name'],'id'=>$item['id'],'name'=>$item['name'],'parent_id'=>$item['parent_id'],'enabled'=>$item['enabled'],'show_in_filter'=>$item['show_in_filter'],'url'=>$item['id']?listUrl(['category'=>$item['id']]):null];
    }
    $report=['mode'=>$apply?'apply':'preview','requested'=>count($catalog),'created'=>count($created),'missing'=>$apply?0:count($catalog)-count($reused),'reused'=>$reused,'parent_id'=>(int)$parent['id'],'backup'=>$backup,'items'=>$items];
    if($apply) {
        if($created) {
            $rollback='-- Only removes the exact newly added category IDs; foreign-key references are protected.'.PHP_EOL.'DELETE FROM xiu_categories WHERE id IN ('.implode(',',$created).') AND parent_id='.(int)$parent['id'].';'.PHP_EOL;
            if(file_put_contents($backup.'/rollback-added-categories.sql',$rollback,LOCK_EX)===false) throw new RuntimeException('Rollback record failed.');
        }
        if(file_put_contents($backup.'/report.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Import report failed.');
        db()->commit();
    } else db()->rollBack();
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
finally { flock($lock,LOCK_UN); fclose($lock); }
