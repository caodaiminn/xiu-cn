<?php
$displayTime=$item['refreshed_at']?:$item['created_at'];
$timeKind=$item['refreshed_at']?'刷新':'发布';
$phoneTarget='tel:'.preg_replace('/[^0-9+]/','',$item['phone']);
?>
<article class="listing-row listing-contact-card" data-listing-id="<?= (int)$item['id'] ?>">
 <a class="listing-cover" href="<?= e(listingUrl($item)) ?>" aria-label="查看<?= e($item['title']) ?>详情"><img src="<?= $item['image_id']?'/media/'.(int)$item['image_id'].'/':'/assets/listing-cover.svg' ?>" alt="<?= $item['image_id']?e($item['title'].'附图'):'' ?>" width="210" height="150" loading="lazy"></a>
 <div class="listing-main">
  <div class="listing-title"><h2><a href="<?= e(listingUrl($item)) ?>"><?= e($item['title']) ?></a></h2></div>
  <div class="listing-meta"><span class="type-pill <?= $item['type']==='request'?'request':'' ?>"><?= listingType($item['type']) ?></span><span><?= e($item['city_name'].($item['district_name']??'')) ?> · <?= e($item['category_name']) ?></span><?php if($item['is_demo']): ?><span class="demo-label">测试信息</span><?php endif ?></div>
  <div class="listing-publisher"><span>联系人：<?= e($item['contact']) ?></span><?php require ROOT.'/views/verification-badge.php'; ?></div>
 </div>
 <div class="listing-contact-actions">
  <time class="listing-relative-time" datetime="<?= e(date(DATE_ATOM,strtotime($displayTime))) ?>" title="<?= e($timeKind.'时间：'.$displayTime) ?>" aria-label="<?= e($timeKind.'于'.relativeListingTime($displayTime)) ?>"><?= e(relativeListingTime($displayTime)) ?></time>
  <details class="listing-consult"><summary>立即咨询<span class="sr-only">：<?= e($item['title']) ?></span></summary><div class="listing-phone"><span>联系电话</span><?php if($item['is_demo']): ?><strong><?= e($item['phone']) ?></strong><small>测试号码，仅供展示</small><?php else: ?><a href="<?= e($phoneTarget) ?>"><?= e($item['phone']) ?></a><?php endif ?></div></details>
 </div>
</article>
