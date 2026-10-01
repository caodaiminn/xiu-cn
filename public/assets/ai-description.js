const modelPreset = document.querySelector('[data-ai-model-preset]');
const modelId = document.querySelector('[data-ai-model-id]');
if (modelPreset && modelId) {
  modelPreset.addEventListener('change', () => { if (modelPreset.value) modelId.value = modelPreset.value; });
  modelId.addEventListener('input', () => { modelPreset.value = Array.from(modelPreset.options).some(option => option.value === modelId.value) ? modelId.value : ''; });
}
const aiButton = document.querySelector('[data-ai-description]');
if (aiButton) {
  const form = aiButton.closest('form'), body = form.querySelector('[name="body"]'), title = form.querySelector('[name="title"]');
  const status = form.querySelector('[data-ai-status]'), csrf = form.querySelector('[name="csrf"]');
  let busy = false;
  aiButton.addEventListener('click', async () => {
    if (busy) return;
    const currentTitle = title.value.trim(), currentType = form.querySelector('[name="type"]:checked')?.value, originalBody = body.value;
    if (Array.from(currentTitle).length < 5 || Array.from(currentTitle).length > 120) { status.textContent = '请先填写5–120字的信息标题。'; title.focus(); return; }
    if (originalBody.trim() && !window.confirm('生成成功后将替换现有详细描述，是否继续？')) return;
    busy = true; aiButton.disabled = true; aiButton.textContent = '生成中…'; aiButton.setAttribute('aria-busy', 'true');
    status.textContent = '正在根据标题生成，请稍候…';
    const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 75000);
    try {
      const response = await fetch(aiButton.dataset.endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body: new URLSearchParams({csrf: csrf.value, title: currentTitle, type: currentType || ''}), signal: controller.signal});
      const contentType = response.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) throw new Error('操作已过期或服务暂不可用，请刷新页面重试，原描述已保留。');
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || '生成失败，请稍后重试，原描述已保留。');
      if (typeof data.body !== 'string' || Array.from(data.body).length < 10 || Array.from(data.body).length > 20000) throw new Error('生成内容无效，请重试，原描述已保留。');
      if (body.value !== originalBody || title.value.trim() !== currentTitle || form.querySelector('[name="type"]:checked')?.value !== currentType) { status.textContent = '生成期间标题、类型或描述发生变化，已保留当前内容，请重新生成。'; return; }
      body.value = data.body; body.dispatchEvent(new Event('input', {bubbles: true}));
      status.textContent = '已生成，请核对服务范围和故障描述后再提交。'; body.focus();
    } catch (error) {
      status.textContent = error.name === 'AbortError' ? '生成超时，原描述已保留，请稍后重试。' : (error instanceof TypeError ? '网络连接失败，原描述已保留，请稍后重试。' : error.message);
    } finally { clearTimeout(timer); busy = false; aiButton.disabled = false; aiButton.textContent = 'AI生成'; aiButton.removeAttribute('aria-busy'); }
  });
}
