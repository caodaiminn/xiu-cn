<!doctype html>
<html lang="zh-CN">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
 <title><?= e($title) ?></title><meta name="description" content="<?= e($seo['description']) ?>"><meta name="robots" content="<?= e($seo['robots']) ?>">
 <?php if($seo['canonical']): ?><link rel="canonical" href="<?= e($seo['canonical']) ?>"><meta property="og:url" content="<?= e($seo['canonical']) ?>"><?php endif ?>
 <meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($seo['description']) ?>"><meta property="og:site_name" content="<?= e(siteName()) ?>"><meta property="og:locale" content="zh_CN"><meta property="og:type" content="<?= $template==='article'?'article':'website' ?>">
 <?php if($seoJson): ?><script type="application/ld+json"><?= $seoJson ?></script><?php endif ?>
 <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg"><link rel="stylesheet" href="/assets/style.css?v=<?= filemtime(ROOT.'/public/assets/style.css') ?>"><script src="/assets/site.js?v=<?= filemtime(ROOT.'/public/assets/site.js') ?>" defer></script>
 <?php if($adminPage && ($section??'')==='articles'): ?>
 <link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css?v=2.0.3">
 <link rel="stylesheet" href="/assets/article-editor.css?v=<?= filemtime(ROOT.'/public/assets/article-editor.css') ?>">
 <script src="/assets/vendor/quill/quill.js?v=2.0.3" defer></script>
 <script src="/assets/article-editor.js?v=<?= filemtime(ROOT.'/public/assets/article-editor.js') ?>" defer></script>
 <?php endif ?>
 <?php if($adminPage && ($section??'')==='navigation'): ?><script src="/assets/navigation.js?v=<?= filemtime(ROOT.'/public/assets/navigation.js') ?>" defer></script><?php endif ?>
 <?php if($template==='home' && !empty($GLOBALS['browser_city_location_needed'])): ?><script src="/assets/city-location.js?v=<?= filemtime(ROOT.'/public/assets/city-location.js') ?>" defer></script><?php endif ?>
 <?php if($template==='publish' || ($adminPage && ($section??'')==='ai')): ?><script src="/assets/ai-description.js?v=<?= filemtime(ROOT.'/public/assets/ai-description.js') ?>" defer></script><?php endif ?>
</head>
<body class="<?= $adminPage?'admin-body':(!empty($memberPage)?'member-body':'') ?>">
<?php if($adminPage): ?>
<header class="admin-header"><a class="admin-brand" href="<?= e(adminUrl('dashboard')) ?>">修 <span><?= e(siteName()) ?>管理后台</span></a><div><a href="/" target="_blank" rel="noopener">查看网站</a><span><?= e(currentAdmin()['username']) ?></span><form method="post" action="<?= e(adminUrl('logout')) ?>"><?= csrfField() ?><button class="text-button">退出</button></form></div></header>
<div class="admin-shell"><aside class="admin-nav"><div class="nav-label">内容与运营</div><?php foreach(['dashboard'=>'概览','listings'=>'信息审核','categories'=>'分类管理','cities'=>'城市管理','districts'=>'区域管理','users'=>'用户管理','verifications'=>'身份认证审核','articles'=>'百科与知识库','navigation'=>'导航栏管理','settings'=>'站点设置','notifications'=>'邮件通知','ai'=>'AI生成设置'] as $key=>$label): ?><a class="<?= ($section??'')===$key?'active':'' ?>" href="<?= e(adminUrl($key)) ?>"><?= e($label) ?><?php if($key==='listings'): ?><span><?= (int)($result['counts']??adminListingCounts())['pending'] ?></span><?php endif ?></a><?php endforeach ?><p>分类与城市停用后，对应信息将暂停展示。</p></aside><main class="admin-main">
<?php elseif(!empty($memberPage)): require ROOT.'/views/member-shell.php'; ?>
<?php else: ?>
<div class="topbar"><div class="container topbar-inner"><span>本地维修服务 · 分类信息平台</span><div><?php if($user=currentUser()): ?><a href="/yong-hu-zhong-xin/">会员中心</a><span><?= e($user['username']) ?></span><form method="post" action="/tui-chu/"><?= csrfField() ?><button class="text-button">退出</button></form><?php else: ?><a href="/deng-lu/">登录</a><a href="/zhu-ce/">免费注册</a><?php endif ?><a href="<?= e(adminUrl('dashboard')) ?>">网站管理</a></div></div></div>
<?php
$headerCity=$city??($homeCity??null);
if($template==='detail' && !empty($item['city_id'])) $headerCity=seoRecord('xiu_cities',(int)$item['city_id']);
if(!$headerCity) $headerCity=selectedLocationCity();
if($headerCity && !$headerCity['enabled']) { $headerCity=null; unset($_SESSION['selected_city_id']); }
?>
<header class="site-header container"><div class="header-identity"><a class="brand" href="/" aria-label="<?= e(siteName()) ?>首页"><span class="brand-mark">修</span><span class="brand-name"><?= e(siteName()) ?><small>维修服务 · 城市信息</small></span></a><div class="header-city"><span class="header-city-location"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s7-7 7-13a7 7 0 0 0-14 0c0 6 7 13 7 13Z"/><circle cx="12" cy="8" r="2.5"/></svg><span><?= e($headerCity['name']??'全国') ?></span></span><a class="header-city-switch" href="/selectcity.html" aria-label="切换城市">[切换]</a></div></div><form class="header-search" method="get" action="<?= e(listUrl(['city'=>$headerCity['id']??0])) ?>"><label class="sr-only" for="header-q">搜索维修服务</label><input id="header-q" name="q" placeholder="找维修服务，输入关键词" value="<?= e(getValue('q')) ?>" maxlength="100"><button>搜索</button></form><a class="publish-button" href="/fa-bu-xin-xi/">免费发布信息</a></header>
<?php require ROOT.'/views/primary-navigation.php'; ?>
<main class="container main-content"><?php if($template==='home' && !empty($GLOBALS['browser_city_location_needed'])): ?><span hidden data-ip-city-location data-csrf="<?= e(csrf()) ?>"></span><?php endif ?>
<?php endif ?>
<?php if($notice): ?><div class="notice <?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div><?php endif ?>
<?php require ROOT.'/views/'.$template.'.php'; ?>
</main>
<?php if($adminPage || !empty($memberPage)): ?></div><?php else: ?><footer class="site-footer"><div class="container"><a class="footer-brand" href="/"><?= e(siteName()) ?></a><div><a href="/fen-lei-xin-xi/">分类信息</a><a href="/selectcity.html">选择城市</a><a href="/wei-xiu-zhi-shi-ku/">维修知识库</a><a href="/wei-xiu-bai-ke/">维修百科</a><a href="/wang-zhan-di-tu/">网站地图</a></div><p>服务与需求由用户发布，请自行核实服务内容及联系方式。标有“测试信息”或“演示”的内容仅用于本地展示。</p><small>© <?= date('Y') ?> <?= e(siteName()) ?></small></div></footer><?php endif ?>
<?= analyticsScripts() ?>
</body></html>
