'use strict';
const dialog = document.querySelector('#registration');
const form = document.querySelector('#demo-form');
const status = document.querySelector('#form-status');

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
document.querySelectorAll('form').forEach(currentForm => {
  const countryCode = currentForm.elements.countryCode;
  const otherField = currentForm.elements.otherCountryCode.closest('label');
  const message = currentForm.querySelector('[role="status"]');
  countryCode.addEventListener('change', () => {
    const other = countryCode.value === 'other';
    otherField.hidden = !other;
    currentForm.elements.otherCountryCode.disabled = !other;
    currentForm.elements.otherCountryCode.required = other;
  });
  currentForm.addEventListener('submit', event => {
    event.preventDefault();
    // Preview only: no network requests, browser storage, or lead integrations.
    message.textContent = 'This is a preview. Your seat has not been booked and your details have not been sent or saved. Registration will open once setup is complete.';
    message.focus();
  });
});
