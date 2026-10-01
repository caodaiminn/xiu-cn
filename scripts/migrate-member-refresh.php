<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
$refreshLock=fopen(ROOT.'/storage/member-refresh-migration.lock','c');
if(!flock($refreshLock,LOCK_EX|LOCK_NB)) exit("Refresh migration already running.\n");
foreach(explode(';',file_get_contents(ROOT.'/database/refresh-schema.sql')) as $statement) if(trim($statement)) db()->exec(trim($statement));
foreach(['refreshed_at'=>'DATETIME(6) NULL DEFAULT NULL','refresh_order'=>'BIGINT UNSIGNED NOT NULL DEFAULT 0'] as $column=>$definition) {
    if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='xiu_listings' AND column_name=?",[$column])) db()->exec('ALTER TABLE xiu_listings ADD '.$column.' '.$definition);
}
if(!scalar("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='xiu_listings' AND index_name='listings_refresh'")) db()->exec('ALTER TABLE xiu_listings ADD INDEX listings_refresh (status,refresh_order,id)');
flock($refreshLock,LOCK_UN); fclose($refreshLock);
echo "Daily listing refresh schema ready.\n";
