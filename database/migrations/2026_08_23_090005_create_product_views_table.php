<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lightweight polymorphic view log (frames or contact_lenses) feeding
     * the owner's trends dashboard: most-viewed vs. most-sold, view-to-order
     * conversion, etc. Kept intentionally thin (no user-agent/referrer) —
     * this is for merchandising trends, not full web analytics.
     */
    public function up(): void
    {
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->string('viewable_type');
            $table->unsignedBigInteger('viewable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['viewable_type', 'viewable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
