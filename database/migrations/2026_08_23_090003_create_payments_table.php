<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One order can have more than one payment attempt (a failed card retry,
     * a later refund), so this is its own table rather than columns on
     * orders. The gateway reference (transaction_id) is what you reconcile
     * against Stripe/PayPal/etc.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('method', [
                'card',
                'paypal',
                'cash_on_delivery',
                'bank_transfer',
            ]);
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'refunded',
            ])->default('pending');
            $table->string('transaction_id')->nullable()->comment('Gateway reference, e.g. Stripe payment intent id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
