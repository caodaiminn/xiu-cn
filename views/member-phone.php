<?php if($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif ?>
<section class="panel admin-form member-phone-form">
 <h2><?= ($memberUser['phone']??'')!==''?'修改手机号码':'添加手机号码' ?></h2>
 <p class="muted">当前手机号码：<strong><?= e(($memberUser['phone']??'')!==''?$memberUser['phone']:'尚未填写') ?></strong></p>
 <p class="field-help">手机号码用于登录和新信息的默认联系电话。保存后，请使用新手机号码和原密码登录。</p>
 <form method="post" class="stack-form"><?= csrfField() ?>
  <label>手机号码<input type="tel" name="phone" inputmode="numeric" autocomplete="tel" minlength="11" maxlength="11" pattern="1[3-9][0-9]{9}" value="<?= e(field('phone',$memberUser['phone']??'')) ?>" placeholder="请输入11位手机号码" required></label>
  <label>当前登录密码<input type="password" name="password" autocomplete="current-password" maxlength="72" placeholder="输入当前密码确认修改" required></label>
  <p class="field-help">已有发布信息的联系电话可在“我的发布”中单独编辑。</p>
  <div class="form-actions"><button class="button">保存手机号码</button><a class="button button-outline" href="<?= e(memberUrl()) ?>">返回管理首页</a></div>
 </form>
</section>
