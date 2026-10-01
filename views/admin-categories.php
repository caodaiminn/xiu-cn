<?php
$categoryMap=[];
foreach($items as $item) $categoryMap[(int)$item['id']]=$item;
$categoryKind=field('kind',$record['kind']??getValue('kind','service'));
if(!in_array($categoryKind,['service','knowledge','encyclopedia'],true)) $categoryKind='service';
$selectedId=(int)($record['id']??getValue('category'));
if($_SERVER['REQUEST_METHOD']==='POST') $selectedId=(int)input('id')?:((int)input('parent_id')?:$selectedId);
$selected=$categoryMap[$selectedId]??null;
if($selected && $selected['kind']!==$categoryKind) $selected=null;
$path=[]; $cursor=$selected;
while($cursor && count($path)<3) { array_unshift($path,$cursor); $cursor=$categoryMap[(int)$cursor['parent_id']]??null; }
$roots=array_values(array_filter($items,fn($item)=>$item['kind']===$categoryKind && !$item['parent_id']));
$root=$path[0]??($roots[0]??null);
$second=$path[1]??null;
$selected=$selected??$root;
$children=fn($id)=>array_values(array_filter($items,fn($item)=>(int)$item['parent_id']===(int)$id && $item['kind']===$categoryKind));
$categoryLink=fn($params)=>adminUrl('categories',['kind'=>$categoryKind]+$params);
$previewLink=fn($item)=>$item['kind']==='service'?listUrl(['category'=>$item['id']]):articleCollectionUrl($item['kind'],['category'=>$item['id']]);
$showForm=$record || getValue('new')==='1' || ($_SERVER['REQUEST_METHOD']==='POST' && input('action','save')==='save');
$formParent=(int)$v('parent_id',getValue('parent',0));
$flat=categories($categoryKind,false);
$branch=[];
if($root) {
    $branchIds=[$root['id']];
    foreach($flat as $item) if(in_array($item['id'],$branchIds,true) || in_array($item['parent_id'],$branchIds,true)) { $branch[]=$item; $branchIds[]=$item['id']; }
}
$levelNames=[1=>'一级大类',2=>'二级分类',3=>'三级分类'];
?>
<nav class="topic-nav" aria-label="分类用途">
<?php foreach(['service','knowledge','encyclopedia'] as $type): ?>
    <a href="<?= e(adminUrl('categories',['kind'=>$type])) ?>" class="<?= $categoryKind===$type?'active':'' ?>" <?= $categoryKind===$type?'aria-current="page"':'' ?>><?= kindLabel($type) ?></a>
<?php endforeach ?>
</nav>
<section class="panel category-manager" aria-label="分级选择分类">
    <div class="category-manager-heading"><h2>选择分类</h2><p>先选择一级大类，再选择对应的二级分类。</p></div>
    <?php foreach([1=>['选择一级大类',$roots,null],2=>['选择二级分类',$root?$children($root['id']):[],$root],3=>['选择三级分类',$second?$children($second['id']):[],$second]] as $level=>[$heading,$options,$parent]): if($level===3 && !$options) continue; ?>
    <div class="category-level" data-level="<?= $level ?>">
        <div class="category-level-heading"><h3><span><?= $level ?></span><?= e($heading) ?> <small><?= count($options) ?> 个</small></h3>
            <?php if($level===1 || $parent): ?><a class="accent-link" href="<?= e($categoryLink(['new'=>1,'parent'=>$parent['id']??0,'category'=>$parent['id']??($root['id']??0)])) ?>#category-editor">＋ 新增<?= $levelNames[$level] ?></a><?php endif ?>
        </div>
        <?php if($parent): ?><p class="category-parent-note">所属<?= $levelNames[$level-1] ?>：<?= e($parent['name']) ?></p><?php endif ?>
        <div class="category-choice-grid">
            <?php foreach($options as $option): $isSelected=($level===1?$root:($level===2?$second:($path[2]??null)))['id']??0; ?>
            <a class="category-choice <?= (int)$isSelected===(int)$option['id']?'is-selected':'' ?>" href="<?= e($categoryLink(['category'=>$option['id']])) ?>" <?= (int)$isSelected===(int)$option['id']?'aria-current="true"':'' ?> data-category-id="<?= $option['id'] ?>">
                <span><?= e($option['name']) ?></span><?php if(!$option['enabled']): ?><small>停用</small><?php endif ?>
            </a>
            <?php endforeach ?>
        </div>
        <?php if(!$options): ?><p class="category-empty"><?= $parent?'这个大类还没有二级分类，点击右上角新增。':'还没有一级大类，点击右上角新增。' ?></p><?php endif ?>
    </div>
    <?php endforeach ?>
    <?php if($selected): ?>
    <div class="category-current"><div><small>当前选择</small><strong><?= e(implode(' › ',array_column($path?:[$root],'name'))) ?></strong></div>
        <div class="form-actions"><a class="button" href="<?= e(adminUrl('categories',['edit'=>$selected['id'],'kind'=>$categoryKind])) ?>#category-editor">编辑当前分类</a><a class="button button-outline" href="<?= e($previewLink($selected)) ?>" target="_blank" rel="noopener">查看前台页面</a></div>
    </div>
    <?php endif ?>
</section>
<?php if($showForm):
$formLevel=1; $cursor=$categoryMap[$formParent]??null;
while($cursor && $formLevel<3) { $formLevel++; $cursor=$categoryMap[(int)$cursor['parent_id']]??null; }
$excluded=[]; if($record) { $excluded=[(int)$record['id']]; foreach($flat as $item) if(in_array((int)$item['parent_id'],$excluded,true)) $excluded[]=(int)$item['id']; }
?>
<section class="panel admin-form" id="category-editor">
    <div class="category-editor-heading"><h2><?= $record?'编辑':'新增' ?><?= $levelNames[$formLevel] ?></h2><a class="text-link" href="<?= e($categoryLink(['category'=>$selected['id']??0])) ?>">取消<?= $record?'编辑':'新增' ?></a></div>
    <form method="post" class="stack-form">
        <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$v('id',0) ?>">
        <div class="form-grid">
            <label>分类名称<input name="name" value="<?= e($v('name')) ?>" maxlength="64" required placeholder="例如：空调维修"></label>
            <label>分类用途<select name="kind" id="category-kind"><?php foreach(['service','knowledge','encyclopedia'] as $type): ?><option value="<?= $type ?>" <?= $categoryKind===$type?'selected':'' ?>><?= kindLabel($type) ?></option><?php endforeach ?></select></label>
            <label>所属分类<select name="parent_id" id="category-parent"><option value="0">无上级（作为一级大类）</option><?php foreach(['service','knowledge','encyclopedia'] as $type): foreach(categories($type,false) as $option): if($option['depth']>=3 || in_array((int)$option['id'],$excluded,true)) continue; ?><option data-kind="<?= $type ?>" value="<?= $option['id'] ?>" <?= $formParent===(int)$option['id']?'selected':'' ?>><?= e(kindLabel($type).' · '.($option['depth']===1?'一级':'二级').' · '.$option['label']) ?></option><?php endforeach; endforeach ?></select><small class="field-help">选一级大类作为上级，即新增二级分类；选二级分类作为上级，即新增三级分类。</small></label>
            <label>排序<input type="number" name="sort_order" value="<?= (int)$v('sort_order',0) ?>"><small class="field-help">数字越小，展示越靠前。</small></label>
            <label>分类导航展示<select name="show_in_filter"><option value="1" <?= (int)$v('show_in_filter',1)===1?'selected':'' ?>>显示链接</option><option value="0" <?= (int)$v('show_in_filter',1)===0?'selected':'' ?>>隐藏链接</option></select><small class="field-help">隐藏导航不会下架已发布内容。</small></label>
            <label>状态<select name="enabled"><option value="1" <?= (int)$v('enabled',1)===1?'selected':'' ?>>启用</option><option value="0" <?= (int)$v('enabled',1)===0?'selected':'' ?>>停用</option></select><small class="field-help">停用后，该分类、子分类及其内容暂停展示。</small></label>
        </div>
        <details class="category-advanced"><summary>高级设置：兼容旧地址</summary><label>兼容标识<input name="slug" value="<?= e($v('slug')) ?>" pattern="[a-z][a-z0-9-]{0,79}" maxlength="80" placeholder="留空自动生成"><small class="field-help">仅用于兼容旧地址；留空自动生成。公开页面的全拼地址由系统生成，修改名称后仍保持原地址。</small></label></details>
        <div class="form-actions"><button class="button">保存分类</button><a class="text-link" href="<?= e($categoryLink(['category'=>$selected['id']??0])) ?>">取消</a></div>
    </form>
</section>
<?php endif ?>
<?php if($root): ?>
<section class="panel table-panel category-branch"><h2><?= e($root['name']) ?> · 分类管理</h2><p class="field-help">这里仅展示当前一级大类及其下属分类。编辑可调整名称、所属分类、排序和展示状态。</p>
    <div class="table-scroll"><table><thead><tr><th>名称</th><th>层级 / 所属分类</th><th>前台页面 / 导航</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach($branch as $item): $parent=$categoryMap[(int)$item['parent_id']]??null; ?>
    <tr><td><?= e($item['name']) ?></td><td><span class="category-depth depth-<?= $item['depth'] ?>"><?= $levelNames[$item['depth']] ?></span><small class="field-help"><?= e($parent['name']??'无上级') ?></small></td><td><a class="accent-link" href="<?= e($previewLink($item)) ?>" target="_blank" rel="noopener">查看页面</a><small class="field-help"><?= $item['show_in_filter']?'导航显示':'导航隐藏' ?></small></td><td><?= (int)$item['sort_order'] ?></td><td><?= $item['enabled']?'启用':'停用' ?></td><td><div class="table-actions"><a href="<?= e(adminUrl('categories',['edit'=>$item['id'],'kind'=>$categoryKind])) ?>#category-editor">编辑</a><?php if($item['depth']<3): ?><a href="<?= e($categoryLink(['new'=>1,'parent'=>$item['id'],'category'=>$item['id']])) ?>#category-editor">新增子分类</a><?php endif ?><form method="post" data-confirm="确定删除此分类？被内容或子分类引用的分类无法删除，可改为停用。"><?= csrfField() ?><input type="hidden" name="id" value="<?= $item['id'] ?>"><input type="hidden" name="kind" value="<?= e($categoryKind) ?>"><input type="hidden" name="action" value="delete"><button class="text-button danger">删除</button></form></div></td></tr>
    <?php endforeach ?>
    </tbody></table></div>
</section>
<?php endif ?>
