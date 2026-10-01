<?php
if(PHP_SAPI!=='cli') exit;
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/pinyin.php';
$seoLock=fopen(ROOT.'/storage/seo-migration.lock','c');
if(!flock($seoLock,LOCK_EX|LOCK_NB)) exit("SEO migration is already running.\n");
$tables=['xiu_categories','xiu_cities','xiu_listings','xiu_articles'];
foreach($tables as $table) {
    if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='seo_slug'",[$table])) db()->exec('ALTER TABLE '.$table." ADD seo_slug VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', ADD INDEX seo_slug_lookup (seo_slug)");
}
foreach(['xiu_listings','xiu_articles'] as $table) {
    if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='short_slug'",[$table])) db()->exec('ALTER TABLE '.$table.' ADD short_slug VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL, ADD UNIQUE INDEX short_slug_unique (short_slug)');
}
db()->beginTransaction();
try {
    foreach($tables as $table) {
        $condition=in_array($table,['xiu_listings','xiu_articles'],true)?"seo_slug='' OR short_slug IS NULL":"seo_slug=''";
        foreach(rows('SELECT id FROM '.$table.' WHERE '.$condition.' ORDER BY id') as $record) ensureSeoSlug($table,(int)$record['id']);
    }
    query("INSERT IGNORE INTO xiu_settings (setting_key,setting_value) VALUES ('site_url','http://www.xiu.cn')");
    query("INSERT IGNORE INTO xiu_settings (setting_key,setting_value) VALUES ('seo_migrated','1')");
    db()->commit(); echo "SEO schema and stable pinyin/short slugs initialized.\n";
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
