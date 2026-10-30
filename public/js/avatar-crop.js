'use strict';
(() => {
  const form = document.querySelector('[data-avatar-form]');
  if (!form) return;

  const input = form.querySelector('[data-avatar-file]');
  const modal = form.querySelector('[data-avatar-crop]');
  const viewport = form.querySelector('[data-avatar-viewport]');
  const image = form.querySelector('[data-avatar-crop-image]');
  const zoomInput = form.querySelector('[data-avatar-zoom]');
  const error = form.querySelector('[data-avatar-error]');
  const keyInput = form.querySelector('[data-avatar-key]');
  const currentPreview = form.querySelector('[data-avatar-current]');
  const choices = [...form.querySelectorAll('[data-avatar-choice]')];
  let originalUrl = null;
  let croppedUrl = null;
  let baseScale = 1;
  let scale = 1;
  let offsetX = 0;
  let offsetY = 0;
  let imageWidth = 0;
  let imageHeight = 0;
  let pointer = null;
  let hasCrop = false;

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const size = () => viewport.getBoundingClientRect().width;

  function draw() {
    const side = size();
    imageWidth = image.naturalWidth * baseScale * scale;
    imageHeight = image.naturalHeight * baseScale * scale;
    const left = clamp((side - imageWidth) / 2 + offsetX, side - imageWidth, 0);
    const top = clamp((side - imageHeight) / 2 + offsetY, side - imageHeight, 0);
    offsetX = left - (side - imageWidth) / 2;
    offsetY = top - (side - imageHeight) / 2;
    image.style.width = imageWidth + 'px';
    image.style.height = imageHeight + 'px';
    image.style.left = left + 'px';
    image.style.top = top + 'px';
  }

  function openCrop(file) {
    if (originalUrl) URL.revokeObjectURL(originalUrl);
    originalUrl = URL.createObjectURL(file);
    image.onload = () => {
      modal.hidden = false;
      const side = size();
      baseScale = Math.max(side / image.naturalWidth, side / image.naturalHeight);
      scale = 1;
      offsetX = 0;
      offsetY = 0;
      zoomInput.value = '1';
      draw();
      error.textContent = '';
    };
    image.src = originalUrl;
  }

  function clearUpload() {
    input.value = '';
    hasCrop = false;
    modal.hidden = true;
    if (originalUrl) URL.revokeObjectURL(originalUrl);
    originalUrl = null;
  }

  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      error.textContent = 'اختر صورة JPG أو PNG أو WEBP لا تتجاوز 5 ميجابايت.';
      clearUpload();
      return;
    }
    hasCrop = false;
    openCrop(file);
  });

  zoomInput.addEventListener('input', () => {
    scale = Number(zoomInput.value) || 1;
    draw();
  });

  viewport.addEventListener('pointerdown', (event) => {
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY, ox: offsetX, oy: offsetY };
    viewport.setPointerCapture(event.pointerId);
  });
  viewport.addEventListener('pointermove', (event) => {
    if (!pointer || pointer.id !== event.pointerId) return;
    offsetX = pointer.ox + event.clientX - pointer.x;
    offsetY = pointer.oy + event.clientY - pointer.y;
    draw();
  });
  const stopDrag = () => { pointer = null; };
  viewport.addEventListener('pointerup', stopDrag);
  viewport.addEventListener('pointercancel', stopDrag);

  form.querySelector('[data-avatar-crop-cancel]').addEventListener('click', clearUpload);

  form.querySelector('[data-avatar-crop-apply]').addEventListener('click', () => {
    const side = size();
    const left = Number.parseFloat(image.style.left) || 0;
    const top = Number.parseFloat(image.style.top) || 0;
    const renderedScale = imageWidth / image.naturalWidth;
    const sx = clamp(-left / renderedScale, 0, image.naturalWidth);
    const sy = clamp(-top / renderedScale, 0, image.naturalHeight);
    const sourceSide = Math.min(side / renderedScale, image.naturalWidth - sx, image.naturalHeight - sy);
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const context = canvas.getContext('2d');
    if (!context || sourceSide <= 0) {
      error.textContent = 'تعذر قص الصورة. حاول اختيار صورة أخرى.';
      return;
    }
    context.drawImage(image, sx, sy, sourceSide, sourceSide, 0, 0, 512, 512);
    canvas.toBlob((blob) => {
      if (!blob) {
        error.textContent = 'تعذر تجهيز الصورة بعد القص.';
        return;
      }
      const file = new File([blob], 'profile-avatar.jpg', { type: 'image/jpeg', lastModified: Date.now() });
      const transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
      hasCrop = true;
      if (croppedUrl) URL.revokeObjectURL(croppedUrl);
      croppedUrl = URL.createObjectURL(blob);
      currentPreview.src = croppedUrl;
      modal.hidden = true;
      error.textContent = 'تم قص الصورة. اضغط حفظ الصورة الشخصية لإتمام التحديث.';
    }, 'image/jpeg', 0.9);
  });

  choices.forEach((choice) => {
    choice.addEventListener('click', () => {
      choices.forEach((item) => {
        const selected = item === choice;
        item.classList.toggle('is-selected', selected);
        item.setAttribute('aria-pressed', String(selected));
      });
      keyInput.value = choice.dataset.avatarChoice || 'technician-blue';
      clearUpload();
      if (croppedUrl) URL.revokeObjectURL(croppedUrl);
      croppedUrl = null;
      currentPreview.src = choice.querySelector('img').src;
      error.textContent = '';
    });
  });

  form.addEventListener('submit', (event) => {
    if (input.files && input.files.length && !hasCrop) {
      event.preventDefault();
      error.textContent = 'اعتمد قص الصورة أولًا قبل الحفظ.';
    }
  });

  window.addEventListener('beforeunload', () => {
    if (originalUrl) URL.revokeObjectURL(originalUrl);
    if (croppedUrl) URL.revokeObjectURL(croppedUrl);
  });
})();
