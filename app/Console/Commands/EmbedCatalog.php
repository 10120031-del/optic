<?php

namespace App\Console\Commands;

use App\Services\CatalogEmbedder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Builds the vectors the recommender runs on.
 *
 * Part of a deploy, right after `php artisan migrate` and alongside
 * `npm run build` — it needs Node, which the deploy already installs to
 * build the CSS. The storefront never runs this; it only reads what this
 * writes. Products added later through the admin area are embedded by
 * App\Observers\CatalogEmbeddingObserver, so this is only needed for the
 * initial build and after a model change.
 */
class EmbedCatalog extends Command
{
    protected $signature = 'catalog:embed
                            {--force : Re-embed every product, even unchanged ones}';

    protected $description = 'Generate neural embeddings for every frame and contact lens';

    public function handle(CatalogEmbedder $embedder): int
    {
        $this->info('Model: '.CatalogEmbedder::MODEL);

        $started = microtime(true);

        try {
            $result = $embedder->embedCatalog(
                force: (bool) $this->option('force'),
                progress: fn (string $line) => $this->line("  <fg=gray>{$line}</>"),
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Embedded %d product(s), skipped %d unchanged, in %.1fs.',
            $result['embedded'],
            $result['skipped'],
            microtime(true) - $started,
        ));

        if ($result['embedded'] === 0 && $result['skipped'] === 0) {
            $this->warn('The catalog is empty — nothing to embed.');
        }

        return self::SUCCESS;
    }
}
