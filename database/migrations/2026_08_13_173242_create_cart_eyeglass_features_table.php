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
        Schema::create('cart_eyeglass_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_eyeglass_id')->constrained('cart_eyeglasses')->cascadeOnDelete();
            $table->foreignId('lens_feature_id')->constrained('lens_features')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cart_eyeglass_id', 'lens_feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_eyeglass_features');
    }
};
