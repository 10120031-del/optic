<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A return/exchange request against a delivered order. Eyewear has an
     * unusually high return rate since customers can't try frames on
     * before buying — this is the record that tracks the request through
     * to a refund or a replacement order.
     */
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['return', 'exchange']);
            $table->enum('reason', [
                'wrong_prescription',
                'wrong_size_fit',
                'damaged_or_defective',
                'not_as_described',
                'changed_mind',
                'other',
            ]);
            $table->text('reason_details')->nullable();
            $table->enum('status', [
                'requested',
                'approved',
                'rejected',
                'item_received',
                'refunded',
                'exchanged',
            ])->default('requested');
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->foreignId('exchange_order_id')->nullable()->constrained('orders')->nullOnDelete()
                ->comment('The replacement order created for an approved exchange');
            $table->text('staff_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
