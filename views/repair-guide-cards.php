<?php if(($result['page']??1)===1 && empty($filters['q']) && repairGuidesAvailable()):
$guideCategory=!empty($filters['category'])?seoRecord('xiu_categories',(int)$filters['category']):null;
$guideCards=$guideCategory?repairGuidesForCategory((int)$guideCategory['id']):[];
?>
<section class="panel repair-guides-panel repair-guides-compact" aria-label="维修与服务指南">
 <div class="section-heading"><h2><?= e(($city['name']??'').($guideCategory['name']??'维修与服务').'指南') ?></h2><a class="accent-link" href="/wei-xiu-zhi-nan/">全部指南</a></div>
 <?php if($guideCards): ?><p class="muted">报修前可参考以下 <?= count($guideCards) ?> 篇指南，服务范围及费用请与服务方确认。</p><div class="repair-guide-cards">
 <?php foreach($guideCards as $guideCard): ?><a class="repair-guide-card" href="<?= e(repairGuideUrl($guideCard)) ?>"><span>指南 <?= (int)$guideCard['slot'] ?></span><h3><?= e($guideCard['title']) ?></h3><p><?= e($guideCard['summary']) ?></p><strong>阅读 →</strong></a><?php endforeach ?>
 </div><?php else: ?><p class="muted">选择维修分类，查看问题记录、服务预约、报价核对、验收和日常维护指南。</p><div class="city-tags"><?php foreach(categories() as $guideRoot): if($guideRoot['depth']!==1) continue; ?><a href="<?= e(listUrl(['category'=>$guideRoot['id'],'city'=>$city['id']??0])) ?>"><?= e($guideRoot['name']) ?></a><?php endforeach ?></div><?php endif ?>
</section>
<?php endif ?>
