'use strict';
(() => {
    const dialog = document.querySelector('[data-image-editor-dialog]');
    if (!dialog) return;
    const canvas = dialog.querySelector('[data-image-editor-canvas]');
    const context = canvas.getContext('2d');
    const editor = dialog.querySelector('[data-image-editor]');
    const textEntry = dialog.querySelector('[data-image-text-entry]');
    const textInput = dialog.querySelector('[data-image-text]');
    const colorInput = dialog.querySelector('[data-image-color]');
    const sizeInput = dialog.querySelector('[data-image-size]');
    const image = new Image();

    let sourceItem = null;
    let onSave = null;
    let state = null;
    let tool = 'select';
    let selectedId = null;
    let pointerStart = null;
    let activeObject = null;
    let activeAction = '';
    let undoStack = [];
    let redoStack = [];
    let renderScale = 1;
    let pendingTextPoint = null;
    let cropPreview = null;
    let drawing = false;

    function newId() {
        return 'shape-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function defaultState() {
        return {
            version: 1,
            crop: { x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight },
            objects: []
        };
    }

    function normalizeState(input) {
        const value = input && typeof input === 'object' ? clone(input) : defaultState();
        if (!value.crop || !Number.isFinite(value.crop.width) || !Number.isFinite(value.crop.height)) {
            value.crop = { x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight };
        }
        value.crop.x = Math.max(0, Math.min(image.naturalWidth - 1, Number(value.crop.x) || 0));
        value.crop.y = Math.max(0, Math.min(image.naturalHeight - 1, Number(value.crop.y) || 0));
        value.crop.width = Math.max(1, Math.min(image.naturalWidth - value.crop.x, Number(value.crop.width) || image.naturalWidth));
        value.crop.height = Math.max(1, Math.min(image.naturalHeight - value.crop.y, Number(value.crop.height) || image.naturalHeight));
        if (!Array.isArray(value.objects)) value.objects = [];
        value.objects = value.objects.filter(function (item) {
            return item && typeof item === 'object' && typeof item.type === 'string';
        });
        return value;
    }

    function pushHistory() {
        undoStack.push(clone(state));
        if (undoStack.length > 60) undoStack.shift();
        redoStack = [];
    }

    function saveProgress() {
        if (sourceItem && window.ZayanComposer && typeof window.ZayanComposer.saveImageEditorDraft === 'function') {
            window.ZayanComposer.saveImageEditorDraft(sourceItem, clone(state));
        }
    }

    function fitCanvas() {
        const crop = state.crop;
        renderScale = Math.min(1, 2048 / Math.max(crop.width, crop.height));
        canvas.width = Math.max(1, Math.round(crop.width * renderScale));
        canvas.height = Math.max(1, Math.round(crop.height * renderScale));
        canvas.style.aspectRatio = canvas.width + ' / ' + canvas.height;
    }

    function toCanvas(point) {
        return {
            x: (point.x - state.crop.x) * renderScale,
            y: (point.y - state.crop.y) * renderScale
        };
    }

    function fromCanvas(point) {
        return {
            x: state.crop.x + point.x / renderScale,
            y: state.crop.y + point.y / renderScale
        };
    }

    function getPoint(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * (canvas.width / rect.width),
            y: (event.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function objectPoint(value) {
        return toCanvas({ x: Number(value.x) || 0, y: Number(value.y) || 0 });
    }

    function drawObject(object) {
        context.save();
        context.strokeStyle = object.color || '#2563eb';
        context.fillStyle = object.color || '#2563eb';
        context.lineWidth = Math.max(1, Number(object.size) || 4) * renderScale;
        context.lineCap = 'round';
        context.lineJoin = 'round';

        if (object.type === 'text') {
            const point = objectPoint(object);
            context.font = '600 ' + (Number(object.fontSize) || 46) * renderScale + 'px Arial, sans-serif';
            context.textBaseline = 'top';
            const lines = String(object.text || '').split('\n');
            lines.forEach(function (line, index) {
                context.fillText(line, point.x, point.y + index * (Number(object.fontSize) || 46) * renderScale * 1.2);
            });
        } else if (object.type === 'pen' && Array.isArray(object.points) && object.points.length) {
            context.beginPath();
            const first = objectPoint(object.points[0]);
            context.moveTo(first.x, first.y);
            object.points.slice(1).forEach(function (point) {
                const converted = objectPoint(point);
                context.lineTo(converted.x, converted.y);
            });
            context.stroke();
        } else {
            const a = objectPoint(object);
            const b = objectPoint({ x: object.x2, y: object.y2 });
            const left = Math.min(a.x, b.x);
            const top = Math.min(a.y, b.y);
            const width = Math.abs(b.x - a.x);
            const height = Math.abs(b.y - a.y);
            context.beginPath();
            if (object.type === 'rect') {
                context.rect(left, top, width, height);
                context.stroke();
            } else if (object.type === 'circle') {
                context.ellipse(left + width / 2, top + height / 2, Math.max(1, width / 2), Math.max(1, height / 2), 0, 0, Math.PI * 2);
                context.stroke();
            } else if (object.type === 'line' || object.type === 'arrow') {
                context.moveTo(a.x, a.y);
                context.lineTo(b.x, b.y);
                context.stroke();
                if (object.type === 'arrow') {
                    const angle = Math.atan2(b.y - a.y, b.x - a.x);
                    const head = Math.max(10, Number(object.size) * 4) * renderScale;
                    context.beginPath();
                    context.moveTo(b.x, b.y);
                    context.lineTo(b.x - head * Math.cos(angle - Math.PI / 6), b.y - head * Math.sin(angle - Math.PI / 6));
                    context.moveTo(b.x, b.y);
                    context.lineTo(b.x - head * Math.cos(angle + Math.PI / 6), b.y - head * Math.sin(angle + Math.PI / 6));
                    context.stroke();
                }
            }
        }

        if (object.id === selectedId) drawSelection(object);
        context.restore();
    }

    function drawSelection(object) {
        context.save();
        context.strokeStyle = '#0ea5e9';
        context.lineWidth = 2;
        context.setLineDash([7, 5]);
        const bounds = objectBounds(object);
        if (bounds) {
            context.strokeRect(bounds.x - 7, bounds.y - 7, bounds.width + 14, bounds.height + 14);
            context.setLineDash([]);
            context.fillStyle = '#ffffff';
            context.strokeStyle = '#0ea5e9';
            context.fillRect(bounds.x + bounds.width, bounds.y + bounds.height, 12, 12);
            context.strokeRect(bounds.x + bounds.width, bounds.y + bounds.height, 12, 12);
        }
        context.restore();
    }

    function objectBounds(object) {
        if (object.type === 'pen' && Array.isArray(object.points) && object.points.length) {
            const points = object.points.map(objectPoint);
            const xs = points.map(function (p) { return p.x; });
            const ys = points.map(function (p) { return p.y; });
            const left = Math.min.apply(null, xs);
            const top = Math.min.apply(null, ys);
            return { x: left, y: top, width: Math.max(1, Math.max.apply(null, xs) - left), height: Math.max(1, Math.max.apply(null, ys) - top) };
        }
        if (object.type === 'text') {
            const point = objectPoint(object);
            context.save();
            context.font = '600 ' + (Number(object.fontSize) || 46) * renderScale + 'px Arial, sans-serif';
            const lines = String(object.text || '').split('\n');
            const width = Math.max(24, ...lines.map(function (line) { return context.measureText(line).width; }));
            context.restore();
            return { x: point.x, y: point.y, width, height: lines.length * (Number(object.fontSize) || 46) * renderScale * 1.2 };
        }
        const a = objectPoint(object);
        const b = objectPoint({ x: object.x2, y: object.y2 });
        return { x: Math.min(a.x, b.x), y: Math.min(a.y, b.y), width: Math.abs(b.x - a.x), height: Math.abs(b.y - a.y) };
    }

    function draw() {
        if (!image.complete || !image.naturalWidth || !state) return;
        fitCanvas();
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(
            image,
            state.crop.x, state.crop.y, state.crop.width, state.crop.height,
            0, 0, canvas.width, canvas.height
        );
        state.objects.forEach(drawObject);
        if (cropPreview) {
            const a = toCanvas(cropPreview.start);
            const b = toCanvas(cropPreview.end);
            context.save();
            context.fillStyle = 'rgba(15,23,42,.32)';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.clearRect(Math.min(a.x, b.x), Math.min(a.y, b.y), Math.abs(b.x - a.x), Math.abs(b.y - a.y));
            context.strokeStyle = '#ffffff';
            context.lineWidth = 2;
            context.setLineDash([8, 5]);
            context.strokeRect(Math.min(a.x, b.x), Math.min(a.y, b.y), Math.abs(b.x - a.x), Math.abs(b.y - a.y));
            context.restore();
        }
    }

    function hitTest(point) {
        for (let index = state.objects.length - 1; index >= 0; index--) {
            const object = state.objects[index];
            const bounds = objectBounds(object);
            if (!bounds) continue;
            const padding = 18;
            if (point.x >= bounds.x - padding && point.x <= bounds.x + bounds.width + padding
                && point.y >= bounds.y - padding && point.y <= bounds.y + bounds.height + padding) {
                return object;
            }
        }
        return null;
    }

    function setTool(next) {
        tool = next;
        dialog.querySelectorAll('[data-image-tool]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.imageTool === tool);
        });
        textEntry.hidden = tool !== 'text';
        canvas.style.cursor = tool === 'select' ? 'default' : 'crosshair';
    }

    function updateSelectedProperty(key, value) {
        const object = state.objects.find(function (item) { return item.id === selectedId; });
        if (!object) return;
        pushHistory();
        object[key] = value;
        draw();
    }

    function pointerDown(event) {
        if (!state) return;
        event.preventDefault();
        const point = fromCanvas(getPoint(event));
        pointerStart = point;
        drawing = true;
        canvas.setPointerCapture(event.pointerId);

        if (tool === 'text') {
            pendingTextPoint = point;
            textEntry.hidden = false;
            textInput.focus();
            drawing = false;
            return;
        }
        if (tool === 'eraser') {
            const target = hitTest(getPoint(event));
            if (target) {
                pushHistory();
                state.objects = state.objects.filter(function (item) { return item.id !== target.id; });
                selectedId = null;
                draw();
            }
            drawing = false;
            return;
        }
        if (tool === 'select') {
            const clickPoint = getPoint(event);
            const target = hitTest(clickPoint);
            selectedId = target ? target.id : null;
            activeObject = target;
            activeAction = target ? 'move' : '';
            if (target) {
                const bounds = objectBounds(target);
                const handleX = bounds.x + bounds.width;
                const handleY = bounds.y + bounds.height;
                if (Math.abs(clickPoint.x - handleX) <= 18 && Math.abs(clickPoint.y - handleY) <= 18) activeAction = 'resize';
                pushHistory();
            }
            textEntry.hidden = !(target && target.type === 'text');
            if (target && target.type === 'text') {
                textInput.value = target.text || '';
                dialog.querySelector('[data-image-add-text]').textContent = 'تحديث النص';
            } else {
                dialog.querySelector('[data-image-add-text]').textContent = 'إضافة النص';
            }
            draw();
            return;
        }
        pushHistory();
        if (tool === 'pen') {
            activeObject = { id: newId(), type: 'pen', points: [point], color: colorInput.value, size: Number(sizeInput.value) };
        } else if (tool === 'crop') {
            cropPreview = { start: point, end: point };
            activeObject = null;
        } else {
            activeObject = {
                id: newId(),
                type: tool,
                x: point.x, y: point.y,
                x2: point.x, y2: point.y,
                color: colorInput.value,
                size: Number(sizeInput.value)
            };
        }
        if (activeObject && tool !== 'crop') {
            state.objects.push(activeObject);
            selectedId = activeObject.id;
        }
        draw();
    }

    function pointerMove(event) {
        if (!drawing || !pointerStart || !state) return;
        const point = fromCanvas(getPoint(event));
        if (tool === 'select' && activeObject && activeAction === 'move') {
            const dx = point.x - pointerStart.x;
            const dy = point.y - pointerStart.y;
            moveObject(activeObject, dx, dy);
            pointerStart = point;
        } else if (tool === 'select' && activeObject && activeAction === 'resize') {
            resizeObject(activeObject, point);
        } else if (tool === 'crop' && cropPreview) {
            cropPreview.end = point;
        } else if (tool === 'pen' && activeObject) {
            activeObject.points.push(point);
        } else if (activeObject) {
            activeObject.x2 = point.x;
            activeObject.y2 = point.y;
        }
        draw();
    }

    function moveObject(object, dx, dy) {
        if (object.type === 'pen') {
            object.points.forEach(function (point) { point.x += dx; point.y += dy; });
        } else {
            object.x += dx;
            object.y += dy;
            if (Number.isFinite(object.x2)) object.x2 += dx;
            if (Number.isFinite(object.y2)) object.y2 += dy;
        }
    }

    function resizeObject(object, point) {
        if (object.type === 'text') {
            const delta = point.x - Number(object.x2 || object.x);
            object.fontSize = Math.max(18, Math.min(220, (Number(object.fontSize) || 46) + delta * 0.45));
            object.size = Math.max(1, Math.min(24, object.fontSize / 12));
            object.x2 = point.x;
            return;
        }
        object.x2 = point.x;
        object.y2 = point.y;
    }

    function pointerUp() {
        if (!drawing || !state) return;
        drawing = false;
        if (tool === 'crop' && cropPreview) {
            const x1 = Math.min(cropPreview.start.x, cropPreview.end.x);
            const y1 = Math.min(cropPreview.start.y, cropPreview.end.y);
            const width = Math.abs(cropPreview.end.x - cropPreview.start.x);
            const height = Math.abs(cropPreview.end.y - cropPreview.start.y);
            if (width > 20 && height > 20) state.crop = {
                x: Math.max(0, Math.min(image.naturalWidth - 1, x1)),
                y: Math.max(0, Math.min(image.naturalHeight - 1, y1)),
                width: Math.max(1, Math.min(image.naturalWidth - x1, width)),
                height: Math.max(1, Math.min(image.naturalHeight - y1, height))
            };
            cropPreview = null;
        }
        activeObject = null;
        activeAction = '';
        draw();
        saveProgress();
    }

    function addText() {
        const text = textInput.value.trim();
        if (!text) return;
        pushHistory();
        const point = pendingTextPoint || { x: state.crop.x + state.crop.width / 2, y: state.crop.y + state.crop.height / 2 };
        const selectedText = state.objects.find(function (item) { return item.id === selectedId && item.type === 'text'; });
        if (selectedText && !pendingTextPoint) {
            pushHistory();
            selectedText.text = text;
            selectedText.color = colorInput.value;
            selectedText.size = Number(sizeInput.value);
            selectedText.fontSize = Math.max(18, Number(sizeInput.value) * 12);
            textInput.value = '';
            dialog.querySelector('[data-image-add-text]').textContent = 'إضافة النص';
            textEntry.hidden = true;
            draw();
            saveProgress();
            return;
        }
        const object = {
            id: newId(),
            type: 'text',
            text: text,
            x: point.x,
            y: point.y,
            color: colorInput.value,
            size: Number(sizeInput.value),
            fontSize: Math.max(18, Number(sizeInput.value) * 12)
        };
        state.objects.push(object);
        selectedId = object.id;
        textInput.value = '';
        pendingTextPoint = null;
        textEntry.hidden = true;
        setTool('select');
        dialog.querySelector('[data-image-add-text]').textContent = 'إضافة النص';
        draw();
        saveProgress();
    }

    function undo() {
        if (!undoStack.length) return;
        redoStack.push(clone(state));
        state = undoStack.pop();
        selectedId = null;
        draw();
        saveProgress();
    }

    function redo() {
        if (!redoStack.length) return;
        undoStack.push(clone(state));
        state = redoStack.pop();
        selectedId = null;
        draw();
        saveProgress();
    }

    async function renderBlob() {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) resolve(blob);
                else reject(new Error('تعذر تجهيز نسخة الصورة المعدلة.'));
            }, 'image/webp', 0.92);
        });
    }

    async function open(item, callback) {
        sourceItem = item;
        onSave = callback;
        selectedId = null;
        undoStack = [];
        redoStack = [];
        cropPreview = null;
        pendingTextPoint = null;
        textEntry.hidden = true;
        setTool('select');
        image.onload = function () {
            state = normalizeState(item.editor_state || null);
            draw();
            if (!dialog.open) dialog.showModal();
        };
        image.onerror = function () {
            window.alert('تعذر فتح الصورة الأصلية للتعديل.');
        };
        image.src = item.original_url || item.url;
        if (image.complete && image.naturalWidth) {
            state = normalizeState(item.editor_state || null);
            draw();
            if (!dialog.open) dialog.showModal();
        }
    }

    function close() {
        if (dialog.open) dialog.close();
        sourceItem = null;
        onSave = null;
        state = null;
        context.clearRect(0, 0, canvas.width, canvas.height);
    }

    dialog.querySelectorAll('[data-image-tool]').forEach(function (button) {
        button.addEventListener('click', function () { setTool(button.dataset.imageTool); });
    });
    dialog.querySelector('[data-image-editor-cancel]').addEventListener('click', close);
    dialog.querySelector('[data-image-add-text]').addEventListener('click', addText);
    dialog.querySelector('[data-image-undo]').addEventListener('click', undo);
    dialog.querySelector('[data-image-redo]').addEventListener('click', redo);
    dialog.querySelector('[data-image-clear]').addEventListener('click', function () {
        if (!state) return;
        if (!window.confirm('مسح كل الكتابة والرسومات وإلغاء القص؟')) return;
        pushHistory();
        state = defaultState();
        selectedId = null;
        draw();
        saveProgress();
    });
    dialog.querySelector('[data-image-editor-save]').addEventListener('click', async function () {
        if (!state || !onSave) return;
        const button = dialog.querySelector('[data-image-editor-save]');
        button.disabled = true;
        try {
            const blob = await renderBlob();
            await onSave({ state: clone(state), renderedBlob: blob });
            close();
        } catch (error) {
            window.alert(error.message || 'تعذر حفظ الصورة.');
        } finally {
            button.disabled = false;
        }
    });
    colorInput.addEventListener('change', function () { updateSelectedProperty('color', colorInput.value); saveProgress(); });
    sizeInput.addEventListener('change', function () {
        updateSelectedProperty('size', Number(sizeInput.value));
        const selected = state.objects.find(function (item) { return item.id === selectedId; });
        if (selected && selected.type === 'text') selected.fontSize = Math.max(18, Number(sizeInput.value) * 12);
        draw();
        saveProgress();
    });
    canvas.addEventListener('pointerdown', pointerDown);
    canvas.addEventListener('pointermove', pointerMove);
    canvas.addEventListener('pointerup', pointerUp);
    canvas.addEventListener('pointercancel', pointerUp);
    window.addEventListener('keydown', function (event) {
        if (!dialog.open) return;
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            event.shiftKey ? redo() : undo();
        }
        if (event.key === 'Escape') close();
        if ((event.key === 'Delete' || event.key === 'Backspace') && selectedId
            && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            pushHistory();
            state.objects = state.objects.filter(function (item) { return item.id !== selectedId; });
            selectedId = null;
            draw();
        }
    });

    window.ZayanImageEditor = { open: open, close: close };
})();
