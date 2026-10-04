<?php
function repairCategoryLandingIds(): array {
    if(!repairGuidesAvailable()) return [];
    return publicDataRemember('repair-category-landings',[],60,function() {
        $active=categoryIds('service');
        $mapped=array_map('intval',array_column(rows('SELECT category_id FROM xiu_repair_guide_categories GROUP BY category_id HAVING COUNT(*)=5'),'category_id'));
        return array_values(array_intersect($active,$mapped));
    });
}
function repairCategoryLandingEligible(array $filters,int $page=1): bool {
    return $page===1 && empty($filters['q']) && empty($filters['type']) && empty($filters['district']) && in_array((int)($filters['category']??0),repairCategoryLandingIds(),true);
}
function repairCategoryLandingContent(array $filters,?array $city,int $total): ?array {
    if(!repairCategoryLandingEligible($filters)) return null;
    require_once ROOT.'/app/repair-guide-content.php';
    $catalog=array_column(categories(),null,'id');$category=$catalog[(int)$filters['category']];
    $profiles=repairGuideProfiles();$key=repairGuideProfileKey($category,$catalog);$profile=$profiles[$key];
    $name=$category['name'];$subject=($city['name']??'全国').$name;
    $children=array_values(array_filter($catalog,fn($item)=>(int)$item['parent_id']===(int)$category['id']));
    $focus=repairGuideFocus($category);
    if(in_array($name,['陪练车','陪驾公司'],true)) {
        $profile['prepare']=['驾驶资格、车辆授权与保险情况','路线、训练目标、车辆状态和交接地点','服务时长、陪同人员及紧急联络安排'];
        $profile['risk']='驾驶和训练需遵守交通规则，先核对驾驶资格、车辆授权与保险；天气、身体状态或道路条件不适合时调整安排。';
    }
    $intro='本页汇总'.$subject.'的公开服务与求修信息，并整理项目确认、预约准备、费用核对和交接要点。选择服务方时，应把具体对象、问题部位与希望完成的项目说明清楚；上门范围、到场时间及费用需根据实际地址和现场条件确认。';
    $status=$total>0?'当前筛选条件下有 '.$total.' 条可浏览信息。可先查看各条信息的具体介绍，再核对联系人、项目范围和预约条件。':'当前分类暂无匹配的公开信息。可以先阅读下面的说明，整理问题与现场条件，再发布需求或切换相近分类；服务是否可预约以服务方实际回复为准。';
    $sections=[
        ['heading'=>$name.'需要先确认哪些问题','paragraphs'=>['先区分问题表现、发生条件与希望完成的工作。不要把未经检查的猜测直接写成故障结论；同一现场涉及多个部位或设备时，分别记录影响范围。'],'items'=>array_merge($profile['observe'],$focus)],
        ['heading'=>'预约前准备的信息','paragraphs'=>['联系前准备以下资料，并说明所在区域、楼层、电梯、停车及可进场时间。涉及物业、公共部位、邻接空间或停机安排时，应先取得相关人员的确认。'],'items'=>$profile['prepare']],
        ['heading'=>'报价和服务范围如何核对','paragraphs'=>['报价应能对应检测、人工、材料和附加项目，写清计费单位、数量及包含范围。现场新增项目先说明原因、方案和费用，经双方确认后再处理；不要只比较一个未说明条件的总价。'],'items'=>$profile['quote']],
        ['heading'=>'完成后的验收与维护','paragraphs'=>['根据原问题和约定项目复核效果，记下测试条件、未完成事项及后续观察时间。保留配件或材料信息、维修凭据和双方约定的保障范围，复发时补充新的现象和时间记录。'],'items'=>array_merge($profile['accept'],[$profile['care'][0]])],
    ];
    return ['category'=>$category,'subject'=>$subject,'intro'=>$intro,'status'=>$status,'region'=>$city?($city['province'].' / '.$city['name']):'全国城市可切换','sections'=>$sections,'children'=>$children,'safety'=>$profile['risk']];
}
function repairCategoryLandingSitemap(int $part=0): array {
    $ids=repairCategoryLandingIds();$cities=cityList();$perCity=count($ids);$count=$perCity*count($cities);
    if(!$part) return ['count'=>$count];
    $entries=[];
    for($index=($part-1)*1000;$index<min($count,$part*1000);$index++) {
        $city=$cities[intdiv($index,$perCity)];$category=$ids[$index%$perCity];
        $entries[listUrl(['city'=>$city['id'],'category'=>$category])]=null;
    }
    return $entries;
}
