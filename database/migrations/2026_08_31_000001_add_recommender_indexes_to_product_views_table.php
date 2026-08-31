<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The recommender's co-view signal (App\Services\Recommender) joins
     * product_views to itself on the visitor — user_id for signed-in
     * shoppers, session_id for guests — and only over a recent window.
     *
     * The table was indexed for "views of this product", which answers the
     * trends dashboard but leaves that self-join scanning. These two
     * composites index the other direction, "everything this visitor looked
     * at lately", which is the half the join actually needs.
     */
    public function up(): void
    {
        Schema::table('product_views', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_views', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['session_id', 'created_at']);
        });
    }
};
