<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('material', [
                'plastic',
                'polycarbonate',
                'high_index',
                'trivex',
                'glass',
            ]);
            $table->enum('type', [
                'plano',
                'single_vision',
                'bifocal',
                'progressive',
                'reading',
            ])->comment('plano = no prescription, e.g. for sunglasses-only purchases');
            // Named refractive_index (not index) to avoid the reserved SQL
            // keyword footgun in raw queries/reports.
            $table->decimal('refractive_index', 3, 2)->nullable()->comment('Refractive index, e.g. 1.50, 1.67');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lenses');
    }
};
