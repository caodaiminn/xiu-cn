document.querySelectorAll('[data-navigation-form]').forEach(form => {
  form.querySelector('[data-navigation-preset]').addEventListener('change', event => {
    const option = event.target.selectedOptions[0];
    if (!option.value) return;
    form.elements.label.value = option.dataset.label;
    form.elements.url.value = option.value;
    form.elements.match_mode.value = option.dataset.match;
  });
});
