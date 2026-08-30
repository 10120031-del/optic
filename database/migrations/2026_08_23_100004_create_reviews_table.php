<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Polymorphic so both frames and contact_lenses can carry reviews.
     * is_verified_purchase is set from order_id when present, so the
     * storefront can badge "verified purchase" — a strong trust signal
     * for frames, where seeing how they actually look on someone matters
     * more than for most product categories (see review_images).
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewable_type');
            $table->unsignedBigInteger('reviewable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating')->comment('1-5');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false)->comment('Moderation gate before it shows publicly');
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index('is_approved');
            $table->unique(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_one_per_user_per_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
