<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data the application cannot run without, and nothing else.
 *
 * DatabaseSeeder is a development fixture: it invents demo accounts with a
 * known password and a placeholder catalogue. Running it against a live shop
 * would hand anyone the admin login. This seeder is the production-safe
 * counterpart — the six face shapes the AI matcher classifies into and the
 * lens add-ons checkout prices, both written with updateOrCreate so it is
 * safe to re-run on every deploy.
 *
 *     php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FaceShapeSeeder::class,
            LensFeatureSeeder::class,
        ]);
    }
}
