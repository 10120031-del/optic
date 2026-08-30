import { measureImage } from './face-measure';

/**
 * Page wiring for the AI Face Match scan.
 *
 * The photo never leaves the browser — face-measure.js runs MediaPipe locally
 * and the only thing POSTed to Laravel is a handful of millimetre
 * measurements. That keeps biometric image data off the server entirely.
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
    const form = document.querySelector('[data-face-scan]');
    if (!form) return;

    const input = form.querySelector('input[type="file"]');
    const button = form.querySelector('[data-face-scan-submit]');
    const status = form.querySelector('[data-face-scan-status]');
    const preview = form.querySelector('[data-face-scan-preview]');

    const setStatus = (message, tone = 'info') => {
        status.textContent = message;
        status.dataset.tone = tone;
        status.hidden = !message;
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        setStatus('');
        if (preview) {
            preview.hidden = !file;
            if (file) preview.src = URL.createObjectURL(file);
        }
        button.disabled = !file;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const file = input.files?.[0];
        if (!file) return;

        button.disabled = true;

        try {
            setStatus('Loading the face model…');
            const image = await loadImage(file);

            setStatus('Measuring…');
            const measurements = await measureImage(image);

            setStatus('Finding your frames…');
            const response = await fetch(form.action, {
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

            window.location.href = payload.redirect;
        } catch (error) {
            setStatus(error.message, 'error');
            button.disabled = false;
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
