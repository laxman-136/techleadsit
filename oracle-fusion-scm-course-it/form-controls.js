'use strict';

// Scoped select-only comboboxes. Native selects remain the form's data source
// and the fallback when this enhancement cannot load.
window.SCMFormControls = (() => {
  let active;
  const chevron = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const check = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4.5 10 3.5 3.5 7.5-7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  document.addEventListener('pointerdown', event => { if (active && !active.field.contains(event.target)) active.close(); });
  window.addEventListener('resize', () => active?.position());
  document.addEventListener('scroll', () => active?.position(), true);

  function enhance(form) {
    const controls = new Map();
    form.querySelectorAll('select').forEach(select => {
      const originalLabel = select.closest('label');
      if (!originalLabel) return;
      const id = form.id + '-' + select.name;
      const field = document.createElement('div');
      field.className = 'form-field select-field';
      field.dataset.selectName = select.name;
      const label = document.createElement('label');
      label.id = id + '-label'; label.htmlFor = id + '-control';
      label.append(...Array.from(originalLabel.childNodes).filter(node => node !== select));
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'select-control'; button.id = id + '-control';
      button.setAttribute('role', 'combobox'); button.setAttribute('aria-haspopup', 'listbox');
      button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-controls', id + '-list');
      button.setAttribute('aria-labelledby', label.id + ' ' + id + '-value');
      button.setAttribute('aria-required', String(select.required));
      const value = document.createElement('span'); value.id = id + '-value'; value.className = 'select-value';
      button.append(value); button.insertAdjacentHTML('beforeend', chevron);
      const menu = document.createElement('div'); menu.className = 'select-menu'; menu.hidden = true;
      const list = document.createElement('div'); list.id = id + '-list'; list.setAttribute('role', 'listbox');
      list.setAttribute('aria-labelledby', label.id); menu.append(list);
      const error = document.createElement('p'); error.className = 'field-error'; error.id = id + '-error'; error.hidden = true;
      error.textContent = 'Choose an option to continue.';
      const options = Array.from(select.options).filter(option => option.value !== '').map((option, index) => {
        const item = document.createElement('button'); item.type = 'button'; item.tabIndex = -1;
        item.className = 'select-option'; item.id = id + '-option-' + index;
        item.setAttribute('role', 'option'); item.disabled = option.disabled;
        const text = document.createElement('span'); text.textContent = option.textContent.trim();
        item.append(text); item.insertAdjacentHTML('beforeend', check); list.append(item);
        item.addEventListener('pointerdown', event => event.preventDefault());
        item.addEventListener('pointermove', () => highlight(index, false));
        item.addEventListener('click', () => commit(index));
        return { option, item };
      });
      originalLabel.replaceWith(field); field.append(label, select, button, menu, error);
      select.hidden = true; select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');
      let current = 0, query = '', queryTimer;

      function sync() {
        const selected = select.selectedOptions[0];
        value.textContent = select.name === 'countryCode' && select.value !== 'other' ? select.value : selected?.textContent.trim() || 'Select an option';
        button.classList.toggle('is-placeholder', !select.value);
        button.removeAttribute('aria-invalid'); button.removeAttribute('aria-describedby'); error.hidden = true;
        options.forEach(({ option, item }) => item.setAttribute('aria-selected', String(option.value === select.value)));
      }
      function highlight(index, scroll = true) {
        current = (index + options.length) % options.length;
        options.forEach(({ item }, i) => item.classList.toggle('is-active', i === current));
        button.setAttribute('aria-activedescendant', options[current].item.id);
        if (scroll) options[current].item.scrollIntoView({ block: 'nearest' });
      }
      function position() {
        if (menu.hidden) return;
        const rect = button.getBoundingClientRect();
        const boundary = form.closest('dialog') || form.closest('.hero');
        const bounds = boundary?.getBoundingClientRect();
        const actionBar = form.closest('dialog') ? null : document.querySelector('.bottom-action-bar');
        const viewportBottom = actionBar && getComputedStyle(actionBar).visibility !== 'hidden'
          ? Math.min(innerHeight - 12, actionBar.getBoundingClientRect().top - 12) : innerHeight - 12;
        const below = Math.min(viewportBottom, bounds ? bounds.bottom - 12 : viewportBottom) - rect.bottom - 8;
        const above = rect.top - Math.max(12, bounds ? bounds.top + 12 : 12) - 8;
        const upward = below < 220 && above > below;
        menu.dataset.side = upward ? 'above' : 'below';
        const fieldRect = field.getBoundingClientRect();
        menu.style.top = upward ? 'auto' : rect.bottom - fieldRect.top + 7 + 'px';
        menu.style.bottom = upward ? fieldRect.bottom - rect.top + 7 + 'px' : 'auto';
        menu.style.maxHeight = Math.max(96, Math.min(304, upward ? above : below)) + 'px';
      }
      function close(focus = false) {
        menu.hidden = true; button.setAttribute('aria-expanded', 'false'); button.removeAttribute('aria-activedescendant');
        field.classList.remove('is-open'); query = ''; clearTimeout(queryTimer);
        if (active === control) active = undefined;
        if (focus) button.focus();
      }
      function open() {
        if (select.matches(':disabled')) return;
        if (active && active !== control) active.close();
        active = control; menu.hidden = false; field.classList.add('is-open'); button.setAttribute('aria-expanded', 'true');
        position(); const index = options.findIndex(({ option }) => option.value === select.value);
        highlight(index < 0 ? 0 : index);
      }
      function commit(index) {
        if (options[index].option.disabled) return;
        select.value = options[index].option.value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        sync(); close(true);
      }
      const control = { field, close, position, sync, validate() {
        if (!select.required || select.value) return true;
        button.setAttribute('aria-invalid', 'true'); button.setAttribute('aria-describedby', error.id); error.hidden = false;
        button.focus(); return false;
      } };
      controls.set(select, control);
      select.addEventListener('change', sync);
      select.addEventListener('invalid', event => { event.preventDefault(); control.validate(); });
      button.addEventListener('click', () => menu.hidden ? open() : close());
      button.addEventListener('keydown', event => {
        const key = event.key;
        if (key === 'Tab') { close(); return; }
        if (key === 'Escape') { if (!menu.hidden) { event.preventDefault(); event.stopPropagation(); close(true); } return; }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(key)) {
          event.preventDefault();
          if (menu.hidden) { open(); return; }
          if (key === 'Enter' || key === ' ') { commit(current); return; }
          highlight(key === 'Home' ? 0 : key === 'End' ? options.length - 1 : current + (key === 'ArrowUp' ? -1 : 1));
          return;
        }
        if (key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
          event.preventDefault(); if (menu.hidden) open();
          query += key.toLowerCase(); clearTimeout(queryTimer); queryTimer = setTimeout(() => { query = ''; }, 650);
          const index = options.findIndex(({ option }) => option.textContent.toLowerCase().includes(query));
          if (index >= 0) highlight(index);
        }
      });
      sync();
    });
    return controls;
  }
  return { enhance };
})();
