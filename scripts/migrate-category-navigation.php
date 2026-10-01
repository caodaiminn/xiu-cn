<?php
if(PHP_SAPI!=='cli') exit;
require_once dirname(__DIR__).'/app/bootstrap.php';
$navigationLock=fopen(ROOT.'/storage/category-navigation-migration.lock','c');
if(!flock($navigationLock,LOCK_EX|LOCK_NB)) exit("Navigation migration already running.\n");
if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='xiu_categories' AND column_name='show_in_filter'")) db()->exec('ALTER TABLE xiu_categories ADD show_in_filter TINYINT NOT NULL DEFAULT 1');
db()->exec("CREATE TABLE IF NOT EXISTS xiu_districts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, city_id INT UNSIGNED NOT NULL,
 name VARCHAR(32) NOT NULL, seo_slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sort_order INT NOT NULL DEFAULT 0, enabled TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY (city_id) REFERENCES xiu_cities(id) ON DELETE RESTRICT,
 UNIQUE INDEX district_slug (city_id,seo_slug), INDEX district_navigation (city_id,enabled,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if(!scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='xiu_listings' AND column_name='district_id'")) db()->exec('ALTER TABLE xiu_listings ADD district_id INT UNSIGNED NULL DEFAULT NULL, ADD FOREIGN KEY (district_id) REFERENCES xiu_districts(id) ON DELETE RESTRICT, ADD INDEX listings_district (status,district_id,id)');
db()->beginTransaction();
try {
    if(!scalar("SELECT setting_value FROM xiu_settings WHERE setting_key='category_navigation_seeded'")) {
        $parent=(int)scalar("SELECT id FROM xiu_categories WHERE slug='life' AND kind='service'");
        $names=['空调清洗','瓷砖空鼓维修','电缆故障检测','门窗维修','管道疏通','化粪池、污水池清理','马桶疏通','下水道疏通','卫浴洁具/浴缸维修安装','智能马桶维修','市政管道清淤','水管维修/改造安装','水龙头维修/安装更换','管道检测修复/置换顶管','开锁换锁','保险柜维修','防盗门维修','指纹锁维修','沙发翻新护理','家具维修','家具/玻璃贴膜','电路维修检测/改造','灯具维修/安装','手表/名表维修','旧房翻新/装修设计','晾衣架维修','石材翻新护理/维修抛光打磨','眼镜维修','地板维修/打蜡','空调加氟清洗','保洁清洗','搬家服务','卷帘门/感应门/旋转门维修','咖啡机维修','麻将机维修','打孔/切割墙体','按摩椅维修','跑步机维修','健身器材/力量器械维修','瓷器修复','缝纫机维修','其它家政服务'];
        if($parent) foreach($names as $order=>$name) {
            if($existingId=scalar('SELECT id FROM xiu_categories WHERE parent_id=? AND name=?',[$parent,$name])) { query('UPDATE xiu_categories SET sort_order=? WHERE id=?',[$order,$existingId]); continue; }
            query("INSERT INTO xiu_categories (parent_id,kind,name,slug,sort_order) VALUES (?,'service',?,?,?)",[$parent,$name,'life-ref-'.substr(hash('sha256',$name),0,12),$order]);
            ensureSeoSlug('xiu_categories',(int)db()->lastInsertId());
        }
        if($parent) foreach(rows('SELECT id,name FROM xiu_categories WHERE parent_id=?',[$parent]) as $extra) if(!in_array($extra['name'],$names,true)) query('UPDATE xiu_categories SET sort_order=? WHERE id=?',[count($names)+1,$extra['id']]);
        $city=(int)scalar("SELECT id FROM xiu_cities WHERE slug='guangzhou'");
        if($city) foreach(['荔湾','越秀','海珠','天河','白云','黄埔','番禺','花都','增城','从化','南沙'] as $order=>$name) query('INSERT IGNORE INTO xiu_districts (city_id,name,seo_slug,sort_order) VALUES (?,?,?,?)',[$city,$name,pinyinSlug($name),$order]);
        query("INSERT INTO xiu_settings (setting_key,setting_value) VALUES ('category_navigation_seeded','1')");
    }
    db()->commit(); echo "Category link navigation and district schema ready.\n";
} catch(Throwable $exception) { if(db()->inTransaction()) db()->rollBack(); throw $exception; }
