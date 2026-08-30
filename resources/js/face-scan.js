import { measureImage, measureVideo } from './face-measure';
import * as camera from './face-camera';

/**
 * Page wiring for the AI Face Match scan.
 *
 * Two ways in — snap one now, or pick an existing photo — both ending in the
 * same place: measurements taken locally, and only those numbers POSTed. The
 * image itself never leaves the browser either way.
 */

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('That file could not be read as an image.'));
        };
        img.src = url;
    });
}

function init() {
    const root = document.querySelector('[data-face-scan]');
    if (!root) return;

    const status = root.querySelector('[data-face-scan-status]');
    const tabs = root.querySelectorAll('[data-face-scan-tab]');
    const panels = root.querySelectorAll('[data-face-scan-panel]');

    const video = root.querySelector('[data-face-scan-video]');
    const captureButton = root.querySelector('[data-face-scan-capture]');
    const cameraTab = root.querySelector('[data-face-scan-tab="camera"]');

    const fileInput = root.querySelector('input[type="file"]');
    const uploadButton = root.querySelector('[data-face-scan-submit]');
    const preview = root.querySelector('[data-face-scan-preview]');

    let stream = null;
    let busy = false;

    const setStatus = (message, tone = 'info') => {
        status.textContent = message;
        status.dataset.tone = tone;
        status.hidden = !message;
    };

    // -- mode switching ----------------------------------------------------

    const showPanel = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.faceScanTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.faceScanPanel !== name;
        });
        setStatus('');
    };

    const openCamera = async () => {
        if (stream) return;
        try {
            setStatus('Starting your camera…');
            stream = await camera.start(video);
            captureButton.disabled = false;
            setStatus('Line your face up in the frame, then capture.');
        } catch (error) {
            captureButton.disabled = true;
            setStatus(error.message, 'error');
        }
    };

    const closeCamera = () => {
        camera.stop(stream);
        stream = null;
        captureButton.disabled = true;
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const name = tab.dataset.faceScanTab;
            showPanel(name);
            name === 'camera' ? openCamera() : closeCamera();
        });
    });

    // Release the camera when the user navigates away or hides the tab —
    // leaving the indicator light on after they've stopped scanning is a
    // trust problem even though nothing is being recorded.
    window.addEventListener('pagehide', closeCamera);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) closeCamera();
    });

    // -- submission --------------------------------------------------------

    const submit = async (measurements) => {
        const response = await fetch(root.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(measurements),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Something went wrong. Please try again.');
        }

        return payload.redirect;
    };

    const run = async (button, measuring) => {
        if (busy) return;
        busy = true;
        button.disabled = true;

        try {
            const measurements = await measuring();
            setStatus('Finding your frames…');
            const redirect = await submit(measurements);
            closeCamera();
            window.location.href = redirect;
        } catch (error) {
            setStatus(error.message, 'error');
            button.disabled = false;
        } finally {
            busy = false;
        }
    };

    captureButton?.addEventListener('click', () =>
        run(captureButton, async () => {
            setStatus('Hold still…');
            return measureVideo(video);
        })
    );

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        setStatus('');
        if (preview) {
            preview.hidden = !file;
            if (file) preview.src = URL.createObjectURL(file);
        }
        uploadButton.disabled = !file;
    });

    root.addEventListener('submit', (event) => {
        event.preventDefault();
        const file = fileInput?.files?.[0];
        if (!file) return;

        run(uploadButton, async () => {
            setStatus('Measuring…');
            return measureImage(await loadImage(file));
        });
    });

    // -- initial mode ------------------------------------------------------

    if (camera.isSupported()) {
        showPanel('camera');
        openCamera();
    } else {
        // No camera, or served over plain HTTP where getUserMedia is blocked.
        cameraTab?.remove();
        showPanel('upload');
    }
}

document.addEventListener('DOMContentLoaded', init);
