<?php
function repairGuidesAvailable(): bool {
    static $available;
    return $available??=(bool)scalar("SELECT COUNT(*)=2 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('xiu_repair_guides','xiu_repair_guide_categories')");
}
function repairGuideUrl(array $guide): string {return '/wei-xiu-zhi-nan/'.$guide['slug'].'.html';}
function repairGuidesForCategory(int $category): array {
    if(!$category || !repairGuidesAvailable() || !in_array($category,categoryIds('service'),true)) return [];
    return publicDataRemember('repair-guides',[$category],60,fn()=>rows('SELECT g.* FROM xiu_repair_guide_categories m JOIN xiu_repair_guides g ON g.id=m.guide_id WHERE m.category_id=? ORDER BY m.slot',[$category]));
}
function repairGuideEntries(): array {
    if(!repairGuidesAvailable()) return [];
    $ids=categoryIds('service');
    return rows('SELECT g.* FROM xiu_repair_guides g WHERE EXISTS (SELECT 1 FROM xiu_repair_guide_categories m WHERE m.guide_id=g.id AND m.category_id IN ('.implode(',',$ids?:[0]).')) ORDER BY g.id');
}
function repairGuideVisibleCategory(array $guide): ?array {
    $ids=categoryIds('service');
    if(in_array((int)$guide['category_id'],$ids,true)) return seoRecord('xiu_categories',(int)$guide['category_id']);
    $id=scalar('SELECT category_id FROM xiu_repair_guide_categories WHERE guide_id=? AND category_id IN ('.implode(',',$ids?:[0]).') ORDER BY category_id LIMIT 1',[$guide['id']]);
    return $id?seoRecord('xiu_categories',(int)$id):null;
}
function repairGuideController(string $path): bool {
    if($path!=='/wei-xiu-zhi-nan' && !str_starts_with($path,'/wei-xiu-zhi-nan/')) return false;
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) abortPage(405,'请使用阅读链接。');
    if(!repairGuidesAvailable()) abortPage(404,'指南尚未开放。');
    if($path==='/wei-xiu-zhi-nan') {
        enforceCanonical('/wei-xiu-zhi-nan/');
        $groups=[];
        foreach(categories() as $category) {
            $guides=repairGuidesForCategory((int)$category['id']);
            if($guides) $groups[]=['category'=>$category,'guides'=>$guides];
        }
        render('repair-guide-index',['title'=>'维修与服务指南','guideGroups'=>$groups]);return true;
    }
    if(!preg_match('#^/wei-xiu-zhi-nan/([a-z0-9-]{1,120})\.html$#',$path,$match)) abortPage(404,'指南不存在。');
    $guide=row('SELECT g.*,c.name category_name FROM xiu_repair_guides g JOIN xiu_categories c ON c.id=g.category_id WHERE g.slug=?',[$match[1]]);
    if(!$guide || !($visibleCategory=repairGuideVisibleCategory($guide))) abortPage(404,'指南不存在或分类已停用。');
    $guide['category_id']=$visibleCategory['id'];$guide['category_name']=$visibleCategory['name'];
    enforceCanonical(repairGuideUrl($guide));
    $sections=json_decode($guide['sections'],true,512,JSON_THROW_ON_ERROR);
    render('repair-guide',['title'=>$guide['title'],'guide'=>$guide,'guideSections'=>$sections,'guideRelated'=>repairGuidesForCategory((int)$guide['category_id'])]);return true;
}
