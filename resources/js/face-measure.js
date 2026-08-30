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
 * Detect and measure in one step. Throws with a message suitable for showing
 * to the customer when the photo isn't usable.
 */
export async function measureImage(image) {
    const landmarker = await getLandmarker();
    const landmarks = landmarker.detect(image).faceLandmarks?.[0];

    if (!landmarks) {
        throw new Error(
            'No face detected. Try a brighter, straight-on photo with your whole face in frame.'
        );
    }

    const measurements = measure(landmarks, image.naturalWidth, image.naturalHeight);

    if (!measurements) {
        throw new Error('Could not locate your eyes clearly. Try a sharper photo.');
    }

    if (measurements.yaw_ratio < 0.82 || measurements.yaw_ratio > 1.22) {
        throw new Error(
            'Your head looks turned to one side. Face the camera straight on and try again.'
        );
    }

    return measurements;
}
