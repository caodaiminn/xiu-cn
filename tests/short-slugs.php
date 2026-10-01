<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
function verify(bool $condition,string $label): void { if(!$condition) throw new RuntimeException($label); }
verify(pinyinSlug('重庆空调维修')==='chong-qing-kong-tiao-wei-xiu','Context-aware pinyin');
$before=[]; foreach(['xiu_listings','xiu_articles'] as $table) $before[$table]=rows('SELECT id,short_slug FROM '.$table.' ORDER BY id');
db()->beginTransaction();
try {
    $template=row('SELECT * FROM xiu_listings ORDER BY id LIMIT 1'); $slugs=[]; $lastId=0;
    for($i=0;$i<400;$i++) {
        query("INSERT INTO xiu_listings (user_id,category_id,city_id,type,title,address,contact,phone,body,status) VALUES (?,?,?,'service','重庆空调维修','transaction test','test','000','Rollback-only collision test','pending')",[$template['user_id'],$template['category_id'],$template['city_id']]);
        $lastId=(int)db()->lastInsertId(); ensureSeoSlug('xiu_listings',$lastId);
        $slug=(string)scalar('SELECT short_slug FROM xiu_listings WHERE id=?',[$lastId]);
        verify(strlen($slug)<=12 && (bool)preg_match('/^[a-z]{1,8}[0-9]{4}$/',$slug),'Short slug format');
        verify(!in_array($slug,$slugs,true),'Collision retries'); $slugs[]=$slug;
    }
    query('UPDATE xiu_listings SET title=? WHERE id=?',['重命名后地址不改变',$lastId]); ensureSeoSlug('xiu_listings',$lastId);
    verify(scalar('SELECT short_slug FROM xiu_listings WHERE id=?',[$lastId])===end($slugs),'Stable after title edit');
    $blocked=false; try { query('UPDATE xiu_listings SET short_slug=? WHERE id=?',[$slugs[0],$lastId]); } catch(PDOException $exception) { $blocked=(int)($exception->errorInfo[1]??0)===1062; }
    verify($blocked,'Database uniqueness guard');
} finally { db()->rollBack(); }
foreach($before as $table=>$records) verify(rows('SELECT id,short_slug FROM '.$table.' ORDER BY id')===$records,'Test leaves no persisted data');
echo "SUCCESS: 400 identical titles, unique short URLs, stable edit, rollback and database uniqueness.\n";
