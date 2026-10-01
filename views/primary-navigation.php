<?php
$primaryNavigationItems=navigationItems();
if(!empty($headerCity['id'])) foreach($primaryNavigationItems as &$primaryItem) if($primaryItem['url']===publicPath('services')) $primaryItem['url']=listUrl(['city'=>(int)$headerCity['id']]);
unset($primaryItem);
$activeNavigation=activeNavigationId($primaryNavigationItems,$seo['canonical']??($_SERVER['REQUEST_URI']??'/'));
?>
<?php if($primaryNavigationItems): ?>
<nav class="primary-nav" aria-label="主导航"><div class="container nav-inner">
<?php foreach($primaryNavigationItems as $navItem): ?>
 <a href="<?= e($navItem['url']) ?>"<?= $activeNavigation===(int)$navItem['id']?' class="active" aria-current="page"':'' ?><?= $navItem['new_tab']?' target="_blank" rel="noopener noreferrer"':'' ?>><?= e($navItem['label']) ?></a>
<?php endforeach ?>
</div></nav>
<?php endif ?>
