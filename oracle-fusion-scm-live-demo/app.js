'use strict';
const dialog = document.querySelector('#registration');
const form = document.querySelector('#demo-form');
const status = document.querySelector('#form-status');
const countryCode = document.querySelector('#country-code');
const otherField = document.querySelector('#other-code-field');
let opener;
document.querySelectorAll('[data-register]').forEach(button => {
  button.addEventListener('click', () => {
    opener = button;
    status.textContent = '';
    dialog.showModal();
    document.body.classList.add('modal-open');
    form.elements.name.focus();
  });
});
document.querySelector('.close').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => {
  const rect = dialog.getBoundingClientRect();
  if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
});
dialog.addEventListener('close', () => {
  document.body.classList.remove('modal-open');
  opener?.focus();
});
countryCode.addEventListener('change', () => {
  const other = countryCode.value === 'other';
  otherField.hidden = !other;
  form.elements.otherCountryCode.disabled = !other;
  form.elements.otherCountryCode.required = other;
});
form.addEventListener('submit', event => {
  event.preventDefault();
  // Deliberately no requests, analytics, browser storage, or lead-bridge integration.
  // Google Sheets destination and submission consent will be configured separately.
  status.textContent = 'This is a preview. Your seat has not been booked and your details have not been sent or saved. Registration will open once setup is complete.';
  status.focus();
});
