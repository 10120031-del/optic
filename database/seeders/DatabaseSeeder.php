<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'first_name' => 'Shop',
            'last_name' => 'Owner',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->call([
            FaceShapeSeeder::class,
            LensFeatureSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
