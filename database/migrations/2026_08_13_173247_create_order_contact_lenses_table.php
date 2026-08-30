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
        Schema::create('order_contact_lenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_lens_id')->nullable()->constrained('contact_lenses')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);

            // Snapshots
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);

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
        Schema::dropIfExists('order_contact_lenses');
    }
};
