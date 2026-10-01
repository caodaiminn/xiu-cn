<div class="article-body-field" data-article-editor>
 <label for="article-body">正文</label>
 <input type="hidden" name="body_format" value="<?= e($v('body_format','text')) ?>">
 <textarea id="article-body" name="body" rows="12" minlength="10" maxlength="400000" required><?= e($v('body')) ?></textarea>
 <div class="article-editor-surface" hidden></div>
 <p class="field-help">支持标题、加粗、斜体、下划线、列表、引用、对齐和链接；正文须为 10–100000 个字符。</p>
 <p class="article-editor-status" role="status" aria-live="polite"></p>
</div>
