<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
$lock=fopen(ROOT.'/storage/city-directory.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('City import already running.');
if(scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='nationwide_cities_seeded'")) { echo "Nationwide cities already initialized; no data changed.\n"; return; }
$data=json_decode(file_get_contents(ROOT.'/app/data/nationwide-cities.json'),true,512,JSON_THROW_ON_ERROR);
$backup=ROOT.'/backup/2026-10-01-city-selector';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup directory.');
if(!is_file($backup.'/cities-before.json')) {
    $snapshot=json_encode(['cities'=>rows('SELECT * FROM xiu_cities ORDER BY id'),'settings'=>rows('SELECT * FROM xiu_settings ORDER BY setting_key')],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(file_put_contents($backup.'/cities-before.json',$snapshot,LOCK_EX)===false) throw new RuntimeException('City backup failed.');
}
$added=0;
db()->beginTransaction();
try {
    foreach($data['cities'] as $order=>$city) {
        // Preserve existing IDs, URLs, popular flags, ordering and disabled cities.
        if(scalar('SELECT id FROM xiu_cities WHERE province=? AND name=?',[$city['province'],$city['name']])) continue;
        $slug=pinyinSlug($city['name']);
        if(preg_match('/(?:^|-)u[0-9a-f]+(?:-|$)/',$slug)) throw new RuntimeException('Missing city pinyin: '.$city['name']);
        if(strlen($slug)>70) $slug=rtrim(substr($slug,0,70),'-');
        $base=$slug;
        while(scalar('SELECT id FROM xiu_cities WHERE slug=?',[$slug])) $slug=$base.'-'.bin2hex(random_bytes(3));
        query('INSERT INTO xiu_cities (province,name,slug,is_hot,sort_order,enabled) VALUES (?,?,?,0,?,1)',[$city['province'],$city['name'],$slug,1000+$order]);
        ensureSeoSlug('xiu_cities',(int)db()->lastInsertId()); $added++;
    }
    query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('nationwide_cities_seeded','1')");
    db()->commit(); echo 'Added '.$added.' city/region entries; existing cities preserved.'.PHP_EOL;
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
