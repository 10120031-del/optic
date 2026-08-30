<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Multiple images per frame (front, side, angle, on-model). is_primary
     * marks the one used in listing/search cards and grids.
     */
    public function up(): void
    {
        Schema::create('frame_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_id')->constrained('frames')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['frame_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frame_images');
    }
};
