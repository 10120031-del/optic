<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a cart/order eyeglass line back to the customer's prescription
     * record on file, for the paper trail. Nullable: a customer can still
     * key in numbers by hand at checkout without a saved prescription.
     */
    public function up(): void
    {
        Schema::table('cart_eyeglasses', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('lens_id')
                ->constrained('prescriptions')->nullOnDelete();
        });

        Schema::table('order_eyeglasses', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('lens_id')
                ->constrained('prescriptions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_eyeglasses', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropColumn('prescription_id');
        });

        Schema::table('order_eyeglasses', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropColumn('prescription_id');
        });
    }
};
