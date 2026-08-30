<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which frames are recommended for which face shapes. Curated by the
     * shop owner (or a styling rule you seed once, e.g. "round face ->
     * square/rectangle/geometric frames") and consumed by the face-scan AI
     * recommender: it classifies the uploaded photo into a face_shape, then
     * queries frames through this pivot.
     */
    public function up(): void
    {
        Schema::create('frame_face_shape', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_id')->constrained('frames')->cascadeOnDelete();
            $table->foreignId('face_shape_id')->constrained('face_shapes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['frame_id', 'face_shape_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frame_face_shape');
    }
};
