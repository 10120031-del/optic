<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which optional features can be paired with which lens packages.
     */
    public function up(): void
    {
        Schema::create('lens_lens_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lens_id')->constrained('lenses')->cascadeOnDelete();
            $table->foreignId('lens_feature_id')->constrained('lens_features')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lens_id', 'lens_feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lens_lens_feature');
    }
};
