/**
 * Camera access for the face scanner.
 *
 * Nothing here records or uploads anything — the stream feeds the local
 * MediaPipe measurement and is torn down as soon as the scan finishes.
 */

/**
 * getUserMedia is only exposed in a secure context, so the camera is
 * unavailable over plain HTTP even though the rest of the page works.
 * localhost counts as secure, which is why this works in local development
 * but would quietly vanish on an http:// deployment.
 */
export function isSupported() {
    return Boolean(navigator.mediaDevices?.getUserMedia) && window.isSecureContext;
}

/**
 * Resolution matters more here than for most camera features: the millimetre
 * scale is derived from the iris, which is only ~15px wide on a 480p stream.
 * At that size a single pixel of landmark error is several percent of the
 * final PD, so ask for 1080p and let the browser fall back if it can't.
 */
export async function start(video) {
    let stream;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });
    } catch (error) {
        throw new Error(describe(error));
    }

    video.srcObject = stream;
    video.setAttribute('playsinline', ''); // iOS won't autoplay inline without it
    video.muted = true;

    await video.play();

    // videoWidth stays 0 until the first frame's metadata lands, and the
    // measurement needs real dimensions.
    if (!video.videoWidth) {
        await new Promise((resolve) => video.addEventListener('loadedmetadata', resolve, { once: true }));
    }

    return stream;
}

export function stop(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

function describe(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera access was blocked. Allow it in your browser’s address bar, or upload a photo instead.';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'No camera found. Upload a photo instead.';
        case 'NotReadableError':
            return 'Your camera is being used by another app. Close it and try again.';
        default:
            return 'Could not start the camera. Upload a photo instead.';
    }
}
