import { FaceLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

/**
 * Face measurement from a photo, entirely in the browser.
 *
 * MediaPipe's 478-point face landmarker includes a ring of points around each
 * iris. The horizontal iris diameter is ~11.7mm across essentially the whole
 * adult population, which makes it the one reliable ruler present in every
 * selfie — that constant is what converts pixel distances to millimetres with
 * no reference card and no known camera distance.
 *
 * Kept free of DOM wiring so it can be exercised on its own; face-scan.js is
 * the page-level consumer.
 */

export const IRIS_DIAMETER_MM = 11.7;

// Landmark indices into the 478-point face mesh.
const L = {
    irisLeft: 468, // iris centres (image-space left/right, not anatomical)
    irisRight: 473,
    irisLeftRing: [469, 470, 471, 472],
    irisRightRing: [474, 475, 476, 477],
    bridge: 168, // nose bridge, between the eyes
    noseTip: 1,
    foreheadTop: 10,
    chin: 152,
    cheekL: 234, // widest point of the face
    cheekR: 454,
    jawL: 172,
    jawR: 397,
    browL: 54, // forehead width
    browR: 284,
};

const sub = (a, b) => ({ x: a.x - b.x, y: a.y - b.y });
const dot = (a, b) => a.x * b.x + a.y * b.y;
const norm = (a) => Math.hypot(a.x, a.y);
const unit = (a) => {
    const n = norm(a) || 1;
    return { x: a.x / n, y: a.y / n };
};
const round = (n, places = 1) => Math.round(n * 10 ** places) / 10 ** places;

/**
 * Measurements are taken in the *face's* own frame, not the image's.
 *
 * The eye line defines the face's horizontal axis, so a tilted (rolled) head
 * measures the same as a level one. Projecting onto that axis and its
 * perpendicular is what makes this robust to the way people actually hold
 * their phone.
 */
export function measure(landmarks, width, height) {
    const p = (i) => ({ x: landmarks[i].x * width, y: landmarks[i].y * height });

    const irisL = p(L.irisLeft);
    const irisR = p(L.irisRight);

    // Face-local axes.
    const axis = unit(sub(irisR, irisL)); // horizontal
    const perp = { x: -axis.y, y: axis.x }; // vertical

    const along = (a, b) => Math.abs(dot(sub(p(a), p(b)), axis));
    const across = (a, b) => Math.abs(dot(sub(p(a), p(b)), perp));

    // Iris diameter in pixels. The ring points sit on a circle, so the widest
    // pairwise distance is the diameter whatever order the indices come in.
    // Averaging both eyes smooths out per-eye landmark jitter.
    const ringDiameter = (centre, ring) => {
        const pts = [p(centre), ...ring.map(p)];
        let max = 0;
        for (const a of pts) for (const b of pts) max = Math.max(max, norm(sub(a, b)));
        return max;
    };
    const irisPx =
        (ringDiameter(L.irisLeft, L.irisLeftRing) + ringDiameter(L.irisRight, L.irisRightRing)) / 2;

    if (!Number.isFinite(irisPx) || irisPx <= 0) return null;

    const mm = (px) => (px / irisPx) * IRIS_DIAMETER_MM;

    // Pupillary distance. Binocular is the straight iris-centre-to-centre
    // distance; monocular is each pupil's offset from the nose bridge along
    // the eye line, which is what an optician records because faces are not
    // symmetric.
    const pd = norm(sub(irisR, irisL));
    const pdLeft = Math.abs(dot(sub(irisL, p(L.bridge)), axis));
    const pdRight = Math.abs(dot(sub(irisR, p(L.bridge)), axis));

    // Head-turn check. A face turned away from the camera foreshortens one
    // side, which corrupts every width measurement — better to ask for
    // another photo than to return a confidently wrong number.
    const yawRatio = along(L.noseTip, L.cheekL) / (along(L.noseTip, L.cheekR) || 1);

    return {
        face_length_mm: round(mm(across(L.foreheadTop, L.chin))),
        cheekbone_width_mm: round(mm(along(L.cheekL, L.cheekR))),
        jaw_width_mm: round(mm(along(L.jawL, L.jawR))),
        forehead_width_mm: round(mm(along(L.browL, L.browR))),
        pd_mm: round(mm(pd)),
        pd_left_mm: round(mm(pdLeft)),
        pd_right_mm: round(mm(pdRight)),
        yaw_ratio: round(yawRatio, 3),
    };
}

// ---------------------------------------------------------------------------
// Model loading. Deferred until first use — the model is ~3.7MB and most
// visitors to the page never scan anything.
// ---------------------------------------------------------------------------

let landmarkerPromise = null;

export function getLandmarker() {
    if (!landmarkerPromise) {
        landmarkerPromise = FilesetResolver.forVisionTasks('/mediapipe/wasm').then((fileset) =>
            FaceLandmarker.createFromOptions(fileset, {
                baseOptions: { modelAssetPath: '/mediapipe/face_landmarker.task' },
                runningMode: 'IMAGE',
                numFaces: 1,
                outputFaceBlendshapes: false,
                outputFacialTransformationMatrixes: false,
            })
        );
    }
    return landmarkerPromise;
}

/**
 * Detect and measure a single still. Returns null when there's no usable
 * face, so callers can decide whether that's an error or just a frame to
 * skip during live preview.
 */
export async function measureSource(source, width, height) {
    const landmarker = await getLandmarker();
    const landmarks = landmarker.detect(source).faceLandmarks?.[0];

    return landmarks ? measure(landmarks, width, height) : null;
}

/**
 * Rejects measurements we shouldn't act on. Throws with copy meant for the
 * customer rather than the console.
 */
export function assertUsable(measurements) {
    if (!measurements) {
        throw new Error(
            'No face detected. Try a brighter, straight-on shot with your whole face in frame.'
        );
    }

    if (measurements.yaw_ratio < 0.82 || measurements.yaw_ratio > 1.22) {
        throw new Error(
            'Your head looks turned to one side. Face the camera straight on and try again.'
        );
    }

    return measurements;
}

export async function measureImage(image) {
    return assertUsable(await measureSource(image, image.naturalWidth, image.naturalHeight));
}

const median = (values) => {
    const sorted = [...values].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);

    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
};

/**
 * Measure from a live camera by sampling several frames and taking the median
 * of each value.
 *
 * A single video frame is a worse input than an uploaded photo: the stream is
 * lower resolution and any small movement blurs the iris, which is the thing
 * the whole millimetre scale depends on. Landmark jitter between frames is
 * largely independent, so the median across a short burst is markedly steadier
 * than any one frame — and unlike a mean, one badly blurred frame can't drag
 * the result.
 */
export async function measureVideo(video, { samples = 9, intervalMs = 55 } = {}) {
    const width = video.videoWidth;
    const height = video.videoHeight;

    if (!width || !height) {
        throw new Error('The camera is still starting up. Give it a moment and try again.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });

    const frames = [];

    for (let i = 0; i < samples; i++) {
        context.drawImage(video, 0, 0, width, height);
        const measurements = await measureSource(canvas, width, height);
        if (measurements) frames.push(measurements);

        if (i < samples - 1) {
            await new Promise((resolve) => setTimeout(resolve, intervalMs));
        }
    }

    // Demand a clear majority of usable frames. A face that only registers
    // intermittently means the framing or lighting isn't good enough to
    // trust whatever did come through.
    if (frames.length < Math.ceil(samples * 0.6)) {
        throw new Error(
            'Could not get a steady read. Hold still, make sure your face is well lit, and try again.'
        );
    }

    const merged = {};
    for (const key of Object.keys(frames[0])) {
        merged[key] = round(median(frames.map((frame) => frame[key])), key === 'yaw_ratio' ? 3 : 1);
    }

    return assertUsable(merged);
}
