<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lookup table for the standard face-shape taxonomy (oval, round, square,
     * heart, diamond, oblong, ...). Seeded once; used both to tag which
     * frame shapes suit which face shapes (see frame_face_shape) and as the
     * output classification of the future face-scan AI feature.
     */
    public function up(): void
    {
        Schema::create('face_shapes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_shapes');
    }
};
