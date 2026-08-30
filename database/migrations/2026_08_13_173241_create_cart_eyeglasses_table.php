<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Configured eyeglass line: frame + lens package + per-eye prescription.
     * Prescription lives here (not on catalog lenses) so catalog stays lean.
     */
    public function up(): void
    {
        Schema::create('cart_eyeglasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('frame_id')->constrained('frames')->restrictOnDelete();
            $table->foreignId('lens_id')->constrained('lenses')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            // Left eye (OS)
            $table->decimal('left_sphere', 5, 2)->nullable();
            $table->decimal('left_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->decimal('left_add', 4, 2)->nullable();

            // Right eye (OD)
            $table->decimal('right_sphere', 5, 2)->nullable();
            $table->decimal('right_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();
            $table->decimal('right_add', 4, 2)->nullable();

            $table->decimal('pd', 4, 1)->nullable()->comment('Pupillary distance in mm');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_eyeglasses');
    }
};
