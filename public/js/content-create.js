'use strict';
(() => {
    const form = document.querySelector('.content-create__form');
    if (!form) return;
    const helpFields = form.querySelector('[data-help-fields]');
    const device = form.querySelector('[name="device_model"]');
    const category = form.querySelector('[name="category"]');
    const measurements = form.querySelector('[name="measurements"]');
    const radios = form.querySelectorAll('[name="content_type"]');

    function syncFields() {
        const selected = form.querySelector('[name="content_type"]:checked');
        const isHelpRequest = selected && selected.value === 'help_request';
        helpFields.hidden = !isHelpRequest;
        device.required = Boolean(isHelpRequest);
        category.required = Boolean(isHelpRequest);
        measurements.required = Boolean(isHelpRequest);
    }

    radios.forEach((radio) => radio.addEventListener('change', syncFields));
    syncFields();
})();
