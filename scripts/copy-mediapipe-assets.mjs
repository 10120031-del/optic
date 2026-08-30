/**
 * Stages the MediaPipe runtime assets into public/mediapipe/.
 *
 * The face scanner needs two things at runtime that aren't part of the Vite
 * bundle: the WASM runtime (shipped inside the npm package) and the face
 * landmarker weights (~3.7MB, downloaded from Google's model store). Both are
 * served from our own origin rather than a CDN, so the customer's browser
 * never announces to a third party that they're using the face scanner.
 *
 * Runs on postinstall, so a fresh clone or a CI deploy gets them
 * automatically. Without this the scan page loads but silently fails to find
 * a model — hence the explicit failure at the bottom.
 */
import { createWriteStream } from 'node:fs';
import { access, cp, mkdir, stat } from 'node:fs/promises';
import { pipeline } from 'node:stream/promises';
import { Readable } from 'node:stream';

const WASM_SRC = 'node_modules/@mediapipe/tasks-vision/wasm';
const DEST = 'public/mediapipe';
const MODEL = `${DEST}/face_landmarker.task`;
const MODEL_URL =
    'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';

const exists = (path) =>
    access(path).then(
        () => true,
        () => false
    );

if (!(await exists(WASM_SRC))) {
    console.error(`[mediapipe] ${WASM_SRC} not found — run npm install first.`);
    process.exit(1);
}

await mkdir(`${DEST}/wasm`, { recursive: true });
await cp(WASM_SRC, `${DEST}/wasm`, { recursive: true });
console.log('[mediapipe] WASM runtime staged.');

// The model is large and immutable, so only fetch it once.
if (await exists(MODEL)) {
    const { size } = await stat(MODEL);
    console.log(`[mediapipe] Model already present (${(size / 1e6).toFixed(1)}MB).`);
} else {
    console.log('[mediapipe] Downloading face landmarker model…');
    const response = await fetch(MODEL_URL);

    if (!response.ok) {
        console.error(`[mediapipe] Download failed: ${response.status} ${response.statusText}`);
        process.exit(1);
    }

    await pipeline(Readable.fromWeb(response.body), createWriteStream(MODEL));
    const { size } = await stat(MODEL);
    console.log(`[mediapipe] Model downloaded (${(size / 1e6).toFixed(1)}MB).`);
}
