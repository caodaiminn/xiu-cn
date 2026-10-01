const { chromium } = require('C:/Users/Administrator/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs'); const path = require('path'); const assert = require('assert');
const out = path.join(__dirname, 'artifacts');
const fixture = JSON.parse(fs.readFileSync(path.join(out, 'article-rich-text-fixture.json'), 'utf8'));
const base = 'http://www.xiu.cn';
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const context = await browser.newContext();
  await context.addCookies([{ name: 'xiu_session', value: fixture.session, url: base }]);
  const page = await context.newPage(); const errors = []; const checks = [];
  page.on('pageerror', error => errors.push(error.message));
  const edit = `/dgadmin/articles/?edit=${fixture.id}&kind=encyclopedia`;
  const setDocument = async () => page.locator('.ql-editor').evaluate(root => {
    const q = window.Quill.find(root.parentElement);
    q.setContents([
      { insert: '维护要点' }, { insert: '\n', attributes: { header: 2 } },
      { insert: '重点文字', attributes: { bold: true } }, { insert: ' 与斜体', attributes: { italic: true } }, { insert: '\n', attributes: { align: 'center' } },
      { insert: '检查电源' }, { insert: '\n', attributes: { list: 'bullet' } },
      { insert: '检查连接' }, { insert: '\n', attributes: { list: 'bullet', indent: 1 } },
      { insert: '按顺序操作' }, { insert: '\n', attributes: { list: 'ordered' } },
      { insert: '请先断电后维护' }, { insert: '\n', attributes: { blockquote: true } },
      { insert: '查看说明', attributes: { link: 'https://example.com/manual' } }, { insert: '\n' }
    ]);
  });
  try {
    await page.goto(base + '/dgadmin/articles/?edit=8&kind=encyclopedia');
    await page.locator('.ql-editor').waitFor();
    assert((await page.locator('.ql-editor').innerText()).trim().length >= 10); checks.push('article 8 loads existing text');
    await page.goto(base + edit); await page.locator('.ql-editor').waitFor();
    await page.locator('.ql-editor').evaluate(root => window.Quill.find(root.parentElement).setText('很短'));
    await page.getByRole('button', { name: '保存文章', exact: true }).click();
    assert((await page.locator('.article-editor-status').innerText()).includes('10–100000')); checks.push('empty or short body cannot submit');
    await setDocument();
    for (const [name, viewport] of [['desktop', { width: 1440, height: 1000 }], ['mobile', { width: 390, height: 844 }], ['small-mobile', { width: 320, height: 760 }]]) {
      await page.setViewportSize(viewport); await page.locator('[data-article-editor]').scrollIntoViewIfNeeded();
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), name + ' overflow');
      await page.locator('[data-article-editor]').screenshot({ path: path.join(out, `rich-editor-${name}.png`) });
      checks.push(name + ' editor layout');
    }
    await page.locator('.ql-editor').evaluate(root => { const q = window.Quill.find(root.parentElement); q.setSelection(0, 4); });
    await page.getByRole('button', { name: '链接', exact: true }).click();
    await page.getByRole('textbox', { name: '链接地址', exact: true }).fill('https://example.com/heading');
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'mobile link popup overflow');
    await page.locator('.ql-tooltip .ql-action').click();
    assert(await page.locator('.ql-editor h2 a').count()); checks.push('mobile link popup inserts a link');
    await setDocument();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('.ql-editor').evaluate(root => { const q = window.Quill.find(root.parentElement); q.setSelection(0, 4); });
    await page.getByRole('button', { name: '加粗', exact: true }).click();
    assert(await page.locator('.ql-editor h2 strong').count()); checks.push('toolbar formats selected text');
    await page.locator('select[name="status"]').selectOption('published');
    await Promise.all([page.waitForURL(url => !url.searchParams.has('edit')), page.getByRole('button', { name: '保存文章', exact: true }).click()]);
    await page.goto(base + edit); await page.locator('.ql-editor').waitFor();
    assert(await page.locator('.ql-editor h2 strong').count());
    assert(await page.locator('.ql-editor li').count() >= 3); checks.push('saved rich text reloads for editing');
    await page.goto(base + fixture.url);
    assert(await page.locator('.prose h2 strong').count());
    assert(await page.locator('.prose ul li').count() >= 2);
    assert(await page.locator('.prose ol li').count() >= 1);
    assert(await page.locator('.prose blockquote').count());
    assert.equal(await page.locator('.prose a').getAttribute('href'), 'https://example.com/manual');
    assert.equal(await page.locator('.prose .ql-align-center').evaluate(node => getComputedStyle(node).textAlign), 'center');
    checks.push('public article renders headings lists quotes links and alignment');
    for (const [name, width] of [['desktop', 1440], ['mobile', 390]]) {
      await page.setViewportSize({ width, height: 1000 });
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      await page.locator('.article-detail').screenshot({ path: path.join(out, `rich-article-${name}.png`) });
      checks.push(name + ' public layout');
    }
    await page.goto(base + edit); const csrf = await page.locator('input[name="csrf"]').first().inputValue();
    const malicious = '<h2>安全测试正文</h2><p><strong>应保留的排版内容</strong></p><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">危险链接</a>';
    const response = await context.request.post(base + '/dgadmin/articles/', { form: { csrf, id: String(fixture.id), kind: 'encyclopedia', category_id: String(fixture.category_id), title: fixture.title, summary: '', body: malicious, body_format: 'html', status: 'published' } });
    assert.equal(response.status(), 200);
    await page.goto(base + edit);
    const stored = await page.locator('#article-body').inputValue();
    assert(!/script|onerror|javascript:|<img/i.test(stored)); assert(stored.includes('<strong>')); checks.push('server strips unsafe markup before storing');
    const rejected = await context.request.post(base + '/dgadmin/articles/', { form: { csrf, id: String(fixture.id), kind: 'encyclopedia', category_id: String(fixture.category_id), title: fixture.title, body: '<p><br></p><script>long malicious contents</script>', body_format: 'html', status: 'published' } });
    assert((await rejected.text()).includes('正文须为')); checks.push('server validates visible text after filtering');
    const anonymous = await browser.newContext();
    const unauthorized = await anonymous.request.post(base + '/dgadmin/articles/', { form: { id: String(fixture.id), body: malicious } });
    assert.equal(unauthorized.status(), 403); await anonymous.close(); checks.push('CSRF protection remains active');
    const noJs = await browser.newContext({ javaScriptEnabled: false });
    await noJs.addCookies([{ name: 'xiu_session', value: fixture.session, url: base }]);
    const fallback = await noJs.newPage(); await fallback.goto(base + edit);
    assert(await fallback.locator('#article-body').isVisible()); await noJs.close(); checks.push('no JavaScript textarea fallback');
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(out, 'article-rich-editor-results.json'), JSON.stringify({ passed: checks.length, checks, errors }, null, 2));
    console.log(JSON.stringify({ passed: checks.length, checks }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
