<?php $directory=cityDirectory(); $total=array_sum(array_map(fn($group)=>count($group['cities']),$directory)); renderBreadcrumbs($seo); ?>
<div class="page-heading"><div><span class="eyebrow">选择所在地，查找当地服务</span><h1>全国城市选择</h1></div><a class="button button-outline" href="/change-city/?city=0">全国信息</a></div>
<section class="panel city-selector">
 <div class="city-selector-top">
  <div><h2>选择城市</h2><p>按省份浏览，或输入城市名称、拼音查找。</p></div>
  <div class="city-selector-search" data-city-search-controls hidden><label class="sr-only" for="city-search">查找城市</label><input type="search" id="city-search" placeholder="城市名称 / 拼音" autocomplete="off"><button type="button" class="button button-outline small" id="city-search-clear">清除</button></div>
 </div>
 <div class="city-selector-hot"><strong>热门城市</strong><nav aria-label="热门城市"><?php foreach(cityList() as $city): if($city['is_hot']): ?><a href="/change-city/?city=<?= (int)$city['id'] ?>"><?= e($city['name']) ?></a><?php endif; endforeach ?></nav></div>
 <div class="city-selector-index"><strong>按省份选择</strong><nav aria-label="省份快速定位"><?php foreach($directory as $group): ?><a href="#<?= e($group['anchor']) ?>"><?= e(preg_replace('/(壮族自治区|回族自治区|维吾尔自治区|自治区|特别行政区|省|市)$/u','',$group['name'])) ?></a><?php endforeach ?></nav></div>
 <div class="city-selector-summary"><span><?= count($directory) ?> 个省级地区 · <?= $total ?> 个城市及地区</span><span id="city-search-status" role="status" aria-live="polite"></span></div>
 <div class="city-selector-groups">
 <?php foreach($directory as $group): ?>
  <section class="city-selector-province" id="<?= e($group['anchor']) ?>" data-city-group>
   <h2><?= e($group['name']) ?></h2><nav aria-label="<?= e($group['name']) ?>城市">
   <?php foreach($group['cities'] as $city): $spelling=recordSlug($city); $initials=implode('',array_map(fn($word)=>$word[0]??'',explode('-',$spelling))); ?>
    <a href="/change-city/?city=<?= (int)$city['id'] ?>" data-city-item data-city-key="<?= e($city['name'].' '.$group['name'].' '.$spelling.' '.str_replace('-','',$spelling).' '.$initials) ?>"><?= e($city['name']) ?></a>
   <?php endforeach ?>
   </nav>
  </section>
 <?php endforeach ?>
 </div>
 <p class="city-selector-empty" id="city-search-empty" hidden>未找到匹配城市，请试试完整城市名称或拼音。</p>
 <?php if(!$directory): ?><p class="empty">暂时没有开通的城市。</p><?php endif ?>
</section>
