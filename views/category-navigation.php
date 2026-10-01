<?php $categoryBase=array_merge($filters,['category'=>$navigation['root']]); ?>
<section class="category-filter" aria-label="分类与区域导航">
 <div class="category-filter-breadcrumb"><?php renderBreadcrumbs($seo) ?></div>
 <div class="category-filter-row category-filter-types">
  <strong class="category-filter-label">类别：</strong>
  <nav class="category-filter-links" id="service-category-links" aria-label="维修类别">
   <a href="<?= e(listUrl($categoryBase)) ?>" <?= (int)$filters['category']===$navigation['root']?'class="current" aria-current="page"':'' ?>>不限</a>
   <?php foreach($navigation['items'] as $option): ?><a href="<?= e(listUrl(array_merge($filters,['category'=>$option['id']]))) ?>" <?= (int)$filters['category']===(int)$option['id']?'class="current" aria-current="page"':'' ?>><?= e($option['name']) ?></a><?php endforeach ?>
  </nav>
  <button class="category-filter-toggle" type="button" aria-expanded="true" aria-controls="service-category-links" title="收起类别" hidden><span aria-hidden="true">⌃</span><span class="sr-only">收起类别</span></button>
 </div>
 <div class="category-filter-row category-filter-region">
  <strong class="category-filter-label">区域：</strong>
  <nav class="category-filter-links" aria-label="服务区域">
   <a href="<?= e(listUrl(array_merge($filters,['district'=>0]))) ?>" <?= !$filters['district']?'class="current" aria-current="page"':'' ?>><?= $city?'全'.e($city['name']):'全国' ?></a>
   <?php if($city): foreach(districtList((int)$city['id']) as $option): ?><a href="<?= e(listUrl(array_merge($filters,['district'=>$option['id']]))) ?>" <?= (int)$filters['district']===(int)$option['id']?'class="current" aria-current="page"':'' ?>><?= e($option['name']) ?></a><?php endforeach; else: foreach(array_slice(array_values(array_filter(cityList(),fn($item)=>$item['is_hot'])),0,12) as $option): ?><a href="<?= e(listUrl(array_merge($filters,['city'=>$option['id'],'district'=>0]))) ?>"><?= e($option['name']) ?></a><?php endforeach ?><a href="<?= e(publicPath('cities')) ?>">全部城市</a><?php endif ?>
  </nav>
 </div>
</section>
