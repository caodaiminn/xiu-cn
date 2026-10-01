<?php
if(PHP_SAPI!=='cli') exit;
require_once dirname(__DIR__).'/app/bootstrap.php';
$columns=array_column(rows("SELECT column_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='xiu_listings' AND index_name='listings_category_filter' ORDER BY seq_in_index"),'column_name');
$expected=['status','category_id','city_id','type','user_id','district_id','refresh_order','id'];
if($columns!==$expected) {
    if($columns) db()->exec('ALTER TABLE xiu_listings DROP INDEX listings_category_filter, ADD INDEX listings_category_filter (status,category_id,city_id,type,user_id,district_id,refresh_order,id)');
    else db()->exec('ALTER TABLE xiu_listings ADD INDEX listings_category_filter (status,category_id,city_id,type,user_id,district_id,refresh_order,id)');
}
echo "Category listing index ready.\n";
