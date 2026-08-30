<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshots catalog prices/names so order history stays accurate if catalog changes.
     */
    public function up(): void
    {
        Schema::create('order_eyeglasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('frame_id')->nullable()->constrained('frames')->nullOnDelete();
            $table->foreignId('lens_id')->nullable()->constrained('lenses')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            // Snapshots
            $table->string('frame_name');
            $table->string('frame_brand')->nullable();
            $table->string('lens_name');
            $table->decimal('frame_unit_price', 10, 2);
            $table->decimal('lens_unit_price', 10, 2);
            $table->decimal('features_unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);

            // Prescription snapshot
            $table->decimal('left_sphere', 5, 2)->nullable();
            $table->decimal('left_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->decimal('left_add', 4, 2)->nullable();
            $table->decimal('right_sphere', 5, 2)->nullable();
            $table->decimal('right_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();
            $table->decimal('right_add', 4, 2)->nullable();
            $table->decimal('pd', 4, 1)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_eyeglasses');
    }
};
