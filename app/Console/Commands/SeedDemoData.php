<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoConfig;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Front door for the demo fixtures.
 *
 *     php artisan demo:seed
 *
 * `db:seed --class=DemoSeeder` does the same work; this exists for the two
 * things that wrapper adds. It refuses to run unattended in production unless
 * someone confirms — the seeder deletes and rewrites rows, and a shop's live
 * database is not where you want to discover that. And --embed chains the
 * catalogue embedding afterwards, which the seeder cannot do for itself
 * because it runs with model events muted.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed
                            {--embed : Also regenerate catalogue embeddings, so semantic search and the recommender cover the new products}
                            {--force : Skip the production confirmation}';

    protected $description = 'Fill the database with a shop’s worth of demo data for presentations';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->line('Seeding demo data — this takes a minute or two.');
        $this->newLine();

        $started = microtime(true);

        // Not callSilent: the seeder reports through $this->command, and the
        // table of row counts and the sign-in credentials it prints at the
        // end are the most useful thing this command produces.
        $this->call('db:seed', [
            '--class' => DemoSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->info(sprintf('Done in %.1fs.', microtime(true) - $started));

        if ($this->option('embed')) {
            $this->newLine();
            $this->line('Generating catalogue embeddings...');
            Artisan::call('catalog:embed', [], $this->getOutput());
        }

        return self::SUCCESS;
    }

    /**
     * Guard the one case that would actually hurt: pointing this at a live
     * shop's database. Anywhere else it runs without ceremony.
     */
    private function confirmToProceed(): bool
    {
        if ($this->option('force') || ! app()->environment('production')) {
            return true;
        }

        $this->warn('APP_ENV is production.');
        $this->line('Database: '.DB::connection()->getDatabaseName().' on '.config('database.connections.'.config('database.default').'.host'));
        $this->line('This will delete every account on '.DemoConfig::EMAIL_DOMAIN.' — and everything attached to them — before rebuilding.');

        return $this->confirm('Seed demo data into this database?', false);
    }
}
