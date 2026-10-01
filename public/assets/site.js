document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
document.querySelectorAll('input[data-image-limit]').forEach(input => {
  input.addEventListener('change', () => {
    const files = Array.from(input.files);
    const max = Number(input.dataset.imageLimit);
    if (files.length > max || files.some(file => file.size > 5 * 1024 * 1024)) {
      input.setCustomValidity(`最多选择 ${max} 张图片，每张不超过 5MB。`);
      input.reportValidity();
    } else input.setCustomValidity('');
  });
});
const categoryKind = document.querySelector('#category-kind');
const categoryParent = document.querySelector('#category-parent');
if (categoryKind && categoryParent) {
  const updateParents = () => {
    Array.from(categoryParent.options).forEach(option => {
      const disabled = Boolean(option.dataset.kind && option.dataset.kind !== categoryKind.value);
      option.hidden = disabled;
      option.disabled = disabled;
      if (disabled && option.selected) categoryParent.value = '0';
    });
  };
  categoryKind.addEventListener('change', updateParents);
  updateParents();
}
document.querySelectorAll('.category-filter-toggle').forEach(button => {
  const links = document.getElementById(button.getAttribute('aria-controls'));
  if (!links) return;
  button.hidden = false;
  links.addEventListener('focusin', () => {
    if (button.getAttribute('aria-expanded') === 'false') button.click();
  });
  button.addEventListener('click', () => {
    const expanded = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(expanded));
    links.classList.toggle('is-collapsed', !expanded);
    const label = expanded ? '收起类别' : '展开类别';
    button.title = label;
    button.querySelector('.sr-only').textContent = label;
    button.querySelector('[aria-hidden]').textContent = expanded ? '⌃' : '⌄';
    if (!expanded) button.focus();
  });
});
const listingProvince = document.getElementById('listing-province');
const publishCategoryPicker = document.getElementById('publish-category-picker');
if (publishCategoryPicker) {
  const categorySelect = document.getElementById('listing-category');
  const categories = Array.from(categorySelect.options).filter(option => option.value).map(option => ({id: option.value, parent: option.dataset.parent, depth: Number(option.dataset.depth), name: option.dataset.name}));
  const map = new Map(categories.map(category => [category.id, category]));
  const children = id => categories.filter(category => category.parent === id);
  const details = document.getElementById('publish-details');
  const summary = document.querySelector('.publish-category-summary');
  const next = publishCategoryPicker.querySelector('[data-publish-category-next]');
  const help = publishCategoryPicker.querySelector('[data-publish-category-help]');
  const originalId = publishCategoryPicker.dataset.originalCategory;
  const path = () => {
    const chosen = []; let category = map.get(categorySelect.value);
    while (category && chosen.length < 3) { chosen.unshift(category); category = map.get(category.parent); }
    return chosen;
  };
  const canContinue = () => {
    const category = map.get(categorySelect.value);
    return Boolean(category && (category.depth > 1 || children(category.id).length === 0 || category.id === originalId));
  };
  const render = () => {
    const chosen = path();
    const levels = [children('0'), chosen[0] ? children(chosen[0].id) : [], chosen[1] ? children(chosen[1].id) : []];
    levels.forEach((options, index) => {
      const level = publishCategoryPicker.querySelector(`[data-publish-category-level="${index + 1}"]`);
      level.hidden = index === 2 && options.length === 0;
      level.querySelector('.publish-category-grid').replaceChildren(...options.map(category => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'publish-category-choice'; button.dataset.publishCategoryId = category.id; button.textContent = category.name;
        const selected = chosen[index]?.id === category.id;
        button.classList.toggle('is-selected', selected); button.setAttribute('aria-pressed', String(selected));
        button.addEventListener('click', () => { categorySelect.value = category.id; categorySelect.dispatchEvent(new Event('change', {bubbles: true})); publishCategoryPicker.querySelector(`[data-publish-category-id="${category.id}"]`)?.focus({preventScroll: true}); });
        return button;
      }));
      const empty = level.querySelector('.publish-category-empty');
      if (empty) { empty.hidden = options.length > 0; empty.textContent = chosen[0] ? '此大类暂无细分，可直接进入下一步。' : '请先选择一级大类。'; }
    });
    document.querySelectorAll('[data-publish-category-path]').forEach(element => { element.textContent = chosen.map(category => category.name).join(' › ') || '尚未选择'; });
    next.disabled = !canContinue();
    help.textContent = !chosen.length ? '请选择一级大类。' : !canContinue() ? '请选择对应的二级分类。' : levels[2].length && !chosen[2] ? '可继续选择三级分类，也可按当前二级分类发布。' : '分类已选择，点击下一步填写信息。';
  };
  const showDetails = (focus = false) => {
    if (!canContinue()) return;
    publishCategoryPicker.hidden = true; details.hidden = false; summary.hidden = false;
    if (focus) { summary.scrollIntoView({block: 'start'}); details.querySelector('[name="title"]').focus({preventScroll: true}); }
  };
  categorySelect.closest('label').hidden = true; categorySelect.required = false;
  publishCategoryPicker.querySelector('.publish-category-selection').hidden = false;
  publishCategoryPicker.querySelector('.publish-category-next').hidden = false;
  categorySelect.addEventListener('change', render);
  next.addEventListener('click', () => showDetails(true));
  document.querySelector('[data-publish-category-change]').addEventListener('click', () => {
    details.hidden = true; summary.hidden = true; publishCategoryPicker.hidden = false;
    publishCategoryPicker.scrollIntoView({block: 'start'}); publishCategoryPicker.querySelector('.publish-category-choice.is-selected, .publish-category-choice')?.focus({preventScroll: true});
  });
  publishCategoryPicker.closest('form').addEventListener('submit', event => {
    if (!canContinue()) { event.preventDefault(); details.hidden = true; summary.hidden = true; publishCategoryPicker.hidden = false; render(); }
  });
  render();
  if (publishCategoryPicker.dataset.openDetails === '1' && canContinue()) showDetails();
  else details.hidden = true;
}
const listingCity = document.getElementById('listing-city');
const listingDistrict = document.getElementById('listing-district');
if (listingProvince && listingCity && listingDistrict) {
  const cityOptions = Array.from(listingCity.options).filter(option => option.value !== '').map(option => option.cloneNode(true));
  const districtOptions = Array.from(listingDistrict.options).filter(option => option.value !== '0').map(option => option.cloneNode(true));
  const regionStatus = document.getElementById('listing-region-status');
  const updateDistricts = (selected = '0') => {
    const available = districtOptions.filter(option => option.dataset.city === listingCity.value);
    const placeholder = !listingCity.value ? '选择区域' : available.length ? '选择区域 / 全市' : '全市（暂无区域）';
    listingDistrict.replaceChildren(new Option(placeholder, '0'), ...available.map(option => {
      const choice = option.cloneNode(true); choice.textContent = choice.dataset.name; return choice;
    }));
    listingDistrict.disabled = !listingCity.value || available.length === 0;
    listingDistrict.value = available.some(option => option.value === selected) ? selected : '0';
    regionStatus.textContent = !listingProvince.value ? '请先选择省份，再选择城市和区域。' : !listingCity.value ? '请选择城市。' : available.length ? '可进一步选择区域，也可按全市发布。' : '该城市暂无区域数据，将按全市发布。';
  };
  const updateCities = (selectedCity = '', selectedDistrict = '0') => {
    const available = cityOptions.filter(option => option.dataset.province === listingProvince.value);
    listingCity.replaceChildren(new Option('选择城市', ''), ...available.map(option => {
      const choice = option.cloneNode(true); choice.textContent = choice.dataset.name; return choice;
    }));
    listingCity.disabled = !listingProvince.value;
    listingCity.value = available.some(option => option.value === selectedCity) ? selectedCity : '';
    updateDistricts(selectedDistrict);
  };
  listingProvince.addEventListener('change', () => updateCities());
  listingCity.addEventListener('change', () => updateDistricts());
  updateCities(listingCity.value, listingDistrict.value);
}

const listingTitle = document.getElementById('listing-title');
const listingCategory = document.getElementById('listing-category');
const listingAddress = document.getElementById('listing-address');
if (listingTitle && listingCategory && listingCity) {
  const map = new Map(Array.from(listingCategory.options).filter(option => option.value).map(option => [option.value, {name: option.dataset.name, parent: option.dataset.parent}]));
  const help = document.querySelector('[data-title-help]');
  const useSuggestion = document.querySelector('[data-use-title-suggestion]');
  const cleanTitlePart = value => value.replace(/[^\p{L}\p{N}]/gu, '');
  const fitTitlePart = (value, limit) => {
    let text = '';
    for (const character of value) {
      if (text.length + character.length > limit) break;
      text += character;
    }
    return text;
  };
  let automatic = listingTitle.value.trim() === '', suggestion = '';
  const updateTitle = () => {
    const path = []; let category = map.get(listingCategory.value);
    while (category && path.length < 3) { path.unshift(category.name); category = map.get(category.parent); }
    const city = listingCity.value ? listingCity.selectedOptions[0]?.dataset.name : '';
    suggestion = '';
    if (city && path.length) {
      const cityText = cleanTitlePart(city), categoryText = cleanTitlePart(path.join(''));
      const addressLimit = Math.max(0, listingTitle.maxLength - cityText.length - categoryText.length);
      const addressText = fitTitlePart(cleanTitlePart(listingAddress?.value || ''), addressLimit);
      suggestion = fitTitlePart(`${cityText}${addressText}${categoryText}`, listingTitle.maxLength);
    }
    if (automatic) listingTitle.value = suggestion;
    help.textContent = !suggestion ? '选择城市和分类后自动填写标题，服务地址也会加入；您手动填写的标题会保留。' : automatic ? '已根据城市、服务地址和分类自动填写，空格及特殊符号已移除；您可以修改标题。' : `建议标题：${suggestion}`;
    useSuggestion.hidden = !suggestion || automatic || listingTitle.value === suggestion;
  };
  listingTitle.addEventListener('input', () => { automatic = listingTitle.value.trim() === ''; if (!automatic) { help.textContent = suggestion ? `建议标题：${suggestion}` : '已保留您手动填写的标题。'; } useSuggestion.hidden = !suggestion || automatic || listingTitle.value === suggestion; });
  listingCategory.addEventListener('change', updateTitle);
  listingCity.addEventListener('change', updateTitle);
  listingProvince?.addEventListener('change', updateTitle);
  listingAddress?.addEventListener('input', updateTitle);
  listingAddress?.addEventListener('change', updateTitle);
  useSuggestion.addEventListener('click', () => { automatic = true; updateTitle(); listingTitle.focus({preventScroll: true}); });
  updateTitle();
}

document.querySelectorAll('[data-copy-phone]').forEach(button => {
  button.addEventListener('click', async () => {
    const phone = button.dataset.copyPhone;
    const status = button.closest('.repair-contact-card').querySelector('.repair-copy-status');
    let copied = false;
    if (navigator.clipboard && window.isSecureContext) {
      try { await navigator.clipboard.writeText(phone); copied = true; } catch (_) {}
    }
    if (!copied) {
      const buffer = document.createElement('textarea');
      buffer.value = phone;
      buffer.readOnly = true;
      buffer.className = 'repair-copy-buffer';
      document.body.appendChild(buffer);
      buffer.select();
      try { copied = document.execCommand('copy'); } catch (_) {}
      buffer.remove();
      button.focus();
    }
    status.textContent = copied ? '电话号码已复制。' : '复制未成功，请选中或长按电话号码复制。';
  });
});

const citySearch = document.getElementById('city-search');
if (citySearch) {
  document.querySelector('[data-city-search-controls]').hidden = false;
  const items = Array.from(document.querySelectorAll('[data-city-item]'));
  const groups = Array.from(document.querySelectorAll('[data-city-group]'));
  const filterCities = () => {
    const keyword = citySearch.value.trim().toLowerCase().replace(/\s+/g, '');
    let matches = 0;
    items.forEach(item => {
      item.hidden = keyword !== '' && !item.dataset.cityKey.toLowerCase().replace(/\s+/g, '').includes(keyword);
      if (!item.hidden) matches++;
    });
    groups.forEach(group => { group.hidden = !Array.from(group.querySelectorAll('[data-city-item]')).some(item => !item.hidden); });
    document.getElementById('city-search-status').textContent = keyword ? `找到 ${matches} 个城市及地区` : '';
    document.getElementById('city-search-empty').hidden = matches !== 0;
  };
  citySearch.addEventListener('input', filterCities);
  document.getElementById('city-search-clear').addEventListener('click', () => { citySearch.value = ''; filterCities(); citySearch.focus(); });
  document.querySelectorAll('.city-selector-index a').forEach(link => link.addEventListener('click', () => { citySearch.value = ''; filterCities(); }));
}
