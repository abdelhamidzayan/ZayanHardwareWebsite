'use strict';
(() => {
    const root = document.querySelector('[data-audio-recorder]');
    if (!root) return;

    const startButton = root.querySelector('[data-audio-start]');
    const pauseButton = root.querySelector('[data-audio-pause]');
    const resumeButton = root.querySelector('[data-audio-resume]');
    const stopButton = root.querySelector('[data-audio-stop]');
    const deleteButton = root.querySelector('[data-audio-delete]');
    const preview = root.querySelector('[data-audio-preview]');
    const wave = root.querySelector('[data-audio-wave]');
    const timeLabel = root.querySelector('[data-audio-time]');
    const statusLabel = root.querySelector('[data-audio-status]');
    const trimWrap = root.querySelector('[data-audio-trim]');
    const trimStart = root.querySelector('[data-trim-start]');
    const trimEnd = root.querySelector('[data-trim-end]');
    const errorLabel = root.querySelector('[data-audio-error]');
    const context = wave.getContext('2d');

    let recorder = null;
    let stream = null;
    let chunks = [];
    let audioContext = null;
    let analyser = null;
    let sourceNode = null;
    let animation = 0;
    let timer = 0;
    let recordingStartedAt = 0;
    let elapsedBeforeCurrentRun = 0;
    let paused = false;
    let durationMs = 0;
    let currentWaveform = [];
    let previewUrl = '';
    let currentTrimStart = 0;
    let currentTrimEnd = 0;

    function formatTime(ms) {
        const total = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(total / 60).toString().padStart(2, '0');
        const seconds = (total % 60).toString().padStart(2, '0');
        return minutes + ':' + seconds;
    }

    function currentElapsed() {
        if (!recorder || recorder.state !== 'recording') return elapsedBeforeCurrentRun;
        return elapsedBeforeCurrentRun + (performance.now() - recordingStartedAt);
    }

    function updateClock() {
        timeLabel.textContent = formatTime(currentElapsed());
    }

    function setError(message) {
        errorLabel.textContent = message;
        errorLabel.hidden = !message;
    }

    function setRecordingUi(state) {
        startButton.hidden = state !== 'ready';
        pauseButton.hidden = state !== 'recording';
        resumeButton.hidden = state !== 'paused';
        stopButton.hidden = state !== 'recording' && state !== 'paused';
        deleteButton.hidden = state !== 'preview';
        preview.hidden = state !== 'preview';
        trimWrap.hidden = state !== 'preview';
        if (state === 'ready') statusLabel.textContent = 'جاهز للتسجيل';
        if (state === 'recording') statusLabel.textContent = 'جاري التسجيل';
        if (state === 'paused') statusLabel.textContent = 'التسجيل متوقف مؤقتًا';
        if (state === 'preview') statusLabel.textContent = 'معاينة التسجيل قبل النشر';
    }

    function drawBars(values) {
        const width = wave.width;
        const height = wave.height;
        context.clearRect(0, 0, width, height);
        const bars = values.length ? values : Array.from({ length: 52 }, function (_, index) {
            return 0.12 + ((index * 17) % 31) / 50;
        });
        const gap = 5;
        const barWidth = Math.max(2, (width - gap * (bars.length - 1)) / bars.length);
        bars.forEach(function (value, index) {
            const barHeight = Math.max(5, Math.min(0.95, value) * height);
            const x = index * (barWidth + gap);
            const y = (height - barHeight) / 2;
            context.fillStyle = '#2563eb';
            context.globalAlpha = 0.45 + Math.min(0.55, value);
            context.beginPath();
            context.roundRect(x, y, barWidth, barHeight, Math.min(4, barWidth / 2));
            context.fill();
        });
        context.globalAlpha = 1;
    }

    function animateWave() {
        if (!analyser) return;
        const samples = new Uint8Array(analyser.fftSize);
        const values = [];
        analyser.getByteTimeDomainData(samples);
        const bucketSize = Math.floor(samples.length / 52);
        for (let i = 0; i < 52; i++) {
            let total = 0;
            for (let j = 0; j < bucketSize; j++) {
                total += Math.abs(samples[i * bucketSize + j] - 128) / 128;
            }
            values.push(Math.max(0.08, total / bucketSize));
        }
        currentWaveform = values;
        drawBars(values);
        animation = requestAnimationFrame(animateWave);
    }

    async function makeWaveform(blob) {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return currentWaveform;
            const temp = new AudioContextClass();
            const buffer = await temp.decodeAudioData(await blob.arrayBuffer());
            const data = buffer.getChannelData(0);
            const bars = 64;
            const step = Math.max(1, Math.floor(data.length / bars));
            const values = [];
            for (let i = 0; i < bars; i++) {
                let max = 0;
                for (let j = i * step; j < Math.min(data.length, (i + 1) * step); j++) {
                    max = Math.max(max, Math.abs(data[j]));
                }
                values.push(Math.max(0.08, max));
            }
            await temp.close();
            return values;
        } catch (_) {
            return currentWaveform.length ? currentWaveform : [];
        }
    }

    async function startRecording() {
        setError('');
        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            setError('التسجيل الصوتي يحتاج متصفحًا يدعم MediaRecorder واتصال HTTPS أو localhost.');
            return;
        }
        if (!window.ZayanComposer) {
            setError('تعذر تجهيز المسودة. حدّث الصفحة وحاول مرة أخرى.');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
            });
            const mimeCandidates = [
                'audio/webm;codecs=opus',
                'audio/ogg;codecs=opus',
                'audio/mp4',
                'audio/webm'
            ];
            const mimeType = mimeCandidates.find(function (mime) {
                return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(mime);
            });
            recorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);
            chunks = [];
            elapsedBeforeCurrentRun = 0;
            recordingStartedAt = performance.now();
            paused = false;

            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioContext = new AudioContextClass();
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 512;
                sourceNode = audioContext.createMediaStreamSource(stream);
                sourceNode.connect(analyser);
                animateWave();
            }

            recorder.addEventListener('dataavailable', function (event) {
                if (event.data && event.data.size > 0) chunks.push(event.data);
            });
            recorder.addEventListener('stop', async function () {
                cancelAnimationFrame(animation);
                clearInterval(timer);
                durationMs = Math.max(0, elapsedBeforeCurrentRun);
                const blob = new Blob(chunks, { type: recorder.mimeType || mimeType || 'audio/webm' });
                if (!blob.size) {
                    setError('التسجيل فارغ. حاول مرة أخرى.');
                    resetRecorder(false);
                    return;
                }
                currentWaveform = await makeWaveform(blob);
                drawBars(currentWaveform);
                currentTrimStart = 0;
                currentTrimEnd = durationMs;
                trimStart.value = '0';
                trimEnd.value = '100';
                trimStart.max = '100';
                trimEnd.max = '100';
                setPreview(blob);
                setRecordingUi('preview');
                if (window.ZayanComposer) {
                    await window.ZayanComposer.addAudioBlob(blob, durationMs, 0, durationMs, currentWaveform);
                }
                if (audioContext && audioContext.state !== 'closed') await audioContext.close().catch(function () {});
                audioContext = null;
                analyser = null;
                sourceNode = null;
                stopTracks();
            });
            recorder.start(1000);
            timer = window.setInterval(updateClock, 200);
            setRecordingUi('recording');
        } catch (error) {
            stopTracks();
            setError(error && error.name === 'NotAllowedError'
                ? 'اسمح باستخدام الميكروفون من إعدادات المتصفح ثم حاول مرة أخرى.'
                : 'تعذر بدء التسجيل. تأكد من اتصال الميكروفون وجرب مرة أخرى.');
        }
    }

    function pauseRecording() {
        if (!recorder || recorder.state !== 'recording') return;
        elapsedBeforeCurrentRun += performance.now() - recordingStartedAt;
        recorder.pause();
        paused = true;
        cancelAnimationFrame(animation);
        clearInterval(timer);
        updateClock();
        setRecordingUi('paused');
    }

    function resumeRecording() {
        if (!recorder || recorder.state !== 'paused') return;
        recordingStartedAt = performance.now();
        recorder.resume();
        paused = false;
        timer = window.setInterval(updateClock, 200);
        if (analyser) animateWave();
        setRecordingUi('recording');
    }

    function stopTracks() {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        stream = null;
    }

    function setPreview(blobOrUrl) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        if (typeof blobOrUrl === 'string') {
            preview.src = blobOrUrl;
            previewUrl = '';
        } else {
            previewUrl = URL.createObjectURL(blobOrUrl);
            preview.src = previewUrl;
        }
        preview.hidden = false;
    }

    function resetRecorder(removeFromDraft) {
        cancelAnimationFrame(animation);
        clearInterval(timer);
        if (recorder && recorder.state !== 'inactive') {
            try { recorder.stop(); } catch (_) {}
        }
        stopTracks();
        if (audioContext && audioContext.state !== 'closed') audioContext.close().catch(function () {});
        audioContext = null;
        analyser = null;
        sourceNode = null;
        recorder = null;
        chunks = [];
        durationMs = 0;
        elapsedBeforeCurrentRun = 0;
        currentTrimStart = 0;
        currentTrimEnd = 0;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        preview.removeAttribute('src');
        preview.load();
        timeLabel.textContent = '00:00';
        drawBars([]);
        trimStart.value = '0';
        trimEnd.value = '100';
        setRecordingUi('ready');
        if (removeFromDraft && window.ZayanComposer) window.ZayanComposer.removeAudio();
    }

    function bindExistingAudio() {
        const item = window.ZayanComposer ? window.ZayanComposer.getAudio() : null;
        if (!item) return;
        setPreview(item.url);
        const meta = window.ZayanComposer.getAudioMeta ? window.ZayanComposer.getAudioMeta() : null;
        durationMs = Number(meta?.duration_ms || item.duration_ms || 0);
        currentTrimStart = Number(meta?.trim_start_ms || 0);
        currentTrimEnd = Number(meta?.trim_end_ms || durationMs);
        timeLabel.textContent = formatTime(durationMs);
        trimStart.value = String(durationMs ? currentTrimStart / durationMs * 100 : 0);
        trimEnd.value = String(durationMs ? currentTrimEnd / durationMs * 100 : 100);
        currentWaveform = Array.isArray(meta?.waveform) ? meta.waveform : [];
        drawBars(currentWaveform);
        setRecordingUi('preview');
    }

    function updateTrim() {
        const startPct = Math.min(99, Number(trimStart.value || 0));
        const endPct = Math.max(startPct + 1, Number(trimEnd.value || 100));
        trimEnd.value = String(Math.min(100, endPct));
        currentTrimStart = Math.round(durationMs * startPct / 100);
        currentTrimEnd = Math.round(durationMs * Number(trimEnd.value) / 100);
        if (currentTrimEnd <= currentTrimStart) currentTrimEnd = Math.min(durationMs, currentTrimStart + 100);
        window.ZayanComposer?.updateAudioTrim(currentTrimStart, currentTrimEnd, durationMs);
    }

    startButton.addEventListener('click', startRecording);
    pauseButton.addEventListener('click', pauseRecording);
    resumeButton.addEventListener('click', resumeRecording);
    stopButton.addEventListener('click', function () {
        if (!recorder || recorder.state === 'inactive') return;
        if (recorder.state === 'recording') elapsedBeforeCurrentRun += performance.now() - recordingStartedAt;
        clearInterval(timer);
        recorder.stop();
    });
    deleteButton.addEventListener('click', function () { resetRecorder(true); });
    trimStart.addEventListener('input', updateTrim);
    trimEnd.addEventListener('input', updateTrim);
    preview.addEventListener('timeupdate', function () {
        if (preview.currentTime * 1000 >= currentTrimEnd && currentTrimEnd > 0) {
            preview.pause();
            preview.currentTime = currentTrimStart / 1000;
        }
    });
    preview.addEventListener('play', function () {
        if (currentTrimStart > 0 && preview.currentTime * 1000 < currentTrimStart) {
            preview.currentTime = currentTrimStart / 1000;
        }
    });
    window.addEventListener('zayan-audio-removed', function () {
        resetRecorder(false);
    });
    window.addEventListener('zayan-composer-restored', bindExistingAudio);
    setRecordingUi('ready');
    drawBars([]);
    window.ZayanAudioRecorder = { refresh: bindExistingAudio };
})();
