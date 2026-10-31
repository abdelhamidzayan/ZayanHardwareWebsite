'use strict';
(() => {
  document.querySelectorAll('[data-publication-consent-form]').forEach((form) => {
    const yes = form.querySelector('[data-publication-yes]');
    const no = form.querySelector('[data-publication-no]');
    const identity = form.querySelector('[data-publication-identity]');
    if (!yes || !no || !identity) return;

    const update = () => {
      const show = yes.checked;
      identity.hidden = !show;
      identity.querySelectorAll('input[type="radio"]').forEach((input) => {
        input.disabled = !show;
      });
    };
    yes.addEventListener('change', update);
    no.addEventListener('change', update);
    update();
  });
})();
