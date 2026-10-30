'use strict';
(() => {
    const dialog = document.querySelector('[data-image-viewer-dialog]');
    if (!dialog) return;
    const image = dialog.querySelector('[data-image-viewer-image]');
    const viewport = dialog.querySelector('[data-image-viewer-viewport]');
    const closeButton = dialog.querySelector('[data-image-viewer-close]');
    const zoomIn = dialog.querySelector('[data-image-zoom-in]');
    const zoomOut = dialog.querySelector('[data-image-zoom-out]');
    const previousButton = dialog.querySelector('[data-image-prev]');
    const nextButton = dialog.querySelector('[data-image-next]');
    const fullscreenButton = dialog.querySelector('[data-image-fullscreen]');
    const counter = dialog.querySelector('[data-image-viewer-counter]');
    let gallery = [];
    let currentIndex = 0;
    let scale = 1;
    let x = 0;
    let y = 0;
    let dragging = false;
    let pointerStartX = 0;
    let pointerStartY = 0;
    let originX = 0;
    let originY = 0;
    let swipeStartX = 0;
    let swipeStartY = 0;

    function render() {
        image.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + scale + ')';
        image.style.cursor = scale > 1 ? (dragging ? 'grabbing' : 'grab') : 'zoom-in';
    }

    function reset() {
        scale = 1;
        x = 0;
        y = 0;
        render();
        viewport.classList.remove('is-zoomed');
    }

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
        viewport.classList.toggle('is-zoomed', scale > 1);
        render();
    }

    function itemUrl(item) {
        return item.dataset.imageSrc || item.href;
    }

    function showItem(index) {
        if (!gallery.length) return;
        currentIndex = (index + gallery.length) % gallery.length;
        const item = gallery[currentIndex];
        image.alt = item.querySelector('img')?.alt || item.getAttribute('aria-label') || 'صورة فنية';
        image.src = itemUrl(item);
        reset();
        counter.textContent = (currentIndex + 1) + ' / ' + gallery.length;
        previousButton.hidden = gallery.length < 2;
        nextButton.hidden = gallery.length < 2;
    }

    function open(trigger) {
        const group = trigger.dataset.imageGallery;
        if (group) {
            gallery = Array.from(document.querySelectorAll('[data-image-viewer][data-image-gallery="' + CSS.escape(group) + '"]'));
        } else {
            const container = trigger.closest('[data-image-gallery]');
            gallery = container
                ? Array.from(container.querySelectorAll('[data-image-viewer]'))
                : [trigger];
        }
        if (!gallery.length) gallery = [trigger];
        const selected = gallery.indexOf(trigger);
        showItem(selected >= 0 ? selected : 0);
        if (!dialog.open) dialog.showModal();
    }

    function close() {
        if (document.fullscreenElement === dialog && document.exitFullscreen) {
            document.exitFullscreen().catch(() => {});
        }
        if (dialog.open) dialog.close();
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-image-viewer]');
        if (!trigger) return;
        event.preventDefault();
        open(trigger);
    });

    closeButton.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    previousButton.addEventListener('click', () => showItem(currentIndex - 1));
    nextButton.addEventListener('click', () => showItem(currentIndex + 1));
    zoomIn.addEventListener('click', () => setScale(scale + 0.5));
    zoomOut.addEventListener('click', () => setScale(scale - 0.5));

    fullscreenButton.addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement && dialog.requestFullscreen) {
                await dialog.requestFullscreen();
                fullscreenButton.textContent = 'إنهاء ملء الشاشة';
            } else if (document.exitFullscreen) {
                await document.exitFullscreen();
                fullscreenButton.textContent = 'ملء الشاشة';
            }
        } catch (_) {
            fullscreenButton.textContent = 'ملء الشاشة غير متاح';
        }
    });
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) fullscreenButton.textContent = 'ملء الشاشة';
    });

    viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        setScale(scale + (event.deltaY < 0 ? 0.2 : -0.2), event.clientX, event.clientY);
    }, { passive: false });

    image.addEventListener('pointerdown', (event) => {
        if (scale <= 1) {
            swipeStartX = event.clientX;
            swipeStartY = event.clientY;
            return;
        }
        dragging = true;
        pointerStartX = event.clientX;
        pointerStartY = event.clientY;
        originX = x;
        originY = y;
        image.setPointerCapture(event.pointerId);
        render();
    });
    image.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        x = originX + event.clientX - pointerStartX;
        y = originY + event.clientY - pointerStartY;
        render();
    });
    function stopDragging(event) {
        if (dragging) {
            dragging = false;
            render();
            return;
        }
        if (scale > 1) return;
        const dx = event.clientX - swipeStartX;
        const dy = event.clientY - swipeStartY;
        if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.3 && gallery.length > 1) {
            showItem(currentIndex + (dx < 0 ? 1 : -1));
        }
    }
    image.addEventListener('pointerup', stopDragging);
    image.addEventListener('pointercancel', () => { dragging = false; render(); });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') { event.preventDefault(); showItem(currentIndex + 1); }
        if (event.key === 'ArrowLeft') { event.preventDefault(); showItem(currentIndex - 1); }
    });
    document.addEventListener('keydown', (event) => {
        if (!dialog.open) return;
        if (event.key === 'Escape') close();
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        gallery = [];
        reset();
        fullscreenButton.textContent = 'ملء الشاشة';
    });
})();
