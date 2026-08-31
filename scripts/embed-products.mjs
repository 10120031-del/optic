/**
 * Turns product descriptions into sentence-transformer embeddings.
 *
 * Reads {"model": "...", "documents": ["...", ...]} as JSON on stdin and
 * writes {"model": "...", "dimensions": 384, "vectors": [[...], ...]} on
 * stdout, one vector per document, in the same order.
 *
 * Deliberately dumb about the catalog: it knows nothing about frames,
 * lenses or the database. App\Services\CatalogEmbedder decides what text
 * describes a product and where the vectors go; this file only runs the
 * model. That keeps the schema in PHP, where the rest of the app's schema
 * lives, and means this script needs no database driver.
 *
 * Run through `php artisan catalog:embed` rather than directly.
 *
 * The model is all-MiniLM-L6-v2: six transformer layers, 384 dimensions,
 * trained on roughly a billion sentence pairs for semantic similarity. It
 * is downloaded once into node_modules/@huggingface/transformers/.cache on
 * first run, the same way npm install fetches the MediaPipe face landmarker
 * into public/mediapipe.
 */
import { pipeline, env } from '@huggingface/transformers';

// The first run downloads the model into the package's own cache; every run
// after that is offline. Set TRANSFORMERS_OFFLINE=1 in CI once the cache is
// warm to make an unexpected download a hard failure rather than a slow build.
env.allowRemoteModels = process.env.TRANSFORMERS_OFFLINE !== '1';

const DEFAULT_MODEL = 'Xenova/all-MiniLM-L6-v2';

/** Sentences per forward pass. Keeps peak memory flat on a large catalog. */
const BATCH_SIZE = 32;

const readStdin = async () => {
    const chunks = [];
    for await (const chunk of process.stdin) chunks.push(chunk);
    return Buffer.concat(chunks).toString('utf8');
};

const fail = (message) => {
    process.stderr.write(`${message}\n`);
    process.exit(1);
};

const raw = await readStdin();

if (!raw.trim()) fail('[embed] nothing on stdin — expected a JSON payload.');

let payload;
try {
    payload = JSON.parse(raw);
} catch (error) {
    fail(`[embed] stdin was not valid JSON: ${error.message}`);
}

const documents = payload.documents ?? [];
const modelName = payload.model ?? DEFAULT_MODEL;

if (!Array.isArray(documents) || documents.length === 0) {
    fail('[embed] payload.documents must be a non-empty array.');
}

let extract;
try {
    // q8 quantization: ~23MB instead of ~87MB, with no meaningful loss on
    // sentence similarity, so a CI checkout stays cheap.
    extract = await pipeline('feature-extraction', modelName, { dtype: 'q8' });
} catch (error) {
    fail(`[embed] could not load model "${modelName}": ${error.message}`);
}

const vectors = [];

for (let offset = 0; offset < documents.length; offset += BATCH_SIZE) {
    const batch = documents.slice(offset, offset + BATCH_SIZE);

    // Mean pooling over token embeddings, then L2 normalization — the
    // pairing all-MiniLM-L6-v2 was trained with. Normalizing here is what
    // lets PHP treat cosine similarity as a plain dot product.
    const output = await extract(batch, { pooling: 'mean', normalize: true });

    const [rows, dimensions] = output.dims;

    for (let row = 0; row < rows; row++) {
        vectors.push(
            Array.from(output.data.slice(row * dimensions, (row + 1) * dimensions))
        );
    }

    process.stderr.write(
        `[embed] ${Math.min(offset + BATCH_SIZE, documents.length)}/${documents.length}\n`
    );
}

process.stdout.write(
    JSON.stringify({
        model: modelName,
        dimensions: vectors[0]?.length ?? 0,
        vectors,
    })
);
