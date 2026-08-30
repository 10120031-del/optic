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
        Schema::create('cart_contact_lenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_lens_id')->constrained('contact_lenses')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            // Customer-selected powers (can differ per eye)
            $table->decimal('left_power', 5, 2)->nullable();
            $table->decimal('right_power', 5, 2)->nullable();
            $table->decimal('left_cylinder', 5, 2)->nullable();
            $table->decimal('right_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_contact_lenses');
    }
};
