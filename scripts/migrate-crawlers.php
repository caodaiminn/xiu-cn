<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$existing=rows("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('xiu_crawler_visits','xiu_crawler_tracking')");
$backup=ROOT.'/backup/'.date('Y-m-d').'-crawler-dashboard/migration-'.date('His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) throw new RuntimeException('Migration backup directory failed.');
$snapshot=[];
foreach($existing as $table) { $name=$table['table_name']; $snapshot[$name]=['schema'=>row('SHOW CREATE TABLE '.$name),'rows'=>$name==='xiu_crawler_tracking'?rows('SELECT * FROM '.$name):null]; }
if(file_put_contents($backup.'/crawler-tables-before.json',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Crawler backup failed.');
foreach(explode(';',file_get_contents(ROOT.'/database/crawler-schema.sql')) as $statement) if(trim($statement)) db()->exec(trim($statement));
query('INSERT IGNORE INTO xiu_crawler_tracking (id,started_at) VALUES (1,?)',[date('Y-m-d H:i:s')]);
echo json_encode(['ready'=>true,'started_at'=>scalar('SELECT started_at FROM xiu_crawler_tracking WHERE id=1'),'backup'=>$backup],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
