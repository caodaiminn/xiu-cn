<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(ROOT.'/storage/limits/navigation-migration.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit("Navigation migration already running.\n");
try {
    foreach(explode(';',file_get_contents(ROOT.'/database/navigation-schema.sql')) as $sql) if(trim($sql)) db()->exec(trim($sql));
    db()->beginTransaction();
    if(!scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='navigation_seeded'")) {
        if(!(int)scalar('SELECT COUNT(*) FROM xiu_navigation')) {
            foreach(array_slice(navigationPresets(),0,5) as $index=>$item) query('INSERT INTO xiu_navigation (label,url,sort_order,match_mode) VALUES (?,?,?,?)',[$item['label'],$item['url'],($index+1)*10,$item['match_mode']]);
        }
        query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('navigation_seeded','1')");
    }
    db()->commit();
} catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
finally { flock($lock,LOCK_UN); fclose($lock); }
echo "Navigation schema ready; existing navigation retained.\n";
