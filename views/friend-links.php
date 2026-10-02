<?php $footerLinks=friendLinks(); if($footerLinks): ?>
<nav class="footer-friend-links" aria-label="友情链接"><strong>友情链接</strong><div><?php foreach($footerLinks as $link): ?><a href="<?= e($link['url']) ?>"<?= $link['new_tab']?' target="_blank" rel="noopener"':'' ?>><?= e($link['label']) ?></a><?php endforeach ?></div></nav>
<?php endif ?>
