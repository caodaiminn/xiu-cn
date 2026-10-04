<?php
renderBreadcrumbs($seo);
$region=$item['city_name'].($item['district_name']??'');
$phoneTarget='tel:'.preg_replace('/[^0-9+]/','',$item['phone']);
$phoneMarkup=function(string $class) use ($item,$phoneTarget): string {
    $text=e($item['phone']); $class=e($class);
    return $item['is_demo']?'<span class="'.$class.'">'.$text.'</span>':'<a class="'.$class.'" href="'.e($phoneTarget).'">'.$text.'</a>';
};
$context=['city'=>$item['service_city_id']??$item['city_id'],'district'=>$item['district_id']??0,'category'=>$item['category_id']];
$relatedItems=array_values(array_filter($related['items'],fn($relatedItem)=>(int)$relatedItem['id']!==(int)$item['id']));
?>
<?php if(!visibleListing($item)): ?><div class="notice">此信息目前<?= statusLabel($item['status']) ?>，仅发布者或管理员可预览。<?php if($item['rejection_reason']): ?> 驳回原因：<?= e($item['rejection_reason']) ?><?php endif ?></div><?php endif ?>
<div class="repair-detail-layout">
 <article class="repair-information">
  <header class="repair-information-header"><h1><?= e($item['title']) ?></h1><div class="repair-information-meta"><span><?= listingType($item['type']) ?></span><span>信息编号：<?= (int)$item['id'] ?></span><span>发布时间：<time datetime="<?= e(date(DATE_ATOM,strtotime($item['created_at']))) ?>"><?= e($item['created_at']) ?></time></span><span>更新时间：<time datetime="<?= e(date(DATE_ATOM,strtotime($item['updated_at']))) ?>"><?= e(substr($item['updated_at'],0,10)) ?></time></span></div></header>
  <section class="repair-basic" aria-labelledby="repair-basic-title"><h2 id="repair-basic-title" class="sr-only">基本信息与联系方式</h2><dl class="repair-facts">
   <div><dt>联系人：</dt><dd><?= e($item['contact']) ?><?php require ROOT.'/views/verification-badge.php'; ?></dd></div>
   <div><dt>联系电话：</dt><dd><?= $phoneMarkup('repair-primary-phone') ?></dd></div>
   <div><dt>维修分类：</dt><dd><a href="<?= e(listUrl($context)) ?>"><?= e($item['category_name']) ?></a></dd></div>
   <div><dt>服务区域：</dt><dd><a href="<?= e(listUrl(['city'=>$context['city'],'district'=>$item['district_id']??0])) ?>"><?= e($region) ?></a></dd></div>
   <div class="repair-fact-address"><dt><?= $item['type']==='request'?'需求地址':'服务地址' ?>：</dt><dd><?= e($item['address']) ?></dd></div>
  </dl><p class="repair-contact-tip">联系时请说明是在<?= e(siteName()) ?>看到此信息。</p></section>
  <nav class="repair-section-nav" aria-label="信息详情栏目"><a href="#repair-description"><?= $item['type']==='request'?'需求详情':'服务介绍' ?></a><?php if($images): ?><a href="#repair-images">图片展示</a><?php endif ?><a href="#repair-contact">联系方式</a></nav>
  <section id="repair-description" class="repair-description"><h2><?= $item['type']==='request'?'需求详情':'服务介绍' ?></h2><div class="prose"><?= nl2br(e($item['body'])) ?></div></section>
  <?php if($images): ?><section id="repair-images" class="repair-images"><h2>图片展示</h2><div class="repair-image-gallery"><?php foreach($images as $number=>$image): ?><a href="/media/<?= (int)$image['id'] ?>/" target="_blank" rel="noopener"><img src="/media/<?= (int)$image['id'] ?>/" alt="<?= e($item['title']) ?>，附图<?= $number+1 ?>" width="<?= (int)$image['width'] ?>" height="<?= (int)$image['height'] ?>" loading="lazy"></a><?php endforeach ?></div></section><?php endif ?>
  <section id="repair-contact" class="repair-bottom-contact"><h2>联系方式</h2><dl><div><dt>联系人：</dt><dd><?= e($item['contact']) ?></dd></div><div><dt>联系电话：</dt><dd><?= $phoneMarkup('repair-primary-phone') ?></dd></div><div><dt><?= $item['type']==='request'?'需求地址':'服务地址' ?>：</dt><dd><?= e($item['address']) ?></dd></div></dl></section>
  <footer class="repair-information-footer"><a href="<?= e(listUrl($context)) ?>">返回<?= e($region.$item['category_name']) ?>列表</a><?php if((int)(currentUser()['id']??0)===(int)$item['user_id']): ?><a href="/fa-bu-xin-xi/<?= e(qs(['id'=>$item['id']])) ?>">编辑我的信息</a><?php endif ?></footer>
 </article>
 <aside class="repair-side-column">
  <section class="repair-contact-card" aria-labelledby="repair-contact-card-title"><h2 id="repair-contact-card-title"><?= $item['type']==='request'?'联系发布者':'联系服务方' ?></h2><div class="repair-contact-name"><span class="repair-contact-avatar" aria-hidden="true"><?= e(mb_substr($item['contact'],0,1)) ?></span><div><strong><?= e($item['contact']) ?></strong><span>联系人</span><?php require ROOT.'/views/verification-badge.php'; ?></div></div><p class="repair-phone-label">联系电话</p><?= $phoneMarkup('repair-side-phone') ?><div class="repair-contact-actions"><?php if($item['is_demo']): ?><span class="button full" aria-disabled="true">测试号码，仅供展示</span><?php else: ?><a class="button full" href="<?= e($phoneTarget) ?>">拨打电话</a><?php endif ?><button type="button" class="button button-outline full" data-copy-phone="<?= e($item['phone']) ?>">复制电话号码</button></div><p class="repair-copy-status" role="status" aria-live="polite"></p><dl class="repair-side-facts"><div><dt>服务区域</dt><dd><?= e($region) ?></dd></div><div><dt><?= $item['type']==='request'?'需求地址':'服务地址' ?></dt><dd><?= e($item['address']) ?></dd></div></dl><?php if($item['is_demo']): ?><p class="repair-side-note">测试号码与地址，仅用于页面展示。</p><?php else: ?><p class="repair-side-note">联系前请核实服务项目、费用及具体安排。</p><?php endif ?></section>
  <section class="repair-related"><div class="repair-sidebar-heading"><h2>同类信息</h2><a href="<?= e(listUrl($context)) ?>">更多</a></div><?php if($relatedItems): ?><ul><?php foreach($relatedItems as $relatedItem): ?><li><a href="<?= e(listingUrl($relatedItem)) ?>"><?= e($relatedItem['title']) ?></a><span><?= e($relatedItem['city_name'].($relatedItem['district_name']??'')) ?> · <?= e(substr($relatedItem['created_at'],0,10)) ?></span></li><?php endforeach ?></ul><?php else: ?><p class="repair-related-empty">暂无其他同类信息</p><?php endif ?></section>
 </aside>
</div>
