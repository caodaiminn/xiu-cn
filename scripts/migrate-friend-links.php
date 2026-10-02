<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$existing=(bool)scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_friend_links'");
$backup=ROOT.'/backup/'.date('Y-m-d').'-friend-links/migration-'.date('His').'-'.bin2hex(random_bytes(3));
if(!mkdir($backup,0700,true)) throw new RuntimeException('Friend links backup directory failed.');
if(file_put_contents($backup.'/before.json',json_encode(['exists'=>$existing,'schema'=>$existing?row('SHOW CREATE TABLE xiu_friend_links'):null,'records'=>$existing?rows('SELECT * FROM xiu_friend_links ORDER BY id'):[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Friend links backup failed.');
db()->exec(file_get_contents(ROOT.'/database/friend-links.sql'));
echo json_encode(['ready'=>true,'links'=>(int)scalar('SELECT COUNT(*) FROM xiu_friend_links'),'backup'=>$backup],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
