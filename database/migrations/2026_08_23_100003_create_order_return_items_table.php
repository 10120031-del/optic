<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which specific order line(s) a return covers — a return doesn't
     * have to include every item on the order. Polymorphic since a line
     * can be either an order_eyeglasses or order_contact_lenses row.
     */
    public function up(): void
    {
        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->string('returnable_type');
            $table->unsignedBigInteger('returnable_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('condition_notes')->nullable();
            $table->timestamps();

            $table->index(['returnable_type', 'returnable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
    }
};
