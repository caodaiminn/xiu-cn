<?php if(in_array($item['verification_type']??null,['company','personal'],true)): ?>
<span class="listing-verification <?= e($item['verification_type']) ?>" data-verification-type="<?= e($item['verification_type']) ?>" aria-label="发布账号<?= $item['verification_type']==='company'?'企业':'个人' ?>身份认证已通过"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6Z"/><path d="m8 12 3 3 5-6"/></svg><?= $item['verification_type']==='company'?'企业已认证':'个人已认证' ?></span>
<?php endif ?>
