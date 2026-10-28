'use strict';
(() => {
    const dialog = document.querySelector('[data-image-viewer-dialog]');
    if (!dialog) return;
    const image = dialog.querySelector('[data-image-viewer-image]');
    const viewport = dialog.querySelector('[data-image-viewer-viewport]');
    const closeButton = dialog.querySelector('[data-image-viewer-close]');
    const zoomIn = dialog.querySelector('[data-image-zoom-in]');
    const zoomOut = dialog.querySelector('[data-image-zoom-out]');
    let scale = 1;
    let x = 0;
    let y = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let originX = 0;
    let originY = 0;

    function render() {
        image.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + scale + ')';
        image.style.cursor = scale > 1 ? (dragging ? 'grabbing' : 'grab') : 'zoom-in';
    }
    function reset() { scale = 1; x = 0; y = 0; render(); }
    function setScale(next, centerX, centerY) {
        const previous = scale;
        scale = Math.max(1, Math.min(5, next));
        if (centerX !== undefined && centerY !== undefined && previous !== scale) {
            const rect = viewport.getBoundingClientRect();
            const px = centerX - rect.left - rect.width / 2;
            const py = centerY - rect.top - rect.height / 2;
            x = px - (px - x) * (scale / previous);
            y = py - (py - y) * (scale / previous);
        }
        if (scale === 1) { x = 0; y = 0; }
        render();
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-image-viewer]');
        if (!trigger) return;
        event.preventDefault();
        image.src = trigger.dataset.imageSrc || trigger.href;
        reset();
        if (!dialog.open) dialog.showModal();
    });
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    zoomIn.addEventListener('click', () => setScale(scale + 0.5));
    zoomOut.addEventListener('click', () => setScale(scale - 0.5));
    viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        setScale(scale + (event.deltaY < 0 ? 0.2 : -0.2), event.clientX, event.clientY);
    }, { passive: false });
    image.addEventListener('pointerdown', (event) => {
        if (scale <= 1) return;
        dragging = true;
        startX = event.clientX;
        startY = event.clientY;
        originX = x;
        originY = y;
        image.setPointerCapture(event.pointerId);
        render();
    });
    image.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        x = originX + event.clientX - startX;
        y = originY + event.clientY - startY;
        render();
    });
    function stopDragging() { dragging = false; render(); }
    image.addEventListener('pointerup', stopDragging);
    image.addEventListener('pointercancel', stopDragging);
    dialog.addEventListener('close', () => { image.removeAttribute('src'); reset(); });
})();
