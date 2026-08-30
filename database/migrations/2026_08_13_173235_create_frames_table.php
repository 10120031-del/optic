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
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand');
            $table->string('sku')->unique();
            $table->string('manufactured_in')->nullable();

            // Physical measurements (mm). Used for size filtering and for the
            // future face-scan AI to match frame dimensions against face width.
            $table->decimal('lens_width', 5, 2);
            $table->decimal('lens_height', 5, 2);
            $table->decimal('bridge_width', 5, 2);
            $table->decimal('temple_length', 5, 2);
            $table->decimal('frame_width', 5, 2)->nullable()->comment('Overall front width in mm, temple to temple');
            $table->unsignedSmallInteger('weight_grams')->nullable();

            // Simple bucketed size, derived from frame_width by an admin/import
            // step, so the storefront can offer a one-click Narrow/Medium/Wide filter
            // without the customer needing to reason about millimeters.
            $table->enum('size', ['narrow', 'medium', 'wide'])->nullable();

            $table->text('description')->nullable();
            $table->enum('material', [
                'acetate',
                'metal',
                'titanium',
                'plastic',
                'mixed',
            ]);
            $table->enum('category', [
                'eyeglasses',
                'sunglasses',
                'sports',
            ]);
            $table->enum('type', [
                'full_rim',
                'semi_rimless',
                'rimless',
            ]);

            // Frame outline shape. This is the key attribute the face-shape AI
            // will map against a scanned face (e.g. round faces -> angular frames).
            $table->enum('shape', [
                'round',
                'square',
                'rectangle',
                'oval',
                'cat_eye',
                'aviator',
                'wayfarer',
                'browline',
                'geometric',
                'hexagonal',
            ])->nullable();

            $table->enum('gender', [
                'men',
                'women',
                'unisex',
                'kids',
            ]);
            $table->string('color')->nullable();
            $table->string('color_hex', 7)->nullable()->comment('For swatch display, e.g. #1A1A1A');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Storefront search/filter combos (color, gender, size, category, shape).
            $table->index('gender');
            $table->index('color');
            $table->index('size');
            $table->index('shape');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frames');
    }
};
