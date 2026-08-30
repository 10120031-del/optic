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
        Schema::create('order_eyeglass_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_eyeglass_id')->constrained('order_eyeglasses')->cascadeOnDelete();
            $table->foreignId('lens_feature_id')->nullable()->constrained('lens_features')->nullOnDelete();
            $table->string('feature_name');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_eyeglass_features');
    }
};
