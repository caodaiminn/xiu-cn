<?php
/** Nationwide service areas confirmed by the site owner during workbook import. */
function nationwideListingIds(): array {
    return publicDataRemember('nationwide-listing-ids',[],60,function() {
        if(!scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='xiu_workbook_imports'")) return [];
        return array_map('intval',array_column(rows('SELECT listing_id FROM xiu_workbook_imports WHERE nationwide=1'),'listing_id'));
    });
}
function listingWithServiceScope(array $item): array {
    $item['is_nationwide']=in_array((int)$item['id'],nationwideListingIds(),true);
    $item['service_city_id']=$item['is_nationwide']?0:(int)$item['city_id'];
    if($item['is_nationwide']) { $item['city_name']='全国';$item['city_slug']='';$item['district_name']=null; }
    return $item;
}
function nationwideListingCategoryIds(): array {
    return publicDataRemember('nationwide-listing-categories',[],60,function() {
        $ids=nationwideListingIds();if(!$ids) return [];
        $active=categoryIds('service');$result=[];
        $records=rows("SELECT DISTINCT l.category_id FROM xiu_listings l JOIN xiu_users u ON u.id=l.user_id WHERE l.id IN (".implode(',',$ids).") AND l.status='published' AND l.is_demo=0 AND u.enabled=1 AND l.category_id IN (".implode(',',$active?:[0]).')');
        foreach($records as $item) $result=array_merge($result,categoryAncestors((int)$item['category_id']));
        return array_values(array_intersect($active,array_unique($result)));
    });
}
