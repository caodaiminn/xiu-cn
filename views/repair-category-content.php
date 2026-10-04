<?php if(!empty($categoryContent)): ?>
<div class="repair-category-content">
 <h3><?= e($categoryContent['subject']) ?>项目与报修说明</h3>
 <p><?= e($categoryContent['intro']) ?></p>
 <p class="category-local-status"><span>所在地区：<?= e($categoryContent['region']) ?></span><?= e($categoryContent['status']) ?></p>
 <?php if($categoryContent['children']): ?><div class="category-related-projects"><strong>按具体项目查找：</strong><?php foreach($categoryContent['children'] as $child): ?><a href="<?= e(listUrl(['city'=>$city['id']??0,'category'=>$child['id']])) ?>"><?= e($child['name']) ?></a><?php endforeach ?></div><?php endif ?>
 <div class="repair-category-sections"><?php foreach($categoryContent['sections'] as $contentSection): ?><section><h3><?= e($contentSection['heading']) ?></h3><?php foreach($contentSection['paragraphs'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach ?><ul><?php foreach($contentSection['items'] as $point): ?><li><?= e($point) ?></li><?php endforeach ?></ul></section><?php endforeach ?></div>
 <p class="category-safety-note"><strong>现场安全：</strong><?= e($categoryContent['safety']) ?></p>
</div>
<?php endif ?>
