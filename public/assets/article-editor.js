(() => {
  const field = document.querySelector('[data-article-editor]');
  if (!field || !window.Quill) return;
  const textarea = field.querySelector('textarea');
  const format = field.querySelector('[name="body_format"]');
  const surface = field.querySelector('.article-editor-surface');
  const status = field.querySelector('.article-editor-status');
  const form = field.closest('form');
  surface.hidden = false;
  const editor = new Quill(surface, {
    theme: 'snow', placeholder: '在这里编写正文…',
    formats: ['header', 'bold', 'italic', 'underline', 'strike', 'blockquote', 'list', 'indent', 'align', 'link'],
    modules: { toolbar: [
      [{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'strike'],
      [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote'],
      [{ indent: '-1' }, { indent: '+1' }], [{ align: [] }], ['link', 'clean']
    ] }
  });
  if (format.value === 'html') editor.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
  else editor.setText(textarea.value, 'silent');
  editor.history.clear();
  editor.root.setAttribute('role', 'textbox');
  editor.root.setAttribute('aria-label', '正文');
  editor.root.setAttribute('aria-multiline', 'true');
  editor.root.setAttribute('aria-describedby', 'article-editor-help');
  field.querySelector('.field-help').id = 'article-editor-help';
  const labels = { header: '段落样式', bold: '加粗', italic: '斜体', underline: '下划线', strike: '删除线', list: '列表', blockquote: '引用', indent: '缩进', align: '对齐', link: '链接', clean: '清除格式' };
  const toolbar = field.querySelector('.ql-toolbar');
  toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', '正文格式');
  toolbar.querySelectorAll('button, select').forEach(control => {
    const name = [...control.classList].find(value => value.startsWith('ql-'))?.slice(3);
    let label = labels[name] || '格式';
    if (name === 'list') label = control.value === 'ordered' ? '有序列表' : '无序列表';
    if (name === 'indent') label = control.value === '+1' ? '增加缩进' : '减少缩进';
    control.setAttribute('aria-label', label); control.title = label;
  });
  toolbar.querySelectorAll('.ql-picker-label').forEach(control => {
    control.setAttribute('aria-label', control.closest('.ql-header') ? '段落样式' : '对齐');
  });
  const linkInput = field.querySelector('.ql-tooltip input');
  if (linkInput) linkInput.setAttribute('aria-label', '链接地址');
  textarea.hidden = true; textarea.required = false;
  field.querySelector('label').addEventListener('click', () => editor.focus());
  const sync = () => {
    textarea.value = editor.getSemanticHTML(); format.value = 'html';
    const length = [...editor.getText().trim()].length;
    status.textContent = `${length.toLocaleString('zh-CN')} / 100000 字`;
    return length;
  };
  editor.on('text-change', sync); sync();
  form.addEventListener('submit', event => {
    const length = sync();
    if (length < 10 || length > 100000 || textarea.value.length > 400000) {
      event.preventDefault(); status.textContent = '正文须为 10–100000 个字符，请调整后保存。';
      editor.root.setAttribute('aria-invalid', 'true'); editor.focus();
    } else editor.root.removeAttribute('aria-invalid');
  });
})();
