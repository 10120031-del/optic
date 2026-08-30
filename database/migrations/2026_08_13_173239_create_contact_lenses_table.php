<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catalog product. Customer prescription (power, etc.) is stored on cart/order lines.
     */
    public function up(): void
    {
        Schema::create('contact_lenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand');
            $table->string('sku')->unique();
            $table->enum('type', [
                'daily',
                'weekly',
                'biweekly',
                'monthly',
                'yearly',
            ]);
            $table->enum('material', [
                'hydrogel',
                'silicone_hydrogel',
            ]);
            $table->string('color')->nullable();
            $table->decimal('diameter', 4, 2)->nullable()->comment('DIA in mm');
            $table->decimal('base_curve', 4, 2)->nullable()->comment('BC in mm');
            $table->unsignedSmallInteger('pack_size')->default(1);
            $table->unsignedSmallInteger('expiry_months')->nullable()->comment('Wear/replacement period in months');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('material');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_lenses');
    }
};
