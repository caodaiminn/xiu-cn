<?php $value=fn($key,$default='')=>field($key,$item[$key]??$default); ?>
<div class="breadcrumbs"><a href="/">首页</a><span>/</span><a href="/yong-hu-zhong-xin/">我的信息</a><span>/</span><span><?= $item?'编辑信息':'发布信息' ?></span></div>
<div class="page-heading"><div><span class="eyebrow">免费发布</span><h1><?= $item?'编辑信息':'发布服务或需求' ?></h1></div></div>
<?php if($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif ?>
<form method="post" enctype="multipart/form-data" class="publish-wizard"><?= csrfField() ?><input type="hidden" name="id" value="<?= $item['id']??0 ?>">
<?php require ROOT.'/views/publish-category-picker.php'; ?>
<div class="publish-category-summary" hidden><div><span>维修分类</span><strong data-publish-category-path></strong></div><button type="button" class="text-button" data-publish-category-change>重新选择分类</button></div>
<div class="content-with-sidebar" id="publish-details"><section class="panel form-panel"><div class="stack-form">
<fieldset><legend>信息类型</legend><div class="radio-options"><label><input type="radio" name="type" value="service" <?= $value('type','service')==='service'?'checked':'' ?>>维修服务 <small>我是师傅或服务商</small></label><label><input type="radio" name="type" value="request" <?= $value('type')==='request'?'checked':'' ?>>求修需求 <small>我需要维修帮助</small></label></div></fieldset>
<div class="publish-title-field"><label>信息标题<input name="title" id="listing-title" value="<?= e($value('title')) ?>" placeholder="根据城市、服务地址和分类自动填写，也可自行输入" minlength="5" maxlength="120" aria-describedby="listing-title-help" required></label><p class="field-help publish-title-help" id="listing-title-help"><span data-title-help>根据城市、服务地址和分类自动填写标题，去除空格及特殊符号；也可自行修改。</span><button type="button" class="text-button" data-use-title-suggestion hidden>使用建议标题</button></p></div>
<?php require ROOT.'/views/publish-region-selector.php'; ?>
<label>服务地址<input name="address" id="listing-address" value="<?= e($value('address')) ?>" placeholder="填写区域、街道或服务范围" minlength="2" maxlength="255" required></label>
<div class="form-grid"><label>联系人<input name="contact" value="<?= e($value('contact')) ?>" maxlength="64" required></label><label>联系电话<input name="phone" value="<?= e($value('phone')) ?>" type="tel" maxlength="32" placeholder="手机号或固定电话" required></label></div>
<?php $publishAi=aiSettings(); $publishAiReady=$publishAi['enabled'] && $publishAi['api_key']!==''; unset($publishAi); ?>
<div class="publish-description"><div class="publish-description-heading"><label for="listing-body">详细描述</label><button type="button" class="button button-outline small ai-generate-button" data-ai-description data-endpoint="/api/ai-description/" <?= !$publishAiReady?'disabled title="AI生成暂未开启"':'' ?>>AI生成</button></div><textarea id="listing-body" name="body" rows="9" minlength="10" maxlength="20000" placeholder="说明服务项目、设备故障、服务范围或维修需求。" aria-describedby="ai-description-status" required><?= e($value('body')) ?></textarea><p class="field-help ai-description-status" id="ai-description-status" data-ai-status role="status" aria-live="polite"><?= $publishAiReady?'根据当前信息标题生成，生成后可继续编辑。':'请说明服务项目、设备故障、服务范围或维修需求。' ?></p></div>
<label>附图<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-image-limit="5"><small class="field-help">最多保留 5 张，每张不超过 5MB。仅支持 JPEG、PNG、WebP，最大 1600 万像素。</small></label>
<?php if($images): ?><div class="existing-images"><?php foreach($images as $image): ?><label><img src="/media/<?= $image['id'] ?>/" alt="已有附图"><span><input type="checkbox" name="remove_images[]" value="<?= $image['id'] ?>">移除此图</span></label><?php endforeach ?></div><?php endif ?>
<p class="field-help">上传验证失败时，文字会保留，图片需要重新选择。修改已发布信息后会重新审核，期间暂停展示。</p>
<div class="form-actions"><button class="button">发布信息</button><a class="button button-outline" href="/yong-hu-zhong-xin/">返回我的信息</a></div>
</div></section><aside class="side-stack"><section class="panel side-card"><h2>让信息更容易找到</h2><ol><li>标题写明服务与地区</li><li>选择最贴近内容的分类</li><li>说明故障、范围或时间</li><li>确认联系电话填写正确</li></ol></section></aside></div>
</form>
