<?php

namespace App\Services;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\ProductEmbedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Writes the catalog into the sentence transformer's vector space.
 *
 * Two jobs, and the first one matters more than the second:
 *
 *   1. Decide what text describes a product. The model only ever sees the
 *      sentences built here, so this is where the shop's domain knowledge
 *      lives now — the enums spelled out as English, the millimetres, the
 *      face shapes an optician tagged the frame with, the cost per lens.
 *      Feed it "acetate cat_eye full_rim" and you get a vector for three
 *      tokens of jargon; feed it the sentence a shop assistant would say
 *      and you get a vector for the product.
 *   2. Run scripts/embed-products.mjs over those sentences and store what
 *      comes back.
 *
 * Only products whose text actually changed are re-embedded, so a re-run
 * after editing one frame costs one forward pass, not the whole catalog.
 */
class CatalogEmbedder
{
    public const MODEL = 'Xenova/all-MiniLM-L6-v2';

    /** Cache key for the in-memory similarity matrix the Recommender reads. */
    public const CACHE_KEY = 'catalog-embeddings:v1';

    /** Inference on a cold model is slow; a big catalog must not time out. */
    private const TIMEOUT_SECONDS = 900;

    /**
     * Embed everything in the catalog that needs it.
     *
     * @param  bool  $force  re-embed even products whose text is unchanged
     * @param  (callable(string): void)|null  $progress
     * @return array{embedded: int, skipped: int}
     */
    public function embedCatalog(bool $force = false, ?callable $progress = null): array
    {
        $products = collect()
            ->concat(Frame::all())
            ->concat(ContactLens::all());

        return $this->embed($products, $force, $progress);
    }

    /**
     * @param  Collection<int, Frame|ContactLens>  $products
     * @param  (callable(string): void)|null  $progress
     * @return array{embedded: int, skipped: int}
     */
    public function embed(Collection $products, bool $force = false, ?callable $progress = null): array
    {
        $existing = ProductEmbedding::all()
            ->keyBy(fn (ProductEmbedding $row) => $row->embeddable_type.':'.$row->embeddable_id);

        $pending = [];
        $skipped = 0;

        foreach ($products as $product) {
            $document = $this->describe($product);
            $hash = hash('sha256', $document);
            $key = $product->getMorphClass().':'.$product->getKey();
            $current = $existing->get($key);

            // Unchanged text through an unchanged model gives an identical
            // vector, so there is nothing to gain from running it again.
            if (! $force && $current && $current->content_hash === $hash && $current->model === self::MODEL) {
                $skipped++;

                continue;
            }

            $pending[] = ['product' => $product, 'document' => $document, 'hash' => $hash];
        }

        if ($pending === []) {
            $progress && $progress("Nothing to embed — {$skipped} product(s) already current.");

            return ['embedded' => 0, 'skipped' => $skipped];
        }

        $progress && $progress('Embedding '.count($pending).' product(s) with '.self::MODEL.'...');

        $vectors = $this->runModel(array_column($pending, 'document'), $progress);

        foreach ($pending as $index => $item) {
            $vector = $vectors[$index] ?? null;

            if ($vector === null) {
                throw new RuntimeException('The model returned no vector for document '.$index.'.');
            }

            ProductEmbedding::updateOrCreate(
                [
                    'embeddable_type' => $item['product']->getMorphClass(),
                    'embeddable_id' => $item['product']->getKey(),
                ],
                [
                    'model' => self::MODEL,
                    'dimensions' => count($vector),
                    'vector' => ProductEmbedding::encode($vector),
                    'content_hash' => $item['hash'],
                ],
            );
        }

        Cache::forget(self::CACHE_KEY);

        return ['embedded' => count($pending), 'skipped' => $skipped];
    }

    /*
    |--------------------------------------------------------------------------
    | The sentences the model sees
    |--------------------------------------------------------------------------
    */

    /**
     * The written description of a product that gets embedded.
     *
     * Written as prose rather than a field dump because the model was
     * trained on sentences: "a round full-rim eyeglasses frame in tortoise
     * acetate" carries the relationships between those words, while
     * "round|full_rim|tortoise|acetate" is four unrelated tokens.
     */
    public function describe(Frame|ContactLens $product): string
    {
        return $product instanceof Frame
            ? $this->describeFrame($product)
            : $this->describeContactLens($product);
    }

    private function describeFrame(Frame $frame): string
    {
        $sentences = [];

        $sentences[] = sprintf(
            '%s, a %s %s %s frame by %s.',
            $frame->name,
            $this->words($frame->shape) ?: 'classic',
            $this->words($frame->type),
            $this->words($frame->category),
            $frame->brand,
        );

        $sentences[] = sprintf(
            'Made of %s%s.',
            $this->words($frame->material),
            $frame->color ? ' in '.strtolower($frame->color) : '',
        );

        $sentences[] = match ($frame->gender) {
            'kids' => 'Sized for children.',
            'unisex' => 'Suits any wearer.',
            default => sprintf("A %s's frame.", $frame->gender === 'men' ? 'men' : 'women'),
        };

        if ($frame->size) {
            $sentences[] = sprintf('A %s fit.', $this->words($frame->size));
        }

        $sentences[] = sprintf(
            'Lens width %smm, bridge %smm, temple %smm.',
            $this->mm($frame->lens_width),
            $this->mm($frame->bridge_width),
            $this->mm($frame->temple_length),
        );

        if ($frame->weight_grams) {
            $sentences[] = sprintf('Weighs %d grams.', $frame->weight_grams);
        }

        $sentences[] = sprintf('Priced at $%s.', number_format((float) $frame->price, 2));

        // An optician tagged these by hand, which makes them the most
        // trustworthy styling signal the catalog carries.
        $faceShapes = $frame->relationLoaded('faceShapes')
            ? $frame->faceShapes
            : $frame->faceShapes()->get();

        if ($faceShapes->isNotEmpty()) {
            $sentences[] = 'Flattering on '.$this->list($faceShapes->pluck('name')->all()).' face shapes.';
        }

        if ($frame->description) {
            $sentences[] = trim($frame->description);
        }

        return implode(' ', $sentences);
    }

    private function describeContactLens(ContactLens $lens): string
    {
        $sentences = [];

        $sentences[] = sprintf(
            '%s, %s contact lenses by %s.',
            $lens->name,
            match ($lens->type) {
                'daily' => 'daily disposable',
                'weekly' => 'weekly replacement',
                'biweekly' => 'two-week replacement',
                'monthly' => 'monthly replacement',
                'yearly' => 'yearly replacement',
                default => $this->words($lens->type),
            },
            $lens->brand,
        );

        $sentences[] = sprintf(
            'Made from %s%s.',
            $this->words($lens->material),
            $lens->color ? ', tinted '.strtolower($lens->color) : ', clear and untinted',
        );

        if ($lens->base_curve || $lens->diameter) {
            $sentences[] = trim(sprintf(
                '%s%s',
                $lens->base_curve ? sprintf('Base curve %smm. ', $this->mm($lens->base_curve)) : '',
                $lens->diameter ? sprintf('Diameter %smm.', $this->mm($lens->diameter)) : '',
            ));
        }

        // Per-lens cost is the number a wearer actually compares on, and it
        // is not in any column — a 30-pack at $30 and a 6-pack at $18 look
        // similarly priced until you divide.
        $packSize = max(1, (int) $lens->pack_size);
        $sentences[] = sprintf(
            '%d lenses per box at $%s, about $%s per lens.',
            $packSize,
            number_format((float) $lens->price, 2),
            number_format((float) $lens->price / $packSize, 2),
        );

        if ($lens->description) {
            $sentences[] = trim($lens->description);
        }

        return implode(' ', $sentences);
    }

    /*
    |--------------------------------------------------------------------------
    | Running the model
    |--------------------------------------------------------------------------
    */

    /**
     * Hand the documents to Node, get vectors back.
     *
     * @param  array<int, string>  $documents
     * @param  (callable(string): void)|null  $progress
     * @return array<int, array<int, float>>
     */
    private function runModel(array $documents, ?callable $progress = null): array
    {
        $process = new Process(
            ['node', 'scripts/embed-products.mjs'],
            base_path(),
            null,
            json_encode(['model' => self::MODEL, 'documents' => $documents]),
            self::TIMEOUT_SECONDS,
        );

        // The script reports batch progress on stderr and returns JSON on
        // stdout, so the two streams stay cleanly separated.
        $process->run(function (string $type, string $buffer) use ($progress) {
            if ($type === Process::ERR && $progress) {
                $progress(trim($buffer));
            }
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                "Embedding failed. Is Node installed and `npm install` run?\n"
                .trim($process->getErrorOutput())
            );
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded) || ! isset($decoded['vectors'])) {
            throw new RuntimeException('The embedding script returned unreadable output.');
        }

        return $decoded['vectors'];
    }

    /*
    |--------------------------------------------------------------------------
    | Small helpers
    |--------------------------------------------------------------------------
    */

    /** `silicone_hydrogel` -> `silicone hydrogel`, `cat_eye` -> `cat-eye`. */
    private function words(?string $value): string
    {
        return match ($value) {
            null, '' => '',
            'cat_eye' => 'cat-eye',
            'full_rim' => 'full-rim',
            'semi_rimless' => 'semi-rimless',
            'high_index' => 'high-index',
            default => str_replace('_', ' ', $value),
        };
    }

    /** Trims the decimal noise off a measurement: 52.00 -> 52, 8.60 -> 8.6. */
    private function mm(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** @param  array<int, string>  $items */
    private function list(array $items): string
    {
        $items = array_map('strtolower', $items);

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
