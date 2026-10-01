<?php $nextSort=$items?min(999999,max(array_column($items,'sort_order'))+10):10; ?>
<section class="panel admin-form navigation-form">
 <h2><?= $record?'编辑导航':'新增导航' ?></h2>
 <form method="post" class="stack-form" data-navigation-form>
  <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$v('id',0) ?>">
  <label>快捷选择站内功能<select data-navigation-preset><option value="">自定义名称和链接</option><?php foreach(navigationPresets() as $preset): ?><option value="<?= e($preset['url']) ?>" data-label="<?= e($preset['label']) ?>" data-match="<?= e($preset['match_mode']) ?>"><?= e($preset['label']) ?> · <?= e($preset['url']) ?></option><?php endforeach ?></select><small class="field-help">选择后填入名称和链接，仍可自由修改。</small></label>
  <div class="form-grid"><label>导航名称<input name="label" value="<?= e($v('label')) ?>" maxlength="40" placeholder="例如：维修知识库" required></label><label>排序<input type="number" name="sort_order" value="<?= (int)$v('sort_order',$nextSort) ?>" min="0" max="999999" required><small class="field-help">数字越小越靠前；也可以使用下方的上移、下移。</small></label></div>
  <label>导航链接<input name="url" value="<?= e($v('url')) ?>" maxlength="1000" placeholder="/wei-xiu-zhi-shi-ku/ 或 https://example.com" required><small class="field-help">可以填写站内页面、分类、城市、文章链接或外部网址。</small></label>
  <div class="form-grid"><label>显示状态<select name="enabled"><option value="1" <?= $v('enabled',1)==1?'selected':'' ?>>显示</option><option value="0" <?= $v('enabled',1)==0?'selected':'' ?>>隐藏</option></select></label><label>打开方式<select name="new_tab"><option value="0" <?= $v('new_tab',0)==0?'selected':'' ?>>当前窗口</option><option value="1" <?= $v('new_tab',0)==1?'selected':'' ?>>新窗口</option></select></label></div>
  <label>选中规则<select name="match_mode"><option value="exact" <?= $v('match_mode','exact')==='exact'?'selected':'' ?>>只在链接对应页面选中</option><option value="prefix" <?= $v('match_mode','exact')==='prefix'?'selected':'' ?>>对应页面及其下级页面选中</option></select></label>
  <div class="form-actions"><button class="button">保存导航</button><?php if($record): ?><a href="<?= e(adminUrl('navigation')) ?>" class="text-link">取消编辑</a><?php endif ?></div>
 </form>
</section>
<section class="panel navigation-panel"><div class="section-heading"><h2>当前导航 <small><?= count($items) ?> 项</small></h2><a href="/" target="_blank" rel="noopener" class="accent-link">查看前台</a></div>
 <p class="field-help">按下方顺序展示。隐藏或删除导航不会删除对应页面。</p>
 <div class="navigation-preview" aria-label="导航预览"><?php foreach($items as $item): if(!$item['enabled']) continue; ?><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($item['label']) ?></a><?php endforeach ?><?php if(!array_filter($items,fn($item)=>$item['enabled'])): ?><span>目前没有显示的导航</span><?php endif ?></div>
 <div class="navigation-list"><?php foreach($items as $index=>$item): ?>
  <article class="navigation-row" data-navigation-id="<?= (int)$item['id'] ?>">
   <span class="navigation-rank"><?= $index+1 ?></span><div class="navigation-description"><h3><?= e($item['label']) ?> <span class="navigation-status <?= $item['enabled']?'':'is-hidden' ?>"><?= $item['enabled']?'显示':'隐藏' ?></span></h3><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="navigation-url"><?= e($item['url']) ?></a><small>排序 <?= (int)$item['sort_order'] ?> · <?= $item['new_tab']?'新窗口':'当前窗口' ?> · <?= $item['match_mode']==='prefix'?'包含下级页面':'精确页面' ?></small></div>
   <div class="navigation-actions"><a class="button button-outline small" href="<?= e(adminUrl('navigation',['edit'=>$item['id']])) ?>">编辑</a><?php foreach(['up'=>'上移','down'=>'下移','toggle'=>$item['enabled']?'隐藏':'显示','delete'=>'删除'] as $action=>$label): ?><form method="post"<?= $action==='delete'?' data-confirm="确定删除这项导航？对应页面会保留。"':'' ?>><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button class="text-button <?= $action==='delete'?'danger':'' ?>"<?= ($action==='up' && $index===0)||($action==='down' && $index===count($items)-1)?' disabled':'' ?>><?= e($label) ?></button></form><?php endforeach ?></div>
  </article>
 <?php endforeach ?><?php if(!$items): ?><div class="empty-state">暂无导航，请在上方新增。</div><?php endif ?></div>
</section>
